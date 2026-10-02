const API_BASE_URL = 'http://localhost/saude-conecta/backend/public';

export const checkApiStatus = async () => {
  try {
    const response = await fetch(`${API_BASE_URL}/api/status`);
    if (!response.ok) throw new Error('Erro ao conectar com o servidor');
    return await response.json();
  } catch (error) {
    console.error('Erro na API:', error);
    return null;
  }
};