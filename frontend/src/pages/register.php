<?php

session_start();


// =========================================================
// SE JÁ ESTIVER LOGADO, VAI PARA O MAPA
// =========================================================

if (isset($_SESSION['usuario'])) {
    header('Location: ../components/mapa.php');
    exit;
}


// =========================================================
// VARIÁVEIS
// =========================================================

$erro = '';
$sucesso = '';


// =========================================================
// PROCESSAMENTO DO CADASTRO
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =====================================================
    // CONEXÃO COM O BANCO
    // =====================================================

    require_once __DIR__ . '/../../../backend/config/database.php';
    require_once __DIR__ . '/../../../backend/models/Usuario.php';


    // =====================================================
    // RECEBER DADOS
    // =====================================================

    $cpf = preg_replace(
        '/\D/',
        '',
        $_POST['cpf'] ?? ''
    );

    $nomeCompleto = trim(
        $_POST['nomeCompleto'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $telefone = trim(
        $_POST['telefone'] ?? ''
    );

    $dataNascimentoDigitada = trim(
        $_POST['dataNascimento'] ?? ''
    );

    $cartaoSus = trim(
        $_POST['cartaoSus'] ?? ''
    );

    $senha = $_POST['senha'] ?? '';

    $confirmarSenha = $_POST['confirmarSenha'] ?? '';

    $aceitaLgpd = isset(
        $_POST['aceitaLgpd']
    );


    // =====================================================
    // CONVERTER DATA DD/MM/AAAA PARA AAAA-MM-DD
    // =====================================================

    $dataNascimento = '';

    if (!empty($dataNascimentoDigitada)) {

        $dataObj = DateTime::createFromFormat(
            'd/m/Y',
            $dataNascimentoDigitada
        );

        $errosData = DateTime::getLastErrors();

        // Em algumas versões do PHP getLastErrors()
        // pode retornar false quando não existem erros.

        $dataInvalida =
            $dataObj === false ||
            (
                $errosData !== false &&
                (
                    $errosData['warning_count'] > 0 ||
                    $errosData['error_count'] > 0
                )
            );


        if ($dataInvalida) {

            $erro = 'Digite uma data de nascimento válida.';

        } elseif (
            $dataObj->format('d/m/Y') !==
            $dataNascimentoDigitada
        ) {

            $erro = 'Digite a data no formato dd/mm/aaaa.';

        } else {

            // Impede data futura

            $hoje = new DateTime();

            if ($dataObj > $hoje) {

                $erro = 'A data de nascimento não pode ser futura.';

            } else {

                $dataNascimento =
                    $dataObj->format('Y-m-d');
            }
        }
    }


    // =====================================================
    // VALIDAÇÕES
    // =====================================================

    if (empty($erro)) {

        if (
            empty($cpf) ||
            empty($nomeCompleto) ||
            empty($email) ||
            empty($telefone) ||
            empty($dataNascimentoDigitada) ||
            empty($senha) ||
            empty($confirmarSenha)
        ) {

            $erro =
                'Preencha todos os campos obrigatórios.';

        } elseif (strlen($cpf) !== 11) {

            $erro = 'CPF inválido.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $erro = 'Digite um e-mail válido.';

        } elseif (
            strlen($senha) < 6
        ) {

            $erro =
                'A senha deve ter no mínimo 6 caracteres.';

        } elseif (
            $senha !== $confirmarSenha
        ) {

            $erro =
                'As senhas não coincidem.';

        } elseif (!$aceitaLgpd) {

            $erro =
                'Você precisa aceitar os termos da LGPD para se cadastrar.';
        }
    }


    // =====================================================
    // CADASTRAR
    // =====================================================

    if (empty($erro)) {

        try {

            // =============================================
            // CONECTAR AO BANCO
            // =============================================

            $db = Database::getConnection();


            // =============================================
            // CRIAR OBJETO USUÁRIO
            // =============================================

            $usuario = new Usuario($db);


            // =============================================
            // VERIFICAR CPF
            // =============================================

            if ($usuario->cpfExiste($cpf)) {

                $erro =
                    'Este CPF já está cadastrado.';

            }

            // =============================================
            // VERIFICAR E-MAIL
            // =============================================

            elseif ($usuario->emailExiste($email)) {

                $erro =
                    'Este e-mail já está cadastrado.';

            }

            // =============================================
            // CRIAR CONTA
            // =============================================

            else {

                $dados = [

                    'cpf' =>
                        $cpf,

                    'nomeCompleto' =>
                        $nomeCompleto,

                    'email' =>
                        $email,

                    'telefone' =>
                        $telefone,

                    'dataNascimento' =>
                        $dataNascimento,

                    'cartaoSus' =>
                        $cartaoSus !== ''
                            ? $cartaoSus
                            : null,

                    'senha' =>
                        $senha,

                    'aceitaLgpd' =>
                        true
                ];


                // =========================================
                // SALVAR
                // =========================================

                $novoId =
                    $usuario->criar($dados);


                if ($novoId) {

                    $sucesso =
                        'Cadastro realizado com sucesso! Você já pode fazer login.';

                    // Limpa os dados do formulário

                    $_POST = [];

                } else {

                    $erro =
                        'Erro ao cadastrar. Tente novamente.';
                }
            }

        } catch (Exception $e) {

            $erro =
                'Erro ao cadastrar. Verifique a conexão com o banco de dados.';
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


    <!-- =====================================================
         TAILWIND CSS
         ===================================================== -->

    <script src="https://cdn.tailwindcss.com"></script>

</head>


<body
    class="bg-slate-50 min-h-screen flex items-center justify-center p-4"
>


    <!-- =====================================================
         CARD
         ===================================================== -->

    <div
        class="max-w-xl w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-100 my-8"
    >


        <!-- =================================================
             CABEÇALHO
             ================================================= -->

        <div class="text-center mb-6">

            <div
                class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full mb-3 text-2xl"
            >

                🏥

            </div>


            <h1
                class="text-2xl font-bold text-slate-800"
            >

                Criar Conta Cidadão

            </h1>


            <p
                class="text-sm text-slate-500 mt-1"
            >

                Preencha seus dados para acessar as unidades de saúde de Patos-PB

            </p>

        </div>


        <!-- =================================================
             MENSAGEM DE ERRO
             ================================================= -->

        <?php if (!empty($erro)): ?>

            <div
                class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-200"
            >

                <?= htmlspecialchars(
                    $erro,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             MENSAGEM DE SUCESSO
             ================================================= -->

        <?php if (!empty($sucesso)): ?>

            <div
                class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg border border-green-200"
            >

                <?= htmlspecialchars(
                    $sucesso,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                <br>

                <a
                    href="login.php"
                    class="underline font-medium"
                >

                    Clique aqui para fazer login

                </a>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FORMULÁRIO
             ================================================= -->

        <form
            method="POST"
            action=""
            class="space-y-4"
        >


            <!-- =================================================
                 NOME
                 ================================================= -->

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
                    value="<?= htmlspecialchars(
                        $_POST['nomeCompleto'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                >

            </div>


            <!-- =================================================
                 CPF + DATA DE NASCIMENTO
                 ================================================= -->

            <div
                class="grid grid-cols-1 md:grid-cols-2 gap-4"
            >


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
                        autocomplete="off"
                        placeholder="000.000.000-00"
                        value="<?= htmlspecialchars(
                            $_POST['cpf'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                    >

                </div>


                <!-- =================================================
                     DATA DE NASCIMENTO
                     ================================================= -->

                <div>

                    <label
                        for="dataNascimento"
                        class="block text-sm font-medium text-slate-700 mb-1"
                    >

                        Data de Nascimento *

                    </label>


                    <input
                        type="text"
                        name="dataNascimento"
                        id="dataNascimento"
                        required
                        maxlength="10"
                        inputmode="numeric"
                        autocomplete="bday"
                        placeholder="dd/mm/aaaa"
                        value="<?= htmlspecialchars(
                            $_POST['dataNascimento'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                    >

                </div>

            </div>


            <!-- =================================================
                 E-MAIL
                 ================================================= -->

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
                    placeholder="seuemail@gmail.com"
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                >

            </div>


            <!-- =================================================
                 TELEFONE
                 ================================================= -->

            <div>

                <label
                    for="telefone"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >

                    Telefone *

                </label>


                <input
                    type="text"
                    name="telefone"
                    id="telefone"
                    required
                    maxlength="15"
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="(83) 99999-9999"
                    value="<?= htmlspecialchars(
                        $_POST['telefone'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                >

            </div>


            <!-- =================================================
                 CARTÃO SUS
                 ================================================= -->

            <div>

                <label
                    for="cartaoSus"
                    class="block text-sm font-medium text-slate-700 mb-1"
                >

                    Cartão SUS
                    <span class="text-slate-400">
                        (opcional)
                    </span>

                </label>


                <input
                    type="text"
                    name="cartaoSus"
                    id="cartaoSus"
                    maxlength="20"
                    inputmode="numeric"
                    placeholder="Número do Cartão SUS"
                    value="<?= htmlspecialchars(
                        $_POST['cartaoSus'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                >

            </div>


            <!-- =================================================
                 SENHA + CONFIRMAR SENHA
                 ================================================= -->

            <div
                class="grid grid-cols-1 md:grid-cols-2 gap-4"
            >


                <!-- SENHA -->

                <div>

                    <label
                        for="senha"
                        class="block text-sm font-medium text-slate-700 mb-1"
                    >

                        Senha *

                    </label>


                    <div class="relative">

                        <input
                            type="password"
                            name="senha"
                            id="senha"
                            required
                            minlength="6"
                            autocomplete="new-password"
                            placeholder="Mínimo 6 caracteres"
                            class="w-full px-4 py-2.5 pr-12 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                        >


                        <!-- BOTÃO OLHO -->

                        <button
                            type="button"
                            onclick="alternarSenha('senha', 'olhoSenhaAberto', 'olhoSenhaFechado')"
                            class="absolute right-0 top-0 h-full px-4 text-slate-500 hover:text-emerald-600"
                            aria-label="Mostrar ou ocultar senha"
                        >

                            <!-- OLHO ABERTO -->

                            <svg
                                id="olhoSenhaAberto"
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path
                                    d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"
                                />

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="3"
                                />

                            </svg>


                            <!-- OLHO FECHADO -->

                            <svg
                                id="olhoSenhaFechado"
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="hidden"
                            >

                                <path d="M3 3l18 18"/>

                                <path
                                    d="M10.58 10.58a2 2 0 0 0 2.83 2.83"
                                />

                                <path
                                    d="M9.88 4.24A9.77 9.77 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-3.16 4.19"
                                />

                                <path
                                    d="M6.61 6.61C3.84 8.48 2 12 2 12s3 8 10 8a9.8 9.8 0 0 0 4.61-1.14"
                                />

                            </svg>

                        </button>

                    </div>

                </div>


                <!-- CONFIRMAR SENHA -->

                <div>

                    <label
                        for="confirmarSenha"
                        class="block text-sm font-medium text-slate-700 mb-1"
                    >

                        Confirmar Senha *

                    </label>


                    <div class="relative">

                        <input
                            type="password"
                            name="confirmarSenha"
                            id="confirmarSenha"
                            required
                            minlength="6"
                            autocomplete="new-password"
                            placeholder="Repita a senha"
                            class="w-full px-4 py-2.5 pr-12 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none"
                        >


                        <!-- BOTÃO OLHO -->

                        <button
                            type="button"
                            onclick="alternarSenha('confirmarSenha', 'olhoConfirmarAberto', 'olhoConfirmarFechado')"
                            class="absolute right-0 top-0 h-full px-4 text-slate-500 hover:text-emerald-600"
                            aria-label="Mostrar ou ocultar confirmação de senha"
                        >

                            <!-- OLHO ABERTO -->

                            <svg
                                id="olhoConfirmarAberto"
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path
                                    d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"
                                />

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="3"
                                />

                            </svg>


                            <!-- OLHO FECHADO -->

                            <svg
                                id="olhoConfirmarFechado"
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="hidden"
                            >

                                <path d="M3 3l18 18"/>

                                <path
                                    d="M10.58 10.58a2 2 0 0 0 2.83 2.83"
                                />

                                <path
                                    d="M9.88 4.24A9.77 9.77 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-3.16 4.19"
                                />

                                <path
                                    d="M6.61 6.61C3.84 8.48 2 12 2 12s3 8 10 8a9.8 9.8 0 0 0 4.61-1.14"
                                />

                            </svg>

                        </button>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 LGPD
                 ================================================= -->

            <div class="flex items-start gap-3 pt-2">

                <input
                    type="checkbox"
                    name="aceitaLgpd"
                    id="aceitaLgpd"
                    required
                    class="mt-1 w-4 h-4 accent-emerald-600"
                    <?= isset($_POST['aceitaLgpd']) ? 'checked' : '' ?>
                >


                <label
                    for="aceitaLgpd"
                    class="text-sm text-slate-600"
                >

                    Li e aceito os termos de uso e a

                    <strong>
                        Política de Privacidade (LGPD)
                    </strong>.

                </label>

            </div>


            <!-- =================================================
                 BOTÃO CADASTRAR
                 ================================================= -->

            <button
                type="submit"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg transition"
            >

                Criar minha conta

            </button>


        </form>


        <!-- =================================================
             LINK PARA LOGIN
             ================================================= -->

        <div class="text-center mt-6">

            <p class="text-sm text-slate-500">

                Já possui uma conta?

                <a
                    href="login.php"
                    class="text-emerald-600 hover:text-emerald-700 font-medium"
                >

                    Fazer login

                </a>

            </p>

        </div>


    </div>


    <!-- =====================================================
         JAVASCRIPT
         ===================================================== -->

    <script>


        // =====================================================
        // MÁSCARA DO CPF
        // =====================================================

        const campoCpf =
            document.getElementById('cpf');


        campoCpf.addEventListener(
            'input',
            function () {

                let valor =
                    this.value.replace(/\D/g, '');


                if (valor.length > 11) {
                    valor =
                        valor.substring(0, 11);
                }


                if (valor.length > 9) {

                    valor =
                        valor.substring(0, 3) +
                        '.' +
                        valor.substring(3, 6) +
                        '.' +
                        valor.substring(6, 9) +
                        '-' +
                        valor.substring(9);

                }

                else if (valor.length > 6) {

                    valor =
                        valor.substring(0, 3) +
                        '.' +
                        valor.substring(3, 6) +
                        '.' +
                        valor.substring(6);

                }

                else if (valor.length > 3) {

                    valor =
                        valor.substring(0, 3) +
                        '.' +
                        valor.substring(3);

                }


                this.value = valor;
            }
        );


        // =====================================================
        // MÁSCARA DA DATA DE NASCIMENTO
        // =====================================================

        const campoData =
            document.getElementById('dataNascimento');


        campoData.addEventListener(
            'input',
            function () {

                let valor =
                    this.value.replace(/\D/g, '');


                // Máximo: 8 números
                // DD + MM + AAAA

                if (valor.length > 8) {

                    valor =
                        valor.substring(0, 8);
                }


                // DD/MM/AAAA

                if (valor.length > 4) {

                    valor =
                        valor.substring(0, 2) +
                        '/' +
                        valor.substring(2, 4) +
                        '/' +
                        valor.substring(4);

                }

                // DD/MM

                else if (valor.length > 2) {

                    valor =
                        valor.substring(0, 2) +
                        '/' +
                        valor.substring(2);

                }


                this.value = valor;
            }
        );


        // =====================================================
        // MÁSCARA DO TELEFONE
        // =====================================================

        const campoTelefone =
            document.getElementById('telefone');


        campoTelefone.addEventListener(
            'input',
            function () {

                let valor =
                    this.value.replace(/\D/g, '');


                if (valor.length > 11) {

                    valor =
                        valor.substring(0, 11);
                }


                if (valor.length > 10) {

                    valor =
                        '(' +
                        valor.substring(0, 2) +
                        ') ' +
                        valor.substring(2, 7) +
                        '-' +
                        valor.substring(7);

                }

                else if (valor.length > 6) {

                    valor =
                        '(' +
                        valor.substring(0, 2) +
                        ') ' +
                        valor.substring(2, 6) +
                        '-' +
                        valor.substring(6);

                }

                else if (valor.length > 2) {

                    valor =
                        '(' +
                        valor.substring(0, 2) +
                        ') ' +
                        valor.substring(2);

                }


                this.value = valor;
            }
        );


        // =====================================================
        // MOSTRAR / ESCONDER SENHA
        // =====================================================

        function alternarSenha(
            campoId,
            olhoAbertoId,
            olhoFechadoId
        ) {

            const campo =
                document.getElementById(campoId);

            const olhoAberto =
                document.getElementById(
                    olhoAbertoId
                );

            const olhoFechado =
                document.getElementById(
                    olhoFechadoId
                );


            // Mostrar senha

            if (campo.type === 'password') {

                campo.type = 'text';

                olhoAberto.classList.add(
                    'hidden'
                );

                olhoFechado.classList.remove(
                    'hidden'
                );

            }

            // Esconder senha

            else {

                campo.type = 'password';

                olhoFechado.classList.add(
                    'hidden'
                );

                olhoAberto.classList.remove(
                    'hidden'
                );
            }
        }

    </script>


</body>

</html>