<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

$pdo = Database::getConnection();

$usuario = $_SESSION['usuario'];

$usuarioId = 0;

if (is_array($usuario)) {

    if (isset($usuario['id'])) {
        $usuarioId = (int) $usuario['id'];
    } elseif (isset($usuario['id_usuario'])) {
        $usuarioId = (int) $usuario['id_usuario'];
    }
}

if ($usuarioId <= 0) {

    session_destroy();

    header('Location: login.php');

    exit;
}

$erro = '';

/*
|--------------------------------------------------------------------------
| CADASTRAR ALERGIA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $alergia = trim(
        $_POST['alergia'] ?? ''
    );

    $tipo = trim(
        $_POST['tipo'] ?? ''
    );

    $gravidade = trim(
        $_POST['gravidade'] ?? ''
    );

    $reacao = trim(
        $_POST['reacao'] ?? ''
    );

    $observacao = trim(
        $_POST['observacao'] ?? ''
    );

    if ($alergia === '') {

        $erro = 'Informe o nome da alergia.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO alergias
                (
                    usuario_id,
                    alergia,
                    tipo,
                    gravidade,
                    reacao,
                    observacao
                )
                VALUES
                (
                    :usuario_id,
                    :alergia,
                    :tipo,
                    :gravidade,
                    :reacao,
                    :observacao
                )
            ");

            $stmt->execute([

                ':usuario_id' => $usuarioId,

                ':alergia' => $alergia,

                ':tipo' => $tipo !== ''
                    ? $tipo
                    : null,

                ':gravidade' => $gravidade !== ''
                    ? $gravidade
                    : null,

                ':reacao' => $reacao !== ''
                    ? $reacao
                    : null,

                ':observacao' => $observacao !== ''
                    ? $observacao
                    : null
            ]);

            header(
                'Location: alergias.php'
            );

            exit;

        } catch (PDOException $e) {

            $erro =
                'Erro ao cadastrar alergia: ' .
                $e->getMessage();
        }
    }
}

function e($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
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
        Cadastrar Alergia - Saúde-Conecta
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

            background: #f4f8fb;

            color: #263238;
        }

        .topo {

            background: #0b7fab;

            color: white;

            padding: 20px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            flex-wrap: wrap;
        }

        .topo h1 {

            margin: 0;

            font-size: 25px;
        }

        .topo p {

            margin: 5px 0 0;

            font-size: 14px;
        }

        .acoes-topo {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;
        }

        .botao {

            display: inline-block;

            padding: 11px 17px;

            border-radius: 8px;

            text-decoration: none;

            border: none;

            cursor: pointer;

            font-size: 14px;

            font-weight: bold;
        }

        .botao-voltar {

            background: white;

            color: #0b7fab;
        }

        .botao-sair {

            background: #c62828;

            color: white;
        }

        .container {

            max-width: 850px;

            margin: 35px auto;

            padding: 0 20px;
        }

        .card {

            background: white;

            border-radius: 14px;

            padding: 30px;

            box-shadow:
                0 5px 20px
                rgba(0, 0, 0, 0.08);
        }

        h2 {

            margin-top: 0;

            color: #0b7fab;
        }

        .descricao {

            color: #607d8b;

            margin-bottom: 25px;
        }

        .mensagem {

            padding: 14px;

            border-radius: 8px;

            background: #ffebee;

            color: #c62828;

            border: 1px solid #ef9a9a;

            margin-bottom: 20px;

            font-weight: bold;
        }

        .formulario {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 20px;
        }

        .campo {

            display: flex;

            flex-direction: column;
        }

        .campo-completo {

            grid-column: 1 / -1;
        }

        label {

            margin-bottom: 7px;

            font-weight: bold;

            color: #37474f;
        }

        input,
        select,
        textarea {

            width: 100%;

            padding: 13px;

            border:
                1px solid #cfd8dc;

            border-radius: 8px;

            font-size: 15px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }

        input:focus,
        select:focus,
        textarea:focus {

            outline: none;

            border-color: #0b7fab;

            box-shadow:
                0 0 0 2px
                rgba(11, 127, 171, 0.12);
        }

        textarea {

            min-height: 120px;

            resize: vertical;
        }

        .botoes {

            grid-column: 1 / -1;

            display: flex;

            justify-content: flex-end;

            gap: 12px;

            margin-top: 5px;
        }

        .botao-cancelar {

            background: #eceff1;

            color: #37474f;
        }

        .botao-salvar {

            background: #2e7d32;

            color: white;
        }

        .informacao {

            margin-top: 25px;

            padding: 15px;

            background: #e3f2fd;

            color: #1565c0;

            border-radius: 8px;

            font-size: 14px;

            line-height: 1.5;
        }

        @media (max-width: 700px) {

            .topo {

                flex-direction: column;

                align-items: flex-start;
            }

            .formulario {

                grid-template-columns: 1fr;
            }

            .campo-completo {

                grid-column: auto;
            }

            .botoes {

                grid-column: auto;

                flex-direction: column;
            }

            .botoes .botao {

                width: 100%;

                text-align: center;
            }

        }

    </style>

</head>

<body>

<header class="topo">

    <div>

        <h1>
            Saúde-Conecta
        </h1>

        <p>
            Cadastro de alergia
        </p>

    </div>

    <div class="acoes-topo">

        <a
            href="alergias.php"
            class="botao botao-voltar"
        >
            ← Voltar para alergias
        </a>

        <a
            href="logout.php"
            class="botao botao-sair"
        >
            Sair
        </a>

    </div>

</header>

<main class="container">

    <section class="card">

        <h2>
            Cadastrar alergia
        </h2>

        <p class="descricao">
            Informe uma alergia para adicioná-la ao seu perfil de saúde.
        </p>

        <?php if ($erro !== ''): ?>

            <div class="mensagem">
                <?= e($erro) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action=""
            class="formulario"
        >

            <div class="campo campo-completo">

                <label for="alergia">
                    Nome da alergia *
                </label>

                <input
                    type="text"
                    id="alergia"
                    name="alergia"
                    placeholder="Ex.: Penicilina, camarão, poeira..."
                    required
                >

            </div>

            <div class="campo">

                <label for="tipo">
                    Tipo de alergia
                </label>

                <select
                    id="tipo"
                    name="tipo"
                >

                    <option value="">
                        Selecione
                    </option>

                    <option value="Medicamento">
                        Medicamento
                    </option>

                    <option value="Alimento">
                        Alimento
                    </option>

                    <option value="Vacina">
                        Vacina
                    </option>

                    <option value="Ambiental">
                        Ambiental
                    </option>

                    <option value="Contato">
                        Contato
                    </option>

                    <option value="Outra">
                        Outra
                    </option>

                </select>

            </div>

            <div class="campo">

                <label for="gravidade">
                    Gravidade
                </label>

                <select
                    id="gravidade"
                    name="gravidade"
                >

                    <option value="">
                        Selecione
                    </option>

                    <option value="Leve">
                        Leve
                    </option>

                    <option value="Moderada">
                        Moderada
                    </option>

                    <option value="Grave">
                        Grave
                    </option>

                </select>

            </div>

            <div class="campo campo-completo">

                <label for="reacao">
                    Reação apresentada
                </label>

                <textarea
                    id="reacao"
                    name="reacao"
                    placeholder="Descreva os sintomas ou a reação apresentada..."
                ></textarea>

            </div>

            <div class="campo campo-completo">

                <label for="observacao">
                    Observações
                </label>

                <textarea
                    id="observacao"
                    name="observacao"
                    placeholder="Adicione outras informações importantes..."
                ></textarea>

            </div>

            <div class="botoes">

                <a
                    href="alergias.php"
                    class="botao botao-cancelar"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="botao botao-salvar"
                >
                    Cadastrar alergia
                </button>

            </div>

        </form>

        <div class="informacao">

            <strong>Importante:</strong>

            mantenha suas informações de alergia atualizadas no
            seu perfil de saúde.

        </div>

    </section>

</main>

</body>

</html>
```
