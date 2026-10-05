<?php

session_start();

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario'])) {
    header('Location: mapa.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Caminhos corretos:
    // register.php -> frontend/src/pages/
    // database.php -> backend/config/
    // Usuario.php  -> backend/models/

    require_once __DIR__ . '/../../../backend/config/database.php';
    require_once __DIR__ . '/../../../backend/models/Usuario.php';

    // Recebe os dados do formulário
    $cpf            = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $nomeCompleto   = trim($_POST['nomeCompleto'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $telefone       = trim($_POST['telefone'] ?? '');
    $dataNascimento = $_POST['dataNascimento'] ?? '';
    $cartaoSus      = trim($_POST['cartaoSus'] ?? '');
    $senha          = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmarSenha'] ?? '';
    $aceitaLgpd     = isset($_POST['aceitaLgpd']);

    // =========================
    // VALIDAÇÕES
    // =========================

    if (
        empty($cpf) ||
        empty($nomeCompleto) ||
        empty($email) ||
        empty($telefone) ||
        empty($dataNascimento) ||
        empty($senha) ||
        empty($confirmarSenha)
    ) {

        $erro = 'Preencha todos os campos obrigatórios.';

    } elseif (strlen($cpf) !== 11) {

        $erro = 'CPF inválido.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = 'Digite um e-mail válido.';

    } elseif (!DateTime::createFromFormat('Y-m-d', $dataNascimento)) {

        $erro = 'Data de nascimento inválida.';

    } elseif ($senha !== $confirmarSenha) {

        $erro = 'As senhas não coincidem.';

    } elseif (strlen($senha) < 6) {

        $erro = 'A senha deve ter no mínimo 6 caracteres.';

    } elseif (!$aceitaLgpd) {

        $erro = 'Você precisa aceitar os termos da LGPD para se cadastrar.';

    } else {

        try {

            // Conecta ao banco
            $db = Database::getConnection();

            // Instancia o usuário
            $usuario = new Usuario($db);

            // Verifica se CPF já existe
            if ($usuario->cpfExiste($cpf)) {

                $erro = 'Este CPF já está cadastrado.';

            // Verifica se e-mail já existe
            } elseif ($usuario->emailExiste($email)) {

                $erro = 'Este e-mail já está cadastrado.';

            } else {

                // Dados para cadastro
                $dados = [
                    'cpf'            => $cpf,
                    'nomeCompleto'   => $nomeCompleto,
                    'email'          => $email,
                    'telefone'       => $telefone,
                    'dataNascimento' => $dataNascimento,
                    'cartaoSus'      => $cartaoSus ?: null,
                    'senha'          => $senha,
                    'aceitaLgpd'     => true
                ];

                // Cria o usuário
                $novoId = $usuario->criar($dados);

                if ($novoId) {

                    $sucesso = 'Cadastro realizado com sucesso! Você já pode fazer login.';

                    // Limpa os campos
                    $_POST = [];

                } else {

                    $erro = 'Erro ao cadastrar. Verifique os dados e tente novamente.';
                }
            }

        } catch (PDOException $e) {

            $erro = 'Erro ao conectar ao banco de dados. Verifique se o MySQL está funcionando.';

        } catch (Exception $e) {

            $erro = 'Ocorreu um erro ao realizar o cadastro. Tente novamente.';
        }
    }
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

    <title>Cadastro - Saúde Conecta</title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-xl w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-100 my-8">

        <!-- CABEÇALHO -->

        <div class="text-center mb-6">

            <div
                class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full mb-3 text-2xl"
            >
                🏥
            </div>

            <h1 class="text-2xl font-bold text-slate-800">
                Criar Conta Cidadão
            </h1>

            <p class="text-sm text-slate-500 mt-1">
                Preencha seus dados para acessar as unidades de saúde de Patos-PB
            </p>

        </div>


        <!-- MENSAGEM DE ERRO -->

        <?php if ($erro): ?>

            <div
                class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-100"
            >

                <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>

            </div>

        <?php endif; ?>


        <!-- MENSAGEM DE SUCESSO -->

        <?php if ($sucesso): ?>

            <div
                class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg border border-green-100"
            >

                <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>

                <br>

                <a
                    href="login.php"
                    class="underline font-medium"
                >
                    Clique aqui para fazer login
                </a>

            </div>

        <?php endif; ?>


        <!-- FORMULÁRIO -->

        <form
            method="POST"
            action=""
            class="space-y-4"
        >

            <!-- NOME COMPLETO -->

            <div>

                <label
                    for="nomeCompleto"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    Nome Completo *
                </label>

                <input
                    type="text"
                    name="nomeCompleto"
                    id="nomeCompleto"
                    required
                    autocomplete="name"
                    value="<?= htmlspecialchars($_POST['nomeCompleto'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                >

            </div>


            <!-- CPF + DATA DE NASCIMENTO -->

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- CPF -->

                <div>

                    <label
                        for="cpf"
                        class="block text-sm font-medium text-slate-700 mb-1"
                    >
                        CPF *
                    </label>

                    <input
                        type="text"
                        name="cpf"
                        id="cpf"
                        required
                        maxlength="14"
                        inputmode="numeric"
                        placeholder="000.000.000-00"
                        autocomplete="username"
                        value="<?= htmlspecialchars($_POST['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                    >

                </div>


                <!-- DATA DE NASCIMENTO -->

                <div>

                    <label
                        for="dataNascimento"
                        class="block text-sm font-medium text-slate-700 mb-1"
                    >
                        Data de Nascimento *
                    </label>

                    <input
                        type="date"
                        name="dataNascimento"
                        id="dataNascimento"
                        required
                        value="<?= htmlspecialchars($_POST['dataNascimento'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                    >

                </div>

            </div>


            <!-- E-MAIL -->

            <div>

                <label
                    for="email"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    E-mail *
                </label>

                <input
                    type="email"
                    name="email"
                    id="email"
                    required
                    autocomplete="email"
                    placeholder="seuemail@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                >

            </div>


            <!-- TELEFONE -->

            <div>

                <label
                    for="telefone"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    Telefone *
                </label>

                <input
                    type="tel"
                    name="telefone"
                    id="telefone"
                    required
                    inputmode="tel"
                    maxlength="15"
                    placeholder="(83) 99999-9999"
                    value="<?= htmlspecialchars($_POST['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                >

            </div>


            <!-- CARTÃO SUS -->

            <div>

                <label
                    for="cartaoSus"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    Cartão SUS
                    <span class="text-slate-400 font-normal">(opcional)</span>
                </label>

                <input
                    type="text"
                    name="cartaoSus"
                    id="cartaoSus"
                    maxlength="20"
                    inputmode="numeric"
                    placeholder="Número do Cartão SUS"
                    value="<?= htmlspecialchars($_POST['cartaoSus'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                >

            </div>


            <!-- SENHA + CONFIRMAÇÃO -->

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- SENHA -->

                <div>

                    <label
                        for="senha"
                        class="block text-sm font-medium text-slate-700 mb-1"
                    >
                        Senha *
                    </label>

                    <input
                        type="password"
                        name="senha"
                        id="senha"
                        required
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="Mínimo 6 caracteres"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                    >

                </div>


                <!-- CONFIRMAR SENHA -->

                <div>

                    <label
                        for="confirmarSenha"
                        class="block text-sm font-medium text-slate-700 mb-1"
                    >
                        Confirmar Senha *
                    </label>

                    <input
                        type="password"
                        name="confirmarSenha"
                        id="confirmarSenha"
                        required
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="Repita a senha"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                    >

                </div>

            </div>


            <!-- LGPD -->

            <div class="flex items-start gap-3 pt-2">

                <input
                    type="checkbox"
                    name="aceitaLgpd"
                    id="aceitaLgpd"
                    required
                    class="mt-1 w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500"
                >

                <label
                    for="aceitaLgpd"
                    class="text-sm text-slate-600"
                >

                    Li e aceito os termos de uso e a
                    <strong>Política de Privacidade (LGPD)</strong>.

                </label>

            </div>


            <!-- BOTÃO -->

            <button
                type="submit"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg transition duration-200"
            >
                Criar minha conta
            </button>

        </form>


        <!-- LOGIN -->

        <div class="text-center mt-6 text-sm text-slate-600">

            Já possui uma conta?

            <a
                href="login.php"
                class="text-emerald-600 font-semibold hover:underline"
            >
                Fazer login
            </a>

        </div>

    </div>


    <!-- JAVASCRIPT -->

    <script>

        // =========================
        // MÁSCARA CPF
        // =========================

        const cpfInput = document.getElementById('cpf');

        cpfInput.addEventListener('input', function () {

            let value = this.value.replace(/\D/g, '');

            if (value.length > 11) {
                value = value.substring(0, 11);
            }

            value = value.replace(
                /(\d{3})(\d)/,
                '$1.$2'
            );

            value = value.replace(
                /(\d{3})(\d)/,
                '$1.$2'
            );

            value = value.replace(
                /(\d{3})(\d{1,2})$/,
                '$1-$2'
            );

            this.value = value;

        });


        // =========================
        // MÁSCARA TELEFONE
        // =========================

        const telefoneInput = document.getElementById('telefone');

        telefoneInput.addEventListener('input', function () {

            let value = this.value.replace(/\D/g, '');

            if (value.length > 11) {
                value = value.substring(0, 11);
            }

            if (value.length <= 10) {

                value = value.replace(
                    /^(\d{2})(\d)/,
                    '($1) $2'
                );

                value = value.replace(
                    /(\d{4})(\d)/,
                    '$1-$2'
                );

            } else {

                value = value.replace(
                    /^(\d{2})(\d)/,
                    '($1) $2'
                );

                value = value.replace(
                    /(\d{5})(\d)/,
                    '$1-$2'
                );

            }

            this.value = value;

        });


        // =========================
        // CARTÃO SUS
        // =========================

        const cartaoSusInput = document.getElementById('cartaoSus');

        cartaoSusInput.addEventListener('input', function () {

            this.value = this.value
                .replace(/\D/g, '')
                .substring(0, 20);

        });


        // =========================
        // VALIDAÇÃO DAS SENHAS
        // =========================

        const form = document.querySelector('form');

        form.addEventListener('submit', function (event) {

            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmarSenha').value;

            if (senha !== confirmarSenha) {

                event.preventDefault();

                alert('As senhas não coincidem.');

            }

        });

    </script>

</body>

</html>