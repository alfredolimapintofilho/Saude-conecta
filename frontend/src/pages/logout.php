<?php
session_start();

// Destrói todas as variáveis de sessão
$_SESSION = [];

// Destrói a sessão
session_destroy();

// Redireciona para a tela de login
header('Location: login.php');
exit;