<?php
// apaga o orcamento. os itens e as medidas vao junto por causa do
// ON DELETE CASCADE la no banco

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('excluir_orcamentos');

$id   = (int) ($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    flash('erro', 'Requisicao invalida.');
    redirecionar('orcamentos_listar.php');
}

$pdo = bd();

$st = $pdo->prepare("SELECT numero, status FROM orcamentos WHERE id = :id");
$st->execute([':id' => $id]);
$orc = $st->fetch();

if (!$orc) {
    flash('erro', 'Orcamento nao encontrado.');
    redirecionar('orcamentos_listar.php');
}

/* Orcamentos ja concluidos nao devem ser apagados: viram registro contabil */
if ($orc['status'] === 'concluido') {
    flash('aviso', 'Orcamentos concluidos nao podem ser excluidos. Cancele-o se necessario.');
    redirecionar('orcamentos_detalhes.php?id=' . $id);
}

$pdo->prepare("DELETE FROM orcamentos WHERE id = :id")->execute([':id' => $id]);

flash('sucesso', 'Orcamento ' . $orc['numero'] . ' excluido.');
redirecionar('orcamentos_listar.php');
