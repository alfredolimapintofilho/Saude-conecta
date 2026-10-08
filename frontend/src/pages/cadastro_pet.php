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
| ID DO PET
|--------------------------------------------------------------------------
*/

$petId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($petId <= 0) {
    header('Location: pets.php');
    exit;
}


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
| BUSCAR PET
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM pets
        WHERE id = ?
        AND usuario_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $petId,
        $usuarioId
    ]);

    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        header('Location: pets.php');
        exit;
    }

} catch (PDOException $e) {

    die(
        'Erro ao buscar o pet: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| BUSCAR VACINAS DO PET
|--------------------------------------------------------------------------
*/

try {

    $stmtVacinas = $pdo->prepare("
        SELECT *
        FROM vacinacoes_pets
        WHERE pet_id = ?
        ORDER BY data_aplicacao DESC, id DESC
    ");

    $stmtVacinas->execute([
        $petId
    ]);

    $vacinas = $stmtVacinas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $vacinas = [];
}


/*
|--------------------------------------------------------------------------
| CONTAGEM DE VACINAS
|--------------------------------------------------------------------------
*/

$totalVacinas = count($vacinas);

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
        <?= htmlspecialchars($pet['nome']) ?>
        - Saúde Conecta
    </title>


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

            flex-wrap: wrap;

            gap: 10px;
        }


        .links a {
            color: white;

            text-decoration: none;

            background:
                rgba(255,255,255,.15);

            padding: 10px 15px;

            border-radius: 8px;
        }


        .container {
            width: 92%;

            max-width: 1200px;

            margin: 30px auto;
        }


        .voltar {
            display: inline-block;

            margin-bottom: 20px;

            color: #0b7a75;

            text-decoration: none;

            font-weight: bold;
        }


        .perfil {
            background: white;

            border-radius: 15px;

            overflow: hidden;

            box-shadow:
                0 3px 15px rgba(0,0,0,.08);

            margin-bottom: 25px;
        }


        .topo {
            display: grid;

            grid-template-columns:
                320px 1fr;
        }


        .foto-area {
            min-height: 320px;

            background: #e5f5f3;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .foto {
            width: 100%;

            height: 320px;

            object-fit: cover;
        }


        .sem-foto {
            font-size: 100px;
        }


        .dados-principais {
            padding: 35px;
        }


        .dados-principais h1 {
            margin-top: 0;

            margin-bottom: 8px;

            color: #0b7a75;

            font-size: 35px;
        }


        .tipo {
            color: #64748b;

            font-size: 18px;

            margin-bottom: 25px;
        }


        .acoes {
            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 25px;
        }


        .botao {
            display: inline-block;

            text-decoration: none;

            padding: 12px 16px;

            border-radius: 8px;

            font-weight: bold;
        }


        .botao-principal {
            background: #0b7a75;

            color: white;
        }


        .botao-verde {
            background: #dcfce7;

            color: #166534;
        }


        .botao-editar {
            background: #e0f2fe;

            color: #0369a1;
        }


        .secao {
            background: white;

            padding: 25px;

            border-radius: 14px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.08);

            margin-bottom: 25px;
        }


        .secao h2 {
            color: #0b7a75;

            margin-top: 0;

            border-bottom:
                1px solid #e5e7eb;

            padding-bottom: 12px;
        }


        .dados-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }


        .dado {
            background: #f8fafc;

            padding: 15px;

            border-radius: 9px;

            border: 1px solid #e5e7eb;
        }


        .dado strong {
            display: block;

            color: #64748b;

            font-size: 13px;

            margin-bottom: 5px;
        }


        .texto {
            background: #f8fafc;

            padding: 15px;

            border-radius: 9px;

            margin-bottom: 12px;

            line-height: 1.6;
        }


        .vacinas {
            display: grid;

            gap: 12px;
        }


        .vacina {
            border: 1px solid #e5e7eb;

            border-radius: 10px;

            padding: 17px;

            background: #f8fafc;
        }


        .vacina h3 {
            margin-top: 0;

            color: #166534;
        }


        .vacina-info {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 8px;
        }


        .vazio {
            padding: 25px;

            text-align: center;

            background: #f8fafc;

            border-radius: 10px;

            color: #64748b;
        }


        .contador {
            display: inline-block;

            background: #dcfce7;

            color: #166534;

            padding: 8px 12px;

            border-radius: 20px;

            font-weight: bold;

            margin-bottom: 15px;
        }


        @media (max-width: 850px) {

            .topo {
                grid-template-columns: 1fr;
            }

            .foto-area {
                min-height: 250px;
            }

            .foto {
                height: 250px;
            }

            .dados-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 550px) {

            .header-content {
                flex-direction: column;

                align-items: flex-start;
            }

            .dados-grid {
                grid-template-columns: 1fr;
            }

            .vacina-info {
                grid-template-columns: 1fr;
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

            <a href="pets.php">
                Meus Pets
            </a>

            <a href="../components/perfil_saude.php">
                Perfil de Saúde
            </a>

            <a href="../components/mapa.php">
                Mapa
            </a>

            <a href="../components/logout.php">
                Sair
            </a>

        </div>

    </div>

</header>


<div class="container">


    <a
        href="pets.php"
        class="voltar"
    >
        ← Voltar para Meus Pets
    </a>


    <section class="perfil">


        <div class="topo">


            <div class="foto-area">

                <?php if (!empty($pet['foto'])): ?>

                    <img
                        src="<?= htmlspecialchars($pet['foto']) ?>"
                        alt="Foto de <?= htmlspecialchars($pet['nome']) ?>"
                        class="foto"
                    >

                <?php else: ?>

                    <div class="sem-foto">
                        🐾
                    </div>

                <?php endif; ?>

            </div>


            <div class="dados-principais">


                <h1>
                    <?= htmlspecialchars($pet['nome']) ?>
                </h1>


                <div class="tipo">

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

                    <p>
                        <strong>Raça:</strong>
                        <?= htmlspecialchars(
                            $pet['raca']
                        ) ?>
                    </p>

                <?php endif; ?>


                <?php if (!empty($pet['sexo'])): ?>

                    <p>
                        <strong>Sexo:</strong>
                        <?= htmlspecialchars(
                            $pet['sexo']
                        ) ?>
                    </p>

                <?php endif; ?>


                <?php if (!empty($pet['data_nascimento'])): ?>

                    <p>
                        <strong>
                            Data de nascimento:
                        </strong>

                        <?= converterDataParaTela(
                            $pet['data_nascimento']
                        ) ?>

                    </p>

                <?php endif; ?>


                <div class="acoes">

                    <a
                        href="editar_pet.php?id=<?= (int) $pet['id'] ?>"
                        class="botao botao-editar"
                    >
                        Editar pet
                    </a>


                    <a
                        href="vacinacao_pet.php?pet_id=<?= (int) $pet['id'] ?>"
                        class="botao botao-verde"
                    >
                        Carteira de vacinação
                    </a>

                </div>


            </div>


        </div>


    </section>


    <section class="secao">


        <h2>
            Informações do Pet
        </h2>


        <div class="dados-grid">


            <?php if (!empty($pet['raca'])): ?>

                <div class="dado">

                    <strong>
                        Raça
                    </strong>

                    <?= htmlspecialchars(
                        $pet['raca']
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($pet['sexo'])): ?>

                <div class="dado">

                    <strong>
                        Sexo
                    </strong>

                    <?= htmlspecialchars(
                        $pet['sexo']
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($pet['data_nascimento'])): ?>

                <div class="dado">

                    <strong>
                        Data de nascimento
                    </strong>

                    <?= converterDataParaTela(
                        $pet['data_nascimento']
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($pet['cor'])): ?>

                <div class="dado">

                    <strong>
                        Cor
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

                <div class="dado">

                    <strong>
                        Peso
                    </strong>

                    <?= htmlspecialchars(
                        $pet['peso']
                    ) ?>

                    kg

                </div>

            <?php endif; ?>


            <?php if (!empty($pet['microchip'])): ?>

                <div class="dado">

                    <strong>
                        Microchip / identificação
                    </strong>

                    <?= htmlspecialchars(
                        $pet['microchip']
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($pet['vacinado'])): ?>

                <div class="dado">

                    <strong>
                        Vacinado
                    </strong>

                    <?= htmlspecialchars(
                        $pet['vacinado']
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($pet['castrado'])): ?>

                <div class="dado">

                    <strong>
                        Castrado
                    </strong>

                    <?= htmlspecialchars(
                        $pet['castrado']
                    ) ?>

                </div>

            <?php endif; ?>


        </div>


    </section>


    <section class="secao">


        <h2>
            Saúde do Pet
        </h2>


        <?php if (!empty($pet['alergias'])): ?>

            <div class="texto">

                <strong>
                    Alergias
                </strong>

                <br>

                <?= nl2br(
                    htmlspecialchars(
                        $pet['alergias']
                    )
                ) ?>

            </div>

        <?php else: ?>

            <div class="texto">

                <strong>
                    Alergias:
                </strong>

                Nenhuma informação cadastrada.

            </div>

        <?php endif; ?>


        <?php if (!empty($pet['doencas'])): ?>

            <div class="texto">

                <strong>
                    Doenças / histórico:
                </strong>

                <br>

                <?= nl2br(
                    htmlspecialchars(
                        $pet['doencas']
                    )
                ) ?>

            </div>

        <?php else: ?>

            <div class="texto">

                <strong>
                    Doenças / histórico:
                </strong>

                Nenhuma informação cadastrada.

            </div>

        <?php endif; ?>


        <?php if (!empty($pet['medicamentos'])): ?>

            <div class="texto">

                <strong>
                    Medicamentos:
                </strong>

                <br>

                <?= nl2br(
                    htmlspecialchars(
                        $pet['medicamentos']
                    )
                ) ?>

            </div>

        <?php else: ?>

            <div class="texto">

                <strong>
                    Medicamentos:
                </strong>

                Nenhum medicamento cadastrado.

            </div>

        <?php endif; ?>


        <?php if (!empty($pet['observacoes'])): ?>

            <div class="texto">

                <strong>
                    Observações:
                </strong>

                <br>

                <?= nl2br(
                    htmlspecialchars(
                        $pet['observacoes']
                    )
                ) ?>

            </div>

        <?php endif; ?>


    </section>


    <section class="secao">


        <h2>
            Carteira de Vacinação
        </h2>


        <div class="contador">

            <?= $totalVacinas ?>

            vacina(s) registrada(s)

        </div>


        <?php if ($totalVacinas > 0): ?>


            <div class="vacinas">


                <?php foreach ($vacinas as $vacina): ?>

                    <div class="vacina">


                        <h3>

                            <?= htmlspecialchars(
                                $vacina['vacina']
                            ) ?>

                        </h3>


                        <div class="vacina-info">


                            <?php if (!empty($vacina['data_aplicacao'])): ?>

                                <div>

                                    <strong>
                                        Data:
                                    </strong>

                                    <?= converterDataParaTela(
                                        $vacina['data_aplicacao']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (!empty($vacina['dose'])): ?>

                                <div>

                                    <strong>
                                        Dose:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vacina['dose']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (!empty($vacina['lote'])): ?>

                                <div>

                                    <strong>
                                        Lote:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vacina['lote']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (!empty($vacina['veterinario'])): ?>

                                <div>

                                    <strong>
                                        Veterinário:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vacina['veterinario']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        </div>


                        <?php if (!empty($vacina['observacao'])): ?>

                            <p>

                                <strong>
                                    Observação:
                                </strong>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $vacina['observacao']
                                    )
                                ) ?>

                            </p>

                        <?php endif; ?>


                    </div>

                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="vazio">

                <p>
                    Nenhuma vacina foi registrada para este pet.
                </p>

                <a
                    href="vacinacao_pet.php?pet_id=<?= (int) $pet['id'] ?>"
                    class="botao botao-principal"
                >
                    + Registrar vacinação
                </a>

            </div>


        <?php endif; ?>


    </section>


</div>


</body>

</html>