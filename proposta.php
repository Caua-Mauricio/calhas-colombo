<?php
// pagina publica que o cliente acessa (sem login), pelo link com
// token que a gente manda no whatsapp. da pra aprovar/recusar
// direto por aqui e fica registrado quando o cliente abriu

require_once __DIR__ . '/inclui/funcoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo   = bd();
$token = $_GET['t'] ?? '';

/* Token precisa ter exatamente o formato gerado pelo sistema */
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(404);
    die('<p style="font-family:sans-serif;text-align:center;margin-top:80px">Proposta nao encontrada.</p>');
}

$st = $pdo->prepare(
    "SELECT o.*,
            c.nome AS cliente_nome, c.telefone AS cliente_telefone,
            c.endereco, c.numero AS cliente_numero, c.bairro, c.cidade, c.uf,
            u.nome AS responsavel_nome
     FROM orcamentos o
     JOIN clientes c ON c.id = o.cliente_id
     JOIN usuarios u ON u.id = o.responsavel_id
     WHERE o.token = :t"
);
$st->execute([':t' => $token]);
$orc = $st->fetch();

if (!$orc) {
    http_response_code(404);
    die('<p style="font-family:sans-serif;text-align:center;margin-top:80px">Proposta nao encontrada.</p>');
}

// Resposta do cliente
$respondido = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resposta'])) {
    $resposta = $_POST['resposta'];

    // So aceita resposta enquanto a proposta estiver aguardando
    if (in_array($orc['status'], ['enviado', 'rascunho'], true)) {
        $novoStatus = $resposta === 'aprovar' ? 'aprovado' : 'cancelado';

        $pdo->prepare(
            "UPDATE orcamentos SET status = :s, data_resposta = NOW() WHERE id = :id"
        )->execute([':s' => $novoStatus, ':id' => $orc['id']]);

        $orc['status'] = $novoStatus;
        $respondido = true;
    }
}

/* Itens e medidas */
$st = $pdo->prepare(
    "SELECT i.*, p.nome AS produto, p.unidade FROM itens_orcamento i
     JOIN produtos p ON p.id = i.produto_id
     WHERE i.orcamento_id = :id ORDER BY i.id"
);
$st->execute([':id' => $orc['id']]);
$itens = $st->fetchAll();

$st = $pdo->prepare("SELECT * FROM medidas WHERE orcamento_id = :id ORDER BY id");
$st->execute([':id' => $orc['id']]);
$medidas = $st->fetchAll();

$subtotal = (float) $orc['valor_materiais'] + (float) $orc['valor_mao_obra'];
$validade = date('d/m/Y', strtotime($orc['data_orcamento'] . ' +' . (int) $orc['validade_dias'] . ' days'));
$expirada = strtotime($orc['data_orcamento'] . ' +' . (int) $orc['validade_dias'] . ' days') < time();
$aguardando = in_array($orc['status'], ['enviado', 'rascunho'], true) && !$expirada;

$inclusos = [];
if ($orc['inclui_rufos'])      { $inclusos[] = 'Rufos'; }
if ($orc['inclui_condutores']) { $inclusos[] = 'Condutores'; }
if ($orc['inclui_veda_calha']) { $inclusos[] = 'Veda calha'; }
if ($orc['inclui_cabeceiras']) { $inclusos[] = 'Cabeceiras'; }

$whatsEmpresa = link_whatsapp(
    EMPRESA_WHATSAPP,
    'Ola! Tenho duvidas sobre a proposta ' . $orc['numero'] . '.'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Proposta <?= e($orc['numero']) ?> &middot; <?= e(EMPRESA_NOME) ?></title>
<link rel="stylesheet" href="assets/css/estilo.css">
<style>
    body { background: var(--fundo); padding: 24px 16px 60px; }
    .faixa-topo {
        max-width: 850px; margin: 0 auto 20px; padding: 16px 20px;
        background: linear-gradient(135deg, var(--laranja-700), var(--laranja-900));
        color: #fff; border-radius: var(--r-lg);
        display: flex; justify-content: space-between; align-items: center;
        gap: 16px; flex-wrap: wrap;
    }
    .faixa-topo h2 { font-size: 17px; }
    .faixa-topo p { font-size: 13px; opacity: .85; }
    .painel-resposta {
        max-width: 850px; margin: 24px auto 0; padding: 28px;
        background: var(--superficie); border: 1px solid var(--borda);
        border-radius: var(--r-lg); text-align: center; box-shadow: var(--sombra-2);
    }
    .painel-resposta h3 { font-size: 19px; margin-bottom: 8px; }
    .painel-resposta p { color: var(--texto-suave); font-size: 14px; margin-bottom: 20px; }
    .botoes-resposta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .botoes-resposta .botao { min-width: 190px; }
    .aviso-final {
        max-width: 850px; margin: 24px auto 0; padding: 24px;
        border-radius: var(--r-lg); text-align: center; font-size: 15px;
    }
</style>
</head>
<body>

<!-- Faixa de identificacao -->
<div class="faixa-topo">
    <div>
        <h2><?= e(EMPRESA_NOME) ?></h2>
        <p><?= e(EMPRESA_SLOGAN) ?></p>
    </div>
    <div style="text-align:right">
        <p style="font-size:11px;text-transform:uppercase;letter-spacing:.7px">Proposta</p>
        <strong style="font-size:19px;font-family:var(--fonte-mono)"><?= e($orc['numero']) ?></strong>
    </div>
</div>

<?php if ($respondido): ?>
    <div class="aviso-final" style="background:<?= $orc['status'] === 'aprovado' ? 'var(--verde-100)' : 'var(--vermelho-100)' ?>;
                color:<?= $orc['status'] === 'aprovado' ? 'var(--verde-600)' : 'var(--vermelho-600)' ?>">
        <strong style="font-size:18px">
            <?= $orc['status'] === 'aprovado' ? 'Proposta aprovada. Obrigado!' : 'Proposta recusada.' ?>
        </strong>
        <p style="margin-top:8px">
            <?= $orc['status'] === 'aprovado'
                ? 'Nossa equipe entrara em contato para agendar a instalacao.'
                : 'Se mudar de ideia ou quiser ajustar algo, fale com a gente.' ?>
        </p>
    </div>
<?php endif; ?>

<!-- FOLHA DA PROPOSTA -->
<div class="folha">

    <div class="folha-topo">
        <div class="folha-empresa">
            <div class="folha-empresa-topo">
                <img src="assets/img/logo-icone.png" alt="" class="folha-logo">
                <h1><?= e(EMPRESA_NOME) ?></h1>
            </div>
            <p><?= e(EMPRESA_ENDERECO) ?></p>
            <p><?= e(EMPRESA_TELEFONE) ?> &middot; <?= e(EMPRESA_EMAIL) ?></p>
            <p>CNPJ <?= e(EMPRESA_CNPJ) ?></p>
        </div>
        <div class="folha-numero">
            <div class="rotulo">Emitida em</div>
            <div style="font-size:15px;font-weight:600"><?= data_br($orc['data_orcamento']) ?></div>
            <div class="rotulo mt1">Valida ate</div>
            <div style="font-size:15px;font-weight:600;color:<?= $expirada ? 'var(--vermelho-600)' : 'inherit' ?>">
                <?= e($validade) ?>
            </div>
        </div>
    </div>

    <div class="folha-blocos">
        <div class="folha-bloco">
            <h3>Cliente</h3>
            <p><strong>Nome:</strong> <?= e($orc['cliente_nome']) ?></p>
            <p><strong>Telefone:</strong> <?= e(formatar_telefone($orc['cliente_telefone'])) ?></p>
            <p><strong>Local da obra:</strong>
                <?= e(trim($orc['endereco'] . ($orc['cliente_numero'] ? ', ' . $orc['cliente_numero'] : ''))) ?>
            </p>
            <p><?= e(trim($orc['bairro'] . ' - ' . $orc['cidade'] . '/' . $orc['uf'], ' -/')) ?></p>
        </div>

        <div class="folha-bloco">
            <h3>Servico</h3>
            <p><strong>Responsavel:</strong> <?= e($orc['responsavel_nome']) ?></p>
            <?php if ($orc['tipo_telhado']): ?>
                <p><strong>Telhado:</strong> <?= e($orc['tipo_telhado']) ?></p>
            <?php endif; ?>
            <?php if ($orc['acabamento_cor']): ?>
                <p><strong>Acabamento:</strong> <?= e($orc['acabamento_cor']) ?></p>
            <?php endif; ?>
            <p><strong>Previsao de instalacao:</strong> <?= data_br($orc['data_entrega_prevista']) ?></p>
            <?php if ($inclusos): ?>
                <p><strong>Inclui:</strong> <?= e(implode(', ', $inclusos)) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($medidas): ?>
        <h3 style="font-size:13px;text-transform:uppercase;letter-spacing:.6px;color:var(--laranja-600);margin-bottom:8px">
            Medidas do local
        </h3>
        <table>
            <thead><tr><th>Descricao</th><th class="num" style="width:140px">Medida</th></tr></thead>
            <tbody>
                <?php foreach ($medidas as $m): ?>
                    <tr>
                        <td><?= e($m['tipo']) ?><?= $m['observacao'] ? ' <small style="color:#64748b">(' . e($m['observacao']) . ')</small>' : '' ?></td>
                        <td class="num"><?= numero($m['valor']) ?> <?= e($m['unidade']) ?></td>
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
                <th>Item</th>
                <th class="num" style="width:110px">Qtd.</th>
                <th class="num" style="width:110px">Valor unit.</th>
                <th class="num" style="width:120px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $item): ?>
                <tr>
                    <td><?= e($item['produto']) ?></td>
                    <td class="num"><?= numero($item['quantidade']) ?> <?= e($item['unidade']) ?></td>
                    <td class="num"><?= moeda($item['valor_unitario']) ?></td>
                    <td class="num"><?= moeda($item['valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="folha-totais">
        <div><span>Materiais</span><span><?= moeda($orc['valor_materiais']) ?></span></div>
        <div><span>Mao de obra e instalacao</span><span><?= moeda($orc['valor_mao_obra']) ?></span></div>
        <?php if ((float) $orc['valor_desconto'] > 0): ?>
            <div><span>Subtotal</span><span><?= moeda($subtotal) ?></span></div>
            <div class="desconto">
                <span>Desconto (<?= numero($orc['percentual_desconto'], 1) ?>%)</span>
                <span>- <?= moeda($orc['valor_desconto']) ?></span>
            </div>
        <?php endif; ?>
        <div><span>Pagamento</span><span><strong><?= e($orc['forma_pagamento'] ?: 'A combinar') ?></strong></span></div>
        <div class="total"><span>TOTAL</span><span><?= moeda($orc['valor_total']) ?></span></div>
    </div>

    <div class="folha-condicoes">
        <h4>Condicoes</h4>
        <ul>
            <li>Proposta valida ate <?= e($validade) ?>.</li>
            <li>Garantia de <?= GARANTIA_MESES ?> meses contra defeitos de instalacao e vazamentos.</li>
            <li>Valores incluem material, mao de obra e transporte ate a obra.</li>
            <?php if ($orc['observacoes']): ?>
                <li><?= nl2br(e($orc['observacoes'])) ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="folha-rodape">
        Duvidas? Fale com a gente pelo telefone <?= e(EMPRESA_TELEFONE) ?>.
    </div>
</div>

<!-- PAINEL DE RESPOSTA DO CLIENTE -->
<?php if ($aguardando && !$respondido): ?>
    <div class="painel-resposta">
        <h3>O que voce decide?</h3>
        <p>Sua resposta chega na hora para a nossa equipe.</p>

        <form method="POST" class="botoes-resposta">
            <button type="submit" name="resposta" value="aprovar"
                    class="botao botao-sucesso botao-g"
                    onclick="return confirm('Confirmar a aprovacao desta proposta?')">
                Aprovar proposta
            </button>
            <button type="submit" name="resposta" value="recusar"
                    class="botao botao-neutro botao-g"
                    onclick="return confirm('Deseja recusar esta proposta?')">
                Recusar
            </button>
        </form>

        <?php if ($whatsEmpresa): ?>
            <p style="margin:20px 0 0">
                Prefere conversar antes?
                <a href="<?= e($whatsEmpresa) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a>
            </p>
        <?php endif; ?>
    </div>

<?php elseif (!$respondido): ?>
    <div class="aviso-final" style="background:var(--superficie);border:1px solid var(--borda);color:var(--texto-suave)">
        <?php if ($expirada && in_array($orc['status'], ['rascunho', 'enviado'], true)): ?>
            Esta proposta expirou em <?= e($validade) ?>.
            <?php if ($whatsEmpresa): ?>
                <a href="<?= e($whatsEmpresa) ?>" target="_blank" rel="noopener">Solicite uma atualizacao pelo WhatsApp</a>.
            <?php endif; ?>
        <?php else: ?>
            Situacao atual desta proposta: <?= selo_status($orc['status']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<p style="text-align:center;margin-top:28px;font-size:12px;color:var(--texto-suave)">
    &copy; <?= date('Y') ?> <?= e(EMPRESA_NOME) ?>
</p>

</body>
</html>
