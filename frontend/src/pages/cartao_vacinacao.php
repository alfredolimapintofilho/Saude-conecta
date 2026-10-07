<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/models/Vacina.php';

/*
|--------------------------------------------------------------------------
| CONEXÃO COM O BANCO
|--------------------------------------------------------------------------
*/

$pdo = Database::getConnection();

/*
|--------------------------------------------------------------------------
| USUÁRIO LOGADO
|--------------------------------------------------------------------------
*/

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
        $nomeUsuario = $usuario['nomeCompleto'];
    } elseif (!empty($usuario['nome'])) {
        $nomeUsuario = $usuario['nome'];
    } elseif (!empty($usuario['nome_completo'])) {
        $nomeUsuario = $usuario['nome_completo'];
    }
}

/*
|--------------------------------------------------------------------------
| VERIFICAÇÃO DO ID DO USUÁRIO
|--------------------------------------------------------------------------
*/

if (!$usuarioId) {

    session_destroy();

    header('Location: login.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| MODELO DE VACINA
|--------------------------------------------------------------------------
*/

$vacinaModel = new Vacina($pdo);

/*
|--------------------------------------------------------------------------
| BUSCAR VACINAÇÕES DO USUÁRIO
|--------------------------------------------------------------------------
*/

$vacinacoes = $vacinaModel->listarPorUsuario($usuarioId);

if (!is_array($vacinacoes)) {
    $vacinacoes = [];
}

/*
|--------------------------------------------------------------------------
| SEPARAR COVID-19 E OUTRAS VACINAS
|--------------------------------------------------------------------------
*/

$covid19 = [];
$outrasVacinas = [];

foreach ($vacinacoes as $vacinacao) {

    $grupo = $vacinacao['grupo'] ?? '';

    if ($grupo === 'COVID-19') {

        $covid19[] = $vacinacao;

    } else {

        $outrasVacinas[] = $vacinacao;
    }
}

/*
|--------------------------------------------------------------------------
| FUNÇÕES AUXILIARES
|--------------------------------------------------------------------------
*/

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
    if (empty($data)) {
        return '-';
    }

    $dataObj = DateTime::createFromFormat(
        'Y-m-d',
        $data
    );

    if ($dataObj) {
        return $dataObj->format('d/m/Y');
    }

    return escapar($data);
}

function ordenarPorData($a, $b): int
{
    $dataA = $a['data_aplicacao'] ?? '';
    $dataB = $b['data_aplicacao'] ?? '';

    return strcmp($dataB, $dataA);
}

/*
|--------------------------------------------------------------------------
| ORDENAR POR DATA
|--------------------------------------------------------------------------
*/

usort(
    $covid19,
    'ordenarPorData'
);

usort(
    $outrasVacinas,
    'ordenarPorData'
);

/*
|--------------------------------------------------------------------------
| TOTAIS
|--------------------------------------------------------------------------
*/

$totalCovid = count($covid19);

$totalOutras = count($outrasVacinas);

$totalVacinas = $totalCovid + $totalOutras;

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
        Cartão de Vacinação - Saúde-Conecta
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

            padding: 22px 20px;

            box-shadow:
                0 3px 10px
                rgba(0, 0, 0, .15);
        }

        .topo-conteudo {

            max-width: 1250px;

            margin: 0 auto;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            flex-wrap: wrap;
        }

        .titulo-topo h1 {

            margin: 0;

            font-size: 27px;
        }

        .titulo-topo p {

            margin: 6px 0 0;

            opacity: .9;

            font-size: 14px;
        }

        .acoes-topo {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;
        }

        .btn {

            display: inline-block;

            padding: 11px 16px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            border: none;

            cursor: pointer;

            font-size: 14px;
        }

        .btn-voltar {

            background: white;

            color: #0f766e;
        }

        .btn-cadastrar {

            background: #16a34a;

            color: white;
        }

        .btn-sair {

            background: #dc2626;

            color: white;
        }

        .container {

            max-width: 1250px;

            margin: 30px auto;

            padding: 0 20px;
        }

        .cabecalho-cartao {

            background: white;

            border-radius: 16px;

            padding: 28px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, .07);
        }

        .cabecalho-cartao h2 {

            margin: 0 0 8px;

            color: #0f766e;

            font-size: 25px;
        }

        .cabecalho-cartao p {

            margin: 0;

            color: #64748b;

            line-height: 1.6;
        }

        .resumo {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-top: 22px;
        }

        .resumo-card {

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 12px;

            padding: 18px;

            text-align: center;
        }

        .resumo-card strong {

            display: block;

            font-size: 28px;

            color: #0f766e;

            margin-bottom: 5px;
        }

        .resumo-card span {

            color: #64748b;

            font-size: 13px;
        }

        .secao {

            background: white;

            border-radius: 16px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, .07);
        }

        .titulo-secao {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 20px;

            flex-wrap: wrap;
        }

        .titulo-secao h2 {

            margin: 0;

            color: #0f766e;

            font-size: 21px;
        }

        .quantidade {

            background: #ccfbf1;

            color: #0f766e;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;
        }

        .tabela-container {

            width: 100%;

            overflow-x: auto;

            border: 1px solid #e2e8f0;

            border-radius: 10px;
        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1050px;
        }

        thead {

            background: #0f766e;

            color: white;
        }

        th {

            padding: 13px 10px;

            text-align: left;

            font-size: 13px;

            white-space: nowrap;
        }

        td {

            padding: 13px 10px;

            border-bottom: 1px solid #e2e8f0;

            font-size: 13px;

            vertical-align: top;
        }

        tbody tr:hover {

            background: #f8fafc;
        }

        tbody tr:last-child td {

            border-bottom: none;
        }

        .acoes {

            white-space: nowrap;
        }

        .btn-editar {

            display: inline-block;

            background: #2563eb;

            color: white;

            padding: 7px 9px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 12px;

            font-weight: bold;

            margin-right: 4px;
        }

        .btn-excluir {

            display: inline-block;

            background: #dc2626;

            color: white;

            padding: 7px 9px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 12px;

            font-weight: bold;
        }

        .vazio {

            text-align: center;

            padding: 35px 20px;

            color: #64748b;

            background: #f8fafc;

            border-radius: 10px;

            border: 1px dashed #cbd5e1;
        }

        .vazio strong {

            display: block;

            color: #475569;

            margin-bottom: 7px;

            font-size: 16px;
        }

        .informacao {

            margin-top: 25px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1e40af;

            padding: 18px;

            border-radius: 12px;

            font-size: 14px;

            line-height: 1.6;
        }

        .rodape {

            text-align: center;

            color: #64748b;

            font-size: 13px;

            padding: 35px 20px;
        }

        @media (max-width: 800px) {

            .resumo {

                grid-template-columns: 1fr;
            }

            .container {

                padding: 0 12px;
            }

            .cabecalho-cartao,
            .secao {

                padding: 20px;
            }

            .topo-conteudo {

                align-items: flex-start;
            }

            .acoes-topo {

                width: 100%;
            }

            .acoes-topo .btn {

                flex: 1;

                text-align: center;
            }
        }

    </style>

</head>

<body>

<header class="topo">

    <div class="topo-conteudo">

        <div class="titulo-topo">

            <h1>
                Saúde-Conecta
            </h1>

            <p>
                Cartão de Vacinação
            </p>

        </div>

        <div class="acoes-topo">

            <!-- VOLTAR PARA O PERFIL -->

            <a
                href="../components/perfil_saude.php"
                class="btn btn-voltar"
            >
                ← Voltar para o Perfil de Saúde
            </a>

            <!-- CADASTRAR NOVA VACINA -->

            <a
                href="cadastrar_vacina.php"
                class="btn btn-cadastrar"
            >
                + Cadastrar vacina
            </a>

            <!-- SAIR -->

            <a
                href="logout.php"
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

    <section class="cabecalho-cartao">

        <h2>
            Cartão de Vacinação
        </h2>

        <p>

            Usuário:
            <strong>
                <?= escapar($nomeUsuario) ?>
            </strong>

        </p>

        <div class="resumo">

            <div class="resumo-card">

                <strong>
                    <?= $totalVacinas ?>
                </strong>

                <span>
                    Total de vacinações
                </span>

            </div>

            <div class="resumo-card">

                <strong>
                    <?= $totalCovid ?>
                </strong>

                <span>
                    Registros COVID-19
                </span>

            </div>

            <div class="resumo-card">

                <strong>
                    <?= $totalOutras ?>
                </strong>

                <span>
                    Outras vacinações
                </span>

            </div>

        </div>

    </section>


    <!-- ===================================================== -->
    <!-- COVID-19 -->
    <!-- ===================================================== -->

    <section class="secao">

        <div class="titulo-secao">

            <h2>
                COVID-19
            </h2>

            <span class="quantidade">
                <?= $totalCovid ?> registro(s)
            </span>

        </div>

        <?php if (empty($covid19)): ?>

            <div class="vazio">

                <strong>
                    Nenhum registro de COVID-19 encontrado.
                </strong>

                Os registros de vacinação contra COVID-19
                aparecerão nesta seção.

            </div>

        <?php else: ?>

            <div class="tabela-container">

                <table>

                    <thead>

                    <tr>

                        <th>
                            Vacina / Profilaxia
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
                            Estratégia
                        </th>

                        <th>
                            CNES
                        </th>

                        <th>
                            Estabelecimento de Saúde
                        </th>

                        <th>
                            Município
                        </th>

                        <th>
                            UF
                        </th>

                        <th>
                            Ações
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($covid19 as $vacinacao): ?>

                        <tr>

                            <td>
                                <?= escapar(
                                    $vacinacao['vacina'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= formatarData(
                                    $vacinacao['data_aplicacao'] ?? ''
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['dose'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['lote'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['estrategia'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['cnes'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['estabelecimento'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['municipio'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['uf'] ?? '-'
                                ) ?>
                            </td>

                            <td class="acoes">

                                <a
                                    href="editar_vacina.php?id=<?= (int) $vacinacao['id'] ?>"
                                    class="btn-editar"
                                >
                                    Editar
                                </a>

                                <a
                                    href="excluir_vacina.php?id=<?= (int) $vacinacao['id'] ?>"
                                    class="btn-excluir"
                                    onclick="
                                        return confirm(
                                            'Deseja realmente excluir esta vacinação?'
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


    <!-- ===================================================== -->
    <!-- OUTRAS VACINAS -->
    <!-- ===================================================== -->

    <section class="secao">

        <div class="titulo-secao">

            <h2>
                VACINAS | SOROS | DILUENTES ADMINISTRADOS
            </h2>

            <span class="quantidade">
                <?= $totalOutras ?> registro(s)
            </span>

        </div>

        <?php if (empty($outrasVacinas)): ?>

            <div class="vazio">

                <strong>
                    Nenhuma vacinação encontrada.
                </strong>

                Cadastre uma nova vacinação utilizando
                o botão "Cadastrar vacina".

            </div>

        <?php else: ?>

            <div class="tabela-container">

                <table>

                    <thead>

                    <tr>

                        <th>
                            Vacina / Profilaxia
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
                            Estratégia
                        </th>

                        <th>
                            CNES
                        </th>

                        <th>
                            Estabelecimento de Saúde
                        </th>

                        <th>
                            Município
                        </th>

                        <th>
                            UF
                        </th>

                        <th>
                            Ações
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($outrasVacinas as $vacinacao): ?>

                        <tr>

                            <td>
                                <?= escapar(
                                    $vacinacao['vacina'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= formatarData(
                                    $vacinacao['data_aplicacao'] ?? ''
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['dose'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['lote'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['estrategia'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['cnes'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['estabelecimento'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['municipio'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacinacao['uf'] ?? '-'
                                ) ?>
                            </td>

                            <td class="acoes">

                                <a
                                    href="editar_vacina.php?id=<?= (int) $vacinacao['id'] ?>"
                                    class="btn-editar"
                                >
                                    Editar
                                </a>

                                <a
                                    href="excluir_vacina.php?id=<?= (int) $vacinacao['id'] ?>"
                                    class="btn-excluir"
                                    onclick="
                                        return confirm(
                                            'Deseja realmente excluir esta vacinação?'
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


    <!-- ===================================================== -->
    <!-- INFORMAÇÃO -->
    <!-- ===================================================== -->

    <div class="informacao">

        <strong>
            Informações do cartão:
        </strong>

        Os registros apresentados pertencem à conta
        atualmente conectada ao Saúde-Conecta.

        Você pode cadastrar novas vacinações através
        do botão <strong>+ Cadastrar vacina</strong>.

        Também é possível editar ou excluir registros
        existentes.

    </div>

</main>

<footer class="rodape">

    Saúde-Conecta

    <br>

    Cartão de Vacinação

</footer>

</body>

</html>