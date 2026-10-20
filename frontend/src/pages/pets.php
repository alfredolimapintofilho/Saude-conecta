<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SAÚDE CONECTA — MEUS PETS
|--------------------------------------------------------------------------
| Arquivo: frontend/src/pages/pets.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../../backend/config/seguranca.php';
require_once __DIR__ . '/../../../backend/config/database.php';

iniciarSessaoSegura();
$usuarioSessao = exigirLogin();

$pdo = Database::getConnection();

/*
|--------------------------------------------------------------------------
| FUNÇÕES AUXILIARES
|--------------------------------------------------------------------------
*/

function h($valor): string
{
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function converterDataParaTela($data): string
{
    if (empty($data)) {
        return '';
    }

    try {
        $dataObj = new DateTime((string)$data);
        return $dataObj->format('d/m/Y');
    } catch (Throwable $e) {
        return '';
    }
}

/*
|--------------------------------------------------------------------------
| IDENTIFICAR USUÁRIO
|--------------------------------------------------------------------------
*/

if (is_array($usuarioSessao)) {
    $usuarioId = (int)(
        $usuarioSessao['id']
        ?? $usuarioSessao['usuario_id']
        ?? $usuarioSessao['id_usuario']
        ?? 0
    );
} elseif (is_object($usuarioSessao)) {
    $usuarioId = (int)(
        $usuarioSessao->id
        ?? $usuarioSessao->usuario_id
        ?? $usuarioSessao->id_usuario
        ?? 0
    );
} else {
    $usuarioId = (int)$usuarioSessao;
}

if ($usuarioId <= 0) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| BUSCAR USUÁRIO
|--------------------------------------------------------------------------
*/

try {
    $stmtUsuario = $pdo->prepare(
        'SELECT * FROM usuarios WHERE id = :id LIMIT 1'
    );

    $stmtUsuario->execute(['id' => $usuarioId]);
    $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(403);
        exit('Usuário não encontrado. Faça login novamente.');
    }
} catch (PDOException $e) {
    error_log('Erro ao buscar usuário: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar os dados do usuário.');
}

$nomeUsuario =
    $usuario['nomeCompleto']
    ?? $usuario['nome_completo']
    ?? $usuario['nome']
    ?? 'Usuário';

/*
|--------------------------------------------------------------------------
| TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_pets'])) {
    $_SESSION['csrf_pets'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_pets'];

/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

$sucesso = '';
$erro = '';

if (isset($_GET['sucesso'])) {
    $mensagens = [
        'cadastrado' => 'Pet cadastrado com sucesso!',
        'editado'    => 'Informações do pet atualizadas com sucesso!',
        'excluido'   => 'Pet excluído com sucesso!'
    ];

    $sucesso = $mensagens[$_GET['sucesso']] ?? '';
}

/*
|--------------------------------------------------------------------------
| EXCLUIR PET
|--------------------------------------------------------------------------
| A exclusão é feita por POST, com token CSRF e validação de propriedade.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['acao'] ?? '') === 'excluir_pet') {

    $tokenRecebido = (string)($_POST['csrf_token'] ?? '');
    $petId = filter_var(
        $_POST['pet_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $tokenRecebido === ''
        || !hash_equals($csrfToken, $tokenRecebido)
    ) {
        $erro = 'Sua sessão de segurança expirou. Atualize a página e tente novamente.';
    } elseif (!$petId || $petId <= 0) {
        $erro = 'Pet inválido.';
    } else {
        try {
            $pdo->beginTransaction();

            // Confirma que o pet pertence ao usuário autenticado.
            $stmtVerificar = $pdo->prepare(
                'SELECT id FROM pets
                 WHERE id = :pet_id AND usuario_id = :usuario_id
                 LIMIT 1'
            );

            $stmtVerificar->execute([
                'pet_id' => $petId,
                'usuario_id' => $usuarioId
            ]);

            if (!$stmtVerificar->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                $erro = 'Pet não encontrado ou você não tem permissão para excluí-lo.';
            } else {
                // Exclui as vacinações associadas, conforme o esquema informado.
                $stmtVacinas = $pdo->prepare(
                    'DELETE FROM vacinacoes_pets WHERE pet_id = :pet_id'
                );

                $stmtVacinas->execute(['pet_id' => $petId]);

                // Exclui somente o pet do usuário autenticado.
                $stmtPet = $pdo->prepare(
                    'DELETE FROM pets
                     WHERE id = :pet_id AND usuario_id = :usuario_id'
                );

                $stmtPet->execute([
                    'pet_id' => $petId,
                    'usuario_id' => $usuarioId
                ]);

                $pdo->commit();

                header('Location: pets.php?sucesso=excluido');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Erro ao excluir pet: ' . $e->getMessage());
            $erro = 'Não foi possível excluir o pet. Verifique se existem outros registros vinculados a ele.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| BUSCAR PETS DO USUÁRIO
|--------------------------------------------------------------------------
*/

try {
    $stmtPets = $pdo->prepare(
        'SELECT * FROM pets
         WHERE usuario_id = :usuario_id
         ORDER BY id DESC'
    );

    $stmtPets->execute(['usuario_id' => $usuarioId]);
    $pets = $stmtPets->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao carregar pets: ' . $e->getMessage());
    $pets = [];
    $erro = 'Não foi possível carregar seus pets. Verifique a configuração do banco de dados.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meus Pets - Saúde Conecta</title>

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

        header {
            background: #0b7a75;
            color: white;
            padding: 20px;
        }

        .header-content {
            width: 92%;
            max-width: 1200px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logo {
            font-size: 25px;
            font-weight: bold;
        }

        .links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .links a {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,.15);
            padding: 10px 15px;
            border-radius: 8px;
        }

        .links a:hover {
            background: rgba(255,255,255,.28);
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 30px auto;
        }

        .intro {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
            margin-bottom: 25px;
        }

        .intro h1 {
            color: #0b7a75;
            margin-top: 0;
        }

        .botao-principal,
        .botao-adocao {
            display: inline-block;
            color: white;
            text-decoration: none;
            padding: 13px 20px;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 10px;
        }

        .botao-principal {
            background: #0b7a75;
        }

        .botao-principal:hover {
            background: #08635f;
        }

        .botao-adocao {
            background: #d97706;
            margin-left: 8px;
        }

        .botao-adocao:hover {
            background: #b45309;
        }

        .mensagem {
            padding: 15px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .sucesso {
            background: #dcfce7;
            color: #166534;
        }

        .erro {
            background: #fee2e2;
            color: #991b1b;
        }

        .titulo-secao {
            color: #0b7a75;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .pets-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .pet-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
            border: 1px solid #e5e7eb;
            min-width: 0;
        }

        .pet-foto,
        .pet-sem-foto {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #e5f5f3;
        }

        .pet-sem-foto {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 65px;
        }

        .pet-conteudo {
            padding: 20px;
        }

        .pet-nome {
            margin: 0 0 5px;
            color: #0b7a75;
            font-size: 23px;
            overflow-wrap: anywhere;
        }

        .pet-tipo {
            color: #64748b;
            margin-bottom: 15px;
        }

        .pet-info {
            margin: 8px 0;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .pet-info strong {
            color: #374151;
        }

        .botoes {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
        }

        .botao {
            display: inline-block;
            text-decoration: none;
            border: 0;
            padding: 10px 12px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
        }

        .botao-editar {
            background: #e0f2fe;
            color: #0369a1;
        }

        .botao-vacina {
            background: #dcfce7;
            color: #166534;
        }

        .botao-excluir {
            background: #fee2e2;
            color: #b91c1c;
        }

        .form-excluir {
            display: inline;
            margin: 0;
        }

        .vazio {
            background: white;
            padding: 40px;
            text-align: center;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
        }

        .vazio .icone {
            font-size: 65px;
            margin-bottom: 10px;
        }

        .adocao {
            margin-top: 30px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 25px;
            border-radius: 14px;
        }

        .adocao h2 {
            color: #c2410c;
            margin-top: 0;
        }

        @media (max-width: 950px) {
            .pets-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .pets-grid {
                grid-template-columns: 1fr;
            }

            .botao-adocao {
                margin-left: 0;
            }

            .intro {
                padding: 20px;
            }

            .vazio {
                padding: 25px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="header-content">
        <div class="logo">Saúde Conecta</div>

        <nav class="links" aria-label="Navegação principal">
            <!-- Correção: os arquivos estão na mesma pasta pages -->
            <a href="perfil_saude.php">← Voltar ao Perfil de Saúde</a>
            <a href="mapa.php">Mapa de Saúde</a>
            <a href="logout.php">Sair</a>
        </nav>
    </div>
</header>

<main class="container">

    <section class="intro">
        <h1>🐾 Meus Pets</h1>

        <p>Olá, <?= h($nomeUsuario) ?>!</p>

        <p>
            Aqui você pode cadastrar e acompanhar as informações
            dos seus animais, incluindo seus dados e vacinação.
        </p>

        <a href="cadastro_pet.php" class="botao-principal">
            + Cadastrar Pet
        </a>

        <a href="adocao_pets.php" class="botao-adocao">
            Adoção de Pets
        </a>
    </section>

    <?php if ($sucesso !== ''): ?>
        <div class="mensagem sucesso" role="status">
            <?= h($sucesso) ?>
        </div>
    <?php endif; ?>

    <?php if ($erro !== ''): ?>
        <div class="mensagem erro" role="alert">
            <?= h($erro) ?>
        </div>
    <?php endif; ?>

    <h2 class="titulo-secao">Animais cadastrados</h2>

    <?php if (count($pets) > 0): ?>

        <div class="pets-grid">

            <?php foreach ($pets as $pet): ?>
                <?php
                $foto = trim((string)($pet['foto'] ?? ''));
                $nomePet = (string)($pet['nome'] ?? 'Pet');
                ?>

                <article class="pet-card">

                    <?php if ($foto !== ''): ?>
                        <img
                            src="<?= h($foto) ?>"
                            alt="Foto de <?= h($nomePet) ?>"
                            class="pet-foto"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <div class="pet-sem-foto" aria-label="Pet sem foto">
                            🐾
                        </div>
                    <?php endif; ?>

                    <div class="pet-conteudo">

                        <h2 class="pet-nome"><?= h($nomePet) ?></h2>

                        <div class="pet-tipo">
                            <?= h($pet['tipo'] ?? 'Animal') ?>

                            <?php if (!empty($pet['especie'])): ?>
                                — <?= h($pet['especie']) ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($pet['raca'])): ?>
                            <div class="pet-info">
                                <strong>Raça:</strong>
                                <?= h($pet['raca']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($pet['sexo'])): ?>
                            <div class="pet-info">
                                <strong>Sexo:</strong>
                                <?= h($pet['sexo']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($pet['data_nascimento'])): ?>
                            <div class="pet-info">
                                <strong>Nascimento:</strong>
                                <?= h(converterDataParaTela($pet['data_nascimento'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($pet['cor'])): ?>
                            <div class="pet-info">
                                <strong>Cor:</strong>
                                <?= h($pet['cor']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (
                            isset($pet['peso'])
                            && $pet['peso'] !== ''
                            && $pet['peso'] !== null
                        ): ?>
                            <div class="pet-info">
                                <strong>Peso:</strong>
                                <?= h($pet['peso']) ?> kg
                            </div>
                        <?php endif; ?>

                        <?php if (
                            isset($pet['vacinado'])
                            && $pet['vacinado'] !== ''
                            && $pet['vacinado'] !== null
                        ): ?>
                            <div class="pet-info">
                                <strong>Vacinado:</strong>
                                <?= h($pet['vacinado']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (
                            isset($pet['castrado'])
                            && $pet['castrado'] !== ''
                            && $pet['castrado'] !== null
                        ): ?>
                            <div class="pet-info">
                                <strong>Castrado:</strong>
                                <?= h($pet['castrado']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="botoes">

                            <a
                                href="editar_pet.php?id=<?= (int)$pet['id'] ?>"
                                class="botao botao-editar"
                            >
                                Editar
                            </a>

                            <a
                                href="vacinacao_pet.php?pet_id=<?= (int)$pet['id'] ?>"
                                class="botao botao-vacina"
                            >
                                Vacinação
                            </a>

                            <a
                                href="pet.php?id=<?= (int)$pet['id'] ?>"
                                class="botao botao-editar"
                            >
                                Ver perfil
                            </a>

                            <form
                                method="POST"
                                action="pets.php"
                                class="form-excluir"
                                onsubmit="return confirmarExclusao(this);"
                            >
                                <input
                                    type="hidden"
                                    name="acao"
                                    value="excluir_pet"
                                >

                                <input
                                    type="hidden"
                                    name="pet_id"
                                    value="<?= (int)$pet['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrfToken) ?>"
                                >

                                <button
                                    type="submit"
                                    class="botao botao-excluir"
                                >
                                    Excluir
                                </button>
                            </form>

                        </div>
                    </div>
                </article>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <section class="vazio">
            <div class="icone">🐾</div>

            <h2>Você ainda não cadastrou nenhum pet.</h2>

            <p>
                Cadastre seu primeiro animal para começar a montar
                o perfil de saúde dele.
            </p>

            <a href="cadastro_pet.php" class="botao-principal">
                Cadastrar meu primeiro pet
            </a>
        </section>

    <?php endif; ?>

    <section class="adocao">
        <h2>🐶 Adoção de Pets</h2>

        <p>
            Está procurando um animal para adotar? Acesse a área
            de adoção para consultar os recursos disponíveis no projeto.
        </p>

        <a href="adocao_pets.php" class="botao-adocao">
            Ver opções de adoção
        </a>
    </section>

</main>

<script>
function confirmarExclusao(formulario) {
    const campoPet = formulario.querySelector('input[name="pet_id"]');
    const cartao = formulario.closest('.pet-card');
    const nome = cartao
        ? cartao.querySelector('.pet-nome')?.textContent.trim()
        : 'este pet';

    if (!campoPet || !campoPet.value) {
        alert('Não foi possível identificar o pet.');
        return false;
    }

    return confirm(
        'Tem certeza de que deseja excluir "' + nome + '"?\n\n' +
        'As informações de vacinação vinculadas também serão excluídas.'
    );
}
</script>

</body>
</html>