<?php
session_start();

if (empty($_SESSION['usuario'])) {
    header('Location: ../pages/login.php');
    exit;
}

function icone(string $nome): string
{
    $icones = [
        'saude' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/><path d="M12 8v7M8.5 11.5h7"/>',
        'mapa' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>',
        'pets' => '<circle cx="7" cy="8" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="17" cy="8" r="2"/><circle cx="20" cy="13" r="2"/><path d="M5 16c0-3 3-5 7-5s7 2 7 5-3 5-7 5-7-2-7-5Z"/>',
        'adocao' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>',
        'seta' => '<path d="M5 12h14M12 5l7 7-7 7"/>'
    ];

    $conteudo = $icones[$nome] ?? $icones['seta'];

    return '<svg viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="1.8"
        stroke-linecap="round" stroke-linejoin="round"
        aria-hidden="true">' . $conteudo . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meu perfil | Saúde-Conecta</title>

    <style>
        :root {
            --fundo: #f3f8f9;
            --texto: #183b46;
            --secundario: #637982;
            --verde: #087f8c;
            --borda: #e1ebee;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--fundo);
            color: var(--texto);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.5;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .topo {
            background: #fff;
            border-bottom: 1px solid var(--borda);
        }

        .topo-conteudo {
            width: min(1160px, calc(100% - 40px));
            min-height: 76px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--verde);
            font-size: 21px;
            font-weight: 800;
        }

        .marca-icone {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            background: #e1f5f4;
            border-radius: 13px;
        }

        svg {
            width: 25px;
            height: 25px;
        }

        .navegacao {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .navegacao a {
            color: #526b74;
            font-size: 14px;
            font-weight: 700;
        }

        .navegacao .sair {
            color: #b54747;
        }

        main {
            width: min(1160px, calc(100% - 40px));
            margin: auto;
            padding: 42px 0 55px;
        }

        .etiqueta {
            display: inline-block;
            margin-bottom: 12px;
            padding: 6px 12px;
            border-radius: 30px;
            background: #e0f3f2;
            color: var(--verde);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 38px);
        }

        .descricao {
            max-width: 680px;
            margin: 0;
            color: var(--secundario);
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-top: 32px;
        }

        .janela {
            min-height: 230px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 24px;
            background: #fff;
            border: 1px solid var(--borda);
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(28, 75, 87, .07);
            transition: transform .2s, box-shadow .2s;
        }

        .janela:hover {
            transform: translateY(-4px);
            box-shadow: 0 13px 30px rgba(28, 75, 87, .12);
        }

        .icone-janela {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            margin-bottom: 18px;
            border-radius: 15px;
        }

        .icone-janela svg {
            width: 28px;
            height: 28px;
        }

        h2 {
            margin: 0 0 8px;
            font-size: 19px;
        }

        .janela p {
            margin: 0 0 20px;
            color: var(--secundario);
            font-size: 14px;
        }

        .abrir {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: auto;
            color: var(--verde);
            font-size: 13px;
            font-weight: 800;
        }

        .abrir svg {
            width: 17px;
            height: 17px;
        }

        .saude .icone-janela {
            background: #e1f5f4;
            color: #087f8c;
        }

        .mapa .icone-janela {
            background: #e8f4ff;
            color: #3477b8;
        }

        .pets .icone-janela {
            background: #fff3d9;
            color: #b17b20;
        }

        .adocao .icone-janela {
            background: #ffe9ef;
            color: #c54c7a;
        }

        footer {
            padding: 22px;
            background: #fff;
            border-top: 1px solid var(--borda);
            color: var(--secundario);
            text-align: center;
            font-size: 13px;
        }

        @media (max-width: 850px) {
            .grade {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .topo-conteudo {
                padding: 15px 0;
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 540px) {
            .grade {
                grid-template-columns: 1fr;
            }

            main,
            .topo-conteudo {
                width: calc(100% - 28px);
            }
        }
    </style>
</head>

<body>
<header class="topo">
    <div class="topo-conteudo">
        <a class="marca" href="perfil_saude.php">
            <span class="marca-icone"><?= icone('saude') ?></span>
            <span>Saúde-Conecta</span>
        </a>

        <nav class="navegacao" aria-label="Navegação principal">
            <a href="perfil_saude.php">Meu perfil</a>
            <a href="mapa.php">Mapa de saúde</a>
            <a class="sair" href="logout.php">Sair da conta</a>
        </nav>
    </div>
</header>

<main>
    <span class="etiqueta">ÁREA DO USUÁRIO</span>
    <h1>Meu perfil de saúde</h1>

    <p class="descricao">
        Acesse suas informações médicas e os serviços do Saúde-Conecta.
        Todas as suas funcionalidades estão organizadas abaixo.
    </p>

    <section class="grade" aria-label="Áreas do Saúde-Conecta">

        <a class="janela saude" href="saude_geral.php">
            <span class="icone-janela"><?= icone('saude') ?></span>
            <h2>Informações de Saúde Geral</h2>
            <p>
                Acesse seus dados físicos, IMC, cartão de vacinação,
                alergias, reações a vacinas, doenças e atestados.
            </p>
            <span class="abrir">
                Acessar informações <?= icone('seta') ?>
            </span>
        </a>

        <a class="janela mapa" href="mapa.php">
            <span class="icone-janela"><?= icone('mapa') ?></span>
            <h2>Mapa de saúde</h2>
            <p>
                Encontre unidades de saúde e consulte os serviços
                disponíveis na sua região.
            </p>
            <span class="abrir">
                Abrir mapa <?= icone('seta') ?>
            </span>
        </a>

        <a class="janela pets" href="../pages/pets.php">
            <span class="icone-janela"><?= icone('pets') ?></span>
            <h2>Meus pets</h2>
            <p>
                Acesse a área destinada ao cadastro e às informações
                dos seus animais.
            </p>
            <span class="abrir">
                Acessar meus pets <?= icone('seta') ?>
            </span>
        </a>

        <a class="janela adocao" href="../pages/adocao_pets.php">
            <span class="icone-janela"><?= icone('adocao') ?></span>
            <h2>Adoção de pets</h2>
            <p>
                Acesse a área de adoção de animais.
            </p>
            <span class="abrir">
                Acessar adoção <?= icone('seta') ?>
            </span>
        </a>

    </section>
</main>

<footer>
    Saúde-Conecta · Sua saúde, organizada em um só lugar.
</footer>
</body>
</html>