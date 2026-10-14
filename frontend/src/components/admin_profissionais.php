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

/*
 * Compatível com sessões que guardam $_SESSION['usuario']
 * como array com id, como número, ou $_SESSION['usuario_id'].
 */
function idDaSessao(): int
{
    $usuario = $_SESSION['usuario'] ?? null;

    if (is_array($usuario)) {
        return (int)($usuario['id'] ?? $usuario['usuario_id'] ?? 0);
    }

    if (is_numeric($usuario)) {
        return (int)$usuario;
    }

    return (int)($_SESSION['usuario_id'] ?? 0);
}

function h(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

$pdo = Database::getConnection();
$usuarioId = idDaSessao();

if ($usuarioId <= 0) {
    header('Location: ../pages/login.php');
    exit;
}

$consultaAdmin = $pdo->prepare(
    'SELECT id, nome_completo, tipo_usuario, papel, ativo
     FROM usuarios
     WHERE id = :id
     LIMIT 1'
);

$consultaAdmin->execute(['id' => $usuarioId]);
$admin = $consultaAdmin->fetch();

if (
    !$admin ||
    (int)$admin['ativo'] !== 1 ||
    (
        $admin['tipo_usuario'] !== 'admin' &&
        $admin['papel'] !== 'admin'
    )
) {
    http_response_code(403);
    exit('Acesso negado. Somente administradores podem revisar cadastros.');
}

if (empty($_SESSION['csrf_admin_profissionais'])) {
    $_SESSION['csrf_admin_profissionais'] = bin2hex(random_bytes(32));
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');

    if (!hash_equals(
        (string)$_SESSION['csrf_admin_profissionais'],
        $token
    )) {
        $erro = 'Token de segurança inválido. Atualize a página.';
    } else {
        $perfilId = filter_input(
            INPUT_POST,
            'perfil_id',
            FILTER_VALIDATE_INT
        );

        $acao = (string)($_POST['acao'] ?? '');
        $observacao = trim((string)($_POST['observacao'] ?? ''));

        if (
            !$perfilId ||
            !in_array($acao, ['aprovar', 'rejeitar'], true)
        ) {
            $erro = 'Ação inválida.';
        } else {
            try {
                $pdo->beginTransaction();

                $consulta = $pdo->prepare(
                    "SELECT id, usuario_id, situacao
                     FROM profissionais_saude
                     WHERE id = :id
                     FOR UPDATE"
                );

                $consulta->execute(['id' => $perfilId]);
                $perfil = $consulta->fetch();

                if (!$perfil || $perfil['situacao'] !== 'pendente') {
                    $pdo->rollBack();
                    $erro = 'Cadastro não encontrado ou já analisado.';
                } else {
                    $situacao = $acao === 'aprovar'
                        ? 'aprovado'
                        : 'rejeitado';

                    $atualizar = $pdo->prepare(
                        "UPDATE profissionais_saude
                         SET situacao = :situacao,
                             analisado_em = NOW(),
                             analisado_por = :admin_id,
                             observacao_admin = :observacao
                         WHERE id = :id"
                    );

                    $atualizar->execute([
                        'situacao' => $situacao,
                        'admin_id' => $usuarioId,
                        'observacao' => $observacao !== ''
                            ? mb_substr($observacao, 0, 500)
                            : null,
                        'id' => $perfilId,
                    ]);

                    /*
                     * A aprovação marca a conta como verificada.
                     * Não concede, por si só, acesso aos pacientes.
                     */
                    $atualizarUsuario = $pdo->prepare(
                        "UPDATE usuarios
                         SET medico_verificado = :verificado
                         WHERE id = :usuario_id
                           AND tipo_usuario = 'profissional'"
                    );

                    $atualizarUsuario->execute([
                        'verificado' => $acao === 'aprovar' ? 1 : 0,
                        'usuario_id' => (int)$perfil['usuario_id'],
                    ]);

                    $pdo->commit();

                    $mensagem = $acao === 'aprovar'
                        ? 'Profissional aprovado. A autorização do paciente continua necessária.'
                        : 'Cadastro rejeitado. O profissional permanece sem verificação.';

                    $_SESSION['csrf_admin_profissionais'] =
                        bin2hex(random_bytes(32));
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Revisão profissional: ' . $e->getMessage());
                $erro = 'Não foi possível processar a análise.';
            }
        }
    }
}

$consultaPendentes = $pdo->query(
    "SELECT
        ps.id AS perfil_id,
        ps.conselho,
        ps.conselho_uf,
        ps.numero_registro,
        ps.especialidade,
        ps.enviado_em,
        u.nome_completo,
        u.email,
        u.telefone
     FROM profissionais_saude ps
     INNER JOIN usuarios u ON u.id = ps.usuario_id
     WHERE ps.situacao = 'pendente'
     ORDER BY ps.enviado_em ASC"
);

$pendentes = $consultaPendentes->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificação de profissionais | Saúde-Conecta</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#f2f7f8;color:#18313a;font-family:Arial,sans-serif}
main{max-width:1100px;margin:30px auto;padding:24px;background:white;border-radius:16px;box-shadow:0 8px 25px #0001}
h1{color:#087f8c}
table{width:100%;border-collapse:collapse;margin-top:18px}
th,td{padding:12px;text-align:left;border-bottom:1px solid #dce5e8;vertical-align:top}
th{background:#eef7f8}
textarea{width:100%;min-height:65px;padding:9px;border:1px solid #bdcbd0;border-radius:8px}
button{padding:10px 13px;border:0;border-radius:8px;cursor:pointer;font-weight:bold;margin:5px 3px 0 0}
.aprovar{background:#087f8c;color:white}
.rejeitar{background:#9e2a2b;color:white}
.alert{padding:12px;border-radius:8px;margin:12px 0}
.ok{background:#e9f8ee;color:#145b31}
.erro{background:#fff0f0;color:#9d2020}
@media(max-width:760px){
 main{margin:10px;padding:12px;overflow-x:auto}
 table{min-width:850px}
}
</style>
</head>

<body>
<main>
<h1>Verificação de profissionais</h1>

<p>
Administrador:
<strong><?= h((string)$admin['nome_completo']) ?></strong>
</p>

<p>
Confira o registro no conselho profissional competente antes de aprovar.
A aprovação não concede acesso automático aos prontuários.
</p>

<?php if ($mensagem !== ''): ?>
    <div class="alert ok"><?= h($mensagem) ?></div>
<?php endif; ?>

<?php if ($erro !== ''): ?>
    <div class="alert erro"><?= h($erro) ?></div>
<?php endif; ?>

<?php if (!$pendentes): ?>

    <p>Não há cadastros pendentes de análise.</p>

<?php else: ?>

<table>
<thead>
<tr>
    <th>Profissional</th>
    <th>Registro</th>
    <th>Especialidade</th>
    <th>Data do cadastro</th>
    <th>Decisão</th>
</tr>
</thead>

<tbody>
<?php foreach ($pendentes as $p): ?>
<tr>
    <td>
        <strong><?= h((string)$p['nome_completo']) ?></strong><br>
        <?= h((string)$p['email']) ?><br>
        <?= h((string)$p['telefone']) ?>
    </td>

    <td>
        <?= h((string)$p['conselho']) ?>/<?= h((string)$p['conselho_uf']) ?>
        <?= h((string)$p['numero_registro']) ?>
    </td>

    <td>
        <?= h((string)($p['especialidade'] ?: 'Não informada')) ?>
    </td>

    <td><?= h((string)$p['enviado_em']) ?></td>

    <td>
        <form method="post">
            <input
                type="hidden"
                name="csrf"
                value="<?= h($_SESSION['csrf_admin_profissionais']) ?>"
            >

            <input
                type="hidden"
                name="perfil_id"
                value="<?= (int)$p['perfil_id'] ?>"
            >

            <label for="obs<?= (int)$p['perfil_id'] ?>">
                Observação
            </label>

            <textarea
                id="obs<?= (int)$p['perfil_id'] ?>"
                name="observacao"
                maxlength="500"
            ></textarea>

            <button
                class="aprovar"
                type="submit"
                name="acao"
                value="aprovar"
                onclick="return confirm('Você conferiu o registro profissional e deseja aprovar?')"
            >Aprovar</button>

            <button
                class="rejeitar"
                type="submit"
                name="acao"
                value="rejeitar"
                onclick="return confirm('Deseja rejeitar este cadastro?')"
            >Rejeitar</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php endif; ?>

<p><a href="../pages/login.php">Voltar ao login</a></p>
</main>
</body>
</html>