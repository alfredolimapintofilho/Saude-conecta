<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['usuario']['id'])) {
    header('Location: ../pages/login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

$usuarioId = (int) $_SESSION['usuario']['id'];
$mensagem = '';
$tipoMensagem = '';
$erroBanco = '';

$sexo = '';
$peso = '';
$altura = '';
$tipoSanguineo = '';
$gestante = 0;

function h($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function calcularIMC($peso, $altura): ?float
{
    if (
        !is_numeric($peso) ||
        !is_numeric($altura) ||
        (float) $peso <= 0 ||
        (float) $altura <= 0
    ) {
        return null;
    }

    return (float) $peso / ((float) $altura ** 2);
}

function classificarIMC(float $imc): string
{
    if ($imc < 18.5) {
        return 'Abaixo do peso';
    }

    if ($imc < 25) {
        return 'Faixa considerada adequada';
    }

    if ($imc < 30) {
        return 'Sobrepeso';
    }

    if ($imc < 35) {
        return 'Obesidade grau I';
    }

    if ($imc < 40) {
        return 'Obesidade grau II';
    }

    return 'Obesidade grau III';
}

try {
    $pdo = Database::getConnection();

    // Carrega os dados de saúde cadastrados.
    $consulta = $pdo->prepare(
        'SELECT sexo, peso, altura, tipo_sanguineo, gestante
         FROM usuarios
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute(['id' => $usuarioId]);
    $dados = $consulta->fetch();

    if (!$dados) {
        throw new RuntimeException(
            'Não foi possível localizar os dados da conta.'
        );
    }

    $sexo = (string) ($dados['sexo'] ?? '');
    $peso = (string) ($dados['peso'] ?? '');
    $altura = (string) ($dados['altura'] ?? '');
    $tipoSanguineo = (string) ($dados['tipo_sanguineo'] ?? '');
    $gestante = (int) ($dados['gestante'] ?? 0);

    // Salva as alterações enviadas pelo formulário.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sexoNovo = trim((string) ($_POST['sexo'] ?? ''));
        $pesoNovo = str_replace(
            ',',
            '.',
            trim((string) ($_POST['peso'] ?? ''))
        );
        $alturaNovo = str_replace(
            ',',
            '.',
            trim((string) ($_POST['altura'] ?? ''))
        );
        $sangueNovo = trim(
            (string) ($_POST['tipo_sanguineo'] ?? '')
        );

        $gestanteNova = (
            $sexoNovo === 'Feminino' &&
            ($_POST['gestante'] ?? '') === '1'
        ) ? 1 : 0;

        $sexosPermitidos = [
            'Feminino',
            'Masculino',
            'Outro',
            'Prefiro não informar'
        ];

        $sanguesPermitidos = [
            '',
            'A+',
            'A-',
            'B+',
            'B-',
            'AB+',
            'AB-',
            'O+',
            'O-',
            'Não sei'
        ];

        if (!in_array($sexoNovo, $sexosPermitidos, true)) {
            throw new InvalidArgumentException(
                'Selecione uma opção válida para o sexo.'
            );
        }

        if (!in_array($sangueNovo, $sanguesPermitidos, true)) {
            throw new InvalidArgumentException(
                'Selecione um tipo sanguíneo válido.'
            );
        }

        $pesoBanco = null;
        if ($pesoNovo !== '') {
            if (!is_numeric($pesoNovo)) {
                throw new InvalidArgumentException(
                    'Informe um peso válido.'
                );
            }

            $pesoFloat = (float) $pesoNovo;

            if ($pesoFloat < 1 || $pesoFloat > 500) {
                throw new InvalidArgumentException(
                    'O peso deve estar entre 1 e 500 kg.'
                );
            }

            $pesoBanco = $pesoFloat;
        }

        $alturaBanco = null;
        if ($alturaNovo !== '') {
            if (!is_numeric($alturaNovo)) {
                throw new InvalidArgumentException(
                    'Informe uma altura válida.'
                );
            }

            $alturaFloat = (float) $alturaNovo;

            // Aceita altura em centímetros ou metros.
            if ($alturaFloat >= 100 && $alturaFloat <= 250) {
                $alturaFloat = $alturaFloat / 100;
            }

            if ($alturaFloat < 0.5 || $alturaFloat > 2.5) {
                throw new InvalidArgumentException(
                    'Informe a altura em metros ou centímetros.'
                );
            }

            $alturaBanco = $alturaFloat;
        }

        $atualizar = $pdo->prepare(
            'UPDATE usuarios
             SET sexo = :sexo,
                 peso = :peso,
                 altura = :altura,
                 tipo_sanguineo = :sangue,
                 gestante = :gestante
             WHERE id = :id'
        );

        $atualizar->execute([
            'sexo' => $sexoNovo,
            'peso' => $pesoBanco,
            'altura' => $alturaBanco,
            'sangue' => $sangueNovo !== '' ? $sangueNovo : null,
            'gestante' => $gestanteNova,
            'id' => $usuarioId
        ]);

        $sexo = $sexoNovo;
        $peso = $pesoBanco !== null ? (string) $pesoBanco : '';
        $altura = $alturaBanco !== null ? (string) $alturaBanco : '';
        $tipoSanguineo = $sangueNovo;
        $gestante = $gestanteNova;

        $mensagem = 'Informações de saúde salvas com sucesso!';
        $tipoMensagem = 'sucesso';
    }
} catch (InvalidArgumentException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
} catch (Throwable $e) {
    $erroBanco = $e->getMessage();

    if ($mensagem === '') {
        $mensagem =
            'Não foi possível carregar ou salvar as informações. ' .
            'Confira a estrutura da tabela usuarios e a conexão com o banco.';
        $tipoMensagem = 'erro';
    }
}

$imc = calcularIMC($peso, $altura);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações de Saúde Geral | Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --verde: #16856b;
            --verde-escuro: #116b56;
            --fundo: #f3f8f6;
            --texto: #263b35;
            --borda: #dce9e3;
            --rosa: #b94e78;
        }

        body {
            margin: 0;
            background: var(--fundo);
            color: var(--texto);
            font-family: Arial, Helvetica, sans-serif;
        }

        header {
            background: linear-gradient(135deg, #16856b, #31a68a);
            color: white;
            padding: 24px 18px;
        }

        .cabecalho {
            max-width: 1100px;
            margin: auto;
        }

        .cabecalho h1 {
            margin: 0 0 8px;
            font-size: clamp(25px, 4vw, 34px);
        }

        .cabecalho p {
            margin: 0;
            line-height: 1.6;
        }

        main {
            width: min(1100px, calc(100% - 30px));
            margin: 28px auto 50px;
        }

        .voltar {
            display: inline-block;
            color: var(--verde-escuro);
            font-weight: bold;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .voltar:hover {
            text-decoration: underline;
        }

        .cartao {
            background: white;
            border: 1px solid var(--borda);
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 5px 20px rgba(27, 79, 61, 0.05);
        }

        .cartao h2 {
            margin: 0 0 10px;
            font-size: 23px;
        }

        .descricao {
            color: #60736b;
            line-height: 1.65;
            margin-top: 0;
        }

        .grade-formulario {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .campo label,
        .rotulo {
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #cbdad2;
            border-radius: 10px;
            font: inherit;
            background: white;
            color: #263b35;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid #8ad2bb;
            border-color: var(--verde);
        }

        .campo-completo {
            grid-column: 1 / -1;
        }

        .pergunta-gravidez {
            margin-top: 20px;
            padding: 18px;
            border-radius: 13px;
            border: 1px solid #efc9d8;
            background: #fff7fa;
        }

        .opcoes {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 13px;
        }

        .opcao {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }

        .opcao input {
            width: auto;
            accent-color: var(--rosa);
        }

        .gestantes {
            border: 1px solid #edc2d3;
            border-radius: 18px;
            overflow: hidden;
            margin-top: 22px;
            background: white;
        }

        .gestantes-cabecalho {
            padding: 22px;
            color: #77304e;
            background: linear-gradient(135deg, #ffe8f0, #fff5f8);
        }

        .gestantes-cabecalho h2 {
            margin: 0 0 8px;
            font-size: 23px;
        }

        .gestantes-cabecalho p {
            margin: 0;
            line-height: 1.6;
        }

        .gestantes-conteudo {
            padding: 22px;
        }

        .aviso {
            border-radius: 10px;
            padding: 14px;
            line-height: 1.6;
            background: #fff8e7;
            color: #73531a;
            margin: 0 0 18px;
        }

        .botoes {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }

        .botao {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border: 0;
            border-radius: 10px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .botao-principal {
            background: var(--verde);
            color: white;
        }

        .botao-principal:hover {
            background: var(--verde-escuro);
        }

        .botao-secundario {
            background: #eaf4ef;
            color: #176b53;
        }

        .mensagem {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 10px;
            line-height: 1.5;
        }

        .mensagem.sucesso {
            background: #e4f7eb;
            color: #176b3a;
        }

        .mensagem.erro {
            background: #fff0f0;
            color: #9b2929;
        }

        .imc-valor {
            font-size: 28px;
            color: var(--verde-escuro);
            font-weight: bold;
            margin: 10px 0;
        }

        .grade-recursos {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .recurso {
            display: block;
            padding: 18px;
            color: var(--texto);
            background: #fbfdfc;
            border: 1px solid var(--borda);
            border-radius: 13px;
            text-decoration: none;
            transition: transform .15s, border-color .15s;
        }

        .recurso:hover {
            transform: translateY(-2px);
            border-color: var(--verde);
        }

        .recurso strong {
            display: block;
            color: var(--verde-escuro);
            margin-bottom: 7px;
            font-size: 16px;
        }

        .recurso span {
            color: #60736b;
            line-height: 1.5;
            font-size: 14px;
        }

        .nota {
            color: #64766e;
            font-size: 13px;
            line-height: 1.6;
        }

        [hidden] {
            display: none !important;
        }

        @media (max-width: 650px) {
            .grade-formulario,
            .grade-recursos {
                grid-template-columns: 1fr;
            }

            .cartao {
                padding: 18px;
            }

            .campo-completo {
                grid-column: auto;
            }

            .botoes .botao {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<header>
    <div class="cabecalho">
        <h1>Informações de Saúde Geral</h1>
        <p>Organize suas informações físicas e acompanhe seus dados de saúde.</p>
    </div>
</header>

<main>
    <a class="voltar" href="perfil_saude.php">
        &#8592; Voltar ao perfil de saúde
    </a>

    <?php if ($mensagem !== ''): ?>
        <div class="mensagem <?= h($tipoMensagem) ?>" role="status">
            <?= h($mensagem) ?>
        </div>
    <?php endif; ?>

    <section class="cartao">
        <h2>Dados físicos e informações de saúde</h2>
        <p class="descricao">
            Preencha os dados abaixo e clique em salvar para registrar
            as informações na sua conta.
        </p>

        <form method="POST" action="">
            <div class="grade-formulario">
                <div class="campo">
                    <label for="sexo">Sexo</label>
                    <select name="sexo" id="sexo" required>
                        <option value="">Selecione</option>
                        <option value="Feminino"
                            <?= $sexo === 'Feminino' ? 'selected' : '' ?>>
                            Feminino
                        </option>
                        <option value="Masculino"
                            <?= $sexo === 'Masculino' ? 'selected' : '' ?>>
                            Masculino
                        </option>
                        <option value="Outro"
                            <?= $sexo === 'Outro' ? 'selected' : '' ?>>
                            Outro
                        </option>
                        <option value="Prefiro não informar"
                            <?= $sexo === 'Prefiro não informar' ? 'selected' : '' ?>>
                            Prefiro não informar
                        </option>
                    </select>
                </div>

                <div class="campo">
                    <label for="tipo_sanguineo">Tipo sanguíneo</label>
                    <select name="tipo_sanguineo" id="tipo_sanguineo">
                        <option value="">Selecione</option>
                        <?php
                        $tipos = [
                            'A+', 'A-', 'B+', 'B-', 'AB+',
                            'AB-', 'O+', 'O-', 'Não sei'
                        ];
                        foreach ($tipos as $tipo):
                        ?>
                            <option value="<?= h($tipo) ?>"
                                <?= $tipoSanguineo === $tipo ? 'selected' : '' ?>>
                                <?= h($tipo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="peso">Peso (kg)</label>
                    <input
                        type="number"
                        name="peso"
                        id="peso"
                        min="1"
                        max="500"
                        step="0.1"
                        placeholder="Ex.: 65,5"
                        value="<?= h($peso) ?>"
                    >
                </div>

                <div class="campo">
                    <label for="altura">Altura (m ou cm)</label>
                    <input
                        type="number"
                        name="altura"
                        id="altura"
                        min="0.5"
                        max="250"
                        step="0.01"
                        placeholder="Ex.: 1,65 ou 165"
                        value="<?= h($altura) ?>"
                    >
                </div>
            </div>

            <div
                id="perguntaGravidez"
                class="pergunta-gravidez"
                <?= $sexo !== 'Feminino' ? 'hidden' : '' ?>
            >
                <div class="rotulo">Está grávida?</div>
                <p class="descricao">
                    Selecione “Sim” para abrir a área exclusiva para gestantes.
                </p>

                <div class="opcoes">
                    <label class="opcao">
                        <input
                            type="radio"
                            name="gestante"
                            value="1"
                            <?= $gestante === 1 ? 'checked' : '' ?>
                        >
                        Sim, estou grávida
                    </label>

                    <label class="opcao">
                        <input
                            type="radio"
                            name="gestante"
                            value="0"
                            <?= $gestante !== 1 ? 'checked' : '' ?>
                        >
                        Não estou grávida
                    </label>
                </div>
            </div>

            <div
                id="areaGestantes"
                class="gestantes"
                <?= ($sexo !== 'Feminino' || $gestante !== 1) ? 'hidden' : '' ?>
            >
                <div class="gestantes-cabecalho">
                    <h2>♡ Área Exclusiva para Gestantes</h2>
                    <p>
                        Um espaço de orientação para ajudar você a organizar
                        o acompanhamento da gestação.
                    </p>
                </div>

                <div class="gestantes-conteudo">
                    <div class="aviso">
                        <strong>Importante:</strong> mantenha o
                        acompanhamento pré-natal com uma equipe de saúde.
                        As informações desta área não substituem consultas,
                        exames ou orientações profissionais.
                    </div>

                    <h3>Cuidados durante a gestação</h3>
                    <ul>
                        <li>Realize as consultas de pré-natal recomendadas.</li>
                        <li>Mantenha os exames e as vacinas em dia conforme orientação profissional.</li>
                        <li>Informe à equipe de saúde sobre alergias e reações a medicamentos ou vacinas.</li>
                        <li>Procure atendimento de saúde se tiver sintomas preocupantes.</li>
                    </ul>

                    <div class="botoes">
                        <a
                            class="botao botao-secundario"
                            href="../pages/saude.php?tipo=vacinacao"
                        >
                            Consultar cartão de vacinação
                        </a>

                        <a
                            class="botao botao-secundario"
                            href="../pages/saude.php?tipo=atestados"
                        >
                            Consultar atestados
                        </a>
                    </div>

                    <p class="nota">
                        Os links dependem das funcionalidades já implementadas
                        na página saude.php. A área acima fornece orientações
                        e atalhos; não registra consultas, exames ou dados
                        obstétricos adicionais.
                    </p>
                </div>
            </div>

            <div class="botoes">
                <button
                    class="botao botao-principal"
                    type="submit"
                >
                    Salvar informações
                </button>

                <a
                    class="botao botao-secundario"
                    href="perfil_saude.php"
                >
                    Voltar ao perfil
                </a>
            </div>
        </form>
    </section>

    <section class="cartao">
        <h2>Índice de Massa Corporal (IMC)</h2>
        <p class="descricao">
            Cálculo estimado com base no peso e na altura informados.
        </p>

        <?php if ($imc !== null): ?>
            <div class="imc-valor">
                <?= number_format($imc, 1, ',', '.') ?>
            </div>

            <p>
                Classificação de referência:
                <strong><?= h(classificarIMC($imc)) ?></strong>
            </p>
        <?php else: ?>
            <p>
                Informe seu peso e sua altura acima para calcular o IMC.
            </p>
        <?php endif; ?>

        <p class="nota">
            O IMC é uma medida geral e não substitui avaliação profissional.
            Durante a gestação, sua interpretação exige cuidados específicos
            e não deve ser usada isoladamente para avaliar a saúde da gestante.
        </p>
    </section>

    <section class="cartao">
        <h2>Outras informações de saúde</h2>
        <p class="descricao">
            Acesse as áreas disponíveis para organizar seus registros médicos.
        </p>

        <div class="grade-recursos">
            <a
                class="recurso"
                href="../pages/saude.php?tipo=vacinacao"
            >
                <strong>Cartão de vacinação</strong>
                <span>Consultar a área de registros de vacinas.</span>
            </a>

            <a
                class="recurso"
                href="../pages/saude.php?tipo=alergias"
            >
                <strong>Alergias</strong>
                <span>Consultar a área de informações sobre alergias.</span>
            </a>

            <a
                class="recurso"
                href="../pages/saude.php?tipo=reacoes_vacinas"
            >
                <strong>Reações a vacinas</strong>
                <span>Consultar a área de registros de reações.</span>
            </a>

            <a
                class="recurso"
                href="../pages/saude.php?tipo=doencas"
            >
                <strong>Histórico de doenças</strong>
                <span>Consultar a área de histórico de doenças.</span>
            </a>

            <a
                class="recurso"
                href="../pages/saude.php?tipo=atestados"
            >
                <strong>Atestados</strong>
                <span>Consultar a área de atestados médicos.</span>
            </a>
        </div>
    </section>
</main>

<script>
(function () {
    const sexo = document.getElementById('sexo');
    const pergunta = document.getElementById('perguntaGravidez');
    const area = document.getElementById('areaGestantes');
    const opcoesGravidez = document.querySelectorAll(
        'input[name="gestante"]'
    );

    function atualizarAreaGestantes() {
        const feminino = sexo.value === 'Feminino';

        pergunta.hidden = !feminino;

        const selecionada = document.querySelector(
            'input[name="gestante"]:checked'
        );

        const estaGravida = feminino &&
            selecionada !== null &&
            selecionada.value === '1';

        area.hidden = !estaGravida;
    }

    sexo.addEventListener('change', atualizarAreaGestantes);

    opcoesGravidez.forEach(function (opcao) {
        opcao.addEventListener('change', atualizarAreaGestantes);
    });

    atualizarAreaGestantes();
})();
</script>
</body>
</html>