<?php
session_start();

/*
|--------------------------------------------------------------------------
| Saúde-Conecta - Perfil de Saúde
|--------------------------------------------------------------------------
| Os dados pessoais continuam no banco, mas não são exibidos nesta página.
| O usuário só pode consultar e atualizar os próprios dados de saúde.
*/

if (!isset($_SESSION['usuario'])) {
    header('Location: ../pages/login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

$pdo = Database::getConnection();

$sessaoUsuario = $_SESSION['usuario'];

$usuarioId = is_array($sessaoUsuario)
    ? (int) ($sessaoUsuario['id'] ?? 0)
    : (int) $sessaoUsuario;

if ($usuarioId <= 0) {
    session_unset();
    session_destroy();
    header('Location: ../pages/login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Conversão de datas digitadas como DD/MM/AAAA para AAAA-MM-DD
|--------------------------------------------------------------------------
*/

function converterDataParaBanco(string $data): ?string
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    $dataObjeto = DateTime::createFromFormat('!d/m/Y', $data);
    $erros = DateTime::getLastErrors();

    if (
        !$dataObjeto ||
        ($erros !== false &&
            ($erros['warning_count'] > 0 || $erros['error_count'] > 0)) ||
        $dataObjeto->format('d/m/Y') !== $data
    ) {
        throw new InvalidArgumentException(
            'Digite uma data válida no formato DD/MM/AAAA.'
        );
    }

    return $dataObjeto->format('Y-m-d');
}

function converterDataParaTela(?string $data): string
{
    if (!$data || $data === '0000-00-00') {
        return '';
    }

    $dataObjeto = DateTime::createFromFormat('!Y-m-d', $data);

    return $dataObjeto ? $dataObjeto->format('d/m/Y') : '';
}

$mensagem = '';
$tipoMensagem = 'sucesso';

/*
|--------------------------------------------------------------------------
| Carregar usuário
|--------------------------------------------------------------------------
| Os campos pessoais são consultados para manter os dados existentes,
| mas não são impressos no HTML.
|--------------------------------------------------------------------------
*/

try {
    $consulta = $pdo->prepare(
        'SELECT
            id,
            nome_completo,
            cpf,
            email,
            data_nascimento,
            telefone,
            cartao_sus,
            peso,
            altura,
            tipo_sanguineo,
            genero,
            sexo,
            gestante,
            semanas_gestacao,
            data_ultima_menstruacao,
            data_prevista_parto,
            pre_natal,
            gestacao_risco,
            observacoes_gestacao
         FROM usuarios
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute(['id' => $usuarioId]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        session_unset();
        session_destroy();
        header('Location: ../pages/login.php');
        exit;
    }
} catch (PDOException $erro) {
    error_log('Erro ao carregar perfil de saúde: ' . $erro->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar o perfil de saúde. Verifique a conexão com o banco de dados.');
}

/*
|--------------------------------------------------------------------------
| Salvar somente informações de saúde
|--------------------------------------------------------------------------
| Telefone e Cartão SUS não são enviados nem alterados por este formulário.
| Os dados pessoais existentes permanecem preservados no banco.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formulario = $_POST['formulario'] ?? '';

    if ($formulario === 'informacoes_saude') {
        try {
            $pesoTexto = trim($_POST['peso'] ?? '');
            $alturaTexto = trim($_POST['altura'] ?? '');
            $tipoSanguineo = trim($_POST['tipo_sanguineo'] ?? '');
            $genero = trim($_POST['genero'] ?? '');
            $sexo = trim($_POST['sexo'] ?? '');

            $peso = $pesoTexto === ''
                ? null
                : filter_var($pesoTexto, FILTER_VALIDATE_FLOAT);

            $altura = $alturaTexto === ''
                ? null
                : filter_var($alturaTexto, FILTER_VALIDATE_FLOAT);

            if ($pesoTexto !== '' && ($peso === false || $peso <= 0 || $peso > 500)) {
                throw new InvalidArgumentException('Informe um peso válido em quilogramas.');
            }

            if ($alturaTexto !== '' && ($altura === false || $altura <= 0 || $altura > 3)) {
                throw new InvalidArgumentException('Informe uma altura válida em metros. Exemplo: 1.75.');
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
                'O-'
            ];

            if (!in_array($tipoSanguineo, $tiposSanguineosPermitidos, true)) {
                throw new InvalidArgumentException('Selecione um tipo sanguíneo válido.');
            }

            $generosPermitidos = [
                '',
                'Feminino',
                'Masculino',
                'Não informado',
                'Outro'
            ];

            if (!in_array($genero, $generosPermitidos, true)) {
                throw new InvalidArgumentException('Selecione uma opção válida para gênero.');
            }

            $sexosPermitidos = [
                '',
                'Feminino',
                'Masculino',
                'Não informado'
            ];

            if (!in_array($sexo, $sexosPermitidos, true)) {
                throw new InvalidArgumentException('Selecione uma opção válida para sexo.');
            }

            $gestante = isset($_POST['gestante']) ? 1 : 0;

            $semanasTexto = trim($_POST['semanas_gestacao'] ?? '');
            $semanasGestacao = $semanasTexto === ''
                ? null
                : filter_var($semanasTexto, FILTER_VALIDATE_INT);

            if (
                $semanasTexto !== '' &&
                ($semanasGestacao === false || $semanasGestacao < 0 || $semanasGestacao > 45)
            ) {
                throw new InvalidArgumentException('Informe um número válido de semanas de gestação.');
            }

            $dataUltimaMenstruacao = converterDataParaBanco(
                trim($_POST['data_ultima_menstruacao'] ?? '')
            );

            $dataPrevistaParto = converterDataParaBanco(
                trim($_POST['data_prevista_parto'] ?? '')
            );

            $preNatal = trim($_POST['pre_natal'] ?? '');
            $observacoesGestacao = trim($_POST['observacoes_gestacao'] ?? '');
            $gestacaoRisco = isset($_POST['gestacao_risco']) ? 1 : 0;

            if (mb_strlen($preNatal) > 50) {
                throw new InvalidArgumentException('O campo pré-natal deve ter no máximo 50 caracteres.');
            }

            $sql = 'UPDATE usuarios SET
                        peso = :peso,
                        altura = :altura,
                        tipo_sanguineo = :tipo_sanguineo,
                        genero = :genero,
                        sexo = :sexo,
                        gestante = :gestante,
                        semanas_gestacao = :semanas_gestacao,
                        data_ultima_menstruacao = :data_ultima_menstruacao,
                        data_prevista_parto = :data_prevista_parto,
                        pre_natal = :pre_natal,
                        gestacao_risco = :gestacao_risco,
                        observacoes_gestacao = :observacoes_gestacao
                    WHERE id = :id';

            $atualizacao = $pdo->prepare($sql);

            $atualizacao->execute([
                'peso' => $peso === false ? null : $peso,
                'altura' => $altura === false ? null : $altura,
                'tipo_sanguineo' => $tipoSanguineo !== '' ? $tipoSanguineo : null,
                'genero' => $genero !== '' ? $genero : null,
                'sexo' => $sexo !== '' ? $sexo : null,
                'gestante' => $gestante,
                'semanas_gestacao' => $semanasGestacao === false ? null : $semanasGestacao,
                'data_ultima_menstruacao' => $dataUltimaMenstruacao,
                'data_prevista_parto' => $dataPrevistaParto,
                'pre_natal' => $preNatal !== '' ? $preNatal : null,
                'gestacao_risco' => $gestacaoRisco,
                'observacoes_gestacao' => $observacoesGestacao !== ''
                    ? $observacoesGestacao
                    : null,
                'id' => $usuarioId
            ]);

            $mensagem = 'Informações de saúde salvas com sucesso. Seus dados pessoais foram preservados.';
            $tipoMensagem = 'sucesso';

            // Atualiza os dados de saúde na memória para mostrar os valores atuais.
            $consulta->closeCursor();

            $consulta = $pdo->prepare(
                'SELECT
                    id,
                    nome_completo,
                    cpf,
                    email,
                    data_nascimento,
                    telefone,
                    cartao_sus,
                    peso,
                    altura,
                    tipo_sanguineo,
                    genero,
                    sexo,
                    gestante,
                    semanas_gestacao,
                    data_ultima_menstruacao,
                    data_prevista_parto,
                    pre_natal,
                    gestacao_risco,
                    observacoes_gestacao
                 FROM usuarios
                 WHERE id = :id
                 LIMIT 1'
            );

            $consulta->execute(['id' => $usuarioId]);
            $usuario = $consulta->fetch(PDO::FETCH_ASSOC);
        } catch (InvalidArgumentException $erro) {
            $mensagem = $erro->getMessage();
            $tipoMensagem = 'erro';
        } catch (PDOException $erro) {
            error_log('Erro ao salvar informações de saúde: ' . $erro->getMessage());
            $mensagem = 'Não foi possível salvar as informações de saúde. Tente novamente.';
            $tipoMensagem = 'erro';
        }
    }
}

$pesoAtual = is_numeric($usuario['peso'] ?? null)
    ? (float) $usuario['peso']
    : 0;

$alturaAtual = is_numeric($usuario['altura'] ?? null)
    ? (float) $usuario['altura']
    : 0;

$imc = ($pesoAtual > 0 && $alturaAtual > 0)
    ? $pesoAtual / ($alturaAtual * $alturaAtual)
    : null;

$classificacaoImc = 'Informe seu peso e altura';

if ($imc !== null) {
    if ($imc < 18.5) {
        $classificacaoImc = 'Abaixo do peso';
    } elseif ($imc < 25) {
        $classificacaoImc = 'Faixa considerada adequada';
    } elseif ($imc < 30) {
        $classificacaoImc = 'Sobrepeso';
    } else {
        $classificacaoImc = 'Obesidade';
    }
}

$hidratacao = $pesoAtual > 0
    ? round($pesoAtual * 35)
    : null;

$mostrarGestacao = ($usuario['sexo'] ?? '') === 'Feminino';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Saúde | Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f2f6f8;
            color: #20313b;
        }

        header {
            background: #087f8c;
            color: white;
            padding: 24px 18px;
        }

        .cabecalho {
            max-width: 1050px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        header h1 {
            margin: 0;
            font-size: 27px;
        }

        header p {
            margin: 7px 0 0;
        }

        .sair {
            color: white;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.8);
            padding: 10px 16px;
            border-radius: 8px;
        }

        main {
            max-width: 1050px;
            margin: 28px auto;
            padding: 0 16px 35px;
        }

        .introducao {
            margin-bottom: 22px;
        }

        .introducao h2 {
            margin-bottom: 8px;
        }

        .introducao p {
            color: #52636c;
            line-height: 1.6;
        }

        .secao {
            background: white;
            margin-bottom: 14px;
            border-radius: 12px;
            border: 1px solid #dce5e9;
            box-shadow: 0 3px 10px rgba(22, 49, 58, .05);
            overflow: hidden;
        }

        .secao-titulo {
            width: 100%;
            border: none;
            background: white;
            color: #075e68;
            text-align: left;
            font-size: 17px;
            font-weight: bold;
            padding: 19px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .secao-titulo:hover {
            background: #f1fafb;
        }

        .seta {
            font-size: 20px;
            transition: transform .2s;
        }

        .secao-titulo[aria-expanded="true"] .seta {
            transform: rotate(180deg);
        }

        .conteudo {
            display: none;
            border-top: 1px solid #e5edef;
            padding: 20px;
            line-height: 1.6;
        }

        .conteudo.aberto {
            display: block;
        }

        .aviso {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .aviso.sucesso {
            background: #e2f6e9;
            color: #176438;
        }

        .aviso.erro {
            background: #fff0ed;
            color: #9c2e20;
        }

        .nota {
            color: #586a72;
            font-size: 14px;
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .campo label {
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #bdcdd3;
            border-radius: 7px;
            padding: 11px;
            font: inherit;
            background: white;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .linha-completa {
            grid-column: 1 / -1;
        }

        .check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 13px 0;
        }

        .check input {
            width: auto;
        }

        .botao {
            display: inline-block;
            border: none;
            border-radius: 8px;
            background: #087f8c;
            color: white;
            padding: 12px 18px;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
            margin-top: 16px;
        }

        .botao:hover {
            background: #056671;
        }

        .links-saude {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .link-card {
            display: block;
            color: #075e68;
            text-decoration: none;
            border: 1px solid #d5e4e8;
            padding: 15px;
            border-radius: 9px;
            background: #f8fcfd;
            font-weight: bold;
        }

        .link-card:hover {
            background: #eaf7f9;
        }

        .numero {
            font-size: 27px;
            font-weight: bold;
            color: #087f8c;
        }

        .rodape {
            text-align: center;
            padding: 20px;
            color: #64757d;
            font-size: 13px;
        }

        @media (max-width: 650px) {
            .grade,
            .links-saude {
                grid-template-columns: 1fr;
            }

            header h1 {
                font-size: 23px;
            }

            .conteudo {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="cabecalho">
        <div>
            <h1>Saúde-Conecta</h1>
            <p>Seu perfil de saúde</p>
        </div>

        <a class="sair" href="logout.php">Sair da conta</a>
    </div>
</header>

<main>
    <div class="introducao">
        <h2>Meu perfil de saúde</h2>
        <p>
            Consulte e organize suas informações de saúde, seus registros
            médicos e os serviços disponíveis no Saúde-Conecta.
        </p>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="aviso <?= e($tipoMensagem) ?>" role="status">
            <?= e($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- DADOS PESSOAIS -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="dados-pessoais">
            <span>Dados pessoais</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="dados-pessoais">
            <p>
                Seus dados pessoais, como nome, CPF, e-mail, data de
                nascimento, telefone e Cartão SUS, permanecem armazenados
                no banco de dados da sua conta.
            </p>

            <p class="nota">
                Por privacidade, esses dados não são exibidos nesta tela.
                Abrir esta seção não revela os valores cadastrados.
            </p>
        </div>
    </section>

    <!-- INFORMAÇÕES DE SAÚDE -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="informacoes-saude">
            <span>Informações de saúde</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="informacoes-saude">
            <p class="nota">
                Preencha ou atualize suas informações de saúde. Telefone
                e Cartão SUS não fazem parte deste formulário.
            </p>

            <form method="POST" action="">
                <input type="hidden" name="formulario" value="informacoes_saude">

                <div class="grade">
                    <div class="campo">
                        <label for="peso">Peso (kg)</label>
                        <input
                            type="number"
                            id="peso"
                            name="peso"
                            min="1"
                            max="500"
                            step="0.1"
                            value="<?= e($usuario['peso'] ?? '') ?>"
                            placeholder="Ex.: 70.5">
                    </div>

                    <div class="campo">
                        <label for="altura">Altura (metros)</label>
                        <input
                            type="number"
                            id="altura"
                            name="altura"
                            min="0.3"
                            max="3"
                            step="0.01"
                            value="<?= e($usuario['altura'] ?? '') ?>"
                            placeholder="Ex.: 1.75">
                    </div>

                    <div class="campo">
                        <label for="tipo_sanguineo">Tipo sanguíneo</label>
                        <select id="tipo_sanguineo" name="tipo_sanguineo">
                            <option value="">Selecione</option>
                            <?php
                            $tipos = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($tipos as $tipo):
                            ?>
                                <option value="<?= e($tipo) ?>"
                                    <?= ($usuario['tipo_sanguineo'] ?? '') === $tipo ? 'selected' : '' ?>>
                                    <?= e($tipo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="genero">Gênero</label>
                        <select id="genero" name="genero">
                            <?php
                            $generos = [
                                '' => 'Selecione',
                                'Feminino' => 'Feminino',
                                'Masculino' => 'Masculino',
                                'Não informado' => 'Prefiro não informar',
                                'Outro' => 'Outro'
                            ];
                            foreach ($generos as $valor => $rotulo):
                            ?>
                                <option value="<?= e($valor) ?>"
                                    <?= ($usuario['genero'] ?? '') === $valor ? 'selected' : '' ?>>
                                    <?= e($rotulo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="sexo">Sexo</label>
                        <select id="sexo" name="sexo">
                            <?php
                            $sexos = [
                                '' => 'Selecione',
                                'Feminino' => 'Feminino',
                                'Masculino' => 'Masculino',
                                'Não informado' => 'Prefiro não informar'
                            ];
                            foreach ($sexos as $valor => $rotulo):
                            ?>
                                <option value="<?= e($valor) ?>"
                                    <?= ($usuario['sexo'] ?? '') === $valor ? 'selected' : '' ?>>
                                    <?= e($rotulo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="campos-gestacao"
                     style="<?= $mostrarGestacao ? '' : 'display:none;' ?>; margin-top:22px;">
                    <h3>Informações sobre gestação</h3>

                    <label class="check">
                        <input
                            type="checkbox"
                            name="gestante"
                            value="1"
                            <?= !empty($usuario['gestante']) ? 'checked' : '' ?>>
                        Estou gestante
                    </label>

                    <div class="grade">
                        <div class="campo">
                            <label for="semanas_gestacao">Semanas de gestação</label>
                            <input
                                type="number"
                                id="semanas_gestacao"
                                name="semanas_gestacao"
                                min="0"
                                max="45"
                                value="<?= e($usuario['semanas_gestacao'] ?? '') ?>">
                        </div>

                        <div class="campo">
                            <label for="pre_natal">Situação do pré-natal</label>
                            <select id="pre_natal" name="pre_natal">
                                <option value="">Selecione</option>
                                <?php foreach (['Não informado', 'Em andamento', 'Concluído', 'Não iniciado'] as $opcao): ?>
                                    <option value="<?= e($opcao) ?>"
                                        <?= ($usuario['pre_natal'] ?? '') === $opcao ? 'selected' : '' ?>>
                                        <?= e($opcao) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo">
                            <label for="data_ultima_menstruacao">Data da última menstruação</label>
                            <input
                                type="text"
                                id="data_ultima_menstruacao"
                                name="data_ultima_menstruacao"
                                inputmode="numeric"
                                maxlength="10"
                                placeholder="DD/MM/AAAA"
                                value="<?= e(converterDataParaTela($usuario['data_ultima_menstruacao'] ?? null)) ?>">
                        </div>

                        <div class="campo">
                            <label for="data_prevista_parto">Data prevista do parto</label>
                            <input
                                type="text"
                                id="data_prevista_parto"
                                name="data_prevista_parto"
                                inputmode="numeric"
                                maxlength="10"
                                placeholder="DD/MM/AAAA"
                                value="<?= e(converterDataParaTela($usuario['data_prevista_parto'] ?? null)) ?>">
                        </div>

                        <div class="campo linha-completa">
                            <label class="check">
                                <input
                                    type="checkbox"
                                    name="gestacao_risco"
                                    value="1"
                                    <?= !empty($usuario['gestacao_risco']) ? 'checked' : '' ?>>
                                Gestação de risco informada
                            </label>
                        </div>

                        <div class="campo linha-completa">
                            <label for="observacoes_gestacao">Observações sobre a gestação</label>
                            <textarea
                                id="observacoes_gestacao"
                                name="observacoes_gestacao"
                                maxlength="5000"
                                placeholder="Observações relevantes"><?= e($usuario['observacoes_gestacao'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <button class="botao" type="submit">
                    Salvar informações de saúde
                </button>
            </form>
        </div>
    </section>

    <!-- IMC -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="imc">
            <span>IMC — Índice de Massa Corporal</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="imc">
            <?php if ($imc !== null): ?>
                <div class="numero"><?= number_format($imc, 1, ',', '.') ?></div>
                <p><?= e($classificacaoImc) ?></p>
            <?php else: ?>
                <p>
                    Para calcular o IMC, abra “Informações de saúde”,
                    informe seu peso e altura e salve os dados.
                </p>
            <?php endif; ?>

            <p class="nota">
                O IMC é uma estimativa geral e não substitui uma avaliação
                individual feita por um profissional de saúde.
            </p>
        </div>
    </section>

    <!-- HIDRATAÇÃO -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="hidratacao">
            <span>Hidratação estimada</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="hidratacao">
            <?php if ($hidratacao !== null): ?>
                <div class="numero">
                    <?= number_format($hidratacao, 0, ',', '.') ?> ml/dia
                </div>
                <p>
                    Estimativa aproximada baseada em 35 ml de água por
                    quilograma de peso corporal.
                </p>
            <?php else: ?>
                <p>
                    Informe seu peso na seção “Informações de saúde”
                    para obter uma estimativa.
                </p>
            <?php endif; ?>

            <p class="nota">
                A necessidade de água varia com clima, atividade física,
                alimentação e condições de saúde. Pessoas com restrição
                de líquidos devem seguir a orientação de seu profissional
                de saúde.
            </p>
        </div>
    </section>

    <!-- MINHA SAÚDE -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="minha-saude">
            <span>Minha saúde</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="minha-saude">
            <p>
                Escolha o registro de saúde que deseja consultar ou atualizar.
            </p>

            <div class="links-saude">
                <a class="link-card" href="../pages/saude.php?tipo=vacina">
                    Cartão de vacinação
                </a>

                <a class="link-card" href="../pages/saude.php?tipo=alergia">
                    Alergias
                </a>

                <a class="link-card" href="../pages/saude.php?tipo=alergia_vacina">
                    Alergias a vacinas
                </a>

                <a class="link-card" href="../pages/saude.php?tipo=doenca">
                    Histórico de doenças
                </a>

                <a class="link-card" href="../pages/saude.php?tipo=atestado">
                    Atestados médicos
                </a>
            </div>
        </div>
    </section>

    <!-- PETS -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="meus-pets">
            <span>Meus Pets</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="meus-pets">
            <p>
                Acesse a área para visualizar e gerenciar os animais
                cadastrados na sua conta.
            </p>

            <a class="botao" href="../pages/pets.php">
                Acessar Meus Pets
            </a>
        </div>
    </section>

    <!-- ADOÇÃO -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="adocao-pets">
            <span>Adoção de Pets</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="adocao-pets">
            <p>
                Consulte a área de adoção para conhecer animais disponíveis
                e informações sobre como adotar.
            </p>

            <a class="botao" href="../pages/adocao_pets.php">
                Acessar adoção de pets
            </a>
        </div>
    </section>

    <!-- MAPA -->
    <section class="secao">
        <button class="secao-titulo"
                type="button"
                aria-expanded="false"
                aria-controls="mapa-saude">
            <span>Mapa de saúde</span>
            <span class="seta" aria-hidden="true">⌄</span>
        </button>

        <div class="conteudo" id="mapa-saude">
            <p>
                Encontre unidades e serviços de saúde disponíveis no mapa
                do Saúde-Conecta.
            </p>

            <a class="botao" href="mapa.php">
                Abrir mapa de saúde
            </a>
        </div>
    </section>
</main>

<footer class="rodape">
    Saúde-Conecta — cuide da sua saúde com informação e organização.
</footer>

<script>
    // Abre e fecha cada seção ao clicar no título.
    document.querySelectorAll('.secao-titulo').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const idConteudo = botao.getAttribute('aria-controls');
            const conteudo = document.getElementById(idConteudo);
            const estaAberto = botao.getAttribute('aria-expanded') === 'true';

            botao.setAttribute('aria-expanded', String(!estaAberto));
            conteudo.classList.toggle('aberto', !estaAberto);
        });
    });

    // Exibe os campos de gestação quando o sexo selecionado for feminino.
    const campoSexo = document.getElementById('sexo');
    const camposGestacao = document.getElementById('campos-gestacao');

    function atualizarCamposGestacao() {
        if (campoSexo.value === 'Feminino') {
            camposGestacao.style.display = '';
        } else {
            camposGestacao.style.display = 'none';
        }
    }

    campoSexo.addEventListener('change', atualizarCamposGestacao);

    // Mantém as datas digitadas manualmente no formato DD/MM/AAAA.
    document.querySelectorAll(
        '#data_ultima_menstruacao, #data_prevista_parto'
    ).forEach(function (campo) {
        campo.addEventListener('input', function () {
            let numeros = campo.value.replace(/\D/g, '').slice(0, 8);

            if (numeros.length > 4) {
                numeros = numeros.slice(0, 2) + '/' +
                    numeros.slice(2, 4) + '/' + numeros.slice(4);
            } else if (numeros.length > 2) {
                numeros = numeros.slice(0, 2) + '/' + numeros.slice(2);
            }

            campo.value = numeros;
        });
    });
</script>

</body>
</html>