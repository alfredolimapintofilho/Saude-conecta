
<?php
session_start();

$raiz = dirname(__DIR__, 3);

require_once $raiz . '/backend/config/database.php';
require_once $raiz . '/backend/models/Usuario.php';

if (isset($_SESSION['usuario'])) {
    header('Location: ../components/perfil_saude.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        $erro = 'Informe um e-mail válido e sua senha.';
    } else {
        try {
            $db = Database::getConnection();

            // Busca o usuário pelo e-mail.
            $sql = 'SELECT * FROM usuarios WHERE email = :email LIMIT 1';
            $stmt = $db->prepare($sql);
            $stmt->execute(['email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Detecta o campo de senha usado pelo projeto.
            $hashSenha = $usuario['senha_hash']
                ?? $usuario['senha']
                ?? null;

            if ($usuario && $hashSenha && password_verify($senha, $hashSenha)) {
                session_regenerate_id(true);

                $_SESSION['usuario'] = [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome_completo']
                        ?? $usuario['nome']
                        ?? 'Usuário',
                    'email' => $usuario['email'],
                    'tipo' => $usuario['tipo_usuario'] ?? 'usuario'
                ];

                header('Location: ../components/perfil_saude.php');
                exit;
            }

            $erro = 'E-mail ou senha incorretos.';
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $erro = 'Não foi possível acessar sua conta. Verifique a conexão com o banco de dados.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f0fdf4;
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
        }

        .card {
            background: #fff;
            padding: 28px;
            border-radius: 14px;
            width: min(380px, 100%);
            box-shadow: 0 4px 18px #0001;
        }

        h1 {
            color: #047857;
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 16px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 22px;
            background: #047857;
            color: white;
            border: 0;
            border-radius: 7px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #065f46;
        }

        .erro {
            color: #b91c1c;
            background: #fef2f2;
            padding: 10px;
            border-radius: 7px;
        }

        a {
            color: #047857;
        }

        .senha-container {
            position: relative;
        }

        .senha-container input {
            padding-right: 45px;
        }

        .mostrar-senha {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-20%);
            width: 34px;
            height: 34px;
            margin: 0;
            padding: 5px;
            background: transparent;
            color: #475569;
            border: none;
            cursor: pointer;
        }

        .mostrar-senha svg {
            width: 22px;
            height: 22px;
        }
    </style>
</head>

<body>
<main class="card">
    <h1>Saúde-Conecta</h1>
    <h2>Entrar na sua conta</h2>

    <?php if ($erro !== ''): ?>
        <p class="erro">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email">E-mail</label>
        <input
            id="email"
            name="email"
            type="email"
            autocomplete="email"
            placeholder="seuemail@exemplo.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="senha">Senha</label>
        <div class="senha-container">
            <input
                id="senha"
                name="senha"
                type="password"
                autocomplete="current-password"
                required
            >

            <button
                class="mostrar-senha"
                type="button"
                id="alternarSenha"
                aria-label="Mostrar senha"
                title="Mostrar ou ocultar senha"
            >
                <svg id="iconeOlho" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>

        <button type="submit">Entrar</button>
    </form>

    <p><a href="recuperar_senha.php">Esqueci minha senha</a></p>
    <p><a href="cadastro.php">Criar uma conta</a></p>
</main>

<script>
const campoSenha = document.getElementById('senha');
const alternarSenha = document.getElementById('alternarSenha');
const iconeOlho = document.getElementById('iconeOlho');

alternarSenha.addEventListener('click', function () {
    const mostrar = campoSenha.type === 'password';

    campoSenha.type = mostrar ? 'text' : 'password';
    alternarSenha.setAttribute(
        'aria-label',
        mostrar ? 'Ocultar senha' : 'Mostrar senha'
    );

    iconeOlho.innerHTML = mostrar
        ? '<path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-4 4.8"/><path d="M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.6 10.6 0 0 0 4-.8"/>'
        : '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>';
});
</script>
</body>
</html>
