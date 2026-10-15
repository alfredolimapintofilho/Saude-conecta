<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../pages/login.php');
    exit;
}

$usuario = $_SESSION['usuario'];

if (is_array($usuario)) {
    $nomeUsuario =
        $usuario['nomeCompleto']
        ?? $usuario['nome']
        ?? $usuario['nome_completo']
        ?? 'Usuário';
} else {
    $nomeUsuario = 'Usuário';
}

$nomeUsuario = htmlspecialchars(
    $nomeUsuario,
    ENT_QUOTES,
    'UTF-8'
);

/*
|--------------------------------------------------------------------------
| ASSUNTOS DE SAÚDE E LINKS OFICIAIS
|--------------------------------------------------------------------------
| Cada cartão abre diretamente a página específica do assunto.
*/

$assuntos = [
    [
        'nome' => 'Asma',
        'letra' => 'A',
        'descricao' => 'Informações sobre asma, sintomas e cuidados.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/a/asma'
    ],
    [
        'nome' => 'Câncer',
        'letra' => 'C',
        'descricao' => 'Informações sobre câncer, prevenção e diagnóstico.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/c/cancer'
    ],
    [
        'nome' => 'Dengue',
        'letra' => 'D',
        'descricao' => 'Sintomas, prevenção e orientações sobre dengue.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/d/dengue'
    ],
    [
        'nome' => 'Diabetes',
        'letra' => 'D',
        'descricao' => 'Informações sobre diabetes, tratamento e prevenção.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/d/diabetes'
    ],
    [
        'nome' => 'Gripe (Influenza)',
        'letra' => 'G',
        'descricao' => 'Orientações sobre gripe, sintomas e prevenção.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/g/gripe-influenza'
    ],
    [
        'nome' => 'Hipertensão',
        'letra' => 'H',
        'descricao' => 'Informações sobre pressão alta e cuidados.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/h/hipertensao'
    ],
    [
        'nome' => 'Saúde mental',
        'letra' => 'S',
        'descricao' => 'Informações e orientações sobre saúde mental.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/s/saude-mental/'
    ],
    [
        'nome' => 'Vacinação',
        'letra' => 'V',
        'descricao' => 'Informações sobre vacinas e vacinação.',
        'url' => 'https://www.gov.br/saude/pt-br/assuntos/vacinacao'
    ]
];

usort($assuntos, function ($a, $b) {
    return strcasecmp($a['nome'], $b['nome']);
});
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saúde de A a Z - Saúde-Conecta</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f9;
            color: #263238;
            line-height: 1.5;
        }

        header {
            background: linear-gradient(135deg, #00695c, #00897b);
            color: white;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .15);
        }

        .header-conteudo {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .acoes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .botao {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: bold;
            color: white;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .35);
        }

        .botao:hover {
            background: rgba(255, 255, 255, .3);
        }

        main {
            max-width: 1200px;
            margin: 28px auto;
            padding: 0 18px;
        }

        .introducao {
            background: white;
            border-radius: 15px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .06);
        }

        .introducao h2 {
            color: #00695c;
            margin-bottom: 8px;
        }

        .pesquisa {
            margin-top: 20px;
        }

        .pesquisa label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .pesquisa input {
            width: 100%;
            padding: 13px;
            border: 1px solid #b0bec5;
            border-radius: 9px;
            font-size: 16px;
        }

        .pesquisa input:focus {
            outline: 2px solid #00897b;
            border-color: #00897b;
        }

        .aviso {
            margin-top: 12px;
            font-size: 13px;
            color: #546e7a;
        }

        .titulo-lista {
            margin: 25px 0 15px;
            color: #00695c;
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .cartao {
            display: flex;
            flex-direction: column;
            background: white;
            padding: 22px;
            border-radius: 14px;
            border: 1px solid #e0e7e9;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .06);
            min-height: 220px;
        }

        .letra {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #e0f2f1;
            color: #00695c;
            font-size: 19px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .cartao h3 {
            color: #263238;
            margin-bottom: 8px;
            font-size: 20px;
        }

        .cartao p {
            color: #546e7a;
            margin-bottom: 18px;
            flex-grow: 1;
        }

        .link-assunto {
            display: block;
            text-align: center;
            text-decoration: none;
            background: #00897b;
            color: white;
            padding: 11px 14px;
            border-radius: 9px;
            font-weight: bold;
        }

        .link-assunto:hover {
            background: #00695c;
        }

        .vazio {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-top: 15px;
            color: #546e7a;
        }

        footer {
            text-align: center;
            padding: 25px 18px;
            color: #546e7a;
            font-size: 13px;
        }

        @media (max-width: 850px) {
            .grade {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 550px) {
            .grade {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 23px;
            }

            .cartao {
                min-height: 190px;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="header-conteudo">
        <div>
            <h1>📚 Saúde de A a Z</h1>
            <p>
                Olá, <?= $nomeUsuario ?>.
                Consulte informações oficiais sobre saúde.
            </p>
        </div>

        <nav class="acoes">
            <a href="perfil_saude.php" class="botao">Perfil de Saúde</a>
            <a href="mapa.php" class="botao">Mapa da Saúde</a>
            <a href="logout.php" class="botao">Sair</a>
        </nav>
    </div>
</header>

<main>
    <section class="introducao">
        <h2>Pesquise assuntos de saúde</h2>
        <p>
            Encontre um assunto e clique em
            "Consultar informação oficial" para abrir
            diretamente a página correspondente no portal
            do Ministério da Saúde.
        </p>

        <div class="pesquisa">
            <label for="pesquisarAssunto">Pesquisar assunto de saúde</label>
            <input
                type="search"
                id="pesquisarAssunto"
                placeholder="Ex.: asma, câncer, diabetes..."
                autocomplete="off"
            >
        </div>

        <p class="aviso">
            As informações detalhadas são apresentadas
            no site oficial do Ministério da Saúde.
        </p>
    </section>

    <h2 class="titulo-lista">Assuntos disponíveis</h2>

    <section class="grade" id="listaAssuntos">
        <?php foreach ($assuntos as $assunto): ?>
            <article
                class="cartao"
                data-nome="<?= htmlspecialchars($assunto['nome'], ENT_QUOTES, 'UTF-8') ?>"
            >
                <span class="letra">
                    <?= htmlspecialchars($assunto['letra'], ENT_QUOTES, 'UTF-8') ?>
                </span>

                <h3>
                    <?= htmlspecialchars($assunto['nome'], ENT_QUOTES, 'UTF-8') ?>
                </h3>

                <p>
                    <?= htmlspecialchars($assunto['descricao'], ENT_QUOTES, 'UTF-8') ?>
                </p>

                <a
                    class="link-assunto"
                    href="<?= htmlspecialchars($assunto['url'], ENT_QUOTES, 'UTF-8') ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Consultar informação oficial
                </a>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="vazio" id="nenhumResultado">
        Nenhum assunto encontrado.
        Tente pesquisar usando outro termo.
    </div>
</main>

<footer>
    Saúde-Conecta — Consulte fontes oficiais de informação em saúde.
</footer>

<script>
    const campoPesquisa = document.getElementById('pesquisarAssunto');
    const cartoes = document.querySelectorAll('.cartao');
    const mensagemVazia = document.getElementById('nenhumResultado');

    campoPesquisa.addEventListener('input', function () {
        const termo = this.value.trim().toLocaleLowerCase('pt-BR');
        let encontrados = 0;

        cartoes.forEach(function (cartao) {
            const nome = cartao.dataset.nome.toLocaleLowerCase('pt-BR');
            const descricao = cartao.querySelector('p').textContent.toLocaleLowerCase('pt-BR');
            const corresponde = nome.includes(termo) || descricao.includes(termo);

            cartao.style.display = corresponde ? 'flex' : 'none';

            if (corresponde) {
                encontrados++;
            }
        });

        mensagemVazia.style.display = encontrados === 0 ? 'block' : 'none';
    });
</script>
</body>
</html>