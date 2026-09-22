<?php
// catalogo de produtos: preco, estoque e margem

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_produtos');

$u = usuario_logado();
$pdo = bd();

/* Salvar (novo ou edicao) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    csrf_validar();

    $pid       = (int) ($_POST['id'] ?? 0);
    $nome      = trim($_POST['nome'] ?? '');
    $categoria = trim($_POST['categoria'] ?? 'Geral');
    $unidade   = $_POST['unidade'] ?? 'un';
    $custo     = valor_float($_POST['preco_custo'] ?? 0);
    $preco     = valor_float($_POST['preco_unitario'] ?? 0);
    $estoque   = valor_float($_POST['estoque_atual'] ?? 0);
    $minimo    = valor_float($_POST['estoque_minimo'] ?? 0);
    $ativo     = isset($_POST['ativo']) ? 1 : 0;

    if ($nome === '') {
        flash('erro', 'Informe o nome do produto.');
    } elseif ($preco <= 0) {
        flash('erro', 'O preco de venda precisa ser maior que zero.');
    } else {
        $dados = [
            ':nome' => $nome, ':categoria' => $categoria ?: 'Geral', ':unidade' => $unidade,
            ':custo' => $custo, ':preco' => $preco, ':estoque' => $estoque,
            ':minimo' => $minimo, ':ativo' => $ativo,
        ];

        if ($pid > 0) {
            $dados[':id'] = $pid;
            $pdo->prepare(
                "UPDATE produtos SET nome=:nome, categoria=:categoria, unidade=:unidade,
                    preco_custo=:custo, preco_unitario=:preco, estoque_atual=:estoque,
                    estoque_minimo=:minimo, ativo=:ativo
                 WHERE id=:id"
            )->execute($dados);
            flash('sucesso', 'Produto atualizado.');
        } else {
            $pdo->prepare(
                "INSERT INTO produtos (nome, categoria, unidade, preco_custo, preco_unitario,
                                       estoque_atual, estoque_minimo, ativo)
                 VALUES (:nome, :categoria, :unidade, :custo, :preco, :estoque, :minimo, :ativo)"
            )->execute($dados);
            flash('sucesso', 'Produto cadastrado.');
        }
        redirecionar('produtos.php');
    }
}

// Desativar
if (isset($_GET['desativar'])) {
    $pid = (int) $_GET['desativar'];
    if (hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'] ?? '')) {
        $pdo->prepare("UPDATE produtos SET ativo = 0 WHERE id = :id")->execute([':id' => $pid]);
        flash('sucesso', 'Produto desativado. Ele nao aparece mais em novos orcamentos.');
    }
    redirecionar('produtos.php');
}

/* Registro em edicao */
$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM produtos WHERE id = :id");
    $st->execute([':id' => (int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}

/* Listagem */
$mostrarInativos = isset($_GET['inativos']);
$sql = "SELECT p.*,
               COALESCE((SELECT SUM(i.quantidade) FROM itens_orcamento i
                         JOIN orcamentos o ON o.id = i.orcamento_id
                         WHERE i.produto_id = p.id
                           AND o.status IN ('aprovado','em_producao','concluido')),0) AS vendido
        FROM produtos p"
     . ($mostrarInativos ? '' : ' WHERE p.ativo = 1')
     . " ORDER BY p.categoria, p.nome";
$produtos = $pdo->query($sql)->fetchAll();

$unidades = ['metro' => 'Metro', 'kg' => 'Quilo', 'un' => 'Unidade', 'peca' => 'Peca', 'rolo' => 'Rolo'];
$valorEstoque = 0.0;
foreach ($produtos as $p) {
    $valorEstoque += (float) $p['estoque_atual'] * (float) $p['preco_custo'];
}
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Produtos';
$pagina_ativa  = 'produtos';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Produtos e materiais</h1>
        <p class="pagina-subtitulo">
            <?= count($produtos) ?> item(ns)
            <?php if (pode('ver_financeiro')): ?>
                &middot; <?= moeda($valorEstoque) ?> imobilizados em estoque
            <?php endif; ?>
        </p>
    </div>
    <div class="pagina-acoes">
        <a href="produtos.php<?= $mostrarInativos ? '' : '?inativos=1' ?>" class="botao botao-neutro">
            <?= $mostrarInativos ? 'Ocultar inativos' : 'Mostrar inativos' ?>
        </a>
    </div>
</div>

<div class="grade-2">

    <!-- FORMULARIO -->
    <div class="cartao" style="align-self:start">
        <div class="cartao-cabecalho">
            <h3><?= $editar ? 'Editar produto' : 'Novo produto' ?></h3>
            <?php if ($editar): ?>
                <a href="produtos.php" class="txt-p">Cancelar edicao</a>
            <?php endif; ?>
        </div>
        <div class="cartao-corpo">
            <form method="POST" class="formulario-grade">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

                <div class="campo col-12">
                    <label for="nome">Nome do produto <span class="obrigatorio">*</span></label>
                    <input type="text" id="nome" name="nome" required
                           value="<?= e($editar['nome'] ?? '') ?>"
                           placeholder="Ex: Calha de Aluminio 0,5mm">
                </div>

                <div class="campo col-6">
                    <label for="categoria">Categoria</label>
                    <input type="text" id="categoria" name="categoria"
                           value="<?= e($editar['categoria'] ?? '') ?>"
                           placeholder="Calhas, Fixacao, Vedacao..." list="listaCategorias">
                    <datalist id="listaCategorias">
                        <?php foreach (array_unique(array_column($produtos, 'categoria')) as $cat): ?>
                            <option value="<?= e($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="campo col-6">
                    <label for="unidade">Unidade de medida</label>
                    <select id="unidade" name="unidade">
                        <?php foreach ($unidades as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= ($editar['unidade'] ?? '') === $k ? 'selected' : '' ?>>
                                <?= e($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-6">
                    <label for="preco_custo">Preco de custo (R$)</label>
                    <input type="number" step="0.01" min="0" id="preco_custo" name="preco_custo"
                           value="<?= number_format((float) ($editar['preco_custo'] ?? 0), 2, '.', '') ?>"
                           oninput="calcularMargem()">
                </div>

                <div class="campo col-6">
                    <label for="preco_unitario">Preco de venda (R$) <span class="obrigatorio">*</span></label>
                    <input type="number" step="0.01" min="0" id="preco_unitario" name="preco_unitario" required
                           value="<?= number_format((float) ($editar['preco_unitario'] ?? 0), 2, '.', '') ?>"
                           oninput="calcularMargem()">
                    <span class="campo-dica" id="dicaMargem">Margem: -</span>
                </div>

                <div class="campo col-6">
                    <label for="estoque_atual">Estoque atual</label>
                    <input type="number" step="0.01" min="0" id="estoque_atual" name="estoque_atual"
                           value="<?= number_format((float) ($editar['estoque_atual'] ?? 0), 2, '.', '') ?>">
                </div>

                <div class="campo col-6">
                    <label for="estoque_minimo">Estoque minimo</label>
                    <input type="number" step="0.01" min="0" id="estoque_minimo" name="estoque_minimo"
                           value="<?= number_format((float) ($editar['estoque_minimo'] ?? 0), 2, '.', '') ?>">
                    <span class="campo-dica">Gera alerta quando o estoque chega neste valor</span>
                </div>

                <div class="campo col-12">
                    <label class="caixa-marcar">
                        <input type="checkbox" name="ativo" value="1"
                            <?= (!$editar || (int) $editar['ativo'] === 1) ? 'checked' : '' ?>>
                        Produto ativo (disponivel para orcamentos)
                    </label>
                </div>

                <div class="formulario-acoes">
                    <button type="submit" class="botao botao-sucesso">
                        <?= $editar ? 'Salvar alteracoes' : 'Cadastrar produto' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LISTA -->
    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>Catalogo</h3>
            <input type="search" placeholder="Filtrar..." data-filtra-tabela="#tabelaProdutos"
                   style="padding:7px 11px;border:1px solid var(--borda-forte);border-radius:6px;background:var(--superficie);color:var(--texto);max-width:180px">
        </div>
        <div class="tabela-rolagem">
            <table class="tabela" id="tabelaProdutos">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="num">Venda</th>
                        <?php if (pode('ver_financeiro')): ?><th class="num">Margem</th><?php endif; ?>
                        <th class="num">Estoque</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produtos as $p): ?>
                    <?php
                    $margem = (float) $p['preco_custo'] > 0
                        ? ((float) $p['preco_unitario'] - (float) $p['preco_custo']) / (float) $p['preco_unitario'] * 100
                        : 100;
                    $baixo = (float) $p['estoque_atual'] <= (float) $p['estoque_minimo'];
                    ?>
                    <tr class="<?= $baixo ? 'linha-destaque' : '' ?>">
                        <td>
                            <div class="titulo-celula"><?= e($p['nome']) ?></div>
                            <div class="sub-celula">
                                <?= e($p['categoria']) ?>
                                <?php if (!(int) $p['ativo']): ?>
                                    &middot; <span class="selo selo-cinza">Inativo</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="num negrito"><?= moeda($p['preco_unitario']) ?></td>
                        <?php if (pode('ver_financeiro')): ?>
                            <td class="num">
                                <span class="selo <?= $margem >= 40 ? 'selo-verde' : ($margem >= 20 ? 'selo-ambar' : 'selo-vermelho') ?>">
                                    <?= numero($margem, 0) ?>%
                                </span>
                            </td>
                        <?php endif; ?>
                        <td class="num">
                            <span style="<?= $baixo ? 'color:var(--vermelho-600);font-weight:700' : '' ?>">
                                <?= numero($p['estoque_atual']) ?>
                            </span>
                            <div class="sub-celula">min <?= numero($p['estoque_minimo'], 0) ?></div>
                        </td>
                        <td>
                            <div class="acoes">
                                <a href="?editar=<?= (int) $p['id'] ?>" class="botao botao-alerta botao-p">Editar</a>
                                <?php if ((int) $p['ativo']): ?>
                                    <a href="?desativar=<?= (int) $p['id'] ?>&csrf=<?= csrf_token() ?>"
                                       class="botao botao-neutro botao-p"
                                       data-confirmar="Desativar <?= e($p['nome']) ?>?">Desativar</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr data-sem-resultado style="display:none">
                    <td colspan="5" class="vazio">Nenhum produto corresponde ao filtro.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function calcularMargem() {
    const custo = parseFloat(document.getElementById('preco_custo').value) || 0;
    const venda = parseFloat(document.getElementById('preco_unitario').value) || 0;
    const dica = document.getElementById('dicaMargem');

    if (venda <= 0) { dica.textContent = 'Margem: -'; return; }

    const margem = ((venda - custo) / venda) * 100;
    const lucro = venda - custo;
    dica.textContent = 'Margem: ' + margem.toFixed(1) + '% (lucro de R$ ' + lucro.toFixed(2).replace('.', ',') + ')';
    dica.style.color = margem >= 40 ? 'var(--verde-600)' : (margem >= 20 ? 'var(--ambar-600)' : 'var(--vermelho-600)');
}
calcularMargem();
</script>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
