<?php
// topo + menu lateral, compartilhado entre as paginas.
// antes de dar include aqui, define $titulo_pagina e $pagina_ativa

if (!defined('SISTEMA_INICIADO')) {
    define('SISTEMA_INICIADO', true);
}

require_once __DIR__ . '/auth.php';
exigir_login();

$u = usuario_logado();
$titulo_pagina = $titulo_pagina ?? 'Painel';
$pagina_ativa  = $pagina_ativa  ?? '';

/* Alerta de estoque baixo mostrado no sino do topo */
$alertas_estoque = bd()->query(
    "SELECT nome, estoque_atual, estoque_minimo, unidade
     FROM produtos
     WHERE ativo = 1 AND estoque_atual <= estoque_minimo
     ORDER BY (estoque_minimo - estoque_atual) DESC
     LIMIT 8"
)->fetchAll();

/* Itens do menu: rotulo, arquivo, icone, capacidade exigida */
$menu = [
    ['Painel',        'index.php',              '&#9632;', 'ver_dashboard'],
    ['Orcamentos',    'orcamentos_listar.php',  '&#9776;', 'gerir_orcamentos'],
    ['Clientes',      'clientes_listar.php',    '&#9679;', 'gerir_clientes'],
    ['Produtos',      'produtos.php',           '&#9670;', 'gerir_produtos'],
    ['Insumos',       'insumos.php',            '&#9635;', 'gerir_insumos'],
    ['Fornecedores',  'fornecedores.php',       '&#9650;', 'gerir_fornecedores'],
    ['Relatorios',    'relatorios.php',         '&#9650;', 'ver_relatorios'],
    ['Funcionarios',  'funcionarios.php',       '&#9786;', 'gerir_usuarios'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo_pagina) ?> &middot; <?= e(EMPRESA_NOME) ?></title>
<link rel="stylesheet" href="assets/css/estilo.css">
<link rel="icon" href="assets/img/logo-icone.png">
</head>
<body>

<!-- TOPO -->
<header class="topo">
    <button class="botao-menu" id="botaoMenu" aria-label="Abrir menu">&#9776;</button>

    <a href="index.php" class="marca">
        <img src="assets/img/logo-icone.png" alt="" class="marca-icone">
        <span class="marca-texto">
            <strong><?= e(EMPRESA_NOME) ?></strong>
            <small>Gestao de Orcamentos</small>
        </span>
    </a>

    <div class="topo-direita">
        <!-- Sino de alertas de estoque -->
        <div class="sino" id="sinoAlertas">
            <button class="sino-botao" aria-label="Alertas">
                &#9888;
                <?php if ($alertas_estoque): ?>
                    <span class="sino-contador"><?= count($alertas_estoque) ?></span>
                <?php endif; ?>
            </button>
            <div class="sino-painel" id="sinoPainel">
                <h4>Estoque baixo</h4>
                <?php if ($alertas_estoque): ?>
                    <ul>
                        <?php foreach ($alertas_estoque as $a): ?>
                            <li>
                                <span><?= e($a['nome']) ?></span>
                                <strong><?= numero($a['estoque_atual']) ?> <?= e($a['unidade']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="produtos.php" class="sino-link">Ver produtos</a>
                <?php else: ?>
                    <p class="vazio-mini">Nenhum produto abaixo do minimo.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alternador de tema claro/escuro -->
        <button class="botao-tema" id="botaoTema" aria-label="Alternar tema">&#9789;</button>

        <!-- Menu do usuario -->
        <div class="usuario" id="menuUsuario">
            <button class="usuario-botao">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nome'], 0, 1))) ?></span>
                <span class="usuario-info">
                    <strong><?= e($u['nome']) ?></strong>
                    <small><?= e(rotulo_perfil($u['perfil'])) ?></small>
                </span>
            </button>
            <div class="usuario-painel" id="usuarioPainel">
                <a href="perfil.php">Meu perfil</a>
                <a href="logout.php" class="sair">Sair do sistema</a>
            </div>
        </div>
    </div>
</header>

<div class="layout">

    <!-- MENU LATERAL -->
    <aside class="lateral" id="menuLateral">
        <nav>
            <ul>
                <?php foreach ($menu as [$rotulo, $arquivo, $icone, $capacidade]): ?>
                    <?php if (!pode($capacidade)) continue; ?>
                    <?php $chave = pathinfo($arquivo, PATHINFO_FILENAME); ?>
                    <li>
                        <a href="<?= e($arquivo) ?>"
                           class="lateral-item <?= $pagina_ativa === $chave ? 'ativo' : '' ?>">
                            <span class="lateral-icone"><?= $icone ?></span>
                            <span><?= e($rotulo) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="lateral-rodape">
            <p><?= e(EMPRESA_NOME) ?></p>
            <small>Versao 2.0</small>
        </div>
    </aside>

    <!-- CONTEUDO -->
    <main class="conteudo">
        <?= flash_exibir() ?>
