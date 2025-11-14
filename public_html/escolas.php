<?php
require_once 'includes/header_security.php';
require_once 'includes/header.php';
require_once 'db.php';

$page_title = "Gerenciar Escolas";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $escola = $_POST['escola'];
    $matriculados = intval($_POST['matriculados']);
    $diretor = $_POST['diretor'];
    $turno_principal = $_POST['turno_principal'];

    if (!empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE school_info SET escola = ?, matriculados = ?, diretor = ?, turno_principal = ? WHERE id = ?");
        $stmt->execute([$escola, $matriculados, $diretor, $turno_principal, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO school_info (escola, matriculados, diretor, turno_principal) VALUES (?, ?, ?, ?)");
        $stmt->execute([$escola, $matriculados, $diretor, $turno_principal]);
    }
    header('Location: escolas.php');
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM school_info WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: escolas.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM school_info ORDER BY escola ASC");
$escolas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h3 class="text-light mb-3">📘 Gerenciar Escolas</h3>

    <div class="card bg-dark text-light mb-4">
        <div class="card-header border-bottom border-secondary">Adicionar / Editar Escola</div>
        <div class="card-body">
            <form method="post" action="escolas.php" class="row g-3">
                <input type="hidden" name="id" id="form_id">
                <div class="col-md-4">
                    <label class="form-label">Escola</label>
                    <input type="text" name="escola" id="form_escola" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Matriculados</label>
                    <input type="number" name="matriculados" id="form_matriculados" class="form-control form-control-sm" min="0" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Diretor</label>
                    <input type="text" name="diretor" id="form_diretor" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Turno Principal</label>
                    <select name="turno_principal" id="form_turno" class="form-select form-select-sm">
                        <option value="Manhã">Manhã</option>
                        <option value="Tarde">Tarde</option>
                        <option value="Noite">Noite</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-success btn-sm w-100">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <table class="table table-dark table-hover table-sm align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Escola</th>
                <th>Matriculados</th>
                <th>Diretor</th>
                <th>Turno</th>
                <th>Atualizado</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($escolas as $escola): ?>
            <tr>
                <td><?= $escola['id']; ?></td>
                <td><?= htmlspecialchars($escola['escola']); ?></td>
                <td><?= $escola['matriculados']; ?></td>
                <td><?= htmlspecialchars($escola['diretor']); ?></td>
                <td><?= $escola['turno_principal']; ?></td>
                <td><?= date('d/m/Y H:i', strtotime($escola['atualizado_em'])); ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-info py-0 px-2"
                            onclick="editarEscola(<?= htmlspecialchars(json_encode($escola)); ?>)">✏️</button>
                    <a href="escolas.php?delete=<?= $escola['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Excluir esta escola?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function editarEscola(data) {
    document.getElementById('form_id').value = data.id;
    document.getElementById('form_escola').value = data.escola;
    document.getElementById('form_matriculados').value = data.matriculados;
    document.getElementById('form_diretor').value = data.diretor;
    document.getElementById('form_turno').value = data.turno_principal;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once 'includes/footer.php'; ?>
