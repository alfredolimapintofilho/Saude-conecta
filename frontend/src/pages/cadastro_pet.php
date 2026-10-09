
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
            'A data de nascimento deve ser válida e estar no formato DD/MM/AAAA.'
        );
    }

    if ($objeto > new DateTime('today')) {
        throw new InvalidArgumentException(
            'A data de nascimento não pode ser no futuro.'
        );
    }

    return $objeto->format('Y-m-d');
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

$nome = '';
$tipo = 'Companhia';
$especie = 'Cachorro';
$raca = '';
$sexo = 'Não identificado';
$dataNascimento = '';
$cor = '';
$peso = '';
$microchip = '';
$vacinado = false;
$castrado = false;
$alergias = '';
$doencas = '';
$medicamentos = '';
$observacoes = '';
$porte = 'Não informado';

$erro = '';
$sucesso = '';

if (empty($_SESSION['csrf_pet'])) {
    $_SESSION['csrf_pet'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $arquivoFotoSalvo = null;

    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_pet'], (string) $_POST['csrf_token'])
        ) {
            throw new InvalidArgumentException(
                'Sua sessão do formulário expirou. Atualize a página e tente novamente.'
            );
        }

        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $especie = trim($_POST['especie'] ?? '');
        $raca = trim($_POST['raca'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');
        $cor = trim($_POST['cor'] ?? '');
        $pesoTexto = trim($_POST['peso'] ?? '');
        $microchip = trim($_POST['microchip'] ?? '');
        $vacinado = isset($_POST['vacinado']);
        $castrado = isset($_POST['castrado']);
        $alergias = trim($_POST['alergias'] ?? '');
        $doencas = trim($_POST['doencas'] ?? '');
        $medicamentos = trim($_POST['medicamentos'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');
        $porte = trim($_POST['porte'] ?? 'Não informado');

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
            throw new InvalidArgumentException('Selecione o sexo do pet.');
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
                'Os campos de informações devem ter no máximo 5.000 caracteres.'
            );
        }

        $dataNascimentoBanco = dataParaBanco($dataNascimento);

        $peso = null;

        if ($pesoTexto !== '') {
            $pesoNormalizado = str_replace(',', '.', $pesoTexto);

            if (!is_numeric($pesoNormalizado)) {
                throw new InvalidArgumentException('Informe um peso válido.');
            }

            $peso = (float) $pesoNormalizado;

            if ($peso <= 0 || $peso > 2000) {
                throw new InvalidArgumentException(
                    'O peso deve ser maior que zero e não pode ultrapassar 2.000 kg.'
                );
            }
        }

        /*
         * Foto opcional.
         * Arquivos são guardados em frontend/src/pages/uploads/pets.
         */
        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    'Não foi possível enviar a foto. Tente outra imagem.'
                );
            }

            if ($_FILES['foto']['size'] > 4 * 1024 * 1024) {
                throw new InvalidArgumentException(
                    'A foto deve ter no máximo 4 MB.'
                );
            }

            $arquivoTemporario = $_FILES['foto']['tmp_name'];

            if (!is_uploaded_file($arquivoTemporario)) {
                throw new InvalidArgumentException('O arquivo enviado não é válido.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($arquivoTemporario);

            $extensoesPermitidas = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($extensoesPermitidas[$mime])) {
                throw new InvalidArgumentException(
                    'Envie uma foto JPG, PNG ou WEBP.'
                );
            }

            $pastaFotos = __DIR__ . '/uploads/pets';

            if (
                !is_dir($pastaFotos) &&
                !mkdir($pastaFotos, 0755, true) &&
                !is_dir($pastaFotos)
            ) {
                throw new RuntimeException(
                    'Não foi possível criar a pasta de fotos dos pets.'
                );
            }

            $nomeArquivo = bin2hex(random_bytes(16)) .
                '.' . $extensoesPermitidas[$mime];

            $destino = $pastaFotos . '/' . $nomeArquivo;

            if (!move_uploaded_file($arquivoTemporario, $destino)) {
                throw new RuntimeException('Não foi possível salvar a foto.');
            }

            $arquivoFotoSalvo = 'uploads/pets/' . $nomeArquivo;
        }

        $sql = 'INSERT INTO pets (
                    usuario_id,
                    nome,
                    tipo,
                    especie,
                    raca,
                    sexo,
                    data_nascimento,
                    cor,
                    peso,
                    microchip,
                    vacinado,
                    castrado,
                    alergias,
                    doencas,
                    medicamentos,
                    observacoes,
                    foto
                ) VALUES (
                    :usuario_id,
                    :nome,
                    :tipo,
                    :especie,
                    :raca,
                    :sexo,
                    :data_nascimento,
                    :cor,
                    :peso,
                    :microchip,
                    :vacinado,
                    :castrado,
                    :alergias,
                    :doencas,
                    :medicamentos,
                    :observacoes,
                    :foto
                )';

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'usuario_id' => $usuarioId,
            'nome' => $nome,
            'tipo' => $tipo,
            'especie' => $especie,
            'raca' => $raca !== '' ? $raca : null,
            'sexo' => $sexo,
            'data_nascimento' => $dataNascimentoBanco,
            'cor' => $cor !== '' ? $cor : null,
            'peso' => $peso,
            'microchip' => $microchip !== '' ? $microchip : null,
            'vacinado' => $vacinado ? 1 : 0,
            'castrado' => $castrado ? 1 : 0,
            'alergias' => $alergias !== '' ? $alergias : null,
            'doencas' => $doencas !== '' ? $doencas : null,
            'medicamentos' => $medicamentos !== '' ? $medicamentos : null,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'foto' => $arquivoFotoSalvo
        ]);

        // Evita reenvio acidental do formulário.
        unset($_SESSION['csrf_pet']);

        header('Location: pets.php?cadastro=sucesso');
        exit;

    } catch (InvalidArgumentException $ex) {
        $erro = $ex->getMessage();
    } catch (Throwable $ex) {
        error_log('Erro ao cadastrar pet: ' . $ex->getMessage());

        // Se o banco falhar depois do envio da foto, remove o arquivo órfão.
        if ($arquivoFotoSalvo !== null) {
            $caminho = __DIR__ . '/' . $arquivoFotoSalvo;

            if (is_file($caminho)) {
                unlink($caminho);
            }
        }

        $erro = 'Não foi possível cadastrar o pet. Confira a tabela pets no banco de dados e tente novamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Pet | Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #20313b;
            background: #f2f6f8;
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
            margin-top: 0;
            color: #075e68;
        }

        .descricao {
            color: #596b73;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .mensagem {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .erro {
            background: #fff0ed;
            color: #982e20;
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

        .completo {
            grid-column: 1 / -1;
        }

        .subtitulo {
            grid-column: 1 / -1;
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
            grid-column: 1 / -1;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .checkbox input {
            width: auto;
        }

        .nota {
            font-size: 13px;
            color: #62737b;
            line-height: 1.5;
        }

        .preview {
            display: none;
            width: 180px;
            max-height: 180px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #dce5e9;
            margin-top: 12px;
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

        .primario:hover {
            background: #056671;
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
            <p>Cadastro de animais</p>
        </div>

        <a class="voltar" href="pets.php">Voltar para Meus Pets</a>
    </div>
</header>

<main>
    <div class="cartao">
        <h2>Cadastrar meu pet</h2>

        <p class="descricao">
            Preencha as informações do seu animal. Os campos com
            asterisco (*) são obrigatórios. Você poderá consultar
            e atualizar os dados depois do cadastro.
        </p>

        <?php if ($erro !== ''): ?>
            <div class="mensagem erro" role="alert">
                <?= e($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_pet']) ?>">

            <div class="grade">
                <h3 class="subtitulo">Identificação do pet</h3>

                <div class="campo">
                    <label for="nome">Nome do pet *</label>
                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="100"
                        required
                        value="<?= e($nome) ?>"
                        placeholder="Ex.: Rex">
                </div>

                <div class="campo">
                    <label for="tipo">Categoria *</label>
                    <select id="tipo" name="tipo" required>
                        <?php foreach ($tiposPermitidos as $opcao): ?>
                            <option value="<?= e($opcao) ?>"
                                <?= $tipo === $opcao ? 'selected' : '' ?>>
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
                                <?= $especie === $opcao ? 'selected' : '' ?>>
                                <?= e($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="raca">Raça</label>
                    <input
                        type="text"
                        id="raca"
                        name="raca"
                        maxlength="100"
                        value="<?= e($raca) ?>"
                        placeholder="Ex.: Labrador ou sem raça definida">
                </div>

                <div class="campo">
                    <label for="sexo">Sexo *</label>
                    <select id="sexo" name="sexo" required>
                        <?php foreach ($sexosPermitidos as $opcao): ?>
                            <option value="<?= e($opcao) ?>"
                                <?= $sexo === $opcao ? 'selected' : '' ?>>
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
                        value="<?= e($dataNascimento) ?>">
                    <span class="nota">Digite a data manualmente.</span>
                </div>

                <div class="campo">
                    <label for="cor">Cor ou pelagem</label>
                    <input
                        type="text"
                        id="cor"
                        name="cor"
                        maxlength="100"
                        value="<?= e($cor) ?>"
                        placeholder="Ex.: preto e branco">
                </div>

                <div class="campo">
                    <label for="porte">Porte</label>
                    <select id="porte" name="porte">
                        <?php foreach ($portesPermitidos as $opcao): ?>
                            <option value="<?= e($opcao) ?>"
                                <?= $porte === $opcao ? 'selected' : '' ?>>
                                <?= e($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="nota">
                        Se a tabela pets não tiver a coluna porte, veja a observação abaixo.
                    </span>
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
                        value="<?= e($peso) ?>"
                        placeholder="Ex.: 12,5">
                </div>

                <div class="campo">
                    <label for="microchip">Número do microchip</label>
                    <input
                        type="text"
                        id="microchip"
                        name="microchip"
                        maxlength="100"
                        value="<?= e($microchip) ?>"
                        placeholder="Se o animal tiver">
                </div>

                <div class="campo completo">
                    <label for="foto">Foto do pet</label>
                    <input
                        type="file"
                        id="foto"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <span class="nota">
                        Formatos JPG, PNG ou WEBP. Tamanho máximo: 4 MB.
                    </span>
                    <img id="preview" class="preview" alt="Prévia da foto selecionada">
                </div>

                <h3 class="subtitulo">Saúde do pet</h3>

                <div class="checkboxes">
                    <label class="checkbox">
                        <input
                            type="checkbox"
                            name="vacinado"
                            value="1"
                            <?= $vacinado ? 'checked' : '' ?>>
                        O pet já foi vacinado
                    </label>

                    <label class="checkbox">
                        <input
                            type="checkbox"
                            name="castrado"
                            value="1"
                            <?= $castrado ? 'checked' : '' ?>>
                        O pet é castrado
                    </label>
                </div>

                <div class="campo completo">
                    <label for="alergias">Alergias</label>
                    <textarea
                        id="alergias"
                        name="alergias"
                        maxlength="5000"
                        placeholder="Informe alergias conhecidas ou deixe em branco"><?= e($alergias) ?></textarea>
                </div>

                <div class="campo completo">
                    <label for="doencas">Doenças ou condições de saúde</label>
                    <textarea
                        id="doencas"
                        name="doencas"
                        maxlength="5000"
                        placeholder="Informe doenças conhecidas"><?= e($doencas) ?></textarea>
                </div>

                <div class="campo completo">
                    <label for="medicamentos">Medicamentos em uso</label>
                    <textarea
                        id="medicamentos"
                        name="medicamentos"
                        maxlength="5000"
                        placeholder="Nome dos medicamentos, se houver"><?= e($medicamentos) ?></textarea>
                </div>

                <div class="campo completo">
                    <label for="observacoes">Observações adicionais</label>
                    <textarea
                        id="observacoes"
                        name="observacoes"
                        maxlength="5000"
                        placeholder="Outras informações importantes"><?= e($observacoes) ?></textarea>
                </div>
            </div>

            <div class="acoes">
                <button class="botao primario" type="submit">
                    Cadastrar pet
                </button>

                <a class="botao secundario" href="pets.php">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</main>

<script>
    // Digitação manual da data no formato DD/MM/AAAA.
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

    // Pré-visualização local da foto antes de enviar.
    const campoFoto = document.getElementById('foto');
    const preview = document.getElementById('preview');

    campoFoto.addEventListener('change', function () {
        const arquivo = campoFoto.files[0];

        if (!arquivo) {
            preview.removeAttribute('src');
            preview.style.display = 'none';
            return;
        }

        const tiposAceitos = ['image/jpeg', 'image/png', 'image/webp'];

        if (!tiposAceitos.includes(arquivo.type)) {
            alert('Selecione uma imagem JPG, PNG ou WEBP.');
            campoFoto.value = '';
            preview.style.display = 'none';
            return;
        }

        if (arquivo.size > 4 * 1024 * 1024) {
            alert('A imagem deve ter no máximo 4 MB.');
            campoFoto.value = '';
            preview.style.display = 'none';
            return;
        }

        preview.src = URL.createObjectURL(arquivo);
        preview.style.display = 'block';
    });

    // Sugere uma categoria de acordo com a espécie selecionada.
    const campoTipo = document.getElementById('tipo');
    const campoEspecie = document.getElementById('especie');

    campoEspecie.addEventListener('change', function () {
        const companhia = [
            'Cachorro',
            'Gato',
            'Hamster',
            'Porquinho-da-índia',
            'Coelho'
        ];

        const trabalho = ['Cavalo', 'Jumento'];

        const producao = [
            'Bovino',
            'Porco',
            'Ovelha',
            'Cabra',
            'Galinha',
            'Pato'
        ];

        if (companhia.includes(campoEspecie.value)) {
            campoTipo.value = 'Companhia';
        } else if (trabalho.includes(campoEspecie.value)) {
            campoTipo.value = 'Trabalho e transporte';
        } else if (producao.includes(campoEspecie.value)) {
            campoTipo.value = 'Produção';
        }
    });
</script>

</body>
</html>