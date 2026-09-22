<?php
// conexao com o banco usando PDO (prepared statements evitam SQL
// injection, que era um problema real na primeira versao que eu tinha feito)

require_once __DIR__ . '/config.php';

/**
 * Retorna sempre a MESMA instancia da conexao (padrao Singleton).
 * Evita abrir varias conexoes na mesma requisicao.
 */
function bd(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NOME . ';charset=' . DB_CHARSET;

        $opcoes = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, $opcoes);
        } catch (PDOException $e) {
            if (MODO_DEBUG) {
                die('Erro de conexao: ' . $e->getMessage());
            }
            die(
                '<div style="font-family:sans-serif;max-width:600px;margin:80px auto;padding:30px;'
                . 'border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:8px">'
                . '<h2 style="margin-top:0">Nao foi possivel conectar ao banco de dados</h2>'
                . '<p>Verifique se o MySQL do XAMPP esta rodando e se o banco '
                . '<strong>' . DB_NOME . '</strong> foi importado.</p></div>'
            );
        }
    }

    return $pdo;
}
