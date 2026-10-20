<?php
/*
 * Saúde-Conecta — Saúde Criança
 * Arquivo: frontend/src/pages/saude_crianca.php
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
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function idUsuarioAtual(array $usuario): int
{
    foreach (['id', 'id_usuario', 'usuario_id'] as $campo) {
        if (isset($usuario[$campo]) && is_numeric($usuario[$campo])) {
            return (int)$usuario[$campo];
        }
    }
    return 0;
}

function nomeResponsavelAtual(array $usuario): string
{
    foreach (['nomeCompleto', 'nome_completo', 'nome', 'name'] as $campo) {
        if (!empty($usuario[$campo])) {
            return (string)$usuario[$campo];
        }
    }
    return 'Responsável';
}

function converterDataBanco(string $valor, string $campo): ?string
{
    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $data = DateTimeImmutable::createFromFormat('!d/m/Y', $valor);
    $erros = DateTimeImmutable::getLastErrors();

    $semErros = $erros === false ||
        ($erros['warning_count'] === 0 && $erros['error_count'] === 0);

    if (!$data || !$semErros || $data->format('d/m/Y') !== $valor) {
        throw new RuntimeException(
            'Data inválida em "' . $campo . '". Use DD/MM/AAAA.'
        );
    }

    if ($data > new DateTimeImmutable('today')) {
        throw new RuntimeException(
            'A data em "' . $campo . '" não pode estar no futuro.'
        );
    }

    return $data->format('Y-m-d');
}

function dataBrasileira($valor): string
{
    if (empty($valor)) {
        return '';
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$valor);

    return $data ? $data->format('d/m/Y') : (string)$valor;
}

function calcularIdade($nascimento): string
{
    if (empty($nascimento)) {
        return 'Não informada';
    }

    try {
        $data = new DateTimeImmutable($nascimento);
        $hoje = new DateTimeImmutable('today');

        if ($data > $hoje) {
            return 'Data inválida';
        }

        $idade = $data->diff($hoje);

        if ($idade->y > 0) {
            return $idade->y . ($idade->y === 1 ? ' ano' : ' anos')
                . ($idade->m > 0 ? ' e ' . $idade->m . ' meses' : '');
        }

        if ($idade->m > 0) {
            return $idade->m . ($idade->m === 1 ? ' mês' : ' meses');
        }

        return $idade->d . ($idade->d === 1 ? ' dia' : ' dias');
    } catch (Throwable $e) {
        return 'Não informada';
    }
}

$idUsuario = idUsuarioAtual($usuario);
$nomeResponsavel = nomeResponsavelAtual($usuario);

if ($idUsuario <= 0) {
    http_response_code(401);
    exit('Sessão inválida. Faça login novamente.');
}

if (empty($_SESSION['csrf_saude_crianca'])) {
    $_SESSION['csrf_saude_crianca'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['csrf_saude_crianca'];
$mensagem = '';
$tipoMensagem = '';
$acao = (string)($_GET['acao'] ?? 'listar');
$idCrianca = (int)($_GET['id'] ?? 0);
$idVacina = (int)($_GET['id_vacina'] ?? 0);

$criancas = [];
$crianca = null;
$vacinas = [];
$vacinaEdicao = null;

try {
    /*
     * Tabela exclusiva dos perfis infantis.
     * Não utiliza a tabela perfil_fisico do adulto.
     */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS criancas_saude (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(180) NOT NULL,
            data_nascimento DATE NOT NULL,
            sexo VARCHAR(40) NULL,
            cpf VARCHAR(20) NULL,
            cartao_sus VARCHAR(40) NULL,
            nome_responsavel VARCHAR(180) NOT NULL,
            parentesco VARCHAR(80) NULL,
            telefone_responsavel VARCHAR(40) NULL,
            tipo_sanguineo VARCHAR(10) NULL,
            pediatra VARCHAR(180) NULL,
            contato_pediatra VARCHAR(120) NULL,
            alergias TEXT NULL,
            medicamentos TEXT NULL,
            condicoes_saude TEXT NULL,
            alimentacao TEXT NULL,
            desenvolvimento TEXT NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_criancas_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
     * Cartão de vacinação infantil independente.
     */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS criancas_vacinacoes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            crianca_id BIGINT UNSIGNED NOT NULL,
            nome_vacina VARCHAR(180) NOT NULL,
            data_aplicacao DATE NOT NULL,
            dose VARCHAR(80) NULL,
            unidade_saude VARCHAR(180) NULL,
            lote VARCHAR(80) NULL,
            observacoes TEXT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_crianca_vacinas (usuario_id, crianca_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tokenEnviado = (string)($_POST['csrf_token'] ?? '');

        if (!hash_equals($token, $tokenEnviado)) {
            throw new RuntimeException(
                'O formulário expirou. Atualize a página e tente novamente.'
            );
        }

        $acaoPost = (string)($_POST['acao_form'] ?? '');

        /*
         * CADASTRAR OU EDITAR CRIANÇA
         */
        if ($acaoPost === 'salvar_crianca') {
            $idSalvar = (int)($_POST['id'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $responsavel = trim((string)($_POST['nome_responsavel'] ?? ''));

            if ($nome === '') {
                throw new RuntimeException('Informe o nome da criança.');
            }

            if ($responsavel === '') {
                throw new RuntimeException('Informe o nome do responsável.');
            }

            $nascimento = converterDataBanco(
                (string)($_POST['data_nascimento'] ?? ''),
                'Data de nascimento'
            );

            if (!$nascimento) {
                throw new RuntimeException('Informe a data de nascimento.');
            }

            $dados = [
                'nome' => $nome,
                'data_nascimento' => $nascimento,
                'sexo' => trim((string)($_POST['sexo'] ?? '')) ?: null,
                'cpf' => trim((string)($_POST['cpf'] ?? '')) ?: null,
                'cartao_sus' => trim((string)($_POST['cartao_sus'] ?? '')) ?: null,
                'nome_responsavel' => $responsavel,
                'parentesco' => trim((string)($_POST['parentesco'] ?? '')) ?: null,
                'telefone_responsavel' => trim((string)($_POST['telefone_responsavel'] ?? '')) ?: null,
                'tipo_sanguineo' => trim((string)($_POST['tipo_sanguineo'] ?? '')) ?: null,
                'pediatra' => trim((string)($_POST['pediatra'] ?? '')) ?: null,
                'contato_pediatra' => trim((string)($_POST['contato_pediatra'] ?? '')) ?: null,
                'alergias' => trim((string)($_POST['alergias'] ?? '')) ?: null,
                'medicamentos' => trim((string)($_POST['medicamentos'] ?? '')) ?: null,
                'condicoes_saude' => trim((string)($_POST['condicoes_saude'] ?? '')) ?: null,
                'alimentacao' => trim((string)($_POST['alimentacao'] ?? '')) ?: null,
                'desenvolvimento' => trim((string)($_POST['desenvolvimento'] ?? '')) ?: null,
                'observacoes' => trim((string)($_POST['observacoes'] ?? '')) ?: null
            ];

            if ($idSalvar > 0) {
                $stmt = $pdo->prepare(
                    'SELECT id FROM criancas_saude
                     WHERE id = :id AND usuario_id = :uid'
                );
                $stmt->execute(['id' => $idSalvar, 'uid' => $idUsuario]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Perfil infantil não encontrado.');
                }

                $sets = [];

                foreach (array_keys($dados) as $campo) {
                    $sets[] = "`$campo` = :$campo";
                }

                $dados['id'] = $idSalvar;
                $dados['uid'] = $idUsuario;

                $stmt = $pdo->prepare(
                    'UPDATE criancas_saude SET ' . implode(', ', $sets) .
                    ' WHERE id = :id AND usuario_id = :uid'
                );
                $stmt->execute($dados);
                $idCrianca = $idSalvar;
                $mensagem = 'Perfil infantil atualizado.';
            } else {
                $dados['usuario_id'] = $idUsuario;
                $colunas = array_keys($dados);
                $marcadores = array_map(fn($c) => ':' . $c, $colunas);

                $stmt = $pdo->prepare(
                    'INSERT INTO criancas_saude (`' .
                    implode('`,`', $colunas) . '`) VALUES (' .
                    implode(',', $marcadores) . ')'
                );
                $stmt->execute($dados);

                $idCrianca = (int)$pdo->lastInsertId();
                $mensagem = 'Perfil infantil cadastrado.';
            }

            $tipoMensagem = 'sucesso';
            $acao = 'ver';
        }

        /*
         * ADICIONAR OU EDITAR VACINAÇÃO INFANTIL
         */
        elseif ($acaoPost === 'salvar_vacina') {
            $criancaIdPost = (int)($_POST['crianca_id'] ?? 0);
            $idVacinaPost = (int)($_POST['id_vacina'] ?? 0);

            $stmt = $pdo->prepare(
                'SELECT id FROM criancas_saude
                 WHERE id = :id AND usuario_id = :uid'
            );
            $stmt->execute([
                'id' => $criancaIdPost,
                'uid' => $idUsuario
            ]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Criança não encontrada.');
            }

            $nomeVacina = trim((string)($_POST['nome_vacina'] ?? ''));

            if ($nomeVacina === '') {
                throw new RuntimeException('Informe o nome da vacina.');
            }

            $dataAplicacao = converterDataBanco(
                (string)($_POST['data_aplicacao'] ?? ''),
                'Data de aplicação'
            );

            if (!$dataAplicacao) {
                throw new RuntimeException('Informe a data de aplicação.');
            }

            $dados = [
                'usuario_id' => $idUsuario,
                'crianca_id' => $criancaIdPost,
                'nome_vacina' => $nomeVacina,
                'data_aplicacao' => $dataAplicacao,
                'dose' => trim((string)($_POST['dose'] ?? '')) ?: null,
                'unidade_saude' => trim((string)($_POST['unidade_saude'] ?? '')) ?: null,
                'lote' => trim((string)($_POST['lote'] ?? '')) ?: null,
                'observacoes' => trim((string)($_POST['observacoes'] ?? '')) ?: null
            ];

            if ($idVacinaPost > 0) {
                $dados['id'] = $idVacinaPost;

                $stmt = $pdo->prepare(
                    'UPDATE criancas_vacinacoes SET
                     nome_vacina = :nome_vacina,
                     data_aplicacao = :data_aplicacao,
                     dose = :dose,
                     unidade_saude = :unidade_saude,
                     lote = :lote,
                     observacoes = :observacoes
                     WHERE id = :id AND crianca_id = :crianca_id
                     AND usuario_id = :usuario_id'
                );
                $stmt->execute($dados);
                $mensagem = 'Vacinação infantil atualizada.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO criancas_vacinacoes
                    (usuario_id, crianca_id, nome_vacina, data_aplicacao,
                     dose, unidade_saude, lote, observacoes)
                    VALUES
                    (:usuario_id, :crianca_id, :nome_vacina, :data_aplicacao,
                     :dose, :unidade_saude, :lote, :observacoes)'
                );
                $stmt->execute($dados);
                $mensagem = 'Vacinação infantil adicionada.';
            }

            $idCrianca = $criancaIdPost;
            $acao = 'ver';
            $tipoMensagem = 'sucesso';
        }

        /*
         * EXCLUIR VACINAÇÃO
         */
        elseif ($acaoPost === 'excluir_vacina') {
            $criancaIdPost = (int)($_POST['crianca_id'] ?? 0);
            $idVacinaPost = (int)($_POST['id_vacina'] ?? 0);

            $stmt = $pdo->prepare(
                'DELETE FROM criancas_vacinacoes
                 WHERE id = :id AND crianca_id = :cid AND usuario_id = :uid'
            );
            $stmt->execute([
                'id' => $idVacinaPost,
                'cid' => $criancaIdPost,
                'uid' => $idUsuario
            ]);

            $idCrianca = $criancaIdPost;
            $acao = 'ver';
            $tipoMensagem = 'sucesso';
            $mensagem = 'Registro de vacinação excluído.';
        }

        /*
         * EXCLUIR PERFIL INFANTIL E SUAS VACINAS
         */
        elseif ($acaoPost === 'excluir_crianca') {
            $idExcluir = (int)($_POST['id'] ?? 0);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'DELETE FROM criancas_vacinacoes
                 WHERE crianca_id = :id AND usuario_id = :uid'
            );
            $stmt->execute(['id' => $idExcluir, 'uid' => $idUsuario]);

            $stmt = $pdo->prepare(
                'DELETE FROM criancas_saude
                 WHERE id = :id AND usuario_id = :uid'
            );
            $stmt->execute(['id' => $idExcluir, 'uid' => $idUsuario]);

            $pdo->commit();

            $idCrianca = 0;
            $acao = 'listar';
            $tipoMensagem = 'sucesso';
            $mensagem = 'Perfil infantil excluído.';
        }

        $_SESSION['csrf_saude_crianca'] = bin2hex(random_bytes(32));
        $token = $_SESSION['csrf_saude_crianca'];
    }

    /*
     * CARREGAR CRIANÇA SELECIONADA
     */
    if (in_array($acao, ['ver', 'editar', 'vacina'], true) && $idCrianca > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM criancas_saude
             WHERE id = :id AND usuario_id = :uid LIMIT 1'
        );
        $stmt->execute(['id' => $idCrianca, 'uid' => $idUsuario]);
        $crianca = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$crianca) {
            $acao = 'listar';
            $idCrianca = 0;
            $mensagem = 'Perfil infantil não encontrado.';
            $tipoMensagem = 'erro';
        }
    }

    /*
     * CARREGAR VACINAS DA CRIANÇA
     */
    if ($crianca) {
        $stmt = $pdo->prepare(
            'SELECT * FROM criancas_vacinacoes
             WHERE crianca_id = :cid AND usuario_id = :uid
             ORDER BY data_aplicacao DESC, id DESC'
        );
        $stmt->execute(['cid' => $idCrianca, 'uid' => $idUsuario]);
        $vacinas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($acao === 'vacina' && $idVacina > 0) {
            $stmt = $pdo->prepare(
                'SELECT * FROM criancas_vacinacoes
                 WHERE id = :id AND crianca_id = :cid AND usuario_id = :uid'
            );
            $stmt->execute([
                'id' => $idVacina,
                'cid' => $idCrianca,
                'uid' => $idUsuario
            ]);
            $vacinaEdicao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    /*
     * LISTAR CRIANÇAS DO RESPONSÁVEL
     */
    $stmt = $pdo->prepare(
        'SELECT * FROM criancas_saude
         WHERE usuario_id = :uid ORDER BY nome ASC LIMIT 200'
    );
    $stmt->execute(['uid' => $idUsuario]);
    $criancas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('saude_crianca.php: ' . $e->getMessage());
    $mensagem = 'Erro ao processar. Verifique a conexão com o banco de dados.';
    $tipoMensagem = 'erro';
}

$mostrarFormulario = $acao === 'adicionar' || $acao === 'editar';
$mostrarVacina = $acao === 'vacina' && $crianca;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Saúde Criança | Saúde-Conecta</title>
<style>
:root {
    --verde: #087f8c;
    --verdeescuro: #066773;
    --fundo: #f1f6fa;
    --texto: #18394e;
    --borda: #e3ebf1;
    --muted: #60716b;
    --vermelho: #b42318;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: var(--fundo);
    color: var(--texto);
    font-family: Arial, Helvetica, sans-serif;
}
header {
    background: #fff;
    border-bottom: 1px solid var(--borda);
    padding: 16px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.marca {
    color: var(--verde);
    font-weight: 800;
    font-size: 20px;
    text-decoration: none;
}
main { width: min(1120px, 94%); margin: 28px auto 40px; }
.voltar {
    display: inline-block;
    color: var(--verde);
    font-weight: bold;
    text-decoration: none;
    margin-bottom: 18px;
}
.hero {
    background: linear-gradient(135deg, #e1f5f6, #fff);
    border: 1px solid #c9e9eb;
    border-radius: 18px;
    padding: 26px;
    margin-bottom: 20px;
}
.hero h1 { margin: 0 0 10px; font-size: clamp(25px, 4vw, 34px); }
.hero p, .subtitulo { color: var(--muted); line-height: 1.65; }
.painel {
    background: #fff;
    border: 1px solid var(--borda);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 18px;
    box-shadow: 0 5px 20px rgba(20,55,45,.035);
}
.painel h2 { margin: 0 0 12px; font-size: 21px; }
.grade-resumo, .grade-cartoes, .lista-criancas {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 18px 0;
}
.grade-cartoes, .lista-criancas { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.resumo, .cartao, .crianca {
    border: 1px solid var(--borda);
    border-radius: 13px;
    padding: 18px;
    background: #fff;
}
.resumo strong { display: block; font-size: 22px; color: var(--verde); margin-bottom: 6px; }
.resumo span, .cartao p, .crianca p { color: var(--muted); font-size: 14px; line-height: 1.6; }
.cartao h3, .crianca h3 { margin: 0 0 9px; }
.cartao p, .crianca p { margin: 5px 0; }
.btn {
    display: inline-block;
    padding: 10px 14px;
    background: var(--verde);
    color: #fff;
    border: 0;
    border-radius: 9px;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
    cursor: pointer;
}
.btn.secundario { background: #e8f2f3; color: var(--verdeescuro); }
.btn.perigo { background: var(--vermelho); }
.btn.pequeno { padding: 7px 10px; font-size: 12px; }
.acoes { display: flex; gap: 9px; flex-wrap: wrap; margin-top: 18px; }
.acoes form { margin: 0; }
.aviso {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 18px;
    background: #fff5f2;
    border: 1px solid #f1d2c8;
    line-height: 1.5;
}
.aviso.sucesso { background: #effaf3; border-color: #bfe8cf; color: #246c43; }
.form-grade { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 18px; }
.campo { margin-bottom: 3px; }
.campo.largo { grid-column: 1 / -1; }
.campo label { display: block; font-weight: bold; font-size: 13px; margin-bottom: 6px; }
.campo input, .campo select, .campo textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #dce7ed;
    border-radius: 9px;
    font: inherit;
    font-size: 14px;
    background: #fff;
    color: var(--texto);
}
.campo textarea { min-height: 88px; resize: vertical; }
.obrigatorio { color: var(--vermelho); }
.dica { font-size: 13px; color: var(--muted); line-height: 1.6; }
.tabela-wrap { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th, td { text-align: left; padding: 11px; border-bottom: 1px solid var(--borda); vertical-align: top; }
th { background: #edf6f7; color: var(--verdeescuro); }
.vazio { text-align: center; padding: 26px 12px; color: var(--muted); line-height: 1.6; }
footer { text-align: center; padding: 24px; color: #708079; font-size: 13px; }
@media (max-width: 700px) {
    .grade-resumo, .grade-cartoes, .lista-criancas, .form-grade { grid-template-columns: 1fr; }
    .campo.largo { grid-column: auto; }
    .painel { padding: 18px; }
    .hero { padding: 20px; }
}
</style>
</head>
<body>
<header>
    <a class="marca" href="perfil_saude.php">Saúde-Conecta</a>
    <span>Responsável: <?= h($nomeResponsavel) ?></span>
</header>

<main>
    <a class="voltar" href="perfil_saude.php">← Voltar para Minhas informações de Saúde</a>

    <section class="hero">
        <h1>🧒 Saúde Criança</h1>
        <p>
            Área exclusiva para acompanhar a saúde de crianças sob sua responsabilidade.
            Cadastre perfis infantis, informações pediátricas e vacinas sem misturar esses
            dados com o seu perfil pessoal.
        </p>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="aviso <?= h($tipoMensagem) ?>" role="status"><?= h($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($mostrarFormulario): ?>
        <?php $edicao = $acao === 'editar' ? $crianca : null; ?>
        <section class="painel">
            <h2><?= $edicao ? 'Editar perfil infantil' : 'Cadastrar criança' ?></h2>
            <p class="subtitulo">Os campos sem asterisco são opcionais.</p>

            <form method="post" action="saude_crianca.php">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="acao_form" value="salvar_crianca">
                <input type="hidden" name="id" value="<?= (int)($edicao['id'] ?? 0) ?>">

                <div class="form-grade">
                    <div class="campo">
                        <label for="nome">Nome completo da criança <span class="obrigatorio">*</span></label>
                        <input id="nome" name="nome" required maxlength="180" value="<?= h($edicao['nome'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="data_nascimento">Data de nascimento <span class="obrigatorio">*</span></label>
                        <input type="text" id="data_nascimento" name="data_nascimento"
                               required placeholder="DD/MM/AAAA" inputmode="numeric" maxlength="10"
                               pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}"
                               value="<?= h(dataBrasileira($edicao['data_nascimento'] ?? '')) ?>">
                    </div>

                    <div class="campo">
                        <label for="sexo">Sexo</label>
                        <?php $sexoAtual = $edicao['sexo'] ?? ''; ?>
                        <select id="sexo" name="sexo">
                            <option value="">Não informado</option>
                            <option value="Feminino" <?= $sexoAtual === 'Feminino' ? 'selected' : '' ?>>Feminino</option>
                            <option value="Masculino" <?= $sexoAtual === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="Não informado" <?= $sexoAtual === 'Não informado' ? 'selected' : '' ?>>Prefiro não informar</option>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="tipo_sanguineo">Tipo sanguíneo</label>
                        <?php $sangueAtual = $edicao['tipo_sanguineo'] ?? ''; ?>
                        <select id="tipo_sanguineo" name="tipo_sanguineo">
                            <option value="">Não informado</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $sangue): ?>
                                <option value="<?= h($sangue) ?>" <?= $sangueAtual === $sangue ? 'selected' : '' ?>><?= h($sangue) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="cpf">CPF da criança</label>
                        <input id="cpf" name="cpf" maxlength="20" value="<?= h($edicao['cpf'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="cartao_sus">Número do Cartão SUS</label>
                        <input id="cartao_sus" name="cartao_sus" maxlength="40" value="<?= h($edicao['cartao_sus'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="nome_responsavel">Nome do responsável <span class="obrigatorio">*</span></label>
                        <input id="nome_responsavel" name="nome_responsavel" required maxlength="180"
                               value="<?= h($edicao['nome_responsavel'] ?? $nomeResponsavel) ?>">
                    </div>

                    <div class="campo">
                        <label for="parentesco">Parentesco</label>
                        <?php $parentescoAtual = $edicao['parentesco'] ?? ''; ?>
                        <select id="parentesco" name="parentesco">
                            <option value="">Selecione</option>
                            <?php foreach (['Mãe', 'Pai', 'Avó', 'Avô', 'Tutor(a)', 'Responsável legal', 'Outro'] as $parentesco): ?>
                                <option value="<?= h($parentesco) ?>" <?= $parentescoAtual === $parentesco ? 'selected' : '' ?>><?= h($parentesco) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="telefone_responsavel">Telefone do responsável</label>
                        <input id="telefone_responsavel" name="telefone_responsavel" maxlength="40"
                               placeholder="(00) 00000-0000" value="<?= h($edicao['telefone_responsavel'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="pediatra">Pediatra / profissional de referência</label>
                        <input id="pediatra" name="pediatra" maxlength="180" value="<?= h($edicao['pediatra'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="contato_pediatra">Contato do pediatra ou unidade de saúde</label>
                        <input id="contato_pediatra" name="contato_pediatra" maxlength="120" value="<?= h($edicao['contato_pediatra'] ?? '') ?>">
                    </div>

                    <div class="campo largo">
                        <label for="alergias">Alergias conhecidas</label>
                        <textarea id="alergias" name="alergias" placeholder="Substância, sintomas e orientações profissionais."><?= h($edicao['alergias'] ?? '') ?></textarea>
                    </div>

                    <div class="campo largo">
                        <label for="medicamentos">Medicamentos em uso</label>
                        <textarea id="medicamentos" name="medicamentos" placeholder="Registre conforme a prescrição profissional."><?= h($edicao['medicamentos'] ?? '') ?></textarea>
                    </div>

                    <div class="campo largo">
                        <label for="condicoes_saude">Doenças e condições de saúde</label>
                        <textarea id="condicoes_saude" name="condicoes_saude" placeholder="Diagnósticos confirmados e informações relevantes."><?= h($edicao['condicoes_saude'] ?? '') ?></textarea>
                    </div>

                    <div class="campo largo">
                        <label for="alimentacao">Alimentação e nutrição</label>
                        <textarea id="alimentacao" name="alimentacao" placeholder="Amamentação, alimentação complementar, restrições ou dificuldades."><?= h($edicao['alimentacao'] ?? '') ?></textarea>
                    </div>

                    <div class="campo largo">
                        <label for="desenvolvimento">Crescimento e desenvolvimento</label>
                        <textarea id="desenvolvimento" name="desenvolvimento" placeholder="Observações sobre sono, fala, mobilidade e desenvolvimento."><?= h($edicao['desenvolvimento'] ?? '') ?></textarea>
                    </div>

                    <div class="campo largo">
                        <label for="observacoes">Observações para consultas</label>
                        <textarea id="observacoes" name="observacoes" placeholder="Dúvidas para o pediatra e outras informações importantes."><?= h($edicao['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>

                <p class="dica">Guarde somente informações necessárias e mantenha os dados da criança protegidos.</p>

                <div class="acoes">
                    <button class="btn" type="submit">Salvar perfil infantil</button>
                    <a class="btn secundario" href="saude_crianca.php">Cancelar</a>
                </div>
            </form>
        </section>

    <?php elseif ($mostrarVacina): ?>
        <section class="painel">
            <h2><?= $vacinaEdicao ? 'Editar vacinação infantil' : 'Adicionar vacinação infantil' ?></h2>
            <p class="subtitulo">Criança: <strong><?= h($crianca['nome']) ?></strong></p>

            <form method="post" action="saude_crianca.php?acao=ver&id=<?= (int)$idCrianca ?>">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                <input type="hidden" name="acao_form" value="salvar_vacina">
                <input type="hidden" name="crianca_id" value="<?= (int)$idCrianca ?>">
                <input type="hidden" name="id_vacina" value="<?= (int)($vacinaEdicao['id'] ?? 0) ?>">

                <div class="form-grade">
                    <div class="campo">
                        <label for="nome_vacina">Nome da vacina <span class="obrigatorio">*</span></label>
                        <input id="nome_vacina" name="nome_vacina" required maxlength="180" value="<?= h($vacinaEdicao['nome_vacina'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="data_aplicacao">Data de aplicação <span class="obrigatorio">*</span></label>
                        <input type="text" id="data_aplicacao" name="data_aplicacao" required
                               placeholder="DD/MM/AAAA" inputmode="numeric" maxlength="10"
                               pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}"
                               value="<?= h(dataBrasileira($vacinaEdicao['data_aplicacao'] ?? '')) ?>">
                    </div>

                    <div class="campo">
                        <label for="dose">Dose</label>
                        <input id="dose" name="dose" maxlength="80" placeholder="Ex.: 1ª dose, reforço" value="<?= h($vacinaEdicao['dose'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="unidade_saude">Unidade de saúde</label>
                        <input id="unidade_saude" name="unidade_saude" maxlength="180" value="<?= h($vacinaEdicao['unidade_saude'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label for="lote">Lote da vacina</label>
                        <input id="lote" name="lote" maxlength="80" value="<?= h($vacinaEdicao['lote'] ?? '') ?>">
                    </div>

                    <div class="campo largo">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes"><?= h($vacinaEdicao['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="acoes">
                    <button class="btn" type="submit">Salvar vacinação</button>
                    <a class="btn secundario" href="saude_crianca.php?acao=ver&id=<?= (int)$idCrianca ?>">Cancelar</a>
                </div>
            </form>
        </section>

    <?php elseif ($acao === 'ver' && $crianca): ?>
        <section class="painel">
            <h2>🧒 <?= h($crianca['nome']) ?></h2>
            <p class="subtitulo">
                Idade: <strong><?= h(calcularIdade($crianca['data_nascimento'])) ?></strong>
                · Nascimento: <?= h(dataBrasileira($crianca['data_nascimento'])) ?>
            </p>

            <div class="grade-cartoes">
                <article class="cartao">
                    <h3>👨‍👩‍👧 Responsável</h3>
                    <p><?= h($crianca['nome_responsavel']) ?><br>
                    <?= h($crianca['parentesco'] ?: 'Parentesco não informado') ?><br>
                    <?= h($crianca['telefone_responsavel'] ?: 'Telefone não informado') ?></p>
                </article>

                <article class="cartao">
                    <h3>🩸 Dados básicos</h3>
                    <p>Sexo: <?= h($crianca['sexo'] ?: 'Não informado') ?><br>
                    Tipo sanguíneo: <?= h($crianca['tipo_sanguineo'] ?: 'Não informado') ?><br>
                    Cartão SUS: <?= h($crianca['cartao_sus'] ?: 'Não informado') ?></p>
                </article>

                <article class="cartao">
                    <h3>🩺 Pediatra</h3>
                    <p><?= nl2br(h($crianca['pediatra'] ?: 'Profissional não informado')) ?><br>
                    <?= nl2br(h($crianca['contato_pediatra'] ?: 'Contato não informado')) ?></p>
                </article>

                <article class="cartao">
                    <h3>⚠️ Alergias</h3>
                    <p><?= nl2br(h($crianca['alergias'] ?: 'Nenhuma informação cadastrada. Isso não significa ausência de alergias.')) ?></p>
                </article>

                <article class="cartao">
                    <h3>💊 Medicamentos</h3>
                    <p><?= nl2br(h($crianca['medicamentos'] ?: 'Nenhuma informação cadastrada.')) ?></p>
                </article>

                <article class="cartao">
                    <h3>📋 Condições de saúde</h3>
                    <p><?= nl2br(h($crianca['condicoes_saude'] ?: 'Nenhuma informação cadastrada.')) ?></p>
                </article>

                <article class="cartao">
                    <h3>🥣 Alimentação</h3>
                    <p><?= nl2br(h($crianca['alimentacao'] ?: 'Nenhuma informação cadastrada.')) ?></p>
                </article>

                <article class="cartao">
                    <h3>🌱 Desenvolvimento</h3>
                    <p><?= nl2br(h($crianca['desenvolvimento'] ?: 'Nenhuma informação cadastrada.')) ?></p>
                </article>

                <article class="cartao">
                    <h3>📝 Observações</h3>
                    <p><?= nl2br(h($crianca['observacoes'] ?: 'Nenhuma observação cadastrada.')) ?></p>
                </article>
            </div>

            <div class="acoes">
                <a class="btn" href="saude_crianca.php?acao=editar&id=<?= (int)$idCrianca ?>">Editar informações</a>
                <a class="btn secundario" href="saude_crianca.php?acao=vacina&id=<?= (int)$idCrianca ?>">+ Registrar vacina</a>
                <a class="btn secundario" href="saude_crianca.php">Lista de crianças</a>
            </div>
        </section>

        <section class="painel">
            <h2>💉 Cartão de vacinação infantil</h2>
            <p class="subtitulo">Este cartão pertence à criança selecionada e é separado do cartão do responsável.</p>

            <?php if (!$vacinas): ?>
                <div class="vazio">Nenhuma vacinação cadastrada para esta criança.</div>
            <?php else: ?>
                <div class="tabela-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Vacina</th>
                            <th>Data</th>
                            <th>Dose</th>
                            <th>Unidade de saúde</th>
                            <th>Lote</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vacinas as $v): ?>
                            <tr>
                                <td><?= h($v['nome_vacina']) ?></td>
                                <td><?= h(dataBrasileira($v['data_aplicacao'])) ?></td>
                                <td><?= h($v['dose'] ?: '—') ?></td>
                                <td><?= h($v['unidade_saude'] ?: '—') ?></td>
                                <td><?= h($v['lote'] ?: '—') ?></td>
                                <td>
                                    <div class="acoes">
                                        <a class="btn secundario pequeno"
                                           href="saude_crianca.php?acao=vacina&id=<?= (int)$idCrianca ?>&id_vacina=<?= (int)$v['id'] ?>">
                                            Editar
                                        </a>

                                        <form method="post"
                                              action="saude_crianca.php?acao=ver&id=<?= (int)$idCrianca ?>"
                                              onsubmit="return confirm('Excluir esta vacinação?');">
                                            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                            <input type="hidden" name="acao_form" value="excluir_vacina">
                                            <input type="hidden" name="crianca_id" value="<?= (int)$idCrianca ?>">
                                            <input type="hidden" name="id_vacina" value="<?= (int)$v['id'] ?>">
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
        </section>

    <?php else: ?>
        <div class="grade-resumo">
            <div class="resumo">
                <strong><?= count($criancas) ?></strong>
                <span>Perfil(is) infantil(is) cadastrado(s)</span>
            </div>
            <div class="resumo">
                <strong>Vacinação</strong>
                <span>Cartão independente para cada criança</span>
            </div>
            <div class="resumo">
                <strong>Desenvolvimento</strong>
                <span>Área para anotações de crescimento e alimentação</span>
            </div>
        </div>

        <section class="painel">
            <h2>Perfis infantis</h2>
            <p class="subtitulo">Cada criança possui um perfil independente do seu perfil pessoal de saúde.</p>

            <div class="acoes" style="margin-top:0;margin-bottom:18px">
                <a class="btn" href="saude_crianca.php?acao=adicionar">+ Cadastrar criança</a>
            </div>

            <?php if (!$criancas): ?>
                <div class="vazio">
                    Você ainda não cadastrou uma criança.<br>
                    Clique em “Cadastrar criança” para começar.
                </div>
            <?php else: ?>
                <div class="lista-criancas">
                    <?php foreach ($criancas as $c): ?>
                        <article class="crianca">
                            <h3>🧒 <?= h($c['nome']) ?></h3>
                            <p><strong>Idade:</strong> <?= h(calcularIdade($c['data_nascimento'])) ?></p>
                            <p><strong>Nascimento:</strong> <?= h(dataBrasileira($c['data_nascimento'])) ?></p>
                            <p><strong>Responsável:</strong> <?= h($c['nome_responsavel']) ?></p>

                            <div class="acoes">
                                <a class="btn" href="saude_crianca.php?acao=ver&id=<?= (int)$c['id'] ?>">Abrir perfil</a>
                                <a class="btn secundario" href="saude_crianca.php?acao=editar&id=<?= (int)$c['id'] ?>">Editar</a>

                                <form method="post" action="saude_crianca.php"
                                      onsubmit="return confirm('Excluir este perfil e suas vacinas? Essa ação não pode ser desfeita.');">
                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                    <input type="hidden" name="acao_form" value="excluir_crianca">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn perigo" type="submit">Excluir</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="painel">
            <h2>Recursos de Saúde Criança</h2>
            <div class="grade-cartoes">
                <article class="cartao">
                    <h3>💉 Vacinação infantil</h3>
                    <p>Registre vacina, data de aplicação, dose, unidade de saúde e lote.</p>
                </article>
                <article class="cartao">
                    <h3>📏 Crescimento e desenvolvimento</h3>
                    <p>Guarde observações para conversar com o pediatra.</p>
                </article>
                <article class="cartao">
                    <h3>🥣 Alimentação</h3>
                    <p>Registre informações sobre alimentação, restrições e dificuldades.</p>
                </article>
                <article class="cartao">
                    <h3>⚠️ Alergias e medicamentos</h3>
                    <p>Mantenha informações importantes acessíveis durante as consultas.</p>
                </article>
            </div>
        </section>
    <?php endif; ?>

    <section class="painel">
        <p class="dica">
            <strong>Importante:</strong> esta área organiza informações fornecidas pelo responsável.
            Não substitui a caderneta oficial, o acompanhamento pediátrico nem as orientações do SUS.
            Confirme o calendário vacinal com a equipe de saúde. Esta página não consulta
            automaticamente o Meu SUS Digital nem envia lembretes.
        </p>
        <div class="acoes">
            <a class="btn secundario" href="perfil_saude.php">Minhas informações de Saúde</a>
        </div>
    </section>
</main>

<footer>Saúde-Conecta — acompanhamento de saúde infantil.</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[placeholder="DD/MM/AAAA"]').forEach(function (campo) {
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
});
</script>
</body>
</html>