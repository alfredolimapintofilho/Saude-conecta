
<?php
declare(strict_types=1);

/**
 * Segurança e autorização do Saúde-Conecta.
 *
 * Inclua este arquivo nas páginas protegidas depois de iniciar
 * a sessão. Não confie somente no papel salvo no navegador.
 */

require_once __DIR__ . '/database.php';

function exigirLogin(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['usuario']['id'])) {
        header('Location: ../../frontend/src/pages/login.php');
        exit;
    }

    return $_SESSION['usuario'];
}

/**
 * Consulta o papel atual diretamente no banco.
 *
 * IMPORTANTE:
 * A coluna "papel" precisa existir na tabela usuarios.
 * Os valores previstos são "titular", "medico" e "usuario".
 * O papel médico não deve ser escolhido pelo próprio usuário.
 */
function obterUsuarioAutenticado(PDO $pdo): array
{
    $sessao = exigirLogin();
    $id = filter_var($sessao['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, papel, ativo
         FROM usuarios
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$usuario ||
        (int) ($usuario['ativo'] ?? 0) !== 1
    ) {
        http_response_code(403);
        exit('Conta indisponível.');
    }

    return $usuario;
}

/**
 * Confirma que o titular pode acessar a própria conta.
 */
function exigirTitular(PDO $pdo): array
{
    $usuario = obterUsuarioAutenticado($pdo);

    if ($usuario['papel'] !== 'titular') {
        http_response_code(403);
        exit('Somente o titular pode realizar esta operação.');
    }

    return $usuario;
}

/**
 * Verifica se um médico tem autorização ativa para um paciente.
 * $acao aceita apenas "visualizar" ou "editar".
 */
function exigirAcessoMedico(
    PDO $pdo,
    int $pacienteId,
    string $acao
): array {
    if (!in_array($acao, ['visualizar', 'editar'], true)) {
        http_response_code(400);
        exit('Operação inválida.');
    }

    $medico = obterUsuarioAutenticado($pdo);

    if ($medico['papel'] !== 'medico') {
        http_response_code(403);
        exit('Acesso reservado a médicos verificados.');
    }

    $coluna = $acao === 'editar'
        ? 'pode_editar'
        : 'pode_visualizar';

    // O nome da coluna é escolhido exclusivamente pelo servidor.
    $sql = "
        SELECT id
        FROM autorizacoes_medicas
        WHERE paciente_id = :paciente
          AND medico_id = :medico
          AND status = 'ativa'
          AND pode_visualizar = 1
          AND {$coluna} = 1
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'paciente' => $pacienteId,
        'medico' => (int) $medico['id']
    ]);

    if (!$stmt->fetch()) {
        http_response_code(403);
        exit('Você não tem autorização para acessar estes dados médicos.');
    }

    return $medico;
}