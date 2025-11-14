<?php
// JARVIS | NextCore: Digital Solutions
// editar_cargo.php - v2.0 (RBAC atualizado: colaborador_escola + colaborador_nre_freq)

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
// APENAS 'admin' pode aceder
check_auth('admin'); 

// 2. INCLUSÃO DA CONEXÃO
require_once 'db.php'; 

$erro_db = null;
$feedback_mensagem = [];
$usuario_para_editar = null;

$current_admin_id = $_SESSION['usuario_id'] ?? 0;

// Lista de papéis válidos (inclui novo RBAC e papel legado “colaborador” para retrocompatibilidade)
$allowed_roles = ['admin', 'view', 'colaborador_escola', 'colaborador_nre', 'colaborador_nre_freq', 'colaborador'];

// 3. CAPTURAR E VALIDAR O ID DO UTILIZADOR (GET)
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    $_SESSION['feedback_usuario'] = ['type' => 'danger', 'text' => "ID de utilizador inválido para edição."];
    header("Location: usuarios.php");
    exit;
}

// Proteção: Admin não pode editar a sua própria função nesta página
if ($user_id === $current_admin_id) {
    $_SESSION['feedback_usuario'] = ['type' => 'warning', 'text' => "Não pode editar a sua própria função através desta interface."];
    header("Location: usuarios.php");
    exit;
}

// 4. LÓGICA DE ATUALIZAÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novo_cargo = filter_input(INPUT_POST, 'novo_cargo', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validação do papel selecionado
    if (!in_array($novo_cargo, $allowed_roles, true)) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "Função selecionada inválida."];
    } else {
        try {
            if (!isset($pdo) || !$pdo instanceof PDO) { 
                throw new Exception("Conexão DB falhou."); 
            }

            // Executa a atualização
            $stmt = $pdo->prepare("UPDATE usuarios SET cargo = ? WHERE id = ?");
            $stmt->execute([$novo_cargo, $user_id]);

            // Feedback e redireciona
            $_SESSION['feedback_usuario'] = [
                'type' => 'success', 
                'text' => "Função do utilizador ID {$user_id} atualizada para '{$novo_cargo}'."
            ];
            header("Location: usuarios.php");
            exit;

        } catch (Exception $e) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro: " . $e->getMessage()];
        } catch (PDOException $e) {
            $erro_db = "Erro de DB: C-USER-EDIT-001.";
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro de PDO: " . $e->getMessage()];
            error_log("Erro em editar_cargo.php: " . $e->getMessage());
        }
    }
}

// 5. BUSCAR DADOS DO UTILIZADOR PARA EXIBIR (GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($feedback_mensagem)) {
    try {
        if (!isset($pdo) || !$pdo instanceof PDO) { 
            throw new Exception("Conexão DB falhou."); 
        }

        $stmt = $pdo->prepare("SELECT usuario, cargo FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_para_editar) {
            $_SESSION['feedback_usuario'] = ['type' => 'danger', 'text' => "Utilizador ID {$user_id} não encontrado."];
            header("Location: usuarios.php");
            exit;
        }

    } catch (Exception $e) {
         $erro_db = "Erro ao buscar dados do utilizador: " . $e->getMessage();
    } catch (PDOException $e) {
        $erro_db = "Erro de DB ao buscar utilizador: C-USER-FIND-001.";
        error_log("Erro em editar_cargo.php (Find User): " . $e->getMessage());
    }
}

// 6. INCLUSÃO DA ESTRUTURA VISUAL
$page_title = "Editar Função de Utilizador";
require_once 'includes/header.php'; 

// Helper para marcar selected
function sel($v, $current) { return $v === $current ? 'selected' : ''; }
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="usuarios.php">Gestão de Utilizadores</a></li>
                <li class="breadcrumb-item active" aria-current="page">Editar Função</li>
            </ol>
        </nav>
        
        <h1 class="mb-4">Editar Função</h1>

        <?php if ($erro_db): ?>
            <div class="alert alert-danger"><?= $erro_db; ?></div>
        <?php endif; ?>

        <?php foreach ($feedback_mensagem as $msg): ?>
            <div class="alert alert-<?= $msg['type']; ?>"><?= htmlspecialchars($msg['text']); ?></div>
        <?php endforeach; ?>

        <?php if ($usuario_para_editar && empty($erro_db)): ?>
            <?php $cargo_atual = $usuario_para_editar['cargo'] ?? 'view'; ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    Editando função para: <strong><?= htmlspecialchars($usuario_para_editar['usuario']); ?></strong> (ID: <?= (int)$user_id ?>)
                </div>
                <div class="card-body">
                    <form method="POST" action="editar_cargo.php?id=<?= (int)$user_id; ?>">
                        <input type="hidden" name="action" value="editar_cargo"> 
                        
                        <div class="mb-3">
                            <label for="novo_cargo_select" class="form-label">Selecione a Nova Função (RBAC):</label>
                            <select class="form-select" id="novo_cargo_select" name="novo_cargo" required>
                                <!-- Papéis atuais -->
                                <option value="view" <?= sel('view', $cargo_atual); ?>>
                                    View — Apenas visualiza
                                </option>
                                <option value="colaborador_escola" <?= sel('colaborador_escola', $cargo_atual) . sel('colaborador', $cargo_atual); ?>>
                                    Colaborador Escola — Insere frequência manualmente
                                </option>
                                <option value="colaborador_nre" <?= sel('colaborador_nre', $cargo_atual); ?>>
                                    Colaborador NRE — Insere RED'S
                                </option>
                                <option value="colaborador_nre_freq" <?= sel('colaborador_nre_freq', $cargo_atual); ?>>
                                    Colaborador NRE Freq — Revisão/Edição de frequência
                                </option>
                                <option value="admin" <?= sel('admin', $cargo_atual); ?>>
                                    Admin — Acesso total
                                </option>

                                <!-- Papel legado visível apenas se o utilizador ainda o possuir -->
                                <?php if ($cargo_atual === 'colaborador'): ?>
                                <optgroup label="LEGADO (use Colaborador Escola)">
                                    <option value="colaborador" selected>colaborador (LEGADO)</option>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                Padrão recomendado: <strong>Colaborador Escola</strong> para equipes das escolas; 
                                <strong>Colaborador NRE</strong> para RED'S; 
                                <strong>Colaborador NRE Freq</strong> para revisão/edição de frequência.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-warning">Confirmar Alteração de Função</button>
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
