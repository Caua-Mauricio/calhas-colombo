<?php
// login, sessao e permissao por perfil. toda pagina que exige
// login inclui esse arquivo la em cima

require_once __DIR__ . '/funcoes.php';

// Sessao segura
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Funcoes de autenticacao

/** Tenta autenticar. Retorna true em caso de sucesso. */
function tentar_login(string $email, string $senha): bool
{
    $st = bd()->prepare("SELECT * FROM usuarios WHERE email = :email AND ativo = 1 LIMIT 1");
    $st->execute([':email' => $email]);
    $usuario = $st->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        return false;
    }

    // Se o hash foi gerado com um algoritmo antigo, regrava atualizado.
    if (password_needs_rehash($usuario['senha'], PASSWORD_DEFAULT)) {
        $novo = password_hash($senha, PASSWORD_DEFAULT);
        bd()->prepare("UPDATE usuarios SET senha = :s WHERE id = :id")
            ->execute([':s' => $novo, ':id' => $usuario['id']]);
    }

    // Previne fixacao de sessao.
    session_regenerate_id(true);

    $_SESSION['usuario_id']       = (int) $usuario['id'];
    $_SESSION['usuario_nome']     = $usuario['nome'];
    $_SESSION['usuario_email']    = $usuario['email'];
    $_SESSION['usuario_perfil']   = $usuario['perfil'];
    $_SESSION['usuario_telefone'] = $usuario['telefone'];

    bd()->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = :id")
        ->execute([':id' => $usuario['id']]);

    return true;
}

function fazer_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
}

function esta_logado(): bool
{
    return isset($_SESSION['usuario_id']);
}

function usuario_logado(): array
{
    return [
        'id'       => $_SESSION['usuario_id']     ?? 0,
        'nome'     => $_SESSION['usuario_nome']   ?? '',
        'email'    => $_SESSION['usuario_email']  ?? '',
        'perfil'   => $_SESSION['usuario_perfil'] ?? '',
        'telefone' => $_SESSION['usuario_telefone'] ?? '',
    ];
}

// Controle de permissao

/**
 * Matriz de permissoes por perfil.
 * Cada chave e uma "capacidade" verificada com pode('capacidade').
 */
function permissoes(): array
{
    return [
        'admin' => [
            'ver_dashboard', 'ver_financeiro', 'gerir_clientes', 'excluir_clientes',
            'gerir_orcamentos', 'excluir_orcamentos', 'gerir_produtos', 'gerir_insumos',
            'gerir_fornecedores', 'gerir_usuarios', 'ver_relatorios',
        ],
        'gerente' => [
            'ver_dashboard', 'ver_financeiro', 'gerir_clientes',
            'gerir_orcamentos', 'gerir_produtos', 'gerir_insumos',
            'gerir_fornecedores', 'ver_relatorios',
        ],
        'calheiro' => [
            'ver_dashboard', 'gerir_clientes', 'gerir_orcamentos', 'gerir_insumos',
        ],
        'campo' => [
            'ver_dashboard', 'gerir_clientes', 'gerir_orcamentos',
        ],
    ];
}

// Verifica se o usuario logado possui determinada capacidade.
function pode(string $capacidade): bool
{
    $perfil = $_SESSION['usuario_perfil'] ?? '';
    $mapa = permissoes();
    return in_array($capacidade, $mapa[$perfil] ?? [], true);
}

function rotulo_perfil(string $perfil): string
{
    $mapa = [
        'admin'    => 'Administrador',
        'gerente'  => 'Gerente',
        'calheiro' => 'Calheiro',
        'campo'    => 'Tecnico de Campo',
    ];
    return $mapa[$perfil] ?? ucfirst($perfil);
}

// "Porteiros" - chame no inicio das paginas

// Bloqueia quem nao esta logado.
function exigir_login(): void
{
    if (!esta_logado()) {
        flash('aviso', 'Faca login para acessar o sistema.');
        redirecionar('login.php');
    }
}

/** Bloqueia quem nao tem a capacidade informada. */
function exigir_permissao(string $capacidade): void
{
    exigir_login();
    if (!pode($capacidade)) {
        flash('erro', 'Voce nao tem permissao para acessar esta area.');
        redirecionar('index.php');
    }
}
