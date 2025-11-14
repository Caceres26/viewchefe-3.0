<?php
// JARVIS | NextCore: Digital Solutions
// mudar_senha.php - Página Obrigatória de Mudança de Palavra-passe (Primeiro Acesso)

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
// Apenas exige login (check_auth cuidará do redirecionamento se não for 1º acesso)
check_auth(); 

// Garante que só quem PRECISA mudar aceda aqui
if (!isset($_SESSION['primeiro_login_pendente']) || $_SESSION['primeiro_login_pendente'] !== true) {
     // Se alguém tentar aceder diretamente sem a flag, vai para o dashboard
     header("Location: metricas.php");
     exit;
}

// 2. INCLUSÃO DA CONEXÃO
require_once 'db.php'; 
$erro_db = null;
$feedback_mensagem = [];
$user_id = $_SESSION['usuario_id']; // ID do utilizador logado

// 3. LÓGICA DE ATUALIZAÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validações
    if (empty($nova_senha) || empty($confirmar_senha)) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "Ambos os campos de palavra-passe são obrigatórios."];
    } elseif ($nova_senha !== $confirmar_senha) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "As palavras-passe não coincidem."];
    } elseif (strlen($nova_senha) < 8) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "A nova palavra-passe deve ter no mínimo 8 caracteres."];
    } else {
        // Validações passaram, tenta atualizar
        try {
            if (!isset($pdo)) { throw new Exception("Conexão DB falhou."); }

            // Gera o Hash Seguro
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            // Executa a atualização E MARCA como 'não precisa mais mudar' (precisa_mudar_senha = 0)
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, precisa_mudar_senha = 0 WHERE id = ?");
            $stmt->execute([$nova_senha_hash, $user_id]);

            // Remove a flag temporária da sessão
            unset($_SESSION['primeiro_login_pendente']);

            // Define feedback na sessão para o dashboard
            $_SESSION['feedback_global'] = ['type' => 'success', 'text' => "Palavra-passe atualizada com sucesso! Bem-vindo(a)."];
            header("Location: metricas.php"); // Redireciona para o Dashboard
            exit;

        } catch (Exception $e) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro: " . $e->getMessage()];
        } catch (PDOException $e) {
            $erro_db = "Erro de DB: C-USER-FIRSTPASS-001.";
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro de PDO: " . $e->getMessage()];
            error_log("Erro em mudar_senha.php: " . $e->getMessage());
        }
    }
}

// 4. INCLUSÃO DA ESTRUTURA VISUAL E EXIBIÇÃO DO FORMULÁRIO
$page_title = "Definir Nova Palavra-passe";
// Usamos um header/footer simplificado ou o mesmo (precisa estar logado)
require_once 'includes/header.php'; 
?>

<div class="row justify-content-center mt-5">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">Primeiro Acesso: Defina a Sua Palavra-passe</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">Por segurança, é necessário que defina uma nova palavra-passe pessoal antes de continuar.</p>

                <?php if ($erro_db): ?>
                    <div class="alert alert-danger"><?= $erro_db; ?></div>
                <?php endif; ?>
                <?php foreach ($feedback_mensagem as $msg): ?>
                    <div class="alert alert-<?= $msg['type']; ?>"><?= htmlspecialchars($msg['text']); ?></div>
                <?php endforeach; ?>

                <form method="POST" action="mudar_senha.php">
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Palavra-passe (mín. 8 caracteres)</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmar_senha" class="form-label">Confirmar Nova Palavra-passe</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Definir Nova Palavra-passe e Continuar</button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
             <a href="logout.php" class="text-secondary">Sair (Logout)</a>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php'; 
?>