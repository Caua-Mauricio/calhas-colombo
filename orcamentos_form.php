<?php
// tela de novo orcamento / edicao. da pra ir adicionando item na
// hora (o preco puxa direto do cadastro de produto), tem a opcao
// "Outro" pra item avulso que nao ta no catalogo, e um espaco pra
// anotar as medidas tiradas na visita. o subtotal, desconto e total
// vao recalculando em tempo real, e o custo estimado fica salvo
// junto pra dar pra calcular margem depois nos relatorios

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_orcamentos');

$u = usuario_logado();
$pdo = bd();
$id  = (int) ($_GET['id'] ?? 0);
$editando = $id > 0;

// Valores padrao
$orc = [
    'cliente_id' => (int) ($_GET['cliente_id'] ?? 0),
    'responsavel_id' => $u['id'],
    'data_entrega_prevista' => date('Y-m-d', strtotime('+30 days')),
    'validade_dias' => VALIDADE_PADRAO_DIAS,
    'status' => 'rascunho',
    'tipo_telhado' => '', 'corte_chapa' => '', 'condicao_acesso' => 'Escada Simples',
    'acabamento_cor' => 'Natural',
    'inclui_rufos' => 1, 'inclui_condutores' => 1, 'inclui_veda_calha' => 1, 'inclui_cabeceiras' => 0,
    'valor_mao_obra' => MAO_OBRA_PADRAO,
    'percentual_desconto' => 0,
    'forma_pagamento' => 'Pix',
    'observacoes' => '',
];
$itens   = [];
$medidas = [];
$erros   = [];

// Carrega registro existente
if ($editando) {
    $st = $pdo->prepare("SELECT * FROM orcamentos WHERE id = :id");
    $st->execute([':id' => $id]);
    $registro = $st->fetch();

    if (!$registro) {
        flash('erro', 'Orcamento nao encontrado.');
        redirecionar('orcamentos_listar.php');
    }
    $orc = array_merge($orc, $registro);

    $st = $pdo->prepare(
        "SELECT i.*, p.nome AS produto_nome FROM itens_orcamento i
         JOIN produtos p ON p.id = i.produto_id
         WHERE i.orcamento_id = :id ORDER BY i.id"
    );
    $st->execute([':id' => $id]);
    $itens = $st->fetchAll();

    $st = $pdo->prepare("SELECT * FROM medidas WHERE orcamento_id = :id ORDER BY id");
    $st->execute([':id' => $id]);
    $medidas = $st->fetchAll();
}

// Processa envio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $orc['cliente_id']            = (int) ($_POST['cliente_id'] ?? 0);
    $orc['responsavel_id']        = (int) ($_POST['responsavel_id'] ?? 0);
    $orc['data_entrega_prevista'] = $_POST['data_entrega_prevista'] ?: null;
    $orc['validade_dias']         = max(1, (int) ($_POST['validade_dias'] ?? 15));
    $orc['status']                = $_POST['status'] ?? 'rascunho';
    $orc['tipo_telhado']          = trim($_POST['tipo_telhado'] ?? '');
    $orc['corte_chapa']           = trim($_POST['corte_chapa'] ?? '');
    $orc['condicao_acesso']       = trim($_POST['condicao_acesso'] ?? '');
    $orc['acabamento_cor']        = trim($_POST['acabamento_cor'] ?? '');
    $orc['inclui_rufos']          = isset($_POST['inclui_rufos']) ? 1 : 0;
    $orc['inclui_condutores']     = isset($_POST['inclui_condutores']) ? 1 : 0;
    $orc['inclui_veda_calha']     = isset($_POST['inclui_veda_calha']) ? 1 : 0;
    $orc['inclui_cabeceiras']     = isset($_POST['inclui_cabeceiras']) ? 1 : 0;
    $orc['valor_mao_obra']        = valor_float($_POST['valor_mao_obra'] ?? 0);
    $orc['percentual_desconto']   = min(100, max(0, valor_float($_POST['percentual_desconto'] ?? 0)));
    $orc['forma_pagamento']       = trim($_POST['forma_pagamento'] ?? '');
    $orc['observacoes']           = trim($_POST['observacoes'] ?? '');

    /* Validacoes */
    if ($orc['cliente_id'] <= 0)     { $erros['cliente_id'] = 'Selecione o cliente.'; }
    if ($orc['responsavel_id'] <= 0) { $erros['responsavel_id'] = 'Selecione o responsavel tecnico.'; }

    /* Monta os itens vindos do formulario */
    $itensPost = [];
    $produtoIds = $_POST['item_produto_id'] ?? [];

    foreach ($produtoIds as $i => $produtoId) {
        $qtd   = valor_float($_POST['item_quantidade'][$i] ?? 0);
        $vUnit = valor_float($_POST['item_valor'][$i] ?? 0);
        $nomeCustom = trim($_POST['item_nome_custom'][$i] ?? '');

        if ($qtd <= 0) {
            continue;
        }

        $itensPost[] = [
            'produto_id'  => $produtoId,
            'nome_custom' => $nomeCustom,
            'quantidade'  => $qtd,
            'valor'       => $vUnit,
        ];
    }

    if (!$itensPost) {
        $erros['itens'] = 'Adicione pelo menos um item ao orcamento.';
    }

    /* Grava tudo dentro de uma transacao */
    if (!$erros) {
        try {
            $pdo->beginTransaction();

            /* 1. Resolve os produtos (cadastra os avulsos) e calcula totais */
            $valorMateriais = 0.0;
            $itensFinais    = [];

            foreach ($itensPost as $item) {
                if ($item['produto_id'] === 'outro') {
                    if ($item['nome_custom'] === '') {
                        continue;
                    }
                    $stp = $pdo->prepare(
                        "INSERT INTO produtos (nome, categoria, unidade, preco_unitario, ativo)
                         VALUES (:nome, 'Avulso', 'un', :preco, 1)"
                    );
                    $stp->execute([
                        ':nome'  => $item['nome_custom'],
                        ':preco' => $item['valor'],
                    ]);
                    $produtoId = (int) $pdo->lastInsertId();
                } else {
                    $produtoId = (int) $item['produto_id'];
                }

                if ($produtoId <= 0) {
                    continue;
                }

                $subtotal = $item['quantidade'] * $item['valor'];
                $valorMateriais += $subtotal;

                $itensFinais[] = [
                    'produto_id' => $produtoId,
                    'quantidade' => $item['quantidade'],
                    'valor'      => $item['valor'],
                    'subtotal'   => $subtotal,
                ];
            }

            $subtotalGeral = $valorMateriais + $orc['valor_mao_obra'];
            $valorDesconto = $subtotalGeral * ($orc['percentual_desconto'] / 100);
            $valorTotal    = $subtotalGeral - $valorDesconto;

            /* 2. Grava o cabecalho */
            $campos = [
                ':cliente_id' => $orc['cliente_id'],
                ':responsavel_id' => $orc['responsavel_id'],
                ':data_entrega_prevista' => $orc['data_entrega_prevista'],
                ':validade_dias' => $orc['validade_dias'],
                ':status' => $orc['status'],
                ':tipo_telhado' => $orc['tipo_telhado'] ?: null,
                ':corte_chapa' => $orc['corte_chapa'] ?: null,
                ':condicao_acesso' => $orc['condicao_acesso'] ?: null,
                ':acabamento_cor' => $orc['acabamento_cor'] ?: null,
                ':inclui_rufos' => $orc['inclui_rufos'],
                ':inclui_condutores' => $orc['inclui_condutores'],
                ':inclui_veda_calha' => $orc['inclui_veda_calha'],
                ':inclui_cabeceiras' => $orc['inclui_cabeceiras'],
                ':valor_materiais' => $valorMateriais,
                ':valor_mao_obra' => $orc['valor_mao_obra'],
                ':percentual_desconto' => $orc['percentual_desconto'],
                ':valor_desconto' => $valorDesconto,
                ':valor_total' => $valorTotal,
                ':forma_pagamento' => $orc['forma_pagamento'] ?: null,
                ':observacoes' => $orc['observacoes'] ?: null,
            ];

            if ($editando) {
                $campos[':id'] = $id;
                $pdo->prepare(
                    "UPDATE orcamentos SET
                        cliente_id=:cliente_id, responsavel_id=:responsavel_id,
                        data_entrega_prevista=:data_entrega_prevista, validade_dias=:validade_dias,
                        status=:status, tipo_telhado=:tipo_telhado, corte_chapa=:corte_chapa,
                        condicao_acesso=:condicao_acesso, acabamento_cor=:acabamento_cor,
                        inclui_rufos=:inclui_rufos, inclui_condutores=:inclui_condutores,
                        inclui_veda_calha=:inclui_veda_calha, inclui_cabeceiras=:inclui_cabeceiras,
                        valor_materiais=:valor_materiais, valor_mao_obra=:valor_mao_obra,
                        percentual_desconto=:percentual_desconto, valor_desconto=:valor_desconto,
                        valor_total=:valor_total,
                        forma_pagamento=:forma_pagamento, observacoes=:observacoes
                     WHERE id=:id"
                )->execute($campos);

                $orcamentoId = $id;
                $pdo->prepare("DELETE FROM itens_orcamento WHERE orcamento_id = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM medidas WHERE orcamento_id = :id")->execute([':id' => $id]);
            } else {
                $campos[':numero'] = gerar_numero_orcamento();
                $campos[':token']  = bin2hex(random_bytes(16));

                $pdo->prepare(
                    "INSERT INTO orcamentos
                       (numero, token, cliente_id, responsavel_id, data_entrega_prevista, validade_dias,
                        status, tipo_telhado, corte_chapa, condicao_acesso, acabamento_cor,
                        inclui_rufos, inclui_condutores, inclui_veda_calha, inclui_cabeceiras,
                        valor_materiais, valor_mao_obra, percentual_desconto, valor_desconto,
                        valor_total, forma_pagamento, observacoes)
                     VALUES
                       (:numero, :token, :cliente_id, :responsavel_id, :data_entrega_prevista, :validade_dias,
                        :status, :tipo_telhado, :corte_chapa, :condicao_acesso, :acabamento_cor,
                        :inclui_rufos, :inclui_condutores, :inclui_veda_calha, :inclui_cabeceiras,
                        :valor_materiais, :valor_mao_obra, :percentual_desconto, :valor_desconto,
                        :valor_total, :forma_pagamento, :observacoes)"
                )->execute($campos);

                $orcamentoId = (int) $pdo->lastInsertId();
            }

            /* 3. Grava os itens */
            $stItem = $pdo->prepare(
                "INSERT INTO itens_orcamento
                   (orcamento_id, produto_id, quantidade, valor_unitario, valor_total)
                 VALUES (:o, :p, :q, :v, :t)"
            );
            foreach ($itensFinais as $it) {
                $stItem->execute([
                    ':o' => $orcamentoId, ':p' => $it['produto_id'], ':q' => $it['quantidade'],
                    ':v' => $it['valor'], ':t' => $it['subtotal'],
                ]);
            }

            /* 4. Grava as medidas de campo */
            $stMedida = $pdo->prepare(
                "INSERT INTO medidas (orcamento_id, tipo, valor, unidade, observacao)
                 VALUES (:o, :t, :v, :u, :obs)"
            );
            foreach (($_POST['medida_tipo'] ?? []) as $i => $tipo) {
                $tipo  = trim($tipo);
                $valor = valor_float($_POST['medida_valor'][$i] ?? 0);
                if ($tipo === '' || $valor <= 0) {
                    continue;
                }
                $stMedida->execute([
                    ':o' => $orcamentoId,
                    ':t' => $tipo,
                    ':v' => $valor,
                    ':u' => trim($_POST['medida_unidade'][$i] ?? 'm'),
                    ':obs' => trim($_POST['medida_obs'][$i] ?? '') ?: null,
                ]);
            }

            $pdo->commit();

            flash('sucesso', $editando ? 'Orcamento atualizado com sucesso.' : 'Orcamento criado com sucesso.');
            redirecionar('orcamentos_detalhes.php?id=' . $orcamentoId);

        } catch (PDOException $ex) {
            $pdo->rollBack();
            $erros['geral'] = 'Erro ao gravar: ' . $ex->getMessage();
        }
    }
}

/* Dados auxiliares */
$clientes = $pdo->query("SELECT id, nome, telefone FROM clientes WHERE ativo = 1 ORDER BY nome")->fetchAll();
$usuarios = $pdo->query("SELECT id, nome, perfil FROM usuarios WHERE ativo = 1 ORDER BY nome")->fetchAll();
$produtos = $pdo->query(
    "SELECT id, nome, unidade, preco_unitario FROM produtos WHERE ativo = 1 ORDER BY nome"
)->fetchAll();

$telhados = ['Telha Ceramica', 'Telha Americana', 'Fibrocimento', 'Telha Metalica', 'Laje Impermeabilizada', 'Telha Shingle'];
$acessos  = ['Escada Simples', 'Escada Extensiva', 'Andaime', 'Balancim / Cinto de Seguranca', 'Plataforma Elevatoria'];
$cores    = ['Natural', 'Branco', 'Marrom', 'Preto', 'Galvanizado', 'Cobre'];
$pagamentos = ['Pix', 'Dinheiro', 'Cartao de Credito', 'Cartao de Debito', 'Boleto', 'Parcelado na Casa'];
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Orcamento';
$pagina_ativa  = 'orcamentos_listar';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<div class="pagina-topo">
    <div>
        <div class="trilha"><a href="orcamentos_listar.php">Orcamentos</a> / <?= $editando ? e($orc['numero']) : 'Novo' ?></div>
        <h1 class="pagina-titulo"><?= $editando ? 'Editar orcamento' : 'Novo orcamento' ?></h1>
        <p class="pagina-subtitulo">Ficha de campo e proposta comercial</p>
    </div>
    <div class="pagina-acoes">
        <a href="orcamentos_listar.php" class="botao botao-neutro">Voltar</a>
    </div>
</div>

<?php if (isset($erros['geral'])): ?>
    <div class="alerta alerta-erro"><span class="alerta-icone">&#33;</span><span><?= e($erros['geral']) ?></span></div>
<?php endif; ?>
<?php if (isset($erros['itens'])): ?>
    <div class="alerta alerta-aviso"><span class="alerta-icone">&#9888;</span><span><?= e($erros['itens']) ?></span></div>
<?php endif; ?>

<form method="POST" id="formOrcamento">
    <?= csrf_campo() ?>

    <!-- 1. ATENDIMENTO -->
    <div class="cartao">
        <div class="cartao-cabecalho"><h3>1. Dados do atendimento</h3></div>
        <div class="cartao-corpo">
            <div class="formulario-grade">

                <div class="campo col-6 <?= isset($erros['cliente_id']) ? 'erro' : '' ?>">
                    <label for="cliente_id">Cliente <span class="obrigatorio">*</span></label>
                    <select id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione o cliente</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) $orc['cliente_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['nome']) ?> &mdash; <?= e(formatar_telefone($c['telefone'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erros['cliente_id'])): ?>
                        <span class="campo-erro"><?= e($erros['cliente_id']) ?></span>
                    <?php else: ?>
                        <span class="campo-dica"><a href="clientes_form.php">Cadastrar novo cliente</a></span>
                    <?php endif; ?>
                </div>

                <div class="campo col-6 <?= isset($erros['responsavel_id']) ? 'erro' : '' ?>">
                    <label for="responsavel_id">Responsavel tecnico <span class="obrigatorio">*</span></label>
                    <select id="responsavel_id" name="responsavel_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($usuarios as $usr): ?>
                            <option value="<?= (int) $usr['id'] ?>"
                                <?= (int) $orc['responsavel_id'] === (int) $usr['id'] ? 'selected' : '' ?>>
                                <?= e($usr['nome']) ?> (<?= e(rotulo_perfil($usr['perfil'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-3">
                    <label for="data_entrega_prevista">Previsao de instalacao</label>
                    <input type="date" id="data_entrega_prevista" name="data_entrega_prevista"
                           value="<?= e($orc['data_entrega_prevista']) ?>">
                </div>

                <div class="campo col-3">
                    <label for="validade_dias">Validade da proposta (dias)</label>
                    <input type="number" id="validade_dias" name="validade_dias" min="1" max="180"
                           value="<?= (int) $orc['validade_dias'] ?>">
                </div>

                <div class="campo col-3">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach (status_orcamento() as $k => $s): ?>
                            <option value="<?= e($k) ?>" <?= $orc['status'] === $k ? 'selected' : '' ?>>
                                <?= e($s['rotulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-3">
                    <label for="condicao_acesso">Acesso ao telhado</label>
                    <select id="condicao_acesso" name="condicao_acesso">
                        <?php foreach ($acessos as $a): ?>
                            <option value="<?= e($a) ?>" <?= $orc['condicao_acesso'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. ESPECIFICACAO TECNICA -->
    <div class="cartao">
        <div class="cartao-cabecalho"><h3>2. Especificacao tecnica</h3></div>
        <div class="cartao-corpo">
            <div class="formulario-grade">

                <div class="campo col-4">
                    <label for="tipo_telhado">Tipo de telhado</label>
                    <select id="tipo_telhado" name="tipo_telhado">
                        <option value="">Nao informado</option>
                        <?php foreach ($telhados as $t): ?>
                            <option value="<?= e($t) ?>" <?= $orc['tipo_telhado'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-4">
                    <label for="corte_chapa">Corte da chapa</label>
                    <input type="text" id="corte_chapa" name="corte_chapa"
                           value="<?= e($orc['corte_chapa']) ?>" placeholder="Ex: Corte 33, Corte 40">
                    <span class="campo-dica">Largura da bobina utilizada, em centimetros</span>
                </div>

                <div class="campo col-4">
                    <label for="acabamento_cor">Cor / acabamento</label>
                    <select id="acabamento_cor" name="acabamento_cor">
                        <?php foreach ($cores as $cor): ?>
                            <option value="<?= e($cor) ?>" <?= $orc['acabamento_cor'] === $cor ? 'selected' : '' ?>><?= e($cor) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo col-12">
                    <label>Itens inclusos no servico</label>
                    <div class="grupo-caixas">
                        <label class="caixa-marcar">
                            <input type="checkbox" name="inclui_rufos" value="1" <?= $orc['inclui_rufos'] ? 'checked' : '' ?>> Rufos
                        </label>
                        <label class="caixa-marcar">
                            <input type="checkbox" name="inclui_condutores" value="1" <?= $orc['inclui_condutores'] ? 'checked' : '' ?>> Condutores
                        </label>
                        <label class="caixa-marcar">
                            <input type="checkbox" name="inclui_veda_calha" value="1" <?= $orc['inclui_veda_calha'] ? 'checked' : '' ?>> Veda calha
                        </label>
                        <label class="caixa-marcar">
                            <input type="checkbox" name="inclui_cabeceiras" value="1" <?= $orc['inclui_cabeceiras'] ? 'checked' : '' ?>> Cabeceiras
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. MEDIDAS DE CAMPO -->
    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>3. Medidas de campo</h3>
            <button type="button" class="botao botao-contorno botao-p" onclick="adicionarMedida()">+ Adicionar medida</button>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela" id="tabelaMedidas">
                <thead>
                    <tr>
                        <th>Descricao</th>
                        <th style="width:130px">Valor</th>
                        <th style="width:110px">Unidade</th>
                        <th>Observacao</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody id="corpoMedidas"></tbody>
            </table>
        </div>
        <div class="cartao-rodape txt-p txt-suave">
            Registre aqui as medicoes feitas na obra. Elas aparecem na proposta impressa.
        </div>
    </div>

    <!-- 4. ITENS E MATERIAIS -->
    <div class="cartao">
        <div class="cartao-cabecalho">
            <h3>4. Itens e materiais</h3>
            <button type="button" class="botao botao-contorno botao-p" onclick="adicionarItem()">+ Adicionar item</button>
        </div>
        <div class="tabela-rolagem">
            <table class="tabela" id="tabelaItens">
                <thead>
                    <tr>
                        <th>Produto / material</th>
                        <th style="width:120px">Quantidade</th>
                        <th style="width:140px">Valor unitario</th>
                        <th style="width:140px" class="num">Subtotal</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody id="corpoItens"></tbody>
            </table>
        </div>
    </div>

    <!-- 5. FECHAMENTO -->
    <div class="cartao">
        <div class="cartao-cabecalho"><h3>5. Fechamento financeiro</h3></div>
        <div class="cartao-corpo">
            <div class="formulario-grade">

                <div class="campo col-3">
                    <label for="valor_materiais_exibe">Total de materiais</label>
                    <input type="text" id="valor_materiais_exibe" readonly value="R$ 0,00">
                </div>

                <div class="campo col-3">
                    <label for="valor_mao_obra">Mao de obra / instalacao (R$)</label>
                    <input type="number" step="0.01" min="0" id="valor_mao_obra" name="valor_mao_obra"
                           value="<?= number_format((float) $orc['valor_mao_obra'], 2, '.', '') ?>"
                           oninput="recalcular()">
                </div>

                <div class="campo col-3">
                    <label for="forma_pagamento">Forma de pagamento</label>
                    <select id="forma_pagamento" name="forma_pagamento" onchange="sugerirDesconto()">
                        <?php foreach ($pagamentos as $p): ?>
                            <option value="<?= e($p) ?>" <?= $orc['forma_pagamento'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="campo-dica">Pix e dinheiro sugerem <?= numero(DESCONTO_A_VISTA, 0) ?>% de desconto</span>
                </div>

                <div class="campo col-3">
                    <label for="percentual_desconto">Desconto (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="percentual_desconto"
                           name="percentual_desconto"
                           value="<?= number_format((float) $orc['percentual_desconto'], 2, '.', '') ?>"
                           oninput="recalcular()">
                </div>

                <div class="campo col-12">
                    <label for="observacoes">Observacoes e condicoes especiais</label>
                    <textarea id="observacoes" name="observacoes" rows="3"
                              placeholder="Ex: entrada de 50% e saldo na conclusao, particularidades da obra..."><?= e($orc['observacoes']) ?></textarea>
                </div>
            </div>

            <!-- Resumo em destaque -->
            <div class="cartao mt3" style="background:var(--superficie-2);margin-bottom:0">
                <div class="cartao-corpo">
                    <div class="folha-totais" style="width:100%;max-width:380px;margin-left:auto">
                        <div><span>Materiais</span><span id="rMateriais">R$ 0,00</span></div>
                        <div><span>Mao de obra</span><span id="rMaoObra">R$ 0,00</span></div>
                        <div><span>Subtotal</span><span id="rSubtotal">R$ 0,00</span></div>
                        <div class="desconto"><span>Desconto</span><span id="rDesconto">- R$ 0,00</span></div>
                        <div class="total"><span>TOTAL</span><span id="rTotal">R$ 0,00</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cartao-rodape flex g3 quebra">
            <button type="submit" class="botao botao-sucesso botao-g">
                <?= $editando ? 'Salvar alteracoes' : 'Criar orcamento' ?>
            </button>
            <a href="orcamentos_listar.php" class="botao botao-neutro botao-g">Cancelar</a>
        </div>
    </div>
</form>

<script>
/* Dados vindos do PHP */
const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE) ?>;
const ITENS_EXISTENTES = <?= json_encode($itens, JSON_UNESCAPED_UNICODE) ?>;
const MEDIDAS_EXISTENTES = <?= json_encode($medidas, JSON_UNESCAPED_UNICODE) ?>;
const DESCONTO_A_VISTA = <?= (float) DESCONTO_A_VISTA ?>;

function formatarMoeda(v) {
    return 'R$ ' + (v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ITENS
function adicionarItem(dados) {
    const corpo = document.getElementById('corpoItens');
    const tr = document.createElement('tr');

    let opcoes = '<option value="">Selecione o item</option>';
    PRODUTOS.forEach(function (p) {
        const sel = dados && String(dados.produto_id) === String(p.id) ? 'selected' : '';
        opcoes += '<option value="' + p.id + '" data-preco="' + p.preco_unitario + '" ' + sel + '>'
                + p.nome + ' (' + p.unidade + ')</option>';
    });
    opcoes += '<option value="outro">+ Outro item (digitar manualmente)</option>';

    tr.innerHTML =
        '<td>' +
            '<select name="item_produto_id[]" onchange="aoTrocarProduto(this)">' + opcoes + '</select>' +
            '<input type="text" name="item_nome_custom[]" class="oculto mt1" placeholder="Nome do item avulso">' +
        '</td>' +
        '<td><input type="number" step="0.01" min="0" name="item_quantidade[]" value="' +
            (dados ? parseFloat(dados.quantidade).toFixed(2) : '1.00') + '" oninput="recalcular()"></td>' +
        '<td><input type="number" step="0.01" min="0" name="item_valor[]" value="' +
            (dados ? parseFloat(dados.valor_unitario).toFixed(2) : '0.00') + '" oninput="recalcular()"></td>' +
        '<td class="num negrito subtotal">R$ 0,00</td>' +
        '<td><button type="button" class="botao botao-perigo botao-p" onclick="removerLinha(this)">&times;</button></td>';

    corpo.appendChild(tr);
    recalcular();
}

function aoTrocarProduto(select) {
    const tr = select.closest('tr');
    const campoCustom = tr.querySelector('input[name="item_nome_custom[]"]');
    const campoValor = tr.querySelector('input[name="item_valor[]"]');

    if (select.value === 'outro') {
        campoCustom.classList.remove('oculto');
        campoCustom.required = true;
        campoValor.value = '0.00';
        campoCustom.focus();
    } else {
        campoCustom.classList.add('oculto');
        campoCustom.required = false;
        campoCustom.value = '';
        const opcao = select.options[select.selectedIndex];
        const preco = opcao.getAttribute('data-preco');
        if (preco) campoValor.value = parseFloat(preco).toFixed(2);
    }
    recalcular();
}

// MEDIDAS
function adicionarMedida(dados) {
    const corpo = document.getElementById('corpoMedidas');
    const tr = document.createElement('tr');

    tr.innerHTML =
        '<td><input type="text" name="medida_tipo[]" placeholder="Ex: Calha frontal" value="' +
            (dados ? dados.tipo.replace(/"/g, '&quot;') : '') + '"></td>' +
        '<td><input type="number" step="0.01" min="0" name="medida_valor[]" value="' +
            (dados ? parseFloat(dados.valor).toFixed(2) : '') + '"></td>' +
        '<td><select name="medida_unidade[]">' +
            ['m', 'm2', 'cm', 'un'].map(function (un) {
                const sel = dados && dados.unidade === un ? 'selected' : '';
                return '<option value="' + un + '" ' + sel + '>' + un + '</option>';
            }).join('') +
        '</select></td>' +
        '<td><input type="text" name="medida_obs[]" placeholder="Opcional" value="' +
            (dados && dados.observacao ? dados.observacao.replace(/"/g, '&quot;') : '') + '"></td>' +
        '<td><button type="button" class="botao botao-perigo botao-p" onclick="this.closest(\'tr\').remove()">&times;</button></td>';

    corpo.appendChild(tr);
}

function removerLinha(botao) {
    botao.closest('tr').remove();
    recalcular();
}

/* CALCULOS */
function recalcular() {
    let materiais = 0;

    document.querySelectorAll('#corpoItens tr').forEach(function (tr) {
        const qtd = parseFloat(tr.querySelector('input[name="item_quantidade[]"]').value) || 0;
        const val = parseFloat(tr.querySelector('input[name="item_valor[]"]').value) || 0;
        const sub = qtd * val;
        tr.querySelector('.subtotal').textContent = formatarMoeda(sub);
        materiais += sub;
    });

    const maoObra = parseFloat(document.getElementById('valor_mao_obra').value) || 0;
    const perc = parseFloat(document.getElementById('percentual_desconto').value) || 0;
    const subtotal = materiais + maoObra;
    const desconto = subtotal * (perc / 100);
    const total = subtotal - desconto;

    document.getElementById('valor_materiais_exibe').value = formatarMoeda(materiais);
    document.getElementById('rMateriais').textContent = formatarMoeda(materiais);
    document.getElementById('rMaoObra').textContent = formatarMoeda(maoObra);
    document.getElementById('rSubtotal').textContent = formatarMoeda(subtotal);
    document.getElementById('rDesconto').textContent = '- ' + formatarMoeda(desconto);
    document.getElementById('rTotal').textContent = formatarMoeda(total);
}

function sugerirDesconto() {
    const forma = document.getElementById('forma_pagamento').value;
    const campo = document.getElementById('percentual_desconto');
    if (forma === 'Pix' || forma === 'Dinheiro') {
        campo.value = DESCONTO_A_VISTA.toFixed(2);
    } else {
        campo.value = '0.00';
    }
    recalcular();
}

// INICIALIZACAO
window.addEventListener('DOMContentLoaded', function () {
    if (ITENS_EXISTENTES.length) {
        ITENS_EXISTENTES.forEach(function (i) { adicionarItem(i); });
    } else {
        adicionarItem();
    }

    if (MEDIDAS_EXISTENTES.length) {
        MEDIDAS_EXISTENTES.forEach(function (m) { adicionarMedida(m); });
    } else {
        adicionarMedida();
    }

    recalcular();
});
</script>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
