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

if (is_array($usuario)) {

    if (isset($usuario['id'])) {
        $usuarioId = (int) $usuario['id'];
    } elseif (isset($usuario['id_usuario'])) {
        $usuarioId = (int) $usuario['id_usuario'];
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

$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? '';

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : (isset($_POST['id']) ? (int) $_POST['id'] : 0);

$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'editar';

if (!in_array($tipo, $tiposPermitidos, true)) {
    die('Tipo de registro inválido.');
}

if ($id <= 0) {
    die('Registro inválido.');
}

$nomes = [
    'vacina' => 'Vacinação',
    'alergia' => 'Alergia',
    'alergia_vacina' => 'Alergia a Vacina',
    'doenca' => 'Histórico de Doença',
    'atestado' => 'Atestado'
];

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatarData($data): string
{
    if (!$data) {
        return '';
    }

    $obj = DateTime::createFromFormat(
        'Y-m-d',
        $data
    );

    if ($obj) {
        return $obj->format('d/m/Y');
    }

    return '';
}

function dataParaBanco(string $data): ?string
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    $obj = DateTime::createFromFormat(
        'd/m/Y',
        $data
    );

    if (
        !$obj ||
        $obj->format('d/m/Y') !== $data
    ) {
        return false;
    }

    return $obj->format('Y-m-d');
}

/*
=========================================================
EXCLUSÃO
=========================================================
*/

if ($acao === 'excluir') {

    try {

        if ($tipo === 'vacina') {

            $sql = "
                DELETE FROM vacinacoes
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

        } elseif ($tipo === 'alergia') {

            $sql = "
                DELETE FROM alergias
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

        } elseif ($tipo === 'alergia_vacina') {

            $sql = "
                DELETE FROM alergias_vacinas
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

        } elseif ($tipo === 'doenca') {

            $sql = "
                DELETE FROM historico_doencas
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

        } else {

            $sql = "
                DELETE FROM atestados
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId
        ]);

        header(
            'Location: registros.php?tipo=' .
            urlencode($tipo) .
            '&excluido=1'
        );

        exit;

    } catch (Throwable $e) {

        die(
            'Erro ao excluir registro: ' .
            escapar($e->getMessage())
        );
    }
}

/*
=========================================================
BUSCAR REGISTRO
=========================================================
*/

$registro = null;

if ($tipo === 'vacina') {

    $sql = "
        SELECT *
        FROM vacinacoes
        WHERE id = :id
        AND usuario_id = :usuario_id
        LIMIT 1
    ";

} elseif ($tipo === 'alergia') {

    $sql = "
        SELECT *
        FROM alergias
        WHERE id = :id
        AND usuario_id = :usuario_id
        LIMIT 1
    ";

} elseif ($tipo === 'alergia_vacina') {

    $sql = "
        SELECT *
        FROM alergias_vacinas
        WHERE id = :id
        AND usuario_id = :usuario_id
        LIMIT 1
    ";

} elseif ($tipo === 'doenca') {

    $sql = "
        SELECT *
        FROM historico_doencas
        WHERE id = :id
        AND usuario_id = :usuario_id
        LIMIT 1
    ";

} else {

    $sql = "
        SELECT *
        FROM atestados
        WHERE id = :id
        AND usuario_id = :usuario_id
        LIMIT 1
    ";
}

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id,
    ':usuario_id' => $usuarioId
]);

$registro = $stmt->fetch();

if (!$registro) {
    die('Registro não encontrado.');
}

$erro = '';

/*
=========================================================
ATUALIZAÇÃO
=========================================================
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /*
        =================================================
        VACINA
        =================================================
        */

        if ($tipo === 'vacina') {

            $vacina =
                trim($_POST['vacina'] ?? '');

            $dataAplicacao =
                trim($_POST['data_aplicacao'] ?? '');

            if ($vacina === '') {
                throw new Exception(
                    'Informe o nome da vacina.'
                );
            }

            $data =
                dataParaBanco($dataAplicacao);

            if ($data === false || $data === null) {
                throw new Exception(
                    'Informe uma data válida no formato DD/MM/AAAA.'
                );
            }

            $sql = "
                UPDATE vacinacoes
                SET
                    grupo = :grupo,
                    vacina = :vacina,
                    data_aplicacao = :data_aplicacao,
                    dose = :dose,
                    lote = :lote,
                    estrategia = :estrategia,
                    cnes = :cnes,
                    estabelecimento = :estabelecimento,
                    municipio = :municipio,
                    uf = :uf,
                    observacao = :observacao
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':grupo' =>
                    $_POST['grupo'] ?? '',

                ':vacina' =>
                    $vacina,

                ':data_aplicacao' =>
                    $data,

                ':dose' =>
                    trim($_POST['dose'] ?? '') ?: null,

                ':lote' =>
                    trim($_POST['lote'] ?? '') ?: null,

                ':estrategia' =>
                    trim($_POST['estrategia'] ?? '') ?: null,

                ':cnes' =>
                    trim($_POST['cnes'] ?? '') ?: null,

                ':estabelecimento' =>
                    trim($_POST['estabelecimento'] ?? '') ?: null,

                ':municipio' =>
                    trim($_POST['municipio'] ?? '') ?: null,

                ':uf' =>
                    trim($_POST['uf'] ?? '') ?: null,

                ':observacao' =>
                    trim($_POST['observacao'] ?? '') ?: null,

                ':id' =>
                    $id,

                ':usuario_id' =>
                    $usuarioId
            ]);
        }

        /*
        =================================================
        ALERGIA
        =================================================
        */

        elseif ($tipo === 'alergia') {

            $alergia =
                trim($_POST['alergia'] ?? '');

            if ($alergia === '') {
                throw new Exception(
                    'Informe a alergia.'
                );
            }

            $sql = "
                UPDATE alergias
                SET
                    alergia = :alergia,
                    tipo = :tipo,
                    gravidade = :gravidade,
                    reacao = :reacao,
                    observacao = :observacao
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':alergia' =>
                    $alergia,

                ':tipo' =>
                    trim($_POST['tipo_alergia'] ?? '') ?: null,

                ':gravidade' =>
                    trim($_POST['gravidade'] ?? '') ?: null,

                ':reacao' =>
                    trim($_POST['reacao'] ?? '') ?: null,

                ':observacao' =>
                    trim($_POST['observacao_alergia'] ?? '') ?: null,

                ':id' =>
                    $id,

                ':usuario_id' =>
                    $usuarioId
            ]);
        }

        /*
        =================================================
        ALERGIA A VACINA
        =================================================
        */

        elseif ($tipo === 'alergia_vacina') {

            $vacina =
                trim($_POST['vacina'] ?? '');

            if ($vacina === '') {
                throw new Exception(
                    'Informe a vacina.'
                );
            }

            $data =
                dataParaBanco(
                    trim($_POST['data_reacao'] ?? '')
                );

            if ($data === false) {
                throw new Exception(
                    'A data da reação é inválida.'
                );
            }

            $sql = "
                UPDATE alergias_vacinas
                SET
                    vacina = :vacina,
                    reacao = :reacao,
                    gravidade = :gravidade,
                    data_reacao = :data_reacao,
                    observacao = :observacao
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':vacina' =>
                    $vacina,

                ':reacao' =>
                    trim($_POST['reacao'] ?? '') ?: null,

                ':gravidade' =>
                    trim($_POST['gravidade'] ?? '') ?: null,

                ':data_reacao' =>
                    $data,

                ':observacao' =>
                    trim($_POST['observacao'] ?? '') ?: null,

                ':id' =>
                    $id,

                ':usuario_id' =>
                    $usuarioId
            ]);
        }

        /*
        =================================================
        DOENÇA
        =================================================
        */

        elseif ($tipo === 'doenca') {

            $doenca =
                trim($_POST['doenca'] ?? '');

            if ($doenca === '') {
                throw new Exception(
                    'Informe a doença.'
                );
            }

            $data =
                dataParaBanco(
                    trim($_POST['data_diagnostico'] ?? '')
                );

            if ($data === false) {
                throw new Exception(
                    'A data do diagnóstico é inválida.'
                );
            }

            $sql = "
                UPDATE historico_doencas
                SET
                    doenca = :doenca,
                    data_diagnostico = :data_diagnostico,
                    status = :status,
                    tratamento = :tratamento,
                    observacao = :observacao
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':doenca' =>
                    $doenca,

                ':data_diagnostico' =>
                    $data,

                ':status' =>
                    trim($_POST['status'] ?? '') ?: null,

                ':tratamento' =>
                    trim($_POST['tratamento'] ?? '') ?: null,

                ':observacao' =>
                    trim($_POST['observacao'] ?? '') ?: null,

                ':id' =>
                    $id,

                ':usuario_id' =>
                    $usuarioId
            ]);
        }

        /*
        =================================================
        ATESTADO
        =================================================
        */

        elseif ($tipo === 'atestado') {

            $tipoAtestado =
                trim($_POST['tipo_atestado'] ?? '');

            if ($tipoAtestado === '') {
                throw new Exception(
                    'Informe o tipo de atestado.'
                );
            }

            $dataEmissao =
                dataParaBanco(
                    trim($_POST['data_emissao'] ?? '')
                );

            if (
                $dataEmissao === false ||
                $dataEmissao === null
            ) {
                throw new Exception(
                    'Informe uma data de emissão válida.'
                );
            }

            $dataValidade =
                dataParaBanco(
                    trim($_POST['data_validade'] ?? '')
                );

            if ($dataValidade === false) {
                throw new Exception(
                    'A data de validade é inválida.'
                );
            }

            $sql = "
                UPDATE atestados
                SET
                    tipo = :tipo,
                    profissional = :profissional,
                    registro_profissional = :registro_profissional,
                    data_emissao = :data_emissao,
                    data_validade = :data_validade,
                    observacao = :observacao,
                    arquivo_url = :arquivo_url
                WHERE id = :id
                AND usuario_id = :usuario_id
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':tipo' =>
                    $tipoAtestado,

                ':profissional' =>
                    trim($_POST['profissional'] ?? '') ?: null,

                ':registro_profissional' =>
                    trim($_POST['registro_profissional'] ?? '') ?: null,

                ':data_emissao' =>
                    $dataEmissao,

                ':data_validade' =>
                    $dataValidade,

                ':observacao' =>
                    trim($_POST['observacao'] ?? '') ?: null,

                ':arquivo_url' =>
                    trim($_POST['arquivo_url'] ?? '') ?: null,

                ':id' =>
                    $id,

                ':usuario_id' =>
                    $usuarioId
            ]);
        }

        header(
            'Location: registros.php?tipo=' .
            urlencode($tipo) .
            '&editado=1'
        );

        exit;

    } catch (Throwable $e) {

        $erro = $e->getMessage();

        /*
        Recarrega os dados enviados para
        manter os valores no formulário.
        */

        foreach ($_POST as $campo => $valor) {

            if ($campo !== 'tipo' && $campo !== 'id') {
                $registro[$campo] = $valor;
            }
        }
    }
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
    Editar <?= escapar($nomes[$tipo]) ?>
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
    max-width: 1000px;

    margin: auto;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.topo h1 {
    margin: 0;

    font-size: 25px;
}

.topo p {
    margin: 5px 0 0;

    font-size: 14px;
}

.container {
    max-width: 1000px;

    margin: 30px auto;

    padding: 0 16px;
}

.card {
    background: white;

    padding: 25px;

    border-radius: 16px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.07);
}

h2 {
    margin-top: 0;

    color: #0f766e;
}

.grid {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 16px;
}

.campo {
    display: flex;

    flex-direction: column;

    gap: 6px;
}

.full {
    grid-column: 1 / -1;
}

label {
    font-weight: bold;

    font-size: 14px;

    color: #475569;
}

input,
select,
textarea {
    width: 100%;

    padding: 11px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    font: inherit;
}

textarea {
    min-height: 100px;

    resize: vertical;
}

.botoes {
    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    margin-top: 25px;
}

.btn {
    display: inline-block;

    border: none;

    border-radius: 8px;

    padding: 11px 16px;

    text-decoration: none;

    font-weight: bold;

    cursor: pointer;
}

.salvar {
    background: #0f766e;

    color: white;
}

.voltar {
    background: #e2e8f0;

    color: #334155;
}

.excluir {
    background: #dc2626;

    color: white;
}

.erro {
    background: #fee2e2;

    color: #991b1b;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 18px;
}

@media (max-width: 700px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .topo-conteudo {
        flex-direction: column;

        align-items: flex-start;

        gap: 10px;
    }
}

</style>

</head>

<body>

<header class="topo">

<div class="topo-conteudo">

<div>

<h1>
    Saúde-Conecta
</h1>

<p>
    Editar <?= escapar($nomes[$tipo]) ?>
</p>

</div>

<a
    href="registros.php?tipo=<?= urlencode($tipo) ?>"
    class="btn voltar"
>
    Voltar
</a>

</div>

</header>

<main class="container">

<section class="card">

<h2>
    Editar <?= escapar($nomes[$tipo]) ?>
</h2>

<?php if ($erro): ?>

<div class="erro">

<?= escapar($erro) ?>

</div>

<?php endif; ?>


<?php if ($tipo === 'vacina'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="vacina"
>

<input
    type="hidden"
    name="id"
    value="<?= $id ?>"
>

<div class="grid">

<div class="campo full">

<label>
    Grupo
</label>

<select name="grupo">

<option
    value="COVID-19"
    <?= (($registro['grupo'] ?? '') === 'COVID-19')
        ? 'selected'
        : '' ?>
>
    COVID-19
</option>

<option
    value="VACINAS | SOROS | DILUENTES ADMINISTRADOS"
    <?= (($registro['grupo'] ?? '') ===
        'VACINAS | SOROS | DILUENTES ADMINISTRADOS')
        ? 'selected'
        : '' ?>
>
    VACINAS | SOROS | DILUENTES ADMINISTRADOS
</option>

</select>

</div>

<div class="campo">

<label>
    Vacina *
</label>

<input
    type="text"
    name="vacina"
    value="<?= escapar($registro['vacina'] ?? '') ?>"
    required
>

</div>

<div class="campo">

<label>
    Data de aplicação *
</label>

<input
    type="text"
    name="data_aplicacao"
    value="<?= escapar(
        $registro['data_aplicacao'] ?? ''
    ) ?>"
    placeholder="DD/MM/AAAA"
    maxlength="10"
    required
>

</div>

<div class="campo">

<label>
    Dose
</label>

<input
    type="text"
    name="dose"
    value="<?= escapar($registro['dose'] ?? '') ?>"
>

</div>

<div class="campo">

<label>
    Lote
</label>

<input
    type="text"
    name="lote"
    value="<?= escapar($registro['lote'] ?? '') ?>"
>

</div>

<div class="campo">

<label>
    Estratégia
</label>

<input
    type="text"
    name="estrategia"
    value="<?= escapar($registro['estrategia'] ?? '') ?>"
>

</div>

<div class="campo">

<label>
    CNES
</label>

<input
    type="text"
    name="cnes"
    value="<?= escapar($registro['cnes'] ?? '') ?>"
>

</div>

<div class="campo full">

<label>
    Estabelecimento
</label>

<input
    type="text"
    name="estabelecimento"
    value="<?= escapar(
        $registro['estabelecimento'] ?? ''
    ) ?>"
>

</div>

<div class="campo">

<label>
    Município
</label>

<input
    type="text"
    name="municipio"
    value="<?= escapar(
        $registro['municipio'] ?? ''
    ) ?>"
>

</div>

<div class="campo">

<label>
    UF
</label>

<input
    type="text"
    name="uf"
    maxlength="2"
    value="<?= escapar($registro['uf'] ?? '') ?>"
>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao"><?= escapar(
    $registro['observacao'] ?? ''
) ?></textarea>

</div>

</div>


<?php elseif ($tipo === 'alergia'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="alergia"
>

<input
    type="hidden"
    name="id"
    value="<?= $id ?>"
>

<div class="grid">

<div class="campo">

<label>
    Alergia *
</label>

<input
    type="text"
    name="alergia"
    value="<?= escapar(
        $registro['alergia'] ?? ''
    ) ?>"
    required
>

</div>

<div class="campo">

<label>
    Tipo
</label>

<select name="tipo_alergia">

<?php

$tiposAlergia = [
    'Medicamento',
    'Alimento',
    'Vacina',
    'Ambiental',
    'Contato',
    'Outra'
];

foreach ($tiposAlergia as $item):

?>

<option
    value="<?= escapar($item) ?>"
    <?= (($registro['tipo'] ?? '') === $item)
        ? 'selected'
        : '' ?>
>
    <?= escapar($item) ?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="campo">

<label>
    Gravidade
</label>

<select name="gravidade">

<option value="">
    Selecione
</option>

<option
    <?= (($registro['gravidade'] ?? '') === 'Leve')
        ? 'selected'
        : '' ?>
>
    Leve
</option>

<option
    <?= (($registro['gravidade'] ?? '') === 'Moderada')
        ? 'selected'
        : '' ?>
>
    Moderada
</option>

<option
    <?= (($registro['gravidade'] ?? '') === 'Grave')
        ? 'selected'
        : '' ?>
>
    Grave
</option>

</select>

</div>

<div class="campo full">

<label>
    Reação
</label>

<textarea name="reacao"><?= escapar(
    $registro['reacao'] ?? ''
) ?></textarea>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao_alergia"><?= escapar(
    $registro['observacao'] ?? ''
) ?></textarea>

</div>

</div>


<?php elseif ($tipo === 'alergia_vacina'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="alergia_vacina"
>

<input
    type="hidden"
    name="id"
    value="<?= $id ?>"
>

<div class="grid">

<div class="campo">

<label>
    Vacina *
</label>

<input
    type="text"
    name="vacina"
    value="<?= escapar(
        $registro['vacina'] ?? ''
    ) ?>"
    required
>

</div>

<div class="campo">

<label>
    Gravidade
</label>

<select name="gravidade">

<option value="">
    Selecione
</option>

<option
    <?= (($registro['gravidade'] ?? '') === 'Leve')
        ? 'selected'
        : '' ?>
>
    Leve
</option>

<option
    <?= (($registro['gravidade'] ?? '') === 'Moderada')
        ? 'selected'
        : '' ?>
>
    Moderada
</option>

<option
    <?= (($registro['gravidade'] ?? '') === 'Grave')
        ? 'selected'
        : '' ?>
>
    Grave
</option>

</select>

</div>

<div class="campo">

<label>
    Data da reação
</label>

<input
    type="text"
    name="data_reacao"
    value="<?= escapar(
        formatarData(
            $registro['data_reacao'] ?? ''
        )
    ) ?>"
    placeholder="DD/MM/AAAA"
    maxlength="10"
>

</div>

<div class="campo full">

<label>
    Reação
</label>

<textarea name="reacao"><?= escapar(
    $registro['reacao'] ?? ''
) ?></textarea>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao"><?= escapar(
    $registro['observacao'] ?? ''
) ?></textarea>

</div>

</div>


<?php elseif ($tipo === 'doenca'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="doenca"
>

<input
    type="hidden"
    name="id"
    value="<?= $id ?>"
>

<div class="grid">

<div class="campo">

<label>
    Doença *
</label>

<input
    type="text"
    name="doenca"
    value="<?= escapar(
        $registro['doenca'] ?? ''
    ) ?>"
    required
>

</div>

<div class="campo">

<label>
    Data do diagnóstico
</label>

<input
    type="text"
    name="data_diagnostico"
    value="<?= escapar(
        formatarData(
            $registro['data_diagnostico'] ?? ''
        )
    ) ?>"
    placeholder="DD/MM/AAAA"
    maxlength="10"
>

</div>

<div class="campo">

<label>
    Status
</label>

<select name="status">

<?php

$statusLista = [
    'Em tratamento',
    'Controlada',
    'Curada',
    'Crônica',
    'Histórico'
];

foreach ($statusLista as $status):

?>

<option
    <?= (($registro['status'] ?? '') === $status)
        ? 'selected'
        : '' ?>
>
    <?= escapar($status) ?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="campo full">

<label>
    Tratamento
</label>

<textarea name="tratamento"><?= escapar(
    $registro['tratamento'] ?? ''
) ?></textarea>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao"><?= escapar(
    $registro['observacao'] ?? ''
) ?></textarea>

</div>

</div>


<?php elseif ($tipo === 'atestado'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="atestado"
>

<input
    type="hidden"
    name="id"
    value="<?= $id ?>"
>

<div class="grid">

<div class="campo">

<label>
    Tipo de atestado *
</label>

<input
    type="text"
    name="tipo_atestado"
    value="<?= escapar(
        $registro['tipo'] ?? ''
    ) ?>"
    required
>

</div>

<div class="campo">

<label>
    Profissional
</label>

<input
    type="text"
    name="profissional"
    value="<?= escapar(
        $registro['profissional'] ?? ''
    ) ?>"
>

</div>

<div class="campo">

<label>
    Registro profissional
</label>

<input
    type="text"
    name="registro_profissional"
    value="<?= escapar(
        $registro['registro_profissional'] ?? ''
    ) ?>"
>

</div>

<div class="campo">

<label>
    Data de emissão *
</label>

<input
    type="text"
    name="data_emissao"
    value="<?= escapar(
        formatarData(
            $registro['data_emissao'] ?? ''
        )
    ) ?>"
    placeholder="DD/MM/AAAA"
    maxlength="10"
    required
>

</div>

<div class="campo">

<label>
    Data de validade
</label>

<input
    type="text"
    name="data_validade"
    value="<?= escapar(
        formatarData(
            $registro['data_validade'] ?? ''
        )
    ) ?>"
    placeholder="DD/MM/AAAA"
    maxlength="10"
>

</div>

<div class="campo full">

<label>
    Link do documento
</label>

<input
    type="url"
    name="arquivo_url"
    value="<?= escapar(
        $registro['arquivo_url'] ?? ''
    ) ?>"
    placeholder="https://..."
>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao"><?= escapar(
    $registro['observacao'] ?? ''
) ?></textarea>

</div>

</div>

<?php endif; ?>


<div class="botoes">

<button
    type="submit"
    class="btn salvar"
>
    Salvar alterações
</button>

<a
    href="registros.php?tipo=<?= urlencode($tipo) ?>"
    class="btn voltar"
>
    Cancelar
</a>

<a
    href="editar.php?tipo=<?= urlencode($tipo) ?>&id=<?= $id ?>&acao=excluir"
    class="btn excluir"
    onclick="
        return confirm(
            'Deseja realmente excluir este registro?'
        );
    "
>
    Excluir
</a>

</div>

</form>

</section>

</main>

<script>

document
.querySelectorAll(
    'input[name="data_aplicacao"],' +
    'input[name="data_reacao"],' +
    'input[name="data_diagnostico"],' +
    'input[name="data_emissao"],' +
    'input[name="data_validade"]'
)
.forEach(function(input) {

    input.addEventListener(
        'input',
        function() {

            let valor =
                this.value
                .replace(/\D/g, '')
                .slice(0, 8);

            if (valor.length > 4) {

                valor =
                    valor.slice(0, 2) +
                    '/' +
                    valor.slice(2, 4) +
                    '/' +
                    valor.slice(4);

            } else if (valor.length > 2) {

                valor =
                    valor.slice(0, 2) +
                    '/' +
                    valor.slice(2);
            }

            this.value = valor;
        }
    );
});

</script>

</body>

</html>