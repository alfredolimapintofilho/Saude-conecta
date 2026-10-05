<?php
session_start();

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario'])) {
    header('Location: mapa.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($cpf) || empty($senha)) {
        $erro = 'Preencha o CPF e a senha.';
    } else {
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/models/Usuario.php';

        $db = Database::getConnection();
        $usuarioModel = new Usuario($db);
        $user = $usuarioModel->buscarPorCpf($cpf);

        if ($user && password_verify($senha, $user['senha_hash'])) {
            // Login bem-sucedido
            $_SESSION['usuario'] = [
                'id' => $user['id'],
                'nome' => $user['nome_completo'],
                'email' => $user['email'],
                'tipo' => $user['tipo_usuario']
            ];

            header('Location: mapa.php');
            exit;
        } else {
            $erro = 'CPF ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Saúde Conecta</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

  <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-100">
    
    <!-- Cabeçalho -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 