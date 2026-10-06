<?php

// Inicia a sessão
session_start();

// Remove todos os dados armazenados na sessão
$_SESSION = [];

// Remove o cookie da sessão, caso esteja sendo utilizado
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destrói a sessão
session_destroy();

// Redireciona para o login
header('Location: ../pages/login.php');
exit;

?>