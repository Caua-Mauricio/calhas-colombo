<?php
// só derruba a sessão e manda de volta pro login

require_once __DIR__ . '/inclui/auth.php';

fazer_logout();

session_start();
flash('info', 'Sessao encerrada com seguranca.');
redirecionar('login.php');
