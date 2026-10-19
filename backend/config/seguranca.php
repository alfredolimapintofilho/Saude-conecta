<?php
/**
 * Saúde-Conecta — Funções de segurança e sessão
 * Arquivo: backend/config/seguranca.php
 */

/**
 * Inicia a sessão de forma mais segura.
 */
function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    // Em produção com HTTPS, descomente:
    // ini_set('session.cookie_secure', '1');

    session_start();
}

/**
 * Exige que o usuário esteja autenticado.
 * Se não estiver, redireciona para a tela de login.
 */
function exigirLogin(): array
{
    iniciarSessaoSegura();

    if (empty($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }

    return $_SESSION['usuario'];
}

/**
 * Verifica se existe um usuário logado (sem redirecionar).
 */
function estaLogado(): bool
{
    iniciarSessaoSegura();
    return !empty($_SESSION['usuario']) && is_array($_SESSION['usuario']);
}

/**
 * Retorna o usuário da sessão ou null.
 */
function obterUsuarioLogado(): ?array
{
    iniciarSessaoSegura();
    return !empty($_SESSION['usuario']) && is_array($_SESSION['usuario'])
        ? $_SESSION['usuario']
        : null;
}