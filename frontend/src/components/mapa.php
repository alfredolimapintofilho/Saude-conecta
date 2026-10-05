<?php
session_start();

// Protege a página: só entra se estiver logado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mapa de Saúde - Saúde Conecta</title>

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    #map {
      height: calc(100vh - 64px);
      width: 100%;
    }
    .leaflet-popup-content {
      margin: 10px 14px;
      font-size: 14px;
    }
  </style>
</head>
<body class="bg-slate-100">

  <!-- Cabeçalho -->
  <header class="bg-emerald-700 text-white px-4 py-3 flex items-center justify-between shadow-md">
    <div>
      <h1 class="font-bold text-lg">🏥 Saúde Conecta</h1>
      <p class="text-xs text-emerald-100">
        Olá, <?= htmlspecialchars($usuario['nome']) ?>
      </p>
    </div>

    <div class="flex items-center gap-3">
      <span class="text-sm hidden sm:inline"><?= htmlspecialchars($usuario['email']) ?></span>
      <a href="logout.php"
         class="bg-emerald-800 hover:bg-emerald-900 px-4 py-1.5 rounded text-sm font-medium transition">
        Sair
      </a>
    </div>
  </header>

  <!-- Filtros -->
  <div class="bg-white border-b px-4 py-3 flex flex-wrap gap-2 items-center">
    <span class="text-sm font-medium text-slate-600 mr-2">Filtrar:</span>

    <button onclick="filtrar('todos')" class="filtro-btn px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-600 text-white">
      Todos
    </button>
    <button onclick="filtrar('hospital')" class="filtro-btn px-3 py-1.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 hover:bg-slate-200">
      🏥 Hospitais
    </button>
    <button onclick="filtrar('maternidade')" class="filtro-btn px-3 py-1.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 hover:bg-slate-200">
      👶 Maternidades
    </button>
    <button onclick="filtrar('ubs')" class="filtro-btn px-3 py-1.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 hover:bg-slate-200">
      🩺 UBS e Postos
    </button>
    <button onclick="filtrar('emergencia')" class="filtro-btn px-3 py-1.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 hover:bg-slate-200">
      🚑 Emergência
    </button>
  </div>

  <!-- Mapa -->
  <div id="map"></div>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script>
    // Dados das unidades de saúde de Patos-PB
    const unidades = [
      {
        id: 1,
        nome: 'Hospital Regional de Patos (Dep. Janduhy Carneiro)',
        tipo: 'hospital',
        endereco: 'R. Belmiro Lira, s/n - Alto da Tubiba',
        telefone: '(83) 3423-2200',
        horario: '24 horas',
        lat: -7.0351,
        lng: -37.2835
      },
      {
        id: 2,
        nome: 'Maternidade Dr. Peregrino Filho',
        tipo: 'maternidade',
        endereco: 'R. Elias Asfora, s/n - Maternidade',
        telefone: '(83) 3421-2300',
        horario: '24 horas',
        lat: -7.0189,
        lng: -37.2712
      },
      {
        id: 3,
        nome: 'UBS Maria José de Vasconcelos',
        tipo: 'ubs',
        endereco: 'Bairro Jatobá',
        telefone: '(83) 3421-1000',
        horario: '07:00 - 17:00',
        lat: -7.0300,
        lng: -37.2750
      },
      {
        id: 4,
        nome: 'UBS Centro',
        tipo: 'ubs',
        endereco: 'Centro - Patos',
        telefone: '(83) 3421-2000',
        horario: '07:00 - 17:00',
        lat: -7.0245,
        lng: -37.2800
      },
      {
        id: 5,
        nome: 'UPA 24h de Patos',
        tipo: 'emergencia',
        endereco: 'Av. Principal',
        telefone: '(83) 3423-3000',
        horario: '24 horas',
        lat: -7.0280,
        lng: -37.2900
      }
    ];

    // Inicializa o mapa
    const map = L.map('map').setView([-7.0245, -37.2800], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Ícone personalizado
    const icon = L.icon({
      iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34]
    });

    let markers = [];

    // Função para criar popup
    function criarPopup(unidade) {
      return `
        <div>
          <h3 class="font-bold text-slate-800 mb-1">${unidade.nome}</h3>
          <p class="text-xs text-slate-600 mb-1">📍 ${unidade.endereco}</p>
          <p class="text-xs text-slate-600 mb-1">📞 ${unidade.telefone}</p>
          <p class="text-xs text-slate-600 mb-3">🕒 ${unidade.horario}</p>
          <button onclick="alert('Agendamento para: ${unidade.nome}')"
                  class="w-full bg-emerald-600 text-white text-xs font-semibold py-1.5 rounded hover:bg-emerald-700">
            Ver Especialidades / Agendar
          </button>
        </div>
      `;
    }

    // Função para mostrar os marcadores
    function mostrarUnidades(filtro = 'todos') {
      // Remove marcadores antigos
      markers.forEach(m => map.removeLayer(m));
      markers = [];

      const filtradas = filtro === 'todos'
        ? unidades
        : unidades.filter(u => u.tipo === filtro);

      filtradas.forEach(unidade => {
        const marker = L.marker([unidade.lat, unidade.lng], { icon })
          .addTo(map)
          .bindPopup(criarPopup(unidade));

        markers.push(marker);
      });
    }

    // Filtro
    function filtrar(tipo) {
      // Atualiza estilo dos botões
      document.querySelectorAll('.filtro-btn').forEach(btn => {
        btn.classList.remove('bg-emerald-600', 'text-white');
        btn.classList.add('bg-slate-100', 'text-slate-600');
      });
      event.target.classList.remove('bg-slate-100', 'text-slate-600');
      event.target.classList.add('bg-emerald-600', 'text-white');

      mostrarUnidades(tipo);
    }

    // Carrega todas as unidades ao iniciar
    mostrarUnidades('todos');
  </script>
</body>
</html>