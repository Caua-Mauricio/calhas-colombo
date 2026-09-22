<?php
// cadastro dos usuarios que acessam o sistema (só admin mexe aqui).
// senha sempre vai pro banco com password_hash, nunca em texto puro

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_usuarios');

$u = usuario_logado();
$pdo = bd();

// Salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    csrf_validar();

    $uid      = (int) ($_POST['id'] ?? 0);
    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $perfil   = $_POST['perfil'] ?? 'campo';
    $telefone = trim($_POST['telefone'] ?? '');
    $ativo    = isset($_POST['ativo']) ? 1 : 0;

    $perfisValidos = ['admin', 'gerente', 'calheiro', 'campo'];

    if (mb_strlen($nome) < 3) {
        flash('erro', 'Informe o nome completo.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('erro', 'E-mail invalido.');
    } elseif (!in_array($perfil, $perfisValidos, true)) {
        flash('erro', 'Perfil invalido.');
    } elseif ($uid === 0 && strlen($senha) < 6) {
        flash('erro', 'A senha precisa ter no minimo 6 caracteres.');
    } elseif ($uid > 0 && $senha !== '' && strlen($senha) < 6) {
        flash('erro', 'A nova senha precisa ter no minimo 6 caracteres.');
    } else {
        /* E-mail duplicado */
        $sql = "SELECT id FROM usuarios WHERE email = :email" . ($uid > 0 ? " AND id <> :id" : "");
        $st = $pdo->prepare($sql);
        $pr = [':email' => $email];
        if ($uid > 0) { $pr[':id'] = $uid; }
        $st->execute($pr);

        if ($st->fetch()) {
            flash('erro', 'Ja existe um usuario com este e-mail.');
        } else {
            if ($uid > 0) {
                /* Impede que o admin remova o proprio acesso */
                if ($uid === $u['id'] && ($perfil !== 'admin' || !$ativo)) {
                    flash('erro', 'Voce nao pode alterar o proprio perfil ou se desativar.');
                    redirecionar('funcionarios.php');
                }

                $campos = [
                    ':nome' => $nome, ':email' => $email, ':perfil' => $perfil,
                    ':telefone' => $telefone ?: null, ':ativo' => $ativo, ':id' => $uid,
                ];

                if ($senha !== '') {
                    $campos[':senha'] = password_hash($senha, PASSWORD_DEFAULT);
                    $pdo->prepare(
                        "UPDATE usuarios SET nome=:nome, email=:email, senha=:senha,
                            perfil=:perfil, telefone=:telefone, ativo=:ativo WHERE id=:id"
                    )->execute($campos);
                } else {
                    $pdo->prepare(
                        "UPDATE usuarios SET nome=:nome, email=:email,
                            perfil=:perfil, telefone=:telefone, ativo=:ativo WHERE id=:id"
                    )->execute($campos);
                }

                flash('sucesso', 'Funcionario atualizado.');
            } else {
                $pdo->prepare(
                    "INSERT INTO usuarios (nome, email, senha, perfil, telefone, ativo)
                     VALUES (:nome, :email, :senha, :perfil, :telefone, :ativo)"
                )->execute([
                    ':nome' => $nome, ':email' => $email,
                    ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                    ':perfil' => $perfil, ':telefone' => $telefone ?: null, ':ativo' => $ativo,
                ]);

                flash('sucesso', 'Funcionario cadastrado com sucesso.');
            }
            redirecionar('funcionarios.php');
        }
    }
}

/* Desativar */
if (isset($_GET['desativar']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'] ?? '')) {
    $uid = (int) $_GET['desativar'];

    if ($uid === $u['id']) {
        flash('erro', 'Voce nao pode desativar o proprio usuario.');
    } else {
        $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = :id")->execute([':id' => $uid]);
        flash('sucesso', 'Funcionario desativado. O historico dele foi preservado.');
    }
    redirecionar('funcionarios.php');
}

/* Edicao */
$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
    $st->execute([':id' => (int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}

// Listagem
$funcionarios = $pdo->query(
    "SELECT us.*,
            COUNT(o.id) AS qtd_orcamentos,
            COALESCE(SUM(CASE WHEN o.status IN ('aprovado','em_producao','concluido')
                         THEN o.valor_total ELSE 0 END),0) AS receita
     FROM usuarios us
     LEFT JOIN orcamentos o ON o.responsavel_id = us.id
     GROUP BY us.id
     ORDER BY us.ativo DESC, us.nome"
)->fetchAll();

$perfis = ['admin' => 'Administrador', 'gerente' => 'Gerente', 'calheiro' => 'Calheiro', 'campo' => 'Tecnico de Campo'];
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Funcionarios';
$pagina_ativa  = 'funcionarios';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Funcionarios</h1>
        <p class="pagina-subtitulo"><?= count($funcionarios) ?> usuario(s) no sistema</p>
    </div>
</div>

<div class="alerta alerta-info">
    <span class="alerta-icone">&#8505;</span>
    <span>
        <strong>Perfis de acesso:</strong>
        Administrador ve tudo e gerencia usuarios &middot;
        Gerente ve o financeiro e os relatorios &middot;
        Calheiro acessa orcamentos, clientes e insumos &middot;
        Tecnico de Campo trabalha apenas com os proprios orcamentos.
    </span>
</div>

<div class="grade-2">

    <!-- FORMULARIO -->
    <div class="cartao" style="align-self:start">
        <div class="cartao-cabecalho">
            <h3><?= $editar ? 'Editar funcionario' : 'Novo funcionario' ?></h3>
            <?php if ($editar): ?>
                <a href="funcionarios.php" class="txt-p">Cancelar</a>
            <?php endif; ?>
        </div>
        <div class="cartao-corpo">
            <form method="POST" class="formulario-grade">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

                <div class="campo col-12">
                    <label for="nome">Nome completo <span class="obrigatorio">*</span></label>
                    <input type="text" id="nome" name="nome" required value="<?= e($editar['nome'] ?? '') ?>">
                </div>

                <div class="campo col-7">
                    <label for="email">E-mail (login) <span class="obrigatorio">*</span></label>
                    <input type="email" id="email" name="email" required value="<?= e($editar['email'] ?? '') ?>">
                </div>

                <div class="campo col-5">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" data-mascara="telefone"
                           value="<?= e($editar['telefone'] ?? '') ?>">
                </div>

                <div class="campo col-6">
                    <label for="senha">
                        Senha <?= $editar ? '' : '<span class="obrigatorio">*</span>' ?>
                    </label>
                    <input type="password" id="senha" name="senha" <?= $editar ? '' : 'required' ?>
                           placeholder="<?= $editar ? 'Deixe vazio para manter' : 'Minimo 6 caracteres' ?>">
                    <span class="campo-dica">Gravada criptografada com BCRYPT</span>
                </div>

                <div class="campo col-6">
                    <label for="perfil">Perfil de acesso</label>
                    <select id="perfil" name="perfil">
                        <?php foreach ($perfis as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= ($editar['perfil'] ?? 'campo') === $k ? 'selected' : '' ?>>
                                <?= e($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-12">
                    <label class="caixa-marcar">
                        <input type="checkbox" name="ativo" value="1"
                            <?= (!$editar || (int) $editar['ativo'] === 1) ? 'checked' : '' ?>>
                        Usuario ativo (pode fazer login)
                    </label>
                </div>

                <div class="formulario-acoes">
                    <button type="submit" class="botao botao-sucesso">
                        <?= $editar ? 'Salvar alteracoes' : 'Cadastrar funcionario' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LISTA -->
    <div class="cartao">
        <div class="cartao-cabecalho"><h3>Equipe</h3></div>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Funcionario</th>
                        <th class="centro">Perfil</th>
                        <?php if (pode('ver_financeiro')): ?><th class="num">Receita</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($funcionarios as $f): ?>
                    <tr style="<?= (int) $f['ativo'] ? '' : 'opacity:.55' ?>">
                        <td>
                            <div class="titulo-celula">
                                <?= e($f['nome']) ?>
                                <?php if ((int) $f['id'] === $u['id']): ?>
                                    <span class="selo selo-laranja">voce</span>
                                <?php endif; ?>
                            </div>
                            <div class="sub-celula"><?= e($f['email']) ?></div>
                            <div class="sub-celula">
                                <?= (int) $f['qtd_orcamentos'] ?> orcamento(s)
                                <?= $f['ultimo_acesso'] ? ' &middot; ultimo acesso ' . data_br($f['ultimo_acesso'], true) : ' &middot; nunca acessou' ?>
                            </div>
                        </td>
                        <td class="centro">
                            <?php
                            $coresPerfil = ['admin' => 'selo-vermelho', 'gerente' => 'selo-roxo',
                                            'calheiro' => 'selo-laranja', 'campo' => 'selo-cinza'];
                            ?>
                            <span class="selo <?= $coresPerfil[$f['perfil']] ?? 'selo-cinza' ?>">
                                <?= e(rotulo_perfil($f['perfil'])) ?>
                            </span>
                            <?php if (!(int) $f['ativo']): ?>
                                <div class="sub-celula mt1">Inativo</div>
                            <?php endif; ?>
                        </td>
                        <?php if (pode('ver_financeiro')): ?>
                            <td class="num negrito"><?= moeda($f['receita']) ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="acoes">
                                <a href="?editar=<?= (int) $f['id'] ?>" class="botao botao-alerta botao-p">Editar</a>
                                <?php if ((int) $f['ativo'] && (int) $f['id'] !== $u['id']): ?>
                                    <a href="?desativar=<?= (int) $f['id'] ?>&csrf=<?= csrf_token() ?>"
                                       class="botao botao-neutro botao-p"
                                       data-confirmar="Desativar o acesso de <?= e($f['nome']) ?>?">Desativar</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
