
<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$usuario = $_SESSION['usuario'];
$usuarioId = is_array($usuario)
    ? (int) ($usuario['id'] ?? 0)
    : (int) $usuario;

if ($usuarioId <= 0) {
    exit('Não foi possível identificar sua conta. Faça login novamente.');
}

require_once __DIR__ . '/../../../backend/config/database.php';
$pdo = Database::getConnection();

$erro = '';
$especies = [
    'Cachorro', 'Gato', 'Coelho', 'Hamster', 'Porquinho-da-índia',
    'Cavalo', 'Jumento', 'Boi/Vaca', 'Porco', 'Ovelha',
    'Cabra', 'Galinha', 'Pato', 'Outro'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $especie = trim($_POST['especie'] ?? '');
    $raca = trim($_POST['raca'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $idade = trim($_POST['idade'] ?? '');
    $porte = trim($_POST['porte'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $uf = strtoupper(trim($_POST['uf'] ?? 'PB'));
    $descricao = trim($_POST['descricao'] ?? '');
    $contato = trim($_POST['contato'] ?? '');
    $vacinado = isset($_POST['vacinado']) ? 1 : 0;
    $castrado = isset($_POST['castrado']) ? 1 : 0;

    if (
        $nome === '' || !in_array($especie, $especies, true) ||
        $cidade === '' || $descricao === '' || $contato === ''
    ) {
        $erro = 'Preencha nome, espécie, cidade, descrição e contato.';
    } elseif (!preg_match('/^[A-Z]{2}$/', $uf)) {
        $erro = 'Informe uma sigla de estado válida, como PB.';
    } else {
        $fotoNome = null;

        try {
            if (
                isset($_FILES['foto']) &&
                $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Não foi possível enviar a foto.');
                }

                if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('A foto deve ter até 5 MB.');
                }

                $tmp = $_FILES['foto']['tmp_name'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

                $extensoes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($extensoes[$mime])) {
                    throw new RuntimeException(
                        'Use uma foto JPG, PNG ou WEBP.'
                    );
                }

                $pasta = __DIR__ . '/uploads/adocao';

                if (
                    !is_dir($pasta) &&
                    !mkdir($pasta, 0755, true) &&
                    !is_dir($pasta)
                ) {
                    throw new RuntimeException(
                        'Não foi possível criar a pasta de fotos.'
                    );
                }

                $fotoNome = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];

                if (!move_uploaded_file($tmp, $pasta . '/' . $fotoNome)) {
                    throw new RuntimeException('Não foi possível salvar a foto.');
                }
            }

            $sql = "INSERT INTO pets_adocao
                (usuario_id, nome, especie, raca, sexo, idade, porte,
                 cidade, uf, descricao, vacinado, castrado, contato, foto)
                VALUES
                (:usuario_id, :nome, :especie, :raca, :sexo, :idade, :porte,
                 :cidade, :uf, :descricao, :vacinado, :castrado, :contato, :foto)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':nome' => $nome,
                ':especie' => $especie,
                ':raca' => $raca ?: null,
                ':sexo' => $sexo ?: null,
                ':idade' => $idade ?: null,
                ':porte' => $porte ?: null,
                ':cidade' => $cidade,
                ':uf' => $uf,
                ':descricao' => $descricao,
                ':vacinado' => $vacinado,
                ':castrado' => $castrado,
                ':contato' => $contato,
                ':foto' => $fotoNome
            ]);

            header('Location: adocao_pets.php?cadastro=sucesso');
            exit;
        } catch (Throwable $e) {
            if (!empty($fotoNome)) {
                $caminho = __DIR__ . '/uploads/adocao/' . $fotoNome;
                if (is_file($caminho)) {
                    unlink($caminho);
                }
            }

            error_log($e->getMessage());
            $erro = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Não foi possível cadastrar o anúncio. Confira a tabela pets_adocao.';
        }
    }
}

function e($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastrar pet para adoção | Saúde-Conecta</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f1f7f5;color:#20332c;font-family:Arial,sans-serif}
header{background:#087f5b;color:white;padding:22px}
header a{color:white}
main{max-width:850px;margin:28px auto;padding:0 16px}
.card{background:white;padding:26px;border-radius:16px;box-shadow:0 5px 22px #1232}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
label{display:block;font-weight:bold;margin-bottom:6px}
input,select,textarea{width:100%;padding:12px;border:1px solid #bdcec6;border-radius:8px;font:inherit}
textarea{min-height:120px;resize:vertical}
.campo{margin-bottom:16px}
.checks{display:flex;gap:20px;flex-wrap:wrap;margin:15px 0}
.checks label{display:flex;align-items:center;gap:8px}
.checks input{width:auto}
button,.btn{display:inline-block;border:0;border-radius:9px;padding:13px 18px;background:#087f5b;color:white;text-decoration:none;font-weight:bold;cursor:pointer}
.voltar{background:#e4eee9;color:#20332c}
.erro{background:#ffeded;color:#8c2020;padding:12px;border-radius:8px}
@media(max-width:600px){.grid{grid-template-columns:1fr}.card{padding:18px}}
</style>
</head>
<body>
<header>
    <h1>🐾 Cadastrar pet para adoção</h1>
    <a href="adocao_pets.php">Voltar para Adoção de Pets</a>
</header>

<main>
<section class="card">
    <h2>Encontre um novo lar para este animal</h2>
    <p>Preencha os dados com atenção. Não anuncie animais para venda.</p>

    <?php if ($erro): ?>
        <p class="erro"><?= e($erro) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="grid">
            <div class="campo">
                <label for="nome">Nome do animal *</label>
                <input id="nome" name="nome" required maxlength="100"
                    value="<?= e($_POST['nome'] ?? '') ?>">
            </div>

            <div class="campo">
                <label for="especie">Espécie *</label>
                <select id="especie" name="especie" required>
                    <option value="">Selecione</option>
                    <?php foreach ($especies as $item): ?>
                        <option value="<?= e($item) ?>"
                            <?= ($_POST['especie'] ?? '') === $item ? 'selected' : '' ?>>
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="raca">Raça (opcional)</label>
                <input id="raca" name="raca" value="<?= e($_POST['raca'] ?? '') ?>">
            </div>

            <div class="campo">
                <label for="sexo">Sexo</label>
                <select id="sexo" name="sexo">
                    <option value="">Não informado</option>
                    <option value="Macho">Macho</option>
                    <option value="Fêmea">Fêmea</option>
                </select>
            </div>

            <div class="campo">
                <label for="idade">Idade aproximada</label>
                <input id="idade" name="idade" placeholder="Ex.: 6 meses"
                    value="<?= e($_POST['idade'] ?? '') ?>">
            </div>

            <div class="campo">
                <label for="porte">Porte</label>
                <select id="porte" name="porte">
                    <option value="">Não se aplica / não informado</option>
                    <option>Pequeno</option>
                    <option>Médio</option>
                    <option>Grande</option>
                </select>
            </div>

            <div class="campo">
                <label for="cidade">Cidade *</label>
                <input id="cidade" name="cidade" required
                    value="<?= e($_POST['cidade'] ?? 'Patos') ?>">
            </div>

            <div class="campo">
                <label for="uf">Estado *</label>
                <input id="uf" name="uf" maxlength="2" required
                    value="<?= e($_POST['uf'] ?? 'PB') ?>">
            </div>
        </div>

        <div class="campo">
            <label for="descricao">História e características do animal *</label>
            <textarea id="descricao" name="descricao" required
                placeholder="Conte sobre o temperamento, necessidades e cuidados do pet."><?= e($_POST['descricao'] ?? '') ?></textarea>
        </div>

        <div class="checks">
            <label><input type="checkbox" name="vacinado" value="1"> Vacinado</label>
            <label><input type="checkbox" name="castrado" value="1"> Castrado</label>
        </div>

        <div class="campo">
            <label for="contato">Contato para adoção *</label>
            <input id="contato" name="contato" required maxlength="150"
                placeholder="Telefone ou WhatsApp para interessados"
                value="<?= e($_POST['contato'] ?? '') ?>">
            <small>Informe um contato que você autoriza a ser exibido publicamente.</small>
        </div>

        <div class="campo">
            <label for="foto">Foto do animal (JPG, PNG ou WEBP; até 5 MB)</label>
            <input id="foto" type="file" name="foto"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        </div>

        <button type="submit">Publicar para adoção</button>
        <a class="btn voltar" href="adocao_pets.php">Cancelar</a>
    </form>
</section>
</main>
</body>
</html>