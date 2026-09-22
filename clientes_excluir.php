<?php
// "exclui" o cliente, mas na real so marca como inativo. assim o
// historico de orcamento dele continua valendo pros relatorios

require_once __DIR__ . '/inclui/auth.php';
exigir_permissao('excluir_clientes');

$id   = (int) ($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    flash('erro', 'Requisicao invalida.');
    redirecionar('clientes_listar.php');
}

if ($id <= 0) {
    flash('erro', 'Cliente nao informado.');
    redirecionar('clientes_listar.php');
}

$pdo = bd();

$st = $pdo->prepare("SELECT nome FROM clientes WHERE id = :id");
$st->execute([':id' => $id]);
$nome = $st->fetchColumn();

if (!$nome) {
    flash('erro', 'Cliente nao encontrado.');
    redirecionar('clientes_listar.php');
}

/* Verifica vinculos */
$st = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = :id");
$st->execute([':id' => $id]);
$vinculos = (int) $st->fetchColumn();

if ($vinculos > 0) {
    // Exclusao logica: preserva o historico
    $pdo->prepare("UPDATE clientes SET ativo = 0 WHERE id = :id")->execute([':id' => $id]);
    flash('aviso', 'O cliente possui ' . $vinculos . ' orcamento(s) e foi desativado em vez de excluido, preservando o historico.');
} else {
    // Sem vinculos: pode remover de fato
    $pdo->prepare("DELETE FROM clientes WHERE id = :id")->execute([':id' => $id]);
    flash('sucesso', 'Cliente excluido com sucesso.');
}

redirecionar('clientes_listar.php');
