<?php
// JARVIS | NextCore: Digital Solutions
// logout.php - Encerramento de Sessão Seguro e Enxuto

session_start();

// Limpa todas as variáveis de sessão
$_SESSION = [];

// Destroi o cookie de sessão, se existir
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Destroi a sessão no servidor
session_destroy();

// Redireciona para login com parâmetro opcional
header("Location: login.php?logged_out=1");
exit;
?>
