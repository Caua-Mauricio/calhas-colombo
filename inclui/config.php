<?php
// configuracoes do sistema. se for rodar em outro servidor/xampp,
// só mexer aqui (usuario e senha do banco principalmente)

// ---------- Banco de dados ----------
define('DB_HOST', 'localhost');
define('DB_NOME', 'calhas_colombo');
define('DB_USUARIO', 'root');
define('DB_SENHA', '');          // No XAMPP padrao a senha do root e vazia
define('DB_CHARSET', 'utf8mb4');

// ---------- Dados da empresa (aparecem na proposta impressa) ----------
define('EMPRESA_NOME', 'Calhas Colombo');
define('EMPRESA_SLOGAN', 'Calhas, Rufos e Condutores em Aluminio');
define('EMPRESA_CNPJ', '00.000.000/0001-00');
define('EMPRESA_TELEFONE', '(48) 3433-0000');
define('EMPRESA_WHATSAPP', '48999999999');
define('EMPRESA_EMAIL', 'contato@calhascolombo.com.br');
define('EMPRESA_ENDERECO', 'Rua Industrial, 250 - Centro - Criciuma/SC');

// ---------- Regras de negocio ----------
define('GARANTIA_MESES', 12);
define('VALIDADE_PADRAO_DIAS', 15);
define('DESCONTO_A_VISTA', 5.00);   // % aplicado em Pix / Dinheiro
define('MAO_OBRA_PADRAO', 150.00);

// ---------- Ambiente ----------
define('FUSO_HORARIO', 'America/Sao_Paulo');
define('MODO_DEBUG', false);        // true = mostra erros na tela (use so no desenvolvimento)

date_default_timezone_set(FUSO_HORARIO);

if (MODO_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
