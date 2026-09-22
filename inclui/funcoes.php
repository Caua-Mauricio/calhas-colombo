<?php
// funcoes que uso em varias paginas: formatar moeda, data, gerar
// token, escapar html, essas coisas

require_once __DIR__ . '/conexao.php';

/* COMPATIBILIDADE */

if (!function_exists('mb_strlen')) {
    function mb_strlen($texto, $enc = null) { return strlen((string) $texto); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($texto, $inicio, $tam = null, $enc = null) {
        return $tam === null ? substr((string) $texto, $inicio) : substr((string) $texto, $inicio, $tam);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($texto, $enc = null) { return strtoupper((string) $texto); }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($texto, $enc = null) { return strtolower((string) $texto); }
}

/* SEGURANCA E SAIDA */

/** Escapa a saida HTML (previne XSS). Use SEMPRE ao imprimir dados do banco. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Gera (uma vez por sessao) e devolve o token CSRF.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Valida o token enviado no POST. Encerra a execucao se for invalido.
function csrf_validar(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $enviado)) {
        http_response_code(403);
        die('Requisicao invalida (token CSRF). Recarregue a pagina e tente novamente.');
    }
}

/* MENSAGENS FLASH */

/** Guarda uma mensagem para exibir na proxima pagina. Tipos: sucesso|erro|aviso|info */
function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function flash_exibir(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $icones = ['sucesso' => '&#10003;', 'erro' => '&#33;', 'aviso' => '&#9888;', 'info' => '&#8505;'];
    $html = '';

    foreach ($_SESSION['flash'] as $f) {
        $icone = $icones[$f['tipo']] ?? '';
        $html .= '<div class="alerta alerta-' . e($f['tipo']) . '">'
               . '<span class="alerta-icone">' . $icone . '</span>'
               . '<span>' . e($f['mensagem']) . '</span>'
               . '<button type="button" class="alerta-fechar" onclick="this.parentElement.remove()">&times;</button>'
               . '</div>';
    }

    unset($_SESSION['flash']);
    return $html;
}

/** Redireciona e encerra. */
function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* FORMATACAO */

function moeda($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function numero($valor, int $casas = 2): string
{
    return number_format((float) $valor, $casas, ',', '.');
}

function data_br(?string $data, bool $comHora = false): string
{
    if (empty($data) || $data === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($data);
    return $ts ? date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '-';
}

// Remove tudo que nao for digito (util para telefone/CPF/CEP).
function so_numeros(?string $texto): string
{
    return preg_replace('/\D/', '', $texto ?? '');
}

function link_whatsapp(?string $numero, string $mensagem = ''): string
{
    $limpo = so_numeros($numero);
    if (strlen($limpo) < 10) {
        return '';
    }
    if (strncmp($limpo, '55', 2) !== 0) {
        $limpo = '55' . $limpo;
    }
    $url = 'https://wa.me/' . $limpo;
    if ($mensagem !== '') {
        $url .= '?text=' . rawurlencode($mensagem);
    }
    return $url;
}

// Formata telefone brasileiro: (48) 99999-9999
function formatar_telefone(?string $tel): string
{
    $n = so_numeros($tel);
    if (strlen($n) === 11) {
        return '(' . substr($n, 0, 2) . ') ' . substr($n, 2, 5) . '-' . substr($n, 7);
    }
    if (strlen($n) === 10) {
        return '(' . substr($n, 0, 2) . ') ' . substr($n, 2, 4) . '-' . substr($n, 6);
    }
    return $tel ?? '';
}

/** Converte "1.234,56" (formato brasileiro) em float 1234.56 */
function valor_float($valor): float
{
    if (is_numeric($valor)) {
        return (float) $valor;
    }
    $limpo = str_replace(['R$', ' ', '.'], '', (string) $valor);
    $limpo = str_replace(',', '.', $limpo);
    return (float) $limpo;
}

/* VALIDACAO */

function validar_cpf(string $cpf): bool
{
    $cpf = so_numeros($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }
    return true;
}

/** Valida CNPJ pelo algoritmo dos digitos verificadores. */
function validar_cnpj(string $cnpj): bool
{
    $cnpj = so_numeros($cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    $soma = 0;
    foreach ($pesos1 as $i => $p) {
        $soma += (int) $cnpj[$i] * $p;
    }
    $resto = $soma % 11;
    $d1 = $resto < 2 ? 0 : 11 - $resto;
    if ((int) $cnpj[12] !== $d1) {
        return false;
    }

    $soma = 0;
    foreach ($pesos2 as $i => $p) {
        $soma += (int) $cnpj[$i] * $p;
    }
    $resto = $soma % 11;
    $d2 = $resto < 2 ? 0 : 11 - $resto;

    return (int) $cnpj[13] === $d2;
}

// Valida CPF ou CNPJ conforme a quantidade de digitos.
function validar_documento(string $doc): bool
{
    $n = so_numeros($doc);
    if ($n === '') {
        return true; // documento e opcional
    }
    if (strlen($n) === 11) {
        return validar_cpf($n);
    }
    if (strlen($n) === 14) {
        return validar_cnpj($n);
    }
    return false;
}

/* REGRAS DE NEGOCIO */

function gerar_numero_orcamento(): string
{
    $ano = date('Y');
    $sql = "SELECT numero FROM orcamentos WHERE numero LIKE :padrao ORDER BY id DESC LIMIT 1";
    $st = bd()->prepare($sql);
    $st->execute([':padrao' => "ORC-$ano-%"]);
    $ultimo = $st->fetchColumn();

    $sequencial = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

    return sprintf('ORC-%s-%04d', $ano, $sequencial);
}

// Rotulos e cores dos status do orcamento.
function status_orcamento(?string $status = null)
{
    $mapa = [
        'rascunho'    => ['rotulo' => 'Rascunho',    'cor' => 'cinza'],
        'enviado'     => ['rotulo' => 'Enviado',     'cor' => 'laranja'],
        'aprovado'    => ['rotulo' => 'Aprovado',    'cor' => 'verde'],
        'em_producao' => ['rotulo' => 'Em producao', 'cor' => 'roxo'],
        'concluido'   => ['rotulo' => 'Concluido',   'cor' => 'verde-escuro'],
        'cancelado'   => ['rotulo' => 'Cancelado',   'cor' => 'vermelho'],
    ];

    if ($status === null) {
        return $mapa;
    }
    return $mapa[$status] ?? ['rotulo' => ucfirst($status), 'cor' => 'cinza'];
}

/** Devolve o HTML do selo de status. */
function selo_status(string $status): string
{
    $s = status_orcamento($status);
    return '<span class="selo selo-' . $s['cor'] . '">' . e($s['rotulo']) . '</span>';
}

function orcamento_vencido(array $orc): bool
{
    if (in_array($orc['status'], ['aprovado', 'em_producao', 'concluido', 'cancelado'], true)) {
        return false;
    }
    $limite = strtotime($orc['data_orcamento'] . ' +' . (int) $orc['validade_dias'] . ' days');
    return $limite < time();
}
