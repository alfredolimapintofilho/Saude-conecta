<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/models/Vacina.php';

$usuario = $_SESSION['usuario'];

$usuarioId = null;
$nomeUsuario = 'Usuário';

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
}

if (!$usuarioId) {
    die('Erro: não foi possível identificar o usuário conectado.');
}

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Converte DD/MM/AAAA para AAAA-MM-DD
|--------------------------------------------------------------------------
*/

function converterDataParaBanco(string $data): ?string
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    $partes = explode('/', $data);

    if (count($partes) !== 3) {
        return null;
    }

    $dia = (int) $partes[0];
    $mes = (int) $partes[1];
    $ano = (int) $partes[2];

    if (
        $dia < 1 ||
        $mes < 1 ||
        $mes > 12 ||
        $ano < 1900
    ) {
        return null;
    }

    if (!checkdate($mes, $dia, $ano)) {
        return null;
    }

    return sprintf(
        '%04d-%02d-%02d',
        $ano,
        $mes,
        $dia
    );
}


$grupo =
    'VACINAS | SOROS | DILUENTES ADMINISTRADOS';

$vacina = '';
$dataAplicacao = '';
$dose = '';
$lote = '';
$estrategia = '';
$cnes = '';
$estabelecimento = '';
$municipio = '';
$uf = '';
$observacao = '';

$erro = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $grupo = trim(
        $_POST['grupo'] ?? ''
    );

    $vacina = trim(
        $_POST['vacina'] ?? ''
    );

    $dataAplicacao = trim(
        $_POST['data_aplicacao'] ?? ''
    );

    $dose = trim(
        $_POST['dose'] ?? ''
    );

    $lote = trim(
        $_POST['lote'] ?? ''
    );

    $estrategia = trim(
        $_POST['estrategia'] ?? ''
    );

    $cnes = trim(
        $_POST['cnes'] ?? ''
    );

    $estabelecimento = trim(
        $_POST['estabelecimento'] ?? ''
    );

    $municipio = trim(
        $_POST['municipio'] ?? ''
    );

    $uf = strtoupper(
        trim(
            $_POST['uf'] ?? ''
        )
    );

    $observacao = trim(
        $_POST['observacao'] ?? ''
    );


    if ($vacina === '') {

        $erro =
            'Informe o nome da vacina ou profilaxia.';

    } elseif ($dataAplicacao === '') {

        $erro =
            'Informe a data da aplicação.';

    } else {

        $dataBanco =
            converterDataParaBanco(
                $dataAplicacao
            );

        if (!$dataBanco) {

            $erro =
                'Informe uma data válida no formato DD/MM/AAAA.';

        } elseif (
            $uf !== '' &&
            strlen($uf) !== 2
        ) {

            $erro =
                'A UF deve possuir exatamente 2 letras.';

        } else {

            try {

                $vacinaModel =
                    new Vacina($conn);

                $dados = [

                    'usuario_id' =>
                        $usuarioId,

                    'grupo' =>
                        $grupo,

                    'vacina' =>
                        $vacina,

                    'data_aplicacao' =>
                        $dataBanco,

                    'dose' =>
                        $dose ?: null,

                    'lote' =>
                        $lote ?: null,

                    'estrategia' =>
                        $estrategia ?: null,

                    'cnes' =>
                        $cnes ?: null,

                    'estabelecimento' =>
                        $estabelecimento ?: null,

                    'municipio' =>
                        $municipio ?: null,

                    'uf' =>
                        $uf ?: null,

                    'observacao' =>
                        $observacao ?: null

                ];

                $vacinaModel->cadastrar(
                    $dados
                );

                header(
                    'Location: cartao_vacinacao.php'
                );

                exit;

            } catch (Throwable $e) {

                $erro =
                    'Erro ao cadastrar a vacinação: '
                    . $e->getMessage();
            }
        }
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
        Cadastrar Vacinação - Saúde-Conecta
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
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
        }

        .topo-conteudo {
            max-width: 1000px;
            margin: 0 auto;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;
        }

        .topo h1 {
            margin: 0;
            font-size: 25px;
        }

        .topo p {
            margin: 6px 0 0;
            font-size: 14px;
            opacity: .9;
        }

        .acoes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-voltar {
            background: white;
            color: #0f766e;
        }

        .btn-sair {
            background: #dc2626;
            color: white;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .formulario {
            background: white;
            border-radius: 16px;
            padding: 30px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, .07);
        }

        .formulario h2 {
            margin: 0 0 8px;
            color: #0f766e;
        }

        .descricao {
            color: #64748b;
            margin-bottom: 25px;
        }

        .erro {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .campo {
            margin-bottom: 18px;
        }

        .linha {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            color: #334155;
        }

        .obrigatorio {
            color: #dc2626;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;

            border: 1px solid #cbd5e1;
            border-radius: 8px;

            font-size: 15px;
            background: white;
            color: #1e293b;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #0f766e;

            box-shadow:
                0 0 0 3px
                rgba(15, 118, 110, .12);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .ajuda {
            margin-top: 5px;
            color: #64748b;
            font-size: 12px;
        }

        .botoes {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-salvar {
            border: none;
            background: #0f766e;
            color: white;

            padding: 13px 20px;
            border-radius: 8px;

            font-size: 15px;
            font-weight: bold;

            cursor: pointer;
        }

        .btn-cancelar {
            background: #64748b;
            color: white;

            padding: 13px 20px;
            border-radius: 8px;

            text-decoration: none;
            font-weight: bold;
        }

        .informacao {
            margin-top: 20px;

            background: #eff6ff;
            border: 1px solid #bfdbfe;

            color: #1e40af;

            padding: 15px;
            border-radius: 10px;

            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 700px) {

            .topo-conteudo {
                flex-direction: column;
                align-items: flex-start;
            }

            .linha {
                grid-template-columns: 1fr;
            }

            .formulario {
                padding: 20px;
            }
        }

    </style>

</head>

<body>

<header class="topo">

    <div class="topo-conteudo">

        <div>

            <h1>
                💉 Cadastrar Vacinação
            </h1>

            <p>
                Saúde-Conecta
            </p>

        </div>

        <div class="acoes">

            <a
                href="cartao_vacinacao.php"
                class="btn btn-voltar"
            >
                ← Voltar
            </a>

            <a
                href="../logout.php"
                class="btn btn-sair"
            >
                Sair
            </a>

        </div>

    </div>

</header>

<main class="container">

<section class="formulario">

    <h2>
        Cadastrar vacinação
    </h2>

    <p class="descricao">

        Preencha os dados da vacinação.
        Os campos marcados com
        <span class="obrigatorio">*</span>
        são obrigatórios.

    </p>


    <?php if ($erro !== ''): ?>

        <div class="erro">
            <?= escapar($erro) ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="campo">

            <label for="grupo">
                Grupo da vacinação
            </label>

            <select
                id="grupo"
                name="grupo"
            >

                <option
                    value="COVID-19"
                    <?= $grupo === 'COVID-19'
                        ? 'selected'
                        : '' ?>
                >
                    COVID-19
                </option>

                <option
                    value="VACINAS | SOROS | DILUENTES ADMINISTRADOS"
                    <?= $grupo === 'VACINAS | SOROS | DILUENTES ADMINISTRADOS'
                        ? 'selected'
                        : '' ?>
                >
                    VACINAS | SOROS | DILUENTES ADMINISTRADOS
                </option>

            </select>

        </div>


        <div class="campo">

            <label for="vacina">

                Vacina / Profilaxia

                <span class="obrigatorio">*</span>

            </label>

            <input
                type="text"
                id="vacina"
                name="vacina"
                value="<?= escapar($vacina) ?>"
                placeholder="Ex.: Influenza, HPV, Febre Amarela..."
                required
            >

        </div>


        <div class="linha">

            <div class="campo">

                <label for="data_aplicacao">

                    Data da aplicação

                    <span class="obrigatorio">*</span>

                </label>

                <input
                    type="text"
                    id="data_aplicacao"
                    name="data_aplicacao"
                    value="<?= escapar($dataAplicacao) ?>"
                    placeholder="DD/MM/AAAA"
                    maxlength="10"
                    inputmode="numeric"
                    autocomplete="off"
                    required
                >

                <div class="ajuda">
                    Digite a data no formato DD/MM/AAAA.
                </div>

            </div>


            <div class="campo">

                <label for="dose">
                    Dose
                </label>

                <input
                    type="text"
                    id="dose"
                    name="dose"
                    value="<?= escapar($dose) ?>"
                    placeholder="Ex.: 1ª dose, 2ª dose, reforço"
                >

            </div>

        </div>


        <div class="linha">

            <div class="campo">

                <label for="lote">
                    Lote
                </label>

                <input
                    type="text"
                    id="lote"
                    name="lote"
                    value="<?= escapar($lote) ?>"
                    placeholder="Número do lote"
                >

            </div>


            <div class="campo">

                <label for="estrategia">
                    Estratégia
                </label>

                <input
                    type="text"
                    id="estrategia"
                    name="estrategia"
                    value="<?= escapar($estrategia) ?>"
                    placeholder="Ex.: Rotina, Campanha"
                >

            </div>

        </div>


        <div class="campo">

            <label for="cnes">
                CNES
            </label>

            <input
                type="text"
                id="cnes"
                name="cnes"
                value="<?= escapar($cnes) ?>"
                placeholder="Código CNES do estabelecimento"
            >

            <div class="ajuda">
                Código Nacional de Estabelecimento de Saúde,
                quando disponível.
            </div>

        </div>


        <div class="campo">

            <label for="estabelecimento">
                Estabelecimento de Saúde
            </label>

            <input
                type="text"
                id="estabelecimento"
                name="estabelecimento"
                value="<?= escapar($estabelecimento) ?>"
                placeholder="Nome da unidade de saúde"
            >

        </div>


        <div class="linha">

            <div class="campo">

                <label for="municipio">
                    Município
                </label>

                <input
                    type="text"
                    id="municipio"
                    name="municipio"
                    value="<?= escapar($municipio) ?>"
                    placeholder="Ex.: Patos"
                >

            </div>


            <div class="campo">

                <label for="uf">
                    UF
                </label>

                <input
                    type="text"
                    id="uf"
                    name="uf"
                    value="<?= escapar($uf) ?>"
                    maxlength="2"
                    placeholder="Ex.: PB"
                >

            </div>

        </div>


        <div class="campo">

            <label for="observacao">
                Observação
            </label>

            <textarea
                id="observacao"
                name="observacao"
                placeholder="Informações adicionais sobre a vacinação..."
            ><?= escapar($observacao) ?></textarea>

        </div>


        <div class="botoes">

            <button
                type="submit"
                class="btn-salvar"
            >
                💾 Salvar vacinação
            </button>

            <a
                href="cartao_vacinacao.php"
                class="btn-cancelar"
            >
                Cancelar
            </a>

        </div>

    </form>


    <div class="informacao">

        <strong>
            Atenção:
        </strong>

        Os dados cadastrados manualmente no
        Saúde-Conecta não representam, por si só,
        uma validação oficial do Ministério da Saúde.

    </div>

</section>

</main>


<script>

/*
|--------------------------------------------------------------------------
| Máscara de data DD/MM/AAAA
|--------------------------------------------------------------------------
*/

const campoData =
    document.getElementById('data_aplicacao');

campoData.addEventListener(
    'input',
    function () {

        let valor =
            this.value.replace(/\D/g, '');

        if (valor.length > 8) {
            valor = valor.substring(0, 8);
        }

        if (valor.length >= 5) {

            valor =
                valor.substring(0, 2)
                + '/'
                + valor.substring(2, 4)
                + '/'
                + valor.substring(4);

        } else if (valor.length >= 3) {

            valor =
                valor.substring(0, 2)
                + '/'
                + valor.substring(2);

        }

        this.value = valor;

    }
);

</script>

</body>

</html>