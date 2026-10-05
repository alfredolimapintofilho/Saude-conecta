import { useState } from 'react';
import Login from './pages/Login';
import Register from './pages/Register';
import MapaSaude from './components/MapaSaude';

export default function App() {
  const [currentPage, setCurrentPage] = useState('login'); // 'login' | 'register' | 'mapa'
  const [usuario, setUsuario] = useState(null);

  // Quando o login for bem-sucedido
  const handleLoginSuccess = (dadosUsuario) => {
    setUsuario(dadosUsuario);
    setCurrentPage('mapa');
  };

  // Logout
  const handleLogout = () => {
    setUsuario(null);
    setCurrentPage('login');
  };

  if (currentPage === 'login') {
    return (
      <Login
        onSwitchToRegister={() => setCurrentPage('register')}
        onLoginSuccess={handleLoginSuccess}
      />
    );
  }

  if (currentPage === 'register') {
    return (
      <Register
        onSwitchToLogin={() => setCurrentPage('login')}
        onRegisterSuccess={() => setCurrentPage('login')}
      />
    );
  }

  return (
    <div className="h-screen flex flex-col">
      <header className="bg-emerald-700 text-white px-4 py-3 flex items-center justify-between shadow">
        <div>
          <h1 className="font-bold text-lg">🏥 Saúde Conecta</h1>
          <p className="text-xs text-emerald-100">
            Olá, {usuario?.nome || 'Cidadão'}
          </p>
        </div>
        <button
          onClick={handleLogout}
          className="bg-emerald-800 hover:bg-emerald-900 px-3 py-1.5 rounded text-sm"
        >
          Sair
        </button>
      </header>
      <div className="flex-1">
        <MapaSaude />
      </div>
    </div>
  );
}