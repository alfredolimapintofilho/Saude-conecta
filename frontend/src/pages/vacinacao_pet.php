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

$petId = isset($_GET['pet_id'])
    ? (int) $_GET['pet_id']
    : 0;

if ($petId <= 0) {
    header('Location: pets.php');
    exit;
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
| CONVERTER DATA DD/MM/AAAA PARA MYSQL
|--------------------------------------------------------------------------
*/

function converterDataBanco($data)
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    if (!preg_match(
        '/^(\d{2})\/(\d{2})\/(\d{4})$/',
        $data,
        $matches
    )) {
        return false;
    }

    $dia = (int) $matches[1];
    $mes = (int) $matches[2];
    $ano = (int) $matches[3];

    if (!checkdate($mes, $dia, $ano)) {
        return false;
    }

    return sprintf(
        '%04d-%02d-%02d',
        $ano,
        $mes,
        $dia
    );
}


/*
|--------------------------------------------------------------------------
| CONVERTER DATA DO BANCO PARA DD/MM/AAAA
|--------------------------------------------------------------------------
*/

function converterDataTela($data)
{
    if (empty($data)) {
        return '';
    }

    $partes = explode('-', $data);

    if (count($partes) !== 3) {
        return '';
    }

    return $partes[2] . '/' .
           $partes[1] . '/' .
           $partes[0];
}


/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$erro = '';
$sucesso = '';

$modoEdicao = false;

$vacinaEditando = null;

$nomeVacina = '';
$dataAplicacao = '';
$dose = '';
$lote = '';
$veterinario = '';
$observacao = '';


/*
|--------------------------------------------------------------------------
| EXCLUIR VACINA
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['acao']) &&
    $_GET['acao'] === 'excluir' &&
    isset($_GET['id'])
) {

    $vacinaId = (int) $_GET['id'];

    if ($vacinaId > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM vacinacoes_pets
                WHERE id = ?
                AND pet_id = ?
            ");

            $stmt->execute([
                $vacinaId,
                $petId
            ]);

            header(
                'Location: vacinacao_pet.php?pet_id=' .
                $petId .
                '&excluida=1'
            );

            exit;

        } catch (PDOException $e) {

            $erro =
                'Erro ao excluir a vacinação: ' .
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| MODO DE EDIÇÃO
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['acao']) &&
    $_GET['acao'] === 'editar' &&
    isset($_GET['id'])
) {

    $vacinaId = (int) $_GET['id'];

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM vacinacoes_pets
            WHERE id = ?
            AND pet_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $vacinaId,
            $petId
        ]);

        $vacinaEditando = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vacinaEditando) {

            $modoEdicao = true;

            $nomeVacina =
                $vacinaEditando['vacina'] ?? '';

            $dataAplicacao =
                converterDataTela(
                    $vacinaEditando['data_aplicacao'] ?? ''
                );

            $dose =
                $vacinaEditando['dose'] ?? '';

            $lote =
                $vacinaEditando['lote'] ?? '';

            $veterinario =
                $vacinaEditando['veterinario'] ?? '';

            $observacao =
                $vacinaEditando['observacao'] ?? '';

        } else {

            $erro = 'Vacinação não encontrada.';
        }

    } catch (PDOException $e) {

        $erro =
            'Erro ao carregar vacinação: ' .
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| PROCESSAR FORMULÁRIO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acaoFormulario =
        $_POST['acao'] ?? 'cadastrar';

    $vacinaId =
        isset($_POST['vacina_id'])
        ? (int) $_POST['vacina_id']
        : 0;

    $nomeVacina =
        trim($_POST['vacina'] ?? '');

    $dataAplicacaoTexto =
        trim($_POST['data_aplicacao'] ?? '');

    $dose =
        trim($_POST['dose'] ?? '');

    $lote =
        trim($_POST['lote'] ?? '');

    $veterinario =
        trim($_POST['veterinario'] ?? '');

    $observacao =
        trim($_POST['observacao'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDAR VACINA
    |--------------------------------------------------------------------------
    */

    if ($nomeVacina === '') {

        $erro = 'Informe o nome da vacina.';
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDAR DATA
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        $dataAplicacao =
            converterDataBanco(
                $dataAplicacaoTexto
            );

        if ($dataAplicacao === false) {

            $erro =
                'Informe uma data válida no formato DD/MM/AAAA.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CADASTRAR
    |--------------------------------------------------------------------------
    */

    if (
        $erro === '' &&
        $acaoFormulario === 'cadastrar'
    ) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO vacinacoes_pets
                (
                    pet_id,
                    vacina,
                    data_aplicacao,
                    dose,
                    lote,
                    veterinario,
                    observacao
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $petId,
                $nomeVacina,
                $dataAplicacao,
                $dose !== '' ? $dose : null,
                $lote !== '' ? $lote : null,
                $veterinario !== '' ? $veterinario : null,
                $observacao !== '' ? $observacao : null
            ]);

            header(
                'Location: vacinacao_pet.php?pet_id=' .
                $petId .
                '&sucesso=1'
            );

            exit;

        } catch (PDOException $e) {

            $erro =
                'Erro ao cadastrar vacinação: ' .
                $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ATUALIZAR
    |--------------------------------------------------------------------------
    */

    if (
        $erro === '' &&
        $acaoFormulario === 'editar'
    ) {

        if ($vacinaId <= 0) {

            $erro =
                'Vacinação inválida.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    UPDATE vacinacoes_pets
                    SET
                        vacina = ?,
                        data_aplicacao = ?,
                        dose = ?,
                        lote = ?,
                        veterinario = ?,
                        observacao = ?
                    WHERE id = ?
                    AND pet_id = ?
                ");

                $stmt->execute([
                    $nomeVacina,
                    $dataAplicacao,
                    $dose !== '' ? $dose : null,
                    $lote !== '' ? $lote : null,
                    $veterinario !== '' ? $veterinario : null,
                    $observacao !== '' ? $observacao : null,
                    $vacinaId,
                    $petId
                ]);

                header(
                    'Location: vacinacao_pet.php?pet_id=' .
                    $petId .
                    '&atualizada=1'
                );

                exit;

            } catch (PDOException $e) {

                $erro =
                    'Erro ao atualizar vacinação: ' .
                    $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

if (isset($_GET['sucesso'])) {
    $sucesso =
        'Vacinação cadastrada com sucesso.';
}

if (isset($_GET['atualizada'])) {
    $sucesso =
        'Vacinação atualizada com sucesso.';
}

if (isset($_GET['excluida'])) {
    $sucesso =
        'Vacinação excluída com sucesso.';
}


/*
|--------------------------------------------------------------------------
| LISTAR VACINAS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM vacinacoes_pets
        WHERE pet_id = ?
        ORDER BY data_aplicacao DESC, id DESC
    ");

    $stmt->execute([
        $petId
    ]);

    $vacinas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $vacinas = [];

    if ($erro === '') {

        $erro =
            'Erro ao listar vacinações: ' .
            $e->getMessage();
    }
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

    <title>
        Vacinação -
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

            justify-content: space-between;

            align-items: center;

            gap: 20px;
        }


        .logo {
            font-size: 24px;

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

            padding: 9px 13px;

            background:
                rgba(255,255,255,.15);

            border-radius: 8px;
        }


        .container {
            width: 92%;

            max-width: 1100px;

            margin: 30px auto;
        }


        .voltar {
            display: inline-block;

            margin-bottom: 20px;

            color: #0b7a75;

            text-decoration: none;

            font-weight: bold;
        }


        .pet-header {
            background: white;

            padding: 20px;

            border-radius: 14px;

            margin-bottom: 20px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.07);
        }


        .pet-header h1 {
            margin: 0 0 5px;

            color: #0b7a75;
        }


        .pet-header p {
            margin: 5px 0;

            color: #64748b;
        }


        .card {
            background: white;

            padding: 25px;

            border-radius: 14px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.07);
        }


        .card h2 {
            margin-top: 0;

            color: #0b7a75;
        }


        .mensagem {
            padding: 14px;

            border-radius: 8px;

            margin-bottom: 18px;
        }


        .erro {
            background: #fee2e2;

            color: #991b1b;
        }


        .sucesso {
            background: #dcfce7;

            color: #166534;
        }


        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 16px;
        }


        .campo {
            display: flex;

            flex-direction: column;

            gap: 6px;
        }


        .campo-completo {
            grid-column: 1 / -1;
        }


        label {
            font-weight: bold;

            color: #374151;
        }


        input,
        textarea {
            width: 100%;

            padding: 12px;

            border:
                1px solid #cbd5e1;

            border-radius: 8px;

            font-size: 15px;

            outline: none;
        }


        input:focus,
        textarea:focus {
            border-color: #0b7a75;

            box-shadow:
                0 0 0 3px
                rgba(11,122,117,.10);
        }


        textarea {
            min-height: 100px;

            resize: vertical;
        }


        .ajuda {
            font-size: 13px;

            color: #64748b;
        }


        .botoes {
            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 20px;
        }


        button,
        .botao {
            border: none;

            padding: 12px 18px;

            border-radius: 8px;

            cursor: pointer;

            text-decoration: none;

            font-weight: bold;

            display: inline-block;
        }


        .principal {
            background: #0b7a75;

            color: white;
        }


        .cancelar {
            background: #e5e7eb;

            color: #374151;
        }


        .lista {
            display: grid;

            gap: 15px;
        }


        .vacina {
            border:
                1px solid #e5e7eb;

            border-radius: 10px;

            padding: 18px;

            background: #f8fafc;
        }


        .vacina h3 {
            margin-top: 0;

            color: #166534;
        }


        .informacoes {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 8px;

            margin-bottom: 10px;
        }


        .acoes {
            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-top: 15px;
        }


        .editar {
            background: #e0f2fe;

            color: #0369a1;
        }


        .excluir {
            background: #fee2e2;

            color: #991b1b;
        }


        .vazio {
            text-align: center;

            color: #64748b;

            background: #f8fafc;

            padding: 25px;

            border-radius: 10px;
        }


        @media (max-width: 700px) {

            .header-content {
                flex-direction: column;

                align-items: flex-start;
            }


            .form-grid {
                grid-template-columns: 1fr;
            }


            .campo-completo {
                grid-column: auto;
            }


            .informacoes {
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

            <a
                href="pet.php?id=<?= (int) $pet['id'] ?>"
            >
                Perfil do Pet
            </a>

            <a href="../components/perfil_saude.php">
                Perfil de Saúde
            </a>

            <a href="../components/logout.php">
                Sair
            </a>

        </div>

    </div>

</header>


<div class="container">


    <a
        href="pet.php?id=<?= (int) $pet['id'] ?>"
        class="voltar"
    >
        ← Voltar para o perfil do pet
    </a>


    <div class="pet-header">

        <h1>
            💉 Carteira de Vacinação
        </h1>

        <p>

            Pet:
            <strong>
                <?= htmlspecialchars(
                    $pet['nome']
                ) ?>
            </strong>

        </p>

        <p>

            Tipo:
            <?= htmlspecialchars(
                $pet['tipo']
            ) ?>

        </p>

    </div>


    <?php if ($erro !== ''): ?>

        <div class="mensagem erro">

            <?= htmlspecialchars($erro) ?>

        </div>

    <?php endif; ?>


    <?php if ($sucesso !== ''): ?>

        <div class="mensagem sucesso">

            <?= htmlspecialchars($sucesso) ?>

        </div>

    <?php endif; ?>


    <section class="card">

        <h2>

            <?= $modoEdicao
                ? 'Editar vacinação'
                : 'Cadastrar vacinação'
            ?>

        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="acao"
                value="<?= $modoEdicao
                    ? 'editar'
                    : 'cadastrar'
                ?>"
            >


            <?php if ($modoEdicao): ?>

                <input
                    type="hidden"
                    name="vacina_id"
                    value="<?= (int)
                        $vacinaEditando['id']
                    ?>"
                >

            <?php endif; ?>


            <div class="form-grid">


                <div class="campo">

                    <label for="vacina">
                        Nome da vacina *
                    </label>

                    <input
                        type="text"
                        id="vacina"
                        name="vacina"
                        value="<?= htmlspecialchars(
                            $nomeVacina
                        ) ?>"
                        placeholder="Ex.: Vacina antirrábica"
                        required
                    >

                </div>


                <div class="campo">

                    <label for="data_aplicacao">
                        Data da aplicação *
                    </label>

                    <input
                        type="text"
                        id="data_aplicacao"
                        name="data_aplicacao"
                        value="<?= htmlspecialchars(
                            $dataAplicacao
                        ) ?>"
                        placeholder="DD/MM/AAAA"
                        maxlength="10"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                    >

                    <span class="ajuda">
                        Digite a data manualmente no formato DD/MM/AAAA.
                    </span>

                </div>


                <div class="campo">

                    <label for="dose">
                        Dose
                    </label>

                    <input
                        type="text"
                        id="dose"
                        name="dose"
                        value="<?= htmlspecialchars(
                            $dose
                        ) ?>"
                        placeholder="Ex.: 1ª dose, reforço, única"
                    >

                </div>


                <div class="campo">

                    <label for="lote">
                        Lote
                    </label>

                    <input
                        type="text"
                        id="lote"
                        name="lote"
                        value="<?= htmlspecialchars(
                            $lote
                        ) ?>"
                        placeholder="Número do lote"
                    >

                </div>


                <div class="campo campo-completo">

                    <label for="veterinario">
                        Veterinário
                    </label>

                    <input
                        type="text"
                        id="veterinario"
                        name="veterinario"
                        value="<?= htmlspecialchars(
                            $veterinario
                        ) ?>"
                        placeholder="Nome do veterinário"
                    >

                </div>


                <div class="campo campo-completo">

                    <label for="observacao">
                        Observação
                    </label>

                    <textarea
                        id="observacao"
                        name="observacao"
                        placeholder="Informações adicionais sobre a vacinação..."
                    ><?= htmlspecialchars(
                        $observacao
                    ) ?></textarea>

                </div>


            </div>


            <div class="botoes">

                <button
                    type="submit"
                    class="principal"
                >

                    <?= $modoEdicao
                        ? 'Salvar alterações'
                        : 'Cadastrar vacinação'
                    ?>

                </button>


                <?php if ($modoEdicao): ?>

                    <a
                        href="vacinacao_pet.php?pet_id=<?= (int) $petId ?>"
                        class="botao cancelar"
                    >
                        Cancelar edição
                    </a>

                <?php endif; ?>

            </div>


        </form>


    </section>


    <section class="card">


        <h2>
            Vacinas registradas
        </h2>


        <?php if (count($vacinas) > 0): ?>


            <div class="lista">


                <?php foreach ($vacinas as $vacina): ?>


                    <div class="vacina">


                        <h3>

                            <?= htmlspecialchars(
                                $vacina['vacina']
                            ) ?>

                        </h3>


                        <div class="informacoes">


                            <?php if (
                                !empty(
                                    $vacina['data_aplicacao']
                                )
                            ): ?>

                                <div>

                                    <strong>
                                        Data:
                                    </strong>

                                    <?= converterDataTela(
                                        $vacina[
                                            'data_aplicacao'
                                        ]
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $vacina['dose']
                                )
                            ): ?>

                                <div>

                                    <strong>
                                        Dose:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vacina['dose']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $vacina['lote']
                                )
                            ): ?>

                                <div>

                                    <strong>
                                        Lote:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vacina['lote']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $vacina['veterinario']
                                )
                            ): ?>

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


                        <?php if (
                            !empty(
                                $vacina['observacao']
                            )
                        ): ?>

                            <p>

                                <strong>
                                    Observação:
                                </strong>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $vacina[
                                            'observacao'
                                        ]
                                    )
                                ) ?>

                            </p>

                        <?php endif; ?>


                        <div class="acoes">


                            <a
                                href="vacinacao_pet.php?pet_id=<?= (int) $petId ?>&acao=editar&id=<?= (int) $vacina['id'] ?>"
                                class="botao editar"
                            >
                                Editar
                            </a>


                            <a
                                href="vacinacao_pet.php?pet_id=<?= (int) $petId ?>&acao=excluir&id=<?= (int) $vacina['id'] ?>"
                                class="botao excluir"
                                onclick="return confirm('Tem certeza que deseja excluir esta vacinação?');"
                            >
                                Excluir
                            </a>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="vazio">

                Nenhuma vacinação foi cadastrada para este pet.

            </div>


        <?php endif; ?>


    </section>


</div>


<script>

/*
|--------------------------------------------------------------------------
| MÁSCARA DE DATA
|--------------------------------------------------------------------------
*/

const campoData =
    document.getElementById('data_aplicacao');


if (campoData) {

    campoData.addEventListener(
        'input',
        function () {

            let valor =
                this.value.replace(/\D/g, '');

            if (valor.length > 8) {
                valor =
                    valor.substring(0, 8);
            }

            if (valor.length >= 5) {

                valor =
                    valor.substring(0, 2) +
                    '/' +
                    valor.substring(2, 4) +
                    '/' +
                    valor.substring(4);

            } else if (valor.length >= 3) {

                valor =
                    valor.substring(0, 2) +
                    '/' +
                    valor.substring(2);

            }

            this.value = valor;

        }
    );

}

</script>


</body>

</html>