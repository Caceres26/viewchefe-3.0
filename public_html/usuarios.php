<?php
// JARVIS | NextCore: Digital Solutions
// usuarios.php - (v4.0 - RBAC atualizado: colaborador_escola + colaborador_nre_freq)

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
check_auth('admin'); 

// 2. INCLUSÃO DA CONEXÃO E ESTRUTURA
require_once 'db.php'; 
$page_title = "Administração de Utilizadores";
require_once 'includes/header.php'; 

$feedback_mensagem = [];
$erro_db = null;
$current_admin_id = $_SESSION['usuario_id'] ?? 0;

// --- Helpers de exibição ---
function nc_role_display_label(string $cargo): string {
    // normaliza papel legado apenas para exibição
    if ($cargo === 'colaborador') {
        $cargo = 'colaborador_escola';
    }
    // label amigável
    $label = strtoupper(str_replace('_', ' ', $cargo));
    return $label;
}
function nc_role_badge_color(string $cargo): string {
    // mapeia cores incluindo papel legado
    switch ($cargo) {
        case 'admin': return 'danger';
        case 'colaborador':
        case 'colaborador_escola': return 'warning';
        case 'colaborador_nre': return 'info';
        case 'colaborador_nre_freq': return 'primary';
        case 'view':
        default: return 'secondary';
    }
}

// --- 3. LÓGICA DE PROCESSAMENTO (ADICIONAR UTILIZADOR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adicionar_usuario') {
    try {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            throw new Exception("Falha crítica na conexão com a base de dados.");
        }
        
        $usuario = filter_input(INPUT_POST, 'novo_usuario', FILTER_SANITIZE_SPECIAL_CHARS);
        $senha_clara = $_POST['nova_senha'] ?? '';
        $cargo = filter_input(INPUT_POST, 'novo_cargo', FILTER_SANITIZE_SPECIAL_CHARS);

        // Papéis válidos (atualizados)
        $cargos_validos = ['admin', 'colaborador_escola', 'colaborador_nre', 'colaborador_nre_freq', 'view'];

        if (empty($usuario) || empty($senha_clara) || !in_array($cargo, $cargos_validos, true)) {
            throw new Exception("Todos os campos são obrigatórios e o cargo deve ser válido.");
        }
        if (strlen($senha_clara) < 8) {
            throw new Exception("A palavra-passe deve ter no mínimo 8 caracteres.");
        }

        $senha_hash = password_hash($senha_clara, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, senha, cargo, precisa_mudar_senha) VALUES (?, ?, ?, 1)");
        $stmt->execute([$usuario, $senha_hash, $cargo]);

        $_SESSION['feedback_usuario'] = [
            'type' => 'success',
            'text' => "Utilizador '{$usuario}' adicionado com sucesso. Ele(a) precisará mudar a palavra-passe no primeiro acesso."
        ];
        header("Location: usuarios.php"); 
        exit;

    } catch (Exception $e) { 
        $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro de Lógica: " . $e->getMessage()];
    } catch (PDOException $e) { 
        if ($e->getCode() == 23000) { 
             $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro de DB: O nome de utilizador '{$usuario}' já existe."];
        } else {
            $erro_db = "Erro de DB: C-USER-ADD-001.";
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro de PDO: " . $e->getMessage()];
            error_log("Erro em usuarios.php (Add User): " . $e->getMessage());
        }
    }
}

// --- FEEDBACK DA SESSÃO ---
if (isset($_SESSION['feedback_usuario'])) {
    $feedback_mensagem[] = $_SESSION['feedback_usuario'];
    unset($_SESSION['feedback_usuario']); 
}

// --- 4. BUSCA DE DADOS (READ) ---
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT id, usuario, cargo, criado_em, precisa_mudar_senha FROM usuarios ORDER BY cargo ASC, usuario ASC");
        $lista_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
         $lista_usuarios = [];
         if (!$erro_db) { 
             $erro_db = "Falha ao carregar lista: conexão DB indisponível.";
         }
    }
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar a lista de utilizadores: " . $e->getMessage();
    $lista_usuarios = []; 
    error_log("Erro em usuarios.php (Query Read): " . $e->getMessage());
}
?>

<h1 class="mb-4">Gestão de Utilizadores e Funções</h1>
<p class="text-muted">
    Acesso restrito a Administradores.<br>
    Papéis disponíveis:
    <span class="badge bg-danger">ADMIN</span>
    <span class="badge bg-warning text-dark">COLABORADOR ESCOLA</span>
    <span class="badge bg-info text-dark">COLABORADOR NRE</span>
    <span class="badge bg-primary">COLABORADOR NRE FREQ</span>
    <span class="badge bg-secondary">VIEW</span>
</p>

<?php if ($erro_db): ?>
    <div class="alert alert-danger"><?= $erro_db; ?></div>
<?php endif; ?>

<?php foreach ($feedback_mensagem as $msg): ?>
    <div class="alert alert-<?= $msg['type']; ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>

<div class="row">
    <!-- FORMULÁRIO -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Adicionar Novo Utilizador</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="usuarios.php">
                    <input type="hidden" name="action" value="adicionar_usuario">
                    <div class="mb-3">
                        <label for="novo_usuario" class="form-label">Nome de Utilizador</label>
                        <input type="text" class="form-control" id="novo_usuario" name="novo_usuario" required>
                    </div>
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Palavra-passe Inicial (min. 8 caracteres)</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                    </div>
                    <div class="mb-3">
                        <label for="novo_cargo" class="form-label">Função (RBAC)</label>
                        <select class="form-select" id="novo_cargo" name="novo_cargo" required>
                            <option value="view" selected>View (Apenas visualiza)</option>
                            <option value="colaborador_escola">Colaborador Escola (Frequência manual)</option>
                            <option value="colaborador_nre">Colaborador NRE (REDS)</option>
                            <option value="colaborador_nre_freq">Colaborador NRE Freq (Revisão/edição de frequência)</option>
                            <option value="admin">Admin (Acesso Total)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Criar Utilizador</button>
                </form>
            </div>
        </div>
    </div>

    <!-- LISTA -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Lista de Utilizadores (<?= count($lista_usuarios); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Utilizador</th>
                                <th>Função</th>
                                <th>Criado Em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_usuarios as $user): ?>
                            <?php
                                $cargo_raw = $user['cargo'];
                                $badge_color = nc_role_badge_color($cargo_raw);
                                $cargo_label = nc_role_display_label($cargo_raw);
                                $is_self = ($user['id'] == $current_admin_id);
                            ?>
                            <tr>
                                <td><?= (int)$user['id']; ?></td>
                                <td>
                                    <?= htmlspecialchars($user['usuario']); ?> 
                                    <?php if((int)$user['precisa_mudar_senha'] === 1): ?>
                                        <span class="badge bg-warning text-dark ms-1" title="Precisa mudar a palavra-passe no próximo login">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $badge_color; ?>">
                                        <?= $cargo_label; ?>
                                    </span>
                                    <a href="editar_cargo.php?id=<?= (int)$user['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1"
                                       title="Editar Função"
                                       <?php if ($is_self) echo 'aria-disabled="true" onclick="return false;" style="border: none; color: grey; cursor: not-allowed;"'; ?>>
                                        &#128221;
                                    </a>
                                </td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($user['criado_em']))); ?></td>
                                <td>
                                    <a href="repor_senha.php?id=<?= (int)$user['id']; ?>" class="btn btn-sm btn-info">
                                        Repor Senha
                                    </a>
                                    
                                    <a href="excluir_usuario.php?id=<?= (int)$user['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       <?php if ($is_self) echo 'aria-disabled="true" onclick="return false;" style="color: grey; background-color: lightgrey; border-color: lightgrey; cursor: not-allowed;"'; ?>>
                                        Excluir
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($lista_usuarios)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Nenhum utilizador cadastrado.</td>
                            </tr>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php'; 
?>
