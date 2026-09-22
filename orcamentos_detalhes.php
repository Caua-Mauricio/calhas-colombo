<?php
// tela com o orcamento completo, essa mesma pagina serve pra
// visualizar e pra imprimir/gerar a proposta

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('gerir_orcamentos');

$u = usuario_logado();
$pdo = bd();
$id  = (int) ($_GET['id'] ?? 0);

/* Mudanca rapida de status */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_status'])) {
    csrf_validar();
    $novo = $_POST['novo_status'];

    if (array_key_exists($novo, status_orcamento())) {
        $pdo->prepare(
            "UPDATE orcamentos
             SET status = :s, data_resposta = CASE WHEN :s2 IN ('aprovado','cancelado') THEN NOW() ELSE data_resposta END
             WHERE id = :id"
        )->execute([':s' => $novo, ':s2' => $novo, ':id' => $id]);

        flash('sucesso', 'Status atualizado para "' . status_orcamento($novo)['rotulo'] . '".');
        redirecionar('orcamentos_detalhes.php?id=' . $id);
    }
}

// Carrega a proposta
$st = $pdo->prepare(
    "SELECT o.*,
            c.nome AS cliente_nome, c.cpf_cnpj, c.telefone AS cliente_telefone,
            c.whatsapp AS cliente_whatsapp, c.email AS cliente_email,
            c.endereco, c.numero AS cliente_numero, c.complemento, c.bairro,
            c.cidade, c.uf, c.cep, c.ponto_referencia,
            u.nome AS responsavel_nome, u.telefone AS responsavel_telefone
     FROM orcamentos o
     JOIN clientes c ON c.id = o.cliente_id
     JOIN usuarios u ON u.id = o.responsavel_id
     WHERE o.id = :id"
);
$st->execute([':id' => $id]);
$orc = $st->fetch();

if (!$orc) {
    flash('erro', 'Orcamento nao encontrado.');
    redirecionar('orcamentos_listar.php');
}

$st = $pdo->prepare(
    "SELECT i.*, p.nome AS produto, p.unidade
     FROM itens_orcamento i
     JOIN produtos p ON p.id = i.produto_id
     WHERE i.orcamento_id = :id ORDER BY i.id"
);
$st->execute([':id' => $id]);
$itens = $st->fetchAll();

$st = $pdo->prepare("SELECT * FROM medidas WHERE orcamento_id = :id ORDER BY id");
$st->execute([':id' => $id]);
$medidas = $st->fetchAll();

// Dados derivados
$subtotal = (float) $orc['valor_materiais'] + (float) $orc['valor_mao_obra'];
$validade = date('d/m/Y', strtotime($orc['data_orcamento'] . ' +' . (int) $orc['validade_dias'] . ' days'));
$vencido  = orcamento_vencido($orc);

$enderecoCompleto = trim(
    $orc['endereco'] . ($orc['cliente_numero'] ? ', ' . $orc['cliente_numero'] : '')
    . ($orc['complemento'] ? ' - ' . $orc['complemento'] : '')
);
$cidadeCompleta = trim($orc['bairro'] . ' - ' . $orc['cidade'] . '/' . $orc['uf'], ' -/');

/* Link publico da proposta */
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST']
      . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$linkPublico = $base . '/proposta.php?t=' . $orc['token'];

$mensagemWhats = "Ola {$orc['cliente_nome']}! Segue a proposta da " . EMPRESA_NOME
    . " ({$orc['numero']}) no valor de " . moeda($orc['valor_total']) . ".\n\nVeja os detalhes aqui: " . $linkPublico;

$whatsCliente = link_whatsapp($orc['cliente_whatsapp'] ?: $orc['cliente_telefone'], $mensagemWhats);

/* Itens inclusos */
$inclusos = [];
if ($orc['inclui_rufos'])      { $inclusos[] = 'Rufos'; }
if ($orc['inclui_condutores']) { $inclusos[] = 'Condutores'; }
if ($orc['inclui_veda_calha']) { $inclusos[] = 'Veda calha'; }
if ($orc['inclui_cabeceiras']) { $inclusos[] = 'Cabeceiras'; }
/* O cabecalho so e carregado agora: toda a logica acima pode
   redirecionar com header() sem que nada tenha sido impresso. */
$titulo_pagina = 'Proposta';
$pagina_ativa  = 'orcamentos_listar';
require_once __DIR__ . '/inclui/cabecalho.php';
?>

<!-- BARRA DE ACOES (nao aparece na impressao) -->
<div class="pagina-topo nao-imprimir">
    <div>
        <div class="trilha"><a href="orcamentos_listar.php">Orcamentos</a> / <?= e($orc['numero']) ?></div>
        <h1 class="pagina-titulo">Proposta <?= e($orc['numero']) ?></h1>
        <p class="pagina-subtitulo">
            <?= selo_status($orc['status']) ?>
            <?php if ($vencido): ?>
                <span class="selo selo-ambar">Validade expirada</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="pagina-acoes">
        <button onclick="window.print()" class="botao botao-primario">Imprimir / PDF</button>
        <a href="orcamentos_form.php?id=<?= $id ?>" class="botao botao-alerta">Editar</a>
        <?php if ($whatsCliente): ?>
            <a href="<?= e($whatsCliente) ?>" target="_blank" rel="noopener" class="botao botao-whats">Enviar no WhatsApp</a>
        <?php endif; ?>
        <a href="orcamentos_listar.php" class="botao botao-neutro">Voltar</a>
    </div>
</div>

<!-- PAINEL DE CONTROLE -->
<div class="cartao nao-imprimir">
    <div class="cartao-corpo">
        <div class="grade-3">

            <div>
                <label class="txt-p txt-suave negrito">ALTERAR STATUS</label>
                <form method="POST" class="flex g2 mt1">
                    <?= csrf_campo() ?>
                    <select name="novo_status" class="largura-total" style="padding:9px 12px;border:1px solid var(--borda-forte);border-radius:6px;background:var(--superficie);color:var(--texto)">
                        <?php foreach (status_orcamento() as $k => $s): ?>
                            <option value="<?= e($k) ?>" <?= $orc['status'] === $k ? 'selected' : '' ?>>
                                <?= e($s['rotulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="botao botao-primario">Aplicar</button>
                </form>
            </div>

            <div>
                <label class="txt-p txt-suave negrito">LINK PUBLICO DA PROPOSTA</label>
                <p class="txt-p txt-suave mt1">
                    O cliente acessa sem precisar de login e pode aprovar online.
                </p>
                <div class="flex g2 mt1 quebra">
                    <a href="proposta.php?t=<?= e($orc['token']) ?>" target="_blank"
                       class="botao botao-contorno botao-p">Abrir</a>
                    <button type="button" class="botao botao-neutro botao-p"
                            data-copiar="<?= e($linkPublico) ?>">Copiar link</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FOLHA DA PROPOSTA -->
<div class="folha">

    <?php if ($orc['status'] === 'cancelado'): ?>
        <div class="marca-dagua">CANCELADO</div>
    <?php endif; ?>

    <div class="folha-topo">
        <div class="folha-empresa">
            <div class="folha-empresa-topo">
                <img src="assets/img/logo-icone.png" alt="" class="folha-logo">
                <div>
                    <h1><?= e(EMPRESA_NOME) ?></h1>
                    <p><?= e(EMPRESA_SLOGAN) ?></p>
                </div>
            </div>
            <p><?= e(EMPRESA_ENDERECO) ?></p>
            <p><?= e(EMPRESA_TELEFONE) ?> &middot; <?= e(EMPRESA_EMAIL) ?></p>
            <p>CNPJ <?= e(EMPRESA_CNPJ) ?></p>
        </div>
        <div class="folha-numero">
            <div class="rotulo">Proposta comercial</div>
            <div class="valor"><?= e($orc['numero']) ?></div>
            <div class="rotulo mt1">Emitida em <?= data_br($orc['data_orcamento']) ?></div>
            <div class="rotulo">Valida ate <?= e($validade) ?></div>
        </div>
    </div>

    <div class="folha-blocos">
        <div class="folha-bloco">
            <h3>Dados do cliente</h3>
            <p><strong>Nome:</strong> <?= e($orc['cliente_nome']) ?></p>
            <?php if ($orc['cpf_cnpj']): ?>
                <p><strong>CPF/CNPJ:</strong> <?= e($orc['cpf_cnpj']) ?></p>
            <?php endif; ?>
            <p><strong>Telefone:</strong> <?= e(formatar_telefone($orc['cliente_telefone'])) ?></p>
            <?php if ($orc['cliente_email']): ?>
                <p><strong>E-mail:</strong> <?= e($orc['cliente_email']) ?></p>
            <?php endif; ?>
            <p><strong>Endereco:</strong> <?= e($enderecoCompleto) ?></p>
            <p><strong>Bairro/Cidade:</strong> <?= e($cidadeCompleta) ?></p>
            <?php if ($orc['ponto_referencia']): ?>
                <p><strong>Referencia:</strong> <?= e($orc['ponto_referencia']) ?></p>
            <?php endif; ?>
        </div>

        <div class="folha-bloco">
            <h3>Dados tecnicos do servico</h3>
            <p><strong>Responsavel:</strong> <?= e($orc['responsavel_nome']) ?></p>
            <?php if ($orc['tipo_telhado']): ?>
                <p><strong>Tipo de telhado:</strong> <?= e($orc['tipo_telhado']) ?></p>
            <?php endif; ?>
            <?php if ($orc['corte_chapa']): ?>
                <p><strong>Corte da chapa:</strong> <?= e($orc['corte_chapa']) ?></p>
            <?php endif; ?>
            <?php if ($orc['acabamento_cor']): ?>
                <p><strong>Cor / acabamento:</strong> <?= e($orc['acabamento_cor']) ?></p>
            <?php endif; ?>
            <p><strong>Acesso ao telhado:</strong> <?= e($orc['condicao_acesso'] ?: 'A definir') ?></p>
            <p><strong>Previsao de instalacao:</strong> <?= data_br($orc['data_entrega_prevista']) ?></p>
            <?php if ($inclusos): ?>
                <p><strong>Itens inclusos:</strong> <?= e(implode(', ', $inclusos)) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($medidas): ?>
        <h3 style="font-size:13px;text-transform:uppercase;letter-spacing:.6px;color:var(--laranja-600);margin-bottom:8px">
            Medidas levantadas em campo
        </h3>
        <table>
            <thead>
                <tr>
                    <th>Descricao</th>
                    <th class="num" style="width:130px">Medida</th>
                    <th>Observacao</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medidas as $m): ?>
                    <tr>
                        <td><?= e($m['tipo']) ?></td>
                        <td class="num"><?= numero($m['valor']) ?> <?= e($m['unidade']) ?></td>
                        <td><?= e($m['observacao'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3 style="font-size:13px;text-transform:uppercase;letter-spacing:.6px;color:var(--laranja-600);margin-bottom:8px">
        Materiais e servicos
    </h3>
    <table>
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th>Item</th>
                <th class="num" style="width:110px">Quantidade</th>
                <th class="num" style="width:120px">Valor unit.</th>
                <th class="num" style="width:120px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($item['produto']) ?></td>
                    <td class="num"><?= numero($item['quantidade']) ?> <?= e($item['unidade']) ?></td>
                    <td class="num"><?= moeda($item['valor_unitario']) ?></td>
                    <td class="num"><?= moeda($item['valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$itens): ?>
                <tr><td colspan="5" style="text-align:center;padding:24px">Nenhum item lancado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="folha-totais">
        <div><span>Materiais</span><span><?= moeda($orc['valor_materiais']) ?></span></div>
        <div><span>Mao de obra e instalacao</span><span><?= moeda($orc['valor_mao_obra']) ?></span></div>
        <div><span>Subtotal</span><span><?= moeda($subtotal) ?></span></div>
        <?php if ((float) $orc['valor_desconto'] > 0): ?>
            <div class="desconto">
                <span>Desconto (<?= numero($orc['percentual_desconto'], 1) ?>%)</span>
                <span>- <?= moeda($orc['valor_desconto']) ?></span>
            </div>
        <?php endif; ?>
        <div><span>Forma de pagamento</span><span><strong><?= e($orc['forma_pagamento'] ?: 'A combinar') ?></strong></span></div>
        <div class="total"><span>VALOR TOTAL</span><span><?= moeda($orc['valor_total']) ?></span></div>
    </div>

    <div class="folha-condicoes">
        <h4>Condicoes gerais</h4>
        <ul>
            <li>Proposta valida por <?= (int) $orc['validade_dias'] ?> dias, ate <?= e($validade) ?>.</li>
            <li>Garantia de <?= GARANTIA_MESES ?> meses contra defeitos de instalacao e vazamentos.</li>
            <li>Os valores incluem material, mao de obra e transporte ate o local da obra.</li>
            <li>Servicos nao descritos nesta proposta serao orcados separadamente.</li>
            <li>A data de instalacao sera confirmada apos a aprovacao e o pagamento da entrada.</li>
            <?php if ($orc['observacoes']): ?>
                <li><strong>Observacoes:</strong> <?= nl2br(e($orc['observacoes'])) ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="folha-assinaturas">
        <div class="assinatura"><?= e(EMPRESA_NOME) ?><br>Responsavel: <?= e($orc['responsavel_nome']) ?></div>
        <div class="assinatura">Cliente<br><?= e($orc['cliente_nome']) ?></div>
    </div>

    <div class="folha-rodape">
        <?= e(EMPRESA_NOME) ?> &middot; <?= e(EMPRESA_TELEFONE) ?> &middot; <?= e(EMPRESA_EMAIL) ?><br>
        Documento gerado pelo sistema em <?= date('d/m/Y \a\s H:i') ?>
    </div>
</div>

<?php require_once __DIR__ . '/inclui/rodape.php'; ?>
