<?php
// registro de compra de insumo. quando vincula a um produto do
// catalogo, o estoque ja entra somado direto

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_insumos');

$u = usuario_logado();
$pdo = bd();

// Registrar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    csrf_validar();

    $descricao   = trim($_POST['descricao'] ?? '');
    $produtoId   = (int) ($_POST['produto_id'] ?? 0) ?: null;
    $fornecedorId = (int) ($_POST['fornecedor_id'] ?? 0) ?: null;
    $quantidade  = valor_float($_POST['quantidade'] ?? 0);
    $unidade     = trim($_POST['unidade'] ?? 'un');
    $valorTotal  = valor_float($_POST['valor_total'] ?? 0);
    $dataCompra  = $_POST['data_compra'] ?? date('Y-m-d');
    $somarEstoque = isset($_POST['somar_estoque']);

    if ($descricao === '' || $quantidade <= 0 || $valorTotal <= 0) {
        flash('erro', 'Preencha a descricao, a quantidade e o valor da compra.');
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                "INSERT INTO compras_insumos
                   (descricao, produto_id, fornecedor_id, quantidade, unidade, valor_total, data_compra, usuario_id)
                 VALUES (:d, :p, :f, :q, :u, :v, :dt, :us)"
            )->execute([
                ':d' => $descricao, ':p' => $produtoId, ':f' => $fornecedorId,
                ':q' => $quantidade, ':u' => $unidade, ':v' => $valorTotal,
                ':dt' => $dataCompra, ':us' => $u['id'],
            ]);

            $compraId = (int) $pdo->lastInsertId();

            /* Entrada no estoque e atualizacao do custo medio */
            if ($produtoId && $somarEstoque) {
                $custoUnitario = $valorTotal / $quantidade;
                $pdo->prepare(
                    "UPDATE produtos
                     SET estoque_atual = estoque_atual + :q,
                         preco_custo = :custo
                     WHERE id = :id"
                )->execute([':q' => $quantidade, ':custo' => $custoUnitario, ':id' => $produtoId]);
            }

            $pdo->commit();
            flash('sucesso', 'Compra registrada' . ($produtoId && $somarEstoque ? ' e estoque atualizado.' : '.'));
        } catch (PDOException $ex) {
            $pdo->rollBack();
            flash('erro', 'Erro ao registrar: ' . $ex->getMessage());
        }
        redirecionar('insumos.php');
    }
}

// Excluir
if (isset($_GET['excluir']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'] ?? '')) {
    $cid = (int) $_GET['excluir'];
    $pdo->prepare("DELETE FROM compras_insumos WHERE id = :id")->execute([':id' => $cid]);
    flash('sucesso', 'Registro de compra removido.');
    redirecionar('insumos.php');
}

// Dados
$mesFiltro = $_GET['mes'] ?? date('Y-m');

$st = $pdo->prepare(
    "SELECT ci.*, p.nome AS produto, f.nome AS fornecedor, us.nome AS comprador
     FROM compras_insumos ci
     LEFT JOIN produtos p ON p.id = ci.produto_id
     LEFT JOIN fornecedores f ON f.id = ci.fornecedor_id
     LEFT JOIN usuarios us ON us.id = ci.usuario_id
     WHERE DATE_FORMAT(ci.data_compra,'%Y-%m') = :mes
     ORDER BY ci.data_compra DESC, ci.id DESC"
);
$st->execute([':mes' => $mesFiltro]);
$compras = $st->fetchAll();

$totalMes = array_sum(array_column($compras, 'valor_total'));

$produtos = $pdo->query("SELECT id, nome, unidade FROM produtos WHERE ativo = 1 ORDER BY nome")->fetchAll();
$fornecedores = $pdo->query("SELECT id, nome FROM fornecedores WHERE ativo = 1 ORDER BY nome")->fetchAll();

/* Meses disponiveis para o filtro */
$meses = $pdo->query(
    "SELECT DISTINCT DATE_FORMAT(data_compra,'%Y-%m') AS mes FROM compras_insumos ORDER BY mes DESC"
)->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($mesFiltro, $meses, true)) {
    array_unshift($meses, $mesFiltro);
}
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Insumos';
$pagina_ativa  = 'insumos';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Compras de insumos</h1>
        <p class="pagina-subtitulo">
            <?= count($compras) ?> compra(s) no periodo
            <?php if (pode('ver_financeiro')): ?>
                &middot; total de <?= moeda($totalMes) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="pagina-acoes">
        <form method="GET" class="flex g2 alinha-centro">
            <select name="mes" onchange="this.form.submit()"
                    style="padding:9px 12px;border:1px solid var(--borda-forte);border-radius:6px;background:var(--superficie);color:var(--texto)">
                <?php foreach ($meses as $m): ?>
                    <option value="<?= e($m) ?>" <?= $mesFiltro === $m ? 'selected' : '' ?>>
                        <?= e(date('m/Y', strtotime($m . '-01'))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="grade-2">

    <!-- FORMULARIO -->
    <div class="cartao" style="align-self:start">
        <div class="cartao-cabecalho"><h3>Registrar compra</h3></div>
        <div class="cartao-corpo">
            <form method="POST" class="formulario-grade">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="cadastrar">

                <div class="campo col-12">
                    <label for="descricao">Descricao <span class="obrigatorio">*</span></label>
                    <input type="text" id="descricao" name="descricao" required
                           placeholder="Ex: Bobina de chapa 0,5mm">
                </div>

                <div class="campo col-6">
                    <label for="produto_id">Vincular a um produto</label>
                    <select id="produto_id" name="produto_id" onchange="ajustarUnidade()">
                        <option value="">Nenhum (compra avulsa)</option>
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" data-unidade="<?= e($p['unidade']) ?>">
                                <?= e($p['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="campo-dica">Permite dar entrada no estoque</span>
                </div>

                <div class="campo col-6">
                    <label for="fornecedor_id">Fornecedor</label>
                    <select id="fornecedor_id" name="fornecedor_id">
                        <option value="">Nao informado</option>
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?= (int) $f['id'] ?>"><?= e($f['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-4">
                    <label for="quantidade">Quantidade <span class="obrigatorio">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="quantidade" name="quantidade"
                           required value="1" oninput="calcularUnitario()">
                </div>

                <div class="campo col-4">
                    <label for="unidade">Unidade</label>
                    <input type="text" id="unidade" name="unidade" value="un" placeholder="metro, kg, un">
                </div>

                <div class="campo col-4">
                    <label for="valor_total">Valor total (R$) <span class="obrigatorio">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="valor_total" name="valor_total"
                           required oninput="calcularUnitario()">
                    <span class="campo-dica" id="dicaUnitario">Custo unitario: -</span>
                </div>

                <div class="campo col-6">
                    <label for="data_compra">Data da compra</label>
                    <input type="date" id="data_compra" name="data_compra" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="campo col-6">
                    <label>&nbsp;</label>
                    <label class="caixa-marcar">
                        <input type="checkbox" name="somar_estoque" value="1" checked>
                        Dar entrada no estoque
                    </label>
                </div>

                <div class="formulario-acoes">
                    <button type="submit" class="botao botao-sucesso">Registrar compra</button>
                </div>
            </form>
        </div>
    </div>

    <!-- HISTORICO -->
    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>Compras de <?= e(date('m/Y', strtotime($mesFiltro . '-01'))) ?></h3>
            <?php if (pode('ver_financeiro')): ?>
                <strong class="txt-g" style="color:var(--vermelho-600)"><?= moeda($totalMes) ?></strong>
            <?php endif; ?>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Descricao</th>
                        <th class="num">Qtd.</th>
                        <?php if (pode('ver_financeiro')): ?><th class="num">Valor</th><?php endif; ?>
                        <th>Data</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($compras): ?>
                    <?php foreach ($compras as $c): ?>
                        <tr>
                            <td>
                                <div class="titulo-celula"><?= e($c['descricao']) ?></div>
                                <div class="sub-celula">
                                    <?= $c['fornecedor'] ? e($c['fornecedor']) : 'Sem fornecedor' ?>
                                    <?= $c['produto'] ? ' &middot; ' . e($c['produto']) : '' ?>
                                </div>
                            </td>
                            <td class="num"><?= numero($c['quantidade']) ?> <?= e($c['unidade']) ?></td>
                            <?php if (pode('ver_financeiro')): ?>
                                <td class="num negrito"><?= moeda($c['valor_total']) ?></td>
                            <?php endif; ?>
                            <td class="txt-p"><?= data_br($c['data_compra']) ?></td>
                            <td>
                                <a href="?excluir=<?= (int) $c['id'] ?>&mes=<?= e($mesFiltro) ?>&csrf=<?= csrf_token() ?>"
                                   class="botao botao-perigo botao-p"
                                   data-confirmar="Remover este registro de compra?">&times;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="vazio">
                                <div class="vazio-icone">&#9635;</div>
                                <h4>Nenhuma compra neste mes</h4>
                                <p>Registre a primeira compra no formulario ao lado.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function ajustarUnidade() {
    const sel = document.getElementById('produto_id');
    const opcao = sel.options[sel.selectedIndex];
    const unidade = opcao.getAttribute('data-unidade');
    if (unidade) document.getElementById('unidade').value = unidade;
}

function calcularUnitario() {
    const qtd = parseFloat(document.getElementById('quantidade').value) || 0;
    const total = parseFloat(document.getElementById('valor_total').value) || 0;
    const dica = document.getElementById('dicaUnitario');

    if (qtd > 0 && total > 0) {
        dica.textContent = 'Custo unitario: R$ ' + (total / qtd).toFixed(2).replace('.', ',');
    } else {
        dica.textContent = 'Custo unitario: -';
    }
}
</script>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
