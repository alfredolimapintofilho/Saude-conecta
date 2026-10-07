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
}

export const loginUsuario = async (credentials) => {
  const response = await fetch(`${API_BASE_URL}/api/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(credentials),
  });
  
  const data = await response.json();
  if (!response.ok) throw new Error(data.message || 'Erro ao realizar login');
  return data;
};

export const cadastrarUsuario = async (userData) => {
  const response = await fetch(`${API_BASE_URL}/api/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(userData),
  });
  
  const data = await response.json();
  if (!response.ok) throw new Error(data.message || 'Erro ao cadastrar cidadão');
  return data;
};