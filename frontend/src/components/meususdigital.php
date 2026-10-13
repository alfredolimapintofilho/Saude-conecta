
<?php

session_start();

if (empty($_SESSION['usuario']['id'])) {
    header('Location: ../pages/login.php');
    exit;
}

function e($valor): string
{
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$nome = $_SESSION['usuario']['nome'] ?? 'Cidadão';
$email = $_SESSION['usuario']['email'] ?? '';

$portalMeuSUS = 'https://meususdigital.saude.gov.br/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Meu SUS Digital | Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f2f8f7;
            color: #263c3c;
        }

        header {
            background: #087f8c;
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        nav a {
            color: white;
            text-decoration: none;
            border: 1px solid #ffffff88;
            border-radius: 7px;
            padding: 9px 12px;
        }

        main {
            width: min(900px, 92%);
            margin: 30px auto;
        }

        .painel {
            background: white;
            border-radius: 14px;
            padding: 26px;
            box-shadow: 0 4px 18px #173d3b0d;
            margin-bottom: 20px;
        }

        h2 {
            color: #087f8c;
        }

        p {
            line-height: 1.65;
        }

        .aviso {
            padding: 15px;
            background: #fff8e6;
            border: 1px solid #f0d58c;
            color: #684c0d;
            border-radius: 9px;
            margin: 20px 0;
        }

        .botao {
            display: inline-block;
            padding: 12px 16px;
            border-radius: 8px;
            background: #087f8c;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .botao:hover {
            background: #066773;
        }

        .secundario {
            background: #e6f3f3;
            color: #075d65;
        }

        .acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        .nota {
            font-size: 13px;
            color: #5a7070;
        }

        footer {
            padding: 24px;
            text-align: center;
            color: #617777;
        }
    </style>
</head>

<body>
<header>
    <h1>Saúde-Conecta</h1>

    <nav>
        <a href="perfil_saude.php">Meu perfil</a>
        <a href="mapa.php">Mapa de saúde</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main>
    <section class="painel">
        <h2>Meu SUS Digital</h2>

        <p>
            Olá, <?= e($nome) ?>! Esta página ajuda você a acessar o
            portal oficial Meu SUS Digital.
        </p>

        <?php if ($email !== ''): ?>
            <p><strong>E-mail da sua conta Saúde-Conecta:</strong> <?= e($email) ?></p>
        <?php endif; ?>

        <div class="aviso">
            <strong>Importante:</strong>
            entrar no portal oficial não significa que os dados médicos
            serão copiados automaticamente para o Saúde-Conecta.
            Não informe sua senha do Meu SUS Digital nesta página.
        </div>

        <h3>Como acessar seus dados</h3>

        <ol>
            <li>Abra o portal oficial pelo botão abaixo.</li>
            <li>Entre diretamente no serviço oficial usando o método de acesso solicitado por ele.</li>
            <li>Consulte os dados disponibilizados para sua conta.</li>
            <li>Se precisar registrar alguma informação no Saúde-Conecta, utilize as funcionalidades disponíveis no perfil de saúde.</li>
        </ol>

        <div class="acoes">
            <a
                class="botao"
                href="<?= e($portalMeuSUS) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                Abrir Meu SUS Digital
            </a>

            <a class="botao secundario" href="perfil_saude.php">
                Voltar ao perfil de saúde
            </a>
        </div>

        <p class="nota">
            O Saúde-Conecta não está conectado automaticamente ao sistema
            do Ministério da Saúde por meio desta página. Uma integração
            real exige uma interface oficial compatível, autorização
            adequada e medidas para proteger os dados pessoais de saúde.
        </p>
    </section>
</main>

<footer>Saúde-Conecta — acesso às informações de saúde.</footer>
</body>
</html>
