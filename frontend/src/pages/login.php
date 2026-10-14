<?php
declare(strict_types=1);

$raiz = dirname(__DIR__, 3);

require_once $raiz . '/backend/config/seguranca.php';

iniciarSessaoSegura();

if (isset($_SESSION['usuario']['id'])) {
    header('Location: ../components/perfil_saude.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_login'] ?? '';

    if (
        !is_string($token) ||
        empty($_SESSION['csrf_login']) ||
        !hash_equals($_SESSION['csrf_login'], $token)
    ) {
        $erro = 'Formulário expirado. Atualize a página e tente novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $senha === ''
        ) {
            $erro = 'Informe um e-mail válido e sua senha.';
        } else {
            try {
                $pdo = Database::getConnection();

                $stmt = $pdo->prepare(
                    'SELECT *
                     FROM usuarios
                     WHERE email = :email
                     LIMIT 1'
                );

                $stmt->execute(['email' => $email]);
                $usuario = $stmt->fetch();

                $hashSenha = $usuario['senha_hash']
                    ?? $usuario['senha']
                    ?? null;

                if (
                    $usuario &&
                    $hashSenha &&
                    password_verify($senha, $hashSenha)
                ) {
                    // Contas desativadas não podem entrar.
                    if (
                        array_key_exists('ativo', $usuario) &&
                        (int) $usuario['ativo'] !== 1
                    ) {
                        $erro = 'Esta conta está desativada.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['usuario'] = [
                            'id' => (int) $usuario['id'],
                            'nome' => $usuario['nome_completo']
                                ?? $usuario['nome']
                                ?? 'Usuário',
                            'email' => $usuario['email'],
                            'tipo' => $usuario['tipo_usuario']
                                ?? 'usuario',
                        ];

                        unset($_SESSION['csrf_login']);

                        header('Location: ../components/perfil_saude.php');
                        exit;
                    }
                } else {
                    $erro = 'E-mail ou senha incorretos.';
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $erro = 'Não foi possível acessar sua conta. '
                    . 'Verifique a conexão com o banco de dados.';
            }
        }
    }
}

if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

$csrfLogin = $_SESSION['csrf_login'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f0f6f8;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .09);
        }

        h1 {
            margin-top: 0;
            color: #087f8c;
            text-align: center;
        }

        .descricao {
            margin-bottom: 24px;
            color: #5b6870;
            text-align: center;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: bold;
            color: #263238;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5da;
            border-radius: 8px;
            font-size: 16px;
        }

        .senha-container {
            position: relative;
        }

        .senha-container input {
            padding-right: 46px;
        }

        .alternar-senha {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            padding: 4px;
            border: 0;
            background: transparent;
            cursor: pointer;
            color: #50636b;
        }

        .alternar-senha svg {
            width: 22px;
            height: 22px;
        }

        .botao {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: 0;
            border-radius: 8px;
            background: #087f8c;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .botao:hover {
            background: #066a75;
        }

        .links {
            margin-top: 18px;
            text-align: center;
            line-height: 2;
        }

        a {
            color: #087f8c;
        }

        .erro {
            margin: 16px 0;
            padding: 12px;
            border-radius: 8px;
            background: #fff0f0;
            color: #a32020;
        }
    </style>
</head>
<body>
    <main class="login-container">
        <h1>Saúde-Conecta</h1>

        <p class="descricao">
            Entre para acessar seu perfil de saúde.
        </p>

        <?php if ($erro !== ''): ?>
            <div class="erro" role="alert">
                <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_login"
                value="<?= htmlspecialchars($csrfLogin, ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="email">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="username"
                required
                value="<?= htmlspecialchars(
                    $_POST['email'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <label for="senha">Senha</label>

            <div class="senha-container">
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    autocomplete="current-password"
                    required
                >

                <button
                    class="alternar-senha"
                    type="button"
                    id="alternarSenha"
                    aria-label="Mostrar senha"
                    aria-pressed="false"
                >
                    <svg
                        id="iconeOlho"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>

            <button class="botao" type="submit">
                Entrar
            </button>
        </form>

        <div class="links">
            <a href="recuperar_senha.php">Esqueci minha senha</a>
            <br>
            <a href="cadastro.php">Criar uma conta</a>
        </div>
    </main>

    <script>
        const campoSenha = document.getElementById('senha');
        const botaoSenha = document.getElementById('alternarSenha');
        const iconeOlho = document.getElementById('iconeOlho');

        botaoSenha.addEventListener('click', function () {
            const mostrar = campoSenha.type === 'password';

            campoSenha.type = mostrar ? 'text' : 'password';

            botaoSenha.setAttribute(
                'aria-label',
                mostrar ? 'Ocultar senha' : 'Mostrar senha'
            );

            botaoSenha.setAttribute(
                'aria-pressed',
                mostrar ? 'true' : 'false'
            );

            iconeOlho.innerHTML = mostrar
                ? '<path d="M3 3l18 18"/>' +
                  '<path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>' +
                  '<path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3.1 3.8"/>' +
                  '<path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"/>'
                : '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>' +
                  '<circle cx="12" cy="12" r="3"/>';
        });
    </script>
</body>
</html>