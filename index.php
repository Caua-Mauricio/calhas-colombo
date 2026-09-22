<?php
// painel principal do sistema - os numeros do mes, os graficos e
// os ultimos orcamentos que entraram

require_once __DIR__ . '/inclui/auth.php';
exigir_login();

$u = usuario_logado();
$pdo = bd();
$verFinanceiro = pode('ver_financeiro');

// Indicadores do mes corrente
$mesAtual   = date('Y-m');
$mesAnterior = date('Y-m', strtotime('-1 month'));

$st = $pdo->prepare(
    "SELECT
        COUNT(*) AS qtd,
        COALESCE(SUM(valor_total),0) AS total,
        COALESCE(SUM(CASE WHEN status IN ('aprovado','em_producao','concluido') THEN valor_total ELSE 0 END),0) AS fechado,
        COALESCE(SUM(CASE WHEN status IN ('aprovado','em_producao','concluido') THEN 1 ELSE 0 END),0) AS qtd_fechado
     FROM orcamentos
     WHERE DATE_FORMAT(data_orcamento,'%Y-%m') = :mes"
);
$st->execute([':mes' => $mesAtual]);
$mes = $st->fetch();

$st->execute([':mes' => $mesAnterior]);
$anterior = $st->fetch();

/* Variacao percentual do faturamento fechado */
$variacao = 0.0;
if ((float) $anterior['fechado'] > 0) {
    $variacao = (((float) $mes['fechado'] - (float) $anterior['fechado']) / (float) $anterior['fechado']) * 100;
}

/* Taxa de conversao */
$conversao = (int) $mes['qtd'] > 0 ? ((int) $mes['qtd_fechado'] / (int) $mes['qtd']) * 100 : 0;

/* Ticket medio */
$ticket = (int) $mes['qtd_fechado'] > 0 ? (float) $mes['fechado'] / (int) $mes['qtd_fechado'] : 0;

/* Totais gerais */
$totalClientes = (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE ativo = 1")->fetchColumn();
$totalPendentes = (int) $pdo->query(
    "SELECT COUNT(*) FROM orcamentos WHERE status IN ('rascunho','enviado')"
)->fetchColumn();
$gastoInsumosMes = (float) $pdo->query(
    "SELECT COALESCE(SUM(valor_total),0) FROM compras_insumos
     WHERE DATE_FORMAT(data_compra,'%Y-%m') = '" . $mesAtual . "'"
)->fetchColumn();

/* Grafico: faturamento dos ultimos 6 meses */
$meses = [];
for ($i = 5; $i >= 0; $i--) {
    $ref = date('Y-m', strtotime("-$i month"));
    $meses[$ref] = ['rotulo' => ucfirst(strftime_pt($ref)), 'valor' => 0.0];
}

$st = $pdo->query(
    "SELECT DATE_FORMAT(data_orcamento,'%Y-%m') AS mes, COALESCE(SUM(valor_total),0) AS total
     FROM orcamentos
     WHERE status IN ('aprovado','em_producao','concluido')
       AND data_orcamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY mes"
);
foreach ($st->fetchAll() as $linha) {
    if (isset($meses[$linha['mes']])) {
        $meses[$linha['mes']]['valor'] = (float) $linha['total'];
    }
}
$maiorMes = max(array_column($meses, 'valor')) ?: 1;

// Funil de status
$funil = $pdo->query(
    "SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total
     FROM orcamentos GROUP BY status"
)->fetchAll();
$mapaFunil = [];
foreach ($funil as $f) {
    $mapaFunil[$f['status']] = $f;
}
$maiorFunil = max(array_column($funil, 'qtd') ?: [1]);

// Produtos mais vendidos
$topProdutos = $pdo->query(
    "SELECT p.nome, p.unidade,
            SUM(i.quantidade) AS qtd,
            SUM(i.valor_total) AS total
     FROM itens_orcamento i
     JOIN produtos p ON p.id = i.produto_id
     JOIN orcamentos o ON o.id = i.orcamento_id
     WHERE o.status IN ('aprovado','em_producao','concluido')
     GROUP BY p.id, p.nome, p.unidade
     ORDER BY total DESC
     LIMIT 5"
)->fetchAll();
$maiorProduto = $topProdutos ? (float) $topProdutos[0]['total'] : 1;

// Ultimos orcamentos
$ultimos = $pdo->query(
    "SELECT o.id, o.numero, o.status, o.valor_total, o.data_orcamento, o.validade_dias,
            c.nome AS cliente, u.nome AS responsavel
     FROM orcamentos o
     JOIN clientes c ON c.id = o.cliente_id
     JOIN usuarios u ON u.id = o.responsavel_id
     ORDER BY o.id DESC LIMIT 6"
)->fetchAll();

/* Helper local para nome do mes em portugues */
function strftime_pt(string $anoMes): string
{
    $nomes = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',
              7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
    [$a, $m] = explode('-', $anoMes);
    return $nomes[(int) $m] . '/' . substr($a, 2);
}
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Painel gerencial';
$pagina_ativa  = 'index';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Painel gerencial</h1>
        <p class="pagina-subtitulo">
            Resumo de <?= e(ucfirst(strftime_pt($mesAtual))) ?> &middot;
            atualizado em <?= date('d/m/Y \a\s H:i') ?>
        </p>
    </div>
    <div class="pagina-acoes">
        <a href="orcamentos_form.php" class="botao botao-sucesso">+ Novo orcamento</a>
        <a href="clientes_form.php" class="botao botao-contorno">+ Novo cliente</a>
    </div>
</div>

<!-- INDICADORES -->
<div class="grade-indicadores">

    <?php if ($verFinanceiro): ?>
    <div class="indicador verde">
        <div class="indicador-rotulo">Faturamento fechado no mes</div>
        <div class="indicador-valor"><?= moeda($mes['fechado']) ?></div>
        <div class="indicador-nota">
            <?php if (abs($variacao) > 0.01): ?>
                <span class="variacao <?= $variacao >= 0 ? 'sobe' : 'desce' ?>">
                    <?= $variacao >= 0 ? '&#9650;' : '&#9660;' ?> <?= numero(abs($variacao), 1) ?>%
                </span>
                em relacao a <?= e(strftime_pt($mesAnterior)) ?>
            <?php else: ?>
                Sem base de comparacao no mes anterior
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="indicador">
        <div class="indicador-rotulo">Orcamentos emitidos no mes</div>
        <div class="indicador-valor"><?= (int) $mes['qtd'] ?></div>
        <div class="indicador-nota"><?= (int) $mes['qtd_fechado'] ?> fechados &middot; <?= $totalPendentes ?> aguardando resposta</div>
    </div>

    <div class="indicador roxo">
        <div class="indicador-rotulo">Taxa de conversao</div>
        <div class="indicador-valor"><?= numero($conversao, 1) ?>%</div>
        <div class="indicador-nota">
            <div class="barra-progresso mt1">
                <span style="width:<?= min(100, $conversao) ?>%;background:var(--roxo-600)"></span>
            </div>
        </div>
    </div>

    <?php if ($verFinanceiro): ?>
    <div class="indicador ambar">
        <div class="indicador-rotulo">Ticket medio</div>
        <div class="indicador-valor"><?= moeda($ticket) ?></div>
        <div class="indicador-nota">Media por orcamento aprovado no mes</div>
    </div>

    <div class="indicador vermelho">
        <div class="indicador-rotulo">Compras de insumos no mes</div>
        <div class="indicador-valor"><?= moeda($gastoInsumosMes) ?></div>
        <div class="indicador-nota">Saida registrada em <?= e(strftime_pt($mesAtual)) ?></div>
    </div>
    <?php endif; ?>

    <div class="indicador">
        <div class="indicador-rotulo">Clientes ativos</div>
        <div class="indicador-valor"><?= $totalClientes ?></div>
        <div class="indicador-nota">Base cadastrada no sistema</div>
    </div>
</div>

<!-- GRAFICOS -->
<div class="grade-2">

    <?php if ($verFinanceiro): ?>
    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>Faturamento dos ultimos 6 meses</h3>
            <span class="txt-p txt-suave">Somente orcamentos fechados</span>
        </div>
        <div class="cartao-corpo">
            <div class="grafico-barras">
                <?php foreach ($meses as $m): ?>
                    <?php $altura = $maiorMes > 0 ? ($m['valor'] / $maiorMes) * 100 : 0; ?>
                    <div class="barra-coluna">
                        <div class="barra" style="height:<?= max(2, $altura) ?>%"
                             title="<?= moeda($m['valor']) ?>">
                            <?php if ($m['valor'] > 0): ?>
                                <span class="barra-valor"><?= number_format($m['valor'] / 1000, 1, ',', '.') ?>k</span>
                            <?php endif; ?>
                        </div>
                        <div class="barra-rotulo"><?= e($m['rotulo']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>Funil de orcamentos</h3>
            <a href="orcamentos_listar.php" class="txt-p">Ver todos</a>
        </div>
        <div class="cartao-corpo">
            <div class="funil">
                <?php foreach (status_orcamento() as $chave => $info): ?>
                    <?php
                    $qtd = isset($mapaFunil[$chave]) ? (int) $mapaFunil[$chave]['qtd'] : 0;
                    $largura = $maiorFunil > 0 ? ($qtd / $maiorFunil) * 100 : 0;
                    $cores = [
                        'rascunho' => 'var(--texto-suave)', 'enviado' => 'var(--laranja-600)',
                        'aprovado' => 'var(--verde-500)', 'em_producao' => 'var(--roxo-600)',
                        'concluido' => 'var(--verde-600)', 'cancelado' => 'var(--vermelho-500)',
                    ];
                    ?>
                    <div class="funil-etapa">
                        <span class="funil-nome"><?= e($info['rotulo']) ?></span>
                        <div class="funil-barra">
                            <div class="funil-preenche"
                                 style="width:<?= max(6, $largura) ?>%;background:<?= $cores[$chave] ?>">
                                <?= $qtd ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>Produtos mais vendidos</h3>
            <a href="produtos.php" class="txt-p">Gerenciar</a>
        </div>
        <div class="cartao-corpo">
            <?php if ($topProdutos): ?>
                <ul class="lista-ranque">
                    <?php foreach ($topProdutos as $p): ?>
                        <li>
                            <div class="ranque-topo">
                                <span><?= e($p['nome']) ?></span>
                                <strong><?= $verFinanceiro ? moeda($p['total']) : numero($p['qtd']) . ' ' . e($p['unidade']) ?></strong>
                            </div>
                            <div class="barra-progresso">
                                <span style="width:<?= ($p['total'] / $maiorProduto) * 100 ?>%;background:var(--laranja-600)"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="txt-suave">Nenhum produto vendido ainda.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>Ultimos orcamentos</h3>
            <a href="orcamentos_listar.php" class="txt-p">Ver todos</a>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Cliente</th>
                        <th class="centro">Status</th>
                        <?php if ($verFinanceiro): ?><th class="num">Valor</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($ultimos): ?>
                    <?php foreach ($ultimos as $o): ?>
                        <tr onclick="location.href='orcamentos_detalhes.php?id=<?= (int) $o['id'] ?>'"
                            style="cursor:pointer">
                            <td class="mono txt-p"><?= e($o['numero']) ?></td>
                            <td>
                                <div class="titulo-celula"><?= e($o['cliente']) ?></div>
                                <div class="sub-celula"><?= data_br($o['data_orcamento']) ?></div>
                            </td>
                            <td class="centro"><?= selo_status($o['status']) ?></td>
                            <?php if ($verFinanceiro): ?>
                                <td class="num negrito"><?= moeda($o['valor_total']) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="vazio">Nenhum orcamento cadastrado.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
