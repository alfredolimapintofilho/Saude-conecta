
<?php

session_start();

// Limpa os dados guardados na sessão.
$_SESSION = [];

// Remove o cookie da sessão, se estiver habilitado.
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'] ?? 'Lax',
        ]
    );
}

// Destrói a sessão no servidor.
session_destroy();

// O login está em frontend/src/pages/.
header('Location: ../pages/login.php');
exit;
