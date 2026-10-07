<?php

session_start();


/*
|--------------------------------------------------------------------------
| VERIFICAR LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['usuario'])) {

    header('Location: ../pages/login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| DADOS DO USUÁRIO
|--------------------------------------------------------------------------
*/

$usuario = $_SESSION['usuario'];

$nome = $usuario['nomeCompleto'] ?? 'Usuário';

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Meu SUS Digital - Saúde Conecta
    </title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f3f7f9;

            color: #263238;

        }


        /* =========================
           HEADER
        ========================== */

        header {

            background:
                linear-gradient(
                    135deg,
                    #087f5b,
                    #0b9b6e
                );

            color: white;

            padding: 20px 30px;

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.15);

        }


        header h1 {

            font-size: 25px;

        }


        header p {

            margin-top: 5px;

            opacity: 0.9;

        }


        .btn-voltar {

            background: white;

            color: #087f5b;

            text-decoration: none;

            padding: 10px 18px;

            border-radius: 8px;

            font-weight: bold;

        }


        /* =========================
           CONTAINER
        ========================== */

        .container {

            width: 95%;

            max-width: 950px;

            margin: 40px auto;

        }


        /* =========================
           CARD PRINCIPAL
        ========================== */

        .card {

            background: white;

            border-radius: 18px;

            padding: 40px;

            box-shadow:
                0 5px 20px
                rgba(0,0,0,0.08);

            margin-bottom: 25px;

        }


        .icone {

            width: 90px;

            height: 90px;

            background: #087f5b;

            color: white;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 45px;

            margin: 0 auto 20px;

        }


        h2 {

            text-align: center;

            color: #087f5b;

            margin-bottom: 15px;

        }


        .descricao {

            text-align: center;

            line-height: 1.7;

            color: #4b5563;

            max-width: 700px;

            margin: auto;

        }


        /* =========================
           INFORMAÇÕES
        ========================== */

        .informacoes {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

            margin-top: 30px;

        }


        .informacao {

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            padding: 20px;

            border-radius: 12px;

        }


        .informacao h3 {

            color: #087f5b;

            margin-bottom: 8px;

        }


        .informacao p {

            color: #555;

            line-height: 1.5;

        }


        /* =========================
           BOTÃO
        ========================== */

        .btn-sus {

            display: block;

            width: 100%;

            background: #087f5b;

            color: white;

            text-decoration: none;

            text-align: center;

            padding: 16px;

            border-radius: 10px;

            font-size: 17px;

            font-weight: bold;

            margin-top: 30px;

            transition: 0.2s;

        }


        .btn-sus:hover {

            background: #066b4c;

            transform: translateY(-1px);

        }


        /* =========================
           AVISO
        ========================== */

        .aviso {

            background: #fff8e6;

            border-left:
                5px solid #f59e0b;

            padding: 18px;

            border-radius: 8px;

            margin-top: 25px;

            line-height: 1.6;

        }


        /* =========================
           SEGURANÇA
        ========================== */

        .seguranca {

            background: #eef7ff;

            border-left:
                5px solid #2563eb;

            padding: 18px;

            border-radius: 8px;

            margin-top: 15px;

            line-height: 1.6;

        }


        /* =========================
           RECURSOS
        ========================== */

        .recursos {

            margin-top: 30px;

        }


        .recursos h3 {

            color: #087f5b;

            margin-bottom: 15px;

        }


        .recursos ul {

            list-style: none;

        }


        .recursos li {

            padding: 12px 0;

            border-bottom:
                1px solid #eeeeee;

        }


        /* =========================
           RESPONSIVO
        ========================== */

        @media (max-width: 650px) {

            header {

                flex-direction: column;

                gap: 15px;

                text-align: center;

            }


            .card {

                padding: 25px 18px;

            }


            .informacoes {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<header>


    <div>

        <h1>
            Saúde Conecta
        </h1>

        <p>
            Integração com o Meu SUS Digital
        </p>

    </div>


    <a
        href="perfil_saude.php"
        class="btn-voltar"
    >

        ← Voltar ao perfil

    </a>


</header>


<main class="container">


    <!-- =========================
         CARD PRINCIPAL
    ========================== -->

    <section class="card">


        <div class="icone">

            🏥

        </div>


        <h2>

            Conectar ao Meu SUS Digital

        </h2>


        <p class="descricao">

            Olá,

            <strong>

                <?= htmlspecialchars(
                    $nome,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>!

            <br><br>

            O Meu SUS Digital é a plataforma oficial
            do Ministério da Saúde para acesso do cidadão
            aos seus serviços e informações de saúde
            disponibilizados pelos sistemas do SUS.

        </p>


        <!-- =========================
             INFORMAÇÕES
        ========================== -->

        <div class="informacoes">


            <div class="informacao">

                <h3>
                    💉 Vacinação
                </h3>

                <p>

                    Consulte os registros de vacinação
                    que estiverem disponíveis nos sistemas
                    integrados.

                </p>

            </div>


            <div class="informacao">

                <h3>
                    🏥 Histórico de saúde
                </h3>

                <p>

                    Consulte informações de saúde
                    disponibilizadas pelos sistemas
                    integrados ao SUS.

                </p>

            </div>


            <div class="informacao">

                <h3>
                    💊 Medicamentos
                </h3>

                <p>

                    Consulte informações sobre
                    medicamentos disponibilizados
                    pela plataforma.

                </p>

            </div>


            <div class="informacao">

                <h3>
                    🧪 Exames
                </h3>

                <p>

                    Consulte exames e resultados
                    disponíveis para o cidadão.

                </p>

            </div>


        </div>


        <!-- =========================
             BOTÃO OFICIAL
        ========================== -->

        <a
            href="https://meususdigital.saude.gov.br/"
            target="_blank"
            rel="noopener noreferrer"
            class="btn-sus"
        >

            🔐 Entrar no Meu SUS Digital

        </a>


        <!-- =========================
             AVISO
        ========================== -->

        <div class="aviso">

            <strong>
                ⚠️ Importante
            </strong>

            <br><br>

            O Saúde Conecta não solicita sua senha
            do Gov.br.

            <br><br>

            A autenticação deve ser realizada
            diretamente no site oficial do
            Meu SUS Digital.

        </div>


        <!-- =========================
             SEGURANÇA
        ========================== -->

        <div class="seguranca">

            <strong>
                🔒 Segurança dos dados
            </strong>

            <br><br>

            Seus dados de saúde são informações
            pessoais sensíveis. O Saúde Conecta
            não deve armazenar sua senha do Gov.br.

            <br><br>

            A importação automática de dados para
            o Saúde Conecta deverá utilizar a
            integração oficial disponibilizada
            pelo Ministério da Saúde/RNDS.

        </div>


        <!-- =========================
             RECURSOS
        ========================== -->

        <div class="recursos">

            <h3>
                Informações que podem estar disponíveis
            </h3>


            <ul>

                <li>
                    💉 Registros de vacinação
                </li>

                <li>
                    🏥 Informações de atendimentos
                </li>

                <li>
                    🧪 Exames disponíveis
                </li>

                <li>
                    💊 Medicamentos
                </li>

                <li>
                    📄 Outros dados disponibilizados
                    pelos sistemas oficiais
                </li>

            </ul>

        </div>


    </section>


    <!-- =========================
         OUTROS BOTÕES
    ========================== -->

    <section class="card">


        <h2>
            Acessar outros recursos
        </h2>


        <a
            href="perfil_saude.php"
            class="btn-sus"
        >

            👤 Voltar para o Perfil de Saúde

        </a>


        <a
            href="mapa.php"
            class="btn-sus"
        >

            🗺️ Acessar Mapa de Saúde

        </a>


    </section>


</main>


</body>

</html>