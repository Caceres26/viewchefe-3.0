<?php
// JARVIS | NextCore: Digital Solutions
// index.php - Página de Acesso Visual (Splash Page Manual)

// Verificamos a sessão para o caso de o usuário já estar logado
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se o usuário já estiver logado, o index o redireciona direto para o dashboard
// Se não, ele exibe o HTML abaixo.
if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
    header("Location: metricas.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso ao Sistema | NextCore: Solução Educacional</title>
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #004aad, #00b4d8);
            color: #fff;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-family: "Inter", sans-serif;
        }
        h1 { font-size: 1.8rem; margin-bottom: 1rem; }
        p { opacity: 0.85; }
        .btn-light {
            font-weight: bold;
            padding: 0.75rem 1.5rem;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <h1>Bem-vindo ao Sistema de Métricas Educacionais</h1>
    
    <p>Acesse o painel para gerenciar os indicadores de desempenho.</p>

    <a href="login.php" class="btn btn-light mt-4">Acessar o Sistema</a>
</body>
</html>