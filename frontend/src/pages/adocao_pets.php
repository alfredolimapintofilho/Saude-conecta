
<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/services/AdotecaClient.php';

$pdo = null;
$erroLocal = '';
$erroAdoteca = '';

$animaisLocais = [];
$animaisAdoteca = [];
$totalAdoteca = 0;

function h($valor): string
{
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function valorOuPadrao($valor, string $padrao = 'Não informado'): string
{
    if ($valor === null || trim((string)$valor) === '') {
        return $padrao;
    }

    return (string)$valor;
}

/**
 * Aceita somente links HTTP/HTTPS.
 */
function urlSegura($url): string
{
    if (!is_string($url) || trim($url) === '') {
        return '';
    }

    $partes = parse_url($url);

    if (
        !is_array($partes) ||
        !isset($partes['scheme'], $partes['host']) ||
        !in_array(strtolower($partes['scheme']), ['http', 'https'], true)
    ) {
        return '';
    }

    return $url;
}

/**
 * Converte respostas do MCP para a estrutura:
 * ['total' => ..., 'pets' => [...]]
 */
function extrairResultadoAdoteca($resposta): array
{
    if (!is_array($resposta)) {
        throw new RuntimeException(
            'A resposta da Adoteca não está no formato esperado.'
        );
    }

    if (!empty($resposta['isError'])) {
        throw new RuntimeException(
            'A API da Adoteca retornou um erro.'
        );
    }

    $candidatos = [];

    if (isset($resposta['structuredContent']['result'])) {
        $candidatos[] = $resposta['structuredContent']['result'];
    }

    if (isset($resposta['result'])) {
        $candidatos[] = $resposta['result'];
    }

    if (isset($resposta['structuredContent'])) {
        $candidatos[] = $resposta['structuredContent'];
    }

    if (isset($resposta['content']) && is_array($resposta['content'])) {
        foreach ($resposta['content'] as $item) {
            if (!empty($item['text']) && is_string($item['text'])) {
                $decodificado = json_decode(
                    $item['text'],
                    true
                );

                if (is_array($decodificado)) {
                    $candidatos[] = $decodificado;
                }
            }
        }
    }

    foreach ($candidatos as $candidato) {
        if (isset($candidato['result']) && is_array($candidato['result'])) {
            $candidato = $candidato['result'];
        }

        if (isset($candidato['pets']) && is_array($candidato['pets'])) {
            return [
                'total' => (int)($candidato['total'] ?? count($candidato['pets'])),
                'page' => (int)($candidato['page'] ?? 1),
                'pets' => $candidato['pets']
            ];
        }
    }

    throw new RuntimeException(
        'Não foi possível encontrar a lista de animais na resposta da API.'
    );
}

function simNaoDesconhecido($valor): string
{
    if ($valor === true || $valor === 1 || $valor === '1') {
        return 'Sim';
    }

    if ($valor === false || $valor === 0 || $valor === '0') {
        return 'Não';
    }

    return 'Não informado';
}

// Filtros
$especie = strtolower(trim((string)($_GET['especie'] ?? '')));
$busca = trim((string)($_GET['busca'] ?? ''));
$paginaAdoteca = filter_input(
    INPUT_GET,
    'pagina_adoteca',
    FILTER_VALIDATE_INT
);

$paginaAdoteca = max(1, $paginaAdoteca ?: 1);
$limiteAdoteca = 10;

$especiesPermitidas = [
    '',
    'cachorro',
    'gato'
];

if (!in_array($especie, $especiesPermitidas, true)) {
    $especie = '';
}

try {
    $pdo = Database::getConnection();

    /*
     * Anúncios cadastrados pelos usuários do Saúde-Conecta.
     * Este código considera a tabela pets_adocao criada anteriormente.
     */
    $sql = "
        SELECT
            id,
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
            status
        FROM pets_adocao
        WHERE status = 'disponivel'
    ";

    $parametros = [];

    if ($especie !== '') {
        $sql .= ' AND LOWER(especie) = :especie';
        $parametros[':especie'] = $especie;
    }

    if ($busca !== '') {
        $sql .= ' AND (nome LIKE :busca OR cidade LIKE :busca OR raca LIKE :busca)';
        $parametros[':busca'] = '%' . $busca . '%';
    }

    $sql .= ' ORDER BY criado_em DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);
    $animaisLocais = $consulta->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $erroLocal = 'Não foi possível carregar os anúncios locais. '
        . 'Confira a tabela pets_adocao e suas colunas.';

    error_log('[Saude-Conecta] Erro nos anúncios locais: ' . $e->getMessage());
}

/*
 * Busca animais na API da Adoteca.
 *
 * A implementação já testada aceita cidade, estado,
 * espécie e quantidade. A página solicitada é enviada
 * somente se a classe oferecer esse parâmetro.
 */
try {
    $cliente = new AdotecaClient();

    $metodo = new ReflectionMethod($cliente, 'buscarPets');

    if ($metodo->getNumberOfParameters() >= 5) {
        $resposta = $cliente->buscarPets(
            'Patos',
            'PB',
            $especie,
            $limiteAdoteca,
            $paginaAdoteca
        );
    } else {
        $resposta = $cliente->buscarPets(
            'Patos',
            'PB',
            $especie,
            $limiteAdoteca
        );
    }

    $resultado = extrairResultadoAdoteca($resposta);

    $totalAdoteca = $resultado['total'];
    $animaisAdoteca = $resultado['pets'];

    // Filtro textual complementar, aplicado aos resultados recebidos.
    if ($busca !== '') {
        $termo = function_exists('mb_strtolower')
            ? mb_strtolower($busca, 'UTF-8')
            : strtolower($busca);

        $animaisAdoteca = array_values(array_filter(
            $animaisAdoteca,
            function ($animal) use ($termo) {
                $texto = implode(' ', [
                    $animal['name'] ?? '',
                    $animal['species'] ?? '',
                    $animal['breed'] ?? '',
                    $animal['city'] ?? ''
                ]);

                $texto = function_exists('mb_strtolower')
                    ? mb_strtolower($texto, 'UTF-8')
                    : strtolower($texto);

                return strpos($texto, $termo) !== false;
            }
        ));
    }

} catch (Throwable $e) {
    $erroAdoteca = 'Não foi possível carregar os animais da Adoteca neste momento. '
        . 'Os anúncios locais continuam disponíveis.';

    error_log('[Saude-Conecta / Adoteca] ' . $e->getMessage());
}

$paginasAdoteca = max(
    1,
    (int)ceil($totalAdoteca / $limiteAdoteca)
);

$nomeUsuario = 'Visitante';

if (isset($_SESSION['usuario'])) {
    if (is_array($_SESSION['usuario'])) {
        $nomeUsuario = $_SESSION['usuario']['nomeCompleto']
            ?? $_SESSION['usuario']['nome']
            ?? $_SESSION['usuario']['nome_completo']
            ?? 'Usuário';
    } elseif (is_string($_SESSION['usuario'])) {
        $nomeUsuario = $_SESSION['usuario'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Adoção de Animais | Saúde-Conecta</title>

    <style>
        :root {
            --verde: #16794b;
            --verde-escuro: #105c39;
            --fundo: #f4f8f5;
            --texto: #23332a;
            --borda: #dce8df;
            --branco: #fff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--fundo);
            color: var(--texto);
            font-family: Arial, Helvetica, sans-serif;
        }

        header {
            background: var(--verde);
            color: white;
            padding: 20px;
        }

        .cabecalho,
        main {
            width: min(1180px, 94%);
            margin: auto;
        }

        .cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        header a {
            color: white;
            font-weight: bold;
            text-decoration: none;
        }

        main {
            padding: 28px 0 50px;
        }

        .introducao {
            margin-bottom: 24px;
        }

        .introducao h2 {
            margin-bottom: 8px;
            font-size: 1.9rem;
        }

        .introducao p {
            line-height: 1.6;
            color: #53675a;
        }

        .filtros {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) 190px auto;
            gap: 12px;
            background: white;
            border: 1px solid var(--borda);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 30px;
        }

        input,
        select,
        button {
            font: inherit;
            min-height: 44px;
            border-radius: 8px;
        }

        input,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd9ce;
            background: white;
        }

        button,
        .botao {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 10px 15px;
            border: 0;
            background: var(--verde);
            color: white;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            border-radius: 8px;
        }

        button:hover,
        .botao:hover {
            background: var(--verde-escuro);
        }

        .botao-secundario {
            background: #e9f4ed;
            color: var(--verde-escuro);
        }

        .botao-secundario:hover {
            background: #d7eadc;
        }

        .secao {
            margin-top: 32px;
        }

        .secao-cabecalho {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .secao-cabecalho h2 {
            margin: 0;
            font-size: 1.45rem;
        }

        .contador {
            color: #53675a;
            font-size: .95rem;
        }

        .galeria {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .cartao {
            background: white;
            border: 1px solid var(--borda);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-width: 0;
            box-shadow: 0 3px 12px rgb(20 60 35 / 5%);
        }

        .foto {
            width: 100%;
            height: 230px;
            object-fit: cover;
            background: #eaf0eb;
        }

        .sem-foto {
            height: 230px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eaf0eb;
            color: #526b5a;
        }

        .cartao-conteudo {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }

        .cartao h3 {
            margin: 0;
            font-size: 1.25rem;
            overflow-wrap: anywhere;
        }

        .detalhes {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .etiqueta {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 20px;
            background: #edf5ef;
            color: #28563b;
            font-size: .82rem;
        }

        .informacoes {
            color: #53675a;
            font-size: .93rem;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .informacoes p {
            margin: 3px 0;
        }

        .acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: auto;
            padding-top: 8px;
        }

        .acoes a {
            flex: 1 1 120px;
            text-align: center;
        }

        .aviso {
            border: 1px solid #eadca8;
            background: #fff9df;
            color: #6b5515;
            border-radius: 10px;
            padding: 12px 14px;
            margin: 14px 0;
            line-height: 1.5;
        }

        .vazio {
            border: 1px dashed #cbd9ce;
            background: white;
            border-radius: 12px;
            padding: 25px;
            color: #53675a;
            text-align: center;
        }

        .paginacao {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin: 24px 0;
        }

        footer {
            text-align: center;
            padding: 22px;
            color: #637568;
            font-size: .9rem;
        }

        @media (max-width: 850px) {
            .galeria {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filtros {
                grid-template-columns: 1fr 1fr;
            }

            .filtros button {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 560px) {
            .galeria,
            .filtros {
                grid-template-columns: 1fr;
            }

            .introducao h2 {
                font-size: 1.5rem;
            }

            .foto,
            .sem-foto {
                height: 250px;
            }
        }
    </style>
</head>

<body>
<header>
    <div class="cabecalho">
        <h1>Saúde-Conecta | Adoção de Animais</h1>

        <nav aria-label="Navegação principal">
            <a href="perfil_saude.php">Meu perfil</a>
            &nbsp; | &nbsp;
            <a href="pets.php">Meus pets</a>
            &nbsp; | &nbsp;
            <a href="cadastrar_pet_adocao.php">Cadastrar anúncio</a>
        </nav>
    </div>
</header>

<main>
    <section class="introducao">
        <h2>Encontre um amigo para a vida toda 🐾</h2>

        <p>
            Veja animais anunciados pela comunidade do Saúde-Conecta
            e pela Adoteca. Consulte o anúncio original para confirmar
            a disponibilidade e combinar a adoção com o responsável.
        </p>

        <p>Olá, <?= h($nomeUsuario) ?>!</p>

        <a class="botao" href="cadastrar_pet_adocao.php">
            + Anunciar animal para adoção
        </a>
    </section>

    <form class="filtros" method="get">
        <input
            type="search"
            name="busca"
            placeholder="Buscar por nome, raça ou cidade"
            value="<?= h($busca) ?>"
        >

        <select name="especie" aria-label="Filtrar por espécie">
            <option value="" <?= $especie === '' ? 'selected' : '' ?>>
                Todas as espécies
            </option>
            <option value="cachorro" <?= $especie === 'cachorro' ? 'selected' : '' ?>>
                Cachorros
            </option>
            <option value="gato" <?= $especie === 'gato' ? 'selected' : '' ?>>
                Gatos
            </option>
        </select>

        <button type="submit">Buscar animais</button>
    </form>

    <?php if ($erroLocal !== ''): ?>
        <div class="aviso"><?= h($erroLocal) ?></div>
    <?php endif; ?>

    <?php if ($erroAdoteca !== ''): ?>
        <div class="aviso">
            <?= h($erroAdoteca) ?>
            <br>
            Tente atualizar a página mais tarde.
        </div>
    <?php endif; ?>

    <section class="secao">
        <div class="secao-cabecalho">
            <h2>Animais da comunidade</h2>
            <span class="contador">
                <?= count($animaisLocais) ?> anúncio(s) encontrado(s)
            </span>
        </div>

        <?php if (count($animaisLocais) === 0): ?>
            <div class="vazio">
                Nenhum anúncio local encontrado com esses filtros.
                Você pode cadastrar um animal para adoção.
            </div>
        <?php else: ?>
            <div class="galeria">
                <?php foreach ($animaisLocais as $animal): ?>
                    <?php
                    $fotoLocal = '';

                    if (!empty($animal['foto'])) {
                        $caminhoFoto = (string)$animal['foto'];

                        if (
                            preg_match('~^https?://~i', $caminhoFoto)
                        ) {
                            $fotoLocal = urlSegura($caminhoFoto);
                        } elseif (strpos($caminhoFoto, '/') === false) {
                            $fotoLocal = 'uploads/adocao/' . rawurlencode($caminhoFoto);
                        } else {
                            $fotoLocal = $caminhoFoto;
                        }
                    }
                    ?>

                    <article class="cartao">
                        <?php if ($fotoLocal !== ''): ?>
                            <img
                                class="foto"
                                src="<?= h($fotoLocal) ?>"
                                alt="Foto de <?= h($animal['nome'] ?? 'animal') ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="sem-foto">Foto não disponível</div>
                        <?php endif; ?>

                        <div class="cartao-conteudo">
                            <h3><?= h($animal['nome'] ?? 'Animal') ?></h3>

                            <div class="detalhes">
                                <span class="etiqueta">
                                    <?= h(valorOuPadrao($animal['especie'] ?? null)) ?>
                                </span>

                                <?php if (!empty($animal['idade'])): ?>
                                    <span class="etiqueta"><?= h($animal['idade']) ?></span>
                                <?php endif; ?>

                                <?php if (!empty($animal['porte'])): ?>
                                    <span class="etiqueta"><?= h($animal['porte']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="informacoes">
                                <p><strong>Raça:</strong> <?= h(valorOuPadrao($animal['raca'] ?? null)) ?></p>
                                <p><strong>Sexo:</strong> <?= h(valorOuPadrao($animal['sexo'] ?? null)) ?></p>
                                <p>
                                    <strong>Local:</strong>
                                    <?= h(valorOuPadrao($animal['cidade'] ?? null)) ?>
                                    <?= !empty($animal['uf']) ? ' - ' . h($animal['uf']) : '' ?>
                                </p>
                                <p><strong>Vacinado:</strong> <?= h(simNaoDesconhecido($animal['vacinado'] ?? null)) ?></p>
                                <p><strong>Castrado:</strong> <?= h(simNaoDesconhecido($animal['castrado'] ?? null)) ?></p>
                                <p><?= nl2br(h($animal['descricao'] ?? '')) ?></p>
                            </div>

                            <div class="acoes">
                                <?php if (!empty($animal['contato'])): ?>
                                    <?php
                                    $contato = trim((string)$animal['contato']);
                                    $linkContato = '';

                                    if (filter_var($contato, FILTER_VALIDATE_EMAIL)) {
                                        $linkContato = 'mailto:' . $contato;
                                    } elseif (preg_match('/^[+\d()\s.-]{8,}$/', $contato)) {
                                        $telefone = preg_replace('/\D+/', '', $contato);
                                        $linkContato = 'https://wa.me/' . $telefone;
                                    }
                                    ?>

                                    <?php if ($linkContato !== ''): ?>
                                        <a
                                            class="botao"
                                            href="<?= h($linkContato) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >Entrar em contato</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="secao">
        <div class="secao-cabecalho">
            <h2>Animais da Adoteca</h2>

            <span class="contador">
                <?= $totalAdoteca ?> resultado(s) informado(s) pela API
            </span>
        </div>

        <p class="informacoes">
            Os anúncios abaixo vêm de uma fonte externa. Fotos, dados e
            disponibilidade são fornecidos pela Adoteca.
        </p>

        <?php if (count($animaisAdoteca) === 0): ?>
            <?php if ($erroAdoteca === ''): ?>
                <div class="vazio">
                    Nenhum animal foi retornado com os filtros atuais.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="galeria">
                <?php foreach ($animaisAdoteca as $animal): ?>
                    <?php
                    $foto = urlSegura($animal['photo_url'] ?? '');
                    $linkAnuncio = urlSegura($animal['adoption_url'] ?? '');
                    $linkContato = urlSegura($animal['contact_url'] ?? '');

                    $responsavel = $animal['rescuer']['name'] ?? '';
                    $instagram = urlSegura(
                        $animal['rescuer']['instagram_url'] ?? ''
                    );

                    $status = strtolower((string)($animal['status'] ?? ''));

                    if ($status !== 'available') {
                        continue;
                    }
                    ?>

                    <article class="cartao">
                        <?php if ($foto !== ''): ?>
                            <img
                                class="foto"
                                src="<?= h($foto) ?>"
                                alt="Foto do animal <?= h($animal['name'] ?? '') ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                            >
                        <?php else: ?>
                            <div class="sem-foto">Foto não disponível</div>
                        <?php endif; ?>

                        <div class="cartao-conteudo">
                            <h3><?= h($animal['name'] ?? 'Animal para adoção') ?></h3>

                            <div class="detalhes">
                                <span class="etiqueta">
                                    <?= h(valorOuPadrao($animal['species'] ?? null)) ?>
                                </span>

                                <?php if (!empty($animal['age'])): ?>
                                    <span class="etiqueta"><?= h($animal['age']) ?></span>
                                <?php endif; ?>

                                <?php if (!empty($animal['size'])): ?>
                                    <span class="etiqueta"><?= h($animal['size']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="informacoes">
                                <p>
                                    <strong>Raça:</strong>
                                    <?= h(valorOuPadrao($animal['breed'] ?? null)) ?>
                                </p>

                                <p>
                                    <strong>Sexo:</strong>
                                    <?= h(valorOuPadrao($animal['sex'] ?? null)) ?>
                                </p>

                                <p>
                                    <strong>Cidade:</strong>
                                    <?= h(valorOuPadrao($animal['city'] ?? null)) ?>
                                </p>

                                <p>
                                    <strong>Vacinado:</strong>
                                    <?= h(simNaoDesconhecido($animal['vaccinated'] ?? null)) ?>
                                </p>

                                <p>
                                    <strong>Castrado:</strong>
                                    <?= h(simNaoDesconhecido($animal['neutered'] ?? null)) ?>
                                </p>

                                <?php if ($responsavel !== ''): ?>
                                    <p><strong>Responsável:</strong> <?= h($responsavel) ?></p>
                                <?php endif; ?>

                                <?php if ($instagram !== ''): ?>
                                    <p>
                                        <a href="<?= h($instagram) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer">
                                            Instagram do responsável
                                        </a>
                                    </p>
                                <?php endif; ?>

                                <?php if (isset($animal['days_without_update'])): ?>
                                    <p>
                                        <strong>Última atualização informada:</strong>
                                        há <?= (int)$animal['days_without_update'] ?>
                                        dia(s)
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="acoes">
                                <?php if ($linkAnuncio !== ''): ?>
                                    <a
                                        class="botao botao-secundario"
                                        href="<?= h($linkAnuncio) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >Ver anúncio</a>
                                <?php endif; ?>

                                <?php if ($linkContato !== ''): ?>
                                    <a
                                        class="botao"
                                        href="<?= h($linkContato) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >Quero adotar</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($paginasAdoteca > 1): ?>
                <nav class="paginacao" aria-label="Paginação dos anúncios da Adoteca">
                    <?php if ($paginaAdoteca > 1): ?>
                        <a
                            class="botao botao-secundario"
                            href="?<?= h(http_build_query([
                                'busca' => $busca,
                                'especie' => $especie,
                                'pagina_adoteca' => $paginaAdoteca - 1
                            ])) ?>"
                        >← Anterior</a>
                    <?php endif; ?>

                    <span>
                        Página <?= $paginaAdoteca ?> de <?= $paginasAdoteca ?>
                    </span>

                    <?php if ($paginaAdoteca < $paginasAdoteca): ?>
                        <a
                            class="botao botao-secundario"
                            href="?<?= h(http_build_query([
                                'busca' => $busca,
                                'especie' => $especie,
                                'pagina_adoteca' => $paginaAdoteca + 1
                            ])) ?>"
                        >Próxima →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <p class="informacoes">
            A disponibilidade pode mudar. Confirme as informações diretamente
            com o responsável antes de combinar a adoção.
        </p>
    </section>
</main>

<footer>
    Saúde-Conecta — adoção responsável de animais.
</footer>
</body>
</html>