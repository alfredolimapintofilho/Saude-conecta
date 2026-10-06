<?php

// =====================================================
// INICIA A SESSÃO
// =====================================================

session_start();


// =====================================================
// VERIFICA LOGIN
// =====================================================

if (!isset($_SESSION['usuario'])) {

    header('Location: ../pages/login.php');
    exit;

}


// =====================================================
// DADOS DO USUÁRIO
// =====================================================

$usuario = $_SESSION['usuario'];


// =====================================================
// NORMALIZA OS DADOS
// =====================================================

if (is_array($usuario)) {

    $idUsuario =
        $usuario['id']
        ?? '';

    $nomeUsuario =
        $usuario['nomeCompleto']
        ?? $usuario['nome']
        ?? $usuario['nome_completo']
        ?? '';

    $emailUsuario =
        $usuario['email']
        ?? '';

    $cpfUsuario =
        $usuario['cpf']
        ?? '';

    $tipoUsuario =
        $usuario['tipoUsuario']
        ?? $usuario['tipo_usuario']
        ?? '';

} else {

    $idUsuario = '';

    $nomeUsuario = $usuario;

    $emailUsuario = '';

    $cpfUsuario = '';

    $tipoUsuario = '';

}


// =====================================================
// VALORES PADRÃO
// =====================================================

if (empty($nomeUsuario)) {

    $nomeUsuario = 'Usuário';

}


// =====================================================
// PROTEÇÃO HTML
// =====================================================

$nomeExibicao = htmlspecialchars(
    $nomeUsuario,
    ENT_QUOTES,
    'UTF-8'
);

$emailExibicao = htmlspecialchars(
    $emailUsuario,
    ENT_QUOTES,
    'UTF-8'
);

$cpfExibicao = htmlspecialchars(
    $cpfUsuario,
    ENT_QUOTES,
    'UTF-8'
);

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Perfil de Saúde - Saúde Conecta</title>


    <!-- TAILWIND -->

    <script src="https://cdn.tailwindcss.com"></script>

</head>


<body class="bg-slate-100 min-h-screen">


    <!-- =================================================
         CABEÇALHO
         ================================================= -->

    <header class="bg-emerald-700 text-white shadow-md">

        <div
            class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between"
        >

            <div>

                <div
                    class="flex items-center gap-2 text-xl font-bold"
                >

                    <span>🏥</span>

                    <span>
                        Saúde Conecta
                    </span>

                </div>


                <div class="text-sm text-emerald-100">

                    Perfil de Saúde

                </div>

            </div>


            <a
                href="logout.php"
                onclick="return confirm('Deseja realmente sair da sua conta?');"
                class="bg-emerald-800 hover:bg-emerald-900 px-4 py-2 rounded-lg text-sm font-medium transition"
            >

                Sair

            </a>

        </div>

    </header>


    <!-- =================================================
         CONTEÚDO
         ================================================= -->

    <main
        class="max-w-7xl mx-auto px-4 py-8"
    >


        <!-- =================================================
             BEM-VINDO
             ================================================= -->

        <section
            class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6"
        >

            <div
                class="flex flex-col md:flex-row md:items-center md:justify-between gap-4"
            >

                <div>

                    <p
                        class="text-sm text-emerald-600 font-semibold mb-1"
                    >

                        SEU PERFIL DE SAÚDE

                    </p>


                    <h1
                        class="text-2xl md:text-3xl font-bold text-slate-800"
                    >

                        Olá, <?= $nomeExibicao ?>!

                    </h1>


                    <p
                        class="text-slate-500 mt-2"
                    >

                        Aqui você pode consultar e acompanhar
                        suas informações de saúde.

                    </p>

                </div>


                <!-- =================================================
                     BOTÃO MAPA
                     ================================================= -->

                <a
                    href="mapa.php"
                    class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl font-semibold transition shadow-sm"
                >

                    🗺️

                    Acessar mapa de unidades

                </a>

            </div>

        </section>


        <!-- =================================================
             DADOS PESSOAIS
             ================================================= -->

        <section
            class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6"
        >

            <div class="flex items-center gap-3 mb-5">

                <div
                    class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center text-xl"
                >

                    👤

                </div>


                <div>

                    <h2
                        class="text-xl font-bold text-slate-800"
                    >

                        Dados pessoais

                    </h2>

                    <p
                        class="text-sm text-slate-500"
                    >

                        Informações cadastradas na sua conta.

                    </p>

                </div>

            </div>


            <div
                class="grid grid-cols-1 md:grid-cols-3 gap-4"
            >


                <!-- NOME -->

                <div
                    class="bg-slate-50 rounded-xl p-4"
                >

                    <p class="text-xs text-slate-500">
                        Nome completo
                    </p>

                    <p
                        class="font-semibold text-slate-800 mt-1"
                    >

                        <?= $nomeExibicao ?>

                    </p>

                </div>


                <!-- CPF -->

                <div
                    class="bg-slate-50 rounded-xl p-4"
                >

                    <p class="text-xs text-slate-500">
                        CPF
                    </p>

                    <p
                        class="font-semibold text-slate-800 mt-1"
                    >

                        <?= $cpfExibicao ?: 'Não informado' ?>

                    </p>

                </div>


                <!-- EMAIL -->

                <div
                    class="bg-slate-50 rounded-xl p-4"
                >

                    <p class="text-xs text-slate-500">
                        E-mail
                    </p>

                    <p
                        class="font-semibold text-slate-800 mt-1 break-all"
                    >

                        <?= $emailExibicao ?: 'Não informado' ?>

                    </p>

                </div>

            </div>

        </section>


        <!-- =================================================
             CARTÃO SUS
             ================================================= -->

        <section
            class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6"
        >

            <div class="flex items-center gap-3 mb-5">

                <div
                    class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center text-xl"
                >

                    🪪

                </div>


                <div>

                    <h2
                        class="text-xl font-bold text-slate-800"
                    >

                        Cartão SUS

                    </h2>

                    <p
                        class="text-sm text-slate-500"
                    >

                        Número do seu Cartão Nacional de Saúde.

                    </p>

                </div>

            </div>


            <div
                class="bg-blue-50 border border-blue-100 rounded-xl p-5"
            >

                <p class="text-sm text-blue-700">

                    Cartão SUS

                </p>


                <p
                    class="text-xl font-bold text-blue-900 mt-1"
                >

                    <?php

                    echo !empty($usuario['cartaoSus'])
                        ? htmlspecialchars(
                            $usuario['cartaoSus'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : 'Não informado';

                    ?>

                </p>

            </div>

        </section>


        <!-- =================================================
             CARDS DE SAÚDE
             ================================================= -->

        <section>

            <div
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"
            >


                <!-- =========================================
                     VACINAÇÃO
                     ========================================= -->

                <a
                    href="#vacinacao"
                    class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-emerald-300 transition"
                >

                    <div
                        class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-2xl mb-4"
                    >

                        💉

                    </div>


                    <h3
                        class="text-lg font-bold text-slate-800"
                    >

                        Cartão de vacinação

                    </h3>


                    <p
                        class="text-sm text-slate-500 mt-2"
                    >

                        Consulte suas vacinas, doses e datas
                        de aplicação.

                    </p>


                    <div
                        class="mt-4 text-emerald-600 font-semibold text-sm"
                    >

                        Acessar →

                    </div>

                </a>


                <!-- =========================================
                     ALERGIAS
                     ========================================= -->

                <a
                    href="#alergias"
                    class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-red-300 transition"
                >

                    <div
                        class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center text-2xl mb-4"
                    >

                        ⚠️

                    </div>


                    <h3
                        class="text-lg font-bold text-slate-800"
                    >

                        Alergias

                    </h3>


                    <p
                        class="text-sm text-slate-500 mt-2"
                    >

                        Registre alergias a medicamentos,
                        alimentos e outras substâncias.

                    </p>


                    <div
                        class="mt-4 text-red-600 font-semibold text-sm"
                    >

                        Acessar →

                    </div>

                </a>


                <!-- =========================================
                     DOENÇAS
                     ========================================= -->

                <a
                    href="#doencas"
                    class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-purple-300 transition"
                >

                    <div
                        class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-2xl mb-4"
                    >

                        🦠

                    </div>


                    <h3
                        class="text-lg font-bold text-slate-800"
                    >

                        Histórico de doenças

                    </h3>


                    <p
                        class="text-sm text-slate-500 mt-2"
                    >

                        Acompanhe doenças diagnosticadas,
                        tratamentos e observações.

                    </p>


                    <div
                        class="mt-4 text-purple-600 font-semibold text-sm"
                    >

                        Acessar →

                    </div>

                </a>


                <!-- =========================================
                     ATESTADOS
                     ========================================= -->

                <a
                    href="#atestados"
                    class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-orange-300 transition"
                >

                    <div
                        class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-2xl mb-4"
                    >

                        📄

                    </div>


                    <h3
                        class="text-lg font-bold text-slate-800"
                    >

                        Atestados médicos

                    </h3>


                    <p
                        class="text-sm text-slate-500 mt-2"
                    >

                        Consulte e organize seus atestados
                        médicos.

                    </p>


                    <div
                        class="mt-4 text-orange-600 font-semibold text-sm"
                    >

                        Acessar →

                    </div>

                </a>


                <!-- =========================================
                     ATENDIMENTOS
                     ========================================= -->

                <a
                    href="#atendimentos"
                    class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-cyan-300 transition"
                >

                    <div
                        class="w-12 h-12 bg-cyan-100 rounded-xl flex items-center justify-center text-2xl mb-4"
                    >

                        🏥

                    </div>


                    <h3
                        class="text-lg font-bold text-slate-800"
                    >

                        Histórico de atendimentos

                    </h3>


                    <p
                        class="text-sm text-slate-500 mt-2"
                    >

                        Consulte seus atendimentos e
                        consultas anteriores.

                    </p>


                    <div
                        class="mt-4 text-cyan-600 font-semibold text-sm"
                    >

                        Acessar →

                    </div>

                </a>


                <!-- =========================================
                     MAPA
                     ========================================= -->

                <a
                    href="mapa.php"
                    class="bg-emerald-600 rounded-2xl p-6 shadow-sm hover:bg-emerald-700 transition text-white"
                >

                    <div
                        class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl mb-4"
                    >

                        🗺️

                    </div>


                    <h3
                        class="text-lg font-bold"
                    >

                        Mapa de saúde

                    </h3>


                    <p
                        class="text-sm text-emerald-50 mt-2"
                    >

                        Encontre hospitais, maternidades,
                        UPAs e UBSs em Patos.

                    </p>


                    <div
                        class="mt-4 text-white font-semibold text-sm"
                    >

                        Abrir mapa →

                    </div>

                </a>

            </div>

        </section>


        <!-- =================================================
             AVISO
             ================================================= -->

        <section
            class="mt-6 bg-amber-50 border border-amber-200 rounded-2xl p-5"
        >

            <div class="flex gap-3">

                <span class="text-xl">
                    🔒
                </span>


                <div>

                    <h3
                        class="font-bold text-amber-900"
                    >

                        Suas informações de saúde

                    </h3>


                    <p
                        class="text-sm text-amber-800 mt-1"
                    >

                        As informações do seu perfil de saúde
                        são pessoais e devem ser utilizadas
                        somente pelo titular da conta e por
                        profissionais autorizados.

                    </p>

                </div>

            </div>

        </section>

    </main>

</body>

</html>