<?php
// tela de login

require_once __DIR__ . '/inclui/auth.php';

if (esta_logado()) {
    redirecionar('index.php');
}

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Informe o e-mail e a senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'O e-mail informado nao e valido.';
    } elseif (tentar_login($email, $senha)) {
        flash('sucesso', 'Bem-vindo de volta, ' . $_SESSION['usuario_nome'] . '!');
        redirecionar('index.php');
    } else {
        $erro = 'E-mail ou senha incorretos.';
    }
}

$contas_demo = [
    ['email' => 'admin@colombo.com',   'senha' => 'admin123',   'perfil' => 'Administrador'],
    ['email' => 'gerente@colombo.com', 'senha' => 'gerente123', 'perfil' => 'Gerente'],
    ['email' => 'caua@colombo.com',    'senha' => '123456',     'perfil' => 'Tecnico de Campo'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar &middot; <?= e(EMPRESA_NOME) ?></title>
<link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="pagina-login">

<div class="caixa-login">

    <div class="login-topo">
        <img src="assets/img/logo-icone.png" alt="" class="login-logo">
        <h1><?= e(EMPRESA_NOME) ?></h1>
        <p>Sistema de Gestao de Orcamentos</p>
    </div>

    <div class="login-corpo">

        <?php if ($erro): ?>
            <div class="alerta alerta-erro">
                <span class="alerta-icone">&#33;</span>
                <span><?= e($erro) ?></span>
            </div>
        <?php endif; ?>

        <?= flash_exibir() ?>

        <form method="POST" autocomplete="on">
            <?= csrf_campo() ?>

            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email"
                       value="<?= e($email) ?>"
                       placeholder="seu@email.com" required autofocus>
            </div>

            <div class="campo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha"
                       placeholder="Digite sua senha" required>
            </div>

            <button type="submit" class="botao botao-primario botao-bloco botao-g">Entrar</button>
        </form>

        <div class="contas-demo">
            <h5>Contas para teste &mdash; clique para preencher</h5>
            <?php foreach ($contas_demo as $c): ?>
                <div class="conta-demo"
                     data-demo-email="<?= e($c['email']) ?>"
                     data-demo-senha="<?= e($c['senha']) ?>">
                    <span><?= e($c['email']) ?></span>
                    <code><?= e($c['perfil']) ?></code>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="login-rodape">
        &copy; <?= date('Y') ?> <?= e(EMPRESA_NOME) ?> &middot; Todos os direitos reservados
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
