import { useState } from 'react';
import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Correção para os ícones padrão do Leaflet no React
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';

const defaultIcon = L.icon({
  iconUrl,
  shadowUrl: iconShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
});
L.Marker.prototype.options.icon = defaultIcon;

// Dados de exemplo de Unidades de Saúde em Patos-PB
const UNIDADES_PATOS = [
  {
    id: 1,
    nome: 'Hospital Regional de Patos (Dep. Janduhy Carneiro)',
    tipo: 'hospital',
    endereco: 'R. Belmiro Lira, s/n - Alto da Tubiba',
    telefone: '(83) 3423-2200',
    horario: '24 horas',
    situacao: 'normal',
    lat: -7.0351,
    lng: -37.2835,
  },
  {
    id: 2,
    nome: 'Maternidade Dr. Peregrino Filho',
    tipo: 'maternidade',
    endereco: 'R. Elias Asfora, s/n - Maternidade',
    telefone: '(83) 3421-2300',
    horario: '24 horas',
    situacao: 'normal',
    lat: -7.0189,
    lng: -37.2712,
  },
  {
    id: 3,
    nome: 'UBS Maria José de Vasconcelos',
    tipo: 'ubs',
    endereco: 'Bairro Jatobá',
    telefone: '(83) 3421-1000',
    horario: '07:00 - 17:00',
    situacao: 'normal',
    lat: -7.0420,
    lng: -37.2750,
  },
  {
    id: 4,
    nome: 'PAI - Pronto Atendimento Infantil',
    tipo: 'emergencia',
    endereco: 'Centro, Patos-PB',
    telefone: '(83) 3421-4000',
    horario: '24 horas',
    situacao: 'alterado',
    lat: -7.0225,
    lng: -37.2790,
  },
];

export default function MapaSaude() {
  const [filtroTipo, setFiltroTipo] = useState('todos');

  // Filtragem de marcadores
  const unidadesFiltradas = UNIDADES_PATOS.filter((unidade) => {
    if (filtroTipo === 'todos') return true;
    return unidade.tipo === filtroTipo;
  });

  // Função auxiliar para badge de status
  const getBadgeSituacao = (situacao) => {
    switch (situacao) {
      case 'normal':
        return <span className="text-xs bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded">🟢 Normal</span>;
      case 'alterado':
        return <span className="text-xs bg-amber-100 text-amber-800 font-semibold px-2 py-0.5 rounded">🟡 Atendimento Alterado</span>;
      case 'indisponivel':
        return <span className="text-xs bg-rose-100 text-rose-800 font-semibold px-2 py-0.5 rounded">🔴 Indisponível</span>;
      default:
        return null;
    }
  };

  return (
    <div className="flex flex-col h-[calc(100vh-80px)] w-full">
      {/* Barra de Filtros */}
      <div className="bg-white p-4 shadow-md z-10 border-b border-slate-200 flex flex-wrap items-center gap-3">
        <span className="font-semibold text-slate-700 text-sm">Filtrar por:</span>
        <button
          onClick={() => setFiltroTipo('todos')}
          className={`px-3 py-1.5 rounded-full text-xs font-medium transition ${
            filtroTipo === 'todos' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          Todos
        </button>
        <button
          onClick={() => setFiltroTipo('hospital')}
          className={`px-3 py-1.5 rounded-full text-xs font-medium transition ${
            filtroTipo === 'hospital' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          🏥 Hospitais
        </button>
        <button
          onClick={() => setFiltroTipo('maternidade')}
          className={`px-3 py-1.5 rounded-full text-xs font-medium transition ${
            filtroTipo === 'maternidade' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          👶 Maternidades
        </button>
        <button
          onClick={() => setFiltroTipo('ubs')}
          className={`px-3 py-1.5 rounded-full text-xs font-medium transition ${
            filtroTipo === 'ubs' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          🩺 UBS e Postos
        </button>
        <button
          onClick={() => setFiltroTipo('emergencia')}
          className={`px-3 py-1.5 rounded-full text-xs font-medium transition ${
            filtroTipo === 'emergencia' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          🚑 Emergência
        </button>
      </div>

      {/* Renderização do Mapa */}
      <div className="flex-1 w-full relative z-0">
        <MapContainer
          center={[-7.0245, -37.2800]} // Coordenadas centrais de Patos - PB
          zoom={14}
          scrollWheelZoom={true}
          className="h-full w-full"
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />

          {unidadesFiltradas.map((unidade) => (
            <Marker key={unidade.id} position={[unidade.lat, unidade.lng]}>
              <Popup>
                <div className="p-1 max-w-xs">
                  <h3 className="font-bold text-slate-800 text-sm mb-1">{unidade.nome}</h3>
                  <div className="mb-2">{getBadgeSituacao(unidade.situacao)}</div>
                  <p className="text-xs text-slate-600 mb-1">📍 {unidade.endereco}</p>
                  <p className="text-xs text-slate-600 mb-1">📞 {unidade.telefone}</p>
                  <p className="text-xs text-slate-600 mb-3">🕒 {unidade.horario}</p>
                  <button
                    onClick={() => alert(`Agendamento para ${unidade.nome}`)}
                    className="w-full bg-emerald-600 text-white text-xs font-semibold py-1.5 rounded hover:bg-emerald-700 transition"
                  >
                    Ver Especialidades / Agendar
                  </button>
                </div>
              </Popup>
            </Marker>
          ))}
        </MapContainer>
      </div>
    </div>
  );
}