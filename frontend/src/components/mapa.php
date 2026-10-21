<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../pages/login.php');
    exit;
}

$usuario = $_SESSION['usuario'];

$nomeUsuario = is_array($usuario)
    ? ($usuario['nome'] ?? $usuario['nome_completo'] ?? 'Cidadão')
    : ($usuario->nome ?? 'Cidadão');

$emailUsuario = is_array($usuario)
    ? ($usuario['email'] ?? '')
    : ($usuario->email ?? '');

function e($valor) {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#087f70">

    <title>Mapa de Saúde | Saúde-Conecta</title>

    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        :root {
            --primary: #087f70;
            --primary-dark: #075e54;
            --primary-soft: #e2f5f0;
            --ink: #183b3a;
            --muted: #627775;
            --page: #f3f8f7;
            --white: #fff;
            --border: #dce9e6;
            --shadow: 0 14px 38px
                rgba(16, 75, 67, .09);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: var(--page);
            font-family: Inter, system-ui, -apple-system,
                "Segoe UI", sans-serif;
        }

        .topbar {
            min-height: 78px;
            padding: 14px clamp(16px, 4vw, 56px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 4px 20px
                rgba(15, 67, 60, .04);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            color: white;
            background: linear-gradient(
                145deg, #0d9488, #06685f
            );
            font-size: 25px;
            flex-shrink: 0;
        }

        .brand h1 {
            margin: 0;
            font-size: clamp(18px, 2vw, 22px);
            letter-spacing: -.5px;
        }

        .brand p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 13px;
            text-decoration: none;
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 750;
            transition: .18s;
        }

        .btn-primary {
            color: white;
            background: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-light {
            color: var(--primary-dark);
            background: var(--primary-soft);
        }

        .page {
            max-width: 1500px;
            margin: 0 auto;
            padding: 28px clamp(14px, 3vw, 42px) 36px;
        }

        .intro {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .intro h2 {
            margin: 7px 0 8px;
            font-size: clamp(25px, 3.2vw, 36px);
            line-height: 1.12;
            letter-spacing: -1.2px;
        }

        .intro p {
            margin: 0;
            max-width: 650px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.65;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 16px 18px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 6px 20px
                rgba(16, 75, 67, .035);
        }

        .summary-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 21px;
            flex-shrink: 0;
        }

        .map-panel {
            overflow: hidden;
            background: white;
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow);
        }

        .map-toolbar {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--border);
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter-btn {
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: #f7fbfa;
            color: #536a67;
            font-size: 12px;
            font-weight: 750;
        }

        .filter-btn.active {
            color: white;
            background: var(--primary);
            border-color: var(--primary);
        }

        .map-wrap {
            padding: 12px;
            background: #f8fbfa;
        }

        #map {
            height: min(64vh, 690px);
            min-height: 390px;
            width: 100%;
            border-radius: 16px;
        }

        @media (max-width: 820px) {
            .intro {
                align-items: flex-start;
                flex-direction: column;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .map-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 520px) {
            .page {
                padding: 22px 12px 28px;
            }

            .filter-btn {
                flex: 1 1 calc(50% - 8px);
            }

            #map {
                min-height: 440px;
                height: 58vh;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="mapa.php"
           style="text-decoration:none;color:inherit">
            <div class="brand-mark">✚</div>
            <div>
                <h1>Saúde-Conecta</h1>
                <p>Saúde mais próxima de você · Patos/PB</p>
            </div>
        </a>

        <div class="top-actions">
            <span>
                Olá, <?= e($nomeUsuario) ?>
            </span>

            <a class="btn btn-light"
               href="../pages/perfil_saude.php">
                ♡ Meu perfil
            </a>

            <a class="btn btn-primary"
               href="../pages/logout.php">
                ↪ Sair
            </a>
        </div>
    </header>

    <main class="page">
        <section class="intro">
            <div>
                <div class="eyebrow">Encontre atendimento</div>
                <h2>
                    Cuide da sua saúde<br>
                    com mais facilidade.
                </h2>
                <p>
                    Encontre unidades de saúde em Patos,
                    confira informações importantes e veja
                    no mapa onde buscar atendimento.
                </p>
            </div>
            <div class="intro-badge">
                📍 Unidades de saúde de Patos/PB
            </div>
        </section>

        <section class="summary">
            <article class="summary-card">
                <div class="summary-icon">🏥</div>
                <div>
                    <strong>Hospitais e maternidades</strong>
                    <span>Atendimento especializado</span>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon">🩺</div>
                <div>
                    <strong>UBS e postos de saúde</strong>
                    <span>Cuidados básicos e acompanhamento</span>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon">🚑</div>
                <div>
                    <strong>Atendimento de urgência</strong>
                    <span>Localize unidades de emergência</span>
                </div>
            </article>
        </section>

        <section class="map-panel">
            <div class="map-toolbar">
                <div>
                    <h3>Mapa de unidades de saúde</h3>
                    <p>
                        Selecione uma categoria para filtrar
                        os pontos exibidos.
                    </p>
                </div>

                <div class="filters">
                    <button class="filter-btn active"
                            onclick="filtrar('todos', this)">
                        Todas
                    </button>
                    <button class="filter-btn"
                            onclick="filtrar('hospital', this)">
                        🏥 Hospitais
                    </button>
                    <button class="filter-btn"
                            onclick="filtrar('maternidade', this)">
                        👶 Maternidades
                    </button>
                    <button class="filter-btn"
                            onclick="filtrar('ubs', this)">
                        🩺 UBS e postos
                    </button>
                    <button class="filter-btn"
                            onclick="filtrar('emergencia', this)">
                        🚑 Emergência
                    </button>
                </div>
            </div>

            <div class="map-wrap">
                <div id="map"></div>
            </div>

            <div class="map-note">
                Confirme os dados da unidade antes de se deslocar.
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Mantenha aqui o JavaScript completo do mapa,
         com as unidades, marcadores e filtros incluídos
         no arquivo para download. -->
</body>
</html>