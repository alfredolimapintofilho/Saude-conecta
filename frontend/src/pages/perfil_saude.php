<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../backend/config/seguranca.php';
require_once __DIR__ . '/../../../backend/config/database.php';

iniciarSessaoSegura();
$usuario = exigirLogin();

/* =========================================================
   1. USUÁRIO AUTENTICADO
========================================================= */
if (is_array($usuario)) {
    $usuarioId = (int) ($usuario['id'] ?? $usuario['id_usuario'] ?? 0);
    $nomeUsuario = '';

    foreach (['nomeCompleto', 'nome_completo', 'nome', 'name'] as $campo) {
        if (!empty($usuario[$campo]) && is_string($usuario[$campo])) {
            $nomeUsuario = trim($usuario[$campo]);
            break;
        }
    }
} elseif (is_object($usuario)) {
    $usuarioId = (int) ($usuario->id ?? $usuario->id_usuario ?? 0);
    $nomeUsuario = '';

    foreach (['nomeCompleto', 'nome_completo', 'nome', 'name'] as $campo) {
        if (isset($usuario->$campo) && is_string($usuario->$campo)) {
            $nomeUsuario = trim($usuario->$campo);
            if ($nomeUsuario !== '') {
                break;
            }
        }
    }
} else {
    $usuarioId = 0;
    $nomeUsuario = '';
}

if ($usuarioId <= 0) {
    http_response_code(401);
    exit('Sessão inválida. Entre novamente na sua conta.');
}

if ($nomeUsuario === '') {
    $nomeUsuario = 'Meu perfil';
}

/* =========================================================
   2. FUNÇÕES AUXILIARES
========================================================= */
function h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Converte DD/MM/AAAA para AAAA-MM-DD.
 * Retorna null quando o campo está vazio.
 */
function converterDataBrasileira(string $data): ?string
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
        throw new RuntimeException(
            'Digite a data no formato DD/MM/AAAA, por exemplo 25/08/2000.'
        );
    }

    $obj = DateTimeImmutable::createFromFormat('!d/m/Y', $data);
    $erros = DateTimeImmutable::getLastErrors();

    if (
        $obj === false ||
        ($erros !== false &&
            ($erros['warning_count'] > 0 || $erros['error_count'] > 0)) ||
        $obj->format('d/m/Y') !== $data
    ) {
        throw new RuntimeException(
            'Data inválida. Confira o dia, o mês e o ano.'
        );
    }

    return $obj->format('Y-m-d');
}

/** Exibe AAAA-MM-DD como DD/MM/AAAA. */
function dataParaExibicao($data): string
{
    if (!is_string($data) || trim($data) === '') {
        return '';
    }

    $obj = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        substr($data, 0, 10)
    );

    return $obj ? $obj->format('d/m/Y') : '';
}

function idadeCalculada(?string $dataNascimento): ?int
{
    if (!$dataNascimento) {
        return null;
    }

    try {
        $data = substr($dataNascimento, 0, 10);

        $nascimento = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        $erros = DateTimeImmutable::getLastErrors();

        if (
            !$nascimento ||
            ($erros !== false &&
                ($erros['warning_count'] > 0 || $erros['error_count'] > 0)) ||
            $nascimento->format('Y-m-d') !== $data
        ) {
            return null;
        }

        $hoje = new DateTimeImmutable('today');

        if ($nascimento > $hoje) {
            return null;
        }

        return $nascimento->diff($hoje)->y;
    } catch (Throwable $e) {
        return null;
    }
}

function icone(string $nome): string
{
    $icones = [
        'saude' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/><path d="M12 8v7M8.5 11.5h7"/>',
        'mapa' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>',
        'pets' => '<circle cx="7" cy="8" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="17" cy="8" r="2"/><circle cx="20" cy="13" r="2"/><path d="M5 16c0-3 3-5 7-5s7 2 7 5-3 5-7 5-7-2-7-5Z"/>',
        'adocao' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>',
        'autorizacao' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'vacina' => '<path d="m18 2 4 4M7 17 3 21M14 4l6 6M5 13l6 6M3 21l7-7M9 7l8 8"/>',
        'alergia' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="M12 8v5M12 16h.01"/>',
        'doenca' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="M12 8v8M8 12h8"/>',
        'atestado' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'livro' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'seta' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'expandir' => '<path d="m7 10 5 5 5-5"/>',
        'usuario' => '<circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
        'editar' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'salvar' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'sair' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
        'crianca' => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/><path d="M9 10h.01M15 10h.01"/>',
        'idoso' => '<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/><path d="M9 11c.5 1 1.5 1.5 3 1.5s2.5-.5 3-1.5"/>',
        'mais' => '<path d="M12 5v14M5 12h14"/>',
        'voltar' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    ];

    $conteudo = $icones[$nome] ?? $icones['seta'];

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $conteudo . '</svg>';
}

/* =========================================================
   3. ESTADO INICIAL E TOKEN CSRF
========================================================= */
$mensagem = '';
$tipoMensagem = '';
$pdo = null;

$dados = [
    'data_nascimento' => '',
    'genero' => '',
    'altura' => '',
    'peso' => '',
    'tipo_sanguineo' => '',
    'gestante' => 0,
    'ultima_menstruacao' => '',
];

$token = $_SESSION['csrf_perfil_fisico'] ?? '';

if (!is_string($token) || strlen($token) < 32) {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_perfil_fisico'] = $token;
}

/* =========================================================
   4. BANCO DE DADOS E SALVAMENTO
========================================================= */
try {
    $pdo = Database::getConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS perfil_fisico (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            data_nascimento DATE NULL,
            genero VARCHAR(40) NULL,
            altura DECIMAL(5,2) NULL,
            peso DECIMAL(5,2) NULL,
            tipo_sanguineo VARCHAR(5) NULL,
            gestante TINYINT(1) NOT NULL DEFAULT 0,
            ultima_menstruacao DATE NULL,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_perfil_fisico_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare(
        'SELECT data_nascimento, genero, altura, peso, tipo_sanguineo,
                gestante, ultima_menstruacao
         FROM perfil_fisico
         WHERE usuario_id = :usuario_id
         LIMIT 1'
    );

    $stmt->execute(['usuario_id' => $usuarioId]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($registro)) {
        $dados = array_merge($dados, $registro);
        $dados['gestante'] = (int) $dados['gestante'];
    }

    if (($dados['genero'] ?? '') !== 'Feminino') {
        $dados['gestante'] = 0;
        $dados['ultima_menstruacao'] = '';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tokenEnviado = $_POST['csrf_token'] ?? '';

        if (
            !is_string($tokenEnviado) ||
            !hash_equals($token, $tokenEnviado)
        ) {
            throw new RuntimeException(
                'A sessão do formulário expirou. Atualize a página e tente novamente.'
            );
        }

        $dataNascimentoDigitada = trim(
            (string) ($_POST['data_nascimento'] ?? '')
        );

        $ultimaMenstruacaoDigitada = trim(
            (string) ($_POST['ultima_menstruacao'] ?? '')
        );

        $dataNascimento = converterDataBrasileira(
            $dataNascimentoDigitada
        );

        $ultimaMenstruacao = converterDataBrasileira(
            $ultimaMenstruacaoDigitada
        );

        $genero = trim((string) ($_POST['genero'] ?? ''));

        $alturaTexto = str_replace(
            ',',
            '.',
            trim((string) ($_POST['altura'] ?? ''))
        );

        $pesoTexto = str_replace(
            ',',
            '.',
            trim((string) ($_POST['peso'] ?? ''))
        );

        $tipoSanguineo = trim(
            (string) ($_POST['tipo_sanguineo'] ?? '')
        );

        $gestante = (
            isset($_POST['gestante']) &&
            $_POST['gestante'] === '1'
        ) ? 1 : 0;

        if ($genero !== 'Feminino') {
            $gestante = 0;
            $ultimaMenstruacao = null;
        }

        $hoje = (new DateTimeImmutable('today'))->format('Y-m-d');

        if ($dataNascimento !== null && $dataNascimento > $hoje) {
            throw new RuntimeException(
                'A data de nascimento não pode ser futura.'
            );
        }

        if ($ultimaMenstruacao !== null && $ultimaMenstruacao > $hoje) {
            throw new RuntimeException(
                'A data da última menstruação não pode ser futura.'
            );
        }

        if (
            $dataNascimento !== null &&
            $ultimaMenstruacao !== null &&
            $ultimaMenstruacao < $dataNascimento
        ) {
            throw new RuntimeException(
                'A última menstruação não pode ser anterior à data de nascimento.'
            );
        }

        $generosPermitidos = [
            '',
            'Feminino',
            'Masculino',
            'Não binário',
            'Prefiro não informar',
            'Outro',
        ];

        if (!in_array($genero, $generosPermitidos, true)) {
            throw new RuntimeException(
                'Selecione uma opção válida para gênero.'
            );
        }

        if (
            $alturaTexto !== '' &&
            (
                !is_numeric($alturaTexto) ||
                (float) $alturaTexto < 0.3 ||
                (float) $alturaTexto > 2.8
            )
        ) {
            throw new RuntimeException(
                'A altura deve ser informada em metros, entre 0,30 e 2,80.'
            );
        }

        if (
            $pesoTexto !== '' &&
            (
                !is_numeric($pesoTexto) ||
                (float) $pesoTexto < 1 ||
                (float) $pesoTexto > 500
            )
        ) {
            throw new RuntimeException(
                'O peso deve estar entre 1 e 500 kg.'
            );
        }

        $tiposSanguineos = [
            '',
            'A+',
            'A-',
            'B+',
            'B-',
            'AB+',
            'AB-',
            'O+',
            'O-',
        ];

        if (!in_array($tipoSanguineo, $tiposSanguineos, true)) {
            throw new RuntimeException(
                'Selecione um tipo sanguíneo válido.'
            );
        }

        $stmt = $pdo->prepare("
            INSERT INTO perfil_fisico
                (
                    usuario_id,
                    data_nascimento,
                    genero,
                    altura,
                    peso,
                    tipo_sanguineo,
                    gestante,
                    ultima_menstruacao
                )
            VALUES
                (
                    :usuario_id,
                    :data_nascimento,
                    :genero,
                    :altura,
                    :peso,
                    :tipo_sanguineo,
                    :gestante,
                    :ultima_menstruacao
                )
            ON DUPLICATE KEY UPDATE
                data_nascimento = VALUES(data_nascimento),
                genero = VALUES(genero),
                altura = VALUES(altura),
                peso = VALUES(peso),
                tipo_sanguineo = VALUES(tipo_sanguineo),
                gestante = VALUES(gestante),
                ultima_menstruacao = VALUES(ultima_menstruacao)
        ");

        $stmt->execute([
            'usuario_id' => $usuarioId,
            'data_nascimento' => $dataNascimento,
            'genero' => $genero !== '' ? $genero : null,
            'altura' => $alturaTexto !== '' ? (float) $alturaTexto : null,
            'peso' => $pesoTexto !== '' ? (float) $pesoTexto : null,
            'tipo_sanguineo' => $tipoSanguineo !== ''
                ? $tipoSanguineo
                : null,
            'gestante' => $gestante,
            'ultima_menstruacao' => $ultimaMenstruacao,
        ]);

        $dados = [
            'data_nascimento' => $dataNascimento ?? '',
            'genero' => $genero,
            'altura' => $alturaTexto,
            'peso' => $pesoTexto,
            'tipo_sanguineo' => $tipoSanguineo,
            'gestante' => $gestante,
            'ultima_menstruacao' => $ultimaMenstruacao ?? '',
        ];

        $mensagem = 'Suas informações físicas foram salvas com sucesso.';
        $tipoMensagem = 'sucesso';

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_perfil_fisico'] = $token;
    }
} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
} catch (Throwable $e) {
    error_log('Erro no perfil de saúde: ' . $e->getMessage());

    $mensagem = 'Não foi possível carregar ou salvar as informações. Verifique a conexão com o banco de dados e as permissões do MySQL.';
    $tipoMensagem = 'erro';
}

/* =========================================================
   5. PREPARAÇÃO PARA EXIBIÇÃO
========================================================= */
$generoFeminino = ($dados['genero'] ?? '') === 'Feminino';

if (!$generoFeminino) {
    $dados['gestante'] = 0;
    $dados['ultima_menstruacao'] = '';
}

$idade = idadeCalculada(
    !empty($dados['data_nascimento'])
        ? (string) $dados['data_nascimento']
        : null
);

$alturaExibida = (
    $dados['altura'] !== '' &&
    $dados['altura'] !== null
)
    ? number_format((float) $dados['altura'], 2, ',', '.') . ' m'
    : 'Não informada';

$pesoExibido = (
    $dados['peso'] !== '' &&
    $dados['peso'] !== null
)
    ? number_format((float) $dados['peso'], 1, ',', '.') . ' kg'
    : 'Não informado';

$tipoSanguineoExibido = $dados['tipo_sanguineo'] ?: 'Não informado';

$iniciais = 'U';

if (preg_match('/^\s*(\p{L})/u', $nomeUsuario, $resultado)) {
    $iniciais = function_exists('mb_strtoupper')
        ? mb_strtoupper($resultado[1], 'UTF-8')
        : strtoupper($resultado[1]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#087f8c">
<meta name="description" content="Gerencie suas informações físicas e acesse os serviços de saúde do Saúde-Conecta.">
<title>Meu espaço de saúde | Saúde-Conecta</title>

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
    --sombra-hover: 0 14px 36px rgba(24, 57, 78, 0.10);
    --raio: 16px;
    --raio-lg: 20px;
}

* { box-sizing: border-box; }

html {
    scroll-behavior: smooth;
    scroll-padding-top: 90px;
}

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
    width: 22px;
    height: 22px;
    flex-shrink: 0;
}

a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible {
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
    width: min(1180px, calc(100% - 40px));
    min-height: 68px;
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
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.4px;
}

.marca-icone {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: linear-gradient(135deg, #087f8c, #1fa8ab);
    color: #fff;
    box-shadow: 0 4px 12px rgba(8, 127, 140, 0.25);
}

.marca-icone svg { width: 22px; height: 22px; }
.marca-texto span { color: var(--verde); }

.menu-topo {
    display: flex;
    align-items: center;
    gap: 20px;
}

.menu-topo a {
    color: #5a7080;
    font-size: 13.5px;
    font-weight: 600;
    transition: color 0.15s;
}

.menu-topo a:hover { color: var(--verde); }

.acoes-topo {
    display: flex;
    align-items: center;
    gap: 10px;
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

.botao-sair {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px 14px;
    border: 1px solid #f0dede;
    border-radius: 10px;
    background: #fffafa;
    color: #b54747;
    font-size: 12.5px;
    font-weight: 700;
    transition: background 0.15s;
}

.botao-sair:hover { background: #fff0f0; }
.botao-sair svg { width: 16px; height: 16px; }

/* ========== PÁGINA ========== */
.pagina {
    width: min(1180px, calc(100% - 40px));
    margin: 24px auto 56px;
}

/* ========== CAPA / HERO ========== */
.capa {
    position: relative;
    overflow: hidden;
    border-radius: var(--raio-lg);
    background:
        radial-gradient(circle at 85% 15%, rgba(255,255,255,0.18) 0 70px, transparent 71px),
        radial-gradient(circle at 70% 120%, rgba(255,255,255,0.10) 0 140px, transparent 141px),
        linear-gradient(125deg, #065f73 0%, #087f8c 45%, #2cb5ae 100%);
    box-shadow: 0 12px 32px rgba(8, 127, 140, 0.18);
}

.capa-conteudo {
    position: relative;
    z-index: 1;
    padding: 32px 36px;
    color: white;
}

.capa-etiqueta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border: 1px solid rgba(255,255,255,0.28);
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
}

.capa-etiqueta svg { width: 14px; height: 14px; }

.capa h1 {
    margin: 14px 0 0;
    font-size: clamp(26px, 3.2vw, 34px);
    line-height: 1.2;
    letter-spacing: -0.5px;
    font-weight: 800;
}

.capa p {
    max-width: 520px;
    margin: 10px 0 0;
    color: rgba(255,255,255,0.88);
    font-size: 14.5px;
    line-height: 1.5;
}

/* ========== PERFIL CABEÇALHO ========== */
.perfil-cabecalho {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-top: 18px;
    padding: 20px 24px;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    background: #fff;
    box-shadow: var(--sombra);
}

.avatar {
    width: 76px;
    height: 76px;
    flex: 0 0 76px;
    display: grid;
    place-items: center;
    border: 3px solid #d9f0ec;
    border-radius: 50%;
    background: linear-gradient(145deg, #d4f3ee, #c5e6f5);
    color: var(--verde-escuro);
    font-size: 28px;
    font-weight: 800;
}

.perfil-identificacao { min-width: 0; flex: 1; }

.perfil-identificacao h2 {
    margin: 0 0 3px;
    overflow-wrap: anywhere;
    font-size: 20px;
    font-weight: 800;
}

.perfil-identificacao p {
    margin: 0;
    color: var(--secundario);
    font-size: 13.5px;
}

.perfil-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e4f6ec;
    color: #26764e;
    font-size: 11px;
    font-weight: 700;
}

.status-ponto {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #2eaa6d;
}

/* ========== LAYOUT ========== */
.layout { display: block; width: 100%; margin-top: 28px; }
.conteudo { width: 100%; min-width: 0; }

.secao {
    margin-bottom: 32px;
    scroll-margin-top: 95px;
}

.secao-titulo {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    margin: 0 0 16px;
}

.secao-titulo h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.3px;
}

.secao-titulo p {
    margin: 4px 0 0;
    color: var(--secundario);
    font-size: 13px;
}

.contador {
    flex-shrink: 0;
    padding: 5px 12px;
    border: 1px solid var(--borda);
    border-radius: 999px;
    background: #fff;
    color: var(--secundario);
    font-size: 11px;
    font-weight: 700;
}

/* ========== PAINEL ========== */
.painel {
    overflow: hidden;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    background: #fff;
    box-shadow: var(--sombra);
}

.painel-topo {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--borda);
}

.painel-titulo {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.icone-painel {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    flex: 0 0 42px;
    border-radius: 12px;
    background: var(--verde-claro);
    color: var(--verde);
}

.painel-titulo h3 { margin: 0; font-size: 15px; font-weight: 800; }

.painel-titulo p {
    margin: 3px 0 0;
    color: var(--secundario);
    font-size: 12px;
}

/* ========== BOTÕES ========== */
.botao {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 9px 15px;
    border: 1px solid var(--borda);
    border-radius: 10px;
    background: #fff;
    color: var(--texto);
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}

.botao svg { width: 16px; height: 16px; }

.botao:hover {
    border-color: #b5dedc;
    background: #f4fbfa;
}

.botao-principal {
    border-color: var(--verde);
    background: var(--verde);
    color: #fff;
}

.botao-principal:hover {
    border-color: var(--verde-escuro);
    background: var(--verde-escuro);
}

.botao-cancelar { color: #667d8b; }

/* ========== RESUMO FÍSICO ========== */
.resumo-fisico {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    padding: 20px 22px;
}

.dado {
    min-width: 0;
    padding: 14px 13px;
    border: 1px solid #e8f0f4;
    border-radius: 12px;
    background: #f9fcfd;
}

.dado span {
    display: block;
    margin-bottom: 6px;
    color: var(--secundario);
    font-size: 11.5px;
    font-weight: 600;
}

.dado strong {
    display: block;
    overflow-wrap: anywhere;
    font-size: 16.5px;
    font-weight: 800;
}

.dado small {
    display: block;
    margin-top: 3px;
    color: #9aa9b2;
    font-size: 10.5px;
}

/* Atalho gravidez */
.atalho-gravidez-resumo {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border: 1px solid #c5e6df;
    border-radius: 14px;
    background: linear-gradient(115deg, #eef9f5, #f3fafd);
    color: #18394e;
    transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
}

.atalho-gravidez-resumo:hover {
    border-color: var(--verde);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(8, 127, 140, 0.10);
}

.atalho-gravidez-resumo[hidden] {
    display: none !important;
}

.gravidez-resumo-conteudo {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.gravidez-resumo-icone {
    display: grid;
    place-items: center;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #d6f0e6;
    font-size: 22px;
}

.gravidez-resumo-texto strong {
    display: block;
    font-size: 14px;
    font-weight: 800;
}

.gravidez-resumo-texto small {
    display: block;
    margin-top: 3px;
    color: #5f7585;
    font-size: 12px;
    line-height: 1.45;
}

.botao-acompanhar-gravidez {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex-shrink: 0;
    padding: 10px 16px;
    border-radius: 10px;
    background: var(--verde);
    color: #fff;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.15s;
}

.botao-acompanhar-gravidez:hover { background: var(--verde-escuro); }

/* ========== FORMULÁRIO ========== */
.formulario {
    padding: 22px;
    border-top: 1px solid var(--borda);
    background: #fafdfd;
}

.formulario[hidden],
.gravidez[hidden],
#campo-menstruacao[hidden] {
    display: none !important;
}

.formulario h4 { margin: 0 0 4px; font-size: 15.5px; font-weight: 800; }

.ajuda-formulario {
    margin: 0 0 18px;
    color: var(--secundario);
    font-size: 12.5px;
}

.campos {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.campo { min-width: 0; }

.campo label {
    display: block;
    margin-bottom: 6px;
    color: #385466;
    font-size: 12.5px;
    font-weight: 700;
}

.campo input,
.campo select {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #d5e3ea;
    border-radius: 10px;
    background: #fff;
    color: var(--texto);
    font: inherit;
    font-size: 13.5px;
    transition: border-color 0.15s;
}

.campo input:focus,
.campo select:focus {
    border-color: var(--verde);
}

.campo small {
    display: block;
    margin-top: 5px;
    color: var(--secundario);
    font-size: 11px;
}

.campo-largo { grid-column: 1 / -1; }

.gravidez {
    grid-column: 1 / -1;
    padding: 15px;
    border: 1px solid #d0ebe7;
    border-radius: 12px;
    background: #f0f9f7;
}

.gravidez-cabecalho {
    display: flex;
    align-items: flex-start;
    gap: 11px;
}

.gravidez-cabecalho input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: var(--verde);
    flex: 0 0 auto;
}

.gravidez-cabecalho label { margin: 0; font-size: 13.5px; }

.gravidez-cabecalho p {
    margin: 3px 0 0;
    color: var(--secundario);
    font-size: 11.5px;
}

#campo-menstruacao { margin-top: 14px; }

.acoes-formulario {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 20px;
}

.aviso {
    margin: 16px 22px 0;
    padding: 12px 14px;
    border: 1px solid;
    border-radius: 10px;
    font-size: 13px;
}

.aviso.sucesso {
    border-color: #b8e5c8;
    background: #eef9f2;
    color: #246c43;
}

.aviso.erro {
    border-color: #efc8c8;
    background: #fff5f5;
    color: #a13535;
}

/* ========== GRADE DE SERVIÇOS ========== */
.grade-servicos {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.janela {
    min-width: 0;
    min-height: 188px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 20px;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    background: #fff;
    box-shadow: var(--sombra);
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.janela:hover {
    border-color: #b8dedc;
    box-shadow: var(--sombra-hover);
    transform: translateY(-3px);
}

.icone-janela {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    margin-bottom: 14px;
    border-radius: 12px;
}

.icone-janela svg { width: 22px; height: 22px; }

.janela h3 {
    margin: 0 0 6px;
    font-size: 14.5px;
    font-weight: 800;
    line-height: 1.35;
}

.janela p {
    margin: 0 0 14px;
    color: var(--secundario);
    font-size: 12.5px;
    line-height: 1.55;
}

.abrir {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: auto;
    color: var(--verde);
    font-size: 12px;
    font-weight: 700;
}

.abrir svg { width: 14px; height: 14px; }

.saude-az .icone-janela { background: #e4f4e8; color: #2e7d4b; }
.mapa .icone-janela { background: #e5f1ff; color: #3477b8; }
.autorizacoes .icone-janela { background: #eceaff; color: #6256b5; }
.pets .icone-janela { background: #fff4e0; color: #ad791e; }
.adocao .icone-janela { background: #ffeaf1; color: #c54c7a; }
.crianca .icone-janela { background: #e6f3ff; color: #2a7abf; }
.idoso .icone-janela { background: #f2eef8; color: #7a4ea3; }

.saude-az .abrir { color: #2e7d4b; }
.mapa .abrir { color: #3477b8; }
.autorizacoes .abrir { color: #6256b5; }
.pets .abrir { color: #ad791e; }
.adocao .abrir { color: #c54c7a; }
.crianca .abrir { color: #2a7abf; }
.idoso .abrir { color: #7a4ea3; }

/* Accordion médico */
.janela-medica {
    overflow: hidden;
    padding: 0;
    border-color: #c5e6e2;
}

.resumo-medico {
    width: 100%;
    min-height: 188px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 20px;
    cursor: pointer;
    list-style: none;
}

.resumo-medico::-webkit-details-marker { display: none; }
.resumo-medico::marker { content: ""; }

.titulo-medico {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.titulo-medico h3 {
    flex: 1;
    margin: 0 0 6px;
    font-size: 14.5px;
    font-weight: 800;
}

.seta-expandir {
    width: 18px;
    height: 18px;
    margin-top: -4px;
    color: var(--verde);
    transition: transform 0.2s;
}

.janela-medica[open] .seta-expandir { transform: rotate(180deg); }

.opcoes-medicas {
    padding: 6px 14px 14px;
    border-top: 1px solid var(--borda);
    background: #fafdfd;
}

.opcao-medica {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 4px;
    border-bottom: 1px solid #edf2f4;
    flex-wrap: wrap;
}

.opcao-medica:last-child { border-bottom: none; }
.opcao-medica:hover { background: #f0f9f8; }

.opcao-icone {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    flex: 0 0 34px;
    border-radius: 9px;
}

.opcao-icone svg { width: 17px; height: 17px; }

.opcao-texto { min-width: 120px; flex: 1; }

.opcao-texto strong {
    display: block;
    font-size: 12.5px;
    font-weight: 800;
}

.opcao-texto small {
    display: block;
    margin-top: 2px;
    color: var(--secundario);
    font-size: 11px;
}

.acoes-opcao {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-left: auto;
}

.btn-mini {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 11px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid var(--borda);
    background: #fff;
    color: var(--texto);
    transition: filter 0.15s;
}

.btn-mini svg { width: 13px; height: 13px; }

.btn-mini.ver {
    color: #087f8c;
    border-color: #c5e6e2;
    background: #f0f9f7;
}

.btn-mini.add {
    color: #2e7d4b;
    border-color: #c2e4cb;
    background: #eef9f2;
}

.btn-mini:hover { filter: brightness(0.97); }

.cor-vacina { background: #e0f6f3; color: #087f8c; }
.cor-alergia, .cor-reacao { background: #fff0e4; color: #b96827; }
.cor-doenca { background: #e8efff; color: #4568ad; }
.cor-atestado { background: #e6f6e9; color: #34804b; }

/* ========== RODAPÉ ========== */
.rodape {
    padding: 24px 20px;
    border-top: 1px solid var(--borda);
    background: #fff;
}

.rodape-conteudo {
    width: min(1180px, 100%);
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.rodape-marca {
    color: var(--verde);
    font-size: 13px;
    font-weight: 800;
}

.rodape p {
    margin: 0;
    color: var(--secundario);
    font-size: 12px;
}

/* ========== RESPONSIVO ========== */
@media (max-width: 950px) {
    .menu-topo { gap: 12px; }
    .resumo-fisico { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .janela, .resumo-medico { padding: 18px; }
    .janela-medica { padding: 0; }
}

@media (max-width: 700px) {
    .topo-conteudo {
        width: calc(100% - 28px);
        min-height: 62px;
    }

    .menu-topo { display: none; }

    .pagina {
        width: calc(100% - 28px);
        margin-top: 16px;
    }

    .capa-conteudo { padding: 26px 22px; }
    .capa h1 { font-size: 26px; }
    .capa p { font-size: 13.5px; }

    .perfil-cabecalho {
        padding: 16px;
        gap: 14px;
    }

    .avatar {
        width: 62px;
        height: 62px;
        flex-basis: 62px;
        font-size: 23px;
    }

    .perfil-identificacao h2 { font-size: 17px; }
    .perfil-identificacao p { font-size: 12.5px; }

    .layout { margin-top: 20px; }
    .secao-titulo h2 { font-size: 18px; }
    .painel-topo { padding: 16px; }
    .resumo-fisico { padding: 14px; gap: 10px; }
    .dado { padding: 12px 11px; }
    .dado strong { font-size: 15px; }
    .formulario { padding: 16px; }
    .campos { gap: 14px; }
    .grade-servicos { gap: 12px; }
    .janela { min-height: 190px; padding: 16px; }
    .janela-medica { padding: 0; }
    .resumo-medico { min-height: 190px; padding: 16px; }
    .icone-janela { width: 40px; height: 40px; margin-bottom: 12px; }
    .janela h3, .titulo-medico h3 { font-size: 13.5px; }
    .janela p { font-size: 12px; }
    .abrir { font-size: 11.5px; }

    .rodape-conteudo {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 600px) {
    .atalho-gravidez-resumo {
        align-items: stretch;
        flex-direction: column;
    }

    .botao-acompanhar-gravidez {
        width: 100%;
    }
}

@media (max-width: 420px) {
    .marca { gap: 8px; font-size: 15px; }
    .marca-icone { width: 36px; height: 36px; }
    .botao-sair, .botao-voltar { padding: 8px 11px; font-size: 12px; }
    .campos { grid-template-columns: minmax(0, 1fr); }
    .campo-largo, .gravidez { grid-column: auto; }
    .grade-servicos { grid-template-columns: minmax(0, 1fr); }
    .janela, .resumo-medico { min-height: 170px; }
    .janela-medica { min-height: 0; }
    .secao-titulo { align-items: flex-start; }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
</head>

<body>
<header class="topo">
    <div class="topo-conteudo">
        <a class="marca" href="index.html" aria-label="Saúde-Conecta, início">
            <span class="marca-icone"><?= icone('saude') ?></span>
            <span class="marca-texto">Saúde<span>-Conecta</span></span>
        </a>

        <nav class="menu-topo" aria-label="Navegação principal">
            <a href="#informacoes-fisicas">Meu espaço</a>
            <a href="#servicos">Serviços</a>
            <a href="saude_a_z.php">Saúde de A a Z</a>
            <a href="mapa.php">Mapa da Saúde</a>
        </nav>

        <div class="acoes-topo">
            <a class="botao-voltar" href="index.html">
                <?= icone('voltar') ?> Voltar ao início
            </a>

            <a class="botao-sair" href="logout.php">
                <?= icone('sair') ?> Sair
            </a>
        </div>
    </div>
</header>

<main class="pagina">
    <section aria-label="Apresentação do perfil">
        <div class="capa">
            <div class="capa-conteudo">
                <span class="capa-etiqueta">
                    <?= icone('saude') ?> Saúde e bem-estar
                </span>
                <h1>Meu espaço de saúde</h1>
                <p>Suas informações físicas e seus serviços de saúde em um só lugar.</p>
            </div>
        </div>

        <div class="perfil-cabecalho">
            <div class="avatar" aria-label="Inicial do nome do usuário">
                <?= h($iniciais) ?>
            </div>

            <div class="perfil-identificacao">
                <h2><?= h($nomeUsuario) ?></h2>
                <p>Seu perfil pessoal de saúde</p>
                <span class="perfil-status">
                    <span class="status-ponto"></span>
                    Área pessoal
                </span>
            </div>
        </div>
    </section>

    <div class="layout">
        <div class="conteudo">

            <section
                class="secao"
                id="informacoes-fisicas"
                aria-label="Minhas informações físicas"
            >
                <div class="painel">

                    <?php if ($mensagem !== ''): ?>
                        <div
                            class="aviso <?= h($tipoMensagem) ?>"
                            role="status"
                            aria-live="polite"
                        ><?= h($mensagem) ?></div>
                    <?php endif; ?>

                    <div class="painel-topo">
                        <div class="painel-titulo">
                            <span class="icone-painel">
                                <?= icone('usuario') ?>
                            </span>
                            <div>
                                <h3>Resumo físico</h3>
                                <p>Dados visíveis somente nesta área do seu perfil.</p>
                            </div>
                        </div>

                        <button
                            class="botao botao-principal"
                            type="button"
                            id="botao-editar"
                            aria-controls="formulario-fisico"
                            aria-expanded="false"
                        >
                            <?= icone('editar') ?> Editar
                        </button>
                    </div>

                    <div class="resumo-fisico" id="resumo-fisico">
                        <div class="dado">
                            <span>Idade</span>
                            <strong>
                                <?= $idade !== null ? h($idade . ' anos') : 'Não informada' ?>
                            </strong>
                            <small>Calculada pela data de nascimento</small>
                        </div>

                        <div class="dado">
                            <span>Altura</span>
                            <strong><?= h($alturaExibida) ?></strong>
                            <small>Em metros</small>
                        </div>

                        <div class="dado">
                            <span>Peso</span>
                            <strong><?= h($pesoExibido) ?></strong>
                            <small>Em quilogramas</small>
                        </div>

                        <div class="dado">
                            <span>Gênero</span>
                            <strong><?= h($dados['genero'] ?: 'Não informado') ?></strong>
                            <small>Conforme preenchido</small>
                        </div>

                        <div class="dado">
                            <span>Tipo sanguíneo</span>
                            <strong><?= h($tipoSanguineoExibido) ?></strong>
                            <small>Se conhecido</small>
                        </div>

                        <?php if ($generoFeminino): ?>
                            <div class="dado" id="resumo-gravidez">
                                <span>Gravidez</span>
                                <strong>
                                    <?= (int) $dados['gestante'] === 1 ? 'Sim' : 'Não' ?>
                                </strong>
                                <small>Conforme sua declaração</small>
                            </div>
                        <?php endif; ?>

                        <a
                            class="atalho-gravidez-resumo"
                            id="atalho-gravidez-resumo"
                            href="gravidez.php"
                            <?= $generoFeminino ? '' : 'hidden' ?>
                        >
                            <span class="gravidez-resumo-conteudo">
                                <span
                                    class="gravidez-resumo-icone"
                                    aria-hidden="true"
                                >🤰</span>

                                <span class="gravidez-resumo-texto">
                                    <strong>Acompanhar minha gravidez</strong>
                                    <small>
                                        Acesse seu acompanhamento gestacional,
                                        consultas, exames e registros.
                                    </small>
                                </span>
                            </span>

                            <span class="botao-acompanhar-gravidez">
                                Abrir acompanhamento →
                            </span>
                        </a>
                    </div>

                    <form
                        class="formulario"
                        id="formulario-fisico"
                        method="post"
                        action="#informacoes-fisicas"
                        hidden
                    >
                        <h4>Editar informações físicas</h4>

                        <p class="ajuda-formulario">
                            Preencha os campos que desejar. As datas devem ser
                            digitadas no formato DD/MM/AAAA.
                        </p>

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($token) ?>"
                        >

                        <div class="campos">
                            <div class="campo">
                                <label for="data_nascimento">
                                    Data de nascimento
                                </label>
                                <input
                                    type="text"
                                    id="data_nascimento"
                                    name="data_nascimento"
                                    placeholder="DD/MM/AAAA"
                                    inputmode="numeric"
                                    maxlength="10"
                                    pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}"
                                    value="<?= h(dataParaExibicao($dados['data_nascimento'] ?? '')) ?>"
                                    autocomplete="bday"
                                >
                                <small>
                                    Exemplo: 25/08/2000. Usada para calcular sua idade.
                                </small>
                            </div>

                            <div class="campo">
                                <label for="genero">Gênero</label>
                                <select id="genero" name="genero">
                                    <option value="">Selecione</option>
                                    <?php foreach (['Feminino','Masculino','Não binário','Outro','Prefiro não informar'] as $opcaoGenero): ?>
                                        <option
                                            value="<?= h($opcaoGenero) ?>"
                                            <?= ($dados['genero'] ?? '') === $opcaoGenero ? 'selected' : '' ?>
                                        ><?= h($opcaoGenero) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="campo">
                                <label for="altura">Altura (metros)</label>
                                <input
                                    type="number"
                                    id="altura"
                                    name="altura"
                                    min="0.30"
                                    max="2.80"
                                    step="0.01"
                                    placeholder="Ex.: 1.70"
                                    value="<?= h($dados['altura']) ?>"
                                >
                                <small>
                                    Exemplo: 1,70 representa 1 metro e 70 centímetros.
                                </small>
                            </div>

                            <div class="campo">
                                <label for="peso">Peso (kg)</label>
                                <input
                                    type="number"
                                    id="peso"
                                    name="peso"
                                    min="1"
                                    max="500"
                                    step="0.1"
                                    placeholder="Ex.: 65.5"
                                    value="<?= h($dados['peso']) ?>"
                                >
                            </div>

                            <div class="campo campo-largo">
                                <label for="tipo_sanguineo">Tipo sanguíneo</label>
                                <select id="tipo_sanguineo" name="tipo_sanguineo">
                                    <option value="">Não sei / não quero informar</option>
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $tipo): ?>
                                        <option
                                            value="<?= h($tipo) ?>"
                                            <?= ($dados['tipo_sanguineo'] ?? '') === $tipo ? 'selected' : '' ?>
                                        ><?= h($tipo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div
                                class="gravidez"
                                id="bloco-gravidez"
                                <?= $generoFeminino ? '' : 'hidden' ?>
                            >
                                <div class="gravidez-cabecalho">
                                    <input
                                        type="checkbox"
                                        id="gestante"
                                        name="gestante"
                                        value="1"
                                        <?= (int) $dados['gestante'] === 1 && $generoFeminino ? 'checked' : '' ?>
                                    >

                                    <div>
                                        <label for="gestante">Estou grávida</label>
                                        <p>
                                            Informação opcional. Não substitui avaliação profissional.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="campo"
                                    id="campo-menstruacao"
                                    <?= (int) $dados['gestante'] === 1 && $generoFeminino ? '' : 'hidden' ?>
                                >
                                    <label for="ultima_menstruacao">
                                        Data da última menstruação (opcional)
                                    </label>
                                    <input
                                        type="text"
                                        id="ultima_menstruacao"
                                        name="ultima_menstruacao"
                                        placeholder="DD/MM/AAAA"
                                        inputmode="numeric"
                                        maxlength="10"
                                        pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}"
                                        value="<?= h(dataParaExibicao($generoFeminino ? ($dados['ultima_menstruacao'] ?? '') : '')) ?>"
                                        autocomplete="off"
                                    >
                                    <small>Digite no formato DD/MM/AAAA.</small>
                                </div>
                            </div>
                        </div>

                        <div class="acoes-formulario">
                            <button class="botao botao-principal" type="submit">
                                <?= icone('salvar') ?> Salvar informações
                            </button>

                            <button
                                class="botao botao-cancelar"
                                type="button"
                                id="botao-cancelar"
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section
                class="secao"
                id="servicos"
                aria-labelledby="titulo-servicos"
            >
                <div class="secao-titulo">
                    <div>
                        <h2 id="titulo-servicos">Serviços de saúde</h2>
                        <p>Escolha uma área para consultar ou gerenciar.</p>
                    </div>
                    <span class="contador">8 áreas</span>
                </div>

                <div class="grade-servicos">
                    <details class="janela janela-medica" id="informacoes-medicas">
                        <summary class="resumo-medico">
                            <span
                                class="icone-janela"
                                style="background:#e0f6f3;color:#087f8c"
                            >
                                <?= icone('saude') ?>
                            </span>

                            <span class="titulo-medico">
                                <h3>Minhas informações médicas</h3>
                                <?= icone('expandir') ?>
                            </span>

                            <p>
                                Cartão de vacinação, alergias, doenças e atestados.
                                Adicione, edite ou exclua registros.
                            </p>

                            <span class="abrir">
                                Ver opções médicas <?= icone('seta') ?>
                            </span>
                        </summary>

                        <div class="opcoes-medicas">
                            <div class="opcao-medica">
                                <span class="opcao-icone cor-vacina">
                                    <?= icone('vacina') ?>
                                </span>
                                <span class="opcao-texto">
                                    <strong>Cartão de Vacinação</strong>
                                    <small>Consultar e gerenciar vacinas</small>
                                </span>
                                <span class="acoes-opcao">
                                    <a class="btn-mini ver" href="registros.php?tipo=vacina">Gerenciar</a>
                                    <a class="btn-mini add" href="registros.php?tipo=vacina&amp;acao=adicionar"><?= icone('mais') ?> Adicionar</a>
                                </span>
                            </div>

                            <div class="opcao-medica">
                                <span class="opcao-icone cor-alergia">
                                    <?= icone('alergia') ?>
                                </span>
                                <span class="opcao-texto">
                                    <strong>Alergias</strong>
                                    <small>Consultar e gerenciar alergias</small>
                                </span>
                                <span class="acoes-opcao">
                                    <a class="btn-mini ver" href="registros.php?tipo=alergia">Gerenciar</a>
                                    <a class="btn-mini add" href="registros.php?tipo=alergia&amp;acao=adicionar"><?= icone('mais') ?> Adicionar</a>
                                </span>
                            </div>

                            <div class="opcao-medica">
                                <span class="opcao-icone cor-reacao">
                                    <?= icone('alergia') ?>
                                </span>
                                <span class="opcao-texto">
                                    <strong>Reações a vacinas</strong>
                                    <small>Consultar e gerenciar reações</small>
                                </span>
                                <span class="acoes-opcao">
                                    <a class="btn-mini ver" href="registros.php?tipo=alergia_vacina">Gerenciar</a>
                                    <a class="btn-mini add" href="registros.php?tipo=alergia_vacina&amp;acao=adicionar"><?= icone('mais') ?> Adicionar</a>
                                </span>
                            </div>

                            <div class="opcao-medica">
                                <span class="opcao-icone cor-doenca">
                                    <?= icone('doenca') ?>
                                </span>
                                <span class="opcao-texto">
                                    <strong>Histórico de doenças</strong>
                                    <small>Consultar e gerenciar doenças</small>
                                </span>
                                <span class="acoes-opcao">
                                    <a class="btn-mini ver" href="registros.php?tipo=doenca">Gerenciar</a>
                                    <a class="btn-mini add" href="registros.php?tipo=doenca&amp;acao=adicionar"><?= icone('mais') ?> Adicionar</a>
                                </span>
                            </div>

                            <div class="opcao-medica">
                                <span class="opcao-icone cor-atestado">
                                    <?= icone('atestado') ?>
                                </span>
                                <span class="opcao-texto">
                                    <strong>Atestados</strong>
                                    <small>Consultar e gerenciar atestados</small>
                                </span>
                                <span class="acoes-opcao">
                                    <a class="btn-mini ver" href="registros.php?tipo=atestado">Gerenciar</a>
                                    <a class="btn-mini add" href="registros.php?tipo=atestado&amp;acao=adicionar"><?= icone('mais') ?> Adicionar</a>
                                </span>
                            </div>
                        </div>
                    </details>

                    <a class="janela crianca" href="saude_crianca.php">
                        <span class="icone-janela"><?= icone('crianca') ?></span>
                        <h3>Saúde Criança</h3>
                        <p>Acompanhamento infantil, vacinas, crescimento e cuidados pediátricos.</p>
                        <span class="abrir">Acessar área infantil <?= icone('seta') ?></span>
                    </a>

                    <a class="janela idoso" href="saude_idosos.php">
                        <span class="icone-janela"><?= icone('idoso') ?></span>
                        <h3>Saúde Idosos</h3>
                        <p>Cuidados com a terceira idade, prevenção, medicações e acompanhamento.</p>
                        <span class="abrir">Acessar área do idoso <?= icone('seta') ?></span>
                    </a>

                    <a class="janela saude-az" href="saude_a_z.php">
                        <span class="icone-janela"><?= icone('livro') ?></span>
                        <h3>Saúde de A a Z</h3>
                        <p>Informações sobre doenças, sintomas, prevenção e cuidados com a saúde.</p>
                        <span class="abrir">Explorar assuntos <?= icone('seta') ?></span>
                    </a>

                    <a class="janela mapa" href="mapa.php">
                        <span class="icone-janela"><?= icone('mapa') ?></span>
                        <h3>Mapa da Saúde</h3>
                        <p>Encontre unidades de saúde e serviços disponíveis na sua região.</p>
                        <span class="abrir">Abrir mapa <?= icone('seta') ?></span>
                    </a>

                    <a class="janela autorizacoes" href="autorizacoes_medicas.php">
                        <span class="icone-janela"><?= icone('autorizacao') ?></span>
                        <h3>Autorizar um profissional de saúde</h3>
                        <p>Gerencie os profissionais autorizados a acessar seus dados médicos.</p>
                        <span class="abrir">Gerenciar autorizações <?= icone('seta') ?></span>
                    </a>

                    <a class="janela pets" href="pets.php">
                        <span class="icone-janela"><?= icone('pets') ?></span>
                        <h3>Meus Pets</h3>
                        <p>Acesse os cadastros e as informações dos seus animais.</p>
                        <span class="abrir">Acessar meus pets <?= icone('seta') ?></span>
                    </a>

                    <a class="janela adocao" href="adocao_pets.php">
                        <span class="icone-janela"><?= icone('adocao') ?></span>
                        <h3>Adoção de Animais</h3>
                        <p>Acesse a área de adoção de animais.</p>
                        <span class="abrir">Ver adoções <?= icone('seta') ?></span>
                    </a>
                </div>
            </section>
        </div>
    </div>
</main>

<footer class="rodape">
    <div class="rodape-conteudo">
        <span class="rodape-marca">Saúde-Conecta</span>
        <p>Sua saúde, organizada em um só lugar.</p>
    </div>
</footer>

<script>
(function () {
    const botaoEditar = document.getElementById('botao-editar');
    const botaoCancelar = document.getElementById('botao-cancelar');
    const formulario = document.getElementById('formulario-fisico');
    const checkboxGestante = document.getElementById('gestante');
    const campoMenstruacao = document.getElementById('campo-menstruacao');
    const campoGenero = document.getElementById('genero');
    const blocoGravidez = document.getElementById('bloco-gravidez');
    const dataUltimaMenstruacao = document.getElementById('ultima_menstruacao');
    const resumoFisico = document.getElementById('resumo-fisico');
    const atalhoGravidez = document.getElementById('atalho-gravidez-resumo');

    function abrirEdicao() {
        formulario.hidden = false;
        botaoEditar.setAttribute('aria-expanded', 'true');
        botaoEditar.innerHTML = <?= json_encode(icone('editar') . ' Fechar edição') ?>;
        atualizarCamposGravidez();
        document.getElementById('data_nascimento').focus();
    }

    function fecharEdicao() {
        formulario.hidden = true;
        botaoEditar.setAttribute('aria-expanded', 'false');
        botaoEditar.innerHTML = <?= json_encode(icone('editar') . ' Editar') ?>;
    }

    botaoEditar.addEventListener('click', function () {
        if (formulario.hidden) {
            abrirEdicao();
        } else {
            fecharEdicao();
        }
    });

    botaoCancelar.addEventListener('click', fecharEdicao);

    function atualizarCamposGravidez() {
        const feminino = campoGenero.value === 'Feminino';

        blocoGravidez.hidden = !feminino;

        if (atalhoGravidez) {
            atalhoGravidez.hidden = !feminino;
        }

        if (!feminino) {
            checkboxGestante.checked = false;
            dataUltimaMenstruacao.value = '';
        }

        atualizarCampoMenstruacao();
        atualizarResumoGravidez();
    }

    function atualizarCampoMenstruacao() {
        campoMenstruacao.hidden =
            campoGenero.value !== 'Feminino' ||
            !checkboxGestante.checked;
    }

    function atualizarResumoGravidez() {
        const feminino = campoGenero.value === 'Feminino';
        const existente = document.getElementById('resumo-gravidez');

        if (!feminino) {
            if (existente) {
                existente.remove();
            }
            return;
        }

        let resumo = existente;

        if (!resumo) {
            resumo = document.createElement('div');
            resumo.className = 'dado';
            resumo.id = 'resumo-gravidez';
            resumo.innerHTML =
                '<span>Gravidez</span>' +
                '<strong></strong>' +
                '<small>Conforme sua declaração</small>';
            resumoFisico.insertBefore(resumo, atalhoGravidez);
        }

        resumo.querySelector('strong').textContent =
            checkboxGestante.checked ? 'Sim' : 'Não';
    }

    function aplicarMascaraData(campo) {
        if (!campo) return;

        campo.addEventListener('input', function () {
            const numeros = campo.value.replace(/\D/g, '').slice(0, 8);
            let formatado = numeros;

            if (numeros.length > 4) {
                formatado =
                    numeros.slice(0, 2) + '/' +
                    numeros.slice(2, 4) + '/' +
                    numeros.slice(4);
            } else if (numeros.length > 2) {
                formatado =
                    numeros.slice(0, 2) + '/' +
                    numeros.slice(2);
            }

            campo.value = formatado;
        });
    }

    campoGenero.addEventListener('change', atualizarCamposGravidez);

    checkboxGestante.addEventListener('change', function () {
        atualizarCampoMenstruacao();
        atualizarResumoGravidez();
    });

    aplicarMascaraData(document.getElementById('data_nascimento'));
    aplicarMascaraData(dataUltimaMenstruacao);

    atualizarCamposGravidez();

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tipoMensagem === 'erro'): ?>
    abrirEdicao();
    <?php endif; ?>
})();
</script>
</body>
</html>