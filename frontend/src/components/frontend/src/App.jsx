import { useState } from 'react';
import Login from './pages/Login';
import Register from './pages/Register';
import MapaSaude from './components/MapaSaude';

export default function App() {
  const [currentPage, setCurrentPage] = useState('mapa'); // Alterado temporariamente para ver o mapa

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      {/* Navbar Superior */}
      <header className="bg-emerald-600 text-white px-6 py-4 flex items-center justify-between shadow-md">
        <div className="flex items-center gap-2 cursor-pointer" onClick={() => setCurrentPage('mapa')}>
          <span className="text-2xl">🏥</span>
          <h1 className="font-bold text-lg">Saúde Conecta — Patos/PB</h1>
        </div>
        <nav className="flex gap-4 text-sm font-medium">
          <button onClick={() => setCurrentPage('mapa')} className="hover:underline">Mapa</button>
          <button onClick={() => setCurrentPage('login')} className="bg-white text-emerald-700 px-3 py-1.5 rounded-lg hover:bg-emerald-50">Entrar</button>
        </nav>
      </header>

      {/* Renderização condicional das páginas */}
      <main className="flex-1">
        {currentPage === 'mapa' && <MapaSaude />}
        {currentPage === 'login' && <Login onSwitchToRegister={() => setCurrentPage('register')} />}
        {currentPage === 'register' && <Register onSwitchToLogin={() => setCurrentPage('login')} />}
      </main>
    </div>
  );
}