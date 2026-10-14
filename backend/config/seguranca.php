<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Inicia a sessão com cookies protegidos.
 */
function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Exige uma sessão autenticada.
 */
function exigirLogin(): array
{
    iniciarSessaoSegura();

    if (empty($_SESSION['usuario']['id'])) {
        header('Location: /Saude-conecta/frontend/src/pages/login.php');
        exit;
    }

    return $_SESSION['usuario'];
}

/**
 * Busca novamente o usuário no banco.
 * Não confia apenas nos dados armazenados na sessão.
 */
function usuarioAtual(PDO $pdo): array
{
    $sessao = exigirLogin();

    $id = filter_var(
        $sessao['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, tipo_usuario, papel, ativo, medico_verificado
         FROM usuarios
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$usuario ||
        (int) $usuario['ativo'] !== 1
    ) {
        http_response_code(403);
        exit('Conta indisponível.');
    }

    return $usuario;
}

/**
 * Para páginas que permitem ao usuário consultar
 * e atualizar exclusivamente os próprios dados.
 */
function exigirDonoDaConta(PDO $pdo): array
{
    return usuarioAtual($pdo);
}

/**
 * Autoriza o acesso aos dados médicos de um paciente.
 *
 * Ação permitida: visualizar ou editar.
 *
 * O paciente pode acessar os próprios dados.
 * Um profissional só pode acessar dados de terceiros
 * se estiver verificado e tiver autorização ativa
 * concedida pelo próprio paciente.
 */
function exigirAcessoMedico(
    PDO $pdo,
    int $pacienteId,
    string $acao
): array {
    if (
        $pacienteId < 1 ||
        !in_array($acao, ['visualizar', 'editar'], true)
    ) {
        http_response_code(400);
        exit('Solicitação inválida.');
    }

    $usuario = usuarioAtual($pdo);

    // O paciente pode acessar os próprios dados.
    if ((int) $usuario['id'] === $pacienteId) {
        return $usuario;
    }

    // Somente profissionais verificados podem acessar terceiros.
    if (
        ($usuario['tipo_usuario'] ?? '') !== 'profissional' ||
        (int) ($usuario['medico_verificado'] ?? 0) !== 1
    ) {
        http_response_code(403);
        exit('Acesso permitido somente a profissionais verificados e autorizados.');
    }

    $permissao = $acao === 'editar'
        ? 'pode_editar'
        : 'pode_visualizar';

    $sql = "
        SELECT id
        FROM autorizacoes_medicas
        WHERE paciente_id = :paciente
          AND medico_id = :profissional
          AND autorizado_por = :autorizador
          AND status = 'ativa'
          AND pode_visualizar = 1
          AND {$permissao} = 1
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'paciente' => $pacienteId,
        'profissional' => (int) $usuario['id'],
        'autorizador' => $pacienteId,
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(403);
        exit('Você não possui autorização para esta operação médica.');
    }

    return $usuario;
}

/**
 * Gera ou recupera o token CSRF da sessão.
 */
function gerarTokenCsrf(): string
{
    iniciarSessaoSegura();

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF enviado pelo formulário.
 */
function validarTokenCsrf(?string $token): void
{
    iniciarSessaoSegura();

    $armazenado = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !is_string($armazenado) ||
        $armazenado === '' ||
        !hash_equals($armazenado, $token)
    ) {
        http_response_code(403);
        exit(
            'Formulário expirado ou inválido. '
            . 'Atualize a página e tente novamente.'
        );
    }
}