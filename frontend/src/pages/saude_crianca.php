<?php
require_once __DIR__ . '/../../../backend/config/seguranca.php';
iniciarSessaoSegura();
$usuario = exigirLogin();
if (is_object($usuario)) {
    $usuario = (array) $usuario;
}
$nome = $usuario['nome'] ?? $usuario['nome_completo'] ?? 'Usuário';
function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saúde Criança | Saúde-Conecta</title>
    <style>
        :root { --verde: #087f8c; --fundo: #f1f6fa; --texto: #18394e; --borda: #e3ebf1; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font-family: Arial, sans-serif; }
        header { background: #fff; border-bottom: 1px solid var(--borda); padding: 16px 5%; display: flex; justify-content: space-between; align-items: center; }
        .marca { color: var(--verde); font-weight: 800; font-size: 20px; text-decoration: none; }
        main { width: min(900px, 92%); margin: 32px auto; }
        .painel { background: #fff; border: 1px solid var(--borda); border-radius: 16px; padding: 28px; }
        h1 { margin: 0 0 10px; }
        p { color: #60716b; line-height: 1.6; }
        .voltar { display: inline-block; color: var(--verde); font-weight: bold; text-decoration: none; margin-bottom: 18px; }
        .btn { display: inline-block; margin-top: 16px; padding: 12px 16px; background: var(--verde); color: #fff; border-radius: 9px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<header>
    <a class="marca" href="perfil_saude.php">Saúde-Conecta</a>
    <span>Olá, <?= h($nome) ?></span>
</header>
<main>
    <a class="voltar" href="perfil_saude.php">← Voltar ao perfil</a>
    <section class="painel">
        <h1>🧒 Saúde Criança</h1>
        <p>
            Área dedicada ao acompanhamento da saúde infantil: vacinas, crescimento,
            consultas pediátricas e orientações para pais e responsáveis.
        </p>
        <p>
            Em breve você poderá registrar o cartão de vacinação infantil,
            curvas de crescimento e lembretes de consultas.
        </p>
        <a class="btn" href="registros.php?tipo=vacina">Ir para Cartão de Vacinação</a>
        <a class="btn" href="perfil_saude.php" style="background:#e8f2ee;color:#066773;margin-left:8px;">Meu perfil</a>
    </section>
</main>
</body>
</html>