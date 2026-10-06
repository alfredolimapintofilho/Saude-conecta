<?php

// =====================================================
// INICIA A SESSÃO
// =====================================================

session_start();


// =====================================================
// SE JÁ ESTIVER LOGADO, VAI PARA O PERFIL DE SAÚDE
// =====================================================

if (isset($_SESSION['usuario'])) {

    header('Location: ../components/perfil_saude.php');

    exit;
}


// =====================================================
// VARIÁVEIS
// =====================================================

$erro = '';

$email = '';


// =====================================================
// PROCESSA O LOGIN
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    // ================================================
    // RECEBE OS DADOS
    // ================================================

    $email = trim(
        $_POST['email'] ?? ''
    );

    $senha = $_POST['senha'] ?? '';


    // ================================================
    // VALIDA OS CAMPOS
    // ================================================

    if (empty($email)) {

        $erro = 'Digite seu e-mail.';

    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = 'Digite um e-mail válido.';

    }

    elseif (empty($senha)) {

        $erro = 'Digite sua senha.';

    }

    else {


        // ============================================
        // CONEXÃO COM O BANCO
        // ============================================

        require_once __DIR__ . '/../../../backend/config/database.php';

        require_once __DIR__ . '/../../../backend/models/Usuario.php';


        try {


            // ========================================
            // PEGA A CONEXÃO
            // ========================================

            $db = Database::getConnection();


            // ========================================
            // CRIA O MODEL
            // ========================================

            $usuarioModel = new Usuario($db);


            // ========================================
            // AUTENTICA
            // ========================================

            $usuario =
                $usuarioModel->autenticarPorEmail(
                    $email,
                    $senha
                );


            // ========================================
            // VERIFICA LOGIN
            // ========================================

            if ($usuario === false) {

                $erro =
                    'E-mail ou senha incorretos.';

            }

            else {


                // ====================================
                // REGENERA A SESSÃO
                // ====================================

                session_regenerate_id(true);


                // ====================================
                // SALVA O USUÁRIO NA SESSÃO
                // ====================================

                $_SESSION['usuario'] = [

                    'id' =>
                        $usuario['id'],

                    'nomeCompleto' =>
                        $usuario['nome_completo'],

                    'email' =>
                        $usuario['email'],

                    'cpf' =>
                        $usuario['cpf'],

                    'tipoUsuario' =>
                        $usuario['tipo_usuario'],

                    'cartaoSus' =>
                        $usuario['cartao_sus'] ?? ''

                ];


                // ====================================
                // REDIRECIONA PARA O PERFIL DE SAÚDE
                // ====================================

                header(
                    'Location: ../components/perfil_saude.php'
                );

                exit;

            }


        } catch (PDOException $e) {


            // ========================================
            // ERRO DE BANCO
            // ========================================

            $erro =
                'Erro ao acessar o banco de dados: '
                . $e->getMessage();

        } catch (Exception $e) {


            // ========================================
            // OUTROS ERROS
            // ========================================

            $erro =
                'Ocorreu um erro ao realizar o login.';

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

    <title>Login - Saúde Conecta</title>


    <!-- =================================================
         TAILWIND CSS
         ================================================= -->

    <script src="https://cdn.tailwindcss.com"></script>


    <!-- =================================================
         ESTILOS
         ================================================= -->

    <style>

        body {

            min-height: 100vh;

            background:
                linear-gradient(
                    135deg,
                    #ecfdf5,
                    #f0fdfa,
                    #f8fafc
                );

        }


        .campo-senha {

            position: relative;

        }


        .campo-senha input {

            padding-right: 50px;

        }


        .botao-senha {

            position: absolute;

            right: 12px;

            top: 50%;

            transform: translateY(-50%);

            background: transparent;

            border: none;

            cursor: pointer;

            font-size: 20px;

            color: #64748b;

        }


        .botao-senha:hover {

            color: #047857;

        }

    </style>

</head>


<body>


    <!-- =================================================
         CONTAINER
         ================================================= -->

    <div
        class="min-h-screen flex items-center justify-center px-4 py-8"
    >

        <div
            class="w-full max-w-md"
        >


            <!-- =================================================
                 LOGO / TÍTULO
                 ================================================= -->

            <div
                class="text-center mb-8"
            >

                <div
                    class="inline-flex items-center justify-center w-16 h-16 bg-emerald-600 text-white rounded-2xl text-3xl shadow-lg mb-4"
                >

                    🏥

                </div>


                <h1
                    class="text-3xl font-bold text-slate-800"
                >

                    Saúde Conecta

                </h1>


                <p
                    class="text-slate-500 mt-2"
                >

                    Acesse seu perfil de saúde

                </p>

            </div>


            <!-- =================================================
                 CARD DE LOGIN
                 ================================================= -->

            <div
                class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6 md:p-8"
            >


                <!-- =================================================
                     MENSAGEM DE ERRO
                     ================================================= -->

                <?php if (!empty($erro)): ?>

                    <div
                        class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm"
                    >

                        <div
                            class="flex items-start gap-2"
                        >

                            <span>
                                ⚠️
                            </span>

                            <span>

                                <?= htmlspecialchars(
                                    $erro,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     FORMULÁRIO
                     ================================================= -->

                <form
                    method="POST"
                    action=""
                    class="space-y-5"
                >


                    <!-- =================================================
                         EMAIL
                         ================================================= -->

                    <div>

                        <label
                            for="email"
                            class="block text-sm font-semibold text-slate-700 mb-2"
                        >

                            E-mail

                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Digite seu e-mail"
                            autocomplete="email"
                            required
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                        >

                    </div>


                    <!-- =================================================
                         SENHA
                         ================================================= -->

                    <div>

                        <div
                            class="flex items-center justify-between mb-2"
                        >

                            <label
                                for="senha"
                                class="block text-sm font-semibold text-slate-700"
                            >

                                Senha

                            </label>


                            <a
                                href="recuperar_senha.php"
                                class="text-sm text-emerald-600 hover:text-emerald-700 font-medium"
                            >

                                Esqueci minha senha

                            </a>

                        </div>


                        <div class="campo-senha">


                            <input
                                type="password"
                                id="senha"
                                name="senha"
                                placeholder="Digite sua senha"
                                autocomplete="current-password"
                                required
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                            >


                            <!-- BOTÃO MOSTRAR SENHA -->

                            <button
                                type="button"
                                class="botao-senha"
                                id="botaoMostrarSenha"
                                onclick="mostrarSenha()"
                                aria-label="Mostrar senha"
                            >

                                👁️

                            </button>

                        </div>

                    </div>


                    <!-- =================================================
                         BOTÃO ENTRAR
                         ================================================= -->

                    <button
                        type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-sm"
                    >

                        Entrar

                    </button>

                </form>


                <!-- =================================================
                     CADASTRO
                     ================================================= -->

                <div
                    class="mt-6 pt-6 border-t border-slate-200 text-center"
                >

                    <p
                        class="text-sm text-slate-500"
                    >

                        Ainda não possui uma conta?

                    </p>


                    <a
                        href="register.php"
                        class="inline-block mt-2 text-emerald-600 hover:text-emerald-700 font-bold"
                    >

                        Criar minha conta

                    </a>

                </div>

            </div>


            <!-- =================================================
                 RODAPÉ
                 ================================================= -->

            <p
                class="text-center text-xs text-slate-400 mt-6"
            >

                Saúde Conecta © <?= date('Y') ?>

            </p>

        </div>

    </div>


    <!-- =================================================
         JAVASCRIPT
         ================================================= -->

    <script>

        function mostrarSenha() {

            const campoSenha =
                document.getElementById('senha');

            const botao =
                document.getElementById(
                    'botaoMostrarSenha'
                );


            if (
                campoSenha.type === 'password'
            ) {

                campoSenha.type = 'text';

                botao.textContent = '🙈';

                botao.setAttribute(
                    'aria-label',
                    'Ocultar senha'
                );

            }

            else {

                campoSenha.type = 'password';

                botao.textContent = '👁️';

                botao.setAttribute(
                    'aria-label',
                    'Mostrar senha'
                );

            }

        }

    </script>

</body>

</html>