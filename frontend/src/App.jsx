import { useEffect, useState } from 'react';
import { checkApiStatus } from './services/api';

export default function App() {
  const [apiStatus, setApiStatus] = useState(null);

  useEffect(() => {
    checkApiStatus().then(data => setApiStatus(data));
  }, []);

  return (
    <div style={{ fontFamily: 'sans-serif', padding: '2rem', textAlign: 'center' }}>
      <h1>🏥 Saúde Conecta - Patos/PB</h1>
      <p>A saúde da cidade na palma da sua mão.</p>
      
      <div style={{ marginTop: '2rem', padding: '1rem', border: '1px solid #ccc', borderRadius: '8px' }}>
        <h3>Status da API Backend:</h3>
        {apiStatus ? (
          <p style={{ color: 'green' }}>🟢 {apiStatus.status.toUpperCase()} - {apiStatus.projeto}</p>
        ) : (
          <p style={{ color: 'red' }}>🔴 Desconectado ou carregando...</p>
        )}
      </div>
    </div>
  );
}