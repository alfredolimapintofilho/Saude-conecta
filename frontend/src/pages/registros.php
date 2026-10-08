<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

$pdo = Database::getConnection();

$usuario = $_SESSION['usuario'];

$usuarioId = null;
$nomeUsuario = 'Usuário';

if (is_array($usuario)) {

    if (isset($usuario['id'])) {
        $usuarioId = (int) $usuario['id'];
    } elseif (isset($usuario['id_usuario'])) {
        $usuarioId = (int) $usuario['id_usuario'];
    }

    if (!empty($usuario['nomeCompleto'])) {
        $nomeUsuario =
            $usuario['nomeCompleto'];

    } elseif (!empty($usuario['nome'])) {
        $nomeUsuario =
            $usuario['nome'];

    } elseif (!empty($usuario['nome_completo'])) {
        $nomeUsuario =
            $usuario['nome_completo'];
    }
}

if (!$usuarioId) {
    session_destroy();

    header('Location: login.php');

    exit;
}

$tiposPermitidos = [
    'vacina',
    'alergia',
    'alergia_vacina',
    'doenca',
    'atestado'
];

$tipo = $_GET['tipo'] ?? 'vacina';

if (!in_array($tipo, $tiposPermitidos, true)) {
    $tipo = 'vacina';
}

$nomes = [
    'vacina' => 'Cartão de Vacinação',
    'alergia' => 'Alergias',
    'alergia_vacina' => 'Alergias a Vacinas',
    'doenca' => 'Histórico de Doenças',
    'atestado' => 'Atestados'
];

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function dataBr($data): string
{
    if (!$data) {
        return '-';
    }

    $obj = DateTime::createFromFormat(
        'Y-m-d',
        $data
    );

    if ($obj) {
        return $obj->format('d/m/Y');
    }

    return '-';
}

$registros = [];

try {

    if ($tipo === 'vacina') {

        $sql = "
            SELECT *
            FROM vacinacoes
            WHERE usuario_id = :usuario_id
            ORDER BY data_aplicacao DESC, id DESC
        ";

    } elseif ($tipo === 'alergia') {

        $sql = "
            SELECT *
            FROM alergias
            WHERE usuario_id = :usuario_id
            ORDER BY id DESC
        ";

    } elseif ($tipo === 'alergia_vacina') {

        $sql = "
            SELECT *
            FROM alergias_vacinas
            WHERE usuario_id = :usuario_id
            ORDER BY data_reacao DESC, id DESC
        ";

    } elseif ($tipo === 'doenca') {

        $sql = "
            SELECT *
            FROM historico_doencas
            WHERE usuario_id = :usuario_id
            ORDER BY data_diagnostico DESC, id DESC
        ";

    } else {

        $sql = "
            SELECT *
            FROM atestados
            WHERE usuario_id = :usuario_id
            ORDER BY data_emissao DESC, id DESC
        ";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':usuario_id' => $usuarioId
    ]);

    $registros = $stmt->fetchAll();

} catch (Throwable $e) {

    die(
        'Erro ao consultar os registros: ' .
        escapar($e->getMessage())
    );
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= escapar($nomes[$tipo]) ?>
    - Saúde-Conecta
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f1f5f9;

    color: #1e293b;
}

.topo {
    background:
        linear-gradient(
            135deg,
            #0f766e,
            #0d9488
        );

    color: white;

    padding: 20px;
}

.topo-conteudo {
    max-width: 1200px;

    margin: auto;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.logo h1 {
    margin: 0;

    font-size: 25px;
}

.logo p {
    margin: 5px 0 0;

    font-size: 14px;

    opacity: .9;
}

.container {
    max-width: 1200px;

    margin: 30px auto;

    padding: 0 16px;
}

.card {
    background: white;

    border-radius: 16px;

    padding: 25px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.07);

    margin-bottom: 20px;
}

.cabecalho {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    flex-wrap: wrap;
}

h2 {
    margin: 0;

    color: #0f766e;
}

.subtitulo {
    color: #64748b;

    margin-top: 7px;
}

.botoes {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}

.btn {
    display: inline-block;

    padding: 10px 14px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: bold;

    border: none;

    cursor: pointer;
}

.btn-principal {
    background: #0f766e;

    color: white;
}

.btn-perfil {
    background: #e2e8f0;

    color: #334155;
}

.btn-sair {
    background: #dc2626;

    color: white;
}

.tabela-container {
    overflow-x: auto;

    margin-top: 20px;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 750px;
}

th,
td {
    padding: 12px;

    border-bottom: 1px solid #e2e8f0;

    text-align: left;

    vertical-align: top;
}

th {
    background: #f8fafc;

    color: #475569;

    font-size: 13px;
}

td {
    font-size: 14px;
}

.acoes {
    white-space: nowrap;
}

.btn-editar {
    background: #2563eb;

    color: white;

    padding: 7px 10px;

    border-radius: 6px;

    text-decoration: none;

    font-weight: bold;

    font-size: 12px;
}

.btn-excluir {
    background: #dc2626;

    color: white;

    padding: 7px 10px;

    border-radius: 6px;

    text-decoration: none;

    font-weight: bold;

    font-size: 12px;
}

.vazio {
    text-align: center;

    padding: 40px 20px;

    color: #64748b;
}

.mensagem {
    background: #dcfce7;

    border: 1px solid #86efac;

    color: #166534;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.badge {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 20px;

    background: #ccfbf1;

    color: #115e59;

    font-size: 12px;

    font-weight: bold;
}

@media (max-width: 700px) {

    .topo-conteudo {
        flex-direction: column;

        align-items: flex-start;
    }

    .card {
        padding: 18px;
    }
}

</style>

</head>

<body>

<header class="topo">

<div class="topo-conteudo">

<div class="logo">

<h1>
    Saúde-Conecta
</h1>

<p>
    <?= escapar($nomeUsuario) ?>
</p>

</div>

<div class="botoes">

<a
    href="../components/perfil_saude.php"
    class="btn btn-perfil"
>
    Perfil de Saúde
</a>

<a
    href="../components/logout.php"
    class="btn btn-sair"
    onclick="
        return confirm(
            'Deseja realmente sair da sua conta?'
        );
    "
>
    Sair
</a>

</div>

</div>

</header>

<main class="container">

<section class="card">

<div class="cabecalho">

<div>

<h2>
    <?= escapar($nomes[$tipo]) ?>
</h2>

<p class="subtitulo">
    Gerencie as informações cadastradas
    na sua conta.
</p>

</div>

<div class="botoes">

<a
    href="cadastro.php?tipo=<?= urlencode($tipo) ?>"
    class="btn btn-principal"
>
    + Novo cadastro
</a>

</div>

</div>

</section>


<?php if (isset($_GET['salvo'])): ?>

<div class="mensagem">
    Registro cadastrado com sucesso.
</div>

<?php endif; ?>


<?php if (isset($_GET['editado'])): ?>

<div class="mensagem">
    Registro atualizado com sucesso.
</div>

<?php endif; ?>


<?php if (isset($_GET['excluido'])): ?>

<div class="mensagem">
    Registro excluído com sucesso.
</div>

<?php endif; ?>


<section class="card">

<?php if (empty($registros)): ?>

<div class="vazio">

<h3>
    Nenhum registro encontrado.
</h3>

<p>
    Clique em "Novo cadastro" para adicionar
    uma informação.
</p>

</div>

<?php else: ?>

<div class="tabela-container">

<table>

<thead>

<tr>

<?php if ($tipo === 'vacina'): ?>

<th>
    Vacina
</th>

<th>
    Data
</th>

<th>
    Dose
</th>

<th>
    Lote
</th>

<th>
    Estabelecimento
</th>

<th>
    Ações
</th>


<?php elseif ($tipo === 'alergia'): ?>

<th>
    Alergia
</th>

<th>
    Tipo
</th>

<th>
    Gravidade
</th>

<th>
    Reação
</th>

<th>
    Observação
</th>

<th>
    Ações
</th>


<?php elseif ($tipo === 'alergia_vacina'): ?>

<th>
    Vacina
</th>

<th>
    Data da reação
</th>

<th>
    Gravidade
</th>

<th>
    Reação
</th>

<th>
    Observação
</th>

<th>
    Ações
</th>


<?php elseif ($tipo === 'doenca'): ?>

<th>
    Doença
</th>

<th>
    Diagnóstico
</th>

<th>
    Status
</th>

<th>
    Tratamento
</th>

<th>
    Observação
</th>

<th>
    Ações
</th>


<?php else: ?>

<th>
    Tipo
</th>

<th>
    Profissional
</th>

<th>
    Emissão
</th>

<th>
    Validade
</th>

<th>
    Documento
</th>

<th>
    Ações
</th>

<?php endif; ?>

</tr>

</thead>

<tbody>

<?php foreach ($registros as $registro): ?>

<tr>


<?php if ($tipo === 'vacina'): ?>

<td>

<strong>
    <?= escapar($registro['vacina']) ?>
</strong>

<br>

<span class="badge">
    <?= escapar($registro['grupo']) ?>
</span>

</td>

<td>
    <?= dataBr($registro['data_aplicacao']) ?>
</td>

<td>
    <?= escapar($registro['dose'] ?? '-') ?>
</td>

<td>
    <?= escapar($registro['lote'] ?? '-') ?>
</td>

<td>

<?= escapar(
    $registro['estabelecimento']
    ?? '-'
) ?>

<br>

<small>

<?= escapar(
    $registro['municipio']
    ?? ''
) ?>

</small>

</td>


<?php elseif ($tipo === 'alergia'): ?>

<td>

<strong>
    <?= escapar($registro['alergia']) ?>
</strong>

</td>

<td>
    <?= escapar($registro['tipo'] ?? '-') ?>
</td>

<td>
    <?= escapar(
        $registro['gravidade']
        ?? '-'
    ) ?>
</td>

<td>
    <?= nl2br(
        escapar(
            $registro['reacao']
            ?? '-'
        )
    ) ?>
</td>

<td>
    <?= nl2br(
        escapar(
            $registro['observacao']
            ?? '-'
        )
    ) ?>
</td>


<?php elseif ($tipo === 'alergia_vacina'): ?>

<td>

<strong>
    <?= escapar($registro['vacina']) ?>
</strong>

</td>

<td>
    <?= dataBr(
        $registro['data_reacao']
        ?? null
    ) ?>
</td>

<td>
    <?= escapar(
        $registro['gravidade']
        ?? '-'
    ) ?>
</td>

<td>
    <?= nl2br(
        escapar(
            $registro['reacao']
            ?? '-'
        )
    ) ?>
</td>

<td>
    <?= nl2br(
        escapar(
            $registro['observacao']
            ?? '-'
        )
    ) ?>
</td>


<?php elseif ($tipo === 'doenca'): ?>

<td>

<strong>
    <?= escapar($registro['doenca']) ?>
</strong>

</td>

<td>
    <?= dataBr(
        $registro['data_diagnostico']
        ?? null
    ) ?>
</td>

<td>

<span class="badge">

<?= escapar(
    $registro['status']
    ?? '-'
) ?>

</span>

</td>

<td>
    <?= nl2br(
        escapar(
            $registro['tratamento']
            ?? '-'
        )
    ) ?>
</td>

<td>
    <?= nl2br(
        escapar(
            $registro['observacao']
            ?? '-'
        )
    ) ?>
</td>


<?php else: ?>

<td>

<strong>
    <?= escapar($registro['tipo']) ?>
</strong>

</td>

<td>

<?= escapar(
    $registro['profissional']
    ?? '-'
) ?>

<br>

<small>

<?= escapar(
    $registro['registro_profissional']
    ?? ''
) ?>

</small>

</td>

<td>
    <?= dataBr(
        $registro['data_emissao']
    ) ?>
</td>

<td>
    <?= dataBr(
        $registro['data_validade']
        ?? null
    ) ?>
</td>

<td>

<?php if (!empty($registro['arquivo_url'])): ?>

<a
    href="<?= escapar(
        $registro['arquivo_url']
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
>
    Abrir documento
</a>

<?php else: ?>

-

<?php endif; ?>

</td>

<?php endif; ?>


<td class="acoes">

<a
    href="editar.php?tipo=<?= urlencode($tipo) ?>&id=<?= (int) $registro['id'] ?>"
    class="btn-editar"
>
    Editar
</a>

<a
    href="editar.php?tipo=<?= urlencode($tipo) ?>&id=<?= (int) $registro['id'] ?>&acao=excluir"
    class="btn-excluir"
    onclick="
        return confirm(
            'Deseja realmente excluir este registro?'
        );
    "
>
    Excluir
</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>

</main>

</body>

</html>