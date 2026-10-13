<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/services/AdotecaClient.php';

$pdo = Database::getConnection();

$busca = trim($_GET['busca'] ?? '');
$especieFiltro = trim($_GET['especie'] ?? '');

$disponiveisLocais = [];
$adotadosLocais = [];
$disponiveisAdoteca = [];
$adotadosAdoteca = [];
$statusIncertoAdoteca = [];

$erroBanco = '';
$erroAdoteca = '';

function h($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function nomeEspecie(string $especie): string
{
    $especies = [
        'dog' => 'Cachorro',
        'dogs' => 'Cachorro',
        'cat' => 'Gato',
        'cats' => 'Gato',
        'bird' => 'Ave',
        'rabbit' => 'Coelho',
        'hamster' => 'Hamster',
        'guinea_pig' => 'Porquinho-da-índia',
        'horse' => 'Cavalo',
        'cow' => 'Bovino',
        'Cachorro' => 'Cachorro',
        'Gato' => 'Gato',
    ];

    $chave = strtolower(trim($especie));

    return $especies[$chave]
        ?? ucfirst(str_replace(['_', '-'], ' ', $especie));
}

/*
|--------------------------------------------------------------------------
| Identificação do status do anúncio
|--------------------------------------------------------------------------
*/

function classificarStatus($status): string
{
    if (!is_string($status) && !is_numeric($status)) {
        return 'incerto';
    }

    $status = strtolower(trim((string) $status));

    $status = strtr($status, [
        'á' => 'a',
        'à' => 'a',
        'ã' => 'a',
        'â' => 'a',
        'é' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ú' => 'u',
        'ç' => 'c',
    ]);

    $adotados = [
        'adotado',
        'adotada',
        'ja adotado',
        'ja adotada',
        'adopted',
        'adopted_pet',
        'adopted_successfully',
        'adocao concluida',
        'adocao finalizada',
        'adoption_completed',
        'adopted_out',
    ];

    $disponiveis = [
        'disponivel',
        'disponivel para adocao',
        'available',
        'active',
        'published',
        'publicado',
        'publicada',
        'para adocao',
        'available_for_adoption',
        'looking_for_home',
    ];

    if (in_array($status, $adotados, true)) {
        return 'adotado';
    }

    if (in_array($status, $disponiveis, true)) {
        return 'disponivel';
    }

    return 'incerto';
}

/*
|--------------------------------------------------------------------------
| Extrair os animais da resposta da Adoteca
|--------------------------------------------------------------------------
*/

function extrairAnimaisAdoteca($resposta): array
{
    if (is_string($resposta)) {
        $resposta = json_decode($resposta, true);
    }

    if (!is_array($resposta)) {
        return [];
    }

    if (
        isset($resposta['pets'])
        && is_array($resposta['pets'])
    ) {
        return $resposta['pets'];
    }

    if (
        isset($resposta['content'])
        && is_array($resposta['content'])
    ) {
        foreach ($resposta['content'] as $item) {
            if (
                !isset($item['text'])
                || !is_string($item['text'])
            ) {
                continue;
            }

            $dados = json_decode($item['text'], true);

            if (!is_array($dados)) {
                continue;
            }

            if (
                isset($dados['pets'])
                && is_array($dados['pets'])
            ) {
                return $dados['pets'];
            }

            if (array_is_list($dados)) {
                return $dados;
            }
        }
    }

    return array_is_list($resposta) ? $resposta : [];
}

/*
|--------------------------------------------------------------------------
| Caminho das fotos dos anúncios locais
|--------------------------------------------------------------------------
*/

function caminhoFotoLocal(?string $foto): string
{
    if (!$foto) {
        return '';
    }

    if (filter_var($foto, FILTER_VALIDATE_URL)) {
        return $foto;
    }

    if (str_starts_with($foto, '/')) {
        return $foto;
    }

    if (str_contains($foto, 'uploads/')) {
        return '/Saude-conecta/frontend/src/pages/'
            . ltrim($foto, '/');
    }

    return '/Saude-conecta/frontend/src/pages/uploads/adocao/'
        . rawurlencode(basename($foto));
}

/*
|--------------------------------------------------------------------------
| 1. Buscar anúncios locais disponíveis e já adotados
|--------------------------------------------------------------------------
*/

try {
    $sql = "
        SELECT
            id,
            usuario_id,
            nome,
            especie,
            raca,
            sexo,
            idade,
            porte,
            cidade,
            uf,
            descricao,
            vacinado,
            castrado,
            contato,
            foto,
            status,
            criado_em
        FROM pets_adocao
        WHERE status IN ('disponivel', 'adotado')
    ";

    $parametros = [];

    if ($busca !== '') {
        $sql .= "
            AND (
                nome LIKE :nome
                OR descricao LIKE :descricao
                OR raca LIKE :raca
                OR cidade LIKE :cidade
            )
        ";

        $termo = '%' . $busca . '%';

        $parametros[':nome'] = $termo;
        $parametros[':descricao'] = $termo;
        $parametros[':raca'] = $termo;
        $parametros[':cidade'] = $termo;
    }

    if ($especieFiltro !== '') {
        $sql .= " AND especie = :especie";
        $parametros[':especie'] = $especieFiltro;
    }

    $sql .= " ORDER BY criado_em DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    $animaisLocais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($animaisLocais as $animal) {
        if ($animal['status'] === 'adotado') {
            $adotadosLocais[] = $animal;
        } elseif ($animal['status'] === 'disponivel') {
            $disponiveisLocais[] = $animal;
        }
    }
} catch (Throwable $e) {
    $erroBanco = 'Não foi possível carregar os anúncios locais. '
        . 'Confira a tabela pets_adocao e suas colunas.';
}

/*
|--------------------------------------------------------------------------
| 2. Consultar a Adoteca e separar por status
|--------------------------------------------------------------------------
*/

try {
    $cliente = new AdotecaClient();

    $resposta = $cliente->buscarPets(
        'Patos',
        'PB',
        $especieFiltro,
        20
    );

    $animaisExternos = extrairAnimaisAdoteca($resposta);

    foreach ($animaisExternos as $animal) {
        if (!is_array($animal)) {
            continue;
        }

        if ($busca !== '') {
            $texto = implode(' ', [
                $animal['name'] ?? '',
                $animal['species'] ?? '',
                $animal['breed'] ?? '',
                $animal['city'] ?? '',
                $animal['description'] ?? '',
            ]);

            if (stripos($texto, $busca) === false) {
                continue;
            }
        }

        $statusAnimal = classificarStatus(
            $animal['status'] ?? null
        );

        if ($statusAnimal === 'adotado') {
            $adotadosAdoteca[] = $animal;
        } elseif ($statusAnimal === 'disponivel') {
            $disponiveisAdoteca[] = $animal;
        } else {
            $statusIncertoAdoteca[] = $animal;
        }
    }
} catch (Throwable $e) {
    $erroAdoteca = 'Não foi possível consultar a Adoteca agora. '
        . 'Os anúncios locais continuam disponíveis.';
}

/*
|--------------------------------------------------------------------------
| 3. Cartão reutilizável para anúncios locais
|--------------------------------------------------------------------------
*/

function exibirAnimalLocal(array $animal, bool $jaAdotado = false): void
{
    $foto = caminhoFotoLocal($animal['foto'] ?? null);
    ?>
    <article class="animal">
        <?php if ($foto !== ''): ?>
            <img
                src="<?= h($foto) ?>"
                alt="Foto de <?= h($animal['nome']) ?>"
                loading="lazy"
            >
        <?php else: ?>
            <div class="sem-foto">🐾</div>
        <?php endif; ?>

        <div class="conteudo">
            <h3><?= h($animal['nome']) ?></h3>

            <div class="detalhes">
                <strong>Espécie:</strong>
                <?= h(nomeEspecie($animal['especie'] ?? '')) ?><br>

                <?php if (!empty($animal['raca'])): ?>
                    <strong>Raça:</strong> <?= h($animal['raca']) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['sexo'])): ?>
                    <strong>Sexo:</strong> <?= h($animal['sexo']) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['idade'])): ?>
                    <strong>Idade:</strong> <?= h($animal['idade']) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['porte'])): ?>
                    <strong>Porte:</strong> <?= h($animal['porte']) ?><br>
                <?php endif; ?>

                <strong>Local:</strong>
                <?= h($animal['cidade']) ?> - <?= h($animal['uf']) ?>
            </div>

            <?php if ($jaAdotado): ?>
                <span class="etiqueta adotado">Já adotado</span>
            <?php else: ?>
                <div class="etiquetas">
                    <?php if (!empty($animal['vacinado'])): ?>
                        <span class="etiqueta">Vacinado</span>
                    <?php endif; ?>

                    <?php if (!empty($animal['castrado'])): ?>
                        <span class="etiqueta">Castrado</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($animal['descricao'])): ?>
                <p><?= nl2br(h($animal['descricao'])) ?></p>
            <?php endif; ?>

            <?php if (!$jaAdotado && !empty($animal['contato'])): ?>
                <p>
                    <strong>Contato:</strong>
                    <?= h($animal['contato']) ?>
                </p>
            <?php endif; ?>

            <div class="fonte">Saúde-Conecta</div>
        </div>
    </article>
    <?php
}

/*
|--------------------------------------------------------------------------
| 4. Cartão reutilizável para anúncios da Adoteca
|--------------------------------------------------------------------------
*/

function exibirAnimalAdoteca(
    array $animal,
    bool $jaAdotado = false
): void {
    $nome = $animal['name'] ?? 'Animal para adoção';
    $especie = $animal['species'] ?? '';
    $foto = $animal['photo_url'] ?? '';
    $urlAdocao = $animal['adoption_url'] ?? '';
    $urlContato = $animal['contact_url'] ?? '';

    $fotoValida = filter_var($foto, FILTER_VALIDATE_URL);
    $urlValida = filter_var($urlAdocao, FILTER_VALIDATE_URL);
    $contatoValido = filter_var($urlContato, FILTER_VALIDATE_URL);
    ?>
    <article class="animal">
        <?php if ($fotoValida): ?>
            <img
                src="<?= h($foto) ?>"
                alt="Foto de <?= h($nome) ?>"
                loading="lazy"
            >
        <?php else: ?>
            <div class="sem-foto">🐾</div>
        <?php endif; ?>

        <div class="conteudo">
            <h3><?= h($nome) ?></h3>

            <div class="detalhes">
                <?php if ($especie !== ''): ?>
                    <strong>Espécie:</strong>
                    <?= h(nomeEspecie($especie)) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['breed'])): ?>
                    <strong>Raça:</strong>
                    <?= h($animal['breed']) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['sex'])): ?>
                    <strong>Sexo:</strong>
                    <?= h($animal['sex']) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['age'])): ?>
                    <strong>Idade:</strong>
                    <?= h($animal['age']) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['size'])): ?>
                    <strong>Porte:</strong>
                    <?= h($animal['size']) ?><br>
                <?php endif; ?>

                <?php if (!empty($animal['city'])): ?>
                    <strong>Local:</strong>
                    <?= h($animal['city']) ?><br>
                <?php endif; ?>
            </div>

            <?php if ($jaAdotado): ?>
                <span class="etiqueta adotado">Já adotado</span>
            <?php else: ?>
                <div class="etiquetas">
                    <?php if (!empty($animal['vaccinated'])): ?>
                        <span class="etiqueta">Vacinado</span>
                    <?php endif; ?>

                    <?php if (!empty($animal['neutered'])): ?>
                        <span class="etiqueta">Castrado</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="fonte">Fonte: Adoteca</div>

            <?php if ($urlValida): ?>
                <a
                    class="botao principal"
                    href="<?= h($urlAdocao) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?= $jaAdotado ? 'Ver registro original' : 'Ver anúncio original' ?>
                </a>
            <?php endif; ?>

            <?php if (!$jaAdotado && $contatoValido): ?>
                <a
                    class="botao secundario"
                    href="<?= h($urlContato) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Ver contato
                </a>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Adoção de Animais | Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f6;
            color: #263238;
        }

        header {
            background: #167d68;
            color: white;
            padding: 24px 20px;
        }

        .cabecalho,
        .container {
            max-width: 1200px;
            margin: auto;
        }

        .cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
            line-height: 1.5;
        }

        .container {
            padding: 25px 18px 45px;
        }

        .acoes,
        .etiquetas {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        .acoes {
            margin-bottom: 22px;
        }

        .botao {
            display: inline-block;
            padding: 11px 15px;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .botao:hover {
            transform: translateY(-1px);
        }

        .principal {
            background: #167d68;
            color: white;
        }

        .principal:hover {
            background: #116653;
        }

        .secundario {
            background: #e4efec;
            color: #165d50;
        }

        .secundario:hover {
            background: #d2e5df;
        }

        .filtros {
            background: white;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .filtros form {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .campo label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .campo input,
        .campo select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5d1;
            border-radius: 8px;
            background: white;
        }

        .campo input:focus,
        .campo select:focus {
            outline: 2px solid #9dd5c5;
            border-color: #167d68;
        }

        .secao {
            margin-top: 30px;
        }

        .secao h2 {
            margin-bottom: 6px;
            font-size: 23px;
        }

        .secao > p {
            color: #63736d;
            line-height: 1.5;
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .animal {
            background: white;
            border-radius: 13px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-width: 0;
            box-shadow: 0 3px 12px #0000000d;
        }

        .animal img,
        .sem-foto {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #e8efec;
        }

        .sem-foto {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 52px;
        }

        .conteudo {
            padding: 17px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
            overflow-wrap: anywhere;
        }

        .conteudo h3 {
            margin: 0;
            font-size: 20px;
        }

        .detalhes {
            font-size: 14px;
            line-height: 1.7;
            color: #52645d;
        }

        .conteudo p {
            font-size: 14px;
            line-height: 1.6;
        }

        .etiqueta {
            display: inline-block;
            background: #e7f4ef;
            color: #176650;
            border-radius: 20px;
            padding: 6px 10px;
            font-size: 12px;
            align-self: flex-start;
        }

        .etiqueta.adotado {
            background: #e8e8e8;
            color: #444;
        }

        .fonte {
            color: #718078;
            font-size: 12px;
            margin-top: auto;
        }

        .aviso,
        .vazio {
            padding: 15px;
            border-radius: 9px;
            margin: 12px 0;
            line-height: 1.6;
        }

        .aviso {
            background: #fff7e5;
            border: 1px solid #f1d9a3;
            color: #795b16;
        }

        .vazio {
            background: white;
            color: #64746d;
        }

        footer {
            padding: 20px;
            text-align: center;
            color: #708079;
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .grade {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filtros form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .grade,
            .filtros form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="cabecalho">
        <div>
            <h1>🐾 Adoção de Animais</h1>
            <p>Encontre um companheiro e ajude um animal a encontrar um lar.</p>
        </div>

        <!-- CORREÇÃO: caminho para perfil_saude.php na pasta components -->
        <a
            class="botao secundario"
            href="../components/perfil_saude.php"
        >
            ← Voltar ao perfil
        </a>
    </div>
</header>

<main class="container">

    <div class="acoes">
        <a class="botao principal" href="cadastrar_pet_adocao.php">
            + Cadastrar animal para adoção
        </a>

        <a class="botao secundario" href="pets.php">
            Meus animais
        </a>
    </div>

    <?php if ($erroBanco !== ''): ?>
        <div class="aviso"><?= h($erroBanco) ?></div>
    <?php endif; ?>

    <?php if ($erroAdoteca !== ''): ?>
        <div class="aviso"><?= h($erroAdoteca) ?></div>
    <?php endif; ?>

    <section class="filtros">
        <form method="GET" action="adocao_pets.php">
            <div class="campo">
                <label for="busca">Buscar animal</label>
                <input
                    id="busca"
                    type="text"
                    name="busca"
                    value="<?= h($busca) ?>"
                    placeholder="Nome, raça ou cidade"
                >
            </div>

            <div class="campo">
                <label for="especie">Espécie</label>
                <select id="especie" name="especie">
                    <option value="">Todas as espécies</option>
                    <?php
                    $opcoes = [
                        'Cachorro',
                        'Gato',
                        'Ave',
                        'Coelho',
                        'Hamster',
                        'Porquinho-da-índia',
                        'Cavalo',
                        'Outro',
                    ];
                    ?>

                    <?php foreach ($opcoes as $opcao): ?>
                        <option
                            value="<?= h($opcao) ?>"
                            <?= $especieFiltro === $opcao ? 'selected' : '' ?>
                        >
                            <?= h($opcao) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="botao principal" type="submit">
                Buscar
            </button>

            <a class="botao secundario" href="adocao_pets.php">
                Limpar
            </a>
        </form>
    </section>

    <section class="secao">
        <h2>Disponíveis para adoção</h2>
        <p>Animais cujo anúncio indica que ainda estão disponíveis.</p>

        <?php if ($disponiveisLocais || $disponiveisAdoteca): ?>
            <div class="grade">
                <?php foreach ($disponiveisLocais as $animal): ?>
                    <?php exibirAnimalLocal($animal); ?>
                <?php endforeach; ?>

                <?php foreach ($disponiveisAdoteca as $animal): ?>
                    <?php exibirAnimalAdoteca($animal); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="vazio">
                Nenhum animal com disponibilidade confirmada foi encontrado
                para os filtros escolhidos.
            </div>
        <?php endif; ?>
    </section>

    <section class="secao">
        <h2>Já adotados</h2>
        <p>
            Animais cujos anúncios indicam que a adoção foi concluída.
            Eles aparecem aqui, separados dos disponíveis.
        </p>

        <?php if ($adotadosLocais || $adotadosAdoteca): ?>
            <div class="grade">
                <?php foreach ($adotadosLocais as $animal): ?>
                    <?php exibirAnimalLocal($animal, true); ?>
                <?php endforeach; ?>

                <?php foreach ($adotadosAdoteca as $animal): ?>
                    <?php exibirAnimalAdoteca($animal, true); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="vazio">
                Nenhum animal com status de adoção concluída foi encontrado.
            </div>
        <?php endif; ?>
    </section>

    <?php if ($statusIncertoAdoteca): ?>
        <section class="secao">
            <h2>Status não confirmado</h2>
            <p>
                A fonte externa não informou um status reconhecível para
                estes anúncios. Eles não são classificados como disponíveis
                nem como adotados até que o status possa ser confirmado.
            </p>

            <div class="grade">
                <?php foreach ($statusIncertoAdoteca as $animal): ?>
                    <?php exibirAnimalAdoteca($animal); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <p class="detalhes" style="margin-top: 25px;">
        Os status externos dependem das informações fornecidas pela
        plataforma de origem. Confirme a situação no anúncio original.
    </p>

</main>

<footer>
    Saúde-Conecta — adoção responsável e cuidado com os animais.
</footer>

</body>
</html>