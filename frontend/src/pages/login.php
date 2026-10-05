<?php
session_start();

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario'])) {
    header('Location: mapa.php'); // ou a página que quiser
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($cpf) || empty($senha)) {
        $erro = 'Preencha o CPF e a senha.';
    } else {
        // Chama a lógica de login (usando o mesmo model)
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../models/Usuario.php';

        $db = Database::getConnection();
        $usuarioModel = new Usuario($db);
        $user = $usuarioModel->buscarPorCpf($cpf);

        if ($user && password_verify($senha, $user['senha_hash'])) {
            // Login bem-sucedido
            $_SESSION['usuario'] = [
                'id' => $user['id'],
                'nome' => $user['nome_completo'],
                'email' => $user['email'],
                'tipo' => $user['tipo_usuario']
            ];

            header('Location: mapa.php'); // Página após login
            exit;
        } else {
            $erro = 'CPF ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Saúde Conecta</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
    }
  </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

  <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-100">
    
    <!-- Cabeçalho -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full mb-3 text-3xl">
        🏥
      </div>
      <h1 class="text-2xl font-bold text-slate-800">Saúde Conecta</h1>
      <p class="text-sm text-slate-500 mt-1">
        "A saúde da cidade na palma da sua mão"
      </p>
    </div>

    <!-- Mensagens de erro/sucesso -->
    <?php if ($erro): ?>
      <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-100">
        <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
      <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg border border-green-100">
        <?= htmlspecialchars($sucesso) ?>
      </div>
    <?php endif; ?>

    <!-- Formulário -->
    <form method="POST" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          CPF do Cidadão
        </label>
        <input
          type="text"
          name="cpf"
          id="cpf"
          placeholder="000.000.000-00"
          required
          maxlength="14"
          class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
          value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>"
        >
      </div>

      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-sm font-medium text-slate-700">
            Senha
          </label>
          <a href="#" class="text-xs text-emerald-600 hover:underline">
            Esqueceu a senha?
          </a>
        </div>
        <div class="relative">
          <input
            type="password"
            name="senha"
            id="senha"
            placeholder="••••••••"
            required
            class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
          >
          <button
            type="button"
            onclick="toggleSenha()"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"
          >
            Mostrar
          </button>
        </div>
      </div>

      <button
        type="submit"
        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg shadow-md transition"
      >
        Entrar
      </button>
    </form>

    <!-- Link para cadastro -->
    <div class="mt-8 text-center border-t border-slate-100 pt-6">
      <p class="text-sm text-slate-600">
        Ainda não tem uma conta?
        <a href="register.php" class="text-emerald-600 font-semibold hover:underline">
          Cadastre-se como cidadão
        </a>
      </p>
    </div>
  </div>

  <script>
    // Máscara de CPF
    document.getElementById('cpf').addEventListener('input', function (e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 11) value = value.slice(0, 11);

      value = value.replace(/(\d{3})(\d)/, '$1.$2');
      value = value.replace(/(\d{3})(\d)/, '$1.$2');
      value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');

      e.target.value = value;
    });

    // Mostrar / ocultar senha
    function toggleSenha() {
      const input = document.getElementById('senha');
      const btn = event.target;
      if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Ocultar';
      } else {
        input.type = 'password';
        btn.textContent = 'Mostrar';
      }
    }
  </script>
</body>
</html>