<?php
// JARVIS | NextCore: Digital Solutions
// repor_senha.php - Página de Reposição de Palavra-passe

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
// REQUISITO RBAC: APENAS 'admin' pode aceder
check_auth('admin'); 

// 2. INCLUSÃO DA CONEXÃO
require_once 'db.php'; 
$erro_db = null;
$feedback_mensagem = [];
$usuario_para_editar = null;

// 3. CAPTURAR E VALIDAR O ID DO UTILIZADOR (GET)
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    $_SESSION['feedback_usuario'] = ['type' => 'danger', 'text' => "ID de utilizador inválido para repor palavra-passe."];
    header("Location: usuarios.php");
    exit;
}

// 4. LÓGICA DE ATUALIZAÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha_clara = $_POST['nova_senha'];

    // Validação da Nova Palavra-passe
    if (empty($nova_senha_clara)) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "O campo 'Nova Palavra-passe' não pode estar vazio."];
    } elseif (strlen($nova_senha_clara) < 8) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "A nova palavra-passe deve ter no mínimo 8 caracteres."];
    } else {
        try {
            if (!isset($pdo)) { throw new Exception("Conexão DB falhou."); }

            // Gera o Hash Seguro
            $nova_senha_hash = password_hash($nova_senha_clara, PASSWORD_DEFAULT);

            // Executa a atualização
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->execute([$nova_senha_hash, $user_id]);

            // Define feedback na sessão e redireciona
            $_SESSION['feedback_usuario'] = ['type' => 'success', 'text' => "Palavra-passe do utilizador ID: {$user_id} reposta com sucesso."];
            header("Location: usuarios.php");
            exit;

        } catch (Exception $e) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro: " . $e->getMessage()];
        } catch (PDOException $e) {
            $erro_db = "Erro de DB: C-USER-RESETPASS-001.";
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro de PDO: " . $e->getMessage()];
            error_log("Erro em repor_senha.php: " . $e->getMessage());
        }
    }
}

// 5. BUSCAR NOME DO UTILIZADOR PARA EXIBIR (GET)
// Executa apenas se não for um POST bem-sucedido
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($feedback_mensagem)) {
    try {
        if (!isset($pdo)) { throw new Exception("Conexão DB falhou."); }

        $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario_para_editar = $stmt->fetch();

        if (!$usuario_para_editar) {
            $_SESSION['feedback_usuario'] = ['type' => 'danger', 'text' => "Utilizador ID: {$user_id} não encontrado."];
            header("Location: usuarios.php");
            exit;
        }

    } catch (Exception $e) {
         $erro_db = "Erro ao buscar dados do utilizador: " . $e->getMessage();
    } catch (PDOException $e) {
        $erro_db = "Erro de DB ao buscar utilizador: C-USER-FIND-001.";
        error_log("Erro em repor_senha.php (Find User): " . $e->getMessage());
    }
}

// 6. INCLUSÃO DA ESTRUTURA VISUAL E EXIBIÇÃO DO FORMULÁRIO
$page_title = "Repor Palavra-passe";
require_once 'includes/header.php'; 
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="usuarios.php">Gestão de Utilizadores</a></li>
                <li class="breadcrumb-item active" aria-current="page">Repor Palavra-passe</li>
            </ol>
        </nav>
        
        <h1 class="mb-4">Repor Palavra-passe</h1>

        <?php if ($erro_db): ?>
            <div class="alert alert-danger"><?= $erro_db; ?></div>
        <?php endif; ?>

        <?php foreach ($feedback_mensagem as $msg): ?>
            <div class="alert alert-<?= $msg['type']; ?>"><?= htmlspecialchars($msg['text']); ?></div>
        <?php endforeach; ?>

        <?php if ($usuario_para_editar && empty($erro_db)): // Só exibe o formulário se o utilizador foi encontrado ?>
            <div class="card shadow-sm">
                 <div class="card-header">
                    Repondo palavra-passe para: <strong><?= htmlspecialchars($usuario_para_editar['usuario']); ?></strong> (ID: <?= $user_id ?>)
                </div>
                <div class="card-body">
                    <form method="POST" action="repor_senha.php?id=<?= $user_id; ?>">
                        <input type="hidden" name="action" value="repor_senha"> 
                        <div class="mb-3">
                            <label for="nova_senha_input" class="form-label">Digite a Nova Palavra-passe (mín. 8 caracteres):</label>
                            <input type="password" class="form-control" id="nova_senha_input" name="nova_senha" required>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-info">Confirmar Reposição</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif (!$erro_db) : ?>
             <div class="alert alert-warning">Não foi possível carregar os dados do utilizador para edição.</div>
             <a href="usuarios.php" class="btn btn-secondary">Voltar para a Lista</a>
        <?php endif; ?>

    </div>
</div>

<?php 
require_once 'includes/footer.php'; 
?>