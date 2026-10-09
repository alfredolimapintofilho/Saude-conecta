
<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

$pdo = Database::getConnection();

$sessaoUsuario = $_SESSION['usuario'];
$usuarioId = is_array($sessaoUsuario)
    ? (int) ($sessaoUsuario['id'] ?? 0)
    : (int) $sessaoUsuario;

if ($usuarioId <= 0) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function dataParaBanco(string $data): ?string
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    $objeto = DateTime::createFromFormat('!d/m/Y', $data);
    $erros = DateTime::getLastErrors();

    if (
        !$objeto ||
        ($erros !== false &&
            ($erros['warning_count'] > 0 || $erros['error_count'] > 0)) ||
        $objeto->format('d/m/Y') !== $data
    ) {
        throw new InvalidArgumentException(
            'A data de nascimento deve estar no formato DD/MM/AAAA.'
        );
    }

    if ($objeto > new DateTime('today')) {
        throw new InvalidArgumentException(
            'A data de nascimento não pode ser no futuro.'
        );
    }

    return $objeto->format('Y-m-d');
}

function dataParaTela(?string $data): string
{
    if (!$data || $data === '0000-00-00') {
        return '';
    }

    $objeto = DateTime::createFromFormat('!Y-m-d', $data);

    return $objeto ? $objeto->format('d/m/Y') : '';
}

$tiposPermitidos = [
    'Companhia',
    'Trabalho e transporte',
    'Produção',
    'Outro'
];

$especiesPermitidas = [
    'Cachorro',
    'Gato',
    'Hamster',
    'Porquinho-da-índia',
    'Coelho',
    'Cavalo',
    'Jumento',
    'Bovino',
    'Porco',
    'Ovelha',
    'Cabra',
    'Galinha',
    'Pato',
    'Outro'
];

$sexosPermitidos = [
    'Macho',
    'Fêmea',
    'Não identificado'
];

$portesPermitidos = [
    'Pequeno',
    'Médio',
    'Grande',
    'Não informado'
];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: pets.php?erro=id_invalido');
    exit;
}

/*
|--------------------------------------------------------------------------
| Buscar o pet pertencente ao usuário conectado
|--------------------------------------------------------------------------
*/

try {
    $consulta = $pdo->prepare(
        'SELECT *
         FROM pets
         WHERE id = :id AND usuario_id = :usuario_id
         LIMIT 1'
    );

    $consulta->execute([
        'id' => $id,
        'usuario_id' => $usuarioId
    ]);

    $pet = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        header('Location: pets.php?erro=pet_nao_encontrado');
        exit;
    }
} catch (PDOException $erro) {
    error_log('Erro ao consultar pet para edição: ' . $erro->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar os dados do pet.');
}

if (empty($_SESSION['csrf_editar_pet'])) {
    $_SESSION['csrf_editar_pet'] = bin2hex(random_bytes(32));
}

$erro = '';
$sucesso = '';

/*
|--------------------------------------------------------------------------
| Salvar alterações
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fotoNovaSalva = null;

    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_editar_pet'],
                (string) $_POST['csrf_token']
            )
        ) {
            throw new InvalidArgumentException(
                'O formulário expirou. Atualize a página e tente novamente.'
            );
        }

        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $especie = trim($_POST['especie'] ?? '');
        $raca = trim($_POST['raca'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $dataNascimentoTexto = trim($_POST['data_nascimento'] ?? '');
        $cor = trim($_POST['cor'] ?? '');
        $porte = trim($_POST['porte'] ?? 'Não informado');
        $pesoTexto = trim($_POST['peso'] ?? '');
        $microchip = trim($_POST['microchip'] ?? '');
        $vacinado = isset($_POST['vacinado']) ? 1 : 0;
        $castrado = isset($_POST['castrado']) ? 1 : 0;
        $alergias = trim($_POST['alergias'] ?? '');
        $doencas = trim($_POST['doencas'] ?? '');
        $medicamentos = trim($_POST['medicamentos'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        if ($nome === '' || mb_strlen($nome) > 100) {
            throw new InvalidArgumentException(
                'Informe o nome do pet, com até 100 caracteres.'
            );
        }

        if (!in_array($tipo, $tiposPermitidos, true)) {
            throw new InvalidArgumentException('Selecione uma categoria válida.');
        }

        if (!in_array($especie, $especiesPermitidas, true)) {
            throw new InvalidArgumentException('Selecione uma espécie válida.');
        }

        if (!in_array($sexo, $sexosPermitidos, true)) {
            throw new InvalidArgumentException('Selecione um sexo válido.');
        }

        if (!in_array($porte, $portesPermitidos, true)) {
            throw new InvalidArgumentException('Selecione um porte válido.');
        }

        if (
            mb_strlen($raca) > 100 ||
            mb_strlen($cor) > 100 ||
            mb_strlen($microchip) > 100
        ) {
            throw new InvalidArgumentException(
                'Raça, cor e microchip devem ter no máximo 100 caracteres.'
            );
        }

        if (
            mb_strlen($alergias) > 5000 ||
            mb_strlen($doencas) > 5000 ||
            mb_strlen($medicamentos) > 5000 ||
            mb_strlen($observacoes) > 5000
        ) {
            throw new InvalidArgumentException(
                'Os campos de saúde e observações devem ter no máximo 5.000 caracteres.'
            );
        }

        $dataNascimento = dataParaBanco($dataNascimentoTexto);

        $peso = null;

        if ($pesoTexto !== '') {
            $pesoNormalizado = str_replace(',', '.', $pesoTexto);

            if (!is_numeric($pesoNormalizado)) {
                throw new InvalidArgumentException('Informe um peso válido.');
            }

            $peso = (float) $pesoNormalizado;

            if ($peso <= 0 || $peso > 2000) {
                throw new InvalidArgumentException(
                    'O peso deve ser maior que zero e não ultrapassar 2.000 kg.'
                );
            }
        }

        /*
         * A foto atual é mantida se o usuário não selecionar outra.
         */
        $foto = $pet['foto'] ?? null;

        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    'Não foi possível enviar a nova foto.'
                );
            }

            if ($_FILES['foto']['size'] > 4 * 1024 * 1024) {
                throw new InvalidArgumentException(
                    'A foto deve ter no máximo 4 MB.'
                );
            }

            $temporario = $_FILES['foto']['tmp_name'];

            if (!is_uploaded_file($temporario)) {
                throw new InvalidArgumentException('O arquivo enviado é inválido.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($temporario);

            $extensoes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($extensoes[$mime])) {
                throw new InvalidArgumentException(
                    'Envie uma imagem JPG, PNG ou WEBP.'
                );
            }

            $pastaFotos = __DIR__ . '/uploads/pets';

            if (
                !is_dir($pastaFotos) &&
                !mkdir($pastaFotos, 0755, true) &&
                !is_dir($pastaFotos)
            ) {
                throw new RuntimeException(
                    'Não foi possível criar a pasta para fotos dos pets.'
                );
            }

            $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
            $destino = $pastaFotos . '/' . $nomeArquivo;

            if (!move_uploaded_file($temporario, $destino)) {
                throw new RuntimeException('Não foi possível salvar a nova foto.');
            }

            $fotoNovaSalva = 'uploads/pets/' . $nomeArquivo;
            $foto = $fotoNovaSalva;
        }

        /*
         * O SQL atualiza somente o pet do usuário autenticado.
         */
        $sql = 'UPDATE pets SET
                    nome = :nome,
                    tipo = :tipo,
                    especie = :especie,
                    raca = :raca,
                    sexo = :sexo,
                    data_nascimento = :data_nascimento,
                    cor = :cor,
                    porte = :porte,
                    peso = :peso,
                    microchip = :microchip,
                    vacinado = :vacinado,
                    castrado = :castrado,
                    alergias = :alergias,
                    doencas = :doencas,
                    medicamentos = :medicamentos,
                    observacoes = :observacoes,
                    foto = :foto
                WHERE id = :id AND usuario_id = :usuario_id';

        $atualizacao = $pdo->prepare($sql);

        $atualizacao->execute([
            'nome' => $nome,
            'tipo' => $tipo,
            'especie' => $especie,
            'raca' => $raca !== '' ? $raca : null,
            'sexo' => $sexo,
            'data_nascimento' => $dataNascimento,
            'cor' => $cor !== '' ? $cor : null,
            'porte' => $porte,
            'peso' => $peso,
            'microchip' => $microchip !== '' ? $microchip : null,
            'vacinado' => $vacinado,
            'castrado' => $castrado,
            'alergias' => $alergias !== '' ? $alergias : null,
            'doencas' => $doencas !== '' ? $doencas : null,
            'medicamentos' => $medicamentos !== '' ? $medicamentos : null,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'foto' => $foto,
            'id' => $id,
            'usuario_id' => $usuarioId
        ]);

        /*
         * Se uma foto nova foi salva, remove a antiga depois da atualização.
         */
        if (
            $fotoNovaSalva !== null &&
            !empty($pet['foto']) &&
            $pet['foto'] !== $fotoNovaSalva
        ) {
            $fotoAntiga = __DIR__ . '/' . $pet['foto'];

            if (is_file($fotoAntiga)) {
                unlink($fotoAntiga);
            }
        }

        unset($_SESSION['csrf_editar_pet']);

        /*
         * CORREÇÃO DO ERRO:
         * Não redireciona para pet.php, que estava retornando Not Found.
         * Volta para a lista de pets, que já existe no projeto.
         */
        header('Location: pets.php?atualizado=1');
        exit;

    } catch (InvalidArgumentException $ex) {
        $erro = $ex->getMessage();
    } catch (Throwable $ex) {
        error_log('Erro ao editar pet: ' . $ex->getMessage());

        if ($fotoNovaSalva !== null) {
            $caminhoFoto = __DIR__ . '/' . $fotoNovaSalva;

            if (is_file($caminhoFoto)) {
                unlink($caminhoFoto);
            }
        }

        $erro = 'Não foi possível atualizar o pet. Confira as colunas da tabela pets e tente novamente.';
    }
}

/*
 * Recarrega o cadastro para exibir os valores atuais.
 */
try {
    $consulta = $pdo->prepare(
        'SELECT *
         FROM pets
         WHERE id = :id AND usuario_id = :usuario_id
         LIMIT 1'
    );

    $consulta->execute([
        'id' => $id,
        'usuario_id' => $usuarioId
    ]);

    $pet = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        header('Location: pets.php?erro=pet_nao_encontrado');
        exit;
    }
} catch (PDOException $ex) {
    error_log('Erro ao recarregar pet: ' . $ex->getMessage());
    http_response_code(500);
    exit('Não foi possível recarregar os dados do pet.');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pet | Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f2f6f8;
            color: #20313b;
        }

        header {
            background: #087f8c;
            color: white;
            padding: 22px 16px;
        }

        .cabecalho,
        main {
            max-width: 1000px;
            margin: auto;
        }

        .cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        header h1 {
            margin: 0;
            font-size: 26px;
        }

        header p {
            margin: 6px 0 0;
        }

        .voltar {
            color: white;
            text-decoration: none;
            border: 1px solid white;
            padding: 10px 14px;
            border-radius: 8px;
        }

        main {
            padding: 25px 16px 40px;
        }

        .cartao {
            background: white;
            padding: 25px;
            border-radius: 13px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, .06);
        }

        h2 {
            color: #075e68;
            margin-top: 0;
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .campo label {
            font-size: 14px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #bdcdd3;
            border-radius: 7px;
            padding: 11px;
            font: inherit;
            background: white;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .completo,
        .subtitulo,
        .checkboxes {
            grid-column: 1 / -1;
        }

        .subtitulo {
            border-bottom: 1px solid #e0e9ec;
            color: #087f8c;
            padding-bottom: 9px;
            margin: 12px 0 0;
            font-size: 18px;
        }

        .checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .checkbox input {
            width: auto;
        }

        .foto-atual {
            width: 170px;
            max-height: 170px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .nota {
            font-size: 13px;
            color: #62737b;
        }

        .mensagem {
            padding: 13px 15px;
            border-radius: 8px;
            background: #fff0ed;
            color: #982e20;
            margin-bottom: 18px;
        }

        .acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 25px;
        }

        .botao {
            display: inline-block;
            padding: 12px 19px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        .primario {
            color: white;
            background: #087f8c;
        }

        .secundario {
            color: #075e68;
            background: #e8f3f5;
        }

        @media (max-width: 650px) {
            .grade {
                grid-template-columns: 1fr;
            }

            .completo,
            .subtitulo,
            .checkboxes {
                grid-column: auto;
            }

            .cartao {
                padding: 18px;
            }
        }
    </style>
</head>

<body>
<header>
    <div class="cabecalho">
        <div>
            <h1>Saúde-Conecta</h1>
            <p>Editar cadastro do pet</p>
        </div>

        <a class="voltar" href="pets.php">Voltar para Meus Pets</a>
    </div>
</header>

<main>
    <div class="cartao">
        <h2>Editar <?= e($pet['nome'] ?? 'pet') ?></h2>

        <?php if ($erro !== ''): ?>
            <div class="mensagem" role="alert"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_editar_pet']) ?>">

            <div class="grade">
                <h3 class="subtitulo">Identificação do pet</h3>

                <div class="campo">
                    <label for="nome">Nome *</label>
                    <input
                        id="nome"
                        name="nome"
                        maxlength="100"
                        required
                        value="<?= e($pet['nome'] ?? '') ?>">
                </div>

                <div class="campo">
                    <label for="tipo">Categoria *</label>
                    <select id="tipo" name="tipo" required>
                        <?php foreach ($tiposPermitidos as $opcao): ?>
                            <option value="<?= e($opcao) ?>"
                                <?= ($pet['tipo'] ?? '') === $opcao ? 'selected' : '' ?>>
                                <?= e($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="especie">Espécie *</label>
                    <select id="especie" name="especie" required>
                        <?php foreach ($especiesPermitidas as $opcao): ?>
                            <option value="<?= e($opcao) ?>"
                                <?= ($pet['especie'] ?? '') === $opcao ? 'selected' : '' ?>>
                                <?= e($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="raca">Raça</label>
                    <input
                        id="raca"
                        name="raca"
                        maxlength="100"
                        value="<?= e($pet['raca'] ?? '') ?>">
                </div>

                <div class="campo">
                    <label for="sexo">Sexo *</label>
                    <select id="sexo" name="sexo" required>
                        <?php foreach ($sexosPermitidos as $opcao): ?>
                            <option value="<?= e($opcao) ?>"
                                <?= ($pet['sexo'] ?? '') === $opcao ? 'selected' : '' ?>>
                                <?= e($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="data_nascimento">Data de nascimento</label>
                    <input
                        type="text"
                        id="data_nascimento"
                        name="data_nascimento"
                        inputmode="numeric"
                        maxlength="10"
                        placeholder="DD/MM/AAAA"
                        value="<?= e(dataParaTela($pet['data_nascimento'] ?? null)) ?>">
                </div>

                <div class="campo">
                    <label for="cor">Cor ou pelagem</label>
                    <input
                        id="cor"
                        name="cor"
                        maxlength="100"
                        value="<?= e($pet['cor'] ?? '') ?>">
                </div>

                <div class="campo">
                    <label for="porte">Porte</label>
                    <select id="porte" name="porte">
                        <?php foreach ($portesPermitidos as $opcao): ?>
                            <option value="<?= e($opcao) ?>"
                                <?= ($pet['porte'] ?? 'Não informado') === $opcao ? 'selected' : '' ?>>
                                <?= e($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="peso">Peso em kg</label>
                    <input
                        type="number"
                        id="peso"
                        name="peso"
                        min="0.01"
                        max="2000"
                        step="0.01"
                        value="<?= e($pet['peso'] ?? '') ?>">
                </div>

                <div class="campo">
                    <label for="microchip">Número do microchip</label>
                    <input
                        id="microchip"
                        name="microchip"
                        maxlength="100"
                        value="<?= e($pet['microchip'] ?? '') ?>">
                </div>

                <div class="campo completo">
                    <label>Foto atual</label>

                    <?php if (!empty($pet['foto'])): ?>
                        <img
                            class="foto-atual"
                            src="<?= e($pet['foto']) ?>"
                            alt="Foto atual de <?= e($pet['nome'] ?? 'pet') ?>">
                    <?php else: ?>
                        <p class="nota">Nenhuma foto cadastrada.</p>
                    <?php endif; ?>

                    <label for="foto">Selecionar uma nova foto (opcional)</label>
                    <input
                        type="file"
                        id="foto"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                    <p class="nota">
                        Se não escolher outra imagem, a foto atual será mantida.
                        JPG, PNG ou WEBP; até 4 MB.
                    </p>
                </div>

                <h3 class="subtitulo">Informações de saúde</h3>

                <div class="checkboxes">
                    <label class="checkbox">
                        <input
                            type="checkbox"
                            name="vacinado"
                            value="1"
                            <?= !empty($pet['vacinado']) ? 'checked' : '' ?>>
                        Já foi vacinado
                    </label>

                    <label class="checkbox">
                        <input
                            type="checkbox"
                            name="castrado"
                            value="1"
                            <?= !empty($pet['castrado']) ? 'checked' : '' ?>>
                        É castrado
                    </label>
                </div>

                <div class="campo completo">
                    <label for="alergias">Alergias</label>
                    <textarea id="alergias" name="alergias" maxlength="5000"><?= e($pet['alergias'] ?? '') ?></textarea>
                </div>

                <div class="campo completo">
                    <label for="doencas">Doenças ou condições de saúde</label>
                    <textarea id="doencas" name="doencas" maxlength="5000"><?= e($pet['doencas'] ?? '') ?></textarea>
                </div>

                <div class="campo completo">
                    <label for="medicamentos">Medicamentos em uso</label>
                    <textarea id="medicamentos" name="medicamentos" maxlength="5000"><?= e($pet['medicamentos'] ?? '') ?></textarea>
                </div>

                <div class="campo completo">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" maxlength="5000"><?= e($pet['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="acoes">
                <button class="botao primario" type="submit">
                    Salvar alterações
                </button>

                <a class="botao secundario" href="pets.php">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</main>

<script>
    const campoData = document.getElementById('data_nascimento');

    campoData.addEventListener('input', function () {
        let numeros = campoData.value.replace(/\D/g, '').slice(0, 8);

        if (numeros.length > 4) {
            numeros = numeros.slice(0, 2) + '/' +
                numeros.slice(2, 4) + '/' + numeros.slice(4);
        } else if (numeros.length > 2) {
            numeros = numeros.slice(0, 2) + '/' + numeros.slice(2);
        }

        campoData.value = numeros;
    });
</script>
</body>
</html>