<?php
// JARVIS | NextCore: Digital Solutions
// excluir_usuario.php - Página de Confirmação e Exclusão de Utilizador

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
// REQUISITO RBAC: APENAS 'admin' pode aceder
check_auth('admin'); 

// 2. INCLUSÃO DA CONEXÃO
require_once 'db.php'; 
$erro_db = null;
$feedback_mensagem = [];
$usuario_para_excluir = null;
$current_admin_id = $_SESSION['usuario_id'] ?? 0;

// 3. CAPTURAR E VALIDAR O ID DO UTILIZADOR (GET)
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    $_SESSION['feedback_usuario'] = ['type' => 'danger', 'text' => "ID de utilizador inválido para exclusão."];
    header("Location: usuarios.php");
    exit;
}

// Proteção Crítica: Admin não pode excluir a própria conta
if ($user_id === $current_admin_id) {
    $_SESSION['feedback_usuario'] = ['type' => 'danger', 'text' => "Não pode excluir a sua própria conta de administrador."];
    header("Location: usuarios.php");
    exit;
}

// 4. LÓGICA DE EXCLUSÃO (POST - APÓS CONFIRMAÇÃO)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao'])) {
    
    try {
        if (!isset($pdo)) { throw new Exception("Conexão DB falhou."); }

        // Executa a exclusão
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);

        // Verifica se alguma linha foi afetada (garante que o ID existia)
        if ($stmt->rowCount() > 0) {
            $_SESSION['feedback_usuario'] = ['type' => 'success', 'text' => "Utilizador ID: {$user_id} excluído com sucesso."];
        } else {
             $_SESSION['feedback_usuario'] = ['type' => 'warning', 'text' => "Utilizador ID: {$user_id} não encontrado ou já excluído."];
        }
        header("Location: usuarios.php");
        exit;

    } catch (Exception $e) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro: " . $e->getMessage()];
    } catch (PDOException $e) {
        $erro_db = "Erro de DB: C-USER-DELETE-001.";
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro de PDO: " . $e->getMessage()];
        error_log("Erro em excluir_usuario.php: " . $e->getMessage());
    }
}

// 5. BUSCAR NOME DO UTILIZADOR PARA EXIBIR NA CONFIRMAÇÃO (GET)
// Executa apenas se não for um POST bem-sucedido
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($feedback_mensagem)) {
    try {
        if (!isset($pdo)) { throw new Exception("Conexão DB falhou."); }

        $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario_para_excluir = $stmt->fetch();

        if (!$usuario_para_excluir) {
            $_SESSION['feedback_usuario'] = ['type' => 'danger', 'text' => "Utilizador ID: {$user_id} não encontrado para exclusão."];
            header("Location: usuarios.php");
            exit;
        }

    } catch (Exception $e) {
         $erro_db = "Erro ao buscar dados do utilizador: " . $e->getMessage();
    } catch (PDOException $e) {
        $erro_db = "Erro de DB ao buscar utilizador: C-USER-FIND-001.";
        error_log("Erro em excluir_usuario.php (Find User): " . $e->getMessage());
    }
}

// 6. INCLUSÃO DA ESTRUTURA VISUAL E EXIBIÇÃO DA CONFIRMAÇÃO
$page_title = "Confirmar Exclusão";
require_once 'includes/header.php'; 
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="usuarios.php">Gestão de Utilizadores</a></li>
                <li class="breadcrumb-item active" aria-current="page">Confirmar Exclusão</li>
            </ol>
        </nav>
        
        <h1 class="mb-4 text-danger">Confirmar Exclusão</h1>

        <?php if ($erro_db): ?>
            <div class="alert alert-danger"><?= $erro_db; ?></div>
        <?php endif; ?>

        <?php foreach ($feedback_mensagem as $msg): ?>
            <div class="alert alert-<?= $msg['type']; ?>"><?= htmlspecialchars($msg['text']); ?></div>
        <?php endforeach; ?>

        <?php if ($usuario_para_excluir && empty($erro_db)): // Só exibe o formulário se o utilizador foi encontrado ?>
            <div class="card shadow-sm border-danger">
                 <div class="card-header bg-danger text-white">
                    Confirmação Necessária
                </div>
                <div class="card-body">
                    <p class="fs-5"><strong>Atenção:</strong> Esta ação é <strong>irreversível</strong>.</p>
                    <p>Tem a certeza absoluta que deseja excluir permanentemente o utilizador:</p> 
                    <p class="text-center fs-4 my-3">
                        <strong><?= htmlspecialchars($usuario_para_excluir['usuario']); ?></strong> (ID: <?= $user_id ?>)
                    </p>
                    
                    <form method="POST" action="excluir_usuario.php?id=<?= $user_id; ?>">
                        <input type="hidden" name="action" value="excluir_usuario"> 
                        <input type="hidden" name="confirmar_exclusao" value="1"> <div class="d-flex justify-content-between mt-4">
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-danger">Sim, Excluir Permanentemente</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif (!$erro_db) : ?>
             <div class="alert alert-warning">Não foi possível carregar os dados do utilizador para exclusão.</div>
             <a href="usuarios.php" class="btn btn-secondary">Voltar para a Lista</a>
        <?php endif; ?>

    </div>
</div>

<?php 
require_once 'includes/footer.php'; 
?>