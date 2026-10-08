<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| IDENTIFICAR USUÁRIO
|--------------------------------------------------------------------------
*/

$usuarioSessao = $_SESSION['usuario'];

if (is_array($usuarioSessao)) {
    $usuarioId =
        $usuarioSessao['id']
        ?? $usuarioSessao['usuario_id']
        ?? null;
} else {
    $usuarioId = $usuarioSessao;
}

if (!$usuarioId) {
    session_destroy();
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| BUSCAR NOME DO USUÁRIO
|--------------------------------------------------------------------------
*/

try {

    $stmtUsuario = $pdo->prepare("
        SELECT *
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmtUsuario->execute([$usuarioId]);

    $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        die('Usuário não encontrado.');
    }

} catch (PDOException $e) {

    die(
        'Erro ao buscar usuário: ' .
        $e->getMessage()
    );
}


$nomeUsuario =
    $usuario['nomeCompleto']
    ?? $usuario['nome_completo']
    ?? $usuario['nome']
    ?? 'Usuário';


/*
|--------------------------------------------------------------------------
| FUNÇÃO DE DATA
|--------------------------------------------------------------------------
*/

function converterDataParaTela($data)
{
    if (empty($data)) {
        return '';
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        return '';
    }

    return date('d/m/Y', $timestamp);
}


/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

$sucesso = '';
$erro = '';


if (isset($_GET['sucesso'])) {

    if ($_GET['sucesso'] === 'cadastrado') {
        $sucesso = 'Pet cadastrado com sucesso!';
    }

    if ($_GET['sucesso'] === 'editado') {
        $sucesso = 'Informações do pet atualizadas com sucesso!';
    }

    if ($_GET['sucesso'] === 'excluido') {
        $sucesso = 'Pet excluído com sucesso!';
    }
}


/*
|--------------------------------------------------------------------------
| EXCLUIR PET
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['excluir']) &&
    is_numeric($_GET['excluir'])
) {

    $petId = (int) $_GET['excluir'];

    try {

        /*
         * Primeiro removemos as vacinas do pet.
         */

        $stmtVacinas = $pdo->prepare("
            DELETE FROM vacinacoes_pets
            WHERE pet_id = ?
        ");

        $stmtVacinas->execute([
            $petId
        ]);


        /*
         * Depois removemos o pet.
         */

        $stmtPet = $pdo->prepare("
            DELETE FROM pets
            WHERE id = ?
            AND usuario_id = ?
        ");

        $stmtPet->execute([
            $petId,
            $usuarioId
        ]);


        header(
            'Location: pets.php?sucesso=excluido'
        );

        exit;

    } catch (PDOException $e) {

        $erro =
            'Erro ao excluir o pet: ' .
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| BUSCAR PETS
|--------------------------------------------------------------------------
*/

try {

    $stmtPets = $pdo->prepare("
        SELECT *
        FROM pets
        WHERE usuario_id = ?
        ORDER BY id DESC
    ");

    $stmtPets->execute([
        $usuarioId
    ]);

    $pets = $stmtPets->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $pets = [];

    $erro =
        'Erro ao carregar seus pets: ' .
        $e->getMessage();
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

    <title>Meus Pets - Saúde Conecta</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        header {
            background: #0b7a75;
            color: white;
            padding: 20px;
        }

        .header-content {
            width: 92%;
            max-width: 1200px;
            margin: auto;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;
        }

        .logo {
            font-size: 25px;
            font-weight: bold;
        }

        .links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .links a {
            color: white;
            text-decoration: none;

            background: rgba(255,255,255,.15);

            padding: 10px 15px;

            border-radius: 8px;
        }

        .links a:hover {
            background: rgba(255,255,255,.25);
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 30px auto;
        }

        .intro {
            background: white;

            padding: 25px;

            border-radius: 14px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.08);

            margin-bottom: 25px;
        }

        .intro h1 {
            color: #0b7a75;
            margin-top: 0;
        }

        .botao-principal {
            display: inline-block;

            background: #0b7a75;
            color: white;

            text-decoration: none;

            padding: 13px 20px;

            border-radius: 8px;

            font-weight: bold;

            margin-top: 10px;
        }

        .botao-principal:hover {
            background: #08635f;
        }

        .botao-adocao {
            display: inline-block;

            background: #d97706;
            color: white;

            text-decoration: none;

            padding: 13px 20px;

            border-radius: 8px;

            font-weight: bold;

            margin-top: 10px;

            margin-left: 8px;
        }

        .botao-adocao:hover {
            background: #b45309;
        }

        .mensagem {
            padding: 15px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-weight: bold;
        }

        .sucesso {
            background: #dcfce7;
            color: #166534;
        }

        .erro {
            background: #fee2e2;
            color: #991b1b;
        }

        .titulo-secao {
            color: #0b7a75;

            margin-top: 30px;
            margin-bottom: 15px;
        }

        .pets-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }

        .pet-card {
            background: white;

            border-radius: 14px;

            overflow: hidden;

            box-shadow:
                0 3px 12px rgba(0,0,0,.08);

            border: 1px solid #e5e7eb;
        }

        .pet-foto {
            width: 100%;
            height: 220px;

            object-fit: cover;

            background: #e5e7eb;
        }

        .pet-sem-foto {
            width: 100%;
            height: 220px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #e5f5f3;

            font-size: 65px;
        }

        .pet-conteudo {
            padding: 20px;
        }

        .pet-nome {
            margin-top: 0;

            margin-bottom: 5px;

            color: #0b7a75;

            font-size: 23px;
        }

        .pet-tipo {
            color: #64748b;

            margin-bottom: 15px;
        }

        .pet-info {
            margin: 8px 0;

            line-height: 1.5;
        }

        .pet-info strong {
            color: #374151;
        }

        .botoes {
            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-top: 18px;
        }

        .botao {
            display: inline-block;

            text-decoration: none;

            border: 0;

            padding: 10px 12px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;
        }

        .botao-editar {
            background: #e0f2fe;
            color: #0369a1;
        }

        .botao-vacina {
            background: #dcfce7;
            color: #166534;
        }

        .botao-excluir {
            background: #fee2e2;
            color: #b91c1c;
        }

        .vazio {
            background: white;

            padding: 40px;

            text-align: center;

            border-radius: 14px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.08);
        }

        .vazio .icone {
            font-size: 65px;
            margin-bottom: 10px;
        }

        .adocao {
            margin-top: 30px;

            background: #fff7ed;

            border: 1px solid #fed7aa;

            padding: 25px;

            border-radius: 14px;
        }

        .adocao h2 {
            color: #c2410c;
            margin-top: 0;
        }

        @media (max-width: 950px) {

            .pets-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }

        @media (max-width: 650px) {

            .header-content {
                flex-direction: column;

                align-items: flex-start;
            }

            .pets-grid {
                grid-template-columns: 1fr;
            }

            .botao-adocao {
                margin-left: 0;
            }

        }

    </style>

</head>

<body>


<header>

    <div class="header-content">

        <div class="logo">
            Saúde Conecta
        </div>

        <div class="links">

            <a href="../components/perfil_saude.php">
                Perfil de Saúde
            </a>

            <a href="../components/mapa.php">
                Mapa de Saúde
            </a>

            <a href="../components/logout.php">
                Sair
            </a>

        </div>

    </div>

</header>


<div class="container">


    <section class="intro">

        <h1>
            🐾 Meus Pets
        </h1>

        <p>
            Olá, <?= htmlspecialchars($nomeUsuario) ?>!
        </p>

        <p>
            Aqui você pode cadastrar e acompanhar
            as informações dos seus animais.
        </p>

        <a
            href="cadastro_pet.php"
            class="botao-principal"
        >
            + Cadastrar Pet
        </a>

        <a
            href="adocao_pets.php"
            class="botao-adocao"
        >
            Adoção de Pets
        </a>

    </section>


    <?php if ($sucesso): ?>

        <div class="mensagem sucesso">
            <?= htmlspecialchars($sucesso) ?>
        </div>

    <?php endif; ?>


    <?php if ($erro): ?>

        <div class="mensagem erro">
            <?= htmlspecialchars($erro) ?>
        </div>

    <?php endif; ?>


    <h2 class="titulo-secao">
        Animais cadastrados
    </h2>


    <?php if (count($pets) > 0): ?>


        <div class="pets-grid">


            <?php foreach ($pets as $pet): ?>

                <article class="pet-card">


                    <?php

                    $foto =
                        trim(
                            $pet['foto'] ?? ''
                        );

                    if ($foto !== ''):

                    ?>

                        <img
                            src="<?= htmlspecialchars($foto) ?>"
                            alt="Foto de <?= htmlspecialchars($pet['nome']) ?>"
                            class="pet-foto"
                        >

                    <?php else: ?>

                        <div class="pet-sem-foto">

                            🐾

                        </div>

                    <?php endif; ?>


                    <div class="pet-conteudo">

                        <h2 class="pet-nome">

                            <?= htmlspecialchars(
                                $pet['nome']
                            ) ?>

                        </h2>


                        <div class="pet-tipo">

                            <?= htmlspecialchars(
                                $pet['tipo']
                            ) ?>

                            <?php if (!empty($pet['especie'])): ?>

                                —
                                <?= htmlspecialchars(
                                    $pet['especie']
                                ) ?>

                            <?php endif; ?>

                        </div>


                        <?php if (!empty($pet['raca'])): ?>

                            <div class="pet-info">

                                <strong>
                                    Raça:
                                </strong>

                                <?= htmlspecialchars(
                                    $pet['raca']
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($pet['sexo'])): ?>

                            <div class="pet-info">

                                <strong>
                                    Sexo:
                                </strong>

                                <?= htmlspecialchars(
                                    $pet['sexo']
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($pet['data_nascimento'])): ?>

                            <div class="pet-info">

                                <strong>
                                    Nascimento:
                                </strong>

                                <?= htmlspecialchars(
                                    converterDataParaTela(
                                        $pet['data_nascimento']
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($pet['cor'])): ?>

                            <div class="pet-info">

                                <strong>
                                    Cor:
                                </strong>

                                <?= htmlspecialchars(
                                    $pet['cor']
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            isset($pet['peso']) &&
                            $pet['peso'] !== null &&
                            $pet['peso'] !== ''
                        ): ?>

                            <div class="pet-info">

                                <strong>
                                    Peso:
                                </strong>

                                <?= htmlspecialchars(
                                    $pet['peso']
                                ) ?>

                                kg

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($pet['vacinado'])): ?>

                            <div class="pet-info">

                                <strong>
                                    Vacinado:
                                </strong>

                                <?= htmlspecialchars(
                                    $pet['vacinado']
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($pet['castrado'])): ?>

                            <div class="pet-info">

                                <strong>
                                    Castrado:
                                </strong>

                                <?= htmlspecialchars(
                                    $pet['castrado']
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <div class="botoes">

                            <a
                                href="editar_pet.php?id=<?= (int) $pet['id'] ?>"
                                class="botao botao-editar"
                            >
                                Editar
                            </a>


                            <a
                                href="vacinacao_pet.php?pet_id=<?= (int) $pet['id'] ?>"
                                class="botao botao-vacina"
                            >
                                Vacinação
                            </a>


                            <a
                                href="pet.php?id=<?= (int) $pet['id'] ?>"
                                class="botao botao-editar"
                            >
                                Ver perfil
                            </a>


                            <a
                                href="pets.php?excluir=<?= (int) $pet['id'] ?>"
                                class="botao botao-excluir"
                                onclick="return confirmarExclusao('<?= htmlspecialchars(addslashes($pet['nome'])) ?>');"
                            >
                                Excluir
                            </a>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="vazio">

            <div class="icone">
                🐾
            </div>

            <h2>
                Você ainda não cadastrou nenhum pet.
            </h2>

            <p>
                Cadastre seu primeiro animal para
                começar a montar o perfil de saúde dele.
            </p>

            <a
                href="cadastro_pet.php"
                class="botao-principal"
            >
                Cadastrar meu primeiro pet
            </a>

        </div>


    <?php endif; ?>


    <section class="adocao">

        <h2>
            🐶 Adoção de Pets
        </h2>

        <p>
            Está procurando um animal para adotar?
            Na área de adoção vamos reunir informações
            sobre pets disponíveis, instituições,
            locais de atendimento e formas de contato.
        </p>

        <a
            href="adocao_pets.php"
            class="botao-adocao"
        >
            Ver opções de adoção
        </a>

    </section>


</div>


<script>

function confirmarExclusao(nome) {

    return confirm(
        'Tem certeza que deseja excluir o pet "' +
        nome +
        '"?\n\n' +
        'As informações de vacinação desse pet também serão excluídas.'
    );
}

</script>


</body>

</html>