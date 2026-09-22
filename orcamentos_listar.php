<?php
// lista de orcamentos, com filtro e uns totais no rodape da tabela

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_orcamentos');

$u = usuario_logado();
$pdo = bd();
$verFinanceiro = pode('ver_financeiro');

// Filtros
$busca   = trim($_GET['busca'] ?? '');
$status  = trim($_GET['status'] ?? '');
$de      = trim($_GET['de'] ?? '');
$ate     = trim($_GET['ate'] ?? '');
$pagina  = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 15;

$where  = ['1=1'];
$params = [];

if ($busca !== '') {
    $where[] = '(o.numero LIKE :busca OR c.nome LIKE :busca OR c.telefone LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($status !== '' && array_key_exists($status, status_orcamento())) {
    $where[] = 'o.status = :status';
    $params[':status'] = $status;
}
if ($de !== '') {
    $where[] = 'DATE(o.data_orcamento) >= :de';
    $params[':de'] = $de;
}
if ($ate !== '') {
    $where[] = 'DATE(o.data_orcamento) <= :ate';
    $params[':ate'] = $ate;
}

/* Perfis operacionais veem apenas os proprios orcamentos */
if (!pode('ver_financeiro')) {
    $where[] = 'o.responsavel_id = :meu_id';
    $params[':meu_id'] = $u['id'];
}

$clausula = 'WHERE ' . implode(' AND ', $where);

// Totalizadores
$st = $pdo->prepare(
    "SELECT COUNT(*) AS qtd,
            COALESCE(SUM(o.valor_total),0) AS total,
            COALESCE(SUM(CASE WHEN o.status IN ('aprovado','em_producao','concluido')
                         THEN o.valor_total ELSE 0 END),0) AS fechado
     FROM orcamentos o
     JOIN clientes c ON c.id = o.cliente_id
     $clausula"
);
$st->execute($params);
$resumo = $st->fetch();

$total = (int) $resumo['qtd'];
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$inicio = ($pagina - 1) * $porPagina;

// Listagem
$st = $pdo->prepare(
    "SELECT o.*, c.nome AS cliente, c.telefone AS cliente_telefone,
            c.whatsapp AS cliente_whatsapp, u.nome AS responsavel
     FROM orcamentos o
     JOIN clientes c ON c.id = o.cliente_id
     JOIN usuarios u ON u.id = o.responsavel_id
     $clausula
     ORDER BY o.id DESC
     LIMIT $porPagina OFFSET $inicio"
);
$st->execute($params);
$orcamentos = $st->fetchAll();

function url_pag(int $p): string
{
    $q = $_GET;
    $q['pagina'] = $p;
    return '?' . http_build_query($q);
}
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Orcamentos';
$pagina_ativa  = 'orcamentos_listar';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Orcamentos</h1>
        <p class="pagina-subtitulo">
            <?= $total ?> registro(s)
            <?php if ($verFinanceiro): ?>
                &middot; <?= moeda($resumo['total']) ?> em propostas
                &middot; <strong><?= moeda($resumo['fechado']) ?> fechados</strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="pagina-acoes">
        <a href="orcamentos_form.php" class="botao botao-sucesso">+ Novo orcamento</a>
    </div>
</div>

<!-- FILTROS -->
<form method="GET" class="barra-filtros">
    <div class="campo campo-busca">
        <label for="busca">Buscar</label>
        <input type="search" id="busca" name="busca" value="<?= e($busca) ?>"
               placeholder="Numero do orcamento, cliente ou telefone">
    </div>

    <div class="campo">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">Todos</option>
            <?php foreach (status_orcamento() as $k => $s): ?>
                <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($s['rotulo']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="campo">
        <label for="de">De</label>
        <input type="date" id="de" name="de" value="<?= e($de) ?>">
    </div>

    <div class="campo">
        <label for="ate">Ate</label>
        <input type="date" id="ate" name="ate" value="<?= e($ate) ?>">
    </div>

    <div class="flex g2">
        <button type="submit" class="botao botao-primario">Filtrar</button>
        <a href="orcamentos_listar.php" class="botao botao-neutro">Limpar</a>
    </div>
</form>

<!-- TABELA -->
<div class="cartao">
    <div class="tabela-rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Numero</th>
                    <th>Cliente</th>
                    <th>Responsavel</th>
                    <th>Emissao</th>
                    <th class="centro">Status</th>
                    <?php if ($verFinanceiro): ?><th class="num">Valor</th><?php endif; ?>
                    <th class="centro">Acoes</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orcamentos): ?>
                <?php foreach ($orcamentos as $o): ?>
                    <?php $vencido = orcamento_vencido($o); ?>
                    <tr class="<?= $vencido ? 'linha-destaque' : '' ?>">
                        <td>
                            <div class="mono negrito txt-p"><?= e($o['numero']) ?></div>
                        </td>
                        <td>
                            <div class="titulo-celula"><?= e($o['cliente']) ?></div>
                            <div class="sub-celula"><?= e(formatar_telefone($o['cliente_telefone'])) ?></div>
                        </td>
                        <td class="txt-p"><?= e($o['responsavel']) ?></td>
                        <td>
                            <div><?= data_br($o['data_orcamento']) ?></div>
                            <?php if ($vencido): ?>
                                <div class="sub-celula" style="color:var(--ambar-600)">Validade expirada</div>
                            <?php endif; ?>
                        </td>
                        <td class="centro"><?= selo_status($o['status']) ?></td>
                        <?php if ($verFinanceiro): ?>
                            <td class="num negrito"><?= moeda($o['valor_total']) ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="acoes">
                                <a href="orcamentos_detalhes.php?id=<?= (int) $o['id'] ?>"
                                   class="botao botao-primario botao-p">Abrir</a>
                                <a href="orcamentos_form.php?id=<?= (int) $o['id'] ?>"
                                   class="botao botao-alerta botao-p">Editar</a>
                                <?php if (pode('excluir_orcamentos')): ?>
                                    <a href="orcamentos_excluir.php?id=<?= (int) $o['id'] ?>&csrf=<?= csrf_token() ?>"
                                       class="botao botao-perigo botao-p"
                                       data-confirmar="Excluir o orcamento <?= e($o['numero']) ?>? Esta acao nao pode ser desfeita.">Excluir</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="vazio">
                            <div class="vazio-icone">&#9776;</div>
                            <h4>Nenhum orcamento encontrado</h4>
                            <p>Ajuste os filtros ou crie a primeira proposta.</p>
                            <a href="orcamentos_form.php" class="botao botao-sucesso">+ Novo orcamento</a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <div class="cartao-rodape flex entre alinha-centro quebra g3">
            <span class="txt-p txt-suave">Pagina <?= $pagina ?> de <?= $totalPaginas ?></span>
            <div class="flex g2">
                <?php if ($pagina > 1): ?>
                    <a href="<?= e(url_pag($pagina - 1)) ?>" class="botao botao-neutro botao-p">Anterior</a>
                <?php endif; ?>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="<?= e(url_pag($pagina + 1)) ?>" class="botao botao-neutro botao-p">Proxima</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
