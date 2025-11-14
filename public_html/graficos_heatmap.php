<?php
// JARVIS | NextCore: Digital Solutions
// graficos_heatmap.php - Heatmap de Presença Semanal por Escola (v5 - Otimizado e Escalável)
// Ajustes: Correção da Escala de Cor (Fixo 0-100), Dropdown de Escolas carregado via AJAX/JS.

require_once 'includes/header_security.php';
check_auth();
require_once 'db.php';

$page_title = "Heatmap • Presença Semanal por Escola";
require_once 'includes/header.php'; // Presume que este arquivo linka 'assets/heatmap-styles.css'

// -------------------- PARÂMETROS / FILTROS --------------------

// Trimestres de 2025
$TRI_2025 = [
    'T1' => ['ini' => '2025-02-05', 'fim' => '2025-04-29'],
    'T2' => ['ini' => '2025-05-05', 'fim' => '2025-08-21'],
    'T3' => ['ini' => '2025-08-25', 'fim' => '2025-12-19'],
];

$periodos = ['Todos','Manhã','Tarde','Noite'];

$trim = $_GET['trim'] ?? 'T1'; // T1 | T2 | T3 | custom
$dt_ini = $_GET['ini'] ?? ($TRI_2025['T1']['ini']);
$dt_fim = $_GET['fim'] ?? ($TRI_2025['T1']['fim']);
$filtro_periodo = $_GET['periodo'] ?? 'Todos';
$filtro_escolas = $_GET['escolas'] ?? []; if (!is_array($filtro_escolas)) $filtro_escolas = [$filtro_escolas];

// --- AJUSTE NextCore: Escala de cor fixa como Contínua Global ---
$escala = 'global';
$paleta = 'continua';

// Se usuário trocou o trimestre (exceto custom), aplicamos intervalo fixo
if (in_array($trim, ['T1','T2','T3'], true)) {
    $dt_ini = $TRI_2025[$trim]['ini'];
    $dt_fim = $TRI_2025[$trim]['fim'];
} else {
    // sanity: se veio custom, normaliza datas
    if (!$dt_ini) $dt_ini = date('Y-m-01');
    if (!$dt_fim) $dt_fim = date('Y-m-d');
    if ($dt_ini > $dt_fim) { $tmp = $dt_ini; $dt_ini = $dt_fim; $dt_fim = $tmp; }
}

// -------------------- HELPERS --------------------

function week_anchor(DateTimeImmutable $d): DateTimeImmutable {
    $dow = (int)$d->format('N'); // 1=Mon..7=Sun
    return $d->modify('-'.($dow-1).' days');
}
function semana_label_br($ymd) {
    $monday = new DateTimeImmutable($ymd);
    $sunday = $monday->modify('+6 days');
    return 'Sem '.$monday->format('W/Y').' · '.$monday->format('d/m').'–'.$sunday->format('d/m');
}

// -------------------- ESCOLAS DISPONÍVEIS --------------------

$erro_db = null;
$escolas_disp = [];
// NOTA: A busca de escolas é mantida aqui para validar os filtros no backend
// mas a lista completa NÃO é mais renderizada no HTML (melhoria de performance).
try {
    $stmt = $pdo->query("SELECT DISTINCT escola FROM school_metrics ORDER BY escola ASC");
    $escolas_disp = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch(PDOException $e) {
    $erro_db = "Erro ao carregar escolas.";
    error_log("HEATMAP ESCOLAS: ".$e->getMessage());
}

// -------------------- SEMANAS (ÂNCORA: SEGUNDA) --------------------

$start = week_anchor(new DateTimeImmutable($dt_ini));
$end   = week_anchor(new DateTimeImmutable($dt_fim));
if ($start > $end) { $tmp=$start; $start=$end; $end=$tmp; }

$weeks = [];
$cursor = $start;
while ($cursor <= $end) {
    $weeks[] = $cursor;
    $cursor = $cursor->modify('+1 week');
}
$labels_weeks = array_map(fn($w)=>$w->format('Y-m-d'), $weeks);

// -------------------- CONSULTA MATRIZ --------------------

$rows = [];
if (!$erro_db && !empty($escolas_disp) && !empty($labels_weeks)) {
    try {
        $where = [];
        $params = [];

        $where[] = "data_referencia BETWEEN :di AND :df";
        $params[':di'] = $dt_ini;
        $params[':df'] = (new DateTimeImmutable(end($labels_weeks)))->modify('+6 days')->format('Y-m-d');

        if ($filtro_periodo !== 'Todos') {
            $where[] = "periodo = :p";
            $params[':p'] = $filtro_periodo;
        }
        
        $filtro_escolas = array_map('trim', $filtro_escolas);
        
        if (!empty($filtro_escolas)) {
            $valid = array_values(array_intersect($filtro_escolas, $escolas_disp));
            if (empty($valid)) { $where[] = "1=0"; }
            else {
                $in = [];
                foreach ($valid as $i=>$esc) { $k=":e{$i}"; $in[]=$k; $params[$k]=$esc; }
                $where[] = "escola IN (".implode(',', $in).")";
            }
        }

        $sql = "
            SELECT
                escola,
                DATE_SUB(data_referencia, INTERVAL (WEEKDAY(data_referencia)) DAY) AS semana_ancora,
                SUM(presentes) as soma_presentes,
                SUM(matriculados) as soma_matriculados
            FROM school_metrics
            WHERE ".implode(' AND ', $where)."
            GROUP BY escola, semana_ancora
            HAVING soma_matriculados > 0
            ORDER BY escola ASC, semana_ancora ASC
        ";
        $st = $pdo->prepare($sql); $st->execute($params);
        $res = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $matrix = [];
        $globalMin = 100; $globalMax = 0;

        foreach ($res as $r) {
            $esc = $r['escola'];
            $anc = (new DateTimeImmutable($r['semana_ancora']))->format('Y-m-d');
            $perc = round(($r['soma_presentes'] / $r['soma_matriculados']) * 100, 2);
            $matrix[$esc][$anc] = $perc;
            if ($perc < $globalMin) $globalMin = $perc;
            if ($perc > $globalMax) $globalMax = $perc;
        }

        $lista_escolas = !empty($filtro_escolas) ? array_values(array_intersect($filtro_escolas, $escolas_disp)) : $escolas_disp;

        usort($lista_escolas, function($a,$b) use($matrix,$labels_weeks){
            $lw = end($labels_weeks);
            $va = $matrix[$a][$lw] ?? null; $vb = $matrix[$b][$lw] ?? null;
            if ($va === $vb) return strcmp($a,$b);
            if ($va === null) return 1; if ($vb === null) return -1;
            return $vb <=> $va;
        });

        $rows = [
            'weeks'   => $labels_weeks,
            'schools' => $lista_escolas,
            'matrix'  => $matrix,
            'globalMin' => $globalMin,
            'globalMax' => $globalMax
        ];

    } catch(PDOException $e) {
        $erro_db = "Erro ao gerar heatmap.";
        error_log("HEATMAP QUERY: ".$e->getMessage());
    }
}

// -------------------- EXPORT CSV --------------------
if (isset($_GET['export']) && $_GET['export']==='csv' && !empty($rows)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="heatmap_presenca.csv"');
    $out = fopen('php://output','w');
    $head = array_merge(['escola'], $rows['weeks']);
    fputcsv($out, $head, ';');
    foreach ($rows['schools'] as $esc) {
        $line = [$esc];
        foreach ($rows['weeks'] as $w) {
            $v = $rows['matrix'][$esc][$w] ?? null;
            $line[] = ($v === null ? '' : str_replace('.', ',', (string)$v));
        }
        fputcsv($out, $line, ';');
    }
    fclose($out);
    exit;
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h1 class="mb-0">Heatmap • Presença Semanal</h1>
        <small class="text-muted">Visão geral por escola × semana</small>
    </div>
    <?php if (!empty($rows)): ?>
        <a class="btn btn-outline-secondary btn-sm" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">
            <i class="fa-solid fa-file-csv me-1"></i> Exportar CSV
        </a>
    <?php endif; ?>
</div>

<?php if ($erro_db): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro_db); ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="heatmap-form">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Trimestre</label>
                <select class="form-select" name="trim" id="trim-select">
                    <option value="T1" <?= $trim==='T1'?'selected':''; ?>>1º Trimestre (05/02/2025–29/04/2025)</option>
                    <option value="T2" <?= $trim==='T2'?'selected':''; ?>>2º Trimestre (05/05/2025–21/08/2025)</option>
                    <option value="T3" <?= $trim==='T3'?'selected':''; ?>>3º Trimestre (25/08/2025–19/12/2025)</option>
                    <option value="custom" <?= $trim==='custom'?'selected':''; ?>>Personalizado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Início</label>
                <input type="date" class="form-control" name="ini" id="ini" value="<?= htmlspecialchars($dt_ini); ?>" <?= $trim==='custom'?'':'disabled'; ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Fim</label>
                <input type="date" class="form-control" name="fim" id="fim" value="<?= htmlspecialchars($dt_fim); ?>" <?= $trim==='custom'?'':'disabled'; ?>>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Período</label>
                <select class="form-select" name="periodo">
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= $p; ?>" <?= $filtro_periodo===$p?'selected':''; ?>><?= $p; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- COLUNA ESCOLAS (8 COLS) -->
            <div class="col-md-8">
                <label class="form-label fw-semibold">Escolas</label>
                <div class="dropdown">
                    <button class="btn btn-outline-primary w-100 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="dropdown-escolas">
                        <span id="schools-label"><?= empty($filtro_escolas) ? 'Selecionar escolas' : count($filtro_escolas) . ' escola(s) selecionada(s)'; ?></span>
                        <i class="fa-solid fa-chevron-down ms-2"></i>
                    </button>
                    <div class="dropdown-menu p-0 w-xxl">
                        <div class="p-2 border-bottom d-flex gap-2">
                            <input type="text" class="form-control" placeholder="Buscar escola..." id="school-search">
                            <button class="btn btn-sm btn-outline-secondary" type="button" id="btn-check-all">Marcar todas</button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" id="btn-uncheck-all">Limpar</button>
                        </div>
                        <div class="dropdown-checklist" id="schools-list">
                            <div class="p-2 text-center text-muted">Carregando escolas...</div>
                        </div>
                    </div>
                </div>
                <div id="schools-hidden">
                <?php 
                    // Renderiza os inputs hidden para manter a seleção após o refresh
                    foreach ($filtro_escolas as $esc) {
                        echo '<input type="hidden" name="escolas[]" value="' . htmlspecialchars($esc) . '">';
                    }
                ?>
                </div>
            </div>

            <!-- COLUNA BOTÕES (4 COLS), ALINHADA AO DROPDOWN -->
            <div class="col-md-4 d-flex align-items-end">
                <div class="d-flex gap-2 w-100">
                    <button class="btn btn-primary flex-grow-1">
                        <i class="fa-solid fa-table-cells-large me-1"></i> Gerar
                    </button>
                    <a class="btn btn-outline-secondary" href="graficos_heatmap.php">Limpar</a>
                </div>
            </div>

            <!-- TEXTO DE AJUDA EM LINHA INDEPENDENTE -->
            <div class="col-12">
                <small class="text-muted">Se nada for marcado, todas as escolas são consideradas.</small>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($rows) && !empty($rows['schools'])): ?>
    <div class="d-flex align-items-center justify-content-between mb-2 small text-muted">
        <div>
            Intervalo: <?= htmlspecialchars((new DateTimeImmutable($rows['weeks'][0]))->format('d/m/Y')); ?>
            — <?= htmlspecialchars((new DateTimeImmutable(end($rows['weeks'])))->modify('+6 days')->format('d/m/Y')); ?>
            <?= $filtro_periodo!=='Todos' ? ' · Turno: '.htmlspecialchars($filtro_periodo) : ''; ?>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2 small text-muted">
            <div class="hm-legend d-flex align-items-center gap-4">
                <div class="d-flex align-items-center">
                    <span class="chip" style="background:#A92C2C;"></span>
                    <small class="ms-1">Abaixo de 90%</small>
                </div>
                <div class="d-flex align-items-center">
                    <span class="chip" style="background:#EBA930;"></span>
                    <small class="ms-1">Maior ou igual a 90% e menor que 95%</small>
                </div>
                <div class="d-flex align-items-center">
                    <span class="chip" style="background:#30A930;"></span>
                    <small class="ms-1">Maior ou igual a 95%</small>
                </div>
            </div>
        </div>
    </div>

    <?php
    // NextCore: Correção da Escala de Cor (Fixo 0% a 100%)
    $min = 0; 
    $max = 100;
    ?>

    <div class="heatmap-wrap">
        <table class="heatmap-table">
            <thead>
                <tr>
                    <th class="sticky-col">Escola</th>
                    <?php foreach ($rows['weeks'] as $w): ?>
                        <th title="<?= htmlspecialchars(semana_label_br($w)); ?>">
                            <?= (new DateTimeImmutable($w))->format('d/m'); ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows['schools'] as $esc): ?>
                    <tr>
                        <th class="sticky-col">
                            <a href="escola_detalhes_reds.php?<?= http_build_query(['escola'=>$esc]); ?>" class="text-decoration-none">
                                <?= htmlspecialchars($esc); ?>
                            </a>
                        </th>
                        <?php foreach ($rows['weeks'] as $w): ?>
                            <?php
                                $v = $rows['matrix'][$esc][$w] ?? null;
                                $title = semana_label_br($w)."\n".$esc.($v!==null ? " · ".number_format($v,1,',','.')."%" : " · Sem dados");
                                
                                $style = '';
                                $text = '–';
                                
                                if ($v !== null) {
                                    $val = max(0, min(100, (float)$v));

                                    // --- NextCore: Lógica de COR POR FAIXA FIXA (Padrão BI) ---
                                    $cor_hex = '';
                                    
                                    if ($val >= 95.0) {
                                        $cor_hex = '#30A930'; // Verde
                                    } elseif ($val >= 90.0) {
                                        $cor_hex = '#EBA930'; // Laranja/Amarelo
                                    } else {
                                        $cor_hex = '#A92C2C'; // Vermelho
                                    }
                                    
                                    // Adiciona a cor hexadecimal na variável CSS --cell-color
                                    $style = "--cell-color:{$cor_hex};";
                                    
                                    // Formato com uma casa decimal (90,0%)
                                    $text = number_format($val, 1, ',', '.') . '%';
                                }
                            ?>
                            <td class="hm-cell" data-v="<?= $v!==null?(float)$v:''; ?>" data-title="<?= htmlspecialchars($title); ?>" style="<?= $style; ?>">
                                <span class="hm-text"><?= $text; ?></span>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php elseif(!$erro_db): ?>
    <div class="alert alert-info">Sem dados para os filtros selecionados.</div>
<?php endif; ?>

<script>
// Variável JS para a lista de escolas selecionadas no PHP
const selectedSchools = <?= json_encode($filtro_escolas); ?>;

// controla trimestre x datas
document.getElementById('trim-select')?.addEventListener('change', function(){
    const ini = document.getElementById('ini');
    const fim = document.getElementById('fim');
    if (this.value === 'custom') {
        ini.removeAttribute('disabled');
        fim.removeAttribute('disabled');
    } else {
        ini.setAttribute('disabled','disabled');
        fim.setAttribute('disabled','disabled');
        const MAP = {
            T1: {ini: '2025-02-05', fim: '2025-04-29'},
            T2: {ini: '2025-05-05', fim: '2025-08-21'},
            T3: {ini: '2025-08-25', fim: '2025-12-19'}
        };
        if (MAP[this.value]) {
            ini.value = MAP[this.value].ini;
            fim.value = MAP[this.value].fim;
        }
    }
});

// Dropdown de escolas com carregamento AJAX (Melhoria de Performance)
(function(){
    const list = document.getElementById('schools-list');
    const hidden = document.getElementById('schools-hidden');
    const label = document.getElementById('schools-label');
    const search = document.getElementById('school-search');
    const btnAll = document.getElementById('btn-check-all');
    const btnNone = document.getElementById('btn-uncheck-all');

    let allSchools = []; // Armazena a lista completa

    function updateHiddenInputs(){
        hidden.innerHTML = '';
        const checked = list.querySelectorAll('.school-item:checked');
        checked.forEach(chk => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'escolas[]';
            inp.value = chk.value;
            hidden.appendChild(inp);
        });
        label.textContent = checked.length ? `${checked.length} escola(s) selecionada(s)` : 'Selecionar escolas';
    }

    function renderSchools(schools) {
        list.innerHTML = '';
        schools.forEach(esc => {
            const isChecked = selectedSchools.includes(esc);
            const id = 'esc_' + esc.replace(/[^a-zA-Z0-9]/g, '_'); // ID simples
            
            const div = document.createElement('div');
            div.className = 'form-check';
            div.innerHTML = `
                <input class="form-check-input school-item" type="checkbox" value="${esc}" id="${id}" ${isChecked ? 'checked' : ''}>
                <label class="form-check-label" for="${id}">${esc}</label>
            `;
            list.appendChild(div);
        });
        // Re-atribui o listener de mudança
        list.querySelectorAll('.school-item').forEach(chk => {
            chk.addEventListener('change', updateHiddenInputs);
        });
        updateHiddenInputs();
    }

    function loadSchools() {
        fetch('api_escolas.php') // Novo endpoint AJAX
            .then(response => response.json())
            .then(data => {
                allSchools = data;
                renderSchools(allSchools);
            })
            .catch(error => {
                list.innerHTML = `<div class="p-2 text-danger">Erro ao carregar lista de escolas.</div>`;
                console.error('NextCore: Erro ao carregar escolas via AJAX', error);
            });
    }
    
    // Carrega escolas ao carregar a página
    loadSchools();

    btnAll?.addEventListener('click', () => {
        list.querySelectorAll('.school-item').forEach(c => c.checked = true);
        updateHiddenInputs();
    });
    btnNone?.addEventListener('click', () => {
        list.querySelectorAll('.school-item').forEach(c => c.checked = false);
        updateHiddenInputs();
    });

    search?.addEventListener('input', () => {
        const q = search.value.toLowerCase().trim();
        // Filtra a lista completa em JS, sem AJAX adicional
        const filtered = allSchools.filter(esc => esc.toLowerCase().includes(q));
        renderSchools(filtered); // Renderiza apenas as filtradas
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
