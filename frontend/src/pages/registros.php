<?php
/*
 * Saúde-Conecta — Registros médicos (CRUD)
 * Salvar em: frontend/src/pages/registros.php
 */

require_once __DIR__ . '/../../../backend/config/seguranca.php';
require_once __DIR__ . '/../../../backend/config/database.php';

iniciarSessaoSegura();
$usuario = exigirLogin();
$pdo = Database::getConnection();

if (is_object($usuario)) {
    $usuario = (array) $usuario;
}

function h($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function obterIdUsuario(array $usuario): int
{
    foreach (['id', 'id_usuario', 'usuario_id'] as $campo) {
        if (isset($usuario[$campo]) && is_numeric($usuario[$campo])) {
            return (int) $usuario[$campo];
        }
    }
    return 0;
}

function nomeUsuario(array $usuario): string
{
    foreach (['nomeCompleto', 'nome_completo', 'nome', 'name'] as $campo) {
        if (!empty($usuario[$campo])) {
            return (string) $usuario[$campo];
        }
    }
    return 'Usuário';
}

$idUsuario = obterIdUsuario($usuario);
$nome = nomeUsuario($usuario);

if ($idUsuario <= 0) {
    http_response_code(401);
    exit('Sessão inválida. Faça login novamente.');
}

/* =========================================================
   CATEGORIAS E ESTRUTURA DAS TABELAS
========================================================= */

$categorias = [
    'vacina' => [
        'titulo' => 'Cartão de vacinação',
        'descricao' => 'Gerencie seus registros de vacinação.',
        'tabela' => 'vacinacoes',
        'campos' => [
            'nome_vacina' => ['label' => 'Nome da vacina', 'tipo' => 'text', 'obrigatorio' => true],
            'data_aplicacao' => ['label' => 'Data de aplicação', 'tipo' => 'date', 'obrigatorio' => true],
            'dose' => ['label' => 'Dose', 'tipo' => 'text', 'obrigatorio' => false, 'placeholder' => 'Ex.: 1ª dose, reforço'],
            'local' => ['label' => 'Local de aplicação', 'tipo' => 'text', 'obrigatorio' => false],
            'lote' => ['label' => 'Lote', 'tipo' => 'text', 'obrigatorio' => false],
            'observacoes' => ['label' => 'Observações', 'tipo' => 'textarea', 'obrigatorio' => false],
        ],
    ],
    'alergia' => [
        'titulo' => 'Alergias',
        'descricao' => 'Gerencie as alergias registradas no seu perfil.',
        'tabela' => 'alergias',
        'campos' => [
            'substancia' => ['label' => 'Substância / agente', 'tipo' => 'text', 'obrigatorio' => true],
            'tipo_reacao' => ['label' => 'Tipo de reação', 'tipo' => 'text', 'obrigatorio' => false, 'placeholder' => 'Ex.: cutânea, respiratória'],
            'gravidade' => ['label' => 'Gravidade', 'tipo' => 'select', 'obrigatorio' => false, 'opcoes' => ['', 'Leve', 'Moderada', 'Grave']],
            'observacoes' => ['label' => 'Observações', 'tipo' => 'textarea', 'obrigatorio' => false],
        ],
    ],
    'alergia_vacina' => [
        'titulo' => 'Reações a vacinas',
        'descricao' => 'Gerencie reações ou alergias relacionadas a vacinas.',
        'tabela' => 'reacoes_vacinas',
        'campos' => [
            'nome_vacina' => ['label' => 'Vacina', 'tipo' => 'text', 'obrigatorio' => true],
            'data_reacao' => ['label' => 'Data da reação', 'tipo' => 'date', 'obrigatorio' => false],
            'sintomas' => ['label' => 'Sintomas', 'tipo' => 'textarea', 'obrigatorio' => true],
            'gravidade' => ['label' => 'Gravidade', 'tipo' => 'select', 'obrigatorio' => false, 'opcoes' => ['', 'Leve', 'Moderada', 'Grave']],
            'observacoes' => ['label' => 'Observações', 'tipo' => 'textarea', 'obrigatorio' => false],
        ],
    ],
    'doenca' => [
        'titulo' => 'Histórico de doenças',
        'descricao' => 'Gerencie as doenças registradas no seu histórico.',
        'tabela' => 'historico_doencas',
        'campos' => [
            'nome_doenca' => ['label' => 'Nome da doença / condição', 'tipo' => 'text', 'obrigatorio' => true],
            'data_diagnostico' => ['label' => 'Data do diagnóstico', 'tipo' => 'date', 'obrigatorio' => false],
            'status' => ['label' => 'Status', 'tipo' => 'select', 'obrigatorio' => false, 'opcoes' => ['', 'Ativa', 'Em tratamento', 'Resolvida', 'Crônica']],
            'observacoes' => ['label' => 'Observações', 'tipo' => 'textarea', 'obrigatorio' => false],
        ],
    ],
    'atestado' => [
        'titulo' => 'Atestados médicos',
        'descricao' => 'Gerencie os atestados registrados na sua conta.',
        'tabela' => 'atestados',
        'campos' => [
            'tipo_atestado' => ['label' => 'Tipo de atestado', 'tipo' => 'text', 'obrigatorio' => true, 'placeholder' => 'Ex.: médico, odontológico'],
            'data_emissao' => ['label' => 'Data de emissão', 'tipo' => 'date', 'obrigatorio' => true],
            'dias_afastamento' => ['label' => 'Dias de afastamento', 'tipo' => 'number', 'obrigatorio' => false],
            'profissional' => ['label' => 'Profissional / CRM', 'tipo' => 'text', 'obrigatorio' => false],
            'observacoes' => ['label' => 'Observações', 'tipo' => 'textarea', 'obrigatorio' => false],
        ],
    ],
];

$tipo = isset($_GET['tipo']) ? (string) $_GET['tipo'] : 'vacina';
if (!isset($categorias[$tipo])) {
    $tipo = 'vacina';
}
$categoria = $categorias[$tipo];
$tabela = $categoria['tabela'];
$camposDef = $categoria['campos'];

$acao = isset($_GET['acao']) ? (string) $_GET['acao'] : 'listar';
$idEdicao = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$mensagem = '';
$tipoMensagem = '';
$registroEdicao = null;
$registros = [];

/* Token CSRF */
$token = $_SESSION['csrf_registros'] ?? '';
if (!is_string($token) || strlen($token) < 32) {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_registros'] = $token;
}

/* =========================================================
   CRIA TABELAS SE NÃO EXISTIREM
========================================================= */

try {
    $defs = [
        'vacinacoes' => "
            CREATE TABLE IF NOT EXISTS vacinacoes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                nome_vacina VARCHAR(180) NOT NULL,
                data_aplicacao DATE NULL,
                dose VARCHAR(80) NULL,
                local VARCHAR(180) NULL,
                lote VARCHAR(80) NULL,
                observacoes TEXT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_vac_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'alergias' => "
            CREATE TABLE IF NOT EXISTS alergias (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                substancia VARCHAR(180) NOT NULL,
                tipo_reacao VARCHAR(120) NULL,
                gravidade VARCHAR(40) NULL,
                observacoes TEXT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_alerg_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'reacoes_vacinas' => "
            CREATE TABLE IF NOT EXISTS reacoes_vacinas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                nome_vacina VARCHAR(180) NOT NULL,
                data_reacao DATE NULL,
                sintomas TEXT NOT NULL,
                gravidade VARCHAR(40) NULL,
                observacoes TEXT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_reac_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'historico_doencas' => "
            CREATE TABLE IF NOT EXISTS historico_doencas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                nome_doenca VARCHAR(180) NOT NULL,
                data_diagnostico DATE NULL,
                status VARCHAR(40) NULL,
                observacoes TEXT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_doenca_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'atestados' => "
            CREATE TABLE IF NOT EXISTS atestados (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                tipo_atestado VARCHAR(120) NOT NULL,
                data_emissao DATE NULL,
                dias_afastamento INT NULL,
                profissional VARCHAR(180) NULL,
                observacoes TEXT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_atest_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
    ];

    if (isset($defs[$tabela])) {
        $pdo->exec($defs[$tabela]);
    }

    /* =========================================================
       PROCESSAR POST (adicionar / editar / excluir)
    ========================================================= */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!is_string($tokenEnviado) || !hash_equals($token, $tokenEnviado)) {
            throw new RuntimeException('Sessão do formulário expirou. Atualize a página e tente novamente.');
        }

        $acaoPost = $_POST['acao_form'] ?? '';

        // EXCLUIR
        if ($acaoPost === 'excluir') {
            $idExcluir = (int) ($_POST['id'] ?? 0);
            if ($idExcluir <= 0) {
                throw new RuntimeException('Registro inválido para exclusão.');
            }
            $stmt = $pdo->prepare("DELETE FROM `{$tabela}` WHERE id = :id AND usuario_id = :uid LIMIT 1");
            $stmt->execute(['id' => $idExcluir, 'uid' => $idUsuario]);
            $mensagem = 'Registro excluído com sucesso.';
            $tipoMensagem = 'sucesso';
            $acao = 'listar';
        }

        // ADICIONAR OU EDITAR
        if ($acaoPost === 'salvar') {
            $idSalvar = (int) ($_POST['id'] ?? 0);
            $dadosForm = [];

            foreach ($camposDef as $campo => $meta) {
                $valor = trim((string) ($_POST[$campo] ?? ''));

                if (!empty($meta['obrigatorio']) && $valor === '') {
                    throw new RuntimeException('Preencha o campo obrigatório: ' . $meta['label']);
                }

                if (($meta['tipo'] ?? '') === 'date' && $valor !== '') {
                    $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
                    if (!$obj || $obj->format('Y-m-d') !== $valor) {
                        throw new RuntimeException('Data inválida em: ' . $meta['label']);
                    }
                }

                if (($meta['tipo'] ?? '') === 'number' && $valor !== '') {
                    if (!is_numeric($valor)) {
                        throw new RuntimeException('Valor numérico inválido em: ' . $meta['label']);
                    }
                    $valor = (int) $valor;
                }

                $dadosForm[$campo] = $valor === '' ? null : $valor;
            }

            if ($idSalvar > 0) {
                $sets = [];
                $params = ['id' => $idSalvar, 'uid' => $idUsuario];
                foreach ($dadosForm as $c => $v) {
                    $sets[] = "`{$c}` = :{$c}";
                    $params[$c] = $v;
                }
                $sql = "UPDATE `{$tabela}` SET " . implode(', ', $sets) . " WHERE id = :id AND usuario_id = :uid LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $mensagem = 'Registro atualizado com sucesso.';
            } else {
                $cols = array_keys($dadosForm);
                $cols[] = 'usuario_id';
                $placeholders = array_map(function ($c) { return ':' . $c; }, $cols);
                $params = $dadosForm;
                $params['usuario_id'] = $idUsuario;

                $sql = "INSERT INTO `{$tabela}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $mensagem = 'Registro adicionado com sucesso.';
            }

            $tipoMensagem = 'sucesso';
            $acao = 'listar';
            $idEdicao = 0;

            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_registros'] = $token;
        }
    }

    /* Carregar registro para edição */
    if ($acao === 'editar' && $idEdicao > 0) {
        $stmt = $pdo->prepare("SELECT * FROM `{$tabela}` WHERE id = :id AND usuario_id = :uid LIMIT 1");
        $stmt->execute(['id' => $idEdicao, 'uid' => $idUsuario]);
        $registroEdicao = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registroEdicao) {
            $mensagem = 'Registro não encontrado.';
            $tipoMensagem = 'erro';
            $acao = 'listar';
        }
    }

    if ($acao === 'adicionar') {
        $registroEdicao = null;
    }

    /* Listar registros do usuário */
    $stmt = $pdo->prepare("SELECT * FROM `{$tabela}` WHERE usuario_id = :uid ORDER BY id DESC LIMIT 200");
    $stmt->execute(['uid' => $idUsuario]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
} catch (Throwable $e) {
    error_log('registros.php: ' . $e->getMessage());
    $mensagem = 'Erro ao processar. Verifique a conexão com o banco de dados.';
    $tipoMensagem = 'erro';
}

$mostrarFormulario = in_array($acao, ['adicionar', 'editar'], true);
$urlPerfil = 'perfil_saude.php';
$urlLista = 'registros.php?tipo=' . urlencode($tipo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($categoria['titulo']) ?> | Saúde-Conecta</title>
    <style>
        :root {
            --verde: #087f68;
            --verde-escuro: #075d4f;
            --fundo: #f3f7f6;
            --texto: #20312d;
            --borda: #dce7e3;
            --branco: #fff;
            --secundario: #60716b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--fundo);
            color: var(--texto);
            font-family: Arial, Helvetica, sans-serif;
        }
        header {
            background: var(--branco);
            border-bottom: 1px solid var(--borda);
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .marca {
            color: var(--verde);
            font-size: 22px;
            font-weight: 800;
            text-decoration: none;
        }
        .usuario { color: #53655f; font-size: 14px; }
        main { width: min(1100px, 92%); margin: 32px auto; }
        .voltar {
            display: inline-block;
            color: var(--verde);
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .painel {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 16px;
            padding: 26px;
            box-shadow: 0 5px 20px rgba(20, 55, 45, .04);
            margin-bottom: 20px;
        }
        h1 { margin: 0 0 10px; font-size: clamp(24px, 4vw, 32px); }
        .descricao { color: var(--secundario); line-height: 1.6; margin-bottom: 20px; }
        .aviso {
            background: #f1f8f5;
            border: 1px solid #cfe7dd;
            padding: 14px 16px;
            border-radius: 10px;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        .aviso.erro { background: #fff5f2; border-color: #f1d2c8; }
        .aviso.sucesso { background: #effaf3; border-color: #bfe8cf; color: #246c43; }
        .tabela-container { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
        th, td {
            padding: 12px;
            border-bottom: 1px solid var(--borda);
            text-align: left;
            vertical-align: top;
        }
        th { background: #edf6f2; color: var(--verde-escuro); }
        .acoes-linha { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 9px;
            background: var(--verde);
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }
        .btn.secundario { background: #e8f2ee; color: var(--verde-escuro); }
        .btn.perigo { background: #c0392b; }
        .btn.pequeno { padding: 7px 11px; font-size: 12px; }
        .acoes { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 22px; }
        .campo { margin-bottom: 16px; }
        .campo label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 13px;
            color: #385466;
        }
        .campo input, .campo select, .campo textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #dce7ed;
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
        }
        .campo textarea { min-height: 90px; resize: vertical; }
        .obrigatorio { color: #c0392b; }
        .grade-campos {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 18px;
        }
        .campo-largo { grid-column: 1 / -1; }
        @media (max-width: 640px) {
            .grade-campos { grid-template-columns: 1fr; }
            .campo-largo { grid-column: auto; }
            main { width: 94%; margin: 20px auto; }
            .painel { padding: 18px; }
        }
        footer {
            text-align: center;
            padding: 28px 12px;
            color: #708079;
            font-size: 13px;
        }
        .vazio {
            text-align: center;
            padding: 28px 12px;
            color: var(--secundario);
        }
    </style>
</head>
<body>
<header>
    <a class="marca" href="<?= h($urlPerfil) ?>">Saúde-Conecta</a>
    <div class="usuario">Olá, <?= h($nome) ?></div>
</header>

<main>
    <a class="voltar" href="<?= h($urlPerfil) ?>">&#8592; Voltar ao perfil de saúde</a>

    <section class="painel">
        <h1><?= h($categoria['titulo']) ?></h1>
        <p class="descricao"><?= h($categoria['descricao']) ?></p>

        <?php if ($mensagem !== ''): ?>
            <div class="aviso <?= h($tipoMensagem) ?>" role="status"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($mostrarFormulario): ?>
            <h2 style="margin:0 0 16px;font-size:18px;">
                <?= $acao === 'editar' ? 'Editar registro' : 'Adicionar registro' ?>
            </h2>

            <form method="post" action="<?= h($urlLista) ?>">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="acao_form" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($registroEdicao['id'] ?? 0) ?>">

                <div class="grade-campos">
                    <?php foreach ($camposDef as $campo => $meta):
                        $valorAtual = $registroEdicao[$campo] ?? '';
                        $largo = ($meta['tipo'] ?? '') === 'textarea';
                    ?>
                        <div class="campo <?= $largo ? 'campo-largo' : '' ?>">
                            <label for="<?= h($campo) ?>">
                                <?= h($meta['label']) ?>
                                <?php if (!empty($meta['obrigatorio'])): ?>
                                    <span class="obrigatorio">*</span>
                                <?php endif; ?>
                            </label>

                            <?php if (($meta['tipo'] ?? 'text') === 'textarea'): ?>
                                <textarea id="<?= h($campo) ?>" name="<?= h($campo) ?>"
                                    <?= !empty($meta['obrigatorio']) ? 'required' : '' ?>
                                    placeholder="<?= h($meta['placeholder'] ?? '') ?>"><?= h((string) $valorAtual) ?></textarea>
                            <?php elseif (($meta['tipo'] ?? '') === 'select'): ?>
                                <select id="<?= h($campo) ?>" name="<?= h($campo) ?>">
                                    <?php foreach (($meta['opcoes'] ?? ['']) as $op): ?>
                                        <option value="<?= h($op) ?>" <?= (string) $valorAtual === (string) $op ? 'selected' : '' ?>>
                                            <?= $op === '' ? 'Selecione' : h($op) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input
                                    type="<?= h($meta['tipo'] ?? 'text') ?>"
                                    id="<?= h($campo) ?>"
                                    name="<?= h($campo) ?>"
                                    value="<?= h((string) $valorAtual) ?>"
                                    <?= !empty($meta['obrigatorio']) ? 'required' : '' ?>
                                    placeholder="<?= h($meta['placeholder'] ?? '') ?>"
                                    <?= ($meta['tipo'] ?? '') === 'date' ? 'max="' . h(date('Y-m-d')) . '"' : '' ?>
                                >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="acoes">
                    <button class="btn" type="submit">Salvar</button>
                    <a class="btn secundario" href="<?= h($urlLista) ?>">Cancelar</a>
                </div>
            </form>

        <?php else: ?>

            <div class="acoes" style="margin-top:0;margin-bottom:18px;">
                <a class="btn" href="<?= h($urlLista . '&acao=adicionar') ?>">+ Adicionar</a>
            </div>

            <?php if (empty($registros)): ?>
                <div class="vazio">
                    Nenhum registro encontrado nesta categoria.<br>
                    Clique em <strong>Adicionar</strong> para incluir o primeiro.
                </div>
            <?php else: ?>
                <div class="tabela-container">
                    <table>
                        <thead>
                        <tr>
                            <?php foreach ($camposDef as $campo => $meta): ?>
                                <th><?= h($meta['label']) ?></th>
                            <?php endforeach; ?>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($registros as $reg): ?>
                            <tr>
                                <?php foreach ($camposDef as $campo => $meta): ?>
                                    <td>
                                        <?php
                                        $v = $reg[$campo] ?? '';
                                        if ($v === null || $v === '') {
                                            echo '—';
                                        } else {
                                            echo nl2br(h((string) $v));
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <div class="acoes-linha">
                                        <a class="btn secundario pequeno"
                                           href="<?= h($urlLista . '&acao=editar&id=' . (int) $reg['id']) ?>">
                                            Editar
                                        </a>
                                        <form method="post" action="<?= h($urlLista) ?>"
                                              style="display:inline;margin:0;"
                                              onsubmit="return confirm('Deseja realmente excluir este registro?');">
                                            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                            <input type="hidden" name="acao_form" value="excluir">
                                            <input type="hidden" name="id" value="<?= (int) $reg['id'] ?>">
                                            <button class="btn perigo pequeno" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="acoes">
                <a class="btn" href="<?= h($urlPerfil) ?>">Meu perfil de saúde</a>
                <a class="btn secundario" href="registros.php?tipo=vacina">Vacinas</a>
                <a class="btn secundario" href="registros.php?tipo=alergia">Alergias</a>
                <a class="btn secundario" href="registros.php?tipo=doenca">Doenças</a>
                <a class="btn secundario" href="registros.php?tipo=atestado">Atestados</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    Saúde-Conecta — seus registros de saúde em um só lugar.
</footer>
</body>
</html>