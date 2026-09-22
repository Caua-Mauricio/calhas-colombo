<?php
// lista de clientes com busca e paginacao

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_clientes');

$u = usuario_logado();
$pdo = bd();

// Filtros
$busca  = trim($_GET['busca'] ?? '');
$cidade = trim($_GET['cidade'] ?? '');
$origem = trim($_GET['origem'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 15;

$where  = ['c.ativo = 1'];
$params = [];

if ($busca !== '') {
    $where[] = '(c.nome LIKE :busca OR c.cpf_cnpj LIKE :busca OR c.telefone LIKE :busca OR c.email LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($cidade !== '') {
    $where[] = 'c.cidade = :cidade';
    $params[':cidade'] = $cidade;
}
if ($origem !== '') {
    $where[] = 'c.origem = :origem';
    $params[':origem'] = $origem;
}

$clausula = 'WHERE ' . implode(' AND ', $where);

// Total para paginacao
$st = $pdo->prepare("SELECT COUNT(*) FROM clientes c $clausula");
$st->execute($params);
$total = (int) $st->fetchColumn();
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$inicio = ($pagina - 1) * $porPagina;

// Consulta principal (com total ja orcado por cliente)
$sql = "SELECT c.*,
               (SELECT COUNT(*) FROM orcamentos o WHERE o.cliente_id = c.id) AS qtd_orcamentos,
               (SELECT COALESCE(SUM(o.valor_total),0) FROM orcamentos o
                 WHERE o.cliente_id = c.id
                   AND o.status IN ('aprovado','em_producao','concluido')) AS total_fechado
        FROM clientes c
        $clausula
        ORDER BY c.nome ASC
        LIMIT $porPagina OFFSET $inicio";

$st = $pdo->prepare($sql);
$st->execute($params);
$clientes = $st->fetchAll();

// Opcoes dos filtros
$cidades = $pdo->query(
    "SELECT DISTINCT cidade FROM clientes WHERE cidade IS NOT NULL AND cidade <> '' ORDER BY cidade"
)->fetchAll(PDO::FETCH_COLUMN);

$origens = [
    'indicacao' => 'Indicacao', 'instagram' => 'Instagram', 'google' => 'Google',
    'fachada' => 'Fachada da loja', 'outro' => 'Outro',
];

/** Monta a URL mantendo os filtros ativos. */
function url_pagina(int $p): string
{
    $q = $_GET;
    $q['pagina'] = $p;
    return '?' . http_build_query($q);
}
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Clientes';
$pagina_ativa  = 'clientes_listar';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Clientes</h1>
        <p class="pagina-subtitulo"><?= $total ?> cliente(s) encontrado(s)</p>
    </div>
    <div class="pagina-acoes">
        <a href="clientes_form.php" class="botao botao-sucesso">+ Novo cliente</a>
    </div>
</div>

<!-- FILTROS -->
<form method="GET" class="barra-filtros">
    <div class="campo campo-busca">
        <label for="busca">Buscar</label>
        <input type="search" id="busca" name="busca" value="<?= e($busca) ?>"
               placeholder="Nome, CPF/CNPJ, telefone ou e-mail">
    </div>

    <div class="campo">
        <label for="cidade">Cidade</label>
        <select id="cidade" name="cidade">
            <option value="">Todas</option>
            <?php foreach ($cidades as $c): ?>
                <option value="<?= e($c) ?>" <?= $cidade === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="campo">
        <label for="origem">Origem</label>
        <select id="origem" name="origem">
            <option value="">Todas</option>
            <?php foreach ($origens as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $origem === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="flex g2">
        <button type="submit" class="botao botao-primario">Filtrar</button>
        <a href="clientes_listar.php" class="botao botao-neutro">Limpar</a>
    </div>
</form>

<!-- TABELA -->
<div class="cartao">
    <div class="tabela-rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Contato</th>
                    <th>Endereco</th>
                    <th class="centro">Orcamentos</th>
                    <?php if (pode('ver_financeiro')): ?>
                        <th class="num">Ja fechado</th>
                    <?php endif; ?>
                    <th class="centro">Acoes</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($clientes): ?>
                <?php foreach ($clientes as $c): ?>
                    <?php
                    $whats = link_whatsapp(
                        $c['whatsapp'] ?: $c['telefone'],
                        'Ola ' . $c['nome'] . ', aqui e da ' . EMPRESA_NOME . '!'
                    );
                    ?>
                    <tr>
                        <td>
                            <div class="titulo-celula"><?= e($c['nome']) ?></div>
                            <div class="sub-celula">
                                <?= $c['tipo_pessoa'] === 'juridica' ? 'PJ' : 'PF' ?>
                                <?= $c['cpf_cnpj'] ? ' &middot; ' . e($c['cpf_cnpj']) : '' ?>
                            </div>
                        </td>
                        <td>
                            <div><?= e(formatar_telefone($c['telefone'])) ?></div>
                            <?php if ($c['email']): ?>
                                <div class="sub-celula"><?= e($c['email']) ?></div>
                            <?php endif; ?>
                            <?php if ($whats): ?>
                                <a href="<?= e($whats) ?>" target="_blank" rel="noopener"
                                   class="botao botao-whats botao-p mt1">WhatsApp</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= e($c['endereco']) ?><?= $c['numero'] ? ', ' . e($c['numero']) : '' ?></div>
                            <div class="sub-celula">
                                <?= e(trim($c['bairro'] . ' - ' . $c['cidade'] . '/' . $c['uf'], ' -/')) ?>
                            </div>
                        </td>
                        <td class="centro">
                            <span class="selo selo-laranja"><?= (int) $c['qtd_orcamentos'] ?></span>
                        </td>
                        <?php if (pode('ver_financeiro')): ?>
                            <td class="num negrito"><?= moeda($c['total_fechado']) ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="acoes">
                                <a href="orcamentos_form.php?cliente_id=<?= (int) $c['id'] ?>"
                                   class="botao botao-contorno botao-p" title="Criar orcamento">Orcar</a>
                                <a href="clientes_form.php?id=<?= (int) $c['id'] ?>"
                                   class="botao botao-alerta botao-p">Editar</a>
                                <?php if (pode('excluir_clientes')): ?>
                                    <a href="clientes_excluir.php?id=<?= (int) $c['id'] ?>&csrf=<?= csrf_token() ?>"
                                       class="botao botao-perigo botao-p"
                                       data-confirmar="Desativar o cliente <?= e($c['nome']) ?>?">Excluir</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="vazio">
                            <div class="vazio-icone">&#9679;</div>
                            <h4>Nenhum cliente encontrado</h4>
                            <p>Ajuste os filtros ou cadastre o primeiro cliente.</p>
                            <a href="clientes_form.php" class="botao botao-sucesso">+ Cadastrar cliente</a>
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
                    <a href="<?= e(url_pagina($pagina - 1)) ?>" class="botao botao-neutro botao-p">Anterior</a>
                <?php endif; ?>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="<?= e(url_pagina($pagina + 1)) ?>" class="botao botao-neutro botao-p">Proxima</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
