<?php
// JARVIS | NextCore: Digital Solutions
// /includes/header_security.php (v3.0 - RBAC unificado + Primeiro Login + retrocompat)

// 1) Sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Normaliza papéis legados e garante valores válidos.
 * - 'colaborador'  -> 'colaborador_escola' (retrocompat)
 */
function nc_normalize_role(): void {
    if (!empty($_SESSION['usuario_cargo']) && $_SESSION['usuario_cargo'] === 'colaborador') {
        $_SESSION['usuario_cargo'] = 'colaborador_escola';
    }
}
nc_normalize_role();

/** Papéis suportados no sistema */
const NEXTCORE_ROLES = [
    'admin',
    'colaborador_escola',   // insere frequência (manual) por escola
    'colaborador_nre',      // insere REDs
    'colaborador_nre_freq', // revisa/edita frequência
    'view'
];

/** Utilitários */
function current_user_role(): string {
    return $_SESSION['usuario_cargo'] ?? 'view';
}
function is_logged_in(): bool {
    return !empty($_SESSION['usuario_id']);
}

/**
 * check_auth
 * - Sem parâmetro: apenas exige login (e aplica trava de primeiro login).
 * - Com string|array: exige que o cargo atual esteja na lista.
 *   Ex.: check_auth(['admin','colaborador_nre']);
 */
function check_auth($required_role = null): void
{
    // 2) Exige login
    if (!is_logged_in()) {
        $_SESSION['auth_error'] = 'Faça login para continuar.';
        header('Location: login.php');
        exit;
    }

    // 3) Trava de primeiro login (força mudar senha)
    $script_atual = basename($_SERVER['PHP_SELF']);
    $paginas_permitidas_primeiro_login = ['mudar_senha.php', 'logout.php', 'login.php'];

    if (!empty($_SESSION['primeiro_login_pendente']) && $_SESSION['primeiro_login_pendente'] === true) {
        if (!in_array($script_atual, $paginas_permitidas_primeiro_login, true)) {
            header('Location: mudar_senha.php');
            exit;
        }
    }

    // 4) RBAC (quando requerido)
    if ($required_role !== null) {
        $role_atual = current_user_role();
        $roles_validos = is_array($required_role) ? $required_role : [$required_role];

        // Segurança extra: normaliza entradas vazias
        $roles_validos = array_values(array_filter($roles_validos, fn($r) => is_string($r) && $r !== ''));

        if (!in_array($role_atual, $roles_validos, true)) {
            $_SESSION['auth_error'] = 'Acesso negado: você não tem permissão para acessar este módulo.';
            header('Location: metricas.php');
            exit;
        }
    }
}
