<?php
// JARVIS | NextCore: Digital Solutions
// login.php - Módulo de Autenticação (v2.2 - Força Mudança de Senha)

// Inicia a sessão no topo absoluto do script
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php'; // Inclui a conexão $pdo

$mensagem_erro = '';

// Se o usuário já estiver logado, redireciona para o dashboard
if (isset($_SESSION['usuario_id'])) {
    // Exceção: Se precisar mudar senha, deixa-o tentar logar de novo
    if (!isset($_SESSION['primeiro_login_pendente'])) {
        header("Location: metricas.php");
        exit;
    }
}

// 2. PROCESSAMENTO DO FORMULÁRIO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_usuario_form = filter_input(INPUT_POST, 'usuario', FILTER_SANITIZE_SPECIAL_CHARS);
    $senha_digitada = $_POST['senha'];

    if (empty($nome_usuario_form) || empty($senha_digitada)) {
        $mensagem_erro = "Preencha todos os campos.";
    } else {
        try {
            // --- ALTERAÇÃO AQUI: Busca a coluna 'precisa_mudar_senha' ---
            $stmt = $pdo->prepare("SELECT id, usuario, senha, cargo, precisa_mudar_senha FROM usuarios WHERE usuario = ?");
            $stmt->execute([$nome_usuario_form]);
            $usuario_db = $stmt->fetch();
            // --- FIM DA ALTERAÇÃO ---

            // 2.3. Verificação de Credenciais
            if ($usuario_db && password_verify($senha_digitada, $usuario_db['senha'])) {
                
                session_regenerate_id(true); 

                $_SESSION['usuario_id'] = $usuario_db['id'];
                $_SESSION['usuario_nome'] = $usuario_db['usuario'];
                $_SESSION['usuario_cargo'] = $usuario_db['cargo'];

                // --- ALTERAÇÃO AQUI: Redirecionamento Condicional ---
                if ($usuario_db['precisa_mudar_senha'] == 1) {
                    $_SESSION['primeiro_login_pendente'] = true; 
                    header("Location: mudar_senha.php");
                    exit;
                } else {
                    unset($_SESSION['primeiro_login_pendente']); 
                    header("Location: metricas.php");
                    exit;
                }
                // --- FIM DA ALTERAÇÃO ---

            } else {
                $mensagem_erro = "Credenciais inválidas. Tente novamente.";
            }

        } catch (PDOException $e) {
            error_log("Erro de Login DB: " . $e->getMessage()); 
            $mensagem_erro = "Erro interno no sistema. Tente novamente. Código: C-LOG-001";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Restrito | NextCore: Sistema de Métricas</title>
    <script>
        (function () {
            const storageKey = 'nextcore-theme';
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            let storageAvailable = true;
            try {
                localStorage.setItem('__t', '1');
                localStorage.removeItem('__t');
            } catch (e) {
                storageAvailable = false;
            }

            const getStored = () => storageAvailable ? localStorage.getItem(storageKey) : null;
            const resolve = (value) => (value === 'light' || value === 'dark')
                ? value
                : (mediaQuery.matches ? 'dark' : 'light');

            const apply = (value) => {
                const choice = (value === 'light' || value === 'dark') ? value : 'auto';
                const resolved = resolve(value);
                document.documentElement.setAttribute('data-bs-theme', resolved);
                document.documentElement.setAttribute('data-theme-choice', choice);
            };

            apply(getStored() ?? 'auto');

            const handleSystemChange = () => {
                const choice = document.documentElement.getAttribute('data-theme-choice') || 'auto';
                if (choice === 'auto') {
                    apply('auto');
                }
            };

            mediaQuery.addEventListener
                ? mediaQuery.addEventListener('change', handleSystemChange)
                : mediaQuery.addListener && mediaQuery.addListener(handleSystemChange);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/css/login.css" rel="stylesheet">
</head>
<body class="login-page-body">
    <div class="card login-card shadow-lg p-4" style="width: 100%; max-width: 400px;">
        <h4 class="card-title text-center mb-4">Acesso ao Sistema</h4>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="usuario" class="form-label">Utilizador</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Palavra-passe</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
        <p class="mt-3 text-center text-muted"><small>&copy; <?php echo date('Y'); ?> NextCore: Digital Solutions</small></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
