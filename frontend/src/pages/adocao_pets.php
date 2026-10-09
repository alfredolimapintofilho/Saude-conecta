<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$usuario = $_SESSION['usuario'];

$nomeUsuario = 'Usuário';

if (is_array($usuario)) {
    $nomeUsuario =
        $usuario['nome_completo']
        ?? $usuario['nomeCompleto']
        ?? $usuario['nome']
        ?? 'Usuário';
}

$tipoFiltro = $_GET['tipo'] ?? 'todos';

$tiposPermitidos = [
    'todos',
    'cao',
    'gato'
];

if (!in_array($tipoFiltro, $tiposPermitidos, true)) {
    $tipoFiltro = 'todos';
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

    <title>Adoção de Pets - Saúde-Conecta</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f8fb;
            color: #1f2937;
        }

        header {
            background: linear-gradient(
                135deg,
                #0f766e,
                #0e7490
            );

            color: white;
            padding: 25px 20px;
        }

        .header-container {
            max-width: 1200px;
            margin: auto;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .titulo {
            font-size: 30px;
            font-weight: bold;
        }

        .subtitulo {
            margin-top: 7px;
            opacity: 0.92;
        }

        .menu {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .menu a {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.15);
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: bold;
        }

        .menu a:hover {
            background: rgba(255,255,255,0.25);
        }

        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .introducao {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            margin-bottom: 25px;
        }

        .introducao h2 {
            color: #0f766e;
            margin-bottom: 12px;
        }

        .introducao p {
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .filtros {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .filtro {
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 10px;
            background: #e5e7eb;
            color: #374151;
            font-weight: bold;
        }

        .filtro.ativo {
            background: #0f766e;
            color: white;
        }

        .secao {
            margin-top: 30px;
        }

        .secao h2 {
            margin-bottom: 18px;
            color: #111827;
        }

        .cards {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(280px, 1fr));

            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
        }

        .card-topo {
            padding: 25px;
            background: #ecfeff;
        }

        .animal-icon {
            font-size: 45px;
            margin-bottom: 10px;
        }

        .card h3 {
            color: #0f766e;
            margin-bottom: 8px;
            font-size: 21px;
        }

        .card p {
            line-height: 1.55;
            color: #4b5563;
        }

        .card-conteudo {
            padding: 20px;
            flex: 1;
        }

        .informacao {
            margin-bottom: 12px;
        }

        .informacao strong {
            color: #111827;
        }

        .botao {
            display: inline-block;
            margin-top: 10px;
            padding: 12px 18px;
            border-radius: 10px;
            background: #0f766e;
            color: white;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
        }

        .botao:hover {
            background: #115e59;
        }

        .botao-secundario {
            background: #0e7490;
        }

        .botao-secundario:hover {
            background: #155e75;
        }

        .aviso {
            background: #fff7ed;
            border-left: 5px solid #f97316;
            padding: 18px;
            border-radius: 10px;
            margin-top: 25px;
            line-height: 1.6;
        }

        .adocao-local {
            background: white;
            border-radius: 18px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        }

        .adocao-local h2 {
            color: #0f766e;
            margin-bottom: 15px;
        }

        .local-item {
            padding: 18px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .local-item:last-child {
            border-bottom: none;
        }

        .local-item h3 {
            margin-bottom: 8px;
            color: #111827;
        }

        .local-item p {
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 8px;
        }

        .fonte {
            margin-top: 25px;
            padding: 18px;
            background: #f0fdfa;
            border-radius: 12px;
            line-height: 1.6;
        }

        .fonte a {
            color: #0f766e;
            font-weight: bold;
        }

        footer {
            margin-top: 50px;
            padding: 25px;
            background: #111827;
            color: #d1d5db;
            text-align: center;
            line-height: 1.6;
        }

        @media (max-width: 700px) {

            .titulo {
                font-size: 24px;
            }

            .header-container {
                align-items: flex-start;
            }

            .menu {
                width: 100%;
            }

            .menu a {
                flex: 1;
                text-align: center;
            }

        }

    </style>

</head>

<body>

<header>

    <div class="header-container">

        <div>

            <div class="titulo">
                Adoção de Pets
            </div>

            <div class="subtitulo">
                Encontre um animal para adotar em Patos/PB
            </div>

        </div>

        <nav class="menu">

            <a href="../components/perfil_saude.php">
                Perfil de Saúde
            </a>

            <a href="pets.php">
                Meus Pets
            </a>

            <a href="cadastro_pet.php">
                Cadastrar Pet
            </a>

            <a href="../components/logout.php">
                Sair
            </a>

        </nav>

    </div>

</header>


<main>

    <section class="introducao">

        <h2>
            Encontre seu novo amigo
        </h2>

        <p>
            O Saúde-Conecta também pode ajudar você a encontrar
            informações sobre animais disponíveis para adoção.
        </p>

        <p>
            A adoção deve ser responsável. Antes de adotar,
            verifique se você possui espaço, tempo e condições
            financeiras para cuidar do animal durante toda a vida.
        </p>

        <div class="filtros">

            <a
                href="adocao_pets.php?tipo=todos"
                class="filtro <?= $tipoFiltro === 'todos' ? 'ativo' : '' ?>"
            >
                Todos
            </a>

            <a
                href="adocao_pets.php?tipo=cao"
                class="filtro <?= $tipoFiltro === 'cao' ? 'ativo' : '' ?>"
            >
                Cães
            </a>

            <a
                href="adocao_pets.php?tipo=gato"
                class="filtro <?= $tipoFiltro === 'gato' ? 'ativo' : '' ?>"
            >
                Gatos
            </a>

        </div>

    </section>


    <?php if ($tipoFiltro === 'todos' || $tipoFiltro === 'cao'): ?>

    <section class="secao">

        <h2>
            🐶 Cães para adoção
        </h2>

        <div class="cards">

            <div class="card">

                <div class="card-topo">

                    <div class="animal-icon">
                        🐶
                    </div>

                    <h3>
                        Rajado
                    </h3>

                    <p>
                        Filhote macho disponível para adoção
                        em Patos/PB.
                    </p>

                </div>

                <div class="card-conteudo">

                    <div class="informacao">
                        <strong>Espécie:</strong>
                        Cão
                    </div>

                    <div class="informacao">
                        <strong>Sexo:</strong>
                        Macho
                    </div>

                    <div class="informacao">
                        <strong>Idade:</strong>
                        Aproximadamente 3 meses
                    </div>

                    <div class="informacao">
                        <strong>Perfil:</strong>
                        Dócil e indicado para convivência
                        com crianças e gatos, segundo o anúncio.
                    </div>

                    <a
                        href="https://adotar.com.br/adocao-de-animais/cao/patos-pb"
                        target="_blank"
                        class="botao"
                    >
                        Quero adotar
                    </a>

                </div>

            </div>


            <div class="card">

                <div class="card-topo">

                    <div class="animal-icon">
                        🐶
                    </div>

                    <h3>
                        Outros cães
                    </h3>

                    <p>
                        Veja outros cães disponíveis
                        para adoção em Patos.
                    </p>

                </div>

                <div class="card-conteudo">

                    <div class="informacao">
                        <strong>Local:</strong>
                        Patos/PB
                    </div>

                    <div class="informacao">
                        <strong>Plataforma:</strong>
                        Adotar.com.br
                    </div>

                    <a
                        href="https://adotar.com.br/adocao-de-animais/cao/patos-pb"
                        target="_blank"
                        class="botao"
                    >
                        Ver cães disponíveis
                    </a>

                </div>

            </div>

        </div>

    </section>

    <?php endif; ?>


    <?php if ($tipoFiltro === 'todos' || $tipoFiltro === 'gato'): ?>

    <section class="secao">

        <h2>
            🐱 Gatos para adoção
        </h2>

        <div class="cards">

            <div class="card">

                <div class="card-topo">

                    <div class="animal-icon">
                        🐱
                    </div>

                    <h3>
                        Mimo
                    </h3>

                    <p>
                        Gato disponível para adoção
                        em Patos/PB.
                    </p>

                </div>

                <div class="card-conteudo">

                    <div class="informacao">
                        <strong>Espécie:</strong>
                        Gato
                    </div>

                    <div class="informacao">
                        <strong>Sexo:</strong>
                        Macho
                    </div>

                    <div class="informacao">
                        <strong>Idade:</strong>
                        Entre 2 e 6 meses
                    </div>

                    <div class="informacao">
                        <strong>Informações:</strong>
                        O anúncio informa que está vacinado
                        e alimentando-se de ração.
                    </div>

                    <a
                        href="https://adotar.com.br/adocao-de-animais/gato/patos-pb"
                        target="_blank"
                        class="botao"
                    >
                        Quero adotar
                    </a>

                </div>

            </div>


            <div class="card">

                <div class="card-topo">

                    <div class="animal-icon">
                        🐱
                    </div>

                    <h3>
                        Laila
                    </h3>

                    <p>
                        Gata disponível para adoção
                        em Patos/PB.
                    </p>

                </div>

                <div class="card-conteudo">

                    <div class="informacao">
                        <strong>Espécie:</strong>
                        Gato
                    </div>

                    <div class="informacao">
                        <strong>Idade:</strong>
                        Entre 2 e 6 meses
                    </div>

                    <div class="informacao">
                        <strong>Perfil:</strong>
                        Dócil e já vacinada,
                        segundo o anúncio.
                    </div>

                    <a
                        href="https://adotar.com.br/adocao-de-animais/gato/patos-pb"
                        target="_blank"
                        class="botao"
                    >
                        Quero adotar
                    </a>

                </div>

            </div>


            <div class="card">

                <div class="card-topo">

                    <div class="animal-icon">
                        🐱
                    </div>

                    <h3>
                        Adote Cat
                    </h3>

                    <p>
                        Protetor de animais que atua
                        em Patos/PB.
                    </p>

                </div>

                <div class="card-conteudo">

                    <div class="informacao">
                        <strong>Local:</strong>
                        Patos/PB
                    </div>

                    <div class="informacao">
                        <strong>Animais:</strong>
                        Cães e gatos
                    </div>

                    <div class="informacao">
                        <strong>Disponíveis:</strong>
                        A página consultada informa
                        animais disponíveis para adoção.
                    </div>

                    <a
                        href="https://www.adoteca.com.br/protetor/adote-cat-patos-pb-w2aajv"
                        target="_blank"
                        class="botao"
                    >
                        Ver animais
                    </a>

                </div>

            </div>

        </div>

    </section>

    <?php endif; ?>


    <section class="adocao-local">

        <h2>
            Onde procurar adoção em Patos/PB
        </h2>


        <div class="local-item">

            <h3>
                Adotar.com.br
            </h3>

            <p>
                Plataforma com anúncios de cães e gatos
                disponíveis para adoção em Patos/PB.
            </p>

            <a
                href="https://adotar.com.br/adocao-de-animais/patos-pb"
                target="_blank"
                class="botao botao-secundario"
            >
                Ver animais de Patos
            </a>

        </div>


        <div class="local-item">

            <h3>
                Adote Cat - Patos/PB
            </h3>

            <p>
                Protetor de animais que atua em Patos.
                A página consultada informa cães e gatos
                disponíveis para adoção.
            </p>

            <a
                href="https://www.adoteca.com.br/protetor/adote-cat-patos-pb-w2aajv"
                target="_blank"
                class="botao botao-secundario"
            >
                Ver perfil do protetor
            </a>

        </div>


        <div class="local-item">

            <h3>
                Prefeitura de Patos
            </h3>

            <p>
                A Prefeitura possui informações relacionadas
                à proteção animal e à apreensão de animais.
                Para informações sobre processos de doação
                de animais apreendidos, a Prefeitura orienta
                procurar a Secretaria de Agricultura.
            </p>

            <p>
                <strong>Contato informado:</strong>
                (83) 9 9352-3393
            </p>

            <a
                href="https://patos.pb.gov.br/apreensao_de_animais"
                target="_blank"
                class="botao botao-secundario"
            >
                Informações da Prefeitura
            </a>

        </div>


        <div class="local-item">

            <h3>
                Associação Adota Patos
            </h3>

            <p>
                A legislação municipal registra a existência
                da Associação Adota Patos, voltada à proteção
                e ao cuidado de animais abandonados.
            </p>

            <a
                href="https://camarapatos.pb.legisgov.com.br/ta/946/text"
                target="_blank"
                class="botao botao-secundario"
            >
                Ver informações oficiais
            </a>

        </div>

    </section>


    <div class="aviso">

        <strong>
            Adoção responsável
        </strong>

        <br><br>

        Antes de adotar, verifique as condições do animal,
        converse com o responsável pela adoção e confirme
        informações sobre vacinação, vermifugação, castração
        e atendimento veterinário.

        <br><br>

        A Prefeitura de Patos também possui políticas e ações
        relacionadas à proteção e ao bem-estar animal.
        Em uma ação realizada em março de 2026, por exemplo,
        houve atendimento veterinário e espaço para adoção
        responsável. :contentReference[oaicite:2]{index=2}

    </div>


    <div class="fonte">

        <strong>
            Fontes consultadas:
        </strong>

        <br><br>

        <a
            href="https://adotar.com.br/adocao-de-animais/patos-pb"
            target="_blank"
        >
            Adotar.com.br — animais para adoção em Patos
        </a>

        <br>

        <a
            href="https://www.adoteca.com.br/protetor/adote-cat-patos-pb-w2aajv"
            target="_blank"
        >
            Adoteca — Adote Cat
        </a>

        <br>

        <a
            href="https://patos.pb.gov.br/apreensao_de_animais"
            target="_blank"
        >
            Prefeitura de Patos — Apreensão de Animais
        </a>

    </div>

</main>


<footer>

    Saúde-Conecta © <?= date('Y') ?>

    <br>

    Cuidado com a saúde. Cuidado com os animais.

</footer>

</body>

</html>