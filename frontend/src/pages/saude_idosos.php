<?php
require_once __DIR__ . '/../../../backend/config/seguranca.php';
require_once __DIR__ . '/../../../backend/config/database.php';

iniciarSessaoSegura();
$usuario = exigirLogin();

if (is_object($usuario)) {
    $usuario = (array) $usuario;
}

$pdo = Database::getConnection();
$nomeUsuario = $usuario['nome'] ?? $usuario['nome_completo'] ?? 'Usuário';

function h($valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function dataParaBanco(string $data): ?string {
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    $dataObj = DateTime::createFromFormat('!d/m/Y', $data);
    $erros = DateTime::getLastErrors();

    if (
        !$dataObj ||
        ($erros !== false && ($erros['warning_count'] || $erros['error_count'])) ||
        $dataObj->format('d/m/Y') !== $data
    ) {
        throw new RuntimeException('Digite uma data válida no formato DD/MM/AAAA.');
    }

    return $dataObj->format('Y-m-d');
}

function dataParaTela(?string $data): string {
    if (!$data || $data === '0000-00-00') {
        return '';
    }

    $obj = DateTime::createFromFormat('!Y-m-d', $data);
    return $obj ? $obj->format('d/m/Y') : '';
}

$idUsuario = (int)(
    $usuario['id']
    ?? $usuario['usuario_id']
    ?? $usuario['id_usuario']
    ?? $_SESSION['usuario_id']
    ?? 0
);

if ($idUsuario < 1) {
    http_response_code(403);
    exit('Não foi possível identificar o usuário autenticado.');
}

/*
 * Tabelas próprias da área de idosos.
 * Não utiliza as tabelas de registros de adultos.
 */
$pdo->exec("
CREATE TABLE IF NOT EXISTS idosos_perfis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(160) NOT NULL,
    nascimento DATE NULL,
    telefone VARCHAR(30) NULL,
    contato_emergencia VARCHAR(160) NULL,
    telefone_emergencia VARCHAR(30) NULL,
    medico VARCHAR(160) NULL,
    doencas TEXT NULL,
    alergias TEXT NULL,
    mobilidade TEXT NULL,
    observacoes TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_idosos_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS idosos_registros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    idoso_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    data_registro DATE NULL,
    proxima_data DATE NULL,
    resultado VARCHAR(120) NULL,
    frequencia VARCHAR(120) NULL,
    detalhes TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_idosos_registros (usuario_id, idoso_id),
    CONSTRAINT fk_idosos_perfil
        FOREIGN KEY (idoso_id)
        REFERENCES idosos_perfis(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (empty($_SESSION['csrf_idosos'])) {
    $_SESSION['csrf_idosos'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_idosos'];

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Formulário expirado. Atualize a página e tente novamente.');
        }

        $acao = (string)($_POST['acao'] ?? '');

        if ($acao === 'criar_perfil' || $acao === 'editar_perfil') {
            $nome = trim((string)($_POST['nome'] ?? ''));

            if ($nome === '') {
                throw new RuntimeException('Informe o nome completo.');
            }

            $nascimento = dataParaBanco((string)($_POST['nascimento'] ?? ''));

            $dados = [
                $nome,
                $nascimento,
                trim((string)($_POST['telefone'] ?? '')),
                trim((string)($_POST['contato_emergencia'] ?? '')),
                trim((string)($_POST['telefone_emergencia'] ?? '')),
                trim((string)($_POST['medico'] ?? '')),
                trim((string)($_POST['doencas'] ?? '')),
                trim((string)($_POST['alergias'] ?? '')),
                trim((string)($_POST['mobilidade'] ?? '')),
                trim((string)($_POST['observacoes'] ?? ''))
            ];

            if ($acao === 'criar_perfil') {
                $stmt = $pdo->prepare("
                    INSERT INTO idosos_perfis
                    (usuario_id,nome,nascimento,telefone,contato_emergencia,
                     telefone_emergencia,medico,doencas,alergias,mobilidade,observacoes)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute(array_merge([$idUsuario], $dados));
                $mensagem = 'Perfil cadastrado com sucesso.';
            } else {
                $idosoId = (int)($_POST['idoso_id'] ?? 0);

                $stmt = $pdo->prepare("
                    UPDATE idosos_perfis SET
                    nome=?, nascimento=?, telefone=?, contato_emergencia=?,
                    telefone_emergencia=?, medico=?, doencas=?, alergias=?,
                    mobilidade=?, observacoes=?
                    WHERE id=? AND usuario_id=?
                ");

                $stmt->execute(array_merge($dados, [$idosoId, $idUsuario]));
                $mensagem = 'Perfil atualizado.';
            }
        } elseif ($acao === 'excluir_perfil') {
            $idosoId = (int)($_POST['idoso_id'] ?? 0);

            $stmt = $pdo->prepare("
                DELETE FROM idosos_perfis
                WHERE id=? AND usuario_id=?
            ");
            $stmt->execute([$idosoId, $idUsuario]);

            if (!$stmt->rowCount()) {
                throw new RuntimeException('Perfil não encontrado.');
            }

            $mensagem = 'Perfil excluído.';
        } elseif ($acao === 'adicionar_registro') {
            $idosoId = (int)($_POST['idoso_id'] ?? 0);

            $check = $pdo->prepare("
                SELECT id FROM idosos_perfis
                WHERE id=? AND usuario_id=?
            ");
            $check->execute([$idosoId, $idUsuario]);

            if (!$check->fetch()) {
                throw new RuntimeException('Perfil inválido.');
            }

            $tiposPermitidos = [
                'medicamento', 'consulta', 'pressao',
                'glicemia', 'queda', 'lembrete'
            ];

            $tipo = (string)($_POST['tipo'] ?? '');
            $titulo = trim((string)($_POST['titulo'] ?? ''));

            if (!in_array($tipo, $tiposPermitidos, true)) {
                throw new RuntimeException('Tipo de registro inválido.');
            }

            if ($titulo === '') {
                throw new RuntimeException('Informe o título do registro.');
            }

            $data = dataParaBanco((string)($_POST['data_registro'] ?? ''));
            $proxima = dataParaBanco((string)($_POST['proxima_data'] ?? ''));

            $stmt = $pdo->prepare("
                INSERT INTO idosos_registros
                (usuario_id,idoso_id,tipo,titulo,data_registro,
                 proxima_data,resultado,frequencia,detalhes)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $idUsuario,
                $idosoId,
                $tipo,
                $titulo,
                $data,
                $proxima,
                trim((string)($_POST['resultado'] ?? '')),
                trim((string)($_POST['frequencia'] ?? '')),
                trim((string)($_POST['detalhes'] ?? ''))
            ]);

            $mensagem = 'Registro adicionado.';
        } elseif ($acao === 'excluir_registro') {
            $registroId = (int)($_POST['registro_id'] ?? 0);

            $stmt = $pdo->prepare("
                DELETE FROM idosos_registros
                WHERE id=? AND usuario_id=?
            ");
            $stmt->execute([$registroId, $idUsuario]);

            if (!$stmt->rowCount()) {
                throw new RuntimeException('Registro não encontrado.');
            }

            $mensagem = 'Registro excluído.';
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        $erro = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Erro ao acessar o banco de dados. Confira a conexão e as tabelas.';
    }
}

$editarId = (int)($_GET['editar'] ?? 0);
$edicao = null;

if ($editarId > 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM idosos_perfis
        WHERE id=? AND usuario_id=?
    ");
    $stmt->execute([$editarId, $idUsuario]);
    $edicao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$stmt = $pdo->prepare("
    SELECT * FROM idosos_perfis
    WHERE usuario_id=?
    ORDER BY nome
");
$stmt->execute([$idUsuario]);
$idosos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selecionadoId = (int)($_GET['idoso'] ?? ($idosos[0]['id'] ?? 0));
$idoso = null;

foreach ($idosos as $item) {
    if ((int)$item['id'] === $selecionadoId) {
        $idoso = $item;
        break;
    }
}

$registros = [];

if ($idoso) {
    $stmt = $pdo->prepare("
        SELECT * FROM idosos_registros
        WHERE usuario_id=? AND idoso_id=?
        ORDER BY data_registro DESC, id DESC
    ");
    $stmt->execute([$idUsuario, (int)$idoso['id']]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$tipos = [
    'medicamento' => 'Medicamentos',
    'consulta' => 'Consultas',
    'pressao' => 'Pressão arterial',
    'glicemia' => 'Glicemia',
    'queda' => 'Quedas e incidentes',
    'lembrete' => 'Lembrete de saúde'
];

function valor(array $dados, string $campo): string {
    return (string)($dados[$campo] ?? '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Saúde Idosos | Saúde-Conecta</title>
<style>
:root{--verde:#087f8c;--fundo:#f1f6fa;--texto:#18394e;--borda:#e3ebf1}
*{box-sizing:border-box}
body{margin:0;background:var(--fundo);color:var(--texto);font-family:Arial,sans-serif}
header{background:white;border-bottom:1px solid var(--borda);padding:16px 5%;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.marca{font-size:20px;font-weight:bold;color:var(--verde);text-decoration:none}
main{width:min(1100px,94%);margin:28px auto}
.painel{background:white;border:1px solid var(--borda);border-radius:15px;padding:22px;margin:18px 0}
h1{margin-bottom:8px}h2{margin-top:0}
p{line-height:1.5;color:#607486}
.voltar{color:var(--verde);font-weight:bold;text-decoration:none}
.grade{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
label{font-weight:bold;font-size:14px}
input,select,textarea{display:block;width:100%;padding:11px;margin-top:6px;border:1px solid #cbd8e2;border-radius:8px;font:inherit}
textarea{min-height:85px}
.largo{grid-column:1/-1}
button,.btn{display:inline-block;padding:11px 15px;background:var(--verde);color:white;border:0;border-radius:8px;text-decoration:none;cursor:pointer;font:inherit;font-weight:bold}
.sec{background:#e8f2f4;color:#066773}
.perigo{background:#fff0ef;color:#a12620}
.acoes{display:flex;gap:9px;flex-wrap:wrap;margin-top:15px}
.perfis{display:flex;gap:8px;flex-wrap:wrap}
.perfis a{background:#edf4f6;color:var(--texto);padding:9px 12px;border-radius:8px;text-decoration:none}
.perfis a.ativo{background:var(--verde);color:white}
.mensagem{padding:12px;border-radius:8px;background:#e6f6ed;color:#17633c;margin:12px 0}
.erro{background:#fff0ef;color:#a12620}
.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.card{background:#f7fafc;padding:14px;border-radius:10px;overflow-wrap:anywhere}
.card strong{display:block;margin-bottom:7px}
.registro{border-bottom:1px solid var(--borda);padding:15px 0}
.registro:last-child{border-bottom:0}
.tag{background:#e7f3f4;color:#066773;padding:5px 8px;border-radius:20px;font-size:12px;font-weight:bold}
.topo{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.vazio{background:#f7fafc;padding:14px;border-radius:8px}
@media(max-width:700px){.grade,.cards{grid-template-columns:1fr}.largo{grid-column:auto}.painel{padding:16px}}
</style>
</head>
<body>
<header>
<a class="marca" href="perfil_saude.php">Saúde-Conecta</a>
<span>Olá, <?= h($nomeUsuario) ?></span>
</header>
<main>
<a class="voltar" href="perfil_saude.php">← Voltar ao perfil de saúde</a>
<h1>👵 Saúde Idosos</h1>
<p>Área independente para acompanhar informações de saúde, medicamentos, consultas e cuidados da pessoa idosa.</p>

<?php if ($mensagem): ?><div class="mensagem"><?= h($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="mensagem erro"><?= h($erro) ?></div><?php endif; ?>

<section class="painel">
<h2><?= $edicao ? 'Editar perfil' : 'Cadastrar pessoa idosa' ?></h2>
<form method="post" action="saude_idosos.php<?= $idoso ? '?idoso='.(int)$idoso['id'] : '' ?>">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<input type="hidden" name="acao" value="<?= $edicao ? 'editar_perfil' : 'criar_perfil' ?>">
<?php if ($edicao): ?><input type="hidden" name="idoso_id" value="<?= (int)$edicao['id'] ?>"><?php endif; ?>
<div class="grade">
<label class="largo">Nome completo *
<input name="nome" required maxlength="160" value="<?= h(valor($edicao ?? [], 'nome')) ?>"></label>
<label>Data de nascimento
<input name="nascimento" class="data-br" inputmode="numeric" maxlength="10" placeholder="DD/MM/AAAA" value="<?= h(dataParaTela(valor($edicao ?? [], 'nascimento'))) ?>"></label>
<label>Telefone
<input name="telefone" maxlength="30" value="<?= h(valor($edicao ?? [], 'telefone')) ?>"></label>
<label>Contato de emergência
<input name="contato_emergencia" maxlength="160" value="<?= h(valor($edicao ?? [], 'contato_emergencia')) ?>"></label>
<label>Telefone de emergência
<input name="telefone_emergencia" maxlength="30" value="<?= h(valor($edicao ?? [], 'telefone_emergencia')) ?>"></label>
<label class="largo">Médico de referência
<input name="medico" maxlength="160" value="<?= h(valor($edicao ?? [], 'medico')) ?>"></label>
<label class="largo">Doenças crônicas
<textarea name="doencas"><?= h(valor($edicao ?? [], 'doencas')) ?></textarea></label>
<label class="largo">Alergias e reações
<textarea name="alergias"><?= h(valor($edicao ?? [], 'alergias')) ?></textarea></label>
<label class="largo">Mobilidade e risco de quedas
<textarea name="mobilidade"><?= h(valor($edicao ?? [], 'mobilidade')) ?></textarea></label>
<label class="largo">Observações
<textarea name="observacoes"><?= h(valor($edicao ?? [], 'observacoes')) ?></textarea></label>
</div>
<div class="acoes">
<button type="submit"><?= $edicao ? 'Salvar alterações' : 'Cadastrar perfil' ?></button>
<?php if ($edicao): ?><a class="btn sec" href="saude_idosos.php?idoso=<?= (int)$edicao['id'] ?>">Cancelar</a><?php endif; ?>
</div>
</form>
</section>

<section class="painel">
<h2>Pessoas cadastradas</h2>
<?php if (!$idosos): ?>
<div class="vazio">Nenhum perfil cadastrado ainda.</div>
<?php else: ?>
<div class="perfis">
<?php foreach ($idosos as $p): ?>
<a class="<?= $idoso && (int)$idoso['id']===(int)$p['id'] ? 'ativo' : '' ?>" href="saude_idosos.php?idoso=<?= (int)$p['id'] ?>"><?= h($p['nome']) ?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<?php if ($idoso): ?>
<section class="painel">
<div class="topo">
<div>
<h2>Resumo de saúde</h2>
<p><?= h($idoso['nome']) ?> · Nascimento: <?= h(dataParaTela($idoso['nascimento'])) ?: 'Não informado' ?></p>
</div>
<div class="acoes">
<a class="btn sec" href="saude_idosos.php?editar=<?= (int)$idoso['id'] ?>&idoso=<?= (int)$idoso['id'] ?>">Editar</a>
<form method="post" onsubmit="return confirm('Excluir o perfil e os registros associados?')">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<input type="hidden" name="acao" value="excluir_perfil">
<input type="hidden" name="idoso_id" value="<?= (int)$idoso['id'] ?>">
<button class="perigo" type="submit">Excluir perfil</button>
</form>
</div>
</div>
<div class="cards">
<div class="card"><strong>Telefone</strong><?= h($idoso['telefone'] ?: 'Não informado') ?></div>
<div class="card"><strong>Médico</strong><?= h($idoso['medico'] ?: 'Não informado') ?></div>
<div class="card"><strong>Emergência</strong><?= h(trim(($idoso['contato_emergencia'] ?? '').' '.($idoso['telefone_emergencia'] ?? '')) ?: 'Não informado') ?></div>
<div class="card"><strong>Doenças</strong><?= nl2br(h($idoso['doencas'] ?: 'Não informado')) ?></div>
<div class="card"><strong>Alergias</strong><?= nl2br(h($idoso['alergias'] ?: 'Não informado')) ?></div>
<div class="card"><strong>Mobilidade</strong><?= nl2br(h($idoso['mobilidade'] ?: 'Não informado')) ?></div>
</div>
</section>

<section class="painel">
<h2>Adicionar acompanhamento</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<input type="hidden" name="acao" value="adicionar_registro">
<input type="hidden" name="idoso_id" value="<?= (int)$idoso['id'] ?>">
<div class="grade">
<label>Tipo de registro
<select name="tipo" required>
<?php foreach ($tipos as $chave => $rotulo): ?>
<option value="<?= h($chave) ?>"><?= h($rotulo) ?></option>
<?php endforeach; ?>
</select></label>
<label>Título / nome *
<input name="titulo" required maxlength="180" placeholder="Ex.: medicamento ou consulta"></label>
<label>Data
<input name="data_registro" class="data-br" inputmode="numeric" maxlength="10" placeholder="DD/MM/AAAA"></label>
<label>Próxima data / retorno
<input name="proxima_data" class="data-br" inputmode="numeric" maxlength="10" placeholder="DD/MM/AAAA"></label>
<label>Resultado / dosagem
<input name="resultado" maxlength="120" placeholder="Ex.: 120/80 mmHg ou dose prescrita"></label>
<label>Frequência / horário
<input name="frequencia" maxlength="120" placeholder="Conforme orientação profissional"></label>
<label class="largo">Detalhes
<textarea name="detalhes" placeholder="Observações, orientações recebidas e informações do acompanhamento"></textarea></label>
</div>
<div class="acoes"><button type="submit">Salvar registro</button></div>
</form>
</section>

<section class="painel">
<h2>Histórico</h2>
<?php if (!$registros): ?>
<div class="vazio">Ainda não há registros para esta pessoa.</div>
<?php else: ?>
<?php foreach ($registros as $r): ?>
<div class="registro">
<div class="topo">
<div>
<span class="tag"><?= h($tipos[$r['tipo']] ?? $r['tipo']) ?></span>
<h3><?= h($r['titulo']) ?></h3>
<p>Data: <?= h(dataParaTela($r['data_registro']) ?: 'Não informada') ?>
<?php if ($r['proxima_data']): ?> · Próxima: <?= h(dataParaTela($r['proxima_data'])) ?><?php endif; ?></p>
</div>
<form method="post" onsubmit="return confirm('Excluir este registro?')">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<input type="hidden" name="acao" value="excluir_registro">
<input type="hidden" name="registro_id" value="<?= (int)$r['id'] ?>">
<button class="perigo" type="submit">Excluir</button>
</form>
</div>
<?php if ($r['resultado']): ?><p><strong>Resultado / dosagem:</strong> <?= h($r['resultado']) ?></p><?php endif; ?>
<?php if ($r['frequencia']): ?><p><strong>Frequência:</strong> <?= h($r['frequencia']) ?></p><?php endif; ?>
<?php if ($r['detalhes']): ?><p><?= nl2br(h($r['detalhes'])) ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</section>
<?php endif; ?>

<p><small>Este espaço serve para organizar informações e não substitui consulta médica. Registre medicamentos conforme a prescrição profissional; não altere doses com base apenas nesta página.</small></p>
</main>
<script>
document.querySelectorAll('.data-br').forEach(function(campo) {
    campo.addEventListener('input', function() {
        let n = this.value.replace(/\D/g, '').slice(0, 8);
        if (n.length > 4) n = n.slice(0, 2) + '/' + n.slice(2, 4) + '/' + n.slice(4);
        else if (n.length > 2) n = n.slice(0, 2) + '/' + n.slice(2);
        this.value = n;
    });
});
</script>
</body>
</html>