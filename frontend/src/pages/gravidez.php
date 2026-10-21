<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../backend/config/seguranca.php';
require_once __DIR__ . '/../../../backend/config/database.php';

iniciarSessaoSegura();
$usuario = exigirLogin();

$usuarioId = is_array($usuario)
    ? (int) ($usuario['id'] ?? $usuario['usuario_id'] ?? $usuario['id_usuario'] ?? 0)
    : (int) ($usuario->id ?? $usuario->usuario_id ?? $usuario->id_usuario ?? 0);

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

function icone(string $nome): string
{
    $icones = [
        'saude' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/><path d="M12 8v7M8.5 11.5h7"/>',
        'voltar' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'calendario' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bebe' => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'mais' => '<path d="M12 5v14M5 12h14"/>',
        'editar' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'salvar' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
    ];

    $conteudo = $icones[$nome] ?? $icones['saude'];

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $conteudo . '</svg>';
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#087f8c">
<meta name="description" content="Acompanhe sua gravidez, consultas, exames e preparação para o bebê no Saúde-Conecta.">
<title>Acompanhar minha gravidez | Saúde-Conecta</title>

<style>
:root {
    --fundo: #f0f7fa;
    --texto: #18394e;
    --secundario: #5f7585;
    --verde: #087f8c;
    --verde-escuro: #066773;
    --verde-claro: #e6f6f4;
    --borda: #e0eaf0;
    --sombra: 0 8px 28px rgba(24, 57, 78, 0.06);
    --sombra-hover: 0 12px 32px rgba(24, 57, 78, 0.10);
    --raio: 16px;
    --raio-lg: 20px;
    --vermelho: #b42318;
}

* { box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
    min-height: 100vh;
    margin: 0;
    background: var(--fundo);
    color: var(--texto);
    font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
    line-height: 1.55;
    -webkit-font-smoothing: antialiased;
}

a { color: inherit; text-decoration: none; }

svg {
    display: block;
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 3px solid #38b6bd;
    outline-offset: 3px;
}

/* ========== HEADER ========== */
.topo {
    position: sticky;
    top: 0;
    z-index: 30;
    background: rgba(255, 255, 255, 0.97);
    border-bottom: 1px solid var(--borda);
    backdrop-filter: blur(16px);
}

.topo-conteudo {
    width: min(1100px, calc(100% - 40px));
    min-height: 64px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.marca {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -0.3px;
}

.marca-icone {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: linear-gradient(135deg, #087f8c, #1fa8ab);
    color: #fff;
    box-shadow: 0 4px 12px rgba(8, 127, 140, 0.25);
}

.marca-icone svg { width: 20px; height: 20px; }
.marca-texto span { color: var(--verde); }

.botao-voltar {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 14px;
    border: 1px solid var(--borda);
    border-radius: 10px;
    background: #fff;
    color: var(--texto);
    font-size: 13px;
    font-weight: 700;
    transition: border-color 0.15s, background 0.15s;
}

.botao-voltar:hover {
    border-color: #b5dedc;
    background: #f4fbfa;
}

.botao-voltar svg { width: 16px; height: 16px; }

/* ========== PÁGINA ========== */
.pagina {
    width: min(1100px, calc(100% - 40px));
    margin: 24px auto 56px;
}

/* ========== HERO ========== */
.hero {
    position: relative;
    overflow: hidden;
    border-radius: var(--raio-lg);
    background:
        radial-gradient(circle at 88% 18%, rgba(255,255,255,0.18) 0 60px, transparent 61px),
        linear-gradient(125deg, #065f73 0%, #087f8c 48%, #2cb5ae 100%);
    box-shadow: 0 12px 32px rgba(8, 127, 140, 0.18);
    color: #fff;
    padding: 28px 32px;
    margin-bottom: 22px;
}

.hero-etiqueta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border: 1px solid rgba(255,255,255,0.28);
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.hero-etiqueta svg { width: 14px; height: 14px; }

.hero h1 {
    margin: 12px 0 0;
    font-size: clamp(24px, 3vw, 30px);
    font-weight: 800;
    letter-spacing: -0.4px;
    line-height: 1.25;
}

.hero p {
    margin: 8px 0 0;
    max-width: 520px;
    color: rgba(255,255,255,0.88);
    font-size: 14.5px;
}

/* ========== CARDS ========== */
.card {
    background: #fff;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    padding: 22px 24px;
    margin-bottom: 18px;
    box-shadow: var(--sombra);
}

.card h2 {
    margin: 0 0 16px;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

.card h3 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 800;
}

/* ========== RESUMO ========== */
.grade-resumo {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.indicador {
    padding: 16px 14px;
    border: 1px solid #e8f0f4;
    border-radius: 12px;
    background: #f9fcfd;
    text-align: center;
}

.indicador .numero {
    display: block;
    color: var(--verde-escuro);
    font-size: 22px;
    font-weight: 800;
    overflow-wrap: anywhere;
    line-height: 1.2;
}

.indicador .rotulo {
    display: block;
    margin-top: 4px;
    color: var(--secundario);
    font-size: 12px;
    font-weight: 600;
}

.progresso-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--secundario);
}

.barra {
    height: 10px;
    background: #e4eef2;
    border-radius: 999px;
    overflow: hidden;
}

.barra-preenchimento {
    height: 100%;
    background: linear-gradient(90deg, var(--verde), #2cb5ae);
    border-radius: 999px;
    transition: width 0.3s ease;
}

.aviso-estimativa {
    margin: 12px 0 0;
    color: var(--secundario);
    font-size: 12.5px;
}

/* ========== FORMULÁRIOS ========== */
.form-grade {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.campo {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.campo-largo { grid-column: 1 / -1; }

.campo label {
    font-size: 13px;
    font-weight: 700;
    color: #385466;
}

.campo input,
.campo select,
.campo textarea {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #d5e3ea;
    border-radius: 10px;
    background: #fff;
    color: var(--texto);
    font: inherit;
    font-size: 14px;
    transition: border-color 0.15s;
}

.campo textarea {
    min-height: 100px;
    resize: vertical;
}

.campo input:focus,
.campo select:focus,
.campo textarea:focus {
    border-color: var(--verde);
    outline: none;
}

.campo small {
    color: var(--secundario);
    font-size: 11.5px;
}

/* ========== BOTÕES ========== */
.acoes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 9px 16px;
    border: 0;
    border-radius: 10px;
    background: var(--verde);
    color: #fff;
    font: inherit;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s;
}

.btn:hover { background: var(--verde-escuro); }
.btn svg { width: 16px; height: 16px; }

.btn-sec {
    background: #edf5f3;
    color: var(--verde-escuro);
}

.btn-sec:hover { background: #dceeea; }

.btn-editar {
    background: #e8efff;
    color: #294e9b;
}

.btn-editar:hover { background: #d9e4ff; }

.btn-excluir {
    background: #fff0ee;
    color: var(--vermelho);
}

.btn-excluir:hover { background: #ffe4e0; }

/* ========== MENSAGENS ========== */
.mensagem {
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 13.5px;
    border: 1px solid;
}

.mensagem.sucesso {
    background: #eef9f2;
    border-color: #b8e5c8;
    color: #246c43;
}

.mensagem.erro {
    background: #fff5f5;
    border-color: #efc8c8;
    color: #a13535;
}

/* ========== LISTA DE REGISTROS ========== */
.secao-categoria {
    margin-top: 22px;
}

.secao-categoria:first-child {
    margin-top: 0;
}

.lista {
    display: grid;
    gap: 10px;
}

.registro {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 18px;
    border: 1px solid var(--borda);
    border-radius: 12px;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.registro:hover {
    border-color: #c5e0de;
    box-shadow: 0 4px 14px rgba(24, 57, 78, 0.05);
}

.registro.concluido {
    background: #f7fbf9;
}

.registro.concluido .titulo {
    text-decoration: line-through;
    color: var(--secundario);
}

.tag {
    display: inline-block;
    padding: 3px 10px;
    margin-bottom: 6px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.tag.andamento {
    background: #e6f6f4;
    color: var(--verde-escuro);
}

.tag.concluido {
    background: #e8f5e9;
    color: #2e7d4b;
}

.titulo {
    font-weight: 800;
    font-size: 14.5px;
    line-height: 1.35;
}

.data-registro {
    margin-top: 4px;
    color: var(--secundario);
    font-size: 12.5px;
}

.descricao {
    margin: 8px 0 0;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    font-size: 13.5px;
    color: #3a5260;
    line-height: 1.5;
}

.registro-acoes {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    flex-shrink: 0;
}

.registro-acoes form { margin: 0; }

.registro-acoes .btn {
    min-height: 34px;
    padding: 6px 12px;
    font-size: 12px;
}

.vazio {
    padding: 28px 20px;
    text-align: center;
    border: 1px dashed #c5d8d4;
    border-radius: 12px;
    background: #f9fcfb;
    color: var(--secundario);
}

.vazio h3 {
    margin: 0 0 6px;
    font-size: 15px;
    color: var(--texto);
}

.vazio p {
    margin: 0;
    font-size: 13.5px;
}

/* ========== AVISO FINAL ========== */
.card-aviso {
    background: linear-gradient(115deg, #f0f9f7, #f5fbfd);
    border-color: #c5e6df;
}

.card-aviso p {
    margin: 0;
    font-size: 13.5px;
    color: #3a5260;
    line-height: 1.55;
}

/* ========== RODAPÉ ========== */
.rodape {
    padding: 22px 20px;
    border-top: 1px solid var(--borda);
    background: #fff;
    text-align: center;
}

.rodape p {
    margin: 0;
    color: var(--secundario);
    font-size: 12.5px;
}

.rodape strong {
    color: var(--verde);
}

/* ========== RESPONSIVO ========== */
@media (max-width: 720px) {
    .topo-conteudo {
        width: calc(100% - 28px);
    }

    .pagina {
        width: calc(100% - 28px);
        margin-top: 16px;
    }

    .hero {
        padding: 24px 20px;
    }

    .hero h1 {
        font-size: 24px;
    }

    .grade-resumo {
        grid-template-columns: 1fr;
    }

    .form-grade {
        grid-template-columns: 1fr;
    }

    .campo-largo {
        grid-column: auto;
    }

    .registro {
        flex-direction: column;
    }

    .registro-acoes {
        width: 100%;
    }

    .card {
        padding: 18px 16px;
    }
}

@media (max-width: 420px) {
    .marca {
        font-size: 15px;
    }

    .marca-icone {
        width: 34px;
        height: 34px;
    }

    .botao-voltar {
        padding: 8px 11px;
        font-size: 12px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
</head>

<body>
<header class="topo">
    <div class="topo-conteudo">
        <a class="marca" href="perfil_saude.php" aria-label="Saúde-Conecta, início">
            <span class="marca-icone"><?= icone('saude') ?></span>
            <span class="marca-texto">Saúde<span>-Conecta</span></span>
        </a>

        <a class="botao-voltar" href="perfil_saude.php">
            <?= icone('voltar') ?> Voltar ao perfil
        </a>
    </div>
</header>

<main class="pagina">

    <section class="hero" aria-label="Apresentação">
        <span class="hero-etiqueta">
            <?= icone('bebe') ?> Acompanhamento gestacional
        </span>
        <h1>Acompanhar minha gravidez</h1>
        <p>Organize consultas, exames, tarefas, preparação para o parto e cuidados com o bebê.</p>
    </section>

    <?php if ($erro !== ''): ?>
        <div class="mensagem erro" role="alert"><?= h($erro) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['salvo'])): ?>
        <div class="mensagem sucesso" role="status">Alterações salvas com sucesso!</div>
    <?php endif; ?>

    <!-- RESUMO -->
    <section class="card">
        <h2>Resumo da gravidez</h2>

        <div class="grade-resumo">
            <div class="indicador">
                <span class="numero">
                    <?= $semanaGestacional !== null ? h((string)$semanaGestacional) . ' sem.' : '—' ?>
                </span>
                <span class="rotulo">Semana estimada</span>
            </div>

            <div class="indicador">
                <span class="numero"><?= h($dataPrevistaParto ?? '—') ?></span>
                <span class="rotulo">Data provável do parto</span>
            </div>

            <div class="indicador">
                <span class="numero"><?= $concluidos ?>/<?= $total ?></span>
                <span class="rotulo">Registros concluídos</span>
            </div>
        </div>

        <div class="progresso-info">
            <span>Progresso geral</span>
            <span><?= $progresso ?>%</span>
        </div>
        <div class="barra">
            <div class="barra-preenchimento" style="width: <?= $progresso ?>%"></div>
        </div>

        <p class="aviso-estimativa">
            As estimativas são baseadas na data da última menstruação e não substituem a avaliação pré-natal.
        </p>
    </section>

    <!-- CONFIGURAÇÕES -->
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
                    <small>Usada para estimar a semana gestacional e a data provável do parto.</small>
                </div>

                <div class="campo campo-largo">
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
                <button class="btn" type="submit">
                    <?= icone('salvar') ?> Salvar configurações
                </button>
            </div>
        </form>
    </section>

    <!-- FORMULÁRIO DE REGISTRO -->
    <section class="card" id="formulario-registro">
        <h2><?= $registroEdicao ? 'Editar registro' : 'Adicionar consulta, exame ou tarefa' ?></h2>

        <?php if ($registroEdicao): ?>
            <p style="margin: -8px 0 16px; color: var(--secundario); font-size: 13.5px;">
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

                <div class="campo campo-largo">
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
                    <?= $registroEdicao
                        ? icone('salvar') . ' Salvar edição'
                        : icone('mais') . ' Adicionar registro' ?>
                </button>

                <?php if ($registroEdicao): ?>
                    <a class="btn btn-sec" href="gravidez.php">Cancelar edição</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- MEUS REGISTROS -->
    <section class="card">
        <h2>Meus registros</h2>

        <?php if (!$registros): ?>
            <div class="vazio">
                <h3>Nenhum registro cadastrado</h3>
                <p>Adicione sua primeira consulta, tarefa ou anotação acima.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($categorias as $chave => $nomeCategoria): ?>
            <?php $itens = $porCategoria[$chave] ?? []; ?>

            <?php if ($itens): ?>
                <div class="secao-categoria">
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
                                    <span class="tag <?= $feito ? 'concluido' : 'andamento' ?>">
                                        <?= $feito ? 'Concluído' : 'Em andamento' ?>
                                    </span>

                                    <div class="titulo"><?= h($r['titulo']) ?></div>

                                    <?php if ($dataR): ?>
                                        <div class="data-registro">
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
                                    >
                                        <?= icone('editar') ?> Editar
                                    </a>

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
            <div class="secao-categoria">
                <h3>Outros registros</h3>

                <div class="lista">
                    <?php foreach ($outros as $r): ?>
                        <?php
                        $feito = (int)$r['concluido'] === 1;
                        $desc = $r['descricao'] ?: $r['detalhes'];
                        ?>

                        <article class="registro <?= $feito ? 'concluido' : '' ?>">
                            <div>
                                <span class="tag <?= $feito ? 'concluido' : 'andamento' ?>">
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

    <!-- AVISO -->
    <section class="card card-aviso">
        <h2>Cuidados importantes</h2>
        <p>
            Esta página serve para organizar informações e compromissos.
            Ela não substitui o acompanhamento pré-natal nem a orientação
            de profissionais de saúde.
        </p>
    </section>

</main>

<footer class="rodape">
    <p><strong>Saúde-Conecta</strong> — acompanhamento da gravidez</p>
</footer>

<script>
document.querySelectorAll('#data_ultima_menstruacao, #data_ref').forEach(function (campo) {
    campo.addEventListener('input', function () {
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