<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../pages/login.php');
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
| USUÁRIO LOGADO
|--------------------------------------------------------------------------
*/

$usuarioSessao = $_SESSION['usuario'];

$usuarioId = 0;

if (is_array($usuarioSessao)) {
    $usuarioId = (int) (
        $usuarioSessao['id']
        ?? $usuarioSessao['usuario_id']
        ?? 0
    );
} else {
    $usuarioId = (int) $usuarioSessao;
}

if ($usuarioId <= 0) {
    session_destroy();
    header('Location: ../pages/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| BUSCAR DADOS DO USUÁRIO
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            cpf,
            nome_completo,
            email,
            telefone,
            data_nascimento,
            cartao_sus,
            peso,
            altura,
            tipo_sanguineo,
            genero,
            sexo,
            gestante,
            semanas_gestacao,
            data_ultima_menstruacao,
            data_prevista_parto,
            pre_natal,
            gestacao_risco,
            observacoes_gestacao
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$usuarioId]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        session_destroy();
        header('Location: ../pages/login.php');
        exit;
    }

} catch (PDOException $e) {

    die(
        'Erro ao carregar os dados do perfil: ' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    );
}

/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$mensagem = '';
$erro = '';

$nomeCompleto = $usuario['nome_completo'] ?? '';
$email = $usuario['email'] ?? '';
$telefone = $usuario['telefone'] ?? '';
$cartaoSus = $usuario['cartao_sus'] ?? '';

$peso = $usuario['peso'] ?? '';
$altura = $usuario['altura'] ?? '';
$tipoSanguineo = $usuario['tipo_sanguineo'] ?? '';

$genero = $usuario['genero'] ?? '';
$sexo = $usuario['sexo'] ?? '';

$gestante = (int) ($usuario['gestante'] ?? 0);
$semanasGestacao = $usuario['semanas_gestacao'] ?? '';

$dataUltimaMenstruacao = $usuario['data_ultima_menstruacao'] ?? '';
$dataPrevistaParto = $usuario['data_prevista_parto'] ?? '';

$preNatal = $usuario['pre_natal'] ?? '';
$gestacaoRisco = (int) ($usuario['gestacao_risco'] ?? 0);
$observacoesGestacao = $usuario['observacoes_gestacao'] ?? '';

/*
|--------------------------------------------------------------------------
| FUNÇÃO DATA
|--------------------------------------------------------------------------
*/

function dataParaBrasileiro($data)
{
    if (empty($data)) {
        return '';
    }

    $partes = explode('-', $data);

    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }

    return $data;
}

function dataParaMysql($data)
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
        return false;
    }

    $partes = explode('/', $data);

    $dia = (int) $partes[0];
    $mes = (int) $partes[1];
    $ano = (int) $partes[2];

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
| CONVERTER DATAS PARA EXIBIÇÃO
|--------------------------------------------------------------------------
*/

$dataNascimentoExibicao = dataParaBrasileiro(
    $usuario['data_nascimento'] ?? ''
);

$dataUltimaMenstruacaoExibicao = dataParaBrasileiro(
    $dataUltimaMenstruacao
);

$dataPrevistaPartoExibicao = dataParaBrasileiro(
    $dataPrevistaParto
);

/*
|--------------------------------------------------------------------------
| ATUALIZAR PERFIL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $novoTelefone = trim($_POST['telefone'] ?? '');
    $novoCartaoSus = trim($_POST['cartao_sus'] ?? '');

    $novoPeso = trim($_POST['peso'] ?? '');
    $novaAltura = trim($_POST['altura'] ?? '');
    $novoTipoSanguineo = trim($_POST['tipo_sanguineo'] ?? '');

    $novoGenero = trim($_POST['genero'] ?? '');
    $novoSexo = trim($_POST['sexo'] ?? '');

    $novaGestante = isset($_POST['gestante']) ? 1 : 0;

    $novasSemanasGestacao = trim(
        $_POST['semanas_gestacao'] ?? ''
    );

    $novaDataUltimaMenstruacao = trim(
        $_POST['data_ultima_menstruacao'] ?? ''
    );

    $novaDataPrevistaParto = trim(
        $_POST['data_prevista_parto'] ?? ''
    );

    $novoPreNatal = trim(
        $_POST['pre_natal'] ?? ''
    );

    $novaGestacaoRisco = isset($_POST['gestacao_risco']) ? 1 : 0;

    $novasObservacoesGestacao = trim(
        $_POST['observacoes_gestacao'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDAR DATAS
    |--------------------------------------------------------------------------
    */

    $dataUltimaMenstruacaoMysql = dataParaMysql(
        $novaDataUltimaMenstruacao
    );

    if ($dataUltimaMenstruacaoMysql === false) {

        $erro = 'A data da última menstruação deve estar no formato DD/MM/AAAA.';

    } else {

        $dataPrevistaPartoMysql = dataParaMysql(
            $novaDataPrevistaParto
        );

        if ($dataPrevistaPartoMysql === false) {

            $erro = 'A data prevista para o parto deve estar no formato DD/MM/AAAA.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | VALIDAR PESO
            |--------------------------------------------------------------------------
            */

            $pesoBanco = null;

            if ($novoPeso !== '') {

                if (!is_numeric($novoPeso) || (float) $novoPeso <= 0) {

                    $erro = 'Informe um peso válido.';

                } else {

                    $pesoBanco = (float) $novoPeso;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR ALTURA
            |--------------------------------------------------------------------------
            */

            $alturaBanco = null;

            if ($novaAltura !== '' && $erro === '') {

                if (!is_numeric($novaAltura) || (float) $novaAltura <= 0) {

                    $erro = 'Informe uma altura válida.';

                } else {

                    $alturaBanco = (float) $novaAltura;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | ATUALIZAR BANCO
            |--------------------------------------------------------------------------
            */

            if ($erro === '') {

                try {

                    $stmt = $pdo->prepare("
                        UPDATE usuarios
                        SET
                            telefone = ?,
                            cartao_sus = ?,
                            peso = ?,
                            altura = ?,
                            tipo_sanguineo = ?,
                            genero = ?,
                            sexo = ?,
                            gestante = ?,
                            semanas_gestacao = ?,
                            data_ultima_menstruacao = ?,
                            data_prevista_parto = ?,
                            pre_natal = ?,
                            gestacao_risco = ?,
                            observacoes_gestacao = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([

                        $novoTelefone,

                        $novoCartaoSus !== ''
                            ? $novoCartaoSus
                            : null,

                        $pesoBanco,

                        $alturaBanco,

                        $novoTipoSanguineo !== ''
                            ? $novoTipoSanguineo
                            : null,

                        $novoGenero !== ''
                            ? $novoGenero
                            : null,

                        $novoSexo !== ''
                            ? $novoSexo
                            : null,

                        $novaGestante,

                        $novasSemanasGestacao !== ''
                            ? (int) $novasSemanasGestacao
                            : null,

                        $dataUltimaMenstruacaoMysql,

                        $dataPrevistaPartoMysql,

                        $novoPreNatal !== ''
                            ? $novoPreNatal
                            : null,

                        $novaGestacaoRisco,

                        $novasObservacoesGestacao !== ''
                            ? $novasObservacoesGestacao
                            : null,

                        $usuarioId
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | ATUALIZAR SESSÃO
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['usuario']['nome_completo'] =
                        $nomeCompleto;

                    $_SESSION['usuario']['email'] =
                        $email;

                    $_SESSION['usuario']['telefone'] =
                        $novoTelefone;

                    $_SESSION['usuario']['cartao_sus'] =
                        $novoCartaoSus;

                    $_SESSION['usuario']['peso'] =
                        $pesoBanco;

                    $_SESSION['usuario']['altura'] =
                        $alturaBanco;

                    $_SESSION['usuario']['tipo_sanguineo'] =
                        $novoTipoSanguineo;

                    $_SESSION['usuario']['genero'] =
                        $novoGenero;

                    $_SESSION['usuario']['sexo'] =
                        $novoSexo;

                    $_SESSION['usuario']['gestante'] =
                        $novaGestante;

                    /*
                    |--------------------------------------------------------------------------
                    | RECARREGAR USUÁRIO
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT
                            id,
                            cpf,
                            nome_completo,
                            email,
                            telefone,
                            data_nascimento,
                            cartao_sus,
                            peso,
                            altura,
                            tipo_sanguineo,
                            genero,
                            sexo,
                            gestante,
                            semanas_gestacao,
                            data_ultima_menstruacao,
                            data_prevista_parto,
                            pre_natal,
                            gestacao_risco,
                            observacoes_gestacao
                        FROM usuarios
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([$usuarioId]);

                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                    /*
                    |--------------------------------------------------------------------------
                    | ATUALIZAR VARIÁVEIS
                    |--------------------------------------------------------------------------
                    */

                    $nomeCompleto =
                        $usuario['nome_completo'] ?? '';

                    $email =
                        $usuario['email'] ?? '';

                    $telefone =
                        $usuario['telefone'] ?? '';

                    $cartaoSus =
                        $usuario['cartao_sus'] ?? '';

                    $peso =
                        $usuario['peso'] ?? '';

                    $altura =
                        $usuario['altura'] ?? '';

                    $tipoSanguineo =
                        $usuario['tipo_sanguineo'] ?? '';

                    $genero =
                        $usuario['genero'] ?? '';

                    $sexo =
                        $usuario['sexo'] ?? '';

                    $gestante =
                        (int) ($usuario['gestante'] ?? 0);

                    $semanasGestacao =
                        $usuario['semanas_gestacao'] ?? '';

                    $dataUltimaMenstruacao =
                        $usuario['data_ultima_menstruacao'] ?? '';

                    $dataPrevistaParto =
                        $usuario['data_prevista_parto'] ?? '';

                    $preNatal =
                        $usuario['pre_natal'] ?? '';

                    $gestacaoRisco =
                        (int) ($usuario['gestacao_risco'] ?? 0);

                    $observacoesGestacao =
                        $usuario['observacoes_gestacao'] ?? '';

                    $dataNascimentoExibicao =
                        dataParaBrasileiro(
                            $usuario['data_nascimento'] ?? ''
                        );

                    $dataUltimaMenstruacaoExibicao =
                        dataParaBrasileiro(
                            $dataUltimaMenstruacao
                        );

                    $dataPrevistaPartoExibicao =
                        dataParaBrasileiro(
                            $dataPrevistaParto
                        );

                    $mensagem =
                        'Perfil atualizado com sucesso!';

                } catch (PDOException $e) {

                    $erro =
                        'Erro ao atualizar perfil: ' .
                        $e->getMessage();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| IMC
|--------------------------------------------------------------------------
*/

$imc = null;
$classificacaoImc = '';

if (
    is_numeric($peso) &&
    is_numeric($altura) &&
    (float) $peso > 0 &&
    (float) $altura > 0
) {

    $pesoImc = (float) $peso;
    $alturaImc = (float) $altura;

    /*
     * Aceita altura em metros.
     * Exemplo:
     * 1.75
     */

    if ($alturaImc > 3) {
        $alturaImc = $alturaImc / 100;
    }

    if ($alturaImc > 0) {

        $imc = $pesoImc / ($alturaImc * $alturaImc);

        if ($imc < 18.5) {

            $classificacaoImc = 'Abaixo do peso';

        } elseif ($imc < 25) {

            $classificacaoImc = 'Peso normal';

        } elseif ($imc < 30) {

            $classificacaoImc = 'Sobrepeso';

        } elseif ($imc < 35) {

            $classificacaoImc = 'Obesidade grau I';

        } elseif ($imc < 40) {

            $classificacaoImc = 'Obesidade grau II';

        } else {

            $classificacaoImc = 'Obesidade grau III';
        }
    }
}

/*
|--------------------------------------------------------------------------
| HIDRATAÇÃO ESTIMADA
|--------------------------------------------------------------------------
*/

$hidratacaoMin = null;
$hidratacaoMax = null;

if (is_numeric($peso) && (float) $peso > 0) {

    $pesoNumerico = (float) $peso;

    $hidratacaoMin = $pesoNumerico * 30;
    $hidratacaoMax = $pesoNumerico * 35;
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

    <title>Meu Perfil de Saúde - Saúde-Conecta</title>

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

        header {
            background: #0f766e;
            color: white;
            padding: 18px 25px;
        }

        .header-container {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
        }

        nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 9px 13px;
            border-radius: 8px;
            background: rgba(255,255,255,0.12);
        }

        nav a:hover {
            background: rgba(255,255,255,0.22);
        }

        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px 40px;
        }

        h1 {
            margin-bottom: 5px;
        }

        .subtitulo {
            color: #64748b;
            margin-top: 0;
            margin-bottom: 25px;
        }

        .mensagem {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .erro {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);
        }

        .card h2 {
            margin-top: 0;
            color: #0f766e;
        }

        .campo {
            margin-bottom: 15px;
        }

        .campo label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .campo input,
        .campo select,
        .campo textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
        }

        .campo textarea {
            min-height: 100px;
            resize: vertical;
        }

        .linha {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .botao {
            border: 0;
            background: #0f766e;
            color: white;
            padding: 12px 18px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 15px;
            text-decoration: none;
            display: inline-block;
        }

        .botao:hover {
            background: #115e59;
        }

        .botao-secundario {
            background: #334155;
        }

        .botao-secundario:hover {
            background: #1e293b;
        }

        .botao-azul {
            background: #2563eb;
        }

        .botao-azul:hover {
            background: #1d4ed8;
        }

        .info {
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .info strong {
            display: block;
            margin-bottom: 3px;
        }

        .saude-links {
            display: grid;
            gap: 10px;
        }

        .saude-links a {
            text-decoration: none;
            color: #0f766e;
            background: #f0fdfa;
            padding: 13px;
            border-radius: 9px;
            font-weight: bold;
        }

        .saude-links a:hover {
            background: #ccfbf1;
        }

        .gestacao {
            margin-top: 20px;
            border: 2px solid #f59e0b;
            background: #fffbeb;
        }

        .gestacao h2 {
            color: #b45309;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 12px 0;
        }

        .checkbox input {
            width: auto;
        }

        .resultado-imc {
            font-size: 20px;
            font-weight: bold;
            color: #0f766e;
        }

        .oculto {
            display: none;
        }

        @media (max-width: 700px) {

            .header-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .linha {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<header>

    <div class="header-container">

        <div class="logo">
            Saúde-Conecta
        </div>

        <nav>

            <a href="perfil_saude.php">
                Meu Perfil
            </a>

            <a href="../pages/pets.php">
                Meus Pets
            </a>

            <a href="mapa.php">
                Mapa
            </a>

            <a href="logout.php">
                Sair
            </a>

        </nav>

    </div>

</header>

<main>

    <h1>
        Meu Perfil de Saúde
    </h1>

    <p class="subtitulo">
        Olá, <?= htmlspecialchars($nomeCompleto, ENT_QUOTES, 'UTF-8') ?>.
        Aqui você pode consultar e atualizar suas informações de saúde.
    </p>

    <?php if ($mensagem !== ''): ?>

        <div class="mensagem">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <?php if ($erro !== ''): ?>

        <div class="erro">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <div class="grid">

        <!-- DADOS PESSOAIS -->

        <div class="card">

            <h2>
                Dados pessoais
            </h2>

            <div class="info">

                <strong>Nome</strong>

                <?= htmlspecialchars(
                    $nomeCompleto,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

            <div class="info">

                <strong>E-mail</strong>

                <?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

            <div class="info">

                <strong>CPF</strong>

                <?= htmlspecialchars(
                    $usuario['cpf'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

            <div class="info">

                <strong>Data de nascimento</strong>

                <?= htmlspecialchars(
                    $dataNascimentoExibicao,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        </div>

        <!-- INFORMAÇÕES DE SAÚDE -->

        <div class="card">

            <h2>
                Informações de saúde
            </h2>

            <form method="POST">

                <div class="campo">

                    <label for="telefone">
                        Telefone
                    </label>

                    <input
                        type="text"
                        id="telefone"
                        name="telefone"
                        value="<?= htmlspecialchars(
                            $telefone,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="cartao_sus">
                        Cartão SUS
                    </label>

                    <input
                        type="text"
                        id="cartao_sus"
                        name="cartao_sus"
                        value="<?= htmlspecialchars(
                            $cartaoSus,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="linha">

                    <div class="campo">

                        <label for="peso">
                            Peso (kg)
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            id="peso"
                            name="peso"
                            value="<?= htmlspecialchars(
                                $peso,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>

                    <div class="campo">

                        <label for="altura">
                            Altura (m)
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            id="altura"
                            name="altura"
                            value="<?= htmlspecialchars(
                                $altura,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>

                </div>

                <div class="campo">

                    <label for="tipo_sanguineo">
                        Tipo sanguíneo
                    </label>

                    <select
                        id="tipo_sanguineo"
                        name="tipo_sanguineo"
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php
                        $tiposSanguineos = [
                            'A+',
                            'A-',
                            'B+',
                            'B-',
                            'AB+',
                            'AB-',
                            'O+',
                            'O-'
                        ];
                        ?>

                        <?php foreach ($tiposSanguineos as $tipo): ?>

                            <option
                                value="<?= $tipo ?>"
                                <?= $tipoSanguineo === $tipo
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $tipo ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="linha">

                    <div class="campo">

                        <label for="genero">
                            Gênero
                        </label>

                        <input
                            type="text"
                            id="genero"
                            name="genero"
                            value="<?= htmlspecialchars(
                                $genero,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Digite seu gênero"
                        >

                    </div>

                    <div class="campo">

                        <label for="sexo">
                            Sexo
                        </label>

                        <select
                            id="sexo"
                            name="sexo"
                        >

                            <option value="">
                                Selecione
                            </option>

                            <option
                                value="Feminino"
                                <?= $sexo === 'Feminino'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Feminino
                            </option>

                            <option
                                value="Masculino"
                                <?= $sexo === 'Masculino'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Masculino
                            </option>

                            <option
                                value="Outro"
                                <?= $sexo === 'Outro'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Outro
                            </option>

                        </select>

                    </div>

                </div>

                <button
                    type="submit"
                    class="botao"
                >
                    Salvar informações
                </button>

            </form>

        </div>

        <!-- IMC -->

        <div class="card">

            <h2>
                IMC
            </h2>

            <?php if ($imc !== null): ?>

                <div class="resultado-imc">

                    <?= number_format(
                        $imc,
                        2,
                        ',',
                        '.'
                    ) ?>

                </div>

                <p>
                    <?= htmlspecialchars(
                        $classificacaoImc,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

            <?php else: ?>

                <p>
                    Informe seu peso e sua altura para calcular o IMC.
                </p>

            <?php endif; ?>

        </div>

        <!-- HIDRATAÇÃO -->

        <div class="card">

            <h2>
                Hidratação estimada
            </h2>

            <?php if ($hidratacaoMin !== null): ?>

                <p>
                    Estimativa diária:
                </p>

                <strong>

                    <?= number_format(
                        $hidratacaoMin,
                        0,
                        ',',
                        '.'
                    ) ?>

                    a

                    <?= number_format(
                        $hidratacaoMax,
                        0,
                        ',',
                        '.'
                    ) ?>

                    ml por dia

                </strong>

                <p>
                    Essa é apenas uma estimativa baseada no peso.
                    Necessidades individuais podem variar.
                </p>

            <?php else: ?>

                <p>
                    Informe seu peso para calcular uma estimativa.
                </p>

            <?php endif; ?>

        </div>

        <!-- CARTÃO DE SAÚDE -->

        <div class="card">

            <h2>
                Minha saúde
            </h2>

            <div class="saude-links">

                <a href="../pages/saude.php?tipo=vacina">
                    Cartão de vacinação
                </a>

                <a href="../pages/saude.php?tipo=alergia">
                    Minhas alergias
                </a>

                <a href="../pages/saude.php?tipo=alergia_vacina">
                    Alergias a vacinas
                </a>

                <a href="../pages/saude.php?tipo=doenca">
                    Histórico de doenças
                </a>

                <a href="../pages/saude.php?tipo=atestado">
                    Meus atestados
                </a>

            </div>

        </div>

        <!-- PET -->

        <div class="card">

            <h2>
                Meus Pets
            </h2>

            <p>
                Cadastre seus animais e acompanhe as informações
                de saúde deles.
            </p>

            <a
                href="../pages/pets.php"
                class="botao"
            >
                Acessar Meus Pets
            </a>

        </div>

        <!-- MAPA -->

        <div class="card">

            <h2>
                Mapa de saúde
            </h2>

            <p>
                Encontre hospitais, UPAs, maternidades e unidades
                básicas de saúde em Patos.
            </p>

            <a
                href="mapa.php"
                class="botao botao-azul"
            >
                Acessar mapa
            </a>

        </div>

    </div>

    <!-- GESTAÇÃO -->

    <div
        id="areaGestacao"
        class="card gestacao <?= $sexo === 'Feminino'
            ? ''
            : 'oculto' ?>"
    >

        <h2>
            Informações de gestação
        </h2>

        <p>
            Esta área aparece somente quando o sexo informado
            é feminino.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="telefone"
                value="<?= htmlspecialchars(
                    $telefone,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="cartao_sus"
                value="<?= htmlspecialchars(
                    $cartaoSus,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="peso"
                value="<?= htmlspecialchars(
                    $peso,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="altura"
                value="<?= htmlspecialchars(
                    $altura,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="tipo_sanguineo"
                value="<?= htmlspecialchars(
                    $tipoSanguineo,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="genero"
                value="<?= htmlspecialchars(
                    $genero,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="sexo"
                value="Feminino"
            >

            <div class="checkbox">

                <input
                    type="checkbox"
                    id="gestante"
                    name="gestante"
                    value="1"
                    <?= $gestante === 1
                        ? 'checked'
                        : '' ?>
                >

                <label for="gestante">
                    Estou grávida
                </label>

            </div>

            <div class="linha">

                <div class="campo">

                    <label for="semanas_gestacao">
                        Semanas de gestação
                    </label>

                    <input
                        type="number"
                        min="0"
                        max="45"
                        id="semanas_gestacao"
                        name="semanas_gestacao"
                        value="<?= htmlspecialchars(
                            $semanasGestacao,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="pre_natal">
                        Pré-natal
                    </label>

                    <select
                        id="pre_natal"
                        name="pre_natal"
                    >

                        <option value="">
                            Selecione
                        </option>

                        <option
                            value="Sim"
                            <?= $preNatal === 'Sim'
                                ? 'selected'
                                : '' ?>
                        >
                            Sim
                        </option>

                        <option
                            value="Não"
                            <?= $preNatal === 'Não'
                                ? 'selected'
                                : '' ?>
                        >
                            Não
                        </option>

                        <option
                            value="Não informado"
                            <?= $preNatal === 'Não informado'
                                ? 'selected'
                                : '' ?>
                        >
                            Não informado
                        </option>

                    </select>

                </div>

            </div>

            <div class="linha">

                <div class="campo">

                    <label for="data_ultima_menstruacao">
                        Data da última menstruação
                    </label>

                    <input
                        type="text"
                        id="data_ultima_menstruacao"
                        name="data_ultima_menstruacao"
                        placeholder="DD/MM/AAAA"
                        maxlength="10"
                        value="<?= htmlspecialchars(
                            $dataUltimaMenstruacaoExibicao,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="data_prevista_parto">
                        Data prevista para o parto
                    </label>

                    <input
                        type="text"
                        id="data_prevista_parto"
                        name="data_prevista_parto"
                        placeholder="DD/MM/AAAA"
                        maxlength="10"
                        value="<?= htmlspecialchars(
                            $dataPrevistaPartoExibicao,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

            </div>

            <div class="checkbox">

                <input
                    type="checkbox"
                    id="gestacao_risco"
                    name="gestacao_risco"
                    value="1"
                    <?= $gestacaoRisco === 1
                        ? 'checked'
                        : '' ?>
                >

                <label for="gestacao_risco">
                    Gestação de risco
                </label>

            </div>

            <div class="campo">

                <label for="observacoes_gestacao">
                    Observações da gestação
                </label>

                <textarea
                    id="observacoes_gestacao"
                    name="observacoes_gestacao"
                    placeholder="Digite informações importantes sobre a gestação..."
                ><?= htmlspecialchars(
                    $observacoesGestacao,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

            </div>

            <button
                type="submit"
                class="botao"
            >
                Salvar informações da gestação
            </button>

        </form>

    </div>

</main>

<script>

/*
|--------------------------------------------------------------------------
| MÁSCARA DD/MM/AAAA
|--------------------------------------------------------------------------
*/

function aplicarMascaraData(input) {

    input.addEventListener('input', function () {

        let valor = this.value.replace(/\D/g, '');

        if (valor.length > 8) {
            valor = valor.substring(0, 8);
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
    });
}

const campoUltimaMenstruacao =
    document.getElementById(
        'data_ultima_menstruacao'
    );

const campoParto =
    document.getElementById(
        'data_prevista_parto'
    );

if (campoUltimaMenstruacao) {
    aplicarMascaraData(campoUltimaMenstruacao);
}

if (campoParto) {
    aplicarMascaraData(campoParto);
}

/*
|--------------------------------------------------------------------------
| MOSTRAR / OCULTAR GESTAÇÃO
|--------------------------------------------------------------------------
*/

const campoSexo =
    document.getElementById('sexo');

const areaGestacao =
    document.getElementById('areaGestacao');

function atualizarGestacao() {

    if (!campoSexo || !areaGestacao) {
        return;
    }

    if (campoSexo.value === 'Feminino') {

        areaGestacao.classList.remove('oculto');

    } else {

        areaGestacao.classList.add('oculto');
    }
}

if (campoSexo) {

    campoSexo.addEventListener(
        'change',
        atualizarGestacao
    );

    atualizarGestacao();
}

</script>

</body>

</html>