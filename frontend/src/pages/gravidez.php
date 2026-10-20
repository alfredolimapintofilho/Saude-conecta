<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../backend/config/seguranca.php';
require_once __DIR__ . '/../../../backend/config/database.php';

iniciarSessaoSegura();
$usuario = exigirLogin();

$usuarioId = is_array($usuario)
    ? (int) ($usuario['id'] ?? $usuario['usuario_id'] ?? 0)
    : (int) ($usuario->id ?? $usuario->usuario_id ?? 0);

if ($usuarioId <= 0) {
    http_response_code(403);
    exit('Usuário não identificado.');
}

$pdo = Database::getConnection();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_gravidez'])) {
    $_SESSION['csrf_gravidez'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_gravidez'];
$erro = '';

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function dataParaBanco(string $data): ?string
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    $d = DateTime::createFromFormat('!d/m/Y', $data);
    $erros = DateTime::getLastErrors();

    if (
        !$d ||
        ($erros !== false &&
            ($erros['warning_count'] > 0 || $erros['error_count'] > 0)) ||
        $d->format('d/m/Y') !== $data
    ) {
        return null;
    }

    return $d->format('Y-m-d');
}

function dataParaTela(?string $data): string
{
    if (!$data) {
        return '';
    }

    try {
        return (new DateTime($data))->format('d/m/Y');
    } catch (Throwable $e) {
        return '';
    }
}

function redirecionar(): void
{
    header('Location: gravidez.php?salvo=1');
    exit;
}

function validarToken(string $csrf): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($csrf, $token)) {
        http_response_code(403);
        exit('Token de segurança inválido. Atualize a página.');
    }
}

$categorias = [
    'consulta'      => 'Consultas',
    'exame'         => 'Exames',
    'tarefa'        => 'Tarefas',
    'diario'        => 'Diário da gravidez',
    'plano'         => 'Plano de parto',
    'bolsa_mae'     => 'Bolsa da maternidade - mãe',
    'bolsa_bebe'    => 'Bolsa da maternidade - bebê',
    'casa'          => 'Preparação da casa',
    'recem_nascido' => 'Cuidados com o recém-nascido',
    'vacina'        => 'Vacinação do bebê',
    'contato'       => 'Contatos importantes',
    'duvida'        => 'Dúvidas para a equipe médica'
];

/* Configurações da gravidez. */
$stmt = $pdo->prepare("
    SELECT esta_gravida, ultima_menstruacao,
           data_ultima_menstruacao, observacoes
    FROM gravidez_configuracoes
    WHERE usuario_id = ?
    LIMIT 1
");
$stmt->execute([$usuarioId]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    $stmt = $pdo->prepare("
        INSERT INTO gravidez_configuracoes
            (usuario_id, esta_gravida, ultima_menstruacao,
             data_ultima_menstruacao, observacoes)
        VALUES (?, 0, NULL, NULL, NULL)
    ");
    $stmt->execute([$usuarioId]);

    $config = [
        'esta_gravida' => 0,
        'ultima_menstruacao' => null,
        'data_ultima_menstruacao' => null,
        'observacoes' => ''
    ];
}

/* Ações: salvar configurações, adicionar, editar, concluir e excluir. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validarToken($csrf);

        $acao = (string)($_POST['acao'] ?? '');

        if ($acao === 'salvar_configuracao') {
            $digitada = trim((string)($_POST['data_ultima_menstruacao'] ?? ''));
            $data = dataParaBanco($digitada);

            if ($digitada !== '' && $data === null) {
                throw new RuntimeException('Digite uma data válida no formato DD/MM/AAAA.');
            }

            if ($data !== null && $data > date('Y-m-d')) {
                throw new RuntimeException('A data não pode estar no futuro.');
            }

            $observacoes = trim((string)($_POST['observacoes'] ?? ''));

            $stmt = $pdo->prepare("
                UPDATE gravidez_configuracoes
                SET ultima_menstruacao = ?,
                    data_ultima_menstruacao = ?,
                    observacoes = ?
                WHERE usuario_id = ?
            ");
            $stmt->execute([
                $data,
                $data,
                $observacoes !== '' ? $observacoes : null,
                $usuarioId
            ]);

            redirecionar();
        }

        if ($acao === 'adicionar' || $acao === 'atualizar') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $categoria = (string)($_POST['categoria'] ?? 'tarefa');
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $dataDigitada = trim((string)($_POST['data_ref'] ?? ''));
            $data = dataParaBanco($dataDigitada);

            if ($dataDigitada !== '' && $data === null) {
                throw new RuntimeException('Digite a data no formato DD/MM/AAAA.');
            }

            if (!isset($categorias[$categoria])) {
                throw new RuntimeException('Selecione uma categoria válida.');
            }

            if ($titulo === '' || mb_strlen($titulo) > 150) {
                throw new RuntimeException('Informe um título de até 150 caracteres.');
            }

            if ($acao === 'adicionar') {
                $stmt = $pdo->prepare("
                    INSERT INTO gravidez_registros (
                        usuario_id, tipo, titulo, data_registro,
                        proxima_data, detalhes, categoria,
                        descricao, data_ref, concluido
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");

                $stmt->execute([
                    $usuarioId,
                    $categoria,
                    $titulo,
                    $data ?? date('Y-m-d'),
                    $data,
                    $descricao !== '' ? $descricao : null,
                    $categoria,
                    $descricao !== '' ? $descricao : null,
                    $data
                ]);
            } else {
                if (!$id || $id <= 0) {
                    throw new RuntimeException('Registro inválido para edição.');
                }

                $stmt = $pdo->prepare("
                    UPDATE gravidez_registros
                    SET tipo = ?,
                        titulo = ?,
                        data_registro = ?,
                        proxima_data = ?,
                        detalhes = ?,
                        categoria = ?,
                        descricao = ?,
                        data_ref = ?
                    WHERE id = ? AND usuario_id = ?
                ");

                $stmt->execute([
                    $categoria,
                    $titulo,
                    $data ?? date('Y-m-d'),
                    $data,
                    $descricao !== '' ? $descricao : null,
                    $categoria,
                    $descricao !== '' ? $descricao : null,
                    $data,
                    $id,
                    $usuarioId
                ]);
            }

            redirecionar();
        }

        if ($acao === 'alternar' || $acao === 'excluir') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {
                throw new RuntimeException('Registro inválido.');
            }

            if ($acao === 'alternar') {
                $stmt = $pdo->prepare("
                    UPDATE gravidez_registros
                    SET concluido = IF(concluido = 1, 0, 1)
                    WHERE id = ? AND usuario_id = ?
                ");
            } else {
                $stmt = $pdo->prepare("
                    DELETE FROM gravidez_registros
                    WHERE id = ? AND usuario_id = ?
                ");
            }

            $stmt->execute([$id, $usuarioId]);
            redirecionar();
        }

        throw new RuntimeException('Ação não reconhecida.');

    } catch (Throwable $e) {
        $erro = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Não foi possível concluir a operação. Verifique o banco de dados.';

        if (!($e instanceof RuntimeException)) {
            error_log('Erro em gravidez.php: ' . $e->getMessage());
        }
    }
}

/* Registro escolhido para editar: só pode ser do usuário conectado. */
$registroEdicao = null;
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);

if ($editarId && $editarId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, categoria, tipo, titulo, descricao,
               detalhes, data_ref, data_registro, proxima_data
        FROM gravidez_registros
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$editarId, $usuarioId]);
    $registroEdicao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$registroEdicao && $erro === '') {
        $erro = 'O registro solicitado não foi encontrado.';
    }
}

/* Recarrega configurações. */
$stmt = $pdo->prepare("
    SELECT esta_gravida, ultima_menstruacao,
           data_ultima_menstruacao, observacoes
    FROM gravidez_configuracoes
    WHERE usuario_id = ?
    LIMIT 1
");
$stmt->execute([$usuarioId]);
$config = $stmt->fetch(PDO::FETCH_ASSOC) ?: $config;

$dataBanco = $config['data_ultima_menstruacao']
    ?: $config['ultima_menstruacao']
    ?: null;

$semanaGestacional = null;
$dataPrevistaParto = null;

if ($dataBanco) {
    try {
        $inicio = new DateTime($dataBanco);
        $hoje = new DateTime('today');

        if ($inicio <= $hoje) {
            $dias = (int)$inicio->diff($hoje)->days;
            $semanaGestacional = intdiv($dias, 7);
            $dataPrevistaParto = (clone $inicio)
                ->modify('+280 days')
                ->format('d/m/Y');
        }
    } catch (Throwable $e) {
        // Ignora datas inválidas para fins de estimativa.
    }
}

/* Lista os registros do usuário. */
$stmt = $pdo->prepare("
    SELECT id, categoria, tipo, titulo, descricao, detalhes,
           data_ref, data_registro, proxima_data, concluido
    FROM gravidez_registros
    WHERE usuario_id = ?
    ORDER BY concluido ASC,
             COALESCE(data_ref, data_registro) ASC,
             id DESC
");
$stmt->execute([$usuarioId]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($registros);
$concluidos = 0;
$porCategoria = [];

foreach ($registros as $r) {
    if ((int)$r['concluido'] === 1) {
        $concluidos++;
    }

    $cat = $r['categoria'] ?: $r['tipo'];
    $porCategoria[$cat][] = $r;
}

$progresso = $total ? (int)round(($concluidos / $total) * 100) : 0;

$valorEdicaoCategoria = $registroEdicao
    ? ($registroEdicao['categoria'] ?: $registroEdicao['tipo'])
    : 'tarefa';

$valorEdicaoTitulo = $registroEdicao['titulo'] ?? '';
$valorEdicaoDescricao = $registroEdicao
    ? ($registroEdicao['descricao'] ?: $registroEdicao['detalhes'])
    : '';

$valorEdicaoData = $registroEdicao
    ? ($registroEdicao['data_ref']
        ?: $registroEdicao['proxima_data']
        ?: $registroEdicao['data_registro'])
    : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acompanhar minha gravidez | Saúde-Conecta</title>

<style>
:root {
    --verde: #16866d;
    --verde-escuro: #106c58;
    --fundo: #f4f8f7;
    --borda: #dce8e4;
    --texto: #253b37;
    --suave: #667873;
    --vermelho: #b42318;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    background: var(--fundo);
    color: var(--texto);
    font-family: Arial, Helvetica, sans-serif;
    line-height: 1.6;
}

.topo {
    background: white;
    border-bottom: 1px solid var(--borda);
    padding: 15px 20px;
}

.topo-interno {
    max-width: 1100px;
    margin: auto;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
}

.marca {
    color: var(--verde-escuro);
    font-weight: 800;
    font-size: 21px;
    text-decoration: none;
}

a { color: var(--verde-escuro); }

.container {
    max-width: 1100px;
    padding: 25px 18px 40px;
    margin: auto;
}

.hero {
    background: linear-gradient(135deg, #e5f5ef, #fff);
    border: 1px solid #cce8dc;
    border-radius: 18px;
    padding: 25px;
    margin-bottom: 20px;
}

.hero h1 { margin: 0 0 8px; }
.hero p { margin: 0; color: var(--suave); }

.card {
    background: white;
    border: 1px solid var(--borda);
    border-radius: 15px;
    padding: 21px;
    margin-bottom: 19px;
    box-shadow: 0 4px 15px #163c3110;
}

.card h2 { margin-top: 0; font-size: 21px; }

.grade {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 13px;
}

.indicador {
    border: 1px solid var(--borda);
    border-radius: 12px;
    padding: 15px;
}

.numero {
    display: block;
    color: var(--verde-escuro);
    font-size: 25px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.suave { color: var(--suave); font-size: 14px; }

.form-grade {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.campo {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.largo { grid-column: 1 / -1; }

label { font-weight: bold; font-size: 14px; }

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbdad5;
    border-radius: 9px;
    font: inherit;
    background: white;
    color: var(--texto);
}

textarea { min-height: 90px; resize: vertical; }

input:focus, select:focus, textarea:focus {
    outline: 2px solid #a4d9c7;
    border-color: var(--verde);
}

.botoes, .acoes {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.acoes { margin-top: 14px; }

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    border: 0;
    border-radius: 8px;
    padding: 9px 13px;
    background: var(--verde);
    color: white;
    font: inherit;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

.btn:hover { background: var(--verde-escuro); }

.btn-sec {
    background: #edf5f1;
    color: var(--verde-escuro);
}

.btn-editar {
    background: #e9efff;
    color: #294e9b;
}

.btn-excluir {
    background: #fff0ee;
    color: var(--vermelho);
}

.mensagem {
    padding: 13px 15px;
    border-radius: 9px;
    margin-bottom: 17px;
    background: #e5f5ef;
    color: var(--verde-escuro);
}

.mensagem.erro {
    background: #fff0ee;
    color: var(--vermelho);
}

.barra {
    height: 10px;
    background: #e7eeeb;
    border-radius: 20px;
    overflow: hidden;
}

.barra div {
    height: 100%;
    background: var(--verde);
}

.secao { margin-top: 24px; }

.lista {
    display: grid;
    gap: 11px;
}

.registro {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    border: 1px solid var(--borda);
    border-radius: 12px;
    padding: 15px;
    background: white;
}

.registro.concluido { background: #f6faf8; }

.registro.concluido .titulo {
    text-decoration: line-through;
    color: var(--suave);
}

.tag {
    display: inline-block;
    padding: 3px 9px;
    margin-bottom: 6px;
    background: #e5f5ef;
    color: var(--verde-escuro);
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.titulo { font-weight: 800; }

.descricao {
    margin: 7px 0 0;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.registro-acoes {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.registro-acoes form { margin: 0; }

.vazio {
    padding: 20px;
    text-align: center;
    border: 1px dashed var(--borda);
    border-radius: 12px;
    color: var(--suave);
}

footer {
    text-align: center;
    padding: 20px;
    color: var(--suave);
}

@media (max-width: 720px) {
    .grade, .form-grade { grid-template-columns: 1fr; }
    .largo { grid-column: auto; }
    .registro { flex-direction: column; }
    .topo-interno { align-items: flex-start; flex-direction: column; }
    .registro-acoes { width: 100%; }
}
</style>
</head>

<body>
<header class="topo">
    <div class="topo-interno">
        <a class="marca" href="perfil_saude.php">Saúde-Conecta</a>
        <a href="perfil_saude.php">← Voltar ao perfil de saúde</a>
    </div>
</header>

<main class="container">

<section class="hero">
    <h1>Acompanhar minha gravidez</h1>
    <p>Organize consultas, exames, tarefas, preparação para o parto e cuidados com o bebê.</p>
</section>

<?php if ($erro !== ''): ?>
    <div class="mensagem erro"><?= h($erro) ?></div>
<?php endif; ?>

<?php if (isset($_GET['salvo'])): ?>
    <div class="mensagem">Alterações salvas com sucesso!</div>
<?php endif; ?>

<section class="card">
    <h2>Resumo da gravidez</h2>

    <div class="grade">
        <div class="indicador">
            <span class="numero">
                <?= $semanaGestacional !== null ? h($semanaGestacional) . ' sem.' : '—' ?>
            </span>
            <span class="suave">Semana estimada</span>
        </div>

        <div class="indicador">
            <span class="numero"><?= h($dataPrevistaParto ?? '—') ?></span>
            <span class="suave">Data provável do parto</span>
        </div>

        <div class="indicador">
            <span class="numero"><?= $concluidos ?>/<?= $total ?></span>
            <span class="suave">Registros concluídos</span>
        </div>
    </div>

    <p class="suave">Progresso: <?= $progresso ?>%</p>
    <div class="barra"><div style="width: <?= $progresso ?>%"></div></div>
    <p class="suave">As estimativas não substituem a avaliação pré-natal.</p>
</section>

<section class="card">
    <h2>Configurações da gravidez</h2>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="acao" value="salvar_configuracao">

        <div class="form-grade">
            <div class="campo">
                <label for="data_ultima_menstruacao">Data da última menstruação</label>
                <input
                    type="text"
                    id="data_ultima_menstruacao"
                    name="data_ultima_menstruacao"
                    value="<?= h(dataParaTela($dataBanco)) ?>"
                    placeholder="DD/MM/AAAA"
                    maxlength="10"
                    inputmode="numeric"
                    autocomplete="off"
                >
            </div>

            <div class="campo largo">
                <label for="observacoes">Observações</label>
                <textarea
                    id="observacoes"
                    name="observacoes"
                    maxlength="5000"
                    placeholder="Anotações para conversar com a equipe de saúde."
                ><?= h($config['observacoes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="acoes">
            <button class="btn" type="submit">Salvar configurações</button>
        </div>
    </form>
</section>

<section class="card" id="formulario-registro">
    <h2><?= $registroEdicao ? 'Editar registro' : 'Adicionar consulta, exame ou tarefa' ?></h2>

    <?php if ($registroEdicao): ?>
        <p class="suave">
            Você está editando: <strong><?= h($registroEdicao['titulo']) ?></strong>
        </p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input
            type="hidden"
            name="acao"
            value="<?= $registroEdicao ? 'atualizar' : 'adicionar' ?>"
        >

        <?php if ($registroEdicao): ?>
            <input type="hidden" name="id" value="<?= (int)$registroEdicao['id'] ?>">
        <?php endif; ?>

        <div class="form-grade">
            <div class="campo">
                <label for="categoria">Categoria</label>
                <select id="categoria" name="categoria" required>
                    <?php foreach ($categorias as $valor => $nome): ?>
                        <option
                            value="<?= h($valor) ?>"
                            <?= $valorEdicaoCategoria === $valor ? 'selected' : '' ?>
                        >
                            <?= h($nome) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="titulo">Título</label>
                <input
                    type="text"
                    id="titulo"
                    name="titulo"
                    maxlength="150"
                    value="<?= h($valorEdicaoTitulo) ?>"
                    placeholder="Ex.: Consulta de pré-natal"
                    required
                >
            </div>

            <div class="campo">
                <label for="data_ref">Data (opcional)</label>
                <input
                    type="text"
                    id="data_ref"
                    name="data_ref"
                    value="<?= h(dataParaTela($valorEdicaoData)) ?>"
                    placeholder="DD/MM/AAAA"
                    maxlength="10"
                    inputmode="numeric"
                    autocomplete="off"
                >
            </div>

            <div class="campo largo">
                <label for="descricao">Descrição</label>
                <textarea
                    id="descricao"
                    name="descricao"
                    maxlength="10000"
                    placeholder="Detalhes, observações ou itens que deseja preparar."
                ><?= h($valorEdicaoDescricao) ?></textarea>
            </div>
        </div>

        <div class="acoes">
            <button class="btn" type="submit">
                <?= $registroEdicao ? 'Salvar edição' : '+ Adicionar registro' ?>
            </button>

            <?php if ($registroEdicao): ?>
                <a class="btn btn-sec" href="gravidez.php">Cancelar edição</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2>Meus registros</h2>

    <?php if (!$registros): ?>
        <div class="vazio">
            <h3>Nenhum registro cadastrado</h3>
            <p>Adicione sua primeira consulta, tarefa ou anotação.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($categorias as $chave => $nomeCategoria): ?>
        <?php $itens = $porCategoria[$chave] ?? []; ?>

        <?php if ($itens): ?>
            <div class="secao">
                <h3><?= h($nomeCategoria) ?></h3>

                <div class="lista">
                    <?php foreach ($itens as $r): ?>
                        <?php
                        $feito = (int)$r['concluido'] === 1;
                        $desc = $r['descricao'] ?: $r['detalhes'];
                        $dataR = $r['data_ref']
                            ?: $r['proxima_data']
                            ?: $r['data_registro'];
                        ?>

                        <article class="registro <?= $feito ? 'concluido' : '' ?>">
                            <div>
                                <span class="tag">
                                    <?= $feito ? 'Concluído' : 'Em andamento' ?>
                                </span>

                                <div class="titulo"><?= h($r['titulo']) ?></div>

                                <?php if ($dataR): ?>
                                    <div class="suave">
                                        Data: <?= h(dataParaTela($dataR)) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($desc): ?>
                                    <p class="descricao"><?= h($desc) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="registro-acoes">
                                <a
                                    class="btn btn-editar"
                                    href="gravidez.php?editar=<?= (int)$r['id'] ?>#formulario-registro"
                                >Editar</a>

                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="acao" value="alternar">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                                    <button class="btn btn-sec" type="submit">
                                        <?= $feito ? 'Reabrir' : 'Concluir' ?>
                                    </button>
                                </form>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Tem certeza de que deseja excluir este registro? Esta ação não pode ser desfeita.');"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                                    <button class="btn btn-excluir" type="submit">
                                        Excluir
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    $outros = [];

    foreach ($registros as $r) {
        $cat = $r['categoria'] ?: $r['tipo'];

        if (!isset($categorias[$cat])) {
            $outros[] = $r;
        }
    }
    ?>

    <?php if ($outros): ?>
        <div class="secao">
            <h3>Outros registros</h3>

            <div class="lista">
                <?php foreach ($outros as $r): ?>
                    <?php
                    $feito = (int)$r['concluido'] === 1;
                    $desc = $r['descricao'] ?: $r['detalhes'];
                    ?>

                    <article class="registro <?= $feito ? 'concluido' : '' ?>">
                        <div>
                            <span class="tag">
                                <?= h($r['categoria'] ?: $r['tipo']) ?>
                            </span>
                            <div class="titulo"><?= h($r['titulo']) ?></div>
                            <?php if ($desc): ?>
                                <p class="descricao"><?= h($desc) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="registro-acoes">
                            <a class="btn btn-editar"
                               href="gravidez.php?editar=<?= (int)$r['id'] ?>#formulario-registro">
                                Editar
                            </a>

                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="acao" value="alternar">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sec" type="submit">
                                    <?= $feito ? 'Reabrir' : 'Concluir' ?>
                                </button>
                            </form>

                            <form method="post"
                                  onsubmit="return confirm('Deseja excluir este registro?');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-excluir" type="submit">Excluir</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Cuidados importantes</h2>
    <p>
        Esta página serve para organizar informações e compromissos.
        Ela não substitui o acompanhamento pré-natal nem a orientação
        de profissionais de saúde.
    </p>
</section>

</main>

<footer>Saúde-Conecta — acompanhamento da gravidez.</footer>

<script>
document.querySelectorAll(
    '#data_ultima_menstruacao, #data_ref'
).forEach(function(campo) {
    campo.addEventListener('input', function() {
        const numeros = campo.value.replace(/\D/g, '').slice(0, 8);

        if (numeros.length > 4) {
            campo.value = numeros.slice(0, 2) + '/' +
                numeros.slice(2, 4) + '/' + numeros.slice(4);
        } else if (numeros.length > 2) {
            campo.value = numeros.slice(0, 2) + '/' + numeros.slice(2);
        } else {
            campo.value = numeros;
        }
    });
});
</script>

</body>
</html>