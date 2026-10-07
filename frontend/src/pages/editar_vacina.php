<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/models/Vacina.php';

try {
    $pdo = Database::getConnection();
    $vacinaModel = new Vacina($pdo);
} catch (PDOException $e) {
    die(
        'Erro ao conectar ao banco de dados: ' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    );
}

/*
|--------------------------------------------------------------------------
| IDENTIFICAR USUÁRIO
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| ID DA VACINA
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: cartao_vacinacao.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| FUNÇÕES
|--------------------------------------------------------------------------
*/

function escapar($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function converterDataParaBrasileiro($data)
{
    if (empty($data)) {
        return '';
    }

    $partes = explode('-', $data);

    if (count($partes) !== 3) {
        return $data;
    }

    return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
}

function converterDataParaMysql($data)
{
    $data = trim($data);

    if ($data === '') {
        return false;
    }

    if (preg_match(
        '/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/',
        $data,
        $resultado
    )) {

        $dia = (int) $resultado[1];
        $mes = (int) $resultado[2];
        $ano = (int) $resultado[3];

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

    return false;
}

/*
|--------------------------------------------------------------------------
| BUSCAR VACINA
|--------------------------------------------------------------------------
*/

try {

    $vacina = $vacinaModel->buscarPorId(
        $id,
        $usuarioId
    );

    if (!$vacina) {
        header('Location: cartao_vacinacao.php');
        exit;
    }

} catch (Throwable $e) {

    die(
        'Erro ao buscar vacinação: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}

$erro = '';
$sucesso = '';

/*
|--------------------------------------------------------------------------
| SALVAR ALTERAÇÕES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $grupo = trim($_POST['grupo'] ?? '');

    $nomeVacina = trim($_POST['vacina'] ?? '');

    $dataDigitada = trim(
        $_POST['data_aplicacao'] ?? ''
    );

    $dose = trim($_POST['dose'] ?? '');

    $lote = trim($_POST['lote'] ?? '');

    $estrategia = trim(
        $_POST['estrategia'] ?? ''
    );

    $cnes = trim($_POST['cnes'] ?? '');

    $estabelecimento = trim(
        $_POST['estabelecimento'] ?? ''
    );

    $municipio = trim(
        $_POST['municipio'] ?? ''
    );

    $uf = strtoupper(
        trim($_POST['uf'] ?? '')
    );

    $observacao = trim(
        $_POST['observacao'] ?? ''
    );

    if ($nomeVacina === '') {

        $erro = 'Informe o nome da vacina.';

    } elseif ($dataDigitada === '') {

        $erro = 'Informe a data de aplicação.';

    } else {

        $dataMysql = converterDataParaMysql(
            $dataDigitada
        );

        if ($dataMysql === false) {

            $erro =
                'Data inválida. Digite no formato DD/MM/AAAA.';

        } else {

            try {

                $dados = [
                    'grupo' => $grupo !== ''
                        ? $grupo
                        : 'VACINAS | SOROS | DILUENTES ADMINISTRADOS',

                    'vacina' => $nomeVacina,

                    'data_aplicacao' => $dataMysql,

                    'dose' => $dose !== ''
                        ? $dose
                        : null,

                    'lote' => $lote !== ''
                        ? $lote
                        : null,

                    'estrategia' => $estrategia !== ''
                        ? $estrategia
                        : null,

                    'cnes' => $cnes !== ''
                        ? $cnes
                        : null,

                    'estabelecimento' => $estabelecimento !== ''
                        ? $estabelecimento
                        : null,

                    'municipio' => $municipio !== ''
                        ? $municipio
                        : null,

                    'uf' => $uf !== ''
                        ? $uf
                        : null,

                    'observacao' => $observacao !== ''
                        ? $observacao
                        : null
                ];

                $resultado = $vacinaModel->atualizar(
                    $id,
                    $usuarioId,
                    $dados
                );

                if ($resultado) {

                    $sucesso =
                        'Vacinação atualizada com sucesso.';

                    $vacina['grupo'] =
                        $dados['grupo'];

                    $vacina['vacina'] =
                        $dados['vacina'];

                    $vacina['data_aplicacao'] =
                        $dados['data_aplicacao'];

                    $vacina['dose'] =
                        $dados['dose'];

                    $vacina['lote'] =
                        $dados['lote'];

                    $vacina['estrategia'] =
                        $dados['estrategia'];

                    $vacina['cnes'] =
                        $dados['cnes'];

                    $vacina['estabelecimento'] =
                        $dados['estabelecimento'];

                    $vacina['municipio'] =
                        $dados['municipio'];

                    $vacina['uf'] =
                        $dados['uf'];

                    $vacina['observacao'] =
                        $dados['observacao'];

                } else {

                    $erro =
                        'Não foi possível atualizar a vacinação.';
                }

            } catch (Throwable $e) {

                $erro =
                    'Erro ao atualizar vacinação: ' .
                    $e->getMessage();
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

    <title>Editar Vacinação - Saúde-Conecta</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
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
            text-decoration: none;
            border: none;
            border-radius: 8px;
            padding: 11px 17px;
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
            max-width: 1000px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .titulo {
            margin-top: 0;
            margin-bottom: 8px;
            color: #0b7fab;
            font-size: 25px;
        }

        .descricao {
            color: #607d8b;
            margin-top: 0;
            margin-bottom: 25px;
        }

        .mensagem {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .erro {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .sucesso {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
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
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            font-size: 15px;
            font-family: Arial, Helvetica, sans-serif;
            background: white;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #0b7fab;
            box-shadow: 0 0 0 2px rgba(11, 127, 171, 0.12);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .ajuda {
            margin-top: 6px;
            font-size: 12px;
            color: #78909c;
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
            background: #0b7fab;
            color: white;
        }

        .informacao {
            margin-top: 25px;
            padding: 15px;
            border-radius: 8px;
            background: #e3f2fd;
            color: #1565c0;
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

        <h1>Saúde-Conecta</h1>

        <p>Editar cartão de vacinação</p>

    </div>

    <div class="acoes-topo">

        <a
            href="cartao_vacinacao.php"
            class="botao botao-voltar"
        >
            ← Voltar para vacinação
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

        <h2 class="titulo">
            Editar vacinação
        </h2>

        <p class="descricao">
            Altere as informações da vacinação cadastrada no seu cartão.
        </p>

        <?php if ($erro !== ''): ?>

            <div class="mensagem erro">
                <?= escapar($erro) ?>
            </div>

        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>

            <div class="mensagem sucesso">
                <?= escapar($sucesso) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action=""
            class="formulario"
        >

            <div class="campo">

                <label for="grupo">
                    Grupo
                </label>

                <select
                    id="grupo"
                    name="grupo"
                >

                    <option
                        value="VACINAS | SOROS | DILUENTES ADMINISTRADOS"
                        <?php
                        if (
                            ($vacina['grupo'] ?? '') ===
                            'VACINAS | SOROS | DILUENTES ADMINISTRADOS'
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        VACINAS | SOROS | DILUENTES ADMINISTRADOS
                    </option>

                    <option
                        value="COVID-19"
                        <?php
                        if (
                            ($vacina['grupo'] ?? '') === 'COVID-19'
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        COVID-19
                    </option>

                </select>

            </div>

            <div class="campo">

                <label for="vacina">
                    Nome da vacina *
                </label>

                <input
                    type="text"
                    id="vacina"
                    name="vacina"
                    value="<?= escapar($vacina['vacina'] ?? '') ?>"
                    placeholder="Ex.: VACINA HEPATITE B"
                    required
                >

            </div>

            <div class="campo">

                <label for="data_aplicacao">
                    Data de aplicação *
                </label>

                <input
                    type="text"
                    id="data_aplicacao"
                    name="data_aplicacao"
                    value="<?= escapar(
                        converterDataParaBrasileiro(
                            $vacina['data_aplicacao'] ?? ''
                        )
                    ) ?>"
                    placeholder="DD/MM/AAAA"
                    maxlength="10"
                    autocomplete="off"
                    required
                >

                <span class="ajuda">
                    Digite a data no formato DD/MM/AAAA.
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
                    value="<?= escapar($vacina['dose'] ?? '') ?>"
                    placeholder="Ex.: 1ª Dose, Reforço, Única"
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
                    value="<?= escapar($vacina['lote'] ?? '') ?>"
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
                    value="<?= escapar($vacina['estrategia'] ?? '') ?>"
                    placeholder="Ex.: Rotina"
                >

            </div>

            <div class="campo">

                <label for="cnes">
                    CNES
                </label>

                <input
                    type="text"
                    id="cnes"
                    name="cnes"
                    value="<?= escapar($vacina['cnes'] ?? '') ?>"
                    placeholder="Número do CNES"
                >

            </div>

            <div class="campo">

                <label for="estabelecimento">
                    Estabelecimento
                </label>

                <input
                    type="text"
                    id="estabelecimento"
                    name="estabelecimento"
                    value="<?= escapar(
                        $vacina['estabelecimento'] ?? ''
                    ) ?>"
                    placeholder="Nome da unidade de saúde"
                >

            </div>

            <div class="campo">

                <label for="municipio">
                    Município
                </label>

                <input
                    type="text"
                    id="municipio"
                    name="municipio"
                    value="<?= escapar(
                        $vacina['municipio'] ?? ''
                    ) ?>"
                    placeholder="Ex.: PATOS"
                >

            </div>

            <div class="campo">

                <label for="uf">
                    UF
                </label>

                <select
                    id="uf"
                    name="uf"
                >

                    <option value="">
                        Selecione
                    </option>

                    <?php

                    $estados = [
                        'AC' => 'Acre',
                        'AL' => 'Alagoas',
                        'AP' => 'Amapá',
                        'AM' => 'Amazonas',
                        'BA' => 'Bahia',
                        'CE' => 'Ceará',
                        'DF' => 'Distrito Federal',
                        'ES' => 'Espírito Santo',
                        'GO' => 'Goiás',
                        'MA' => 'Maranhão',
                        'MT' => 'Mato Grosso',
                        'MS' => 'Mato Grosso do Sul',
                        'MG' => 'Minas Gerais',
                        'PA' => 'Pará',
                        'PB' => 'Paraíba',
                        'PR' => 'Paraná',
                        'PE' => 'Pernambuco',
                        'PI' => 'Piauí',
                        'RJ' => 'Rio de Janeiro',
                        'RN' => 'Rio Grande do Norte',
                        'RS' => 'Rio Grande do Sul',
                        'RO' => 'Rondônia',
                        'RR' => 'Roraima',
                        'SC' => 'Santa Catarina',
                        'SP' => 'São Paulo',
                        'SE' => 'Sergipe',
                        'TO' => 'Tocantins'
                    ];

                    foreach ($estados as $sigla => $nome) {

                        $selecionado = '';

                        if (($vacina['uf'] ?? '') === $sigla) {
                            $selecionado = 'selected';
                        }

                        echo '<option value="' .
                            escapar($sigla) .
                            '" ' .
                            $selecionado .
                            '>' .
                            escapar($sigla) .
                            ' - ' .
                            escapar($nome) .
                            '</option>';
                    }

                    ?>

                </select>

            </div>

            <div class="campo campo-completo">

                <label for="observacao">
                    Observação
                </label>

                <textarea
                    id="observacao"
                    name="observacao"
                    placeholder="Adicione outras informações..."
                ><?= escapar($vacina['observacao'] ?? '') ?></textarea>

            </div>

            <div class="botoes">

                <a
                    href="cartao_vacinacao.php"
                    class="botao botao-cancelar"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="botao botao-salvar"
                >
                    Salvar alterações
                </button>

            </div>

        </form>

        <div class="informacao">

            <strong>Importante:</strong>

            a data deve ser digitada manualmente no formato
            <strong>DD/MM/AAAA</strong>.

            Exemplo:
            <strong>31/07/2026</strong>.

        </div>

    </section>

</main>

<script>

const campoData = document.getElementById(
    'data_aplicacao'
);

if (campoData) {

    campoData.addEventListener(
        'input',
        function () {

            let valor = this.value.replace(
                /[^0-9]/g,
                ''
            );

            if (valor.length > 8) {
                valor = valor.substring(0, 8);
            }

            if (valor.length > 4) {

                valor =
                    valor.substring(0, 2) +
                    '/' +
                    valor.substring(2, 4) +
                    '/' +
                    valor.substring(4);

            } else if (valor.length > 2) {

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
```
