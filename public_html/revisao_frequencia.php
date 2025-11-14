<?php
// JARVIS | NextCore: Digital Solutions
// revisão_frequencia.php – Edição manual de dados de frequência

require_once 'includes/header_security.php';
check_auth();

$cargo = $_SESSION['usuario_cargo'] ?? '';
if (!in_array($cargo, ['admin', 'colaborador_nre_frequencia'])) {
    http_response_code(403);
    die('Acesso não autorizado.');
}

require_once 'db.php';
$page_title = "Revisão de Frequência";
require_once 'includes/header.php';

$escolas_disp = [];
$mensagem = '';
$erro = '';
$dados = null;

try {
    $stmt = $pdo->query("SELECT DISTINCT escola FROM school_metrics ORDER BY escola ASC");
    $escolas_disp = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $erro = "Erro ao carregar escolas.";
    error_log("ERRO ESCOLAS: " . $e->getMessage());
}

// Carregamento ou atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $escola = $_POST['escola'] ?? '';
    $data = $_POST['data'] ?? '';
    $periodo = $_POST['periodo'] ?? '';
    $matriculados = $_POST['matriculados'] ?? null;
    $presentes = $_POST['presentes'] ?? null;
    $faltas = $_POST['faltas'] ?? null;
    $faltas_justificadas = $_POST['faltas_justificadas'] ?? null;

    if ($escola && $data && $periodo) {
        try {
            $stmt = $pdo->prepare("
                UPDATE school_metrics
                SET matriculados = :mat,
                    presentes = :pres,
                    faltas = :falt,
                    faltas_justificadas = :fjust
                WHERE escola = :esc AND data_referencia = :dt AND periodo = :per
            ");
            $stmt->execute([
                ':mat' => $matriculados,
                ':pres' => $presentes,
                ':falt' => $faltas,
                ':fjust' => $faltas_justificadas,
                ':esc' => $escola,
                ':dt' => $data,
                ':per' => $periodo
            ]);
            $mensagem = "Dados atualizados com sucesso.";
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar dados.";
            error_log("ERRO UPDATE: " . $e->getMessage());
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['buscar'])) {
    $escola = $_GET['escola'] ?? '';
    $data = $_GET['data'] ?? '';
    $periodo = $_GET['periodo'] ?? '';

    if ($escola && $data && $periodo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM school_metrics WHERE escola = :esc AND data_referencia = :dt AND periodo = :per LIMIT 1");
            $stmt->execute([':esc' => $escola, ':dt' => $data, ':per' => $periodo]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$dados) {
                $erro = "Nenhum dado encontrado para os filtros informados.";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao buscar dados.";
            error_log("ERRO BUSCA: " . $e->getMessage());
        }
    }
}
?>

<div class="container my-4">
    <h1 class="mb-3">Revisão de Frequência</h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php elseif ($mensagem): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Escola</label>
            <select name="escola" class="form-select" required>
                <option value="">Selecione</option>
                <?php foreach ($escolas_disp as $esc): ?>
                    <option value="<?= htmlspecialchars($esc) ?>" <?= ($esc === ($_GET['escola'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($esc) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Data</label>
            <input type="date" name="data" class="form-control" value="<?= htmlspecialchars($_GET['data'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Período</label>
            <select name="periodo" class="form-select" required>
                <option <?= ($_GET['periodo'] ?? '') == 'Manhã' ? 'selected' : '' ?>>Manhã</option>
                <option <?= ($_GET['periodo'] ?? '') == 'Tarde' ? 'selected' : '' ?>>Tarde</option>
                <option <?= ($_GET['periodo'] ?? '') == 'Noite' ? 'selected' : '' ?>>Noite</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" name="buscar" value="1" class="btn btn-primary w-100">Buscar</button>
        </div>
    </form>

    <?php if ($dados): ?>
        <hr>
        <form method="post" class="row g-3 mt-2">
            <input type="hidden" name="escola" value="<?= htmlspecialchars($dados['escola']) ?>">
            <input type="hidden" name="data" value="<?= htmlspecialchars($dados['data_referencia']) ?>">
            <input type="hidden" name="periodo" value="<?= htmlspecialchars($dados['periodo']) ?>">

            <div class="col-md-3">
                <label class="form-label">Matriculados</label>
                <input type="number" name="matriculados" class="form-control" value="<?= htmlspecialchars($dados['matriculados']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Presentes</label>
                <input type="number" name="presentes" class="form-control" value="<?= htmlspecialchars($dados['presentes']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Faltas</label>
                <input type="number" name="faltas" class="form-control" value="<?= htmlspecialchars($dados['faltas']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Faltas Justificadas</label>
                <input type="number" name="faltas_justificadas" class="form-control" value="<?= htmlspecialchars($dados['faltas_justificadas']) ?>" required>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success">Salvar Alterações</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
