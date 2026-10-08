<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

$pdo = Database::getConnection();

$usuario = $_SESSION['usuario'];

$usuarioId = (int) (
    $usuario['id']
    ?? $usuario['id_usuario']
    ?? 0
);

if ($usuarioId <= 0) {
    die('Erro: usuário não identificado.');
}

$nomeUsuario =
    $usuario['nomeCompleto']
    ?? $usuario['nome']
    ?? $usuario['nome_completo']
    ?? 'Usuário';


/*
|--------------------------------------------------------------------------
| TIPO DA PÁGINA
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| FUNÇÕES
|--------------------------------------------------------------------------
*/

function escapar($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatarData($data)
{
    if (empty($data)) {
        return '-';
    }

    $timestamp = strtotime($data);

    if (!$timestamp) {
        return $data;
    }

    return date('d/m/Y', $timestamp);
}


/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO DA PÁGINA
|--------------------------------------------------------------------------
*/

$config = [

    'vacina' => [
        'titulo' => 'Cartão de Vacinação',
        'descricao' => 'Consulte e gerencie suas vacinas.',
        'botao' => '+ Adicionar Vacina',
        'linkCadastro' => 'cadastro.php?tipo=vacina'
    ],

    'alergia' => [
        'titulo' => 'Alergias',
        'descricao' => 'Consulte e gerencie suas alergias.',
        'botao' => '+ Adicionar Alergia',
        'linkCadastro' => 'cadastro.php?tipo=alergia'
    ],

    'alergia_vacina' => [
        'titulo' => 'Alergias a Vacinas',
        'descricao' => 'Consulte seu histórico de reações a vacinas.',
        'botao' => '+ Adicionar Registro',
        'linkCadastro' => 'cadastro.php?tipo=alergia_vacina'
    ],

    'doenca' => [
        'titulo' => 'Histórico de Doenças',
        'descricao' => 'Consulte e gerencie seu histórico de doenças.',
        'botao' => '+ Adicionar Doença',
        'linkCadastro' => 'cadastro.php?tipo=doenca'
    ],

    'atestado' => [
        'titulo' => 'Atestados Médicos',
        'descricao' => 'Consulte e gerencie seus atestados.',
        'botao' => '+ Adicionar Atestado',
        'linkCadastro' => 'cadastro.php?tipo=atestado'
    ]

];

$tituloPagina = $config[$tipo]['titulo'];
$descricaoPagina = $config[$tipo]['descricao'];
$botaoCadastro = $config[$tipo]['botao'];
$linkCadastro = $config[$tipo]['linkCadastro'];


/*
|--------------------------------------------------------------------------
| BUSCAR DADOS SOMENTE DO TIPO SELECIONADO
|--------------------------------------------------------------------------
*/

$registros = [];


/*
|--------------------------------------------------------------------------
| VACINAS
|--------------------------------------------------------------------------
*/

if ($tipo === 'vacina') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM vacinacoes
        WHERE usuario_id = ?
        ORDER BY data_aplicacao DESC, id DESC
    ");

    $stmt->execute([$usuarioId]);

    $registros = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| ALERGIAS
|--------------------------------------------------------------------------
*/

if ($tipo === 'alergia') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM alergias
        WHERE usuario_id = ?
        ORDER BY id DESC
    ");

    $stmt->execute([$usuarioId]);

    $registros = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| ALERGIAS A VACINAS
|--------------------------------------------------------------------------
*/

if ($tipo === 'alergia_vacina') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM alergias_vacinas
        WHERE usuario_id = ?
        ORDER BY id DESC
    ");

    $stmt->execute([$usuarioId]);

    $registros = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| DOENÇAS
|--------------------------------------------------------------------------
*/

if ($tipo === 'doenca') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM historico_doencas
        WHERE usuario_id = ?
        ORDER BY id DESC
    ");

    $stmt->execute([$usuarioId]);

    $registros = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| ATESTADOS
|--------------------------------------------------------------------------
*/

if ($tipo === 'atestado') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM atestados
        WHERE usuario_id = ?
        ORDER BY id DESC
    ");

    $stmt->execute([$usuarioId]);

    $registros = $stmt->fetchAll();
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
        <?= escapar($tituloPagina) ?> - Saúde-Conecta
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f9;
            color: #263238;
        }

        /*
        ------------------------------------------------------------
        TOPO
        ------------------------------------------------------------
        */

        .topo {
            background: #087f5b;
            color: white;

            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            flex-wrap: wrap;

            gap: 15px;
        }

        .topo h1 {
            margin: 0;
            font-size: 25px;
        }

        .topo p {
            margin: 5px 0 0;
            opacity: 0.9;
        }

        .acoes-topo {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .botao-topo {
            text-decoration: none;
            color: white;

            border: 1px solid rgba(255,255,255,0.5);

            padding: 10px 15px;

            border-radius: 8px;

            transition: 0.2s;
        }

        .botao-topo:hover {
            background: rgba(255,255,255,0.15);
        }


        /*
        ------------------------------------------------------------
        CONTAINER
        ------------------------------------------------------------
        */

        .container {
            width: 95%;
            max-width: 1400px;

            margin: 30px auto;
        }


        /*
        ------------------------------------------------------------
        NAVEGAÇÃO ENTRE INFORMAÇÕES
        ------------------------------------------------------------
        */

        .menu-saude {
            background: white;

            border-radius: 14px;

            padding: 18px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.07);

            display: flex;

            gap: 10px;

            flex-wrap: wrap;
        }

        .menu-saude a {
            text-decoration: none;

            color: #455a64;

            background: #f1f5f7;

            padding: 10px 14px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            transition: 0.2s;
        }

        .menu-saude a:hover {
            background: #dfeee9;
            color: #087f5b;
        }

        .menu-saude a.ativo {
            background: #087f5b;
            color: white;
        }


        /*
        ------------------------------------------------------------
        CABEÇALHO DA SEÇÃO
        ------------------------------------------------------------
        */

        .cabecalho {
            background: white;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.07);

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            flex-wrap: wrap;
        }

        .cabecalho h2 {
            margin: 0;

            color: #087f5b;

            font-size: 24px;
        }

        .cabecalho p {
            margin: 8px 0 0;

            color: #607d8b;
        }

        .botao-adicionar {
            background: #087f5b;

            color: white;

            text-decoration: none;

            padding: 12px 17px;

            border-radius: 8px;

            font-weight: bold;

            white-space: nowrap;
        }

        .botao-adicionar:hover {
            background: #066b4d;
        }


        /*
        ------------------------------------------------------------
        TABELA
        ------------------------------------------------------------
        */

        .tabela-card {
            background: white;

            border-radius: 14px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.07);

            overflow: hidden;
        }

        .tabela-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 850px;
        }

        th,
        td {
            padding: 14px 15px;

            border-bottom:
                1px solid #eeeeee;

            text-align: left;

            vertical-align: top;
        }

        th {
            background: #f8fafb;

            color: #455a64;

            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        tr:hover td {
            background: #fafdfc;
        }


        /*
        ------------------------------------------------------------
        VAZIO
        ------------------------------------------------------------
        */

        .vazio {
            background: white;

            border-radius: 14px;

            padding: 50px 30px;

            text-align: center;

            color: #78909c;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.07);
        }

        .vazio h3 {
            margin-top: 0;

            color: #455a64;
        }


        /*
        ------------------------------------------------------------
        AÇÕES
        ------------------------------------------------------------
        */

        .acoes {
            display: flex;

            gap: 7px;

            flex-wrap: wrap;
        }

        .editar {
            background: #1976d2;

            color: white;

            text-decoration: none;

            padding: 7px 10px;

            border-radius: 6px;

            font-size: 13px;
        }

        .excluir {
            background: #d32f2f;

            color: white;

            text-decoration: none;

            padding: 7px 10px;

            border-radius: 6px;

            font-size: 13px;
        }


        /*
        ------------------------------------------------------------
        RODAPÉ
        ------------------------------------------------------------
        */

        .rodape {
            text-align: center;

            color: #78909c;

            padding: 25px;
        }


        /*
        ------------------------------------------------------------
        RESPONSIVO
        ------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .topo {
                padding: 18px;
            }

            .container {
                width: 94%;
            }

            .cabecalho {
                align-items: flex-start;
            }

            .botao-adicionar {
                width: 100%;
                text-align: center;
            }

        }

    </style>

</head>


<body>


<!-- ============================================================
     TOPO
============================================================ -->

<header class="topo">

    <div>

        <h1>
            <?= escapar($tituloPagina) ?>
        </h1>

        <p>
            Olá,
            <?= escapar($nomeUsuario) ?>.
        </p>

    </div>


    <div class="acoes-topo">

        <a
            href="../components/perfil_saude.php"
            class="botao-topo"
        >
            Perfil de Saúde
        </a>

        <a
            href="../components/mapa.php"
            class="botao-topo"
        >
            Mapa de Saúde
        </a>

        <a
            href="../components/logout.php"
            class="botao-topo"
        >
            Sair
        </a>

    </div>

</header>


<main class="container">


<!-- ============================================================
     MENU DE INFORMAÇÕES
============================================================ -->

<nav class="menu-saude">

    <a
        href="saude.php?tipo=vacina"
        class="<?= $tipo === 'vacina' ? 'ativo' : '' ?>"
    >
        Cartão de Vacinação
    </a>


    <a
        href="saude.php?tipo=alergia"
        class="<?= $tipo === 'alergia' ? 'ativo' : '' ?>"
    >
        Alergias
    </a>


    <a
        href="saude.php?tipo=alergia_vacina"
        class="<?= $tipo === 'alergia_vacina' ? 'ativo' : '' ?>"
    >
        Alergias a Vacinas
    </a>


    <a
        href="saude.php?tipo=doenca"
        class="<?= $tipo === 'doenca' ? 'ativo' : '' ?>"
    >
        Histórico de Doenças
    </a>


    <a
        href="saude.php?tipo=atestado"
        class="<?= $tipo === 'atestado' ? 'ativo' : '' ?>"
    >
        Atestados
    </a>

</nav>


<!-- ============================================================
     CABEÇALHO
============================================================ -->

<section class="cabecalho">

    <div>

        <h2>
            <?= escapar($tituloPagina) ?>
        </h2>

        <p>
            <?= escapar($descricaoPagina) ?>
        </p>

    </div>


    <a
        href="<?= escapar($linkCadastro) ?>"
        class="botao-adicionar"
    >
        <?= escapar($botaoCadastro) ?>
    </a>

</section>


<!-- ============================================================
     VACINAÇÕES
============================================================ -->

<?php if ($tipo === 'vacina'): ?>


    <?php if (empty($registros)): ?>

        <div class="vazio">

            <h3>
                Nenhuma vacinação cadastrada
            </h3>

            <p>
                Clique em "Adicionar Vacina" para registrar
                uma vacinação.
            </p>

        </div>

    <?php else: ?>

        <section class="tabela-card">

            <div class="tabela-container">

                <table>

                    <thead>

                        <tr>

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
                                Estratégia
                            </th>

                            <th>
                                Estabelecimento
                            </th>

                            <th>
                                Ações
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($registros as $vacina): ?>

                        <tr>

                            <td>
                                <?= escapar(
                                    $vacina['vacina']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= formatarData(
                                    $vacina['data_aplicacao']
                                    ?? null
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacina['dose']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacina['lote']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacina['estrategia']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $vacina['estabelecimento']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>

                                <div class="acoes">

                                    <a
                                        href="editar.php?tipo=vacina&id=<?= (int) $vacina['id'] ?>"
                                        class="editar"
                                    >
                                        Editar
                                    </a>

                                    <a
                                        href="editar.php?tipo=vacina&id=<?= (int) $vacina['id'] ?>&acao=excluir"
                                        class="excluir"
                                        onclick="return confirm('Deseja realmente excluir esta vacinação?');"
                                    >
                                        Excluir
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>


<?php endif; ?>


<!-- ============================================================
     ALERGIAS
============================================================ -->

<?php if ($tipo === 'alergia'): ?>


    <?php if (empty($registros)): ?>

        <div class="vazio">

            <h3>
                Nenhuma alergia cadastrada
            </h3>

            <p>
                Clique em "Adicionar Alergia" para registrar
                uma alergia.
            </p>

        </div>

    <?php else: ?>

        <section class="tabela-card">

            <div class="tabela-container">

                <table>

                    <thead>

                        <tr>

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

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($registros as $alergia): ?>

                        <tr>

                            <td>
                                <?= escapar(
                                    $alergia['alergia']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $alergia['tipo']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $alergia['gravidade']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    escapar(
                                        $alergia['reacao']
                                        ?? '-'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    escapar(
                                        $alergia['observacao']
                                        ?? '-'
                                    )
                                ) ?>
                            </td>

                            <td>

                                <div class="acoes">

                                    <a
                                        href="editar.php?tipo=alergia&id=<?= (int) $alergia['id'] ?>"
                                        class="editar"
                                    >
                                        Editar
                                    </a>

                                    <a
                                        href="editar.php?tipo=alergia&id=<?= (int) $alergia['id'] ?>&acao=excluir"
                                        class="excluir"
                                        onclick="return confirm('Deseja realmente excluir esta alergia?');"
                                    >
                                        Excluir
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>


<?php endif; ?>


<!-- ============================================================
     ALERGIAS A VACINAS
============================================================ -->

<?php if ($tipo === 'alergia_vacina'): ?>


    <?php if (empty($registros)): ?>

        <div class="vazio">

            <h3>
                Nenhuma alergia a vacina cadastrada
            </h3>

            <p>
                Clique em "Adicionar Registro" para cadastrar
                uma reação relacionada a vacina.
            </p>

        </div>

    <?php else: ?>

        <section class="tabela-card">

            <div class="tabela-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Vacina
                            </th>

                            <th>
                                Reação
                            </th>

                            <th>
                                Gravidade
                            </th>

                            <th>
                                Data
                            </th>

                            <th>
                                Observação
                            </th>

                            <th>
                                Ações
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($registros as $registro): ?>

                        <tr>

                            <td>
                                <?= escapar(
                                    $registro['vacina']
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
                                <?= escapar(
                                    $registro['gravidade']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= formatarData(
                                    $registro['data_reacao']
                                    ?? null
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

                            <td>

                                <div class="acoes">

                                    <a
                                        href="editar.php?tipo=alergia_vacina&id=<?= (int) $registro['id'] ?>"
                                        class="editar"
                                    >
                                        Editar
                                    </a>

                                    <a
                                        href="editar.php?tipo=alergia_vacina&id=<?= (int) $registro['id'] ?>&acao=excluir"
                                        class="excluir"
                                        onclick="return confirm('Deseja realmente excluir este registro?');"
                                    >
                                        Excluir
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>


<?php endif; ?>


<!-- ============================================================
     DOENÇAS
============================================================ -->

<?php if ($tipo === 'doenca'): ?>


    <?php if (empty($registros)): ?>

        <div class="vazio">

            <h3>
                Nenhuma doença cadastrada
            </h3>

            <p>
                Clique em "Adicionar Doença" para registrar
                uma doença no seu histórico.
            </p>

        </div>

    <?php else: ?>

        <section class="tabela-card">

            <div class="tabela-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Doença
                            </th>

                            <th>
                                Data do Diagnóstico
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

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($registros as $doenca): ?>

                        <tr>

                            <td>
                                <?= escapar(
                                    $doenca['doenca']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= formatarData(
                                    $doenca['data_diagnostico']
                                    ?? null
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $doenca['status']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    escapar(
                                        $doenca['tratamento']
                                        ?? '-'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    escapar(
                                        $doenca['observacao']
                                        ?? '-'
                                    )
                                ) ?>
                            </td>

                            <td>

                                <div class="acoes">

                                    <a
                                        href="editar.php?tipo=doenca&id=<?= (int) $doenca['id'] ?>"
                                        class="editar"
                                    >
                                        Editar
                                    </a>

                                    <a
                                        href="editar.php?tipo=doenca&id=<?= (int) $doenca['id'] ?>&acao=excluir"
                                        class="excluir"
                                        onclick="return confirm('Deseja realmente excluir esta doença?');"
                                    >
                                        Excluir
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>


<?php endif; ?>


<!-- ============================================================
     ATESTADOS
============================================================ -->

<?php if ($tipo === 'atestado'): ?>


    <?php if (empty($registros)): ?>

        <div class="vazio">

            <h3>
                Nenhum atestado cadastrado
            </h3>

            <p>
                Clique em "Adicionar Atestado" para registrar
                um atestado.
            </p>

        </div>

    <?php else: ?>

        <section class="tabela-card">

            <div class="tabela-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Profissional
                            </th>

                            <th>
                                Registro
                            </th>

                            <th>
                                Emissão
                            </th>

                            <th>
                                Validade
                            </th>

                            <th>
                                Observação
                            </th>

                            <th>
                                Ações
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($registros as $atestado): ?>

                        <tr>

                            <td>
                                <?= escapar(
                                    $atestado['tipo']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $atestado['profissional']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= escapar(
                                    $atestado['registro_profissional']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= formatarData(
                                    $atestado['data_emissao']
                                    ?? $atestado['data']
                                    ?? null
                                ) ?>
                            </td>

                            <td>
                                <?= formatarData(
                                    $atestado['data_validade']
                                    ?? null
                                ) ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    escapar(
                                        $atestado['observacao']
                                        ?? '-'
                                    )
                                ) ?>
                            </td>

                            <td>

                                <div class="acoes">

                                    <a
                                        href="editar.php?tipo=atestado&id=<?= (int) $atestado['id'] ?>"
                                        class="editar"
                                    >
                                        Editar
                                    </a>

                                    <a
                                        href="editar.php?tipo=atestado&id=<?= (int) $atestado['id'] ?>&acao=excluir"
                                        class="excluir"
                                        onclick="return confirm('Deseja realmente excluir este atestado?');"
                                    >
                                        Excluir
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>


<?php endif; ?>


</main>


<footer class="rodape">

    Saúde-Conecta © <?= date('Y') ?>

</footer>


</body>

</html>