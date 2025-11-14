<?php
// JARVIS | NextCore: Digital Solutions
// frequencia_manual.php - v1.0 (Cadastro Manual de Frequência)

// 1. Segurança (RBAC): Admin e Colaborador Escola podem cadastrar manualmente
require_once 'includes/header_security.php';
check_auth(['admin', 'colaborador_escola','colaborador_nre_freq']);

// 2. Conexão + Header
require_once 'db.php';
$page_title = "Cadastro Manual de Frequência";
require_once 'includes/header.php';

// 3. Variáveis
$feedback = [];
$erro_db = null;
$today = date('Y-m-d');
$periodos_disp = ['Manhã', 'Tarde', 'Noite'];
$escolas = [];

// 4. Carrega escolas (school_info)
try {
    $stmt = $pdo->query("SELECT nome FROM school_info ORDER BY nome ASC");
    $escolas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($escolas)) {
        $erro_db = "Nenhuma escola encontrada na tabela de referência (school_info).";
    }
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar escolas: C-FREQ-MAN-001.";
    error_log("frequencia_manual.php [load schools]: " . $e->getMessage());
}

// 5. Sanitização básica
function iint($v) {
    if ($v === '' || $v === null) return null;
    if (!is_numeric($v)) return null;
    return (int)$v;
}

// 6. Processamento do POST (insert em school_metrics)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erro_db) {

    $escola     = filter_input(INPUT_POST, 'escola', FILTER_SANITIZE_SPECIAL_CHARS);
    $data_ref   = filter_input(INPUT_POST, 'data_referencia', FILTER_SANITIZE_SPECIAL_CHARS);
    $periodo    = filter_input(INPUT_POST, 'periodo', FILTER_SANITIZE_SPECIAL_CHARS);

    $matriculados         = iint($_POST['matriculados'] ?? null);
    $presentes            = iint($_POST['presentes'] ?? null);
    $faltas               = iint($_POST['faltas'] ?? null);
    $faltas_justificadas  = iint($_POST['faltas_justificadas'] ?? null);

    // Validações
    if (!$escola || !in_array($escola, $escolas, true)) {
        $feedback[] = ['type' => 'danger', 'text' => 'Selecione uma escola válida.'];
    }
    $dt = DateTime::createFromFormat('Y-m-d', (string)$data_ref);
    if (!$dt || $dt->format('Y-m-d') !== $data_ref) {
        $feedback[] = ['type' => 'danger', 'text' => 'Data de referência inválida (use AAAA-MM-DD).'];
    }
    if (!$periodo || !in_array($periodo, $periodos_disp, true)) {
        $feedback[] = ['type' => 'danger', 'text' => 'Selecione um turno válido (Manhã/Tarde/Noite).'];
    }

    foreach ([
        'matriculados' => $matriculados,
        'presentes' => $presentes,
        'faltas' => $faltas,
        'faltas_justificadas' => $faltas_justificadas
    ] as $campo => $valor) {
        if (!is_int($valor) || $valor < 0) {
            $feedback[] = ['type' => 'danger', 'text' => "O campo '{$campo}' deve ser um inteiro >= 0."];
        }
    }

    // Regra simples: presentes + faltas >= 0; se quiser, pode exigir presença + faltas = matriculados
    if (is_int($presentes) && is_int($faltas) && is_int($matriculados)) {
        if (($presentes + $faltas) > $matriculados) {
            $feedback[] = ['type' => 'warning', 'text' => "Presentes + Faltas é maior que 'Matriculados'. Verifique os números."];
        }
    }

    // Se passou na validação, tenta gravar
    if (empty(array_filter($feedback, fn($m) => $m['type'] === 'danger'))) {
        try {
            // Checa duplicidade por (escola, data_referencia, periodo)
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM school_metrics
                WHERE escola = :escola AND data_referencia = :data_ref AND periodo = :periodo
            ");
            $stmtCheck->execute([
                ':escola'   => $escola,
                ':data_ref' => $data_ref,
                ':periodo'  => $periodo
            ]);
            $jaExiste = (int)$stmtCheck->fetchColumn() > 0;

            if ($jaExiste) {
                // Não sobrescreve; apenas informa. (Admin/NRE terá página de edição separada)
                $feedback[] = ['type' => 'danger', 'text' => 'Já existe um registro para esta Escola/Data/Turno. Solicite revisão via página de Edição de Frequência.'];
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO school_metrics
                        (escola, data_referencia, periodo, matriculados, presentes, faltas, faltas_justificadas)
                    VALUES
                        (:escola, :data_ref, :periodo, :matriculados, :presentes, :faltas, :faltas_justificadas)
                ");
                $stmtIns->execute([
                    ':escola'               => $escola,
                    ':data_ref'             => $data_ref,
                    ':periodo'              => $periodo,
                    ':matriculados'         => $matriculados,
                    ':presentes'            => $presentes,
                    ':faltas'               => $faltas,
                    ':faltas_justificadas'  => $faltas_justificadas
                ]);

                $feedback[] = ['type' => 'success', 'text' => 'Registro inserido com sucesso.'];
                // Limpa campos (opcional)
                $_POST = [];
            }

        } catch (PDOException $e) {
            $feedback[] = ['type' => 'danger', 'text' => 'Erro de DB ao inserir: C-FREQ-MAN-002.'];
            error_log("frequencia_manual.php [insert]: " . $e->getMessage());
        }
    }
}
?>

<h1 class="mb-3">Cadastro Manual de Frequência</h1>
<p class="text-muted mb-4">
    Insira diariamente a frequência por escola e turno. Registros duplicados (Escola/Data/Turno) não são sobrescritos.
</p>

<?php foreach ($feedback as $msg): ?>
    <div class="alert alert-<?= $msg['type']; ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endforeach; ?>

<?php if ($erro_db): ?>
    <div class="alert alert-danger"><?= $erro_db; ?></div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Nova entrada</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 needs-validation" novalidate>
                <div class="col-lg-5">
                    <label for="escola" class="form-label fw-bold">Escola</label>
                    <select class="form-select" id="escola" name="escola" required>
                        <option value="" disabled <?= empty($_POST['escola']) ? 'selected' : '' ?>>-- Selecione --</option>
                        <?php foreach ($escolas as $esc): ?>
                            <option value="<?= htmlspecialchars($esc); ?>" <?= (($_POST['escola'] ?? '') === $esc) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($esc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Selecione uma escola.</div>
                </div>

                <div class="col-lg-3">
                    <label for="data_referencia" class="form-label fw-bold">Data de Referência</label>
                    <input type="date" class="form-control" id="data_referencia" name="data_referencia"
                           value="<?= htmlspecialchars($_POST['data_referencia'] ?? $today); ?>" required>
                    <div class="invalid-feedback">Informe uma data válida (AAAA-MM-DD).</div>
                </div>

                <div class="col-lg-4">
                    <label for="periodo" class="form-label fw-bold">Turno</label>
                    <select class="form-select" id="periodo" name="periodo" required>
                        <option value="" disabled <?= empty($_POST['periodo']) ? 'selected' : '' ?>>-- Selecione --</option>
                        <?php foreach ($periodos_disp as $p): ?>
                            <option value="<?= $p; ?>" <?= (($_POST['periodo'] ?? '') === $p) ? 'selected' : '' ?>><?= $p; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Selecione um turno.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Matriculados</label>
                    <input type="number" min="0" class="form-control" name="matriculados" value="<?= htmlspecialchars($_POST['matriculados'] ?? ''); ?>" required>
                    <div class="invalid-feedback">Informe um inteiro ≥ 0.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Presentes</label>
                    <input type="number" min="0" class="form-control" name="presentes" value="<?= htmlspecialchars($_POST['presentes'] ?? ''); ?>" required>
                    <div class="invalid-feedback">Informe um inteiro ≥ 0.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Faltas</label>
                    <input type="number" min="0" class="form-control" name="faltas" value="<?= htmlspecialchars($_POST['faltas'] ?? ''); ?>" required>
                    <div class="invalid-feedback">Informe um inteiro ≥ 0.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Faltas Justificadas</label>
                    <input type="number" min="0" class="form-control" name="faltas_justificadas" value="<?= htmlspecialchars($_POST['faltas_justificadas'] ?? ''); ?>" required>
                    <div class="invalid-feedback">Informe um inteiro ≥ 0.</div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Salvar Registro
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
