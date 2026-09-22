<?php
// relatorios: faturamento por periodo, desempenho de cada calheiro,
// de onde vem os clientes e quais produtos vendem mais

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('ver_relatorios');

$u = usuario_logado();
$pdo = bd();

/* Periodo */
$de  = $_GET['de']  ?? date('Y-m-01', strtotime('-5 months'));
$ate = $_GET['ate'] ?? date('Y-m-d');

$p = [':de' => $de, ':ate' => $ate];
$filtroData = "DATE(o.data_orcamento) BETWEEN :de AND :ate";

/* 1. Resumo do periodo */
$st = $pdo->prepare(
    "SELECT COUNT(*) AS emitidos,
            COALESCE(SUM(CASE WHEN o.status IN ('aprovado','em_producao','concluido') THEN 1 ELSE 0 END),0) AS fechados,
            COALESCE(SUM(CASE WHEN o.status = 'cancelado' THEN 1 ELSE 0 END),0) AS perdidos,
            COALESCE(SUM(CASE WHEN o.status IN ('aprovado','em_producao','concluido') THEN o.valor_total ELSE 0 END),0) AS receita,
            COALESCE(SUM(CASE WHEN o.status IN ('rascunho','enviado') THEN o.valor_total ELSE 0 END),0) AS pipeline
     FROM orcamentos o WHERE $filtroData"
);
$st->execute($p);
$r = $st->fetch();

$conversao = (int) $r['emitidos'] > 0 ? ((int) $r['fechados'] / (int) $r['emitidos']) * 100 : 0;
$ticket = (int) $r['fechados'] > 0 ? (float) $r['receita'] / (int) $r['fechados'] : 0;

/* Custo com insumos no mesmo periodo */
$st = $pdo->prepare(
    "SELECT COALESCE(SUM(valor_total),0) FROM compras_insumos WHERE data_compra BETWEEN :de AND :ate"
);
$st->execute($p);
$gastoInsumos = (float) $st->fetchColumn();

// 2. Evolucao mensal
$st = $pdo->prepare(
    "SELECT DATE_FORMAT(o.data_orcamento,'%Y-%m') AS mes,
            COUNT(*) AS emitidos,
            COALESCE(SUM(CASE WHEN o.status IN ('aprovado','em_producao','concluido') THEN o.valor_total ELSE 0 END),0) AS receita
     FROM orcamentos o WHERE $filtroData
     GROUP BY mes ORDER BY mes"
);
$st->execute($p);
$evolucao = $st->fetchAll();
$maiorReceita = $evolucao ? max(array_column($evolucao, 'receita')) : 1;
$maiorReceita = $maiorReceita ?: 1;

// 3. Desempenho da equipe
$st = $pdo->prepare(
    "SELECT us.nome, us.perfil,
            COUNT(o.id) AS emitidos,
            SUM(CASE WHEN o.status IN ('aprovado','em_producao','concluido') THEN 1 ELSE 0 END) AS fechados,
            COALESCE(SUM(CASE WHEN o.status IN ('aprovado','em_producao','concluido') THEN o.valor_total ELSE 0 END),0) AS receita
     FROM orcamentos o
     JOIN usuarios us ON us.id = o.responsavel_id
     WHERE $filtroData
     GROUP BY us.id, us.nome, us.perfil
     ORDER BY receita DESC"
);
$st->execute($p);
$equipe = $st->fetchAll();
$maiorEquipe = $equipe ? max(array_column($equipe, 'receita')) : 1;
$maiorEquipe = $maiorEquipe ?: 1;

// 4. Origem dos clientes
$origens = $pdo->query(
    "SELECT c.origem, COUNT(*) AS qtd FROM clientes c WHERE c.ativo = 1 GROUP BY c.origem ORDER BY qtd DESC"
)->fetchAll();
$totalOrigens = array_sum(array_column($origens, 'qtd')) ?: 1;
$rotulosOrigem = [
    'indicacao' => 'Indicacao', 'instagram' => 'Instagram', 'google' => 'Google',
    'fachada' => 'Fachada da loja', 'outro' => 'Outro',
];

/* 5. Ranking de produtos */
$st = $pdo->prepare(
    "SELECT pr.nome, pr.unidade,
            SUM(i.quantidade) AS qtd,
            SUM(i.valor_total) AS receita
     FROM itens_orcamento i
     JOIN produtos pr ON pr.id = i.produto_id
     JOIN orcamentos o ON o.id = i.orcamento_id
     WHERE o.status IN ('aprovado','em_producao','concluido') AND $filtroData
     GROUP BY pr.id, pr.nome, pr.unidade
     ORDER BY receita DESC LIMIT 10"
);
$st->execute($p);
$ranking = $st->fetchAll();

// 6. Melhores clientes
$st = $pdo->prepare(
    "SELECT c.nome, COUNT(o.id) AS obras, COALESCE(SUM(o.valor_total),0) AS total
     FROM orcamentos o
     JOIN clientes c ON c.id = o.cliente_id
     WHERE o.status IN ('aprovado','em_producao','concluido') AND $filtroData
     GROUP BY c.id, c.nome ORDER BY total DESC LIMIT 8"
);
$st->execute($p);
$melhoresClientes = $st->fetchAll();

function mes_curto(string $anoMes): string
{
    $n = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
    [$a, $m] = explode('-', $anoMes);
    return $n[(int) $m] . '/' . substr($a, 2);
}
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Relatorios';
$pagina_ativa  = 'relatorios';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo nao-imprimir">
    <div>
        <h1 class="pagina-titulo">Relatorios gerenciais</h1>
        <p class="pagina-subtitulo">Periodo de <?= data_br($de) ?> ate <?= data_br($ate) ?></p>
    </div>
    <div class="pagina-acoes">
        <button onclick="window.print()" class="botao botao-primario">Imprimir</button>
    </div>
</div>

<form method="GET" class="barra-filtros nao-imprimir">
    <div class="campo">
        <label for="de">Data inicial</label>
        <input type="date" id="de" name="de" value="<?= e($de) ?>">
    </div>
    <div class="campo">
        <label for="ate">Data final</label>
        <input type="date" id="ate" name="ate" value="<?= e($ate) ?>">
    </div>
    <div class="flex g2">
        <button type="submit" class="botao botao-primario">Gerar relatorio</button>
        <a href="relatorios.php" class="botao botao-neutro">Restaurar</a>
    </div>
</form>

<!-- RESUMO -->
<div class="grade-indicadores">
    <div class="indicador verde">
        <div class="indicador-rotulo">Receita fechada</div>
        <div class="indicador-valor"><?= moeda($r['receita']) ?></div>
        <div class="indicador-nota"><?= (int) $r['fechados'] ?> obra(s) confirmada(s)</div>
    </div>

    <div class="indicador roxo">
        <div class="indicador-rotulo">Taxa de conversao</div>
        <div class="indicador-valor"><?= numero($conversao, 1) ?>%</div>
        <div class="indicador-nota">
            <?= (int) $r['emitidos'] ?> emitidos &middot; <?= (int) $r['perdidos'] ?> perdidos
        </div>
    </div>

    <div class="indicador ambar">
        <div class="indicador-rotulo">Em negociacao</div>
        <div class="indicador-valor"><?= moeda($r['pipeline']) ?></div>
        <div class="indicador-nota">Propostas ainda sem resposta</div>
    </div>

    <div class="indicador">
        <div class="indicador-rotulo">Ticket medio</div>
        <div class="indicador-valor"><?= moeda($ticket) ?></div>
        <div class="indicador-nota">Valor medio por obra fechada</div>
    </div>

    <div class="indicador vermelho">
        <div class="indicador-rotulo">Compras de insumos</div>
        <div class="indicador-valor"><?= moeda($gastoInsumos) ?></div>
        <div class="indicador-nota">Saida de caixa no periodo</div>
    </div>
</div>

<!-- EVOLUCAO MENSAL -->
<div class="cartao">
    <div class="cartao-cabecalho">
        <h3>Evolucao da receita</h3>
        <span class="txt-p txt-suave">Somente orcamentos fechados</span>
    </div>
    <div class="cartao-corpo">
        <?php if ($evolucao): ?>
            <div class="grafico-barras">
                <?php foreach ($evolucao as $ev): ?>
                    <div class="barra-coluna">
                        <div class="barra" style="height:<?= max(2, ((float) $ev['receita'] / $maiorReceita) * 100) ?>%"
                             title="<?= moeda($ev['receita']) ?> em <?= (int) $ev['emitidos'] ?> orcamento(s)">
                            <?php if ((float) $ev['receita'] > 0): ?>
                                <span class="barra-valor"><?= number_format((float) $ev['receita'] / 1000, 1, ',', '.') ?>k</span>
                            <?php endif; ?>
                        </div>
                        <div class="barra-rotulo"><?= e(mes_curto($ev['mes'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="txt-suave txt-centro">Nenhum dado no periodo selecionado.</p>
        <?php endif; ?>
    </div>
</div>

<div class="grade-2">

    <!-- EQUIPE -->
    <div class="cartao">
        <div class="cartao-cabecalho"><h3>Desempenho da equipe</h3></div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Responsavel</th>
                        <th class="centro">Fechados</th>
                        <th class="num">Receita</th>
                        <th class="centro">Conversao</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($equipe as $m): ?>
                    <?php $conv = (int) $m['emitidos'] > 0 ? ((int) $m['fechados'] / (int) $m['emitidos']) * 100 : 0; ?>
                    <tr>
                        <td>
                            <div class="titulo-celula"><?= e($m['nome']) ?></div>
                            <div class="sub-celula"><?= e(rotulo_perfil($m['perfil'])) ?></div>
                            <div class="barra-progresso mt1" style="max-width:150px">
                                <span style="width:<?= ((float) $m['receita'] / $maiorEquipe) * 100 ?>%;background:var(--laranja-600)"></span>
                            </div>
                        </td>
                        <td class="centro"><?= (int) $m['fechados'] ?>/<?= (int) $m['emitidos'] ?></td>
                        <td class="num negrito"><?= moeda($m['receita']) ?></td>
                        <td class="centro">
                            <span class="selo <?= $conv >= 50 ? 'selo-verde' : ($conv >= 25 ? 'selo-ambar' : 'selo-cinza') ?>">
                                <?= numero($conv, 0) ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$equipe): ?>
                    <tr><td colspan="4" class="vazio">Sem dados no periodo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ORIGEM DOS CLIENTES -->
    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>De onde vem os clientes</h3>
            <span class="txt-p txt-suave">Base completa</span>
        </div>
        <div class="cartao-corpo">
            <ul class="lista-ranque">
                <?php foreach ($origens as $o): ?>
                    <?php $perc = ((int) $o['qtd'] / $totalOrigens) * 100; ?>
                    <li>
                        <div class="ranque-topo">
                            <span><?= e($rotulosOrigem[$o['origem']] ?? ucfirst($o['origem'])) ?></span>
                            <strong><?= (int) $o['qtd'] ?> (<?= numero($perc, 0) ?>%)</strong>
                        </div>
                        <div class="barra-progresso">
                            <span style="width:<?= $perc ?>%;background:var(--roxo-600)"></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="txt-p txt-suave mt3">
                Use esta informacao para decidir onde investir em divulgacao.
            </p>
        </div>
    </div>

    <!-- RANKING DE PRODUTOS -->
    <div class="cartao">
        <div class="cartao-cabecalho"><h3>Produtos mais lucrativos</h3></div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="num">Vendido</th>
                        <th class="num">Receita</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ranking as $rk): ?>
                    <tr>
                        <td><?= e($rk['nome']) ?></td>
                        <td class="num"><?= numero($rk['qtd']) ?> <?= e($rk['unidade']) ?></td>
                        <td class="num"><?= moeda($rk['receita']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ranking): ?>
                    <tr><td colspan="3" class="vazio">Nenhuma venda no periodo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MELHORES CLIENTES -->
    <div class="cartao">
        <div class="cartao-cabecalho"><h3>Clientes que mais compraram</h3></div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th class="centro">Obras</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($melhoresClientes as $i => $c): ?>
                    <tr>
                        <td class="txt-suave"><?= $i + 1 ?></td>
                        <td class="titulo-celula"><?= e($c['nome']) ?></td>
                        <td class="centro"><?= (int) $c['obras'] ?></td>
                        <td class="num negrito"><?= moeda($c['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$melhoresClientes): ?>
                    <tr><td colspan="4" class="vazio">Nenhum cliente com obra fechada no periodo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
