<?php
session_start();

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario'])) {
    header('Location: mapa.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/models/Usuario.php';

    $cpf            = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $nomeCompleto   = trim($_POST['nomeCompleto'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $telefone       = trim($_POST['telefone'] ?? '');
    $dataNascimento = $_POST['dataNascimento'] ?? '';
    $cartaoSus      = trim($_POST['cartaoSus'] ?? '');
    $senha          = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmarSenha'] ?? '';
    $aceitaLgpd     = isset($_POST['aceitaLgpd']);

    // Validações básicas
    if (empty($cpf) || empty($nomeCompleto) || empty($email) || empty($telefone) || empty($dataNascimento) || empty($senha)) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif (strlen($cpf) !== 11) {
        $erro = 'CPF inválido.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres.';
    } elseif (!$aceitaLgpd) {
        $erro = 'Você precisa aceitar os termos da LGPD para se cadastrar.';
    } else {
        $db = Database::getConnection();
        $usuario = new Usuario($db);

        // Verifica se CPF ou e-mail já existem
        if ($usuario->cpfExiste($cpf)) {
            $erro = 'Este CPF já está cadastrado.';
        } elseif ($usuario->emailExiste($email)) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            $dados = [
                'cpf'            => $cpf,
                'nomeCompleto'   => $nomeCompleto,
                'email'          => $email,
                'telefone'       => $telefone,
                'dataNascimento' => $dataNascimento,
                'cartaoSus'      => $cartaoSus ?: null,
                'senha'          => $senha,
                'aceitaLgpd'     => true
            ];

            $novoId = $usuario->criar($dados);

            if ($novoId) {
                $sucesso = 'Cadastro realizado com sucesso! Você já pode fazer login.';
                $_POST = []; // limpa o formulário
            } else {
                $erro = 'Erro ao cadastrar. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro - Saúde Conecta</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

  <div class="max-w-xl w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-100 my-8">

    <!-- Cabeçalho -->
    <div class="text-center mb-6">
      <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full mb-3 text-2xl">
        🏥
      </div>
      <h1 class="text-2xl font-bold text-slate-800">Criar Conta Cidadão</h1>
      <p class="text-sm text-slate-500 mt-1">
        Preencha seus dados para acessar as unidades de saúde de Patos-PB
      </p>
    </div>

    <!-- Mensagens -->
    <?php if ($erro): ?>
      <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-100">
        <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
      <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg border border-green-100">
        <?= htmlspecialchars($sucesso) ?>
        <br>
        <a href="login.php" class="underline font-medium">Clique aqui para fazer login</a>
      </div>
    <?php endif; ?>

    <!-- Formulário -->
    <form method="POST" class="space-y-4">

      <!-- Nome Completo -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nome Completo *</label>
        <input type="text" name="nomeCompleto" required
               value="<?= htmlspecialchars($_POST['nomeCompleto'] ?? '') ?>"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none">
      </div>

      <!-- CPF + Data de Nascimento -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">CPF *</label>
          <input type="text" name="cpf" id="cpf" required maxlength="14"
                 placeholder="000.000.000-00"
                 value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>"
                 class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Data de N