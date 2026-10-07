<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../pages/login.php');
    exit;
}

$usuario = $_SESSION['usuario'];

$usuarioId = null;
$nomeUsuario = 'Usuário';
$emailUsuario = '';
$telefoneUsuario = '';
$dataNascimento = '';
$cartaoSus = '';

if (is_array($usuario)) {

    if (isset($usuario['id'])) {
        $usuarioId = (int) $usuario['id'];
    } elseif (isset($usuario['id_usuario'])) {
        $usuarioId = (int) $usuario['id_usuario'];
    }

    if (!empty($usuario['nomeCompleto'])) {
        $nomeUsuario = $usuario['nomeCompleto'];
    } elseif (!empty($usuario['nome'])) {
        $nomeUsuario = $usuario['nome'];
    } elseif (!empty($usuario['nome_completo'])) {
        $nomeUsuario = $usuario['nome_completo'];
    }

    if (!empty($usuario['email'])) {
        $emailUsuario = $usuario['email'];
    }

    if (!empty($usuario['telefone'])) {
        $telefoneUsuario = $usuario['telefone'];
    }

    if (!empty($usuario['dataNascimento'])) {
        $dataNascimento = $usuario['dataNascimento'];
    } elseif (!empty($usuario['data_nascimento'])) {
        $dataNascimento = $usuario['data_nascimento'];
    }

    if (!empty($usuario['cartaoSus'])) {
        $cartaoSus = $usuario['cartaoSus'];
    } elseif (!empty($usuario['cartao_sus'])) {
        $cartaoSus = $usuario['cartao_sus'];
    }
}

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatarData($data): string
{
    if (empty($data)) {
        return '-';
    }

    $dataObj = DateTime::createFromFormat(
        'Y-m-d',
        $data
    );

    if ($dataObj) {
        return $dataObj->format('d/m/Y');
    }

    return escapar($data);
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Perfil de Saúde - Saúde-Conecta</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f1f5f9;

            color: #1e293b;
        }

        .topo {
            background:
                linear-gradient(
                    135deg,
                    #0f766e,
                    #0d9488
                );

            color: white;

            padding: 22px 20px;

            box-shadow:
                0 3px 10px
                rgba(0, 0, 0, .15);
        }

        .topo-conteudo {

            max-width: 1200px;

            margin: 0 auto;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;
        }

        .logo-area h1 {

            margin: 0;

            font-size: 27px;
        }

        .logo-area p {

            margin: 6px 0 0;

            opacity: .9;

            font-size: 14px;
        }

        .acoes-topo {

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;
        }

        .btn {

            display: inline-block;

            padding: 10px 15px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            border: none;

            cursor: pointer;
        }

        .btn-branco {

            background: white;

            color: #0f766e;
        }

        .btn-sair {

            background: #dc2626;

            color: white;
        }

        .container {

            max-width: 1200px;

            margin: 30px auto;

            padding: 0 20px;
        }

        .boas-vindas {

            background: white;

            border-radius: 16px;

            padding: 30px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, .07);
        }

        .boas-vindas h2 {

            margin: 0 0 8px;

            color: #0f766e;

            font-size: 27px;
        }

        .boas-vindas p {

            margin: 0;

            color: #64748b;

            font-size: 16px;

            line-height: 1.6;
        }

        .dados-pessoais {

            background: white;

            border-radius: 16px;

            padding: 25px;

            margin-bottom: 30px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, .07);
        }

        .titulo-secao {

            margin: 0 0 20px;

            color: #0f766e;

            font-size: 21px;
        }

        .dados-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }

        .dado {

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            padding: 15px;
        }

        .dado strong {

            display: block;

            color: #475569;

            font-size: 13px;

            margin-bottom: 5px;
        }

        .dado span {

            color: #0f172a;

            font-size: 15px;

            word-break: break-word;
        }

        .titulo-principal {

            margin-bottom: 18px;
        }

        .titulo-principal h2 {

            margin: 0;

            color: #0f766e;

            font-size: 23px;
        }

        .titulo-principal p {

            margin: 7px 0 0;

            color: #64748b;
        }

        .cards-saude {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }

        .card-saude {

            background: white;

            border-radius: 16px;

            padding: 24px;

            text-decoration: none;

            color: inherit;

            display: flex;

            align-items: flex-start;

            gap: 18px;

            border: 1px solid #e2e8f0;

            box-shadow:
                0 4px 12px
                rgba(0, 0, 0, .06);

            transition:
                transform .2s,
                box-shadow .2s,
                border-color .2s;
        }

        .card-saude:hover {

            transform: translateY(-3px);

            box-shadow:
                0 8px 20px
                rgba(0, 0, 0, .10);

            border-color: #0f766e;
        }

        .icone {

            min-width: 58px;

            height: 58px;

            border-radius: 14px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 29px;

            background: #ccfbf1;
        }

        .card-conteudo h3 {

            margin: 0 0 7px;

            color: #0f766e;

            font-size: 18px;
        }

        .card-conteudo p {

            margin: 0;

            color: #64748b;

            font-size: 14px;

            line-height: 1.5;
        }

        .card-vacina {

            border: 2px solid #0f766e;

            background:
                linear-gradient(
                    135deg,
                    #ffffff,
                    #f0fdfa
                );
        }

        .card-vacina .icone {

            background: #0f766e;

            color: white;
        }

        .mapa-area {

            margin-top: 30px;

            background: white;

            border-radius: 16px;

            padding: 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, .07);
        }

        .mapa-area h2 {

            margin: 0 0 8px;

            color: #0f766e;
        }

        .mapa-area p {

            color: #64748b;

            line-height: 1.5;
        }

        .btn-mapa {

            background: #2563eb;

            color: white;

            margin-top: 8px;
        }

        .aviso {

            margin-top: 30px;

            padding: 18px;

            border-radius: 12px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1e40af;

            font-size: 14px;

            line-height: 1.6;
        }

        .rodape {

            text-align: center;

            color: #64748b;

            font-size: 13px;

            padding: 35px 20px;
        }

        @media (max-width: 800px) {

            .topo-conteudo {

                flex-direction: column;

                align-items: flex-start;
            }

            .dados-grid {

                grid-template-columns: 1fr;
            }

            .cards-saude {

                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 500px) {

            .container {

                padding: 0 12px;
            }

            .boas-vindas,
            .dados-pessoais,
            .mapa-area {

                padding: 20px;
            }

            .card-saude {

                padding: 18px;
            }

            .logo-area h1 {

                font-size: 23px;
            }
        }

    </style>

</head>

<body>

<header class="topo">

    <div class="topo-conteudo">

        <div class="logo-area">

            <h1>
                Saúde-Conecta
            </h1>

            <p>
                Seu perfil de saúde digital
            </p>

        </div>

        <div class="acoes-topo">

            <a
                href="../pages/logout.php"
                class="btn btn-sair"
                onclick="
                    return confirm(
                        'Deseja realmente sair da sua conta?'
                    );
                "
            >
                Sair
            </a>

        </div>

    </div>

</header>

<main class="container">

    <section class="boas-vindas">

        <h2>

            Olá,
            <?= escapar($nomeUsuario) ?>!

        </h2>

        <p>

            Este é o seu Perfil de Saúde do
            <strong>Saúde-Conecta</strong>.

            Aqui você poderá consultar e organizar
            suas principais informações de saúde.

        </p>

    </section>

    <section class="dados-pessoais">

        <h2 class="titulo-secao">

            Dados da conta

        </h2>

        <div class="dados-grid">

            <div class="dado">

                <strong>
                    Nome completo
                </strong>

                <span>

                    <?= escapar($nomeUsuario) ?>

                </span>

            </div>

            <div class="dado">

                <strong>
                    E-mail
                </strong>

                <span>

                    <?= $emailUsuario
                        ? escapar($emailUsuario)
                        : '-' ?>

                </span>

            </div>

            <div class="dado">

                <strong>
                    Telefone
                </strong>

                <span>

                    <?= $telefoneUsuario
                        ? escapar($telefoneUsuario)
                        : '-' ?>

                </span>

            </div>

            <div class="dado">

                <strong>
                    Data de nascimento
                </strong>

                <span>

                    <?= formatarData($dataNascimento) ?>

                </span>

            </div>

            <div class="dado">

                <strong>
                    Cartão SUS
                </strong>

                <span>

                    <?= $cartaoSus
                        ? escapar($cartaoSus)
                        : '-' ?>

                </span>

            </div>

        </div>

    </section>

    <section>

        <div class="titulo-principal">

            <h2>
                Meu Perfil de Saúde
            </h2>

            <p>
                Acesse suas informações de saúde.
            </p>

        </div>

        <div class="cards-saude">

            <!-- CARTÃO DE VACINAÇÃO -->

            <a
                href="../pages/cartao_vacinacao.php"
                class="card-saude card-vacina"
            >

                <div class="icone">
                    💉
                </div>

                <div class="card-conteudo">

                    <h3>
                        Cartão de Vacinação
                    </h3>

                    <p>

                        Consulte seus registros de vacinação,
                        doses, datas, lotes, estabelecimentos
                        de saúde e demais informações.

                    </p>

                </div>

            </a>

            <!-- ALERGIAS -->

            <a
                href="../pages/alergias.php"
                class="card-saude"
            >

                <div class="icone">
                    🤧
                </div>

                <div class="card-conteudo">

                    <h3>
                        Alergias
                    </h3>

                    <p>

                        Consulte e registre informações
                        sobre alergias.

                    </p>

                </div>

            </a>

            <!-- ALERGIAS A VACINAS -->

            <a
                href="../pages/alergias_vacinas.php"
                class="card-saude"
            >

                <div class="icone">
                    ⚠️
                </div>

                <div class="card-conteudo">

                    <h3>
                        Alergias a Vacinas
                    </h3>

                    <p>

                        Registre informações sobre histórico
                        de alergia ou reação relacionada
                        a vacinas.

                    </p>

                </div>

            </a>

            <!-- HISTÓRICO DE DOENÇAS -->

            <a
                href="../pages/historico_doencas.php"
                class="card-saude"
            >

                <div class="icone">
                    🦠
                </div>

                <div class="card-conteudo">

                    <h3>
                        Histórico de Doenças
                    </h3>

                    <p>

                        Consulte e organize o histórico
                        de doenças informado na sua conta.

                    </p>

                </div>

            </a>

            <!-- ATESTADOS -->

            <a
                href="../pages/atestados.php"
                class="card-saude"
            >

                <div class="icone">
                    📄
                </div>

                <div class="card-conteudo">

                    <h3>
                        Atestados
                    </h3>

                    <p>

                        Consulte os atestados e documentos
                        de saúde associados à sua conta.

                    </p>

                </div>

            </a>

            <!-- MAPA -->

            <a
                href="../pages/mapa.php"
                class="card-saude"
            >

                <div class="icone">
                    🗺️
                </div>

                <div class="card-conteudo">

                    <h3>
                        Mapa de Saúde
                    </h3>

                    <p>

                        Encontre hospitais, UPAs,
                        maternidades e outros serviços
                        de saúde.

                    </p>

                </div>

            </a>

        </div>

    </section>

    <section class="mapa-area">

        <h2>
            Encontre atendimento de saúde
        </h2>

        <p>

            Acesse o mapa do Saúde-Conecta para
            localizar serviços de saúde e consultar
            informações dos estabelecimentos.

        </p>

        <a
            href="../pages/mapa.php"
            class="btn btn-mapa"
        >
            🗺️ Acessar Mapa de Saúde
        </a>

    </section>

    <div class="aviso">

        <strong>
            Importante:
        </strong>

        As informações apresentadas no Perfil de Saúde
        correspondem aos dados cadastrados na conta
        do usuário.

        O Saúde-Conecta não deve ser considerado,
        por si só, uma fonte oficial de diagnóstico
        ou tratamento médico.

    </div>

</main>

<footer class="rodape">

    Saúde-Conecta

    <br>

    Perfil de Saúde

</footer>

</body>

</html>s