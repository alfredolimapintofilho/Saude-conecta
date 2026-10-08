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

$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'vacina';

if (!in_array($tipo, $tiposPermitidos, true)) {
    $tipo = 'vacina';
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

$erro = '';

$valores = [

    // VACINA
    'grupo' => 'VACINAS | SOROS | DILUENTES ADMINISTRADOS',
    'vacina' => '',
    'data_aplicacao' => '',
    'dose' => '',
    'lote' => '',
    'estrategia' => '',
    'cnes' => '',
    'estabelecimento' => '',
    'municipio' => '',
    'uf' => '',
    'observacao' => '',

    // ALERGIA
    'alergia' => '',
    'tipo_alergia' => '',
    'gravidade' => '',
    'reacao' => '',
    'observacao_alergia' => '',

    // ALERGIA A VACINA
    'vacina_alergia' => '',
    'reacao_vacina' => '',
    'gravidade_vacina' => '',
    'data_reacao_vacina' => '',
    'observacao_vacina' => '',

    // DOENÇA
    'doenca' => '',
    'data_diagnostico' => '',
    'status' => '',
    'tratamento' => '',
    'observacao_doenca' => '',

    // ATESTADO
    'tipo_atestado' => '',
    'profissional' => '',
    'registro_profissional' => '',
    'data_emissao' => '',
    'data_validade' => '',
    'observacao_atestado' => '',
    'arquivo_url' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($valores as $campo => $valor) {

        if (isset($_POST[$campo])) {
            $valores[$campo] =
                trim((string) $_POST[$campo]);
        }
    }

    try {

        /*
        =====================================================
        VACINA
        =====================================================
        */

        if ($tipo === 'vacina') {

            if ($valores['vacina'] === '') {
                throw new Exception(
                    'Informe o nome da vacina.'
                );
            }

            $data = dataParaBanco(
                $valores['data_aplicacao']
            );

            if ($data === false || $data === null) {
                throw new Exception(
                    'Informe uma data válida no formato DD/MM/AAAA.'
                );
            }

            $sql = "
                INSERT INTO vacinacoes (
                    usuario_id,
                    grupo,
                    vacina,
                    data_aplicacao,
                    dose,
                    lote,
                    estrategia,
                    cnes,
                    estabelecimento,
                    municipio,
                    uf,
                    observacao
                )
                VALUES (
                    :usuario_id,
                    :grupo,
                    :vacina,
                    :data_aplicacao,
                    :dose,
                    :lote,
                    :estrategia,
                    :cnes,
                    :estabelecimento,
                    :municipio,
                    :uf,
                    :observacao
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':usuario_id' =>
                    $usuarioId,

                ':grupo' =>
                    $valores['grupo'],

                ':vacina' =>
                    $valores['vacina'],

                ':data_aplicacao' =>
                    $data,

                ':dose' =>
                    $valores['dose'] ?: null,

                ':lote' =>
                    $valores['lote'] ?: null,

                ':estrategia' =>
                    $valores['estrategia'] ?: null,

                ':cnes' =>
                    $valores['cnes'] ?: null,

                ':estabelecimento' =>
                    $valores['estabelecimento'] ?: null,

                ':municipio' =>
                    $valores['municipio'] ?: null,

                ':uf' =>
                    $valores['uf'] ?: null,

                ':observacao' =>
                    $valores['observacao'] ?: null
            ]);
        }

        /*
        =====================================================
        ALERGIA
        =====================================================
        */

        elseif ($tipo === 'alergia') {

            if ($valores['alergia'] === '') {

                throw new Exception(
                    'Informe a alergia.'
                );
            }

            $sql = "
                INSERT INTO alergias (
                    usuario_id,
                    alergia,
                    tipo,
                    gravidade,
                    reacao,
                    observacao
                )
                VALUES (
                    :usuario_id,
                    :alergia,
                    :tipo,
                    :gravidade,
                    :reacao,
                    :observacao
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':usuario_id' =>
                    $usuarioId,

                ':alergia' =>
                    $valores['alergia'],

                ':tipo' =>
                    $valores['tipo_alergia'] ?: null,

                ':gravidade' =>
                    $valores['gravidade'] ?: null,

                ':reacao' =>
                    $valores['reacao'] ?: null,

                ':observacao' =>
                    $valores['observacao_alergia'] ?: null
            ]);
        }

        /*
        =====================================================
        ALERGIA A VACINA
        =====================================================
        */

        elseif ($tipo === 'alergia_vacina') {

            if ($valores['vacina_alergia'] === '') {

                throw new Exception(
                    'Informe a vacina relacionada à reação.'
                );
            }

            $data = dataParaBanco(
                $valores['data_reacao_vacina']
            );

            if ($data === false) {

                throw new Exception(
                    'A data da reação é inválida.'
                );
            }

            $sql = "
                INSERT INTO alergias_vacinas (
                    usuario_id,
                    vacina,
                    reacao,
                    gravidade,
                    data_reacao,
                    observacao
                )
                VALUES (
                    :usuario_id,
                    :vacina,
                    :reacao,
                    :gravidade,
                    :data_reacao,
                    :observacao
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':usuario_id' =>
                    $usuarioId,

                ':vacina' =>
                    $valores['vacina_alergia'],

                ':reacao' =>
                    $valores['reacao_vacina'] ?: null,

                ':gravidade' =>
                    $valores['gravidade_vacina'] ?: null,

                ':data_reacao' =>
                    $data,

                ':observacao' =>
                    $valores['observacao_vacina'] ?: null
            ]);
        }

        /*
        =====================================================
        DOENÇA
        =====================================================
        */

        elseif ($tipo === 'doenca') {

            if ($valores['doenca'] === '') {

                throw new Exception(
                    'Informe a doença.'
                );
            }

            $data = dataParaBanco(
                $valores['data_diagnostico']
            );

            if ($data === false) {

                throw new Exception(
                    'A data do diagnóstico é inválida.'
                );
            }

            $sql = "
                INSERT INTO historico_doencas (
                    usuario_id,
                    doenca,
                    data_diagnostico,
                    status,
                    tratamento,
                    observacao
                )
                VALUES (
                    :usuario_id,
                    :doenca,
                    :data_diagnostico,
                    :status,
                    :tratamento,
                    :observacao
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':usuario_id' =>
                    $usuarioId,

                ':doenca' =>
                    $valores['doenca'],

                ':data_diagnostico' =>
                    $data,

                ':status' =>
                    $valores['status'] ?: null,

                ':tratamento' =>
                    $valores['tratamento'] ?: null,

                ':observacao' =>
                    $valores['observacao_doenca'] ?: null
            ]);
        }

        /*
        =====================================================
        ATESTADO
        =====================================================
        */

        elseif ($tipo === 'atestado') {

            if ($valores['tipo_atestado'] === '') {

                throw new Exception(
                    'Informe o tipo de atestado.'
                );
            }

            $dataEmissao = dataParaBanco(
                $valores['data_emissao']
            );

            if (
                $dataEmissao === false ||
                $dataEmissao === null
            ) {

                throw new Exception(
                    'Informe uma data de emissão válida.'
                );
            }

            $dataValidade = dataParaBanco(
                $valores['data_validade']
            );

            if ($dataValidade === false) {

                throw new Exception(
                    'A data de validade é inválida.'
                );
            }

            $sql = "
                INSERT INTO atestados (
                    usuario_id,
                    tipo,
                    profissional,
                    registro_profissional,
                    data_emissao,
                    data_validade,
                    observacao,
                    arquivo_url
                )
                VALUES (
                    :usuario_id,
                    :tipo,
                    :profissional,
                    :registro_profissional,
                    :data_emissao,
                    :data_validade,
                    :observacao,
                    :arquivo_url
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':usuario_id' =>
                    $usuarioId,

                ':tipo' =>
                    $valores['tipo_atestado'],

                ':profissional' =>
                    $valores['profissional'] ?: null,

                ':registro_profissional' =>
                    $valores['registro_profissional'] ?: null,

                ':data_emissao' =>
                    $dataEmissao,

                ':data_validade' =>
                    $dataValidade,

                ':observacao' =>
                    $valores['observacao_atestado'] ?: null,

                ':arquivo_url' =>
                    $valores['arquivo_url'] ?: null
            ]);
        }

        header(
            'Location: registros.php?tipo=' .
            urlencode($tipo) .
            '&salvo=1'
        );

        exit;

    } catch (Throwable $e) {

        $erro = $e->getMessage();
    }
}

$titulo =
    'Cadastrar ' .
    $nomes[$tipo];

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
    <?= escapar($titulo) ?>
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

    gap: 12px;
}

.topo h1 {
    margin: 0;
    font-size: 25px;
}

.topo p {
    margin: 5px 0 0;

    font-size: 14px;

    opacity: .9;
}

.container {
    max-width: 1000px;

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

.campo.full {
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

.btns {
    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    margin-top: 22px;
}

.btn {
    display: inline-block;

    padding: 11px 16px;

    border: 0;

    border-radius: 8px;

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

    .campo.full {
        grid-column: auto;
    }

    .topo-conteudo {
        flex-direction: column;

        align-items: flex-start;
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
    <?= escapar($titulo) ?>
</p>

</div>

<a
    class="btn voltar"
    href="registros.php?tipo=<?= urlencode($tipo) ?>"
>
    Voltar
</a>

</div>

</header>

<main class="container">

<section class="card">

<h2>
    <?= escapar($titulo) ?>
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

<div class="grid">

<div class="campo full">

<label>
    Grupo
</label>

<select name="grupo">

<option value="COVID-19">
    COVID-19
</option>

<option
    value="VACINAS | SOROS | DILUENTES ADMINISTRADOS"
    selected
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
    value="<?= escapar($valores['vacina']) ?>"
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
    placeholder="DD/MM/AAAA"
    maxlength="10"
    value="<?= escapar($valores['data_aplicacao']) ?>"
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
    value="<?= escapar($valores['dose']) ?>"
>

</div>

<div class="campo">

<label>
    Lote
</label>

<input
    type="text"
    name="lote"
    value="<?= escapar($valores['lote']) ?>"
>

</div>

<div class="campo">

<label>
    Estratégia
</label>

<input
    type="text"
    name="estrategia"
    value="<?= escapar($valores['estrategia']) ?>"
>

</div>

<div class="campo">

<label>
    CNES
</label>

<input
    type="text"
    name="cnes"
    value="<?= escapar($valores['cnes']) ?>"
>

</div>

<div class="campo full">

<label>
    Estabelecimento
</label>

<input
    type="text"
    name="estabelecimento"
    value="<?= escapar($valores['estabelecimento']) ?>"
>

</div>

<div class="campo">

<label>
    Município
</label>

<input
    type="text"
    name="municipio"
    value="<?= escapar($valores['municipio']) ?>"
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
    value="<?= escapar($valores['uf']) ?>"
>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao"><?= escapar($valores['observacao']) ?></textarea>

</div>

</div>


<?php elseif ($tipo === 'alergia'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="alergia"
>

<div class="grid">

<div class="campo">

<label>
    Alergia *
</label>

<input
    type="text"
    name="alergia"
    value="<?= escapar($valores['alergia']) ?>"
    required
>

</div>

<div class="campo">

<label>
    Tipo
</label>

<select name="tipo_alergia">

<option value="">
    Selecione
</option>

<option>Medicamento</option>
<option>Alimento</option>
<option>Vacina</option>
<option>Ambiental</option>
<option>Contato</option>
<option>Outra</option>

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

<option>
    Leve
</option>

<option>
    Moderada
</option>

<option>
    Grave
</option>

</select>

</div>

<div class="campo full">

<label>
    Reação
</label>

<textarea name="reacao"></textarea>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao_alergia"></textarea>

</div>

</div>


<?php elseif ($tipo === 'alergia_vacina'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="alergia_vacina"
>

<div class="grid">

<div class="campo">

<label>
    Vacina *
</label>

<input
    type="text"
    name="vacina_alergia"
    value="<?= escapar($valores['vacina_alergia']) ?>"
    required
>

</div>

<div class="campo">

<label>
    Gravidade
</label>

<select name="gravidade_vacina">

<option value="">
    Selecione
</option>

<option>
    Leve
</option>

<option>
    Moderada
</option>

<option>
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
    name="data_reacao_vacina"
    placeholder="DD/MM/AAAA"
    maxlength="10"
>

</div>

<div class="campo full">

<label>
    Reação
</label>

<textarea name="reacao_vacina"></textarea>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao_vacina"></textarea>

</div>

</div>


<?php elseif ($tipo === 'doenca'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="doenca"
>

<div class="grid">

<div class="campo">

<label>
    Doença *
</label>

<input
    type="text"
    name="doenca"
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
    placeholder="DD/MM/AAAA"
    maxlength="10"
>

</div>

<div class="campo">

<label>
    Status
</label>

<select name="status">

<option value="">
    Selecione
</option>

<option>
    Em tratamento
</option>

<option>
    Controlada
</option>

<option>
    Curada
</option>

<option>
    Crônica
</option>

<option>
    Histórico
</option>

</select>

</div>

<div class="campo full">

<label>
    Tratamento
</label>

<textarea name="tratamento"></textarea>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao_doenca"></textarea>

</div>

</div>


<?php elseif ($tipo === 'atestado'): ?>

<form method="post">

<input
    type="hidden"
    name="tipo"
    value="atestado"
>

<div class="grid">

<div class="campo">

<label>
    Tipo de atestado *
</label>

<input
    type="text"
    name="tipo_atestado"
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
>

</div>

<div class="campo">

<label>
    Registro profissional
</label>

<input
    type="text"
    name="registro_profissional"
>

</div>

<div class="campo">

<label>
    Data de emissão *
</label>

<input
    type="text"
    name="data_emissao"
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
    placeholder="https://..."
>

</div>

<div class="campo full">

<label>
    Observação
</label>

<textarea name="observacao_atestado"></textarea>

</div>

</div>

<?php endif; ?>


<div class="btns">

<button
    class="btn salvar"
    type="submit"
>
    Salvar
</button>

<a
    class="btn voltar"
    href="registros.php?tipo=<?= urlencode($tipo) ?>"
>
    Cancelar
</a>

</div>

</form>

</section>

</main>

<script>

document
.querySelectorAll(
    'input[name="data_aplicacao"],' +
    'input[name="data_reacao_vacina"],' +
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