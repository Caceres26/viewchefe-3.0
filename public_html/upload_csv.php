<?php
// JARVIS | NextCore: Digital Solutions
// upload_csv.php - Módulo de Importação com pré-visualização e staging seguro

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
// REQUISITO RBAC: Apenas Admin ou Colaborador podem importar
check_auth(['admin', 'colaborador']);

// 2. INCLUSÃO DA CONEXÃO E ESTRUTURA
require_once 'db.php';

// =====================================================================
// Download de Template CSV para FREQUÊNCIA
// GET: upload_csv.php?action=download_template_freq&scope=context|with_escola&sep=;&bom=1
// =====================================================================
if (isset($_GET['action']) && $_GET['action'] === 'download_template_freq') {
    $scope = $_GET['scope'] ?? 'context';
    $sep = $_GET['sep'] ?? ';';
    $bom = isset($_GET['bom']) ? ($_GET['bom'] === '1') : true;

    $allowedSep = [',', ';', "\t"];
    if (!in_array($sep, $allowedSep, true)) {
        $sep = ';';
    }

    if ($scope === 'with_escola') {
        $cols = ['escola', 'data_referencia', 'periodo', 'matriculados', 'presentes', 'faltas', 'faltas_justificadas'];
    } else {
        $cols = ['data_referencia', 'periodo', 'matriculados', 'presentes', 'faltas', 'faltas_justificadas'];
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_frequencia.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    if ($bom) {
        fwrite($out, "\xEF\xBB\xBF");
    }

    if ($sep === "\t") {
        fwrite($out, implode("\t", $cols) . "\r\n");
    } else {
        fputcsv($out, $cols, $sep);
    }

    fclose($out);
    exit;
}

$page_title = "Importação de Dados (CSV)";
require_once 'includes/header.php';

// 3. CONFIGURAÇÕES DE UPLOAD
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '6M');
ini_set('max_file_uploads', '3');

const EXPECTED_HEADERS = [
    'escola',
    'data_referencia',
    'periodo',
    'matriculados',
    'presentes',
    'faltas',
    'faltas_justificadas'
];

const CSV_MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB
const CSV_PREVIEW_LIMIT = 200;
const CSV_IMPORT_ROW_LIMIT = 20000;
const CSV_ALLOWED_EXTENSIONS = ['csv'];
const CSV_ALLOWED_MIME = ['text/csv', 'application/vnd.ms-excel', 'text/plain', 'application/csv'];

$feedback_mensagem = [];
$mode = $_POST['mode'] ?? null;
$delimitador_selecionado_default = $_POST['delimitador'] ?? ';';
$csv_preview = null;

/**
 * Remove caracteres de controle e aplica trim.
 */
function sanitize_csv_field(string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return trim($value);
}

function limpar_staging_csv(): void
{
    if (!empty($_SESSION['csv_staging']['path']) && is_file($_SESSION['csv_staging']['path'])) {
        @unlink($_SESSION['csv_staging']['path']);
    }
    unset($_SESSION['csv_staging']);
}

function gc_staging_temp(int $maxAgeSeconds = 86400): void
{
    $dir = sys_get_temp_dir();
    $now = time();
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'reds_*.csv') as $file) {
        if (is_file($file) && ($now - @filemtime($file)) > $maxAgeSeconds) {
            @unlink($file);
        }
    }
}

function validar_linha_csv(array $linha): bool
{
    if (count($linha) !== count(EXPECTED_HEADERS)) {
        return false;
    }

    $linha = array_map('sanitize_csv_field', $linha);
    [$escola, $data, $periodo, $matriculados, $presentes, $faltas, $faltasJustificadas] = $linha;

    if ($escola === '') {
        return false;
    }

    $dataObj = DateTime::createFromFormat('Y-m-d', $data);
    if (!$dataObj || $dataObj->format('Y-m-d') !== $data) {
        return false;
    }

    if (!in_array($periodo, ['Manhã', 'Tarde', 'Noite'], true)) {
        return false;
    }

    foreach ([$matriculados, $presentes, $faltas, $faltasJustificadas] as $valor) {
        if ($valor === '' || !is_numeric($valor)) {
            return false;
        }
    }

    return true;
}

function gerar_preview_csv(string $path, string $delimitador, array &$feedback): ?array
{
    if (!is_readable($path)) {
        $feedback[] = ['type' => 'danger', 'text' => 'Arquivo temporário indisponível para pré-visualização.'];
        return null;
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        $feedback[] = ['type' => 'danger', 'text' => 'Não foi possível abrir o arquivo CSV para pré-visualização.'];
        return null;
    }

    $headersRaw = fgetcsv($handle, 0, $delimitador);
    if ($headersRaw === false) {
        fclose($handle);
        $feedback[] = ['type' => 'danger', 'text' => 'Arquivo CSV vazio ou inválido.'];
        return null;
    }

    $headersNormalizados = array_map(fn($h) => strtolower(sanitize_csv_field((string) $h)), $headersRaw);
    if ($headersNormalizados !== EXPECTED_HEADERS) {
        fclose($handle);
        $feedback[] = ['type' => 'danger', 'text' => '<strong>Erro de Cabeçalho:</strong> Estrutura inesperada. Verifique o delimitador e o modelo fornecido.'];
        return null;
    }

    $previewRows = [];
    $validRows = 0;
    $invalidRows = 0;

    while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
        if (!validar_linha_csv($data)) {
            $invalidRows++;
            continue;
        }

        $validRows++;
        if (count($previewRows) < CSV_PREVIEW_LIMIT) {
            $previewRows[] = array_map('sanitize_csv_field', $data);
        }
    }

    fclose($handle);

    return [
        'headers' => array_map('sanitize_csv_field', $headersRaw),
        'rows' => $previewRows,
        'stats' => [
            'valid' => $validRows,
            'invalid' => $invalidRows,
            'total' => $validRows + $invalidRows,
        ],
    ];
}

gc_staging_temp();

if ($mode === 'csv_discard') {
    limpar_staging_csv();
    $feedback_mensagem[] = ['type' => 'info', 'text' => 'Pré-visualização descartada com sucesso.'];
} elseif ($mode === 'csv_preview') {
    $delimitador_selecionado_default = $_POST['delimitador'] ?? ';';
    if (!in_array($delimitador_selecionado_default, [';', ',', 'auto'], true)) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Delimitador inválido selecionado.'];
    } else {
        $arquivo = $_FILES['csv_file'] ?? null;
        $MAX_SIZE = CSV_MAX_UPLOAD_SIZE;

        if (
            !$arquivo ||
            ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Selecione um arquivo CSV.'];
        } elseif ($arquivo['error'] !== UPLOAD_ERR_OK) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Falha no upload (código ' . $arquivo['error'] . ').'];
        } elseif (($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > $MAX_SIZE) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Arquivo excede 5MB. Ajuste o arquivo e tente novamente.'];
        } else {
            $fname = $arquivo['name'] ?? '';
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $tmpPath = $arquivo['tmp_name'];
            $mime = $tmpPath && is_file($tmpPath) ? (@mime_content_type($tmpPath) ?: ($arquivo['type'] ?? '')) : '';

            if (!in_array($ext, CSV_ALLOWED_EXTENSIONS, true) || !in_array($mime, CSV_ALLOWED_MIME, true)) {
                $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Tipo de arquivo inválido. Envie um .csv'];
            } else {
                $stagingDir = sys_get_temp_dir();
                $stagingName = 'reds_' . bin2hex(random_bytes(8)) . '.csv';
                $stagingPath = $stagingDir . DIRECTORY_SEPARATOR . $stagingName;

                if (!move_uploaded_file($tmpPath, $stagingPath)) {
                    $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Não foi possível armazenar o arquivo temporário para pré-visualização.'];
                } else {
                    if (!empty($_SESSION['csv_staging'])) {
                        limpar_staging_csv();
                    }

                    $delimiterDetectado = $delimitador_selecionado_default;
                    if ($delimiterDetectado === 'auto') {
                        $sample = file_get_contents($stagingPath, false, null, 0, 2048);
                        $countSemicolon = substr_count((string) $sample, ';');
                        $countComma = substr_count((string) $sample, ',');
                        $delimiterDetectado = $countSemicolon >= $countComma ? ';' : ',';
                    }

                    $_SESSION['csv_staging'] = [
                        'path' => $stagingPath,
                        'delimiter' => $delimiterDetectado,
                        'created_at' => time(),
                        'original_name' => $fname,
                        'size' => $arquivo['size'] ?? 0,
                    ];

                    $csv_preview = gerar_preview_csv($stagingPath, $delimiterDetectado, $feedback_mensagem);

                    if ($csv_preview) {
                        $stats = $csv_preview['stats'];
                        $feedback_mensagem[] = ['type' => 'success', 'text' => "Pré-visualização gerada. {$stats['valid']} linhas válidas, {$stats['invalid']} inválidas."];

                        if ($stats['valid'] > CSV_IMPORT_ROW_LIMIT) {
                            limpar_staging_csv();
                            $csv_preview = null;
                            $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Arquivo excede o limite de 20.000 linhas válidas por importação.'];
                        }
                    } else {
                        limpar_staging_csv();
                    }
                }
            }
        }
    }
} elseif ($mode === 'csv_import') {
    $staging = $_SESSION['csv_staging'] ?? null;
    if (!$staging) {
        $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Nenhuma pré-visualização ativa encontrada. Envie o CSV novamente.'];
    } else {
        $path = $staging['path'];
        $delimiter = $staging['delimiter'];

        if (!is_file($path) || !is_readable($path)) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Arquivo temporário indisponível para importação.'];
            limpar_staging_csv();
        } else {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Falha ao reabrir o arquivo CSV.'];
                limpar_staging_csv();
            } else {
                $headersRaw = fgetcsv($handle, 0, $delimiter);
                $headersNormalizados = array_map(fn($h) => strtolower(sanitize_csv_field((string) $h)), $headersRaw ?: []);

                if ($headersNormalizados !== EXPECTED_HEADERS) {
                    $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Cabeçalho do arquivo sofreu alteração. Reenvie o CSV.'];
                    fclose($handle);
                    limpar_staging_csv();
                } else {
                    $linhasValidas = 0;
                    $linhasInvalidas = 0;
                    $linhasDuplicadas = 0;

                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'INSERT INTO school_metrics (escola, data_referencia, periodo, matriculados, presentes, faltas, faltas_justificadas)
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );

                        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                            if (!validar_linha_csv($data)) {
                                $linhasInvalidas++;
                                continue;
                            }

                            $valores = array_map('sanitize_csv_field', $data);
                            $valores[3] = (int) $valores[3];
                            $valores[4] = (int) $valores[4];
                            $valores[5] = (int) $valores[5];
                            $valores[6] = (int) $valores[6];

                            try {
                                $stmt->execute($valores);
                                $linhasValidas++;
                            } catch (PDOException $e) {
                                if ($e->getCode() === '23000') {
                                    $linhasDuplicadas++;
                                    continue;
                                }
                                throw $e;
                            }

                            if ($linhasValidas > CSV_IMPORT_ROW_LIMIT) {
                                throw new RuntimeException('Arquivo excede o limite de 20.000 linhas válidas por importação.');
                            }
                        }

                        $pdo->commit();

                        $feedbackParts = [];
                        if ($linhasValidas > 0) {
                            $feedbackParts[] = "$linhasValidas inseridas";
                        }
                        if ($linhasDuplicadas > 0) {
                            $feedbackParts[] = "$linhasDuplicadas duplicadas";
                        }
                        if ($linhasInvalidas > 0) {
                            $feedbackParts[] = "$linhasInvalidas inválidas";
                        }

                        $resumo = !empty($feedbackParts) ? implode(', ', $feedbackParts) : 'Nenhuma linha processada.';
                        $feedback_mensagem[] = ['type' => 'success', 'text' => 'Importação concluída: ' . $resumo];
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Falha na importação. Detalhe: ' . $e->getMessage()];
                    } finally {
                        fclose($handle);
                        limpar_staging_csv();
                    }
                }
            }
        }
    }
}

$stagingInfo = $_SESSION['csv_staging'] ?? null;
if (!$csv_preview && $stagingInfo && is_file($stagingInfo['path'])) {
    $csv_preview = gerar_preview_csv($stagingInfo['path'], $stagingInfo['delimiter'], $feedback_mensagem);
    if (!$csv_preview) {
        limpar_staging_csv();
        $stagingInfo = null;
    }
} else {
    $stagingInfo = $_SESSION['csv_staging'] ?? null;
}
?>

<h1 class="mb-4">Importação de Dados Educacionais (CSV)</h1>

<?php foreach ($feedback_mensagem as $msg): ?>
    <div class="alert alert-<?= $msg['type']; ?> alert-dismissible fade show" role="alert">
        <?= $msg['text']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>

<?php if ($csv_preview && $stagingInfo): ?>
    <?php $stats = $csv_preview['stats']; ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <h5 class="mb-1">Pré-visualização do CSV</h5>
                <small class="text-muted">Arquivo: <?= htmlspecialchars($stagingInfo['original_name'] ?? ''); ?> · Delimitador: <code><?= htmlspecialchars($stagingInfo['delimiter']); ?></code> · Tamanho: <?= number_format(($stagingInfo['size'] ?? 0) / 1024, 2); ?> KB</small>
            </div>
            <div class="mt-2 mt-md-0 text-md-end">
                <div class="badge bg-success text-white"><?= $stats['valid']; ?> linhas válidas</div>
                <div class="badge bg-warning text-dark"><?= $stats['invalid']; ?> inválidas</div>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted">Mostrando até <?= CSV_PREVIEW_LIMIT; ?> linhas válidas para conferência. Total processado: <?= $stats['total']; ?>.</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($csv_preview['headers'] as $header): ?>
                                <th scope="col"><?= htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($csv_preview['rows'])): ?>
                            <tr>
                                <td colspan="<?= count($csv_preview['headers']); ?>" class="text-center text-muted">Nenhuma linha válida encontrada no arquivo.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($csv_preview['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?= htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-column flex-md-row gap-2 mt-3">
                <form method="POST" class="flex-fill">
                    <input type="hidden" name="mode" value="csv_import">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-database me-2"></i>Importar linhas válidas
                    </button>
                </form>
                <form method="POST" class="flex-fill">
                    <input type="hidden" name="mode" value="csv_discard">
                    <button type="submit" class="btn btn-outline-danger w-100">
                        <i class="fas fa-trash-alt me-2"></i>Descartar pré-visualização
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="mb-1">1. Modelo de Arquivo Requerido</h5>
                    <small class="text-muted">O template será baixado em .csv com separador ; e codificação UTF-8 (compatível com Excel).</small>
                </div>
                <a id="btn-template-freq" class="btn btn-outline-primary" href="#" target="_blank" aria-disabled="true" tabindex="-1">
                    Baixar Template (CSV)
                </a>
            </div>
            <div class="card-body">
                <p>Para evitar erros, utilize o nosso modelo padrão. O arquivo CSV deve usar ponto-e-vírgula (;) ou vírgula (,) como separador.</p>

                <h6>Cabeçalhos Obrigatórios (Exatamente nesta ordem):</h6>
                <code class="d-block p-2 bg-light border mb-2 text-break">
                    escola;data_referencia;periodo;matriculados;presentes;faltas;faltas_justificadas
                </code>

                <h6>Regras de Formato:</h6>
                <ul>
                    <li><strong>data_referencia:</strong> AAAA-MM-DD (Ex: 2025-10-24)</li>
                    <li><strong>periodo:</strong> Somente "Manhã", "Tarde" ou "Noite"</li>
                    <li><strong>Campos Numéricos:</strong> Apenas números inteiros.</li>
                    <li><strong>Tamanho máximo:</strong> 5 MB por arquivo.</li>
                    <li>Quando usar o escopo do contexto (padrão), a escola selecionada na página será aplicada a todas as linhas.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">2. Enviar Arquivo para Pré-visualização</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="mode" value="csv_preview">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Selecione o Arquivo CSV (até 5MB):</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="invalid-feedback">Escolha um arquivo CSV válido.</div>
                    </div>

                    <div class="mb-3">
                        <label for="delimitador" class="form-label">Separador do Arquivo (Delimitador):</label>
                        <select class="form-select" id="delimitador" name="delimitador">
                            <option value=";" <?= $delimitador_selecionado_default === ';' ? 'selected' : ''; ?>>Ponto e Vírgula (;)</option>
                            <option value="," <?= $delimitador_selecionado_default === ',' ? 'selected' : ''; ?>>Vírgula (,)</option>
                            <option value="auto" <?= $delimitador_selecionado_default === 'auto' ? 'selected' : ''; ?>>Detectar automaticamente</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-search me-2"></i>Gerar pré-visualização
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var templateBtn = document.getElementById('btn-template-freq');
        if (!templateBtn) {
            return;
        }

        var scope = 'context';
        var sep = ';';
        var bom = '1';

        templateBtn.href = 'upload_csv.php?action=download_template_freq'
            + '&scope=' + encodeURIComponent(scope)
            + '&sep=' + encodeURIComponent(sep)
            + '&bom=' + encodeURIComponent(bom);
        templateBtn.setAttribute('aria-disabled', 'false');
        templateBtn.removeAttribute('tabindex');
    });
</script>

<?php
require_once 'includes/footer.php';
?>
