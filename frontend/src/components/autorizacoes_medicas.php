<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Saúde-Conecta — Autorizações Médicas
|--------------------------------------------------------------------------
| Arquivo: frontend/src/components/autorizacoes_medicas.php
|
| O paciente pode:
| - Consultar profissionais verificados;
| - Conceder acesso aos próprios dados médicos;
| - Definir permissão para visualizar e editar;
| - Revogar autorizações concedidas anteriormente.
|
| Requer:
| backend/config/seguranca.php
| backend/config/database.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../../backend/config/seguranca.php';

iniciarSessaoSegura();

$pdo = Database::getConnection();

/*
 * A página trabalha somente com a conta autenticada.
 * O paciente não pode escolher outro paciente pela URL ou formulário.
 */
$usuario = exigirDonoDaConta($pdo);
$pacienteId = (int) $usuario['id'];

$erro = '';
$sucesso = '';

/*
|--------------------------------------------------------------------------
| Proteção CSRF
|--------------------------------------------------------------------------
*/

$tokenCsrf = gerarTokenCsrf();

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
*/

function escaparAutorizacao(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Processar formulários
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validarTokenCsrf($_POST['csrf_token'] ?? null);

        $acao = (string) ($_POST['acao'] ?? '');

        /*
         * Conceder ou atualizar uma autorização.
         */
        if ($acao === 'autorizar') {
            $profissionalId = filter_var(
                $_POST['profissional_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                !$profissionalId ||
                $profissionalId < 1 ||
                $profissionalId === $pacienteId
            ) {
                throw new InvalidArgumentException(
                    'Selecione um profissional válido.'
                );
            }

            $podeEditar = isset($_POST['pode_editar'])
                && (string) $_POST['pode_editar'] === '1'
                    ? 1
                    : 0;

            /*
             * Confirma que a conta existe, está ativa,
             * tem tipo profissional e foi verificada.
             */
            $stmtProfissional = $pdo->prepare(
                "SELECT id
                 FROM usuarios
                 WHERE id = :id
                   AND tipo_usuario = 'profissional'
                   AND ativo = 1
                   AND medico_verificado = 1
                 LIMIT 1"
            );

            $stmtProfissional->execute([
                'id' => $profissionalId,
            ]);

            if (!$stmtProfissional->fetch()) {
                throw new InvalidArgumentException(
                    'O profissional não está disponível ou '
                    . 'ainda não foi verificado.'
                );
            }

            $pdo->beginTransaction();

            /*
             * Procura a autorização mais recente entre
             * este paciente e o profissional escolhido.
             */
            $stmtExistente = $pdo->prepare(
                "SELECT id
                 FROM autorizacoes_medicas
                 WHERE paciente_id = :paciente
                   AND medico_id = :profissional
                 ORDER BY id DESC
                 LIMIT 1"
            );

            $stmtExistente->execute([
                'paciente' => $pacienteId,
                'profissional' => $profissionalId,
            ]);

            $autorizacaoExistente = $stmtExistente->fetch(
                PDO::FETCH_ASSOC
            );

            if ($autorizacaoExistente) {
                /*
                 * Reativa ou atualiza o registro mais recente.
                 * O autorizador é sempre o próprio paciente.
                 */
                $stmtSalvar = $pdo->prepare(
                    "UPDATE autorizacoes_medicas
                     SET autorizado_por = :autorizador,
                         pode_visualizar = 1,
                         pode_editar = :pode_editar,
                         status = 'ativa'
                     WHERE id = :id
                       AND paciente_id = :paciente"
                );

                $stmtSalvar->execute([
                    'autorizador' => $pacienteId,
                    'pode_editar' => $podeEditar,
                    'id' => (int) $autorizacaoExistente['id'],
                    'paciente' => $pacienteId,
                ]);
            } else {
                $stmtSalvar = $pdo->prepare(
                    "INSERT INTO autorizacoes_medicas
                        (
                            paciente_id,
                            medico_id,
                            autorizado_por,
                            pode_visualizar,
                            pode_editar,
                            status
                        )
                     VALUES
                        (
                            :paciente,
                            :profissional,
                            :autorizador,
                            1,
                            :pode_editar,
                            'ativa'
                        )"
                );

                $stmtSalvar->execute([
                    'paciente' => $pacienteId,
                    'profissional' => $profissionalId,
                    'autorizador' => $pacienteId,
                    'pode_editar' => $podeEditar,
                ]);
            }

            $pdo->commit();

            $sucesso = 'Autorização concedida com sucesso.';

        /*
         * Revogar uma autorização do próprio paciente.
         */
        } elseif ($acao === 'revogar') {
            $autorizacaoId = filter_var(
                $_POST['autorizacao_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$autorizacaoId || $autorizacaoId < 1) {
                throw new InvalidArgumentException(
                    'Autorização inválida.'
                );
            }

            $stmtRevogar = $pdo->prepare(
                "UPDATE autorizacoes_medicas
                 SET status = 'revogada',
                     pode_visualizar = 0,
                     pode_editar = 0
                 WHERE id = :id
                   AND paciente_id = :paciente
                   AND autorizado_por = :autorizador
                   AND status IN ('ativa', 'pendente')"
            );

            $stmtRevogar->execute([
                'id' => $autorizacaoId,
                'paciente' => $pacienteId,
                'autorizador' => $pacienteId,
            ]);

            if ($stmtRevogar->rowCount() < 1) {
                throw new InvalidArgumentException(
                    'A autorização não foi encontrada, '
                    . 'já foi revogada ou não pertence a você.'
                );
            }

            $sucesso = 'Autorização revogada com sucesso.';

        } else {
            throw new InvalidArgumentException(
                'Operação inválida.'
            );
        }

        /*
         * Impede o reenvio acidental do formulário ao atualizar.
         */
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header(
            'Location: autorizacoes_medicas.php?resultado='
            . rawurlencode($sucesso)
        );
        exit;

    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro = $e->getMessage();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        $erro = 'Não foi possível concluir a operação. '
            . 'Verifique o banco de dados e tente novamente.';
    }
}

/*
|--------------------------------------------------------------------------
| Mensagem após redirecionamento
|--------------------------------------------------------------------------
*/

if (isset($_GET['resultado']) && is_string($_GET['resultado'])) {
    $mensagemPermitida = 'Autorização concedida com sucesso.';
    $mensagemRevogada = 'Autorização revogada com sucesso.';

    if (in_array(
        $_GET['resultado'],
        [$mensagemPermitida, $mensagemRevogada],
        true
    )) {
        $sucesso = $_GET['resultado'];
    }
}

/*
|--------------------------------------------------------------------------
| Buscar profissionais disponíveis
|--------------------------------------------------------------------------
*/

try {
    $stmtProfissionais = $pdo->prepare(
        "SELECT id, nome_completo
         FROM usuarios
         WHERE tipo_usuario = 'profissional'
           AND ativo = 1
           AND medico_verificado = 1
           AND id <> :paciente
         ORDER BY nome_completo ASC"
    );

    $stmtProfissionais->execute([
        'paciente' => $pacienteId,
    ]);

    $profissionais = $stmtProfissionais->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);
    exit(
        'Não foi possível consultar os profissionais. '
        . 'Verifique a estrutura da tabela usuarios.'
    );
}

/*
|--------------------------------------------------------------------------
| Buscar autorizações deste paciente
|--------------------------------------------------------------------------
*/

try {
    $stmtAutorizacoes = $pdo->prepare(
        "SELECT
            a.id,
            a.medico_id,
            a.pode_visualizar,
            a.pode_editar,
            a.status,
            a.criado_em,
            u.nome_completo AS nome_profissional
         FROM autorizacoes_medicas a
         INNER JOIN usuarios u
            ON u.id = a.medico_id
         WHERE a.paciente_id = :paciente
         ORDER BY a.id DESC"
    );

    $stmtAutorizacoes->execute([
        'paciente' => $pacienteId,
    ]);

    $autorizacoes = $stmtAutorizacoes->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);
    exit('Não foi possível carregar suas autorizações médicas.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Autorizações Médicas - Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f2f7f8;
            color: #263238;
            font-family: Arial, Helvetica, sans-serif;
        }

        header {
            background: #087f8c;
            color: white;
            padding: 22px;
        }

        .cabecalho,
        main {
            width: min(1050px, calc(100% - 32px));
            margin: 0 auto;
        }

        .cabecalho {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .cabecalho h1 {
            margin: 0;
            font-size: 23px;
        }

        .voltar {
            padding: 10px 15px;
            border-radius: 8px;
            background: white;
            color: #087f8c;
            text-decoration: none;
            font-weight: bold;
        }

        main {
            padding: 28px 0 40px;
        }

        .painel {
            padding: 24px;
            margin-bottom: 22px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .05);
        }

        h2 {
            margin-top: 0;
            color: #087f8c;
            font-size: 21px;
        }

        .descricao,
        .nota {
            color: #5f6f75;
            line-height: 1.6;
        }

        .campo {
            margin: 20px 0;
        }

        .campo label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        select {
            width: 100%;
            min-height: 45px;
            padding: 10px;
            border: 1px solid #cbd5da;
            border-radius: 8px;
            background: white;
            font-size: 16px;
        }

        .permissao {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 17px 0;
            line-height: 1.5;
        }

        .permissao input {
            margin-top: 4px;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .botao {
            border: 0;
            border-radius: 8px;
            padding: 12px 18px;
            background: #087f8c;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .botao:hover {
            background: #066a75;
        }

        .botao-revogar {
            background: #b42318;
        }

        .botao-revogar:hover {
            background: #8f1c13;
        }

        .mensagem {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .sucesso {
            background: #e8f7ed;
            color: #176536;
        }

        .erro {
            background: #fff0f0;
            color: #a32020;
        }

        .aviso {
            padding: 14px;
            margin-top: 18px;
            border-radius: 8px;
            background: #fff5dd;
            color: #76540b;
            line-height: 1.6;
        }

        .lista {
            display: grid;
            gap: 15px;
        }

        .autorizacao {
            border: 1px solid #dce8eb;
            border-radius: 10px;
            padding: 18px;
        }

        .autorizacao h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }

        .detalhes {
            color: #5f6f75;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            background: #edf0f2;
            color: #3f4b50;
        }

        .status-ativa {
            background: #e8f7ed;
            color: #176536;
        }

        .status-pendente {
            background: #fff5dd;
            color: #76540b;
        }

        .status-revogada {
            background: #fff0f0;
            color: #a32020;
        }

        .vazio {
            padding: 20px;
            border: 1px dashed #cbd5da;
            border-radius: 10px;
            color: #5f6f75;
            line-height: 1.6;
        }

        footer {
            padding: 20px;
            text-align: center;
            color: #64757b;
        }

        @media (max-width: 600px) {
            .painel {
                padding: 18px;
            }

            .cabecalho h1 {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="cabecalho">
        <h1>Saúde-Conecta</h1>

        <a class="voltar" href="perfil_saude.php">
            Voltar ao perfil
        </a>
    </div>
</header>

<main>
    <section class="painel">
        <h2>Autorizar um profissional de saúde</h2>

        <p class="descricao">
            Escolha um profissional verificado para permitir o acesso
            aos seus dados médicos. Você pode revogar essa autorização
            posteriormente.
        </p>

        <?php if ($sucesso !== ''): ?>
            <div class="mensagem sucesso" role="status">
                <?= escaparAutorizacao($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div class="mensagem erro" role="alert">
                <?= escaparAutorizacao($erro) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($profissionais)): ?>
            <div class="vazio">
                Nenhum profissional verificado está disponível para
                autorização no momento. Os profissionais precisam estar
                cadastrados, ativos e com a verificação confirmada.
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escaparAutorizacao($tokenCsrf) ?>"
                >

                <input type="hidden" name="acao" value="autorizar">

                <div class="campo">
                    <label for="profissional_id">
                        Profissional de saúde
                    </label>

                    <select
                        id="profissional_id"
                        name="profissional_id"
                        required
                    >
                        <option value="">Selecione um profissional</option>

                        <?php foreach ($profissionais as $profissional): ?>
                            <option
                                value="<?= (int) $profissional['id'] ?>"
                            >
                                <?= escaparAutorizacao(
                                    $profissional['nome_completo']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="permissao">
                    <input
                        type="checkbox"
                        name="pode_editar"
                        value="1"
                    >

                    <span>
                        <strong>Permitir edição dos dados médicos</strong>
                        <br>
                        <span class="nota">
                            Se não marcar, o profissional poderá visualizar
                            os dados autorizados, mas não editá-los.
                        </span>
                    </span>
                </label>

                <button class="botao" type="submit">
                    Conceder autorização
                </button>
            </form>
        <?php endif; ?>

        <div class="aviso">
            <strong>Importante:</strong> conceda acesso apenas a
            profissionais cuja identidade e vínculo profissional tenham
            sido confirmados. A autorização desta página não substitui
            as verificações de permissão em cada página médica.
        </div>
    </section>

    <section class="painel">
        <h2>Minhas autorizações</h2>

        <p class="descricao">
            Consulte os acessos concedidos e revogue as permissões
            que não deseja mais manter.
        </p>

        <?php if (empty($autorizacoes)): ?>
            <div class="vazio">
                Você ainda não possui autorizações médicas registradas.
            </div>
        <?php else: ?>
            <div class="lista">
                <?php foreach ($autorizacoes as $autorizacao): ?>
                    <?php
                    $status = (string) $autorizacao['status'];

                    $classeStatus = match ($status) {
                        'ativa' => 'status-ativa',
                        'pendente' => 'status-pendente',
                        'revogada' => 'status-revogada',
                        default => '',
                    };

                    $nomeStatus = match ($status) {
                        'ativa' => 'Ativa',
                        'pendente' => 'Pendente',
                        'revogada' => 'Revogada',
                        default => 'Desconhecida',
                    };
                    ?>

                    <article class="autorizacao">
                        <h3>
                            <?= escaparAutorizacao(
                                $autorizacao['nome_profissional']
                            ) ?>
                        </h3>

                        <p>
                            <span class="status <?= $classeStatus ?>">
                                <?= escaparAutorizacao($nomeStatus) ?>
                            </span>
                        </p>

                        <div class="detalhes">
                            <div>
                                Visualização:
                                <strong>
                                    <?= (int) $autorizacao['pode_visualizar'] === 1
                                        && $status === 'ativa'
                                        ? 'Permitida'
                                        : 'Não permitida' ?>
                                </strong>
                            </div>

                            <div>
                                Edição:
                                <strong>
                                    <?= (int) $autorizacao['pode_editar'] === 1
                                        && $status === 'ativa'
                                        ? 'Permitida'
                                        : 'Não permitida' ?>
                                </strong>
                            </div>

                            <div>
                                Criada em:
                                <?= escaparAutorizacao(
                                    date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            (string) $autorizacao['criado_em']
                                        )
                                    )
                                ) ?>
                            </div>
                        </div>

                        <?php if (in_array(
                            $status,
                            ['ativa', 'pendente'],
                            true
                        ) && (int) $autorizacao['medico_id'] > 0): ?>
                            <form
                                method="POST"
                                action=""
                                onsubmit="return confirm(
                                    'Tem certeza de que deseja revogar esta autorização?'
                                );"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escaparAutorizacao($tokenCsrf) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="revogar"
                                >

                                <input
                                    type="hidden"
                                    name="autorizacao_id"
                                    value="<?= (int) $autorizacao['id'] ?>"
                                >

                                <button
                                    class="botao botao-revogar"
                                    type="submit"
                                >
                                    Revogar acesso
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    Saúde-Conecta — Controle de autorizações médicas
</footer>

</body>
</html>