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
    --fundo: #f1f6fa;
    --texto: #18394e;
    --secundario: #718493;
    --verde: #087f8c;
    --verde-escuro: #066773;
    --borda: #e3ebf1;
    --sombra: 0 7px 24px rgba(28,63,84,.055);
    --raio: 18px;
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
    font-family: Inter, "Segoe UI", Arial, sans-serif;
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

.topo {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(255,255,255,.96);
    border-bottom: 1px solid var(--borda);
    backdrop-filter: blur(14px);
}

.topo-conteudo {
    width: min(1240px, calc(100% - 40px));
    min-height: 70px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.marca {
    display: inline-flex;
    align-items: center;
    gap: 11px;
    font-size: 19px;
    font-weight: 850;
    letter-spacing: -.5px;
}

.marca-icone {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: linear-gradient(135deg,#087f8c,#24adb0);
    color: #fff;
}

.marca-icone svg { width: 25px; height: 25px; }
.marca-texto span { color: var(--verde); }

.menu-topo {
    display: flex;
    align-items: center;
    gap: 22px;
}

.menu-topo a {
    color: #627887;
    font-size: 13px;
    font-weight: 700;
}

.menu-topo a:hover { color: var(--verde); }

.botao-sair {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 14px;
    border: 1px solid #f0dede;
    border-radius: 11px;
    background: #fffafa;
    color: #b54747;
    font-size: 12px;
    font-weight: 800;
}

.botao-sair svg { width: 17px; height: 17px; }

.pagina {
    width: min(1240px, calc(100% - 40px));
    margin: 28px auto 48px;
}

.capa {
    position: relative;
    min-height: 180px;
    overflow: hidden;
    border-radius: 22px;
    background:
        radial-gradient(circle at 83% 22%,rgba(255,255,255,.22) 0 58px,transparent 59px),
        radial-gradient(circle at 73% 115%,rgba(255,255,255,.13) 0 130px,transparent 131px),
        linear-gradient(115deg,#075b78 0%,#087f8c 48%,#37b9b3 100%);
}

.capa-conteudo {
    position: relative;
    z-index: 1;
    padding: 30px;
    color: white;
}

.capa-etiqueta {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 10px;
    border: 1px solid rgba(255,255,255,.3);
    border-radius: 30px;
    background: rgba(255,255,255,.12);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.capa-etiqueta svg { width: 15px; height: 15px; }

.capa h1 {
    margin: 12px 0 0;
    font-size: clamp(26px,3vw,35px);
    line-height: 1.2;
    letter-spacing: -.6px;
}

.capa p {
    max-width: 570px;
    margin: 8px 0 0;
    color: rgba(255,255,255,.86);
    font-size: 13px;
}

.perfil-cabecalho {
    display: flex;
    align-items: center;
    gap: 18px;
    min-height: 125px;
    margin-top: 14px;
    padding: 20px 24px;
    border: 1px solid var(--borda);
    border-radius: 18px;
    background: #fff;
    box-shadow: var(--sombra);
}

.avatar {
    width: 82px;
    height: 82px;
    flex: 0 0 82px;
    display: grid;
    place-items: center;
    border: 4px solid #e7f6f4;
    border-radius: 50%;
    background: linear-gradient(145deg,#d8f4ef,#cce8fa);
    color: var(--verde-escuro);
    font-size: 30px;
    font-weight: 850;
}

.perfil-identificacao { min-width: 0; flex: 1; }

.perfil-identificacao h2 {
    margin: 0 0 4px;
    overflow-wrap: anywhere;
    font-size: 21px;
    font-weight: 850;
}

.perfil-identificacao p {
    margin: 0;
    color: var(--secundario);
    font-size: 13px;
}

.perfil-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 9px;
    padding: 5px 9px;
    border-radius: 30px;
    background: #e5f7ee;
    color: #26764e;
    font-size: 10px;
    font-weight: 800;
}

.status-ponto {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #2eaa6d;
}

.layout { display: block; width: 100%; margin-top: 24px; }
.conteudo { width: 100%; min-width: 0; }

.secao {
    margin-bottom: 24px;
    scroll-margin-top: 95px;
}

.secao-titulo {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    margin: 0 0 15px;
}

.secao-titulo h2 {
    margin: 0;
    font-size: 21px;
    font-weight: 850;
    letter-spacing: -.4px;
}

.secao-titulo p {
    margin: 4px 0 0;
    color: var(--secundario);
    font-size: 12px;
}

.contador {
    flex-shrink: 0;
    padding: 6px 10px;
    border: 1px solid var(--borda);
    border-radius: 30px;
    background: #fff;
    color: var(--secundario);
    font-size: 10px;
    font-weight: 800;
}

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
    padding: 21px 22px;
    border-bottom: 1px solid var(--borda);
}

.painel-titulo {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.icone-painel {
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    flex: 0 0 43px;
    border-radius: 13px;
    background: #e0f6f3;
    color: var(--verde);
}

.painel-titulo h3 { margin: 0; font-size: 15px; font-weight: 850; }

.painel-titulo p {
    margin: 3px 0 0;
    color: var(--secundario);
    font-size: 11px;
}

.botao {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 10px 14px;
    border: 1px solid var(--borda);
    border-radius: 10px;
    background: #fff;
    color: var(--texto);
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.botao svg { width: 17px; height: 17px; }

.botao:hover {
    border-color: #b9e0df;
    background: #f3fbfa;
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

.resumo-fisico {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 12px;
    padding: 20px 22px;
}

.dado {
    min-width: 0;
    padding: 15px 13px;
    border: 1px solid #eaf0f3;
    border-radius: 13px;
    background: #fbfdfe;
}

.dado span {
    display: block;
    margin-bottom: 7px;
    color: var(--secundario);
    font-size: 11px;
    font-weight: 650;
}

.dado strong {
    display: block;
    overflow-wrap: anywhere;
    font-size: 17px;
    font-weight: 850;
}

.dado small {
    display: block;
    margin-top: 3px;
    color: #9aa9b2;
    font-size: 10px;
}

/* NOVO: BOTÃO DE ACOMPANHAMENTO DA GRAVIDEZ */
.atalho-gravidez-resumo {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border: 1px solid #c9e8df;
    border-radius: 14px;
    background: linear-gradient(110deg,#effaf5,#f5fbff);
    color: #18394e;
    transition: border-color .2s, transform .2s;
}

.atalho-gravidez-resumo:hover {
    border-color: #087f8c;
    transform: translateY(-1px);
}

.atalho-gravidez-resumo[hidden] {
    display: none !important;
}

.gravidez-resumo-conteudo {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
}

.gravidez-resumo-icone {
    display: grid;
    place-items: center;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: #d9f2e8;
    font-size: 25px;
}

.gravidez-resumo-texto strong {
    display: block;
    font-size: 14px;
    font-weight: 850;
}

.gravidez-resumo-texto small {
    display: block;
    margin-top: 4px;
    color: #718493;
    font-size: 11px;
    line-height: 1.5;
}

.botao-acompanhar-gravidez {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    flex-shrink: 0;
    padding: 11px 15px;
    border-radius: 10px;
    background: #087f8c;
    color: #fff;
    font-size: 12px;
    font-weight: 850;
    text-decoration: none;
}

.botao-acompanhar-gravidez:hover { background: #066773; }

.formulario {
    padding: 22px;
    border-top: 1px solid var(--borda);
    background: #fcfefe;
}

.formulario[hidden],
.gravidez[hidden],
#campo-menstruacao[hidden] {
    display: none !important;
}

.formulario h4 { margin: 0 0 5px; font-size: 16px; }

.ajuda-formulario {
    margin: 0 0 20px;
    color: var(--secundario);
    font-size: 12px;
}

.campos {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 16px;
}

.campo { min-width: 0; }

.campo label {
    display: block;
    margin-bottom: 7px;
    color: #385466;
    font-size: 12px;
    font-weight: 800;
}

.campo input,
.campo select {
    width: 100%;
    min-height: 45px;
    padding: 10px 12px;
    border: 1px solid #dce7ed;
    border-radius: 10px;
    background: #fff;
    color: var(--texto);
    font: inherit;
    font-size: 13px;
}

.campo small {
    display: block;
    margin-top: 5px;
    color: var(--secundario);
    font-size: 10px;
}

.campo-largo { grid-column: 1 / -1; }

.gravidez {
    grid-column: 1 / -1;
    padding: 16px;
    border: 1px solid #d8eeeb;
    border-radius: 13px;
    background: #f0faf8;
}

.gravidez-cabecalho {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.gravidez-cabecalho input {
    width: 19px;
    height: 19px;
    margin-top: 2px;
    accent-color: var(--verde);
    flex: 0 0 auto;
}

.gravidez-cabecalho label { margin: 0; font-size: 13px; }

.gravidez-cabecalho p {
    margin: 4px 0 0;
    color: var(--secundario);
    font-size: 11px;
}

#campo-menstruacao { margin-top: 15px; }

.acoes-formulario {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 21px;
}

.aviso {
    margin: 16px 22px 0;
    padding: 12px 14px;
    border: 1px solid;
    border-radius: 11px;
    font-size: 12px;
}

.aviso.sucesso {
    border-color: #bfe8cf;
    background: #effaf3;
    color: #246c43;
}

.aviso.erro {
    border-color: #f0caca;
    background: #fff4f4;
    color: #a13535;
}

.grade-servicos {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 14px;
}

.janela {
    min-width: 0;
    min-height: 194px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 19px;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    background: #fff;
    box-shadow: var(--sombra);
    transition: transform .2s,box-shadow .2s,border-color .2s;
}

.janela:hover {
    border-color: #c8e5e6;
    box-shadow: 0 12px 28px rgba(28,63,84,.11);
    transform: translateY(-2px);
}

.icone-janela {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    margin-bottom: 14px;
    border-radius: 13px;
}

.icone-janela svg { width: 23px; height: 23px; }

.janela h3 {
    margin: 0 0 7px;
    font-size: 14px;
    font-weight: 850;
    line-height: 1.4;
}

.janela p {
    margin: 0 0 15px;
    color: var(--secundario);
    font-size: 11px;
    line-height: 1.7;
}

.abrir {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: auto;
    color: var(--verde);
    font-size: 11px;
    font-weight: 850;
}

.abrir svg { width: 15px; height: 15px; }

.saude-az .icone-janela { background: #e5f5e9; color: #2e7d4b; }
.mapa .icone-janela { background: #e6f2ff; color: #3477b8; }
.autorizacoes .icone-janela { background: #eceaff; color: #6256b5; }
.pets .icone-janela { background: #fff3dc; color: #ad791e; }
.adocao .icone-janela { background: #ffebf1; color: #c54c7a; }
.crianca .icone-janela { background: #e8f4ff; color: #2a7abf; }
.idoso .icone-janela { background: #f3eef8; color: #7a4ea3; }

.saude-az .abrir { color: #2e7d4b; }
.mapa .abrir { color: #3477b8; }
.autorizacoes .abrir { color: #6256b5; }
.pets .abrir { color: #ad791e; }
.adocao .abrir { color: #c54c7a; }
.crianca .abrir { color: #2a7abf; }
.idoso .abrir { color: #7a4ea3; }

.janela-medica {
    overflow: hidden;
    padding: 0;
    border-color: #c9e8e5;
}

.resumo-medico {
    width: 100%;
    min-height: 194px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 19px;
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
    margin: 0 0 7px;
    font-size: 14px;
    font-weight: 850;
}

.seta-expandir {
    width: 18px;
    height: 18px;
    margin-top: -6px;
    color: var(--verde);
    transition: transform .2s;
}

.janela-medica[open] .seta-expandir { transform: rotate(180deg); }

.opcoes-medicas {
    padding: 5px 13px 12px;
    border-top: 1px solid var(--borda);
    background: #fcfefe;
}

.opcao-medica {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 3px;
    border-bottom: 1px solid #edf2f4;
    flex-wrap: wrap;
}

.opcao-medica:last-child { border-bottom: none; }
.opcao-medica:hover { background: #f0f9f8; }

.opcao-icone {
    width: 33px;
    height: 33px;
    display: grid;
    place-items: center;
    flex: 0 0 33px;
    border-radius: 9px;
}

.opcao-icone svg { width: 18px; height: 18px; }

.opcao-texto { min-width: 120px; flex: 1; }

.opcao-texto strong {
    display: block;
    font-size: 11px;
    font-weight: 850;
}

.opcao-texto small {
    display: block;
    margin-top: 2px;
    color: var(--secundario);
    font-size: 10px;
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
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
    text-decoration: none;
    border: 1px solid var(--borda);
    background: #fff;
    color: var(--texto);
}

.btn-mini svg { width: 13px; height: 13px; }

.btn-mini.ver {
    color: #087f8c;
    border-color: #c9e8e5;
    background: #f0faf8;
}

.btn-mini.add {
    color: #2e7d4b;
    border-color: #c5e6cf;
    background: #effaf3;
}

.btn-mini:hover { filter: brightness(.97); }

.cor-vacina { background: #e0f6f3; color: #087f8c; }
.cor-alergia,.cor-reacao { background: #fff0e4; color: #b96827; }
.cor-doenca { background: #e8efff; color: #4568ad; }
.cor-atestado { background: #e6f6e9; color: #34804b; }

.rodape {
    padding: 22px 20px;
    border-top: 1px solid var(--borda);
    background: #fff;
}

.rodape-conteudo {
    width: min(1240px,100%);
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.rodape-marca {
    color: var(--verde);
    font-size: 12px;
    font-weight: 850;
}

.rodape p {
    margin: 0;
    color: var(--secundario);
    font-size: 11px;
}

@media (max-width: 950px) {
    .menu-topo { gap: 12px; }
    .resumo-fisico { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .janela,.resumo-medico { padding: 16px; }
    .janela-medica { padding: 0; }
}

@media (max-width: 700px) {
    .topo-conteudo {
        width: calc(100% - 26px);
        min-height: 64px;
    }

    .menu-topo { display: none; }

    .pagina {
        width: calc(100% - 26px);
        margin-top: 17px;
    }

    .capa { min-height: 175px; }
    .capa-conteudo { padding: 24px 19px; }
    .capa h1 { font-size: 27px; }
    .capa p { max-width: 90%; font-size: 12px; }

    .perfil-cabecalho {
        min-height: 0;
        padding: 17px;
        gap: 13px;
    }

    .avatar {
        width: 64px;
        height: 64px;
        flex-basis: 64px;
        font-size: 24px;
    }

    .perfil-identificacao h2 { font-size: 17px; }
    .perfil-identificacao p { font-size: 11px; }
    .layout { margin-top: 19px; }
    .secao-titulo h2 { font-size: 19px; }
    .secao-titulo p { font-size: 11px; }
    .painel-topo { padding: 17px; }
    .resumo-fisico { padding: 14px; gap: 9px; }
    .dado { padding: 12px 10px; }
    .dado strong { font-size: 15px; }
    .formulario { padding: 17px; }
    .campos { gap: 13px; }
    .grade-servicos { gap: 10px; }
    .janela { min-height: 202px; padding: 14px; }
    .janela-medica { padding: 0; }
    .resumo-medico { min-height: 202px; padding: 14px; }
    .icone-janela { width: 40px; height: 40px; margin-bottom: 12px; }
    .janela h3,.titulo-medico h3 { font-size: 12px; }
    .janela p { font-size: 10px; }
    .abrir { gap: 4px; font-size: 10px; }
    .contador { font-size: 9px; }

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

@media (max-width: 400px) {
    .marca { gap: 7px; font-size: 15px; }
    .marca-icone { width: 36px; height: 36px; }
    .botao-sair { padding: 9px; }
    .campos { grid-template-columns: minmax(0,1fr); }
    .campo-largo,.gravidez { grid-column: auto; }
    .grade-servicos { grid-template-columns: minmax(0,1fr); }
    .janela,.resumo-medico { min-height: 175px; }
    .janela-medica { min-height: 0; }
    .secao-titulo { align-items: flex-start; }
}

@media (prefers-reduced-motion: reduce) {
    *,*::before,*::after {
        scroll-behavior: auto !important;
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
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

        <nav class="menu-topo" aria-label="Navegação principal">
            <a href="#informacoes-fisicas">Meu espaço</a>
            <a href="#servicos">Serviços</a>
            <a href="saude_a_z.php">Saúde de A a Z</a>
            <a href="mapa.php">Mapa da Saúde</a>
        </nav>

        <a class="botao-sair" href="logout.php">
            <?= icone('sair') ?> Sair
        </a>
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

                        <!-- BOTÃO NOVO: ACOMPANHAR MINHA GRAVIDEZ -->
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

        // NOVO: mostra ou oculta o botão no Resumo físico.
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

            // Insere antes do atalho para manter a ordem do Resumo físico.
            resumoFisico.insertBefore(resumo, atalhoGravidez);
        }

        resumo.querySelector('strong').textContent =
            checkboxGestante.checked ? 'Sim' : 'Não';
    }

    function aplicarMascaraData(campo) {
        if (!campo) {
            return;
        }

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