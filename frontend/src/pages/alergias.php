<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

$pdo = Database::getConnection();

$usuario = $_SESSION['usuario'];

$usuarioId = 0;

if (isset($usuario['id'])) {
    $usuarioId = (int) $usuario['id'];
}

if ($usuarioId <= 0 && isset($usuario['id_usuario'])) {
    $usuarioId = (int) $usuario['id_usuario'];
}

if ($usuarioId <= 0) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$nomeUsuario = 'Usuário';

if (!empty($usuario['nomeCompleto'])) {
    $nomeUsuario = $usuario['nomeCompleto'];
} elseif (!empty($usuario['nome'])) {
    $nomeUsuario = $usuario['nome'];
} elseif (!empty($usuario['nome_completo'])) {
    $nomeUsuario = $usuario['nome_completo'];
}

$mensagemErro = '';

if (isset($_GET['excluir'])) {
    $idExcluir = (int) $_GET['excluir'];

    if ($idExcluir > 0) {
        try {
            $stmt = $pdo->prepare(
                'DELETE FROM alergias
                 WHERE id = :id
                 AND usuario_id = :usuario_id'
            );

            $stmt->execute([
                ':id' => $idExcluir,
                ':usuario_id' => $usuarioId
            ]);
        } catch (PDOException $e) {
            $mensagemErro = 'Erro ao excluir a alergia.';
        }
    }

    header('Location: alergias.php');
    exit;
}

$alergias = [];

try {
    $stmt = $pdo->prepare(
        'SELECT id, alergia, tipo, gravidade, reacao, observacao, criado_em
         FROM alergias
         WHERE usuario_id = :usuario_id
         ORDER BY id DESC'
    );

    $stmt->execute([
        ':usuario_id' => $usuarioId
    ]);

    $alergias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagemErro = 'Erro ao carregar as alergias cadastradas.';
}

$totalAlergias = count($alergias);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alergias - Saúde-Conecta</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

.topo {
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.logo {
    font-size: 25px;
    font-weight: bold;
    color: #087f5b;
}

.subtitulo {
    margin-top: 5px;
    font-size: 14px;
    color: #6b7280;
}

.acoes-topo {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.botao {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    border: none;
    cursor: pointer;
}

.perfil {
    background: #e8f5f0;
    color: #087f5b;
}

.adicionar {
    background: #087f5b;
    color: #ffffff;
}

.sair {
    background: #fee2e2;
    color: #b91c1c;
}

.container {
    max-width: 1250px;
    margin: 0 auto;
    padding: 30px 20px 50px;
}

.cabecalho {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.cabecalho h1 {
    margin: 0 0 8px;
    font-size: 30px;
}

.cabecalho p {
    margin: 0;
    color: #6b7280;
}

.contador {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 15px 25px;
    text-align: center;
}

.contador strong {
    display: block;
    font-size: 28px;
    color: #087f5b;
}

.contador span {
    font-size: 13px;
    color: #6b7280;
}

.alerta {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}

.tabela {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

th {
    background: #f8fafc;
    text-align: left;
    padding: 15px;
    color: #475569;
    font-size: 13px;
    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 15px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: top;
    font-size: 14px;
}

tr:last-child td {
    border-bottom: none;
}

.nome {
    font-weight: bold;
    color: #111827;
}

.gravidade {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.leve {
    background: #dcfce7;
    color: #166534;
}

.moderada {
    background: #fef3c7;
    color: #92400e;
}

.grave {
    background: #fee2e2;
    color: #991b1b;
}

.normal {
    background: #f1f5f9;
    color: #475569;
}

.sem-dado {
    color: #94a3b8;
    font-style: italic;
}

.acoes {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.editar {
    background: #e0f2fe;
    color: #0369a1;
}

.excluir {
    background: #fee2e2;
    color: #b91c1c;
}

.vazio {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 50px 25px;
    text-align: center;
}

.vazio h2 {
    margin-top: 0;
}

.vazio p {
    color: #6b7280;
    max-width: 550px;
    margin: 0 auto 25px;
    line-height: 1.6;
}

.rodape {
    margin-top: 25px;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 700px) {
    .topo {
        padding: 16px;
    }

    .container {
        padding: 22px 14px 40px;
    }

    .acoes-topo {
        width: 100%;
    }

    .acoes-topo .botao {
        flex: 1;
        text-align: center;
    }

    .cabecalho h1 {
        font-size: 25px;
    }

    .contador {
        width: 100%;
    }
}
</style>
</head>

<body>

<header class="topo">

    <div>
        <div class="logo">Saúde-Conecta</div>

        <div class="subtitulo">
            Perfil de saúde de
            <?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>

    <div class="acoes-topo">

        <a href="../components/perfil_saude.php" class="botao perfil">
            Perfil de Saúde
        </a>

        <a href="cadastrar_alergia.php" class="botao adicionar">
            Cadastrar alergia
        </a>

        <a href="logout.php" class="botao sair">
            Sair
        </a>

    </div>

</header>

<main class="container">

    <div class="cabecalho">

        <div>
            <h1>Minhas alergias</h1>

            <p>
                Consulte as alergias cadastradas no seu perfil de saúde.
            </p>
        </div>

        <div class="contador">

            <strong><?php echo $totalAlergias; ?></strong>

            <span>alergia(s) cadastrada(s)</span>

        </div>

    </div>

    <?php if ($mensagemErro !== ''): ?>

        <div class="alerta">
            <?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?>
        </div>

    <?php endif; ?>

    <?php if ($totalAlergias > 0): ?>

        <div class="tabela">

            <table>

                <thead>

                    <tr>
                        <th>Alergia</th>
                        <th>Tipo</th>
                        <th>Gravidade</th>
                        <th>Reação</th>
                        <th>Observação</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($alergias as $alergia): ?>

                    <?php
                    $gravidade = isset($alergia['gravidade'])
                        ? $alergia['gravidade']
                        : '';

                    $classeGravidade = 'normal';

                    if ($gravidade === 'Leve') {
                        $classeGravidade = 'leve';
                    } elseif ($gravidade === 'Moderada') {
                        $classeGravidade = 'moderada';
                    } elseif ($gravidade === 'Grave') {
                        $classeGravidade = 'grave';
                    }
                    ?>

                    <tr>

                        <td>
                            <div class="nome">
                                <?php echo htmlspecialchars(
                                    $alergia['alergia'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </div>
                        </td>

                        <td>

                            <?php if (!empty($alergia['tipo'])): ?>

                                <?php echo htmlspecialchars(
                                    $alergia['tipo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            <?php else: ?>

                                <span class="sem-dado">
                                    Não informado
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <span class="gravidade <?php echo $classeGravidade; ?>">

                                <?php if ($gravidade !== ''): ?>

                                    <?php echo htmlspecialchars(
                                        $gravidade,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                <?php else: ?>

                                    Não informada

                                <?php endif; ?>

                            </span>

                        </td>

                        <td>

                            <?php if (!empty($alergia['reacao'])): ?>

                                <?php echo nl2br(
                                    htmlspecialchars(
                                        $alergia['reacao'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ); ?>

                            <?php else: ?>

                                <span class="sem-dado">
                                    Não informado
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (!empty($alergia['observacao'])): ?>

                                <?php echo nl2br(
                                    htmlspecialchars(
                                        $alergia['observacao'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ); ?>

                            <?php else: ?>

                                <span class="sem-dado">
                                    Não informado
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="acoes">

                                <a
                                    href="editar_alergia.php?id=<?php echo (int) $alergia['id']; ?>"
                                    class="botao editar"
                                >
                                    Editar
                                </a>

                                <a
                                    href="alergias.php?excluir=<?php echo (int) $alergia['id']; ?>"
                                    class="botao excluir"
                                    onclick="return confirm('Tem certeza que deseja excluir esta alergia?');"
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

    <?php else: ?>

        <div class="vazio">

            <h2>Nenhuma alergia cadastrada</h2>

            <p>
                Você ainda não possui alergias cadastradas.
                Clique no botão abaixo para cadastrar uma alergia.
            </p>

            <a href="cadastrar_alergia.php" class="botao adicionar">
                Cadastrar primeira alergia
            </a>

        </div>

    <?php endif; ?>

    <div class="rodape">

        <a
            href="../components/perfil_saude.php"
            class="botao perfil"
        >
            Voltar para o Perfil de Saúde
        </a>

        <a
            href="cadastrar_alergia.php"
            class="botao adicionar"
        >
            + Cadastrar alergia
        </a>

    </div>

</main>

</body>
</html>