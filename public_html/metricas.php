<?php
// JARVIS | NextCore: Digital Solutions
// metricas.php - v3.3 (Matriculados de school_info + formatação compacta pt-BR + UI consistente)
// - Mantém lógica/consultas originais
// - Adiciona funções de formatação locale-aware e remove zeros desnecessários

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
check_auth(); 

// 2. INCLUSÃO DA CONEXÃO E ESTRUTURA VISUAL
require_once 'db.php'; 
$page_title = "Dashboard - Análise de Período";
require_once 'includes/header.php'; 

// 3. INICIALIZAÇÃO DE VARIÁVEIS
$erro_db = null;
$escolas_disp = [];
$periodos_disp = ['Manhã', 'Tarde', 'Noite'];
$sem_dados_no_sistema = false;
$dados_encontrados_geral = false; // Flag se dados de FREQUÊNCIA foram encontrados
$kpis_atuais = []; 
$kpis_anteriores = [];
$total_matriculados_oficial = 0; // school_info (individual ou soma)
$percentual_presenca_atual = 0.0;
$percentual_presenca_anterior = 0.0;
$variacao_presenca = 0.0;
$label_periodo_atual = '';
$label_periodo_anterior = '';
$total_dias_periodo = 0;
$escola_info_nao_encontrada = false;

// 3.1 Funções utilitárias de formatação (pt-BR)
function to_float_locale($v) {
    if ($v === null || $v === '') return null;
    if (is_float($v) || is_int($v)) return (float)$v;
    $s = preg_replace('/\s/u', '', (string)$v);
    $posC = strrpos($s, ',');
    $posD = strrpos($s, '.');
    if ($posC !== false && $posD === false) { // vírgula decimal (pt-BR)
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($posD !== false && $posC === false) { // ponto decimal (en-US)
        $s = str_replace(',', '', $s);
    } elseif ($posD !== false && $posC !== false) { // ambos: o último é decimal
        if ($posD > $posC) { $s = str_replace(',', '', $s); }
        else { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
    }
    return is_numeric($s) ? (float)$s : null;
}

function format_compacto_ptbr(float $num, int $maxDec = 4, int $minDec = 0): string {
    $s = number_format($num, $maxDec, ',', '.');
    $s = preg_replace('/(,\d*?)0+$/u', '$1', $s); // remove zeros à direita
    $s = rtrim($s, ','); // remove vírgula solta
    if ($minDec > 0 && strpos($s, ',') === false) { $s .= ',' . str_repeat('0', $minDec); }
    return $s;
}

function fmt_percent($v, $maxDec = 2) {
    $n = to_float_locale($v);
    if (!is_numeric($n)) return 'N/D';
    return format_compacto_ptbr((float)$n, $maxDec) . '%';
}

// 4. BUSCA DE ESCOLAS PARA FILTRO (da school_metrics)
try {
    $stmt_escolas = $pdo->query("SELECT DISTINCT escola FROM school_metrics ORDER BY escola ASC");
    $escolas_disp = $stmt_escolas->fetchAll(PDO::FETCH_COLUMN);
    if (empty($escolas_disp)) {
        $sem_dados_no_sistema = true;
    }
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar filtros: C-MET-002.";
    error_log("Erro em metricas.php (Filtros): " . $e->getMessage());
}

// 5. PROCESSAMENTO DOS FILTROS E DEFINIÇÃO DOS PERÍODOS
$filtro_escola = $_GET['escola'] ?? 'Todas';
$filtro_periodo_turno = $_GET['periodo_turno'] ?? 'Todos';
$filtro_periodo_tipo = $_GET['periodo_tipo'] ?? 'hoje'; 
$data_inicio_form = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-6 days'));
$data_fim_form = $_GET['data_fim'] ?? date('Y-m-d');

// Função definirPeriodos (Mantida)
function definirPeriodos($tipo, $inicio_form, $fim_form) { /* ... Código mantido ... */ 
    $hoje = date('Y-m-d'); $datas = ['inicio_atual' => $hoje, 'fim_atual' => $hoje, 'inicio_anterior' => date('Y-m-d', strtotime('-1 day')), 'fim_anterior' => date('Y-m-d', strtotime('-1 day')), 'label_atual' => 'Hoje (' . date('d/m') . ')', 'label_anterior' => 'Ontem (' . date('d/m', strtotime('-1 day')) . ')', 'dias' => 1 ];
    switch ($tipo) { case '7dias': $datas['inicio_atual'] = date('Y-m-d', strtotime('-6 days')); $datas['fim_atual'] = $hoje; $datas['inicio_anterior'] = date('Y-m-d', strtotime('-13 days')); $datas['fim_anterior'] = date('Y-m-d', strtotime('-7 days')); $datas['label_atual'] = 'Últimos 7 dias'; $datas['label_anterior'] = '7 dias anteriores'; $datas['dias'] = 7; break; case 'mes_atual': $datas['inicio_atual'] = date('Y-m-01'); $datas['fim_atual'] = date('Y-m-t'); $datas['inicio_anterior'] = date('Y-m-01', strtotime('first day of last month')); $datas['fim_anterior'] = date('Y-m-t', strtotime('last day of last month')); $datas['label_atual'] = 'Mês Atual (' . date('m/Y') . ')'; $datas['label_anterior'] = 'Mês Anterior'; $datas['dias'] = (int)date('t'); break; case 'mes_anterior': $datas['inicio_atual'] = date('Y-m-01', strtotime('first day of last month')); $datas['fim_atual'] = date('Y-m-t', strtotime('last day of last month')); $datas['inicio_anterior'] = date('Y-m-01', strtotime('first day of -2 month')); $datas['fim_anterior'] = date('Y-m-t', strtotime('last day of -2 month')); $datas['label_atual'] = 'Mês Anterior (' . date('m/Y', strtotime($datas['inicio_atual'])) . ')'; $datas['label_anterior'] = 'Mês Retrasado'; $datas['dias'] = (int)date('t', strtotime('last day of last month')); break; case 'personalizado': $datas['inicio_atual'] = $inicio_form; $datas['fim_atual'] = $fim_form; try { $date1 = new DateTime($datas['inicio_atual']); $date2 = new DateTime($datas['fim_atual']); if ($date2 < $date1) { $datas['fim_atual'] = $datas['inicio_atual']; $date2 = $date1; } $intervalo = $date1->diff($date2); $duracao_dias = $intervalo->days + 1; $datas['fim_anterior'] = date('Y-m-d', strtotime($datas['inicio_atual'] . ' -1 day')); $datas['inicio_anterior'] = date('Y-m-d', strtotime($datas['fim_anterior'] . ' -' . ($duracao_dias - 1) . ' days')); $datas['label_atual'] = date('d/m/Y', strtotime($datas['inicio_atual'])) . ' a ' . date('d/m/Y', strtotime($datas['fim_atual'])); $datas['label_anterior'] = date('d/m/Y', strtotime($datas['inicio_anterior'])) . ' a ' . date('d/m/Y', strtotime($datas['fim_anterior'])); $datas['dias'] = $duracao_dias; } catch (Exception $e) { return definirPeriodos('hoje', $inicio_form, $fim_form); } break; } return $datas;
}
$periodos = definirPeriodos($filtro_periodo_tipo, $data_inicio_form, $data_fim_form);
$data_inicio = $periodos['inicio_atual'];
$data_fim = $periodos['fim_atual'];
$data_inicio_anterior = $periodos['inicio_anterior'];
$data_fim_anterior = $periodos['fim_anterior'];
$label_periodo_atual = $periodos['label_atual'];
$label_periodo_anterior = $periodos['label_anterior'];
$total_dias_periodo = $periodos['dias'];

// --- Função Auxiliar calcular_kpis (SUMs)
function calcular_kpis_metrics($pdo, $data_inicio, $data_fim, $filtro_escola, $filtro_periodo_turno) {
    $kpis_metrics = [ 'total_matriculados_soma' => 0, 'total_presentes' => 0, 'total_faltas' => 0, 'dados_encontrados' => false ];
    try {
        $sql_where = ["data_referencia BETWEEN :data_inicio AND :data_fim"];
        $sql_params = [':data_inicio' => $data_inicio, ':data_fim' => $data_fim];
        if ($filtro_escola !== 'Todas') { $sql_where[] = "escola = :escola"; $sql_params[':escola'] = $filtro_escola; }
        if ($filtro_periodo_turno !== 'Todos') { $sql_where[] = "periodo = :periodo"; $sql_params[':periodo'] = $filtro_periodo_turno; }
        $sql = "SELECT SUM(matriculados) AS total_matriculados_soma, SUM(presentes) AS total_presentes, SUM(faltas) AS total_faltas FROM school_metrics WHERE " . implode(" AND ", $sql_where);
        $stmt = $pdo->prepare($sql); $stmt->execute($sql_params); $resultado = $stmt->fetch();
        $check_count_sql = "SELECT COUNT(*) FROM school_metrics WHERE " . implode(" AND ", $sql_where);
        $stmt_count = $pdo->prepare($check_count_sql); $stmt_count->execute($sql_params); $rowCount = $stmt_count->fetchColumn();
        if ($rowCount > 0 && $resultado) {
            $kpis_metrics['dados_encontrados'] = true;
            $kpis_metrics['total_matriculados_soma'] = (int)($resultado['total_matriculados_soma'] ?? 0);
            $kpis_metrics['total_presentes'] = (int)($resultado['total_presentes'] ?? 0);
            $kpis_metrics['total_faltas'] = (int)($resultado['total_faltas'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log("Erro em calcular_kpis_metrics: " . $e->getMessage());
    }
    return $kpis_metrics;
}

// 6. BUSCA DE KPIS E MATRICULADOS OFICIAIS
if (!$sem_dados_no_sistema) {
    try {
        $kpis_atuais = calcular_kpis_metrics($pdo, $data_inicio, $data_fim, $filtro_escola, $filtro_periodo_turno);
        if ($data_inicio_anterior && $data_fim_anterior) {
             $kpis_anteriores = calcular_kpis_metrics($pdo, $data_inicio_anterior, $data_fim_anterior, $filtro_escola, $filtro_periodo_turno);
        } else {
             $kpis_anteriores = ['dados_encontrados' => false, 'total_matriculados_soma' => 0, 'total_presentes' => 0]; 
        }
        $dados_encontrados_geral = $kpis_atuais['dados_encontrados'];

        // MATRICULADOS OFICIAIS (school_info)
        if ($filtro_escola !== 'Todas') {
            $stmt_info = $pdo->prepare("SELECT total_matriculados FROM school_info WHERE nome = :nome_escola");
            $stmt_info->execute([':nome_escola' => $filtro_escola]);
            $info = $stmt_info->fetch();
            if ($info && isset($info['total_matriculados'])) { $total_matriculados_oficial = (int)$info['total_matriculados']; }
            else { $escola_info_nao_encontrada = true; }
        } else {
            $total_matriculados_oficial = (int)($pdo->query("SELECT SUM(total_matriculados) FROM school_info")->fetchColumn() ?? 0);
        }

        // Percentuais (sempre sobre SUMs de school_metrics)
        $percentual_presenca_atual = 0.0;
        if ($kpis_atuais['dados_encontrados'] && $kpis_atuais['total_matriculados_soma'] > 0) {
            $percentual_presenca_atual = ($kpis_atuais['total_presentes'] / $kpis_atuais['total_matriculados_soma']) * 100;
        }
        $percentual_presenca_anterior = 0.0;
        if (($kpis_anteriores['dados_encontrados'] ?? false) && $kpis_anteriores['total_matriculados_soma'] > 0) {
            $percentual_presenca_anterior = ($kpis_anteriores['total_presentes'] / $kpis_anteriores['total_matriculados_soma']) * 100;
        }
        // Variação
        $variacao_presenca = 0.0; 
        if ($percentual_presenca_anterior > 0) {
            $variacao_presenca = (($percentual_presenca_atual / $percentual_presenca_anterior) - 1) * 100;
        } elseif ($percentual_presenca_atual > 0) {
            $variacao_presenca = 100.0;
        }

    } catch (PDOException $e) {
        $erro_db = "Erro ao buscar métricas: C-MET-001.";
        error_log("Erro em metricas.php (KPIs): " . $e->getMessage());
        $dados_encontrados_geral = false; 
    }
}
?>

<h1 class="mb-2">Dashboard de Desempenho</h1>
<p class="text-muted">Análise do período: <?= htmlspecialchars($label_periodo_atual); ?> 
    <?php if ($total_dias_periodo > 0): ?>
        <span class="badge bg-secondary ms-2"><?= $total_dias_periodo ?> dia<?= $total_dias_periodo > 1 ? 's' : '' ?></span>
    <?php endif; ?>
</p>

<?php if (isset($_SESSION['auth_error'])): ?> <div class="alert alert-warning"><?= $_SESSION['auth_error']; unset($_SESSION['auth_error']); ?></div> <?php endif; ?>
<?php if (isset($_SESSION['feedback_export'])): ?> <div class="alert alert-<?= $_SESSION['feedback_export']['type']; ?>"><?= $_SESSION['feedback_export']['text']; unset($_SESSION['feedback_export']); ?></div> <?php endif; ?>
<?php if (isset($_SESSION['feedback_global'])): ?> <div class="alert alert-<?= $_SESSION['feedback_global']['type']; ?>"><?= $_SESSION['feedback_global']['text']; unset($_SESSION['feedback_global']); ?></div> <?php endif; ?>
<?php if ($erro_db): ?> <div class="alert alert-danger"><?= $erro_db; ?></div> <?php endif; ?>
<?php if ($escola_info_nao_encontrada): ?> <div class="alert alert-warning">Atenção: Os dados de matrícula oficial para a escola selecionada ("<?= htmlspecialchars($filtro_escola) ?>") não foram encontrados na tabela 'school_info'.</div> <?php endif; ?>

<?php if ($sem_dados_no_sistema): ?>
   <div class="card text-center shadow-sm border mb-4">
       <div class="card-body p-5">
           <h3 class="text-primary">Bem-vindo ao Sistema de Métricas!</h3>
           <p class="lead">Ainda não há dados registrados.</p>
           <p>Acesse o módulo de importação para começar.</p>
           <a href="upload_csv.php" class="btn btn-success btn-lg mt-3">Importar Dados (CSV)</a>
       </div>
   </div>
<?php else: ?>
    <div class="card filter-card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" action="metricas.php" class="row g-3 align-items-end">
       <div class="col-md-3"> <label for="periodo_tipo_filtro" class="form-label">Período:</label> <select class="form-select" id="periodo_tipo_filtro" name="periodo_tipo"> <option value="hoje" <?= $filtro_periodo_tipo == 'hoje' ? 'selected' : ''; ?>>Hoje</option> <option value="7dias" <?= $filtro_periodo_tipo == '7dias' ? 'selected' : ''; ?>>Últimos 7 dias</option> <option value="mes_atual" <?= $filtro_periodo_tipo == 'mes_atual' ? 'selected' : ''; ?>>Mês Atual</option> <option value="mes_anterior" <?= $filtro_periodo_tipo == 'mes_anterior' ? 'selected' : ''; ?>>Mês Anterior</option> <option value="personalizado" <?= $filtro_periodo_tipo == 'personalizado' ? 'selected' : ''; ?>>Personalizado</option> </select> </div> <div class="col-md-2" id="data_inicio_container" style="<?= $filtro_periodo_tipo !== 'personalizado' ? 'display: none;' : '' ?>"> <label for="data_inicio_filtro" class="form-label">De:</label> <input type="date" class="form-control" id="data_inicio_filtro" name="data_inicio" value="<?= htmlspecialchars($data_inicio); ?>"> </div> <div class="col-md-2" id="data_fim_container" style="<?= $filtro_periodo_tipo !== 'personalizado' ? 'display: none;' : '' ?>"> <label for="data_fim_filtro" class="form-label">Até:</label> <input type="date" class="form-control" id="data_fim_filtro" name="data_fim" value="<?= htmlspecialchars($data_fim); ?>"> </div> <div class="col-md-2"> <label for="escola_filtro" class="form-label">Escola:</label> <select class="form-select" id="escola_filtro" name="escola"> <option value="Todas" <?= $filtro_escola == 'Todas' ? 'selected' : ''; ?>>Todas</option> <?php foreach ($escolas_disp as $escola): ?> <option value="<?= htmlspecialchars($escola); ?>" <?= $filtro_escola == $escola ? 'selected' : ''; ?>><?= htmlspecialchars($escola); ?></option> <?php endforeach; ?> </select> </div> <div class="col-md-2"> <label for="periodo_turno_filtro" class="form-label">Turno:</label> <select class="form-select" id="periodo_turno_filtro" name="periodo_turno"> <option value="Todos" <?= $filtro_periodo_turno == 'Todos' ? 'selected' : ''; ?>>Todos</option> <?php foreach ($periodos_disp as $periodo): ?> <option value="<?= htmlspecialchars($periodo); ?>" <?= $filtro_periodo_turno == $periodo ? 'selected' : ''; ?>><?= htmlspecialchars($periodo); ?></option> <?php endforeach; ?> </select> </div> <div class="col-md-1"> <button type="submit" class="btn btn-success w-100">Filtrar</button> </div>
            </form>
        </div>
    </div>

    <?php if (!$dados_encontrados_geral): ?>
        <div class="alert alert-info text-center"> Nenhum dado de frequência encontrado para o período e filtros selecionados. </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                 <div class="card text-white bg-primary shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Presença no Período</h5>
                        <p class="card-text fs-2"><?= fmt_percent($percentual_presenca_atual, 2); ?></p>
                        <p class="card-text"><small><?= htmlspecialchars($label_periodo_atual); ?></small></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                  <?php 
                    $cor_variacao = 'secondary'; $icone_variacao = 'fa-minus'; 
                    $tem_dados_anterior = $kpis_anteriores['dados_encontrados'] ?? false;
                    if ($tem_dados_anterior || $kpis_atuais['dados_encontrados']) {
                        if ($variacao_presenca > 0.1) { $cor_variacao = 'success'; $icone_variacao = 'fa-arrow-up'; }
                        elseif ($variacao_presenca < -0.1) { $cor_variacao = 'danger'; $icone_variacao = 'fa-arrow-down'; }
                        else { $cor_variacao = 'warning'; $icone_variacao = 'fa-equals'; }
                    }
                 ?>
                <div class="card text-white bg-<?= $cor_variacao; ?> shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Variação vs. Per. Anterior</h5>
                         <p class="card-text fs-2">
                             <i class="fas <?= $icone_variacao; ?> me-2"></i>
                             <?= ($tem_dados_anterior || $kpis_atuais['dados_encontrados'] ? fmt_percent($variacao_presenca, 1) : 'N/D'); ?>
                         </p>
                        <p class="card-text"><small><?= htmlspecialchars($label_periodo_anterior); ?></small></p>
                    </div>
                </div>
            </div>

            <?php if ($total_matriculados_oficial > 0): // Mostra se encontrou valor em school_info ?>
                 <div class="col-lg-3 col-md-6 mb-4">
                     <div class="card border-info shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-info">
                                <?= ($filtro_escola !== 'Todas') ? 'Total Matriculados (Oficial)' : 'Total Matriculados (Geral)'; ?>
                            </h5>
                            <p class="card-text fs-2"><?= number_format($total_matriculados_oficial, 0, ',', '.'); ?></p>
                            <p class="card-text text-muted">
                                <small><?= ($filtro_escola !== 'Todas') ? 'Valor oficial da escola' : 'Soma de todas as escolas'; ?></small>
                            </p> 
                        </div>
                    </div>
                 </div>
             <?php endif; ?>

             <div class="<?= ($total_matriculados_oficial > 0) ? 'col-lg-3' : 'col-lg-6'; ?> col-md-6 mb-4">
                  <div class="card border-success shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-success">Total Presentes</h5>
                        <p class="card-text fs-2"><?= number_format($kpis_atuais['total_presentes'], 0, ',', '.'); ?></p>
                         <p class="card-text text-muted"><small>Soma no período (dados de frequência)</small></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array($usuario_cargo, ['admin', 'colaborador'])): ?>
       <div class="card shadow-sm mt-4"> <div class="card-header bg-secondary text-white"> <h5 class="mb-0">Exportação de Relatórios (CSV)</h5> </div> <div class="card-body"> <p class="card-text">Exporte os dados brutos de métricas para análise externa.</p> <form method="GET" action="exportar_dados.php" class="row g-3 align-items-end"> <div class="col-md-4"> <label for="export_data_inicio" class="form-label">Data Início:</label> <input type="date" class="form-control" id="export_data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio); ?>" required> </div> <div class="col-md-4"> <label for="export_data_fim" class="form-label">Data Fim:</label> <input type="date" class="form-control" id="export_data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim); ?>" required> </div> <div class="col-md-4"> <button type="submit" class="btn btn-secondary w-100"> <i class="fas fa-download me-2"></i>Exportar Período Selecionado </button> </div> </form> </div> </div>
    <?php endif; ?>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodoTipoSelect = document.getElementById('periodo_tipo_filtro');
    const dataInicioContainer = document.getElementById('data_inicio_container');
    const dataFimContainer = document.getElementById('data_fim_container');
    function toggleDateInputs() { const show = periodoTipoSelect.value === 'personalizado'; dataInicioContainer.style.display = show ? 'block' : 'none'; dataFimContainer.style.display = show ? 'block' : 'none'; }
    toggleDateInputs(); 
    periodoTipoSelect.addEventListener('change', toggleDateInputs); 
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/chart-defaults.js"></script>

<?php 
require_once 'includes/footer.php'; 
?>
