<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Saúde-Conecta — Informações de Saúde Geral
|--------------------------------------------------------------------------
| Arquivo: frontend/src/components/saude_geral.php
|
| Requisitos:
| - backend/config/database.php
| - backend/config/seguranca.php
|
| A página permite que o usuário autenticado consulte e atualize
| apenas as próprias informações de saúde.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../../backend/config/seguranca.php';

iniciarSessaoSegura();

$pdo = Database::getConnection();

// Exige autenticação e confirma que a conta continua ativa.
$usuarioSessao = exigirLogin();
$usuarioAtual = exigirDonoDaConta($pdo);

$pacienteId = (int) $usuarioAtual['id'];

$erro = '';
$sucesso = '';
$dados = [];
$colunasDisponiveis = [];

/*
|--------------------------------------------------------------------------
| Descobrir as colunas existentes
|--------------------------------------------------------------------------
| Isso evita tentar ler ou gravar colunas que não existem na tabela.
*/

try {
    $resultadoColunas = $pdo->query('SHOW COLUMNS FROM usuarios');

    foreach ($resultadoColunas->fetchAll(PDO::FETCH_ASSOC) as $coluna) {
        $colunasDisponiveis[] = $coluna['Field'];
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Não foi possível verificar a estrutura do banco de dados.');
}

$camposSaude = [
    'sexo',
    'peso',
    'altura',
    'tipo_sanguineo',
    'gestante',
];

$camposAusentes = array_values(
    array_diff($camposSaude, $colunasDisponiveis)
);

/*
|--------------------------------------------------------------------------
| Token de segurança dos formulários
|--------------------------------------------------------------------------
*/

$tokenCsrf = gerarTokenCsrf();

/*
|--------------------------------------------------------------------------
| Ler dados do titular
|--------------------------------------------------------------------------
*/

try {
    $camposConsulta = ['id'];

    foreach ($camposSaude as $campo) {
        if (in_array($campo, $colunasDisponiveis, true)) {
            $camposConsulta[] = $campo;
        }
    }

    $sqlConsulta = 'SELECT '
        . implode(', ', $camposConsulta)
        . ' FROM usuarios WHERE id = :id LIMIT 1';

    $stmt = $pdo->prepare($sqlConsulta);
    $stmt->execute(['id' => $pacienteId]);

    $dadosBanco = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dadosBanco) {
        http_response_code(404);
        exit('Usuário não encontrado.');
    }

    $dados = $dadosBanco;
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar suas informações de saúde.');
}

/*
|--------------------------------------------------------------------------
| Salvar informações de saúde
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validarTokenCsrf($_POST['csrf_token'] ?? null);

        $sexo = trim((string) ($_POST['sexo'] ?? ''));

        $sexosPermitidos = [
            'Feminino',
            'Masculino',
            'Outro',
            'Prefiro não informar',
        ];

        if (!in_array($sexo, $sexosPermitidos, true)) {
            throw new InvalidArgumentException(
                'Selecione uma opção válida para o sexo.'
            );
        }

        $pesoTexto = trim((string) ($_POST['peso'] ?? ''));
        $alturaTexto = trim((string) ($_POST['altura'] ?? ''));
        $tipoSanguineo = trim(
            (string) ($_POST['tipo_sanguineo'] ?? '')
        );

        /*
         * Aceita vírgula ou ponto como separador decimal.
         */
        $peso = null;
        if ($pesoTexto !== '') {
            $pesoNormalizado = str_replace(',', '.', $pesoTexto);

            if (
                !is_numeric($pesoNormalizado) ||
                (float) $pesoNormalizado <= 0 ||
                (float) $pesoNormalizado > 700
            ) {
                throw new InvalidArgumentException(
                    'Informe um peso válido em quilogramas.'
                );
            }

            $peso = (float) $pesoNormalizado;
        }

        $altura = null;
        if ($alturaTexto !== '') {
            $alturaNormalizada = str_replace(',', '.', $alturaTexto);

            if (
                !is_numeric($alturaNormalizada) ||
                (float) $alturaNormalizada <= 0 ||
                (float) $alturaNormalizada > 2.8
            ) {
                throw new InvalidArgumentException(
                    'Informe uma altura válida em metros. Exemplo: 1,75.'
                );
            }

            $altura = (float) $alturaNormalizada;
        }

        $tiposSanguineosPermitidos = [
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

        if (
            !in_array(
                strtoupper($tipoSanguineo),
                $tiposSanguineosPermitidos,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Selecione um tipo sanguíneo válido.'
            );
        }

        $tipoSanguineo = strtoupper($tipoSanguineo);

        /*
         * A gravidez só pode ser marcada nesta página quando
         * o sexo selecionado for Feminino.
         */
        $gestante = 0;

        if ($sexo === 'Feminino') {
            $respostaGestante = $_POST['gestante'] ?? '0';

            if (!in_array((string) $respostaGestante, ['0', '1'], true)) {
                throw new InvalidArgumentException(
                    'Selecione se está grávida.'
                );
            }

            $gestante = (int) $respostaGestante;
        }

        /*
         * Monta o UPDATE somente com colunas conhecidas.
         * Os nomes dos campos vêm de uma lista fixa.
         */
        $valores = [
            'sexo' => $sexo,
            'peso' => $peso,
            'altura' => $altura,
            'tipo_sanguineo' => $tipoSanguineo !== ''
                ? $tipoSanguineo
                : null,
            'gestante' => $gestante,
        ];

        $atualizacoes = [];
        $parametros = ['id' => $pacienteId];

        foreach ($valores as $campo => $valor) {
            if (in_array($campo, $colunasDisponiveis, true)) {
                $atualizacoes[] = "{$campo} = :{$campo}";
                $parametros[$campo] = $valor;
            }
        }

        if (empty($atualizacoes)) {
            throw new RuntimeException(
                'Nenhum campo de saúde está disponível para atualização.'
            );
        }

        $sqlAtualizacao = 'UPDATE usuarios SET '
            . implode(', ', $atualizacoes)
            . ' WHERE id = :id';

        $stmt = $pdo->prepare($sqlAtualizacao);
        $stmt->execute($parametros);

        $sucesso = 'Informações de saúde salvas com sucesso.';

        // Atualiza os dados apresentados na tela.
        foreach ($valores as $campo => $valor) {
            if (in_array($campo, $colunasDisponiveis, true)) {
                $dados[$campo] = $valor;
            }
        }

        // Gera novo token após salvar.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $tokenCsrf = $_SESSION['csrf_token'];

    } catch (InvalidArgumentException $e) {
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        error_log($e->getMessage());

        if ($erro === '') {
            $erro = 'Não foi possível salvar as informações. '
                . 'Verifique os dados e tente novamente.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Preparar valores para exibição
|--------------------------------------------------------------------------
*/

$sexoAtual = (string) ($dados['sexo'] ?? '');
$gestanteAtual = (int) ($dados['gestante'] ?? 0);

function escaparSaude(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Informações de Saúde Geral - Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f2f7f8;
            color: #263238;
            font-family: Arial, Helvetica, sans-serif;
        }

        header {
            background: #087f8c;
            color: #fff;
            padding: 20px;
        }

        .cabecalho {
            max-width: 1050px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .cabecalho h1 {
            margin: 0;
            font-size: 24px;
        }

        .voltar {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            color: #087f8c;
            background: #fff;
            text-decoration: none;
            font-weight: bold;
        }

        main {
            max-width: 1050px;
            margin: 30px auto;
            padding: 0 18px 35px;
        }

        .painel {
            background: #fff;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .05);
        }

        .painel h2 {
            color: #087f8c;
            margin-top: 0;
        }

        .descricao {
            color: #5f6f75;
            line-height: 1.6;
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .campo {
            margin-bottom: 18px;
        }

        .campo label,
        .rotulo {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #cbd5da;
            border-radius: 8px;
            background: #fff;
            color: #263238;
            font-size: 16px;
        }

        input:focus,
        select:focus {
            outline: 2px solid #8ad3d9;
            border-color: #087f8c;
        }

        .opcoes {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }

        .opcoes label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }

        .opcoes input {
            width: 18px;
            min-height: 18px;
            height: 18px;
        }

        .botao {
            display: inline-block;
            border: 0;
            border-radius: 8px;
            padding: 13px 20px;
            background: #087f8c;
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            text-decoration: none;
            cursor: pointer;
        }

        .botao:hover {
            background: #066a75;
        }

        .mensagem {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .mensagem.sucesso {
            background: #e8f7ed;
            color: #176536;
        }

        .mensagem.erro {
            background: #fff0f0;
            color: #a32020;
        }

        .alerta {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fff5dd;
            color: #76540b;
            line-height: 1.5;
        }

        .gestacao {
            padding: 20px;
            border-radius: 10px;
            background: #fff3f7;
            border: 1px solid #f0cad9;
            margin-top: 20px;
        }

        .gestacao h3 {
            color: #9b3865;
            margin-top: 0;
        }

        .atalhos {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .atalho {
            display: block;
            padding: 18px;
            border: 1px solid #dce8eb;
            border-radius: 10px;
            color: #087f8c;
            text-decoration: none;
            font-weight: bold;
            background: #fbfefe;
        }

        .atalho:hover {
            background: #edf8f9;
            border-color: #087f8c;
        }

        .atalho small {
            display: block;
            margin-top: 7px;
            color: #64757b;
            font-weight: normal;
            line-height: 1.4;
        }

        .nota {
            color: #64757b;
            font-size: 14px;
            line-height: 1.5;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #64757b;
        }

        @media (max-width: 650px) {
            .grade,
            .atalhos {
                grid-template-columns: 1fr;
            }

            .painel {
                padding: 19px;
            }

            .cabecalho h1 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="cabecalho">
        <h1>Saúde-Conecta</h1>

        <a class="voltar" href="perfil_saude.php">
            Voltar ao perfil de saúde
        </a>
    </div>
</header>

<main>
    <section class="painel">
        <h2>Informações de Saúde Geral</h2>

        <p class="descricao">
            Consulte e atualize suas informações pessoais de saúde.
            Os dados desta página são associados à sua conta.
        </p>

        <?php if ($sucesso !== ''): ?>
            <div class="mensagem sucesso" role="status">
                <?= escaparSaude($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div class="mensagem erro" role="alert">
                <?= escaparSaude($erro) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($camposAusentes)): ?>
            <div class="alerta">
                <strong>Atenção:</strong>
                algumas informações ainda não estão disponíveis no banco:
                <?= escaparSaude(implode(', ', $camposAusentes)) ?>.
                Os campos ausentes não poderão ser salvos até que as
                respectivas colunas sejam criadas.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escaparSaude($tokenCsrf) ?>"
            >

            <div class="grade">
                <div class="campo">
                    <label for="sexo">Sexo</label>

                    <select id="sexo" name="sexo" required>
                        <option value="">Selecione</option>

                        <option
                            value="Feminino"
                            <?= $sexoAtual === 'Feminino' ? 'selected' : '' ?>
                        >
                            Feminino
                        </option>

                        <option
                            value="Masculino"
                            <?= $sexoAtual === 'Masculino' ? 'selected' : '' ?>
                        >
                            Masculino
                        </option>

                        <option
                            value="Outro"
                            <?= $sexoAtual === 'Outro' ? 'selected' : '' ?>
                        >
                            Outro
                        </option>

                        <option
                            value="Prefiro não informar"
                            <?= $sexoAtual === 'Prefiro não informar'
                                ? 'selected'
                                : '' ?>
                        >
                            Prefiro não informar
                        </option>
                    </select>
                </div>

                <div class="campo">
                    <label for="tipo_sanguineo">Tipo sanguíneo</label>

                    <select id="tipo_sanguineo" name="tipo_sanguineo">
                        <option value="">Não informado</option>

                        <?php
                        $tipos = [
                            'A+', 'A-', 'B+', 'B-',
                            'AB+', 'AB-', 'O+', 'O-',
                        ];
                        ?>

                        <?php foreach ($tipos as $tipo): ?>
                            <option
                                value="<?= escaparSaude($tipo) ?>"
                                <?= ($dados['tipo_sanguineo'] ?? '') === $tipo
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escaparSaude($tipo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="peso">Peso (kg)</label>

                    <input
                        type="text"
                        inputmode="decimal"
                        id="peso"
                        name="peso"
                        placeholder="Ex.: 70,5"
                        value="<?= escaparSaude($dados['peso'] ?? '') ?>"
                    >
                </div>

                <div class="campo">
                    <label for="altura">Altura (metros)</label>

                    <input
                        type="text"
                        inputmode="decimal"
                        id="altura"
                        name="altura"
                        placeholder="Ex.: 1,75"
                        value="<?= escaparSaude($dados['altura'] ?? '') ?>"
                    >
                </div>
            </div>

            <section
                class="gestacao"
                id="areaGestante"
                <?= $sexoAtual !== 'Feminino' ? 'hidden' : '' ?>
            >
                <h3>Área Exclusiva para Gestantes</h3>

                <p class="descricao">
                    Informe se você está grávida para que essa informação
                    fique registrada no seu perfil de saúde.
                </p>

                <span class="rotulo">Está grávida?</span>

                <div class="opcoes">
                    <label>
                        <input
                            type="radio"
                            name="gestante"
                            value="1"
                            <?= $gestanteAtual === 1 ? 'checked' : '' ?>
                        >
                        Sim
                    </label>

                    <label>
                        <input
                            type="radio"
                            name="gestante"
                            value="0"
                            <?= $gestanteAtual !== 1 ? 'checked' : '' ?>
                        >
                        Não
                    </label>
                </div>

                <p class="nota">
                    Esta informação é fornecida por você e não substitui
                    avaliação ou acompanhamento de um profissional de saúde.
                </p>
            </section>

            <p class="nota">
                Confira as informações antes de salvar. Os campos de peso
                e altura aceitam vírgula ou ponto decimal.
            </p>

            <button class="botao" type="submit">
                Salvar informações
            </button>
        </form>
    </section>

    <section class="painel">
        <h2>Outras informações médicas</h2>

        <p class="descricao">
            Acesse as demais áreas do seu perfil de saúde.
        </p>

        <div class="atalhos">
            <a class="atalho" href="../pages/cartao_vacinacao.php">
                Cartão de vacinação
                <small>Consultar e gerenciar registros de vacinas.</small>
            </a>

            <a class="atalho" href="../pages/alergias.php">
                Alergias
                <small>Consultar ou registrar alergias conhecidas.</small>
            </a>

            <a class="atalho" href="../pages/reacoes_vacinas.php">
                Reações a vacinas
                <small>Registrar histórico de reações a vacinas.</small>
            </a>

            <a class="atalho" href="../pages/historico_doencas.php">
                Histórico de doenças
                <small>Consultar ou registrar informações de saúde.</small>
            </a>

            <a class="atalho" href="../pages/atestados.php">
                Atestados
                <small>Acessar a área de documentos médicos.</small>
            </a>

            <a class="atalho" href="../components/perfil_saude.php">
                Perfil de saúde
                <small>Voltar à página principal das suas informações médicas.</small>
            </a>
        </div>

        <p class="nota">
            Os endereços dos atalhos precisam corresponder aos arquivos
            existentes no seu projeto. Se alguma página tiver outro nome,
            ajuste o respectivo link.
        </p>
    </section>
</main>

<footer>
    Saúde-Conecta — Informações de saúde
</footer>

<script>
    const campoSexo = document.getElementById('sexo');
    const areaGestante = document.getElementById('areaGestante');

    function atualizarAreaGestante() {
        const feminino = campoSexo.value === 'Feminino';

        areaGestante.hidden = !feminino;

        const opcoesGestante = areaGestante.querySelectorAll(
            'input[name="gestante"]'
        );

        opcoesGestante.forEach(function (opcao) {
            opcao.disabled = !feminino;
        });

        if (!feminino) {
            opcoesGestante.forEach(function (opcao) {
                opcao.checked = opcao.value === '0';
            });
        }
    }

    campoSexo.addEventListener('change', atualizarAreaGestante);

    atualizarAreaGestante();
</script>

</body>
</html>