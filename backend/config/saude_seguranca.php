<?php
declare(strict_types=1);

/**
 * Segurança compartilhada do Saúde-Conecta.
 *
 * Não registra dados clínicos nos logs.
 * O proprietário da conta tem acesso aos próprios registros.
 * Terceiros só podem acessar registros quando houver permissão explícita.
 */

function saudePrepararSeguranca(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saude_auditoria (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            entidade VARCHAR(40) NOT NULL,
            registro_id BIGINT UNSIGNED NULL,
            acao VARCHAR(40) NOT NULL,
            resultado VARCHAR(20) NOT NULL DEFAULT 'sucesso',
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_auditoria_usuario_data (usuario_id, criado_em),
            KEY idx_auditoria_entidade_registro (entidade, registro_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saude_permissoes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            proprietario_id BIGINT UNSIGNED NOT NULL,
            autorizado_id BIGINT UNSIGNED NOT NULL,
            entidade VARCHAR(40) NOT NULL,
            registro_id BIGINT UNSIGNED NOT NULL,
            permissao VARCHAR(20) NOT NULL DEFAULT 'visualizar',
            concedida_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expira_em DATETIME NULL,
            revogada_em DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_saude_permissao (
                proprietario_id,
                autorizado_id,
                entidade,
                registro_id
            ),
            KEY idx_saude_permissao_autorizado (
                autorizado_id,
                revogada_em
            )
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Registra metadados mínimos de auditoria.
 * Nunca passe nome, diagnóstico, medicamento, telefone ou outro
 * dado pessoal para esta função.
 */
function saudeAuditar(
    PDO $pdo,
    int $usuarioId,
    string $entidade,
    ?int $registroId,
    string $acao,
    string $resultado = 'sucesso'
): void {
    if (
        $usuarioId <= 0
        || !in_array($entidade, ['crianca', 'idoso'], true)
        || !in_array(
            $acao,
            [
                'listar',
                'visualizar',
                'criar',
                'editar',
                'excluir',
                'acesso_negado'
            ],
            true
        )
        || !in_array($resultado, ['sucesso', 'negado', 'erro'], true)
    ) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO saude_auditoria
                (usuario_id, entidade, registro_id, acao, resultado)
            VALUES
                (:usuario_id, :entidade, :registro_id, :acao, :resultado)
        ");

        $stmt->execute([
            'usuario_id' => $usuarioId,
            'entidade' => $entidade,
            'registro_id' => $registroId,
            'acao' => $acao,
            'resultado' => $resultado
        ]);
    } catch (Throwable $e) {
        // Não expor erros internos ou informações médicas.
        error_log('Saúde-Conecta: falha ao registrar auditoria.');
    }
}

/**
 * Verifica se um usuário pode visualizar ou editar um registro.
 *
 * Esta função não substitui a filtragem por proprietário nas consultas.
 * Toda consulta de leitura ou alteração também deve limitar o registro
 * ao proprietário ou ao compartilhamento validado.
 */
function saudePodeAcessar(
    PDO $pdo,
    int $usuarioAtual,
    int $proprietarioId,
    string $entidade,
    int $registroId,
    string $acao = 'visualizar'
): bool {
    if (
        $usuarioAtual <= 0
        || $proprietarioId <= 0
        || $registroId <= 0
        || !in_array($entidade, ['crianca', 'idoso'], true)
        || !in_array($acao, ['visualizar', 'editar'], true)
    ) {
        return false;
    }

    if ($usuarioAtual === $proprietarioId) {
        return true;
    }

    $permissoes = $acao === 'editar'
        ? ['editar']
        : ['visualizar', 'editar'];

    $marcadores = implode(
        ', ',
        array_fill(0, count($permissoes), '?')
    );

    $sql = "
        SELECT 1
        FROM saude_permissoes
        WHERE proprietario_id = ?
          AND autorizado_id = ?
          AND entidade = ?
          AND registro_id = ?
          AND permissao IN ($marcadores)
          AND revogada_em IS NULL
          AND (expira_em IS NULL OR expira_em > NOW())
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(
        [
            $proprietarioId,
            $usuarioAtual,
            $entidade,
            $registroId
        ],
        $permissoes
    ));

    return (bool) $stmt->fetchColumn();
}

/**
 * Valida token CSRF para formulários.
 */
function saudeValidarCsrf(string $sessao, string $enviado): bool
{
    return $sessao !== ''
        && $enviado !== ''
        && hash_equals($sessao, $enviado);
}

/**
 * Cria um token CSRF para o formulário atual.
 */
function saudeNovoCsrf(string $chaveSessao): string
{
    if (
        !isset($_SESSION[$chaveSessao])
        || !is_string($_SESSION[$chaveSessao])
        || strlen($_SESSION[$chaveSessao]) < 32
    ) {
        $_SESSION[$chaveSessao] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$chaveSessao];
}

/**
 * Formata dados de saída em HTML.
 */
function saudeH(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Valida data no formato YYYY-MM-DD e impede datas futuras,
 * quando solicitado.
 */
function saudeDataValida(string $data, bool $impedirFutura = true): bool
{
    if ($data === '') {
        return true;
    }

    $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    if ($obj === false || $obj->format('Y-m-d') !== $data) {
        return false;
    }

    if ($impedirFutura && $obj > new DateTimeImmutable('today')) {
        return false;
    }

    return true;
}