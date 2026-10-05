```php
<?php

session_start();

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario'])) {
    header('Location: mapa.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Remove pontos, traços e outros caracteres do CPF
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($cpf) || empty($senha)) {

        $erro = 'Preencha o CPF e a senha.';

    } elseif (strlen($cpf) !== 11) {

        $erro = 'CPF inválido.';

    } else {

        /*
         * CAMINHO CORRETO
         *
         * login.php está em:
         * frontend/src/pages/
         *
         * database.php está em:
         * backend/config/
         *
         * Por isso precisamos subir:
         * pages -> src -> frontend -> raiz
         */
        require_once __DIR__ . '/../../../backend/config/database.php';
        require_once __DIR__ . '/../../../backend/models/Usuario.php';

        try {

            // Conecta ao banco usando PDO
            $db = Database::getConnection();

            // Cria o modelo de usuário
            $usuarioModel = new Usuario($db);

            // Procura o usuário pelo CPF
            $user = $usuarioModel->buscarPorCpf($cpf);

            // Verifica se encontrou o usuário e se a senha está correta
            if ($user && password_verify($senha, $user['senha_hash'])) {

                // Cria a sessão do usuário
                $_SESSION['usuario'] = [
                    'id'    => $user['id'],
                    'nome'  => $user['nome_completo'],
                    'email' => $user['email'],
                    'tipo'  => $user['tipo_usuario']
                ];

                // Login realizado
                header('Location: mapa.php');
                exit;

            } else {

                $erro = 'CPF ou senha incorretos.';
            }

        } catch (PDOException $e) {

            // Não mostra detalhes do banco para o usuário
            $erro = 'Erro ao conectar ao banco de dados. Verifique a configuração do MySQL.';
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

            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full mb-4 text-3xl">
                🏥
            </div>

            <h1 class="text-2xl font-bold text-slate-800">
                Saúde Conecta
            </h1>

            <p class="text-sm text-slate-500 mt-1">
                Acesse sua conta
            </p>

        </div>

        <!-- Mensagem de erro -->
        <?php if ($erro): ?>

            <div class="mb-5 p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-200">
                <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <!-- Formulário -->
        <form method="POST" action="" class="space-y-5">

            <!-- CPF -->
            <div>

                <label
                    for="cpf"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    CPF
                </label>

                <input
                    type="text"
                    id="cpf"
                    name="cpf"
                    maxlength="14"
                    required
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="000.000.000-00"
                    class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                >

            </div>

            <!-- Senha -->
            <div>

                <label
                    for="senha"
                    class="block text-sm font-medium text-slate-700 mb-1"
```
