<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../backend/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (empty($_SESSION['csrf_profissional'])) {
    $_SESSION['csrf_profissional'] = bin2hex(random_bytes(32));
}

$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');

    if (!hash_equals(
        (string)$_SESSION['csrf_profissional'],
        $token
    )) {
        $erro = 'Sessão expirada. Atualize a página.';
    } else {
        $nome = trim((string)($_POST['nome_completo'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $cpf = preg_replace('/\D+/', '', (string)($_POST['cpf'] ?? ''));
        $telefone = trim((string)($_POST['telefone'] ?? ''));
        $nascimento = trim((string)($_POST['data_nascimento'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');
        $confirmar = (string)($_POST['confirmar_senha'] ?? '');

        $conselho = strtoupper(trim((string)($_POST['conselho'] ?? '')));
        $uf = strtoupper(trim((string)($_POST['conselho_uf'] ?? '')));
        $registro = trim((string)($_POST['numero_registro'] ?? ''));
        $especialidade = trim((string)($_POST['especialidade'] ?? ''));

        $conselhos = [
            'CRM', 'COREN', 'CRO', 'CRP',
            'CREFITO', 'CRF', 'CRESS', 'OUTRO'
        ];

        $data = DateTime::createFromFormat('!Y-m-d', $nascimento);
        $dataValida = $data && $data->format('Y-m-d') === $nascimento;

        if ($nome === '' || mb_strlen($nome) > 150) {
            $erro = 'Informe seu nome completo.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif (strlen($cpf) !== 11) {
            $erro = 'Informe o CPF com 11 números.';
        } elseif ($telefone === '') {
            $erro = 'Informe seu telefone.';
        } elseif (!$dataValida) {
            $erro = 'Informe uma data de nascimento válida.';
        } elseif (
            strlen($senha) < 10 ||
            !preg_match('/[A-Za-z]/', $senha) ||
            !preg_match('/[0-9]/', $senha)
        ) {
            $erro = 'A senha precisa ter 10 caracteres, letras e números.';
        } elseif ($senha !== $confirmar) {
            $erro = 'As senhas não coincidem.';
        } elseif (!in_array($conselho, $conselhos, true)) {
            $erro = 'Selecione o conselho profissional.';
        } elseif (!preg_match('/^[A-Z]{2}$/', $uf)) {
            $erro = 'Informe a UF com duas letras.';
        } elseif ($registro === '') {
            $erro = 'Informe o número de registro profissional.';
        } else {
            $pdo = null;

            try {
                $pdo = Database::getConnection();
                $pdo->beginTransaction();

                $consulta = $pdo->prepare(
                    'SELECT id FROM usuarios
                     WHERE email = :email OR cpf = :cpf
                     LIMIT 1'
                );

                $consulta->execute([
                    'email' => $email,
                    'cpf' => $cpf,
                ]);

                if ($consulta->fetch()) {
                    $pdo->rollBack();
                    $erro = 'Este e-mail ou CPF já está cadastrado.';
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);

                    $inserir = $pdo->prepare(
                        "INSERT INTO usuarios
                        (
                            cpf, nome_completo, email, senha_hash,
                            telefone, data_nascimento,
                            tipo_usuario, papel, ativo, medico_verificado
                        )
                        VALUES
                        (
                            :cpf, :nome, :email, :senha,
                            :telefone, :nascimento,
                            'profissional', 'usuario', 1, 0
                        )"
                    );

                    $inserir->execute([
                        'cpf' => $cpf,
                        'nome' => $nome,
                        'email' => $email,
                        'senha' => $hash,
                        'telefone' => $telefone,
                        'nascimento' => $nascimento,
                    ]);

                    $usuarioId = (int)$pdo->lastInsertId();

                    $perfil = $pdo->prepare(
                        "INSERT INTO profissionais_saude
                        (
                            usuario_id, conselho, conselho_uf,
                            numero_registro, especialidade, situacao
                        )
                        VALUES
                        (
                            :usuario_id, :conselho, :uf,
                            :registro, :especialidade, 'pendente'
                        )"
                    );

                    $perfil->execute([
                        'usuario_id' => $usuarioId,
                        'conselho' => $conselho,
                        'uf' => $uf,
                        'registro' => $registro,
                        'especialidade' => $especialidade ?: null,
                    ]);

                    $pdo->commit();

                    $sucesso = true;
                    $_SESSION['csrf_profissional'] =
                        bin2hex(random_bytes(32));
                }
            } catch (Throwable $e) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Cadastro profissional: ' . $e->getMessage());
                $erro = 'Erro ao cadastrar. Confira a tabela profissionais_saude.';
            }
        }
    }
}

function h(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastro profissional | Saúde-Conecta</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#f2f7f8;color:#18313a;font-family:Arial,sans-serif}
main{max-width:780px;margin:35px auto;padding:28px;background:white;border-radius:16px;box-shadow:0 8px 25px #0001}
h1{color:#087f8c}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
label{display:block;font-weight:bold;font-size:14px}
input,select{display:block;width:100%;padding:12px;margin-top:7px;border:1px solid #bdcbd0;border-radius:8px;font-size:16px}
button{background:#087f8c;color:white;border:0;padding:13px 18px;border-radius:8px;font-weight:bold;cursor:pointer;margin-top:18px}
.alert{padding:13px;border-radius:8px;margin:15px 0}
.erro{background:#fff0f0;color:#9d2020}
.ok{background:#e9f8ee;color:#145b31}
.nota{background:#eef7fa;padding:12px;border-radius:8px;line-height:1.5}
a{color:#087f8c}
@media(max-width:600px){main{margin:10px;padding:18px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<h1>Cadastro de profissional de saúde</h1>

<p>Preencha seus dados e seu registro profissional. Sua conta precisará ser verificada antes de ser reconhecida como profissional aprovado.</p>

<?php if ($erro !== ''): ?>
    <div class="alert erro"><?= h($erro) ?></div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="alert ok">
        <strong>Cadastro enviado!</strong><br>
        Seu registro está pendente de verificação pelo administrador.
    </div>
    <a href="login.php">Voltar ao login</a>
<?php else: ?>

<form method="post" autocomplete="on">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_profissional']) ?>">

<div class="grid">
    <label>Nome completo
        <input name="nome_completo" maxlength="150" required>
    </label>

    <label>E-mail
        <input name="email" type="email" maxlength="150" required>
    </label>

    <label>CPF
        <input name="cpf" inputmode="numeric" maxlength="14" required>
    </label>

    <label>Telefone
        <input name="telefone" maxlength="20" required>
    </label>

    <label>Data de nascimento
        <input name="data_nascimento" type="date" required>
    </label>

    <label>Conselho profissional
        <select name="conselho" required>
            <option value="">Selecione</option>
            <option value="CRM">CRM</option>
            <option value="COREN">COREN</option>
            <option value="CRO">CRO</option>
            <option value="CRP">CRP</option>
            <option value="CREFITO">CREFITO</option>
            <option value="CRF">CRF</option>
            <option value="CRESS">CRESS</option>
            <option value="OUTRO">Outro</option>
        </select>
    </label>

    <label>UF do conselho
        <input name="conselho_uf" maxlength="2" placeholder="PB" required>
    </label>

    <label>Número de registro
        <input name="numero_registro" maxlength="40" required>
    </label>

    <label>Especialidade (opcional)
        <input name="especialidade" maxlength="120">
    </label>

    <label>Senha
        <input name="senha" type="password" minlength="10" autocomplete="new-password" required>
    </label>

    <label>Confirmar senha
        <input name="confirmar_senha" type="password" minlength="10" autocomplete="new-password" required>
    </label>
</div>

<p class="nota">
O cadastro não concede acesso automático aos dados médicos. O registro precisa ser conferido por um administrador, e o paciente deve autorizar o acesso aos seus dados.
</p>

<button type="submit">Enviar cadastro</button>
</form>
<?php endif; ?>
</main>
</body>
</html>