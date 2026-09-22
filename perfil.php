<?php
// tela de perfil: o usuario ve os proprios dados e troca a senha

require_once __DIR__ . '/inclui/auth.php';
exigir_login();

$u = usuario_logado();
$pdo = bd();

// Atualizar dados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'dados') {
    csrf_validar();

    $nome     = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if (mb_strlen($nome) < 3) {
        flash('erro', 'Informe o nome completo.');
    } else {
        $pdo->prepare("UPDATE usuarios SET nome = :n, telefone = :t WHERE id = :id")
            ->execute([':n' => $nome, ':t' => $telefone ?: null, ':id' => $u['id']]);

        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_telefone'] = $telefone;

        flash('sucesso', 'Dados atualizados.');
    }
    redirecionar('perfil.php');
}

/* Trocar senha */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'senha') {
    csrf_validar();

    $atual     = $_POST['senha_atual'] ?? '';
    $nova      = $_POST['senha_nova'] ?? '';
    $confirma  = $_POST['senha_confirma'] ?? '';

    $st = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
    $st->execute([':id' => $u['id']]);
    $hashAtual = $st->fetchColumn();

    if (!password_verify($atual, $hashAtual)) {
        flash('erro', 'A senha atual esta incorreta.');
    } elseif (strlen($nova) < 6) {
        flash('erro', 'A nova senha precisa ter no minimo 6 caracteres.');
    } elseif ($nova !== $confirma) {
        flash('erro', 'A confirmacao nao confere com a nova senha.');
    } else {
        $pdo->prepare("UPDATE usuarios SET senha = :s WHERE id = :id")
            ->execute([':s' => password_hash($nova, PASSWORD_DEFAULT), ':id' => $u['id']]);

        flash('sucesso', 'Senha alterada com sucesso.');
    }
    redirecionar('perfil.php');
}

// Dados atuais
$st = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$st->execute([':id' => $u['id']]);
$usuario = $st->fetch();

$st = $pdo->prepare(
    "SELECT COUNT(*) AS qtd,
            COALESCE(SUM(CASE WHEN status IN ('aprovado','em_producao','concluido') THEN valor_total ELSE 0 END),0) AS receita
     FROM orcamentos WHERE responsavel_id = :id"
);
$st->execute([':id' => $u['id']]);
$estatisticas = $st->fetch();

/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Meu perfil';
$pagina_ativa  = 'perfil';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-titulo">Meu perfil</h1>
        <p class="pagina-subtitulo">
            <?= e(rotulo_perfil($usuario['perfil'])) ?> &middot;
            cadastrado em <?= data_br($usuario['data_cadastro']) ?>
        </p>
    </div>
</div>

<div class="grade-indicadores">
    <div class="indicador">
        <div class="indicador-rotulo">Orcamentos sob sua responsabilidade</div>
        <div class="indicador-valor"><?= (int) $estatisticas['qtd'] ?></div>
    </div>
    <?php if (pode('ver_financeiro')): ?>
        <div class="indicador verde">
            <div class="indicador-rotulo">Receita gerada</div>
            <div class="indicador-valor"><?= moeda($estatisticas['receita']) ?></div>
        </div>
    <?php endif; ?>
    <div class="indicador roxo">
        <div class="indicador-rotulo">Ultimo acesso</div>
        <div class="indicador-valor" style="font-size:19px">
            <?= data_br($usuario['ultimo_acesso'], true) ?>
        </div>
    </div>
</div>

<div class="grade-2">

    <div class="cartao">
        <div class="cartao-cabecalho"><h3>Dados pessoais</h3></div>
        <div class="cartao-corpo">
            <form method="POST" class="formulario-grade">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="dados">

                <div class="campo col-12">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" required value="<?= e($usuario['nome']) ?>">
                </div>

                <div class="campo col-12">
                    <label for="email_ro">E-mail (login)</label>
                    <input type="email" id="email_ro" value="<?= e($usuario['email']) ?>" readonly>
                    <span class="campo-dica">Apenas o administrador pode alterar o e-mail</span>
                </div>

                <div class="campo col-12">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" data-mascara="telefone"
                           value="<?= e($usuario['telefone'] ?? '') ?>">
                </div>

                <div class="formulario-acoes">
                    <button type="submit" class="botao botao-sucesso">Salvar dados</button>
                </div>
            </form>
        </div>
    </div>

    <div class="cartao">
        <div class="cartao-cabecalho"><h3>Alterar senha</h3></div>
        <div class="cartao-corpo">
            <form method="POST" class="formulario-grade">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="senha">

                <div class="campo col-12">
                    <label for="senha_atual">Senha atual</label>
                    <input type="password" id="senha_atual" name="senha_atual" required>
                </div>

                <div class="campo col-12">
                    <label for="senha_nova">Nova senha</label>
                    <input type="password" id="senha_nova" name="senha_nova" required minlength="6">
                    <span class="campo-dica">Minimo de 6 caracteres</span>
                </div>

                <div class="campo col-12">
                    <label for="senha_confirma">Confirmar nova senha</label>
                    <input type="password" id="senha_confirma" name="senha_confirma" required minlength="6">
                </div>

                <div class="formulario-acoes">
                    <button type="submit" class="botao botao-primario">Alterar senha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
