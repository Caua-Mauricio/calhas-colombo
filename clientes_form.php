<?php
// form de cliente, cadastro e edicao no mesmo arquivo
// (sem ?id na url = cadastro novo, com ?id = ta editando)

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_clientes');

$u = usuario_logado();
$pdo = bd();
$id  = (int) ($_GET['id'] ?? 0);
$editando = $id > 0;

/* Valores padrao do formulario */
$dados = [
    'nome' => '', 'tipo_pessoa' => 'fisica', 'cpf_cnpj' => '', 'telefone' => '',
    'whatsapp' => '', 'email' => '', 'cep' => '', 'endereco' => '', 'numero' => '',
    'complemento' => '', 'bairro' => '', 'cidade' => 'Criciuma', 'uf' => 'SC',
    'ponto_referencia' => '', 'origem' => 'outro', 'observacoes' => '',
];
$erros = [];

// Carrega o registro na edicao
if ($editando) {
    $st = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
    $st->execute([':id' => $id]);
    $registro = $st->fetch();

    if (!$registro) {
        flash('erro', 'Cliente nao encontrado.');
        redirecionar('clientes_listar.php');
    }
    $dados = array_merge($dados, $registro);
}

// Processa o envio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    foreach (array_keys($dados) as $campo) {
        if (isset($_POST[$campo])) {
            $dados[$campo] = trim($_POST[$campo]);
        }
    }

    /* Validacoes */
    if (mb_strlen($dados['nome']) < 3) {
        $erros['nome'] = 'Informe o nome completo (minimo 3 caracteres).';
    }
    if (so_numeros($dados['telefone']) === '' || strlen(so_numeros($dados['telefone'])) < 10) {
        $erros['telefone'] = 'Informe um telefone valido com DDD.';
    }
    if ($dados['endereco'] === '') {
        $erros['endereco'] = 'Informe o endereco da obra ou residencia.';
    }
    if ($dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'E-mail invalido.';
    }
    if (!validar_documento($dados['cpf_cnpj'])) {
        $erros['cpf_cnpj'] = 'CPF ou CNPJ invalido. Confira os digitos.';
    }

    /* Documento duplicado */
    if (!isset($erros['cpf_cnpj']) && so_numeros($dados['cpf_cnpj']) !== '') {
        $sql = "SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(cpf_cnpj,'.',''),'-',''),'/','') = :doc";
        if ($editando) {
            $sql .= ' AND id <> :id';
        }
        $st = $pdo->prepare($sql);
        $p = [':doc' => so_numeros($dados['cpf_cnpj'])];
        if ($editando) {
            $p[':id'] = $id;
        }
        $st->execute($p);
        if ($st->fetch()) {
            $erros['cpf_cnpj'] = 'Ja existe um cliente cadastrado com este documento.';
        }
    }

    /* Grava */
    if (!$erros) {
        $campos = [
            ':nome' => $dados['nome'], ':tipo_pessoa' => $dados['tipo_pessoa'],
            ':cpf_cnpj' => $dados['cpf_cnpj'] ?: null, ':telefone' => $dados['telefone'],
            ':whatsapp' => $dados['whatsapp'] ?: null, ':email' => $dados['email'] ?: null,
            ':cep' => $dados['cep'] ?: null, ':endereco' => $dados['endereco'],
            ':numero' => $dados['numero'] ?: null, ':complemento' => $dados['complemento'] ?: null,
            ':bairro' => $dados['bairro'] ?: null, ':cidade' => $dados['cidade'] ?: null,
            ':uf' => $dados['uf'] ?: null, ':ponto_referencia' => $dados['ponto_referencia'] ?: null,
            ':origem' => $dados['origem'], ':observacoes' => $dados['observacoes'] ?: null,
        ];

        try {
            if ($editando) {
                $campos[':id'] = $id;
                $pdo->prepare(
                    "UPDATE clientes SET
                        nome=:nome, tipo_pessoa=:tipo_pessoa, cpf_cnpj=:cpf_cnpj, telefone=:telefone,
                        whatsapp=:whatsapp, email=:email, cep=:cep, endereco=:endereco, numero=:numero,
                        complemento=:complemento, bairro=:bairro, cidade=:cidade, uf=:uf,
                        ponto_referencia=:ponto_referencia, origem=:origem, observacoes=:observacoes
                     WHERE id=:id"
                )->execute($campos);

                flash('sucesso', 'Cliente atualizado com sucesso.');
            } else {
                $pdo->prepare(
                    "INSERT INTO clientes
                       (nome, tipo_pessoa, cpf_cnpj, telefone, whatsapp, email, cep, endereco, numero,
                        complemento, bairro, cidade, uf, ponto_referencia, origem, observacoes)
                     VALUES
                       (:nome, :tipo_pessoa, :cpf_cnpj, :telefone, :whatsapp, :email, :cep, :endereco, :numero,
                        :complemento, :bairro, :cidade, :uf, :ponto_referencia, :origem, :observacoes)"
                )->execute($campos);

                $novoId = (int) $pdo->lastInsertId();
                flash('sucesso', 'Cliente cadastrado com sucesso.');
            }

            redirecionar('clientes_listar.php');
        } catch (PDOException $e) {
            $erros['geral'] = 'Erro ao gravar no banco: ' . $e->getMessage();
        }
    }
}

$origens = [
    'indicacao' => 'Indicacao', 'instagram' => 'Instagram', 'google' => 'Google',
    'fachada' => 'Fachada da loja', 'outro' => 'Outro',
];
$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR',
        'PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Cliente';
$pagina_ativa  = 'clientes_listar';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <div class="trilha"><a href="clientes_listar.php">Clientes</a> / <?= $editando ? 'Editar' : 'Novo' ?></div>
        <h1 class="pagina-titulo"><?= $editando ? 'Editar cliente' : 'Novo cliente' ?></h1>
        <p class="pagina-subtitulo">Os campos marcados com <span style="color:var(--vermelho-500)">*</span> sao obrigatorios</p>
    </div>
    <div class="pagina-acoes">
        <a href="clientes_listar.php" class="botao botao-neutro">Voltar</a>
    </div>
</div>

<?php if (isset($erros['geral'])): ?>
    <div class="alerta alerta-erro"><span class="alerta-icone">&#33;</span><span><?= e($erros['geral']) ?></span></div>
<?php endif; ?>

<div class="cartao">
    <div class="cartao-corpo">
        <form method="POST" class="formulario-grade" novalidate>
            <?= csrf_campo() ?>

            <div class="secao-formulario">Identificacao</div>

            <div class="campo col-8 <?= isset($erros['nome']) ? 'erro' : '' ?>">
                <label for="nome">Nome completo / Razao social <span class="obrigatorio">*</span></label>
                <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>"
                       placeholder="Ex: Roberto Construcoes LTDA" required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="campo-erro"><?= e($erros['nome']) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo col-4">
                <label for="tipo_pessoa">Tipo de pessoa</label>
                <select id="tipo_pessoa" name="tipo_pessoa">
                    <option value="fisica"   <?= $dados['tipo_pessoa'] === 'fisica'   ? 'selected' : '' ?>>Pessoa fisica</option>
                    <option value="juridica" <?= $dados['tipo_pessoa'] === 'juridica' ? 'selected' : '' ?>>Pessoa juridica</option>
                </select>
            </div>

            <div class="campo col-4 <?= isset($erros['cpf_cnpj']) ? 'erro' : '' ?>">
                <label for="cpf_cnpj">CPF / CNPJ</label>
                <input type="text" id="cpf_cnpj" name="cpf_cnpj" data-mascara="documento"
                       value="<?= e($dados['cpf_cnpj']) ?>" placeholder="000.000.000-00">
                <?php if (isset($erros['cpf_cnpj'])): ?>
                    <span class="campo-erro"><?= e($erros['cpf_cnpj']) ?></span>
                <?php else: ?>
                    <span class="campo-dica">Validado automaticamente pelos digitos verificadores</span>
                <?php endif; ?>
            </div>

            <div class="campo col-4">
                <label for="origem">Como conheceu a empresa</label>
                <select id="origem" name="origem">
                    <?php foreach ($origens as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= $dados['origem'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="secao-formulario">Contato</div>

            <div class="campo col-4 <?= isset($erros['telefone']) ? 'erro' : '' ?>">
                <label for="telefone">Telefone principal <span class="obrigatorio">*</span></label>
                <input type="text" id="telefone" name="telefone" data-mascara="telefone"
                       value="<?= e($dados['telefone']) ?>" placeholder="(48) 99999-9999" required>
                <?php if (isset($erros['telefone'])): ?>
                    <span class="campo-erro"><?= e($erros['telefone']) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo col-4">
                <label for="whatsapp">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" data-mascara="telefone"
                       value="<?= e($dados['whatsapp']) ?>" placeholder="(48) 99999-9999">
                <span class="campo-dica">Usado para enviar a proposta ao cliente</span>
            </div>

            <div class="campo col-4 <?= isset($erros['email']) ? 'erro' : '' ?>">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>"
                       placeholder="cliente@email.com">
                <?php if (isset($erros['email'])): ?>
                    <span class="campo-erro"><?= e($erros['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="secao-formulario">Endereco da obra</div>

            <div class="campo col-3">
                <label for="cep">CEP</label>
                <input type="text" id="cep" name="cep" data-mascara="cep" data-buscar-cep
                       value="<?= e($dados['cep']) ?>" placeholder="88800-000">
                <span class="campo-dica">Preenche o endereco automaticamente</span>
            </div>

            <div class="campo col-6 <?= isset($erros['endereco']) ? 'erro' : '' ?>">
                <label for="endereco">Logradouro <span class="obrigatorio">*</span></label>
                <input type="text" id="endereco" name="endereco" value="<?= e($dados['endereco']) ?>"
                       placeholder="Rua, avenida, servidao..." required>
                <?php if (isset($erros['endereco'])): ?>
                    <span class="campo-erro"><?= e($erros['endereco']) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo col-3">
                <label for="numero">Numero</label>
                <input type="text" id="numero" name="numero" value="<?= e($dados['numero']) ?>" placeholder="123">
            </div>

            <div class="campo col-4">
                <label for="bairro">Bairro</label>
                <input type="text" id="bairro" name="bairro" value="<?= e($dados['bairro']) ?>">
            </div>

            <div class="campo col-4">
                <label for="cidade">Cidade</label>
                <input type="text" id="cidade" name="cidade" value="<?= e($dados['cidade']) ?>">
            </div>

            <div class="campo col-2">
                <label for="uf">UF</label>
                <select id="uf" name="uf">
                    <?php foreach ($ufs as $sigla): ?>
                        <option value="<?= $sigla ?>" <?= $dados['uf'] === $sigla ? 'selected' : '' ?>><?= $sigla ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo col-2">
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento"
                       value="<?= e($dados['complemento']) ?>" placeholder="Casa, apto">
            </div>

            <div class="campo col-12">
                <label for="ponto_referencia">Ponto de referencia</label>
                <input type="text" id="ponto_referencia" name="ponto_referencia"
                       value="<?= e($dados['ponto_referencia']) ?>"
                       placeholder="Ex: proximo ao mercado, portao azul">
            </div>

            <div class="secao-formulario">Observacoes</div>

            <div class="campo col-12">
                <label for="observacoes">Anotacoes da equipe</label>
                <textarea id="observacoes" name="observacoes" rows="3"
                          placeholder="Ex: cachorro bravo no quintal, atender apenas pela manha..."><?= e($dados['observacoes']) ?></textarea>
                <span class="campo-dica">Informacoes uteis para a equipe de campo</span>
            </div>

            <div class="formulario-acoes">
                <button type="submit" class="botao botao-sucesso botao-g">
                    <?= $editando ? 'Salvar alteracoes' : 'Cadastrar cliente' ?>
                </button>
                <a href="clientes_listar.php" class="botao botao-neutro botao-g">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
