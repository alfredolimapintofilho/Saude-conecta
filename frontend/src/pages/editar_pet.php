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
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| ID DO PET
|--------------------------------------------------------------------------
*/

$petId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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
        'Erro ao carregar o pet: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}

/*
|--------------------------------------------------------------------------
| FUNÇÕES DE DATA
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
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$nome = $pet['nome'] ?? '';
$tipo = $pet['tipo'] ?? '';
$especie = $pet['especie'] ?? '';
$raca = $pet['raca'] ?? '';
$sexo = $pet['sexo'] ?? '';
$dataNascimento = dataParaBrasileiro(
    $pet['data_nascimento'] ?? ''
);
$cor = $pet['cor'] ?? '';
$peso = $pet['peso'] ?? '';
$microchip = $pet['microchip'] ?? '';
$vacinado = $pet['vacinado'] ?? '';
$castrado = $pet['castrado'] ?? '';
$alergias = $pet['alergias'] ?? '';
$doencas = $pet['doencas'] ?? '';
$medicamentos = $pet['medicamentos'] ?? '';
$observacoes = $pet['observacoes'] ?? '';
$fotoAtual = $pet['foto'] ?? '';

$erro = '';
$mensagem = '';

/*
|--------------------------------------------------------------------------
| SALVAR ALTERAÇÕES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $tipo = trim($_POST['tipo'] ?? '');
    $especie = trim($_POST['especie'] ?? '');
    $raca = trim($_POST['raca'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');

    $dataNascimento = trim(
        $_POST['data_nascimento'] ?? ''
    );

    $cor = trim($_POST['cor'] ?? '');
    $peso = trim($_POST['peso'] ?? '');
    $microchip = trim($_POST['microchip'] ?? '');

    $vacinado = trim($_POST['vacinado'] ?? '');
    $castrado = trim($_POST['castrado'] ?? '');

    $alergias = trim($_POST['alergias'] ?? '');
    $doencas = trim($_POST['doencas'] ?? '');
    $medicamentos = trim($_POST['medicamentos'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CAMPOS OBRIGATÓRIOS
    |--------------------------------------------------------------------------
    */

    if ($nome === '') {

        $erro = 'Informe o nome do pet.';

    } elseif ($tipo === '') {

        $erro = 'Informe o tipo do pet.';

    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR DATA
    |--------------------------------------------------------------------------
    */

    $dataNascimentoMysql = null;

    if ($erro === '') {

        $dataNascimentoMysql = dataParaMysql(
            $dataNascimento
        );

        if ($dataNascimentoMysql === false) {

            $erro =
                'A data de nascimento deve estar no formato DD/MM/AAAA.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR PESO
    |--------------------------------------------------------------------------
    */

    $pesoBanco = null;

    if ($erro === '' && $peso !== '') {

        if (!is_numeric($peso) || (float) $peso <= 0) {

            $erro = 'Informe um peso válido.';

        } else {

            $pesoBanco = (float) $peso;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FOTO
    |--------------------------------------------------------------------------
    */

    $fotoBanco = $fotoAtual;

    if (
        $erro === '' &&
        isset($_FILES['foto']) &&
        $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {

            $erro = 'Erro ao enviar a foto.';

        } else {

            $arquivo = $_FILES['foto'];

            $extensao = strtolower(
                pathinfo(
                    $arquivo['name'],
                    PATHINFO_EXTENSION
                )
            );

            $extensoesPermitidas = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            if (!in_array(
                $extensao,
                $extensoesPermitidas,
                true
            )) {

                $erro =
                    'Formato de imagem inválido. Use JPG, JPEG, PNG ou WEBP.';

            } elseif ($arquivo['size'] > 5 * 1024 * 1024) {

                $erro =
                    'A foto deve ter no máximo 5 MB.';

            } else {

                $pastaFotos =
                    __DIR__ . '/../uploads/pets/';

                if (!is_dir($pastaFotos)) {

                    mkdir(
                        $pastaFotos,
                        0777,
                        true
                    );
                }

                $novoNome =
                    'pet_' .
                    $petId .
                    '_' .
                    time() .
                    '_' .
                    bin2hex(random_bytes(4)) .
                    '.' .
                    $extensao;

                $caminhoCompleto =
                    $pastaFotos . $novoNome;

                if (
                    move_uploaded_file(
                        $arquivo['tmp_name'],
                        $caminhoCompleto
                    )
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | APAGAR FOTO ANTIGA
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $fotoAtual !== '' &&
                        strpos($fotoAtual, 'uploads/pets/') === 0
                    ) {

                        $arquivoAntigo =
                            __DIR__ .
                            '/../' .
                            $fotoAtual;

                        if (is_file($arquivoAntigo)) {
                            @unlink($arquivoAntigo);
                        }
                    }

                    $fotoBanco =
                        'uploads/pets/' .
                        $novoNome;

                } else {

                    $erro =
                        'Não foi possível salvar a nova foto.';
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ATUALIZAR PET
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        try {

            $stmt = $pdo->prepare("
                UPDATE pets
                SET
                    nome = ?,
                    tipo = ?,
                    especie = ?,
                    raca = ?,
                    sexo = ?,
                    data_nascimento = ?,
                    cor = ?,
                    peso = ?,
                    microchip = ?,
                    vacinado = ?,
                    castrado = ?,
                    alergias = ?,
                    doencas = ?,
                    medicamentos = ?,
                    observacoes = ?,
                    foto = ?
                WHERE id = ?
                AND usuario_id = ?
            ");

            $stmt->execute([

                $nome,

                $tipo,

                $especie !== ''
                    ? $especie
                    : null,

                $raca !== ''
                    ? $raca
                    : null,

                $sexo !== ''
                    ? $sexo
                    : null,

                $dataNascimentoMysql,

                $cor !== ''
                    ? $cor
                    : null,

                $pesoBanco,

                $microchip !== ''
                    ? $microchip
                    : null,

                $vacinado !== ''
                    ? $vacinado
                    : null,

                $castrado !== ''
                    ? $castrado
                    : null,

                $alergias !== ''
                    ? $alergias
                    : null,

                $doencas !== ''
                    ? $doencas
                    : null,

                $medicamentos !== ''
                    ? $medicamentos
                    : null,

                $observacoes !== ''
                    ? $observacoes
                    : null,

                $fotoBanco !== ''
                    ? $fotoBanco
                    : null,

                $petId,

                $usuarioId
            ]);

            header(
                'Location: pet.php?id=' .
                $petId .
                '&atualizado=1'
            );

            exit;

        } catch (PDOException $e) {

            $erro =
                'Erro ao atualizar o pet: ' .
                $e->getMessage();
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
        Editar Pet - Saúde-Conecta
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

        header {
            background: #0f766e;
            color: white;
            padding: 18px 25px;
        }

        .header-container {
            max-width: 1000px;
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
            gap: 8px;
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
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px 50px;
        }

        .titulo {
            margin-bottom: 20px;
        }

        .titulo h1 {
            margin-bottom: 5px;
        }

        .titulo p {
            color: #64748b;
            margin-top: 0;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
        }

        .erro {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .campo {
            margin-bottom: 18px;
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
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 15px;
            background: white;
        }

        .campo textarea {
            min-height: 110px;
            resize: vertical;
        }

        .linha {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .foto-atual {
            margin-bottom: 20px;
            text-align: center;
        }

        .foto-atual img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 15px;
            border: 3px solid #e2e8f0;
        }

        .sem-foto {
            width: 180px;
            height: 180px;
            margin: auto;
            border-radius: 15px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .botoes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .botao {
            border: 0;
            padding: 12px 20px;
            border-radius: 9px;
            background: #0f766e;
            color: white;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
            display: inline-block;
        }

        .botao:hover {
            background: #115e59;
        }

        .botao-cinza {
            background: #475569;
        }

        .botao-cinza:hover {
            background: #334155;
        }

        .botao-azul {
            background: #2563eb;
        }

        .botao-azul:hover {
            background: #1d4ed8;
        }

        .observacao {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
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

            <a href="pets.php">
                Meus Pets
            </a>

            <a href="pet.php?id=<?= $petId ?>">
                Perfil do Pet
            </a>

            <a href="logout.php">
                Sair
            </a>

        </nav>

    </div>

</header>

<main>

    <div class="titulo">

        <h1>
            Editar Pet
        </h1>

        <p>
            Atualize as informações de
            <?= htmlspecialchars(
                $nome,
                ENT_QUOTES,
                'UTF-8'
            ) ?>.
        </p>

    </div>

    <?php if ($erro !== ''): ?>

        <div class="erro">

            <?= htmlspecialchars(
                $erro,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>

    <div class="card">

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <!-- FOTO -->

            <div class="foto-atual">

                <?php if ($fotoAtual !== ''): ?>

                    <img
                        src="../<?= htmlspecialchars(
                            $fotoAtual,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="Foto do pet"
                    >

                <?php else: ?>

                    <div class="sem-foto">
                        Sem foto
                    </div>

                <?php endif; ?>

            </div>

            <div class="campo">

                <label for="foto">
                    Alterar foto
                </label>

                <input
                    type="file"
                    id="foto"
                    name="foto"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <div class="observacao">
                    JPG, JPEG, PNG ou WEBP. Máximo de 5 MB.
                </div>

            </div>

            <!-- DADOS PRINCIPAIS -->

            <div class="linha">

                <div class="campo">

                    <label for="nome">
                        Nome do pet *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        required
                        value="<?= htmlspecialchars(
                            $nome,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="tipo">
                        Tipo do pet *
                    </label>

                    <select
                        id="tipo"
                        name="tipo"
                        required
                    >

                        <option value="">
                            Selecione
                        </option>

                        <option
                            value="Cão"
                            <?= $tipo === 'Cão'
                                ? 'selected'
                                : '' ?>
                        >
                            Cão
                        </option>

                        <option
                            value="Gato"
                            <?= $tipo === 'Gato'
                                ? 'selected'
                                : '' ?>
                        >
                            Gato
                        </option>

                        <option
                            value="Hamster"
                            <?= $tipo === 'Hamster'
                                ? 'selected'
                                : '' ?>
                        >
                            Hamster
                        </option>

                        <option
                            value="Porquinho-da-índia"
                            <?= $tipo === 'Porquinho-da-índia'
                                ? 'selected'
                                : '' ?>
                        >
                            Porquinho-da-índia
                        </option>

                        <option
                            value="Coelho"
                            <?= $tipo === 'Coelho'
                                ? 'selected'
                                : '' ?>
                        >
                            Coelho
                        </option>

                        <option
                            value="Cavalo"
                            <?= $tipo === 'Cavalo'
                                ? 'selected'
                                : '' ?>
                        >
                            Cavalo
                        </option>

                        <option
                            value="Jumento"
                            <?= $tipo === 'Jumento'
                                ? 'selected'
                                : '' ?>
                        >
                            Jumento
                        </option>

                        <option
                            value="Bovino"
                            <?= $tipo === 'Bovino'
                                ? 'selected'
                                : '' ?>
                        >
                            Bovino
                        </option>

                        <option
                            value="Suíno"
                            <?= $tipo === 'Suíno'
                                ? 'selected'
                                : '' ?>
                        >
                            Suíno
                        </option>

                        <option
                            value="Ovelha"
                            <?= $tipo === 'Ovelha'
                                ? 'selected'
                                : '' ?>
                        >
                            Ovelha
                        </option>

                        <option
                            value="Cabra"
                            <?= $tipo === 'Cabra'
                                ? 'selected'
                                : '' ?>
                        >
                            Cabra
                        </option>

                        <option
                            value="Galinha"
                            <?= $tipo === 'Galinha'
                                ? 'selected'
                                : '' ?>
                        >
                            Galinha
                        </option>

                        <option
                            value="Pato"
                            <?= $tipo === 'Pato'
                                ? 'selected'
                                : '' ?>
                        >
                            Pato
                        </option>

                        <option
                            value="Outro"
                            <?= $tipo === 'Outro'
                                ? 'selected'
                                : '' ?>
                        >
                            Outro
                        </option>

                    </select>

                </div>

            </div>

            <div class="linha">

                <div class="campo">

                    <label for="especie">
                        Espécie
                    </label>

                    <input
                        type="text"
                        id="especie"
                        name="especie"
                        value="<?= htmlspecialchars(
                            $especie,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Ex.: Canino"
                    >

                </div>

                <div class="campo">

                    <label for="raca">
                        Raça
                    </label>

                    <input
                        type="text"
                        id="raca"
                        name="raca"
                        value="<?= htmlspecialchars(
                            $raca,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Ex.: Labrador"
                    >

                </div>

            </div>

            <div class="linha">

                <div class="campo">

                    <label for="sexo">
                        Sexo
                    </label>

                    <select
                        id="sexo"
                        name="sexo"
                    >

                        <option value="">
                            Não informado
                        </option>

                        <option
                            value="Macho"
                            <?= $sexo === 'Macho'
                                ? 'selected'
                                : '' ?>
                        >
                            Macho
                        </option>

                        <option
                            value="Fêmea"
                            <?= $sexo === 'Fêmea'
                                ? 'selected'
                                : '' ?>
                        >
                            Fêmea
                        </option>

                    </select>

                </div>

                <div class="campo">

                    <label for="data_nascimento">
                        Data de nascimento
                    </label>

                    <input
                        type="text"
                        id="data_nascimento"
                        name="data_nascimento"
                        placeholder="DD/MM/AAAA"
                        maxlength="10"
                        value="<?= htmlspecialchars(
                            $dataNascimento,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <div class="observacao">
                        Digite a data manualmente.
                    </div>

                </div>

            </div>

            <div class="linha">

                <div class="campo">

                    <label for="cor">
                        Cor
                    </label>

                    <input
                        type="text"
                        id="cor"
                        name="cor"
                        value="<?= htmlspecialchars(
                            $cor,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="peso">
                        Peso (kg)
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="peso"
                        name="peso"
                        value="<?= htmlspecialchars(
                            $peso,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

            </div>

            <div class="campo">

                <label for="microchip">
                    Microchip
                </label>

                <input
                    type="text"
                    id="microchip"
                    name="microchip"
                    value="<?= htmlspecialchars(
                        $microchip,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="Número do microchip, se houver"
                >

            </div>

            <!-- SAÚDE -->

            <div class="linha">

                <div class="campo">

                    <label for="vacinado">
                        Vacinado
                    </label>

                    <select
                        id="vacinado"
                        name="vacinado"
                    >

                        <option value="">
                            Não informado
                        </option>

                        <option
                            value="Sim"
                            <?= $vacinado === 'Sim'
                                ? 'selected'
                                : '' ?>
                        >
                            Sim
                        </option>

                        <option
                            value="Não"
                            <?= $vacinado === 'Não'
                                ? 'selected'
                                : '' ?>
                        >
                            Não
                        </option>

                        <option
                            value="Parcialmente"
                            <?= $vacinado === 'Parcialmente'
                                ? 'selected'
                                : '' ?>
                        >
                            Parcialmente
                        </option>

                    </select>

                </div>

                <div class="campo">

                    <label for="castrado">
                        Castrado
                    </label>

                    <select
                        id="castrado"
                        name="castrado"
                    >

                        <option value="">
                            Não informado
                        </option>

                        <option
                            value="Sim"
                            <?= $castrado === 'Sim'
                                ? 'selected'
                                : '' ?>
                        >
                            Sim
                        </option>

                        <option
                            value="Não"
                            <?= $castrado === 'Não'
                                ? 'selected'
                                : '' ?>
                        >
                            Não
                        </option>

                    </select>

                </div>

            </div>

            <div class="campo">

                <label for="alergias">
                    Alergias
                </label>

                <textarea
                    id="alergias"
                    name="alergias"
                    placeholder="Informe alergias conhecidas..."
                ><?= htmlspecialchars(
                    $alergias,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

            </div>

            <div class="campo">

                <label for="doencas">
                    Doenças
                </label>

                <textarea
                    id="doencas"
                    name="doencas"
                    placeholder="Informe doenças ou condições de saúde..."
                ><?= htmlspecialchars(
                    $doencas,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

            </div>

            <div class="campo">

                <label for="medicamentos">
                    Medicamentos
                </label>

                <textarea
                    id="medicamentos"
                    name="medicamentos"
                    placeholder="Informe medicamentos utilizados..."
                ><?= htmlspecialchars(
                    $medicamentos,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

            </div>

            <div class="campo">

                <label for="observacoes">
                    Observações
                </label>

                <textarea
                    id="observacoes"
                    name="observacoes"
                    placeholder="Outras informações importantes..."
                ><?= htmlspecialchars(
                    $observacoes,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

            </div>

            <!-- BOTÕES -->

            <div class="botoes">

                <button
                    type="submit"
                    class="botao"
                >
                    Salvar alterações
                </button>

                <a
                    href="pet.php?id=<?= $petId ?>"
                    class="botao botao-azul"
                >
                    Ver perfil do pet
                </a>

                <a
                    href="pets.php"
                    class="botao botao-cinza"
                >
                    Voltar para Meus Pets
                </a>

            </div>

        </form>

    </div>

</main>

<script>

/*
|--------------------------------------------------------------------------
| MÁSCARA DE DATA DD/MM/AAAA
|--------------------------------------------------------------------------
*/

const campoData =
    document.getElementById('data_nascimento');

if (campoData) {

    campoData.addEventListener('input', function () {

        let valor =
            this.value.replace(/\D/g, '');

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

</script>

</body>

</html>