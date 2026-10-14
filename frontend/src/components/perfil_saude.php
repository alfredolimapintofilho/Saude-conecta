<?php

require_once __DIR__ . '/../../../backend/config/seguranca.php';

iniciarSessaoSegura();
$usuario = exigirLogin();

function icone(string $nome): string
{
    $icones = [
        'saude' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/><path d="M12 8v7M8.5 11.5h7"/>',
        'mapa' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>',
        'pets' => '<circle cx="7" cy="8" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="17" cy="8" r="2"/><circle cx="20" cy="13" r="2"/><path d="M5 16c0-3 3-5 7-5s7 2 7 5-3 5-7 5-7-2-7-5Z"/>',
        'adocao' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>',
        'autorizacao' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'vacina' => '<path d="m18 2 4 4M7 17 3 21M14 4l6 6M5 13l6 6M3 21l7-7M9 7l8 8"/>',
        'alergia' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="M12 8v5M12 16h.01"/>',
        'doenca' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="M12 8v8M8 12h8"/>',
        'atestado' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'seta' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'expandir' => '<path d="m7 10 5 5 5-5"/>'
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
            width: min(1050px, calc(100% - 32px));
            min-height: 64px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--verde);
            font-size: 19px;
            font-weight: 800;
        }

        .marca-icone {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            background: #e1f5f4;
            border-radius: 11px;
        }

        svg {
            width: 23px;
            height: 23px;
            flex-shrink: 0;
        }

        .sair {
            color: #b54747;
            font-size: 13px;
            font-weight: 700;
        }

        main {
            width: min(1050px, calc(100% - 32px));
            margin: auto;
            padding: 30px 0 40px;
        }

        .etiqueta {
            display: inline-block;
            margin-bottom: 8px;
            padding: 5px 10px;
            border-radius: 30px;
            background: #e0f3f2;
            color: var(--verde);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .8px;
        }

        h1 {
            margin: 0 0 7px;
            font-size: clamp(25px, 3vw, 32px);
        }

        .descricao {
            max-width: 620px;
            margin: 0;
            color: var(--secundario);
            font-size: 14px;
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 24px;
            align-items: start;
        }

        .janela {
            min-width: 0;
            background: #fff;
            border: 1px solid var(--borda);
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(28, 75, 87, .05);
            transition: box-shadow .2s, transform .2s;
        }

        .janela:hover {
            box-shadow: 0 8px 22px rgba(28, 75, 87, .09);
        }

        .janela-link {
            min-height: 142px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 17px;
        }

        .icone-janela {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            margin-bottom: 12px;
            border-radius: 12px;
        }

        .icone-janela svg {
            width: 23px;
            height: 23px;
        }

        h2 {
            margin: 0 0 5px;
            font-size: 16px;
            line-height: 1.35;
        }

        .janela p {
            margin: 0 0 12px;
            color: var(--secundario);
            font-size: 12px;
            line-height: 1.5;
        }

        .abrir {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: auto;
            color: var(--verde);
            font-size: 12px;
            font-weight: 800;
        }

        .abrir svg {
            width: 15px;
            height: 15px;
        }

        /* Janela expansível das informações médicas */
        .janela-medica {
            overflow: hidden;
        }

        .resumo-medico {
            min-height: 142px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 17px;
            cursor: pointer;
            list-style: none;
        }

        .resumo-medico::-webkit-details-marker {
            display: none;
        }

        .resumo-medico::marker {
            content: "";
        }

        .titulo-medico {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .seta-expandir {
            width: 19px;
            height: 19px;
            color: var(--verde);
            transition: transform .2s;
        }

        .janela-medica[open] .seta-expandir {
            transform: rotate(180deg);
        }

        .janela-medica[open] {
            grid-column: span 2;
        }

        .opcoes-medicas {
            padding: 0 14px 14px;
            border-top: 1px solid var(--borda);
        }

        .opcao-medica {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 4px;
            border-bottom: 1px solid #edf2f4;
        }

        .opcao-medica:last-child {
            border-bottom: none;
        }

        .opcao-icone {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 10px;
        }

        .opcao-icone svg {
            width: 19px;
            height: 19px;
        }

        .opcao-texto {
            flex: 1;
            min-width: 0;
        }

        .opcao-texto strong {
            display: block;
            font-size: 13px;
        }

        .opcao-texto small {
            display: block;
            margin-top: 2px;
            color: var(--secundario);
            font-size: 11px;
        }

        .opcao-medica > svg {
            width: 17px;
            height: 17px;
            color: var(--verde);
        }

        .opcao-medica:hover {
            background: #f6fbfb;
        }

        .vacina .icone-janela,
        .cor-vacina {
            background: #e1f5f4;
            color: #087f8c;
        }

        .alergia .icone-janela,
        .cor-alergia {
            background: #fff0e5;
            color: #b96827;
        }

        .reacao .icone-janela,
        .cor-reacao {
            background: #fff0e5;
            color: #b96827;
        }

        .doenca .icone-janela,
        .cor-doenca {
            background: #e7efff;
            color: #4568ad;
        }

        .atestado .icone-janela,
        .cor-atestado {
            background: #e8f6e9;
            color: #34804b;
        }

        .autorizacoes .icone-janela {
            background: #e9e8ff;
            color: #5b54a4;
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
            padding: 18px;
            background: #fff;
            border-top: 1px solid var(--borda);
            color: var(--secundario);
            text-align: center;
            font-size: 12px;
        }

        @media (max-width: 760px) {
            .grade {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .janela-medica[open] {
                grid-column: span 2;
            }

            .topo-conteudo {
                min-height: 60px;
            }
        }

        @media (max-width: 480px) {
            main,
            .topo-conteudo {
                width: calc(100% - 24px);
            }

            main {
                padding-top: 23px;
            }

            .grade {
                gap: 10px;
                margin-top: 18px;
            }

            .janela-link,
            .resumo-medico {
                min-height: 132px;
                padding: 13px;
            }

            .icone-janela {
                width: 37px;
                height: 37px;
                margin-bottom: 9px;
            }

            h2 {
                font-size: 14px;
            }

            .janela p {
                font-size: 11px;
            }

            .abrir {
                font-size: 11px;
            }

            .janela-medica[open] {
                grid-column: span 2;
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

        <a class="sair" href="logout.php">Sair da conta</a>
    </div>
</header>

<main>
    <span class="etiqueta">ÁREA DO USUÁRIO</span>

    <h1>Meu perfil de saúde</h1>

    <p class="descricao">
        Acesse suas informações médicas e os serviços do Saúde-Conecta.
        Clique em uma janela para abrir a funcionalidade desejada.
    </p>

    <section class="grade" aria-label="Serviços do Saúde-Conecta">

        <!-- JANELA 1: informações médicas expansíveis -->
        <details class="janela janela-medica">
            <summary class="resumo-medico">
                <span class="icone-janela vacina">
                    <?= icone('saude') ?>
                </span>

                <span class="titulo-medico">
                    <h2>Minhas informações médicas</h2>
                    <?= icone('expandir') ?>
                </span>

                <p>
                    Vacinas, alergias, doenças e atestados em um só lugar.
                </p>

                <span class="abrir">
                    Ver opções médicas <?= icone('seta') ?>
                </span>
            </summary>

            <div class="opcoes-medicas">

                <a class="opcao-medica"
                   href="../pages/registros.php?tipo=vacina">
                    <span class="opcao-icone cor-vacina">
                        <?= icone('vacina') ?>
                    </span>
                    <span class="opcao-texto">
                        <strong>Cartão de Vacinação</strong>
                        <small>Consultar e cadastrar vacinas</small>
                    </span>
                    <?= icone('seta') ?>
                </a>

                <a class="opcao-medica"
                   href="../pages/registros.php?tipo=alergia">
                    <span class="opcao-icone cor-alergia">
                        <?= icone('alergia') ?>
                    </span>
                    <span class="opcao-texto">
                        <strong>Alergias</strong>
                        <small>Consultar e cadastrar alergias</small>
                    </span>
                    <?= icone('seta') ?>
                </a>

                <a class="opcao-medica"
                   href="../pages/registros.php?tipo=alergia_vacina">
                    <span class="opcao-icone cor-reacao">
                        <?= icone('alergia') ?>
                    </span>
                    <span class="opcao-texto">
                        <strong>Alergias a Vacinas</strong>
                        <small>Consultar e cadastrar reações</small>
                    </span>
                    <?= icone('seta') ?>
                </a>

                <a class="opcao-medica"
                   href="../pages/registros.php?tipo=doenca">
                    <span class="opcao-icone cor-doenca">
                        <?= icone('doenca') ?>
                    </span>
                    <span class="opcao-texto">
                        <strong>Histórico de Doenças</strong>
                        <small>Consultar e cadastrar doenças</small>
                    </span>
                    <?= icone('seta') ?>
                </a>

                <a class="opcao-medica"
                   href="../pages/registros.php?tipo=atestado">
                    <span class="opcao-icone cor-atestado">
                        <?= icone('atestado') ?>
                    </span>
                    <span class="opcao-texto">
                        <strong>Atestados</strong>
                        <small>Consultar e cadastrar atestados</small>
                    </span>
                    <?= icone('seta') ?>
                </a>

            </div>
        </details>

        <!-- JANELA 2: autorização de profissionais -->
        <a class="janela autorizacoes janela-link"
           href="autorizacoes_medicas.php">
            <span class="icone-janela">
                <?= icone('autorizacao') ?>
            </span>

            <h2>Autorizar um profissional de saúde</h2>

            <p>
                Gerencie os profissionais autorizados a acessar seus dados médicos.
            </p>

            <span class="abrir">
                Gerenciar autorizações <?= icone('seta') ?>
            </span>
        </a>

        <!-- JANELA 3: mapa da saúde -->
        <a class="janela mapa janela-link" href="mapa.php">
            <span class="icone-janela">
                <?= icone('mapa') ?>
            </span>

            <h2>Mapa da Saúde</h2>

            <p>
                Encontre unidades de saúde e serviços disponíveis na sua região.
            </p>

            <span class="abrir">
                Abrir mapa <?= icone('seta') ?>
            </span>
        </a>

        <!-- JANELA 4: meus pets -->
        <a class="janela pets janela-link" href="../pages/pets.php">
            <span class="icone-janela">
                <?= icone('pets') ?>
            </span>

            <h2>Meus Pets</h2>

            <p>
                Acesse os cadastros e as informações dos seus animais.
            </p>

            <span class="abrir">
                Acessar meus pets <?= icone('seta') ?>
            </span>
        </a>

        <!-- JANELA 5: adoção de animais -->
        <a class="janela adocao janela-link" href="../pages/adocao_pets.php">
            <span class="icone-janela">
                <?= icone('adocao') ?>
            </span>

            <h2>Adoção de Animais</h2>

            <p>
                Acesse a área de adoção de animais.
            </p>

            <span class="abrir">
                Ver adoções <?= icone('seta') ?>
            </span>
        </a>

    </section>
</main>

<footer>
    Saúde-Conecta · Sua saúde, organizada em um só lugar.
</footer>
</body>
</html>