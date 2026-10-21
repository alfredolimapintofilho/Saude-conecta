<?php
/*
 * Saúde-Conecta — Registros médicos (CRUD)
 * Salvar em: frontend/src/pages/registros.php
 * Datas digitadas/exibidas como DD/MM/AAAA; armazenadas no MySQL como AAAA-MM-DD.
 */

declare(strict_types=1);

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

/** Aceita DD/MM/AAAA (preferencial) ou AAAA-MM-DD e retorna AAAA-MM-DD. */
function converterDataParaBanco(string $data): ?string
{
    $data = trim($data);
    if ($data === '') {
        return null;
    }

    foreach (['!d/m/Y', '!Y-m-d'] as $formato) {
        $dataObj = DateTimeImmutable::createFromFormat($formato, $data);
        $erros = DateTimeImmutable::getLastErrors();
        $semErros = $erros === false || (
            $erros['warning_count'] === 0 && $erros['error_count'] === 0
        );
        $formatoComparacao = substr($formato, 1);

        if ($dataObj !== false && $semErros && $dataObj->format($formatoComparacao) === $data) {
            return $dataObj->format('Y-m-d');
        }
    }

    throw new RuntimeException('Data inválida. Digite uma data real no formato DD/MM/AAAA.');
}

/** Exibe datas armazenadas no banco como DD/MM/AAAA. */
function formatarDataBrasileira($data): string
{
    if ($data === null || trim((string) $data) === '') {
        return '';
    }

    $data = trim((string) $data);
    $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    $erros = DateTimeImmutable::getLastErrors();
    $semErros = $erros === false || (
        $erros['warning_count'] === 0 && $erros['error_count'] === 0
    );

    if ($obj !== false && $semErros && $obj->format('Y-m-d') === $data) {
        return $obj->format('d/m/Y');
    }
    return $data;
}

function icone(string $nome): string
{
    $icones = [
        'saude' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/><path d="M12 8v7M8.5 11.5h7"/>',
        'voltar' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'mais' => '<path d="M12 5v14M5 12h14"/>',
        'editar' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'salvar' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'lixeira' => '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'vacina' => '<path d="m18 2 4 4M7 17 3 21M14 4l6 6M5 13l6 6M3 21l7-7M9 7l8 8"/>',
        'alergia' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="M12 8v5M12 16h.01"/>',
        'doenca' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="M12 8v8M8 12h8"/>',
        'atestado' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
    ];

    $conteudo = $icones[$nome] ?? $icones['saude'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $conteudo . '</svg>';
}

$idUsuario = obterIdUsuario($usuario);
$nome = nomeUsuario($usuario);

if ($idUsuario <= 0) {
    http_response_code(401);
    exit('Sessão inválida. Faça login novamente.');
}

$categorias = [
    'vacina' => [
        'titulo' => 'Cartão de vacinação',
        'descricao' => 'Gerencie seus registros de vacinação.',
        'tabela' => 'vacinacoes',
        'icone' => 'vacina',
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
        'icone' => 'alergia',
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
        'icone' => 'alergia',
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
        'icone' => 'doenca',
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
        'icone' => 'atestado',
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

$token = $_SESSION['csrf_registros'] ?? '';
if (!is_string($token) || strlen($token) < 32) {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_registros'] = $token;
}

try {
    $defs = [
        'vacinacoes' => "CREATE TABLE IF NOT EXISTS vacinacoes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            nome_vacina VARCHAR(180) NOT NULL,
            data_aplicacao DATE NULL,
            dose VARCHAR(80) NULL,
            local VARCHAR(180) NULL,
            lote VARCHAR(80) NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_vac_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'alergias' => "CREATE TABLE IF NOT EXISTS alergias (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            substancia VARCHAR(180) NOT NULL,
            tipo_reacao VARCHAR(120) NULL,
            gravidade VARCHAR(40) NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_alerg_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'reacoes_vacinas' => "CREATE TABLE IF NOT EXISTS reacoes_vacinas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            nome_vacina VARCHAR(180) NOT NULL,
            data_reacao DATE NULL,
            sintomas TEXT NOT NULL,
            gravidade VARCHAR(40) NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_reac_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'historico_doencas' => "CREATE TABLE IF NOT EXISTS historico_doencas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            nome_doenca VARCHAR(180) NOT NULL,
            data_diagnostico DATE NULL,
            status VARCHAR(40) NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_doenca_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'atestados' => "CREATE TABLE IF NOT EXISTS atestados (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            tipo_atestado VARCHAR(120) NOT NULL,
            data_emissao DATE NULL,
            dias_afastamento INT NULL,
            profissional VARCHAR(180) NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_atest_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    $pdo->exec($defs[$tabela]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!is_string($tokenEnviado) || !hash_equals($token, $tokenEnviado)) {
            throw new RuntimeException('Sessão do formulário expirou. Atualize a página e tente novamente.');
        }

        $acaoPost = $_POST['acao_form'] ?? '';
        if ($acaoPost === 'excluir') {
            $idExcluir = (int) ($_POST['id'] ?? 0);
            if ($idExcluir <= 0) {
                throw new RuntimeException('Registro inválido para exclusão.');
            }
            $stmt = $pdo->prepare("DELETE FROM `{$tabela}` WHERE id = :id AND usuario_id = :uid LIMIT 1");
            $stmt->execute(['id' => $idExcluir, 'uid' => $idUsuario]);
            $mensagem = $stmt->rowCount() ? 'Registro excluído com sucesso.' : 'Registro não encontrado ou já excluído.';
            $tipoMensagem = $stmt->rowCount() ? 'sucesso' : 'erro';
            $acao = 'listar';
        } elseif ($acaoPost === 'salvar') {
            $idSalvar = (int) ($_POST['id'] ?? 0);
            $dadosForm = [];

            foreach ($camposDef as $campo => $meta) {
                $valor = trim((string) ($_POST[$campo] ?? ''));

                if (!empty($meta['obrigatorio']) && $valor === '') {
                    throw new RuntimeException('Preencha o campo obrigatório: ' . $meta['label']);
                }

                if (($meta['tipo'] ?? '') === 'date') {
                    try {
                        $valor = converterDataParaBanco($valor) ?? '';
                    } catch (RuntimeException $e) {
                        throw new RuntimeException('Confira o campo "' . $meta['label'] . '". Digite uma data válida no formato DD/MM/AAAA.');
                    }
                    if ($valor !== '' && $valor > date('Y-m-d')) {
                        throw new RuntimeException('A data do campo "' . $meta['label'] . '" não pode ser futura.');
                    }
                }

                if (($meta['tipo'] ?? '') === 'number' && $valor !== '') {
                    if (!ctype_digit($valor)) {
                        throw new RuntimeException('Informe um número inteiro válido em: ' . $meta['label']);
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
                $placeholders = array_map(static function ($c) { return ':' . $c; }, $cols);
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
<meta name="theme-color" content="#087f8c">
<meta name="description" content="<?= h($categoria['titulo']) ?> — Saúde-Conecta">
<title><?= h($categoria['titulo']) ?> | Saúde-Conecta</title>

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

.usuario-topo {
    color: var(--secundario);
    font-size: 13.5px;
    font-weight: 600;
}

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
    padding: 26px 30px;
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
    font-size: clamp(22px, 3vw, 28px);
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

/* ========== CARD ========== */
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
}

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

/* ========== NAVEGAÇÃO ENTRE CATEGORIAS ========== */
.nav-categorias {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}

.nav-categorias a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: 1px solid var(--borda);
    border-radius: 999px;
    background: #fff;
    color: var(--secundario);
    font-size: 12.5px;
    font-weight: 700;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.nav-categorias a:hover {
    border-color: #b5dedc;
    background: #f4fbfa;
    color: var(--verde);
}

.nav-categorias a.ativo {
    border-color: var(--verde);
    background: var(--verde-claro);
    color: var(--verde-escuro);
}

.nav-categorias a svg { width: 15px; height: 15px; }

/* ========== BOTÕES ========== */
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

.btn-pequeno {
    min-height: 34px;
    padding: 6px 12px;
    font-size: 12px;
}

.acoes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

/* ========== FORMULÁRIO ========== */
.grade-campos {
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

.obrigatorio { color: var(--vermelho); }

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

.ajuda-data {
    color: var(--secundario);
    font-size: 11.5px;
}

/* ========== TABELA ========== */
.tabela-container {
    width: 100%;
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--borda);
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}

th, td {
    padding: 13px 14px;
    text-align: left;
    vertical-align: top;
    border-bottom: 1px solid var(--borda);
}

th {
    background: #f0f8f6;
    color: var(--verde-escuro);
    font-weight: 700;
    font-size: 12.5px;
    white-space: nowrap;
}

tr:last-child td { border-bottom: none; }

tr:hover td { background: #f9fcfb; }

.acoes-linha {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.vazio {
    padding: 32px 20px;
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

.rodape strong { color: var(--verde); }

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
        padding: 22px 18px;
    }

    .hero h1 { font-size: 22px; }

    .grade-campos {
        grid-template-columns: 1fr;
    }

    .campo-largo { grid-column: auto; }

    .card { padding: 18px 16px; }

    .usuario-topo { display: none; }
}

@media (max-width: 420px) {
    .marca { font-size: 15px; }
    .marca-icone { width: 34px; height: 34px; }
    .botao-voltar { padding: 8px 11px; font-size: 12px; }
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
        <a class="marca" href="<?= h($urlPerfil) ?>" aria-label="Saúde-Conecta, início">
            <span class="marca-icone"><?= icone('saude') ?></span>
            <span class="marca-texto">Saúde<span>-Conecta</span></span>
        </a>

        <div style="display:flex;align-items:center;gap:12px;">
            <span class="usuario-topo">Olá, <?= h($nome) ?></span>
            <a class="botao-voltar" href="<?= h($urlPerfil) ?>">
                <?= icone('voltar') ?> Voltar ao perfil
            </a>
        </div>
    </div>
</header>

<main class="pagina">

    <section class="hero" aria-label="Apresentação">
        <span class="hero-etiqueta">
            <?= icone($categoria['icone'] ?? 'saude') ?> Registros de saúde
        </span>
        <h1><?= h($categoria['titulo']) ?></h1>
        <p><?= h($categoria['descricao']) ?></p>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="mensagem <?= h($tipoMensagem) ?>" role="status">
            <?= h($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- Navegação rápida entre categorias -->
    <nav class="nav-categorias" aria-label="Categorias de registros">
        <a href="registros.php?tipo=vacina" class="<?= $tipo === 'vacina' ? 'ativo' : '' ?>">
            <?= icone('vacina') ?> Vacinas
        </a>
        <a href="registros.php?tipo=alergia" class="<?= $tipo === 'alergia' ? 'ativo' : '' ?>">
            <?= icone('alergia') ?> Alergias
        </a>
        <a href="registros.php?tipo=alergia_vacina" class="<?= $tipo === 'alergia_vacina' ? 'ativo' : '' ?>">
            <?= icone('alergia') ?> Reações
        </a>
        <a href="registros.php?tipo=doenca" class="<?= $tipo === 'doenca' ? 'ativo' : '' ?>">
            <?= icone('doenca') ?> Doenças
        </a>
        <a href="registros.php?tipo=atestado" class="<?= $tipo === 'atestado' ? 'ativo' : '' ?>">
            <?= icone('atestado') ?> Atestados
        </a>
    </nav>

    <section class="card">
        <?php if ($mostrarFormulario): ?>
            <h2><?= $acao === 'editar' ? 'Editar registro' : 'Adicionar registro' ?></h2>

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
                                <textarea
                                    id="<?= h($campo) ?>"
                                    name="<?= h($campo) ?>"
                                    <?= !empty($meta['obrigatorio']) ? 'required' : '' ?>
                                    placeholder="<?= h($meta['placeholder'] ?? '') ?>"
                                ><?= h((string) $valorAtual) ?></textarea>

                            <?php elseif (($meta['tipo'] ?? '') === 'select'): ?>
                                <select id="<?= h($campo) ?>" name="<?= h($campo) ?>">
                                    <?php foreach (($meta['opcoes'] ?? ['']) as $op): ?>
                                        <option
                                            value="<?= h($op) ?>"
                                            <?= (string) $valorAtual === (string) $op ? 'selected' : '' ?>
                                        >
                                            <?= $op === '' ? 'Selecione' : h($op) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif (($meta['tipo'] ?? '') === 'date'): ?>
                                <input
                                    type="text"
                                    id="<?= h($campo) ?>"
                                    name="<?= h($campo) ?>"
                                    value="<?= h(formatarDataBrasileira($valorAtual)) ?>"
                                    placeholder="DD/MM/AAAA"
                                    inputmode="numeric"
                                    maxlength="10"
                                    pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}"
                                    title="Digite a data no formato DD/MM/AAAA"
                                    autocomplete="off"
                                    <?= !empty($meta['obrigatorio']) ? 'required' : '' ?>
                                >
                                <small class="ajuda-data">Digite no formato dia/mês/ano (ex.: 25/12/2026).</small>

                            <?php else: ?>
                                <input
                                    type="<?= h($meta['tipo'] ?? 'text') ?>"
                                    id="<?= h($campo) ?>"
                                    name="<?= h($campo) ?>"
                                    value="<?= h((string) $valorAtual) ?>"
                                    <?= !empty($meta['obrigatorio']) ? 'required' : '' ?>
                                    placeholder="<?= h($meta['placeholder'] ?? '') ?>"
                                >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="acoes">
                    <button class="btn" type="submit">
                        <?= icone('salvar') ?> Salvar
                    </button>
                    <a class="btn btn-sec" href="<?= h($urlLista) ?>">Cancelar</a>
                </div>
            </form>

        <?php else: ?>
            <div class="acoes" style="margin-top:0;margin-bottom:18px;">
                <a class="btn" href="<?= h($urlLista . '&acao=adicionar') ?>">
                    <?= icone('mais') ?> Adicionar
                </a>
            </div>

            <?php if (empty($registros)): ?>
                <div class="vazio">
                    <h3>Nenhum registro encontrado</h3>
                    <p>Clique em <strong>Adicionar</strong> para incluir o primeiro registro nesta categoria.</p>
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
                                    <td><?php
                                        $v = $reg[$campo] ?? '';
                                        if ($v === null || $v === '') {
                                            echo '—';
                                        } else {
                                            if (($meta['tipo'] ?? '') === 'date') {
                                                $v = formatarDataBrasileira($v);
                                            }
                                            echo nl2br(h((string) $v));
                                        }
                                    ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <div class="acoes-linha">
                                        <a
                                            class="btn btn-editar btn-pequeno"
                                            href="<?= h($urlLista . '&acao=editar&id=' . (int) $reg['id']) ?>"
                                        >
                                            <?= icone('editar') ?> Editar
                                        </a>
                                        <form
                                            method="post"
                                            action="<?= h($urlLista) ?>"
                                            style="display:inline;margin:0;"
                                            onsubmit="return confirm('Deseja realmente excluir este registro?');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                            <input type="hidden" name="acao_form" value="excluir">
                                            <input type="hidden" name="id" value="<?= (int) $reg['id'] ?>">
                                            <button class="btn btn-excluir btn-pequeno" type="submit">
                                                Excluir
                                            </button>
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
            </div>
        <?php endif; ?>
    </section>

</main>

<footer class="rodape">
    <p><strong>Saúde-Conecta</strong> — seus registros de saúde em um só lugar</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[placeholder="DD/MM/AAAA"]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            const inicio = campo.selectionStart;
            const valorAntes = campo.value;
            let numeros = campo.value.replace(/\D/g, '').slice(0, 8);
            let formatado = numeros;

            if (numeros.length > 4) {
                formatado = numeros.slice(0, 2) + '/' + numeros.slice(2, 4) + '/' + numeros.slice(4);
            } else if (numeros.length > 2) {
                formatado = numeros.slice(0, 2) + '/' + numeros.slice(2);
            }

            campo.value = formatado;

            if (inicio !== null && valorAntes.length <= campo.value.length) {
                campo.setSelectionRange(campo.value.length, campo.value.length);
            }
        });
    });
});
</script>
</body>
</html>