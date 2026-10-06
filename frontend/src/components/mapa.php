<?php

// ============================================
// INICIA A SESSÃO
// ============================================

session_start();


// ============================================
// VERIFICA SE O USUÁRIO ESTÁ LOGADO
// ============================================

if (!isset($_SESSION['usuario'])) {

    header('Location: ../pages/login.php');
    exit;

}


// ============================================
// DADOS DO USUÁRIO
// ============================================

$usuario = $_SESSION['usuario'];

$nomeUsuario = '';
$emailUsuario = '';

if (is_array($usuario)) {

    $nomeUsuario =
        $usuario['nomeCompleto']
        ?? $usuario['nome']
        ?? $usuario['nome_completo']
        ?? '';

    $emailUsuario =
        $usuario['email']
        ?? '';

} else {

    $nomeUsuario = $usuario;

}

if (empty($nomeUsuario)) {

    $nomeUsuario = 'Usuário';

}


// ============================================
// PROTEÇÃO CONTRA HTML
// ============================================

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

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Saúde Conecta</title>


    <!-- TAILWIND -->

    <script src="https://cdn.tailwindcss.com"></script>


    <!-- LEAFLET -->

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >


    <style>

        html,
        body {

            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;

        }


        #map {

            width: 100%;
            height: calc(100vh - 106px);

        }


        /* ======================================
           POPUP
           ====================================== */

        .popup-saude {

            width: 290px;
            max-width: 290px;

        }


        .popup-saude img {

            width: 100%;
            height: 155px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
            display: block;

        }


        .popup-titulo {

            font-size: 17px;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 10px;
            line-height: 1.3;

        }


        .popup-info {

            margin-top: 8px;
            line-height: 1.45;
            color: #475569;

        }


        .popup-label {

            font-weight: 700;
            color: #334155;

        }


        .popup-social {

            display: inline-block;
            margin-top: 10px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 8px;
            text-decoration: none;
            color: #0f766e;
            font-weight: 600;

        }


        .popup-social:hover {

            background: #dff7ef;

        }


        .popup-emergencia {

            margin-top: 10px;
            padding: 8px;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.4;

        }


        .popup-atencao {

            margin-top: 10px;
            padding: 8px;
            border-radius: 8px;
            background: #ecfdf5;
            color: #047857;
            font-size: 13px;
            line-height: 1.4;

        }

    </style>

</head>


<body class="bg-slate-50">


    <!-- ==========================================
         CABEÇALHO
         ========================================== -->

    <header class="bg-emerald-700 text-white">

        <div
            class="flex items-center justify-between px-4 py-3"
        >

            <div>

                <div
                    class="flex items-center gap-2 text-lg font-bold"
                >

                    <span>🏥</span>

                    <span>
                        Saúde Conecta
                    </span>

                </div>


                <div
                    class="text-xs text-emerald-100"
                >

                    Olá,
                    <?= $nomeExibicao ?>

                </div>

            </div>


            <div
                class="flex items-center gap-3"
            >

                <?php if (!empty($emailExibicao)): ?>

                    <span
                        class="hidden md:block text-sm font-medium"
                    >

                        <?= $emailExibicao ?>

                    </span>

                <?php endif; ?>


                <a
                    href="logout.php"
                    onclick="return confirm('Deseja realmente sair da sua conta?');"
                    class="bg-emerald-800 hover:bg-emerald-900 text-white px-4 py-2 rounded-lg text-sm font-medium transition"
                >

                    Sair

                </a>

            </div>

        </div>

    </header>


    <!-- ==========================================
         FILTROS
         ========================================== -->

    <div
        class="bg-white border-b border-slate-200 px-4 py-3"
    >

        <div
            class="flex items-center gap-2 flex-wrap"
        >

            <span
                class="text-sm font-medium text-slate-700 mr-1"
            >

                Filtrar:

            </span>


            <button
                type="button"
                onclick="filtrarUnidades('todos')"
                class="filtro-btn bg-emerald-600 text-white px-4 py-2 rounded-full text-sm font-medium"
            >

                Todos

            </button>


            <button
                type="button"
                onclick="filtrarUnidades('hospital')"
                class="filtro-btn bg-slate-100 text-slate-700 px-4 py-2 rounded-full text-sm"
            >

                🏥 Hospitais

            </button>


            <button
                type="button"
                onclick="filtrarUnidades('maternidade')"
                class="filtro-btn bg-slate-100 text-slate-700 px-4 py-2 rounded-full text-sm"
            >

                👶 Maternidades

            </button>


            <button
                type="button"
                onclick="filtrarUnidades('upa')"
                class="filtro-btn bg-slate-100 text-slate-700 px-4 py-2 rounded-full text-sm"
            >

                🚑 UPAs

            </button>


            <button
                type="button"
                onclick="filtrarUnidades('ubs')"
                class="filtro-btn bg-slate-100 text-slate-700 px-4 py-2 rounded-full text-sm"
            >

                🩺 UBS e Postos

            </button>

        </div>

    </div>


    <!-- ==========================================
         MAPA
         ========================================== -->

    <div id="map"></div>


    <!-- ==========================================
         LEAFLET JS
         ========================================== -->

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    ></script>


    <script>

        // ==========================================
        // CRIA O MAPA
        // ==========================================

        const map = L.map('map').setView(
            [-7.0178, -37.2748],
            13
        );


        // ==========================================
        // OPENSTREETMAP
        // ==========================================

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {

                maxZoom: 19,

                attribution:
                    '&copy; OpenStreetMap contributors'

            }
        ).addTo(map);


        // ==========================================
        // UNIDADES DE SAÚDE
        // ==========================================

        const unidades = [

            // ======================================
            // HOSPITAL REGIONAL
            // ======================================

            {
                nome:
                    'Hospital Regional de Patos Deputado Janduhy Carneiro',

                tipo:
                    'hospital',

                lat:
                    -7.0250,

                lng:
                    -37.2760,

                endereco:
                    'Rua Horácio Nóbrega, s/n - Belo Horizonte, Patos - PB',

                horario:
                    '24 horas',

                emergencia:
                    'Sim. Atendimento de urgência e emergência.',

                telefone:
                    'Não localizado publicamente',

                social:
                    '',

                socialNome:
                    '',

                imagem:
                    'https://pbnews.com.br/images/noticias/28470/a1c36132cfb43ef9854ea260c4416445.jpg'
            },


            // ======================================
            // MATERNIDADE
            // ======================================

            {
                nome:
                    'Maternidade Dr. Peregrino Filho',

                tipo:
                    'maternidade',

                lat:
                    -7.0310,

                lng:
                    -37.2700,

                endereco:
                    'Rua Elias Asfora, s/n - Jardim Guanabara, Patos - PB',

                horario:
                    '24 horas',

                emergencia:
                    'Atendimento hospitalar e obstétrico.',

                telefone:
                    'Não localizado publicamente',

                social:
                    '',

                socialNome:
                    '',

                imagem:
                    'https://www.submit.10envolve.com.br/uploads/060c211a683eb92a9288c98a2a5c77fdb7d2d2c7/29cf881fb407297482a86301a32652de.jpg'
            },


            // ======================================
            // UPA JOÃO BOSCO
            // ======================================

            {
                nome:
                    'UPA João Bosco de Araújo',

                tipo:
                    'upa',

                lat:
                    -7.02457,

                lng:
                    -37.28658,

                endereco:
                    'Bairro Jatobá, Patos - PB',

                horario:
                    '24 horas',

                emergencia:
                    'Sim. Atendimento de urgência e emergência.',

                telefone:
                    'Não localizado publicamente',

                social:
                    'https://www.instagram.com/upa_joaoboscoaraujo/',

                socialNome:
                    '@upa_joaoboscoaraujo',

                imagem:
                    'https://lh3.googleusercontent.com/gps-cs-s/ANWiy9QxlYIbnPdONdcjrPV9aS0qLn_BJpPuZNsbAuyhRx0qevGm1eLIlngBNmdO8wclSkDgZLyqyahRsTbjckrNQkYx8PfZnNYbQga3WwkuOPzPtdoO8NAip4SYjWKFM4duVSIonUla=s680-w680-h510-rw'
            },


            // ======================================
            // UPA DR. OTÁVIO
            // ======================================

            {
                nome:
                    'UPA Dr. Otávio Pires de Lacerda',

                tipo:
                    'upa',

                lat:
                    -7.03053,

                lng:
                    -37.27833,

                endereco:
                    'Rua do Prado, s/n - Liberdade, Patos - PB',

                horario:
                    '24 horas',

                emergencia:
                    'Sim. Atendimento de urgência e emergência.',

                telefone:
                    'Não localizado publicamente',

                social:
                    'https://www.instagram.com/upadrotaviopires/',

                socialNome:
                    '@upadrotaviopires',

                imagem:
                    'https://www.polemicaparaiba.com.br/wp-content/uploads/2017/07/upa-Patos.jpg'
            },


            // ======================================
            // UBS HORÁCIO NÓBREGA
            // ======================================

            {
                nome:
                    'UBS Horácio Nóbrega',

                tipo:
                    'ubs',

                lat:
                    -7.01842,

                lng:
                    -37.28265,

                endereco:
                    'Rua Horácio Nóbrega, s/n - São Sebastião, Patos - PB',

                horario:
                    'Atendimento ampliado: 18h às 00h',

                emergencia:
                    'Não. UBS destinada principalmente à atenção básica.',

                telefone:
                    'Não localizado publicamente',

                social:
                    '',

                socialNome:
                    '',

                imagem:
                    'https://lh3.googleusercontent.com/gps-cs-s/ANWiy9R2c8KXB6NJWUumb7RIn18kCpBuUJz2j4g2HiSyEqMrl1CJKGaHTFN689_iETvm5i7P3HwIVJCJ4C8KbLwnVLaJFB1kncUQcoQFEZGio_9IZCd5j0gvUPhnkD7PoqUPmyC8_9A2=s680-w680-h510-rw'
            },


            // ======================================
            // UBS CARLEUSA CANDEIA
            // ======================================

            {
                nome:
                    'UBS Carleusa Candeia',

                tipo:
                    'ubs',

                lat:
                    -7.0197,

                lng:
                    -37.2860,

                endereco:
                    'Rua Escritor Augusto dos Anjos, s/n - Santo Antônio, Patos - PB',

                horario:
                    'Segunda a sexta: 7h às 11h, 13h às 17h e atendimento ampliado das 18h às 22h',

                emergencia:
                    'Não. UBS destinada principalmente à atenção básica.',

                telefone:
                    'Não localizado publicamente',

                social:
                    'https://www.instagram.com/ubscarleusacandeia/',

                socialNome:
                    '@ubscarleusacandeia',

                imagem:
                    'https://lh3.googleusercontent.com/gps-cs-s/ANWiy9RoEuEwY4k3aNGhhi4kxuSeebE5qajbYW2CySFqu6rSWrqz_m5bXstQ3Fs_qDMOGFLOAuUJMmU4TCcqWcW_IL1F5ly2Nm982dLqVz4bvUyxnpS8UhyrlJtYWWloWlm21YCN9vfCFQ=s680-w680-h510-rw'
            },


            // ======================================
            // UBS JOSÉ DE OLIVEIRA PIO
            // ======================================

            {
                nome:
                    'UBS José de Oliveira Pio',

                tipo:
                    'ubs',

                lat:
                    -7.0300,

                lng:
                    -37.3000,

                endereco:
                    'Rua Semeão Gentil, s/n - Bivar Olinto, Patos - PB',

                horario:
                    'Atendimento ampliado: 17h às 21h',

                emergencia:
                    'Não. UBS destinada principalmente à atenção básica.',

                telefone:
                    'Não localizado publicamente',

                social:
                    'https://www.instagram.com/ubsjosedeoliveirapio/',

                socialNome:
                    '@ubsjosedeoliveirapio',

                imagem:
                    'https://scontent.cdninstagram.com/v/t51.2885-19/90342932_2301194696847146_237314213167497216_n.jpg?stp=dst-jpg_s150x150_tt6&_nc_cat=103&ccb=7-5&_nc_sid=f7ccc5&efg=eyJ2ZW5jb2RlX3RhZyI6InByb2ZpbGVfcGljLnd3dy4zNzYuQzMifQ%3D%3D&_nc_ohc=MHthDEL75ocQ7kNvwGHdnca&_nc_oc=AdrNxkPQKxf6wiEaXYtUEL6QeGjn8ZxEvo_cEtkZdhMy7YRn0vRcAaQ5nv2rPPYz1PE&_nc_zt=24&_nc_ht=scontent.cdninstagram.com&_nc_ss=7b689&oh=00_AQNCgkf_eIRPqHnD_wZfvbxAO23riVugQcnZpLW-Vwxz-w&oe=6ACB1AD0'
            },


            // ======================================
            // UBS MARIA MARQUES
            // ======================================

            {
                nome:
                    'UBS Maria Marques',

                tipo:
                    'ubs',

                lat:
                    -7.0308,

                lng:
                    -37.2850,

                endereco:
                    'Rua Manoel Mota, 1758-1774 - Jatobá, Patos - PB',

                horario:
                    'Segunda a sexta: 13h às 17h e 18h às 22h',

                emergencia:
                    'Não. UBS destinada principalmente à atenção básica.',

                telefone:
                    'Não localizado publicamente',

                social:
                    'https://www.instagram.com/ubsmariamarques/',

                socialNome:
                    '@ubsmariamarques',

                imagem:
                    'https://scontent.cdninstagram.com/v/t51.2885-19/236964643_693076891649518_8495659670102807657_n.jpg?stp=dst-jpg_s150x150_tt6&_nc_cat=110&ccb=7-5&_nc_sid=f7ccc5&efg=eyJ2ZW5jb2RlX3RhZyI6InByb2ZpbGVfcGljLnd3dy4xMDgwLkMzIn0%3D&_nc_ohc=NvtWiGz7mQ8Q7kNvwHogaSs&_nc_oc=Adr7zk57fAs-C10ddFABQWHtdQl_vL5gsHx8Oy2ZfESllrgaK29fkpyuGieWAMx5dVI&_nc_zt=24&_nc_ht=scontent.cdninstagram.com&_nc_ss=7b689&oh=00_AQPk_jQKqKyZRUvCmw6YtrEMkQdlWZgP3KV_lrr1f7F6LQ&oe=6ACB2D6C'
            },


            // ======================================
            // UBS PEDRO FIRMINO
            // ======================================

            {
                nome:
                    'UBS Pedro Firmino',

                tipo:
                    'ubs',

                lat:
                    -7.0340,

                lng:
                    -37.2910,

                endereco:
                    'Bairro Santa Clara / José Mariz, Patos - PB',

                horario:
                    'Atendimento ampliado: 18h às 22h',

                emergencia:
                    'Não. UBS destinada principalmente à atenção básica.',

                telefone:
                    'Não localizado publicamente',

                social:
                    '',

                socialNome:
                    '',

                imagem:
                    'https://encrypted-tbn3.gstatic.com/images?q=tbn:ANd9GcRxOBSdcfIiOuPENEqgZQgstXpVyl3RaYjbZqErVa2ROt6Uf3bN'
            }

        ];


        // ==========================================
        // ARRAY DE MARCADORES
        // ==========================================

        const marcadores = [];


        // ==========================================
        // CRIA OS MARCADORES
        // ==========================================

        unidades.forEach(function(unidade) {


            const marcador = L.marker([

                unidade.lat,
                unidade.lng

            ]).addTo(map);


            // ======================================
            // IMAGEM
            // ======================================

            let imagemHTML = '';


            if (unidade.imagem) {

                imagemHTML = `

                    <img
                        src="${unidade.imagem}"
                        alt="${unidade.nome}"
                        onerror="this.style.display='none';"
                    >

                `;

            }


            // ======================================
            // REDE SOCIAL
            // ======================================

            let socialHTML = '';


            if (unidade.social) {

                socialHTML = `

                    <a
                        href="${unidade.social}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="popup-social"
                    >

                        📱 ${unidade.socialNome}

                    </a>

                `;

            } else {

                socialHTML = `

                    <div class="popup-info">

                        📱

                        <span class="popup-label">
                            Rede social:
                        </span>

                        Não localizada

                    </div>

                `;

            }


            // ======================================
            // TIPO DE ATENDIMENTO
            // ======================================

            let atendimentoHTML = '';


            if (unidade.tipo === 'upa') {

                atendimentoHTML = `

                    <div class="popup-emergencia">

                        🚑

                        <strong>
                            Atendimento de urgência e emergência
                        </strong>

                    </div>

                `;

            }


            else if (unidade.tipo === 'hospital') {

                atendimentoHTML = `

                    <div class="popup-emergencia">

                        🚑

                        <strong>
                            Atendimento hospitalar e de urgência/emergência
                        </strong>

                    </div>

                `;

            }


            else if (unidade.tipo === 'maternidade') {

                atendimentoHTML = `

                    <div class="popup-atencao">

                        👶

                        <strong>
                            Atendimento obstétrico e hospitalar
                        </strong>

                    </div>

                `;

            }


            else {

                atendimentoHTML = `

                    <div class="popup-atencao">

                        🩺

                        <strong>
                            Unidade Básica de Saúde
                        </strong>

                        <br>

                        Atendimento de atenção básica.

                    </div>

                `;

            }


            // ======================================
            // POPUP
            // ======================================

            const popup = `

                <div class="popup-saude">

                    ${imagemHTML}


                    <div class="popup-titulo">

                        ${unidade.nome}

                    </div>


                    <div class="popup-info">

                        📍

                        <span class="popup-label">
                            Endereço:
                        </span>

                        ${unidade.endereco}

                    </div>


                    <div class="popup-info">

                        🕐

                        <span class="popup-label">
                            Horário:
                        </span>

                        ${unidade.horario}

                    </div>


                    <div class="popup-info">

                        🚑

                        <span class="popup-label">
                            Urgência/emergência:
                        </span>

                        ${unidade.emergencia}

                    </div>


                    <div class="popup-info">

                        ☎️

                        <span class="popup-label">
                            Telefone:
                        </span>

                        ${unidade.telefone}

                    </div>


                    ${atendimentoHTML}


                    ${socialHTML}

                </div>

            `;


            // ======================================
            // ADICIONA POPUP
            // ======================================

            marcador.bindPopup(
                popup,
                {
                    maxWidth: 320
                }
            );


            // ======================================
            // GUARDA O TIPO
            // ======================================

            marcador.unidadeTipo =
                unidade.tipo;


            // ======================================
            // GUARDA O MARCADOR
            // ======================================

            marcadores.push(marcador);

        });


        // ==========================================
        // FILTRAR UNIDADES
        // ==========================================

        function filtrarUnidades(tipo) {

            marcadores.forEach(
                function(marcador) {

                    if (
                        tipo === 'todos' ||
                        marcador.unidadeTipo === tipo
                    ) {

                        if (!map.hasLayer(marcador)) {

                            marcador.addTo(map);

                        }

                    } else {

                        if (map.hasLayer(marcador)) {

                            map.removeLayer(marcador);

                        }

                    }

                }
            );

        }


        // ==========================================
        // ESTILO DOS BOTÕES
        // ==========================================

        const botoesFiltro =
            document.querySelectorAll(
                '.filtro-btn'
            );


        botoesFiltro.forEach(
            function(botao) {

                botao.addEventListener(
                    'click',
                    function() {

                        botoesFiltro.forEach(
                            function(b) {

                                b.classList.remove(
                                    'bg-emerald-600',
                                    'text-white'
                                );

                                b.classList.add(
                                    'bg-slate-100',
                                    'text-slate-700'
                                );

                            }
                        );


                        botao.classList.remove(
                            'bg-slate-100',
                            'text-slate-700'
                        );


                        botao.classList.add(
                            'bg-emerald-600',
                            'text-white'
                        );

                    }
                );

            }
        );

    </script>

</body>

</html>