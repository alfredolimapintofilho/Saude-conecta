<?php

session_start();

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/models/Usuario.php';

$erro = '';
$sucesso = '';

$etapa = 1;


// ======================================================
// PROCESSAMENTO
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';


    // ==================================================
    // ETAPA 1
    // CPF + E-MAIL
    // ==================================================

    if ($acao === 'verificar') {

        $cpf = preg_replace(
            '/\D/',
            '',
            $_POST['cpf'] ?? ''
        );

        $email =
            trim($_POST['email'] ?? '');


        if (
            empty($cpf) ||
            empty($email)
        ) {

            $erro =
                'Informe o CPF e o e-mail.';

        } elseif (strlen($cpf) !== 11) {

            $erro =
                'CPF inválido.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $erro =
                'Digite um e-mail válido.';

        } else {

            try {

                $db =
                    Database::getConnection();

                $usuario =
                    new Usuario($db);

                $dadosUsuario =
                    $usuario->buscarPorCpf($cpf);


                if (
                    $dadosUsuario &&
                    strtolower(
                        $dadosUsuario['email']
                    ) === strtolower($email)
                ) {

                    // Guarda CPF na sessão
                    $_SESSION['recuperacao_cpf'] =
                        $cpf;

                    $etapa = 2;

                } else {

                    $erro =
                        'CPF e e-mail não correspondem a um cadastro.';
                }


            } catch (Exception $e) {

                $erro =
                    'Erro ao consultar o banco de dados.';
            }
        }
    }


    // ==================================================
    // ETAPA 2
    // ALTERAR SENHA
    // ==================================================

    elseif ($acao === 'alterar') {

        $novaSenha =
            $_POST['novaSenha'] ?? '';

        $confirmarSenha =
            $_POST['confirmarSenha'] ?? '';

        $cpf =
            $_SESSION['recuperacao_cpf'] ?? '';


        if (empty($cpf)) {

            $erro =
                'A sessão de recuperação expirou. Comece novamente.';

            $etapa = 1;

        } elseif (
            empty($novaSenha) ||
            empty($confirmarSenha)
        ) {

            $erro =
                'Preencha os dois campos de senha.';

            $etapa = 2;

        } elseif (strlen($novaSenha) < 6) {

            $erro =
                'A nova senha deve ter no mínimo 6 caracteres.';

            $etapa = 2;

        } elseif (
            $novaSenha !== $confirmarSenha
        ) {

            $erro =
                'As senhas não coincidem.';

            $etapa = 2;

        } else {

            try {

                $db =
                    Database::getConnection();


                // Cria hash seguro
                $senhaHash =
                    password_hash(
                        $novaSenha,
                        PASSWORD_DEFAULT
                    );


                // Atualiza senha
                $sql = "
                    UPDATE usuarios
                    SET senha_hash = :senha
                    WHERE cpf = :cpf
                ";


                $stmt =
                    $db->prepare($sql);


                $stmt->bindValue(
                    ':senha',
                    $senhaHash,
                    PDO::PARAM_STR
                );


                $stmt->bindValue(
                    ':cpf',
                    $cpf,
                    PDO::PARAM_STR
                );


                $stmt->execute();


                if ($stmt->rowCount() > 0) {

                    unset(
                        $_SESSION['recuperacao_cpf']
                    );


                    $sucesso =
                        'Senha alterada com sucesso! Você já pode fazer login.';

                    $etapa = 3;

                } else {

                    $erro =
                        'Não foi possível alterar a senha.';

                    $etapa = 2;
                }


            } catch (Exception $e) {

                $erro =
                    'Erro ao alterar a senha.';

                $etapa = 2;
            }
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

    <title>
        Recuperar senha - Saúde Conecta
    </title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>


<body
    class="bg-slate-50 min-h-screen flex items-center justify-center p-4"
>


<div
    class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-100"
>


    <!-- ==================================================
         CABEÇALHO
         ================================================== -->

    <div class="text-center mb-8">

        <div
            class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full mb-4 text-3xl"
        >
            🔐
        </div>

        <h1
            class="text-2xl font-bold text-slate-800"
        >
            Recuperar senha
        </h1>

        <p
            class="text-sm text-slate-500 mt-1"
        >
            Crie uma nova senha para acessar sua conta
        </p>

    </div>


    <!-- ==================================================
         ERRO
         ================================================== -->

    <?php if ($erro): ?>

        <div
            class="mb-5 p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-200"
        >

            <?= htmlspecialchars(
                $erro,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         SUCESSO
         ================================================== -->

    <?php if ($sucesso): ?>

        <div
            class="mb-5 p-4 bg-green-50 text-green-700 text-sm rounded-lg border border-green-200"
        >

            <?= htmlspecialchars(
                $sucesso,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>


        <a
            href="login.php"
            class="block text-center w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg"
        >
            Voltar para o login
        </a>

    <?php endif; ?>


    <!-- ==================================================
         ETAPA 1
         ================================================== -->

    <?php if ($etapa === 1): ?>


        <form
            method="POST"
            class="space-y-5"
        >

            <input
                type="hidden"
                name="acao"
                value="verificar"
            >


            <!-- CPF -->

            <div>

                <label
                    for="cpf"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    CPF
                </label>

                <input
                    type="text"
                    name="cpf"
                    id="cpf"
                    required
                    maxlength="14"
                    inputmode="numeric"
                    placeholder="000.000.000-00"
                    class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                >

            </div>


            <!-- E-MAIL -->

            <div>

                <label
                    for="email"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    E-mail cadastrado
                </label>

                <input
                    type="email"
                    name="email"
                    id="email"
                    required
                    placeholder="seuemail@email.com"
                    class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                >

            </div>


            <!-- BOTÃO -->

            <button
                type="submit"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg"
            >
                Continuar
            </button>

        </form>


    <?php elseif ($etapa === 2): ?>


        <!-- ==================================================
             ETAPA 2
             ================================================== -->

        <form
            method="POST"
            class="space-y-5"
        >

            <input
                type="hidden"
                name="acao"
                value="alterar"
            >


            <!-- ==================================================
                 NOVA SENHA
                 ================================================== -->

            <div>

                <label
                    for="novaSenha"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    Nova senha
                </label>


                <div class="relative">

                    <input
                        type="password"
                        name="novaSenha"
                        id="novaSenha"
                        required
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="Digite a nova senha"
                        class="w-full px-4 py-3 pr-12 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                    >


                    <!-- BOTÃO -->

                    <button
                        type="button"
                        onclick="alternarSenha('novaSenha', this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-500 hover:text-emerald-600 transition"
                        title="Mostrar senha"
                        aria-label="Mostrar senha"
                    >

                        <!-- OLHO RISCADO -->

                        <svg
                            class="icone-olho-riscado w-6 h-6"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 3l18 18"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M10.58 10.58a2 2 0 102.83 2.83"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9.88 4.24A9.77 9.77 0 0112 4c5 0 8.27 4.11 9.5 6a11.7 11.7 0 01-3.16 3.74"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6.61 6.61C4.62 7.89 3.35 9.65 2.5 11c1.23 1.89 4.5 6 9.5 6 1.61 0 3.03-.43 4.26-1.06"
                            />

                        </svg>


                        <!-- OLHO NORMAL -->

                        <svg
                            class="icone-olho w-6 h-6 hidden"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"
                            />

                            <circle
                                cx="12"
                                cy="12"
                                r="2.5"
                            />

                        </svg>

                    </button>

                </div>


                <p
                    class="text-xs text-slate-500 mt-1"
                >
                    Mínimo de 6 caracteres.
                </p>

            </div>


            <!-- ==================================================
                 CONFIRMAR SENHA
                 ================================================== -->

            <div>

                <label
                    for="confirmarSenha"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >
                    Confirmar nova senha
                </label>


                <div class="relative">

                    <input
                        type="password"
                        name="confirmarSenha"
                        id="confirmarSenha"
                        required
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="Digite novamente a senha"
                        class="w-full px-4 py-3 pr-12 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                    >


                    <!-- BOTÃO -->

                    <button
                        type="button"
                        onclick="alternarSenha('confirmarSenha', this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-500 hover:text-emerald-600 transition"
                        title="Mostrar senha"
                        aria-label="Mostrar senha"
                    >

                        <!-- OLHO RISCADO -->

                        <svg
                            class="icone-olho-riscado w-6 h-6"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 3l18 18"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M10.58 10.58a2 2 0 102.83 2.83"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9.88 4.24A9.77 9.77 0 0112 4c5 0 8.27 4.11 9.5 6a11.7 11.7 0 01-3.16 3.74"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6.61 6.61C4.62 7.89 3.35 9.65 2.5 11c1.23 1.89 4.5 6 9.5 6 1.61 0 3.03-.43 4.26-1.06"
                            />

                        </svg>


                        <!-- OLHO NORMAL -->

                        <svg
                            class="icone-olho w-6 h-6 hidden"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"
                            />

                            <circle
                                cx="12"
                                cy="12"
                                r="2.5"
                            />

                        </svg>

                    </button>

                </div>

            </div>


            <!-- BOTÃO -->

            <button
                type="submit"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg"
            >
                Alterar senha
            </button>

        </form>


    <?php endif; ?>


    <!-- VOLTAR -->

    <?php if ($etapa !== 3): ?>

        <div
            class="text-center mt-6"
        >

            <a
                href="login.php"
                class="text-sm text-emerald-600 hover:underline"
            >
                ← Voltar para o login
            </a>

        </div>

    <?php endif; ?>


</div>


<script>


    // ==================================================
    // MOSTRAR / ESCONDER SENHA
    // ==================================================

    function alternarSenha(
        campoId,
        botao
    ) {

        const campo =
            document.getElementById(campoId);

        const olhoRiscado =
            botao.querySelector('.icone-olho-riscado');

        const olho =
            botao.querySelector('.icone-olho');


        if (campo.type === 'password') {

            // Mostra senha
            campo.type = 'text';

            // Troca para olho normal
            olhoRiscado.classList.add('hidden');

            olho.classList.remove('hidden');

            botao.title = 'Ocultar senha';

            botao.setAttribute(
                'aria-label',
                'Ocultar senha'
            );

        } else {

            // Esconde senha
            campo.type = 'password';

            // Troca para olho riscado
            olhoRiscado.classList.remove('hidden');

            olho.classList.add('hidden');

            botao.title = 'Mostrar senha';

            botao.setAttribute(
                'aria-label',
                'Mostrar senha'
            );
        }
    }


    // ==================================================
    // MÁSCARA CPF
    // ==================================================

    const cpfInput =
        document.getElementById('cpf');


    if (cpfInput) {

        cpfInput.addEventListener(
            'input',
            function () {

                let value =
                    this.value.replace(/\D/g, '');

                value =
                    value.substring(0, 11);


                if (value.length > 9) {

                    value = value.replace(
                        /^(\d{3})(\d{3})(\d{3})(\d{1,2})$/,
                        '$1.$2.$3-$4'
                    );

                } else if (value.length > 6) {

                    value = value.replace(
                        /^(\d{3})(\d{3})(\d{1,3})$/,
                        '$1.$2.$3'
                    );

                } else if (value.length > 3) {

                    value = value.replace(
                        /^(\d{3})(\d{1,3})$/,
                        '$1.$2'
                    );
                }


                this.value = value;

            }
        );

    }

</script>


</body>

</html>