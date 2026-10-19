<?php
/*
 * Saúde-Conecta — Autorizações médicas
 * Salvar em: frontend/src/pages/autorizacoes_medicas.php
 */

require_once __DIR__ . '/../../../backend/config/seguranca.php';
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/config/saude_seguranca.php';

iniciarSessaoSegura();
$usuario = exigirLogin();

if (is_object($usuario)) {
    $usuario = (array) $usuario;
}

$usuarioId = 0;
foreach (['id', 'id_usuario', 'usuario_id'] as $campo) {
    if (isset($usuario[$campo]) && is_numeric($usuario[$campo])) {
        $usuarioId = (int) $usuario[$campo];
        break;
    }
}

$nomeUsuario = 'Usuário';
foreach (['nomeCompleto', 'nome_completo', 'nome', 'name'] as $campo) {
    if (!empty($usuario[$campo])) {
        $nomeUsuario = (string) $usuario[$campo];
        break;
    }
}

if ($usuarioId <= 0) {
    http_response_code(401);
    exit('Sessão inválida. Faça login novamente.');
}

$pdo = Database::getConnection();
saudePrepararSeguranca($pdo);

$csrfChave = 'csrf_autorizacoes';
$csrf = saudeNovoCsrf($csrfChave);

$mensagem = '';
$tipoMensagem = '';
$editando = null;
$autorizacoes = [];

$campos = [
    'titulo' => '',
    'tipo' => '',
    'data_emissao' => '',
    'data_validade' => '',
    'profissional' => '',
    'instituicao' => '',
    'descricao' => '',
    'observacoes' => '',
];

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS autorizacoes_medicas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            tipo VARCHAR(80) NULL,
            data_emissao DATE NULL,
            data_validade DATE NULL,
            profissional VARCHAR(180) NULL,
            instituicao VARCHAR(180) NULL,
            descricao TEXT NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aut_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string) ($_POST['csrf_token'] ?? '');

        if (!saudeValidarCsrf($csrf, $token)) {
            throw new RuntimeException(
                'Token de segurança inválido. Atualize a página e tente novamente.'
            );
        }

        $acao = (string) ($_POST['acao'] ?? '');
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) {
            throw new RuntimeException('Identificador inválido.');
        }

        if ($acao === 'excluir') {
            if ($id <= 0) {
                throw new RuntimeException('Registro inválido.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM autorizacoes_medicas
                WHERE id = :id AND usuario_id = :uid
            ");
            $stmt->execute(['id' => $id, 'uid' => $usuarioId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Registro não encontrado ou sem autorização.'
                );
            }

            saudeAuditar($pdo, $usuarioId, 'autorizacao', $id, 'excluir');
            $mensagem = 'Autorização excluída com sucesso.';
            $tipoMensagem = 'sucesso';

        } elseif ($acao === 'salvar') {
            foreach ($campos as $campo => $_v) {
                $campos[$campo] = trim((string) ($_POST[$campo] ?? ''));
            }

            if ($campos['titulo'] === '' || mb_strlen($campos['titulo']) > 180) {
                throw new RuntimeException(
                    'Informe o título da autorização (até 180 caracteres).'
                );
            }

            if ($campos['data_emissao'] !== '' && !saudeDataValida($campos['data_emissao'])) {
                throw new RuntimeException('Data de emissão inválida.');
            }

            if ($campos['data_validade'] !== '' && !saudeDataValida($campos['data_validade'])) {
                throw new RuntimeException('Data de validade inválida.');
            }

            foreach (['descricao', 'observacoes'] as $campoTexto) {
                if (mb_strlen($campos[$campoTexto]) > 10000) {
                    throw new RuntimeException(
                        'Um campo de texto ultrapassou o limite de 10.000 caracteres.'
                    );
                }
            }

            $dados = [
                'titulo' => $campos['titulo'],
                'tipo' => $campos['tipo'] !== '' ? $campos['tipo'] : null,
                'data_emissao' => $campos['data_emissao'] !== '' ? $campos['data_emissao'] : null,
                'data_validade' => $campos['data_validade'] !== '' ? $campos['data_validade'] : null,
                'profissional' => $campos['profissional'] !== '' ? $campos['profissional'] : null,
                'instituicao' => $campos['instituicao'] !== '' ? $campos['instituicao'] : null,
                'descricao' => $campos['descricao'] !== '' ? $campos['descricao'] : null,
                'observacoes' => $campos['observacoes'] !== '' ? $campos['observacoes'] : null,
            ];

            if ($id > 0) {
                $verifica = $pdo->prepare("
                    SELECT id FROM autorizacoes_medicas
                    WHERE id = :id AND usuario_id = :uid LIMIT 1
                ");
                $verifica->execute(['id' => $id, 'uid' => $usuarioId]);

                if (!$verifica->fetchColumn()) {
                    throw new RuntimeException(
                        'Registro não encontrado ou sem autorização.'
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE autorizacoes_medicas SET
                        titulo = :titulo,
                        tipo = :tipo,
                        data_emissao = :data_emissao,
                        data_validade = :data_validade,
                        profissional = :profissional,
                        instituicao = :instituicao,
                        descricao = :descricao,
                        observacoes = :observacoes
                    WHERE id = :id AND usuario_id = :uid
                ");
                $stmt->execute($dados + ['id' => $id, 'uid' => $usuarioId]);

                saudeAuditar($pdo, $usuarioId, 'autorizacao', $id, 'editar');
                $mensagem = 'Autorização atualizada com sucesso.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO autorizacoes_medicas (
                        usuario_id, titulo, tipo, data_emissao, data_validade,
                        profissional, instituicao, descricao, observacoes
                    ) VALUES (
                        :uid, :titulo, :tipo, :data_emissao, :data_validade,
                        :profissional, :instituicao, :descricao, :observacoes
                    )
                ");
                $stmt->execute($dados + ['uid' => $usuarioId]);
                $novoId = (int) $pdo->lastInsertId();

                saudeAuditar($pdo, $usuarioId, 'autorizacao', $novoId, 'criar');
                $mensagem = 'Autorização salva com sucesso.';
            }

            $tipoMensagem = 'sucesso';
            $campos = array_fill_keys(array_keys($campos), '');
        } else {
            throw new RuntimeException('Ação inválida.');
        }

        $_SESSION[$csrfChave] = bin2hex(random_bytes(32));
        $csrf = $_SESSION[$csrfChave];
    }

    if (isset($_GET['editar'])) {
        $editarId = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
        if (!$editarId || $editarId <= 0) {
            throw new RuntimeException('Identificador inválido.');
        }

        $stmt = $pdo->prepare("
            SELECT * FROM autorizacoes_medicas
            WHERE id = :id AND usuario_id = :uid LIMIT 1
        ");
        $stmt->execute(['id' => $editarId, 'uid' => $usuarioId]);
        $editando = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editando) {
            throw new RuntimeException(
                'Registro não encontrado ou sem autorização.'
            );
        }

        foreach ($campos as $campo => $_v) {
            $campos[$campo] = (string) ($editando[$campo] ?? '');
        }
    }

    $stmt = $pdo->prepare("
        SELECT id, titulo, tipo, data_emissao, data_validade, profissional
        FROM autorizacoes_medicas
        WHERE usuario_id = :uid
        ORDER BY id DESC
    ");
    $stmt->execute(['uid' => $usuarioId]);
    $autorizacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
} catch (Throwable $e) {
    error_log('autorizacoes_medicas.php: ' . $e->getMessage());
    $mensagem = 'Não foi possível concluir a operação. Verifique o banco de dados.';
    $tipoMensagem = 'erro';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Autorizações Médicas | Saúde-Conecta</title>
<style>
:root {
    --cor: #087f8c;
    --fundo: #f1f6fa;
    --texto: #18394e;
    --borda: #e3ebf1;
    --cinza: #718493;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: var(--fundo);
    color: var(--texto);
    font: 15px/1.55 Arial, sans-serif;
}
a { text-decoration: none; color: inherit; }
.topo {
    background: #fff;
    border-bottom: 1px solid var(--borda);
    padding: 16px max(18px, calc((100% - 1100px) / 2));
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.marca, .voltar { color: var(--cor); font-weight: 700; }
main {
    max-width: 1100px;
    width: calc(100% - 30px);
    margin: 26px auto 50px;
}
.capa {
    background: linear-gradient(120deg, #075b78, #087f8c, #54c8bd);
    padding: 28px;
    border-radius: 18px;
    color: #fff;
}
.capa h1 { margin: 7px 0; font-size: 32px; }
.capa p { margin: 0; }
.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 18px;
}
.painel {
    background: #fff;
    border: 1px solid var(--borda);
    border-radius: 15px;
    padding: 21px;
    min-width: 0;
}
.painel h2 { font-size: 19px; margin: 0 0 6px; }
.sub { color: var(--cinza); font-size: 13px; margin: 0 0 18px; }
.campo { margin-bottom: 13px; }
label { display: block; font-weight: 700; font-size: 12px; margin-bottom: 5px; }
input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #d7e2e9;
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    background: #fff;
    color: var(--texto);
}
textarea { min-height: 80px; resize: vertical; }
.duas {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.btn {
    display: inline-block;
    border: 0;
    border-radius: 8px;
    background: var(--cor);
    color: #fff;
    padding: 10px 14px;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
}
.sec { background: #eaf6f6; color: var(--cor); }
.perigo { background: #fff0f0; color: #a13535; }
.acoes {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 10px;
}
.item {
    padding: 14px;
    border: 1px solid var(--borda);
    border-radius: 11px;
    background: #fbfdfe;
    margin-bottom: 10px;
}
.item h3 { margin: 0; font-size: 16px; }
.item p {
    font-size: 12px;
    color: var(--cinza);
    margin: 5px 0;
    overflow-wrap: anywhere;
}
.aviso {
    padding: 12px;
    border-radius: 9px;
    margin: 16px 0;
    font-size: 13px;
}
.sucesso { background: #effaf3; color: #246c43; }
.erro { background: #fff0f0; color: #a13535; }
.vazio {
    padding: 18px;
    border: 1px dashed var(--borda);
    border-radius: 10px;
    color: var(--cinza);
    font-size: 13px;
}
footer {
    text-align: center;
    color: var(--cinza);
    font-size: 12px;
    padding: 22px;
}
@media (max-width: 720px) {
    .grid { grid-template-columns: 1fr; }
    .capa { padding: 22px; }
}
@media (max-width: 400px) {
    .duas { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<header class="topo">
    <a class="marca" href="perfil_saude.php">Saúde-Conecta</a>
    <a class="voltar" href="perfil_saude.php">← Voltar ao Perfil de Saúde</a>
</header>

<main>
    <section class="capa">
        <small>DOCUMENTOS E AUTORIZAÇÕES</small>
        <h1>Autorizações Médicas</h1>
        <p>Registre autorizações, liberações e documentos médicos vinculados à sua conta.</p>
    </section>

    <p>
        Olá, <strong><?= saudeH($nomeUsuario) ?></strong>.
        Os registros ficam vinculados à sua conta autenticada.
    </p>

    <?php if ($mensagem !== ''): ?>
        <div class="aviso <?= saudeH($tipoMensagem) ?>" role="status">
            <?= saudeH($mensagem) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <section class="painel">
            <h2><?= $editando ? 'Editar autorização' : 'Nova autorização' ?></h2>
            <p class="sub">Preencha as informações do documento.</p>

            <form method="post" action="autorizacoes_medicas.php">
                <input type="hidden" name="csrf_token" value="<?= saudeH($csrf) ?>">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">

                <div class="campo">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" maxlength="180" required
                           value="<?= saudeH($campos['titulo']) ?>"
                           placeholder="Ex.: Autorização para procedimento">
                </div>

                <div class="duas">
                    <div class="campo">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo">
                            <?php
                            $tipos = [
                                '' => 'Não informado',
                                'Procedimento' => 'Procedimento',
                                'Exame' => 'Exame',
                                'Cirurgia' => 'Cirurgia',
                                'Internação' => 'Internação',
                                'Outro' => 'Outro',
                            ];
                            foreach ($tipos as $v => $r): ?>
                                <option value="<?= saudeH($v) ?>"
                                    <?= $campos['tipo'] === $v ? 'selected' : '' ?>>
                                    <?= saudeH($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="profissional">Profissional / CRM</label>
                        <input type="text" id="profissional" name="profissional" maxlength="180"
                               value="<?= saudeH($campos['profissional']) ?>">
                    </div>
                    <div class="campo">
                        <label for="data_emissao">Data de emissão</label>
                        <input type="date" id="data_emissao" name="data_emissao"
                               max="<?= date('Y-m-d') ?>"
                               value="<?= saudeH($campos['data_emissao']) ?>">
                    </div>
                    <div class="campo">
                        <label for="data_validade">Validade</label>
                        <input type="date" id="data_validade" name="data_validade"
                               value="<?= saudeH($campos['data_validade']) ?>">
                    </div>
                </div>

                <div class="campo">
                    <label for="instituicao">Instituição / local</label>
                    <input type="text" id="instituicao" name="instituicao" maxlength="180"
                           value="<?= saudeH($campos['instituicao']) ?>">
                </div>
                <div class="campo">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" maxlength="10000"><?= saudeH($campos['descricao']) ?></textarea>
                </div>
                <div class="campo">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" maxlength="10000"><?= saudeH($campos['observacoes']) ?></textarea>
                </div>

                <div class="acoes">
                    <button class="btn" type="submit">
                        <?= $editando ? 'Salvar alterações' : 'Salvar autorização' ?>
                    </button>
                    <?php if ($editando): ?>
                        <a class="btn sec" href="autorizacoes_medicas.php">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="painel">
            <h2>Autorizações cadastradas</h2>
            <p class="sub">Somente os registros da sua conta são exibidos.</p>

            <?php if (empty($autorizacoes)): ?>
                <div class="vazio">Nenhuma autorização cadastrada ainda.</div>
            <?php else: ?>
                <?php foreach ($autorizacoes as $item): ?>
                    <article class="item">
                        <h3><?= saudeH($item['titulo']) ?></h3>
                        <p>Tipo: <?= saudeH($item['tipo'] ?: 'Não informado') ?></p>
                        <p>
                            Emissão: <?= saudeH($item['data_emissao'] ?: '—') ?>
                            ·
                            Validade: <?= saudeH($item['data_validade'] ?: '—') ?>
                        </p>
                        <p>Profissional: <?= saudeH($item['profissional'] ?: 'Não informado') ?></p>
                        <div class="acoes">
                            <a class="btn sec" href="autorizacoes_medicas.php?editar=<?= (int) $item['id'] ?>">Editar</a>
                            <form method="post" action="autorizacoes_medicas.php"
                                  onsubmit="return confirm('Excluir esta autorização?');">
                                <input type="hidden" name="csrf_token" value="<?= saudeH($csrf) ?>">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button class="btn perigo" type="submit">Excluir</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<footer>
    Saúde-Conecta · Documentos informativos; não substituem originais oficiais.
</footer>
</body>
</html>