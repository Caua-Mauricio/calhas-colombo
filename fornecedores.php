<?php
// cadastro simples de fornecedores

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_fornecedores');

$u = usuario_logado();
$pdo = bd();

/* Salvar */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    csrf_validar();

    $fid      = (int) ($_POST['id'] ?? 0);
    $nome     = trim($_POST['nome'] ?? '');
    $cnpj     = trim($_POST['cnpj'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $cidade   = trim($_POST['cidade'] ?? '');
    $uf       = trim($_POST['uf'] ?? '');
    $obs      = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {
        flash('erro', 'Informe o nome do fornecedor.');
    } elseif ($cnpj !== '' && !validar_documento($cnpj)) {
        flash('erro', 'CNPJ invalido.');
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('erro', 'E-mail invalido.');
    } else {
        $dados = [
            ':nome' => $nome, ':cnpj' => $cnpj ?: null, ':telefone' => $telefone ?: null,
            ':email' => $email ?: null, ':cidade' => $cidade ?: null,
            ':uf' => $uf ?: null, ':obs' => $obs ?: null,
        ];

        if ($fid > 0) {
            $dados[':id'] = $fid;
            $pdo->prepare(
                "UPDATE fornecedores SET nome=:nome, cnpj=:cnpj, telefone=:telefone,
                    email=:email, cidade=:cidade, uf=:uf, observacoes=:obs WHERE id=:id"
            )->execute($dados);
            flash('sucesso', 'Fornecedor atualizado.');
        } else {
            $pdo->prepare(
                "INSERT INTO fornecedores (nome, cnpj, telefone, email, cidade, uf, observacoes)
                 VALUES (:nome, :cnpj, :telefone, :email, :cidade, :uf, :obs)"
            )->execute($dados);
            flash('sucesso', 'Fornecedor cadastrado.');
        }
        redirecionar('fornecedores.php');
    }
}

/* Desativar */
if (isset($_GET['desativar']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'] ?? '')) {
    $fid = (int) $_GET['desativar'];
    $pdo->prepare("UPDATE fornecedores SET ativo = 0 WHERE id = :id")->execute([':id' => $fid]);
    flash('sucesso', 'Fornecedor desativado.');
    redirecionar('fornecedores.php');
}

/* Edicao */
$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM fornecedores WHERE id = :id");
    $st->execute([':id' => (int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}

// Listagem com historico de compras
$fornecedores = $pdo->query(
    "SELECT f.*,
            COUNT(ci.id) AS qtd_compras,
            COALESCE(SUM(ci.valor_total),0) AS total_comprado,
            MAX(ci.data_compra) AS ultima_compra
     FROM fornecedores f
     LEFT JOIN compras_insumos ci ON ci.fornecedor_id = f.id
     WHERE f.ativo = 1
     GROUP BY f.id
     ORDER BY total_comprado DESC, f.nome"
)->fetchAll();

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR',
        'PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Fornecedores';
$pagina_ativa  = 'fornecedores';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Fornecedores</h1>
        <p class="pagina-subtitulo"><?= count($fornecedores) ?> fornecedor(es) ativo(s)</p>
    </div>
</div>

<div class="grade-2">

    <div class="cartao" style="align-self:start">
        <div class="cartao-cabecalho">
            <h3><?= $editar ? 'Editar fornecedor' : 'Novo fornecedor' ?></h3>
            <?php if ($editar): ?>
                <a href="fornecedores.php" class="txt-p">Cancelar</a>
            <?php endif; ?>
        </div>
        <div class="cartao-corpo">
            <form method="POST" class="formulario-grade">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

                <div class="campo col-12">
                    <label for="nome">Nome / Razao social <span class="obrigatorio">*</span></label>
                    <input type="text" id="nome" name="nome" required value="<?= e($editar['nome'] ?? '') ?>">
                </div>

                <div class="campo col-6">
                    <label for="cnpj">CNPJ</label>
                    <input type="text" id="cnpj" name="cnpj" data-mascara="cnpj"
                           value="<?= e($editar['cnpj'] ?? '') ?>" placeholder="00.000.000/0001-00">
                </div>

                <div class="campo col-6">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" data-mascara="telefone"
                           value="<?= e($editar['telefone'] ?? '') ?>">
                </div>

                <div class="campo col-12">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($editar['email'] ?? '') ?>">
                </div>

                <div class="campo col-8">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" value="<?= e($editar['cidade'] ?? '') ?>">
                </div>

                <div class="campo col-4">
                    <label for="uf">UF</label>
                    <select id="uf" name="uf">
                        <option value="">-</option>
                        <?php foreach ($ufs as $sigla): ?>
                            <option value="<?= $sigla ?>" <?= ($editar['uf'] ?? '') === $sigla ? 'selected' : '' ?>>
                                <?= $sigla ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-12">
                    <label for="observacoes">Observacoes</label>
                    <textarea id="observacoes" name="observacoes" rows="2"
                              placeholder="Prazo de entrega, condicoes de pagamento..."><?= e($editar['observacoes'] ?? '') ?></textarea>
                </div>

                <div class="formulario-acoes">
                    <button type="submit" class="botao botao-sucesso">
                        <?= $editar ? 'Salvar' : 'Cadastrar' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="cartao">
        <div class="cartao-cabecalho"><h3>Fornecedores cadastrados</h3></div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Fornecedor</th>
                        <th class="centro">Compras</th>
                        <?php if (pode('ver_financeiro')): ?><th class="num">Total</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($fornecedores): ?>
                    <?php foreach ($fornecedores as $f): ?>
                        <tr>
                            <td>
                                <div class="titulo-celula"><?= e($f['nome']) ?></div>
                                <div class="sub-celula">
                                    <?= $f['telefone'] ? e(formatar_telefone($f['telefone'])) : 'Sem telefone' ?>
                                    <?= $f['cidade'] ? ' &middot; ' . e($f['cidade']) . '/' . e($f['uf']) : '' ?>
                                </div>
                                <?php if ($f['ultima_compra']): ?>
                                    <div class="sub-celula">Ultima compra: <?= data_br($f['ultima_compra']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="centro">
                                <span class="selo selo-laranja"><?= (int) $f['qtd_compras'] ?></span>
                            </td>
                            <?php if (pode('ver_financeiro')): ?>
                                <td class="num negrito"><?= moeda($f['total_comprado']) ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="acoes">
                                    <a href="?editar=<?= (int) $f['id'] ?>" class="botao botao-alerta botao-p">Editar</a>
                                    <a href="?desativar=<?= (int) $f['id'] ?>&csrf=<?= csrf_token() ?>"
                                       class="botao botao-neutro botao-p"
                                       data-confirmar="Desativar <?= e($f['nome']) ?>?">Remover</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">
                            <div class="vazio">
                                <div class="vazio-icone">&#9650;</div>
                                <h4>Nenhum fornecedor cadastrado</h4>
                                <p>Use o formulario ao lado para cadastrar.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
