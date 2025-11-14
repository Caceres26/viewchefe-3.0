<?php
// JARVIS | NextCore: Digital Solutions
// escola_detalhes_reds.php - v2.9 (layout compacto + Accordion manual + KhanMigo)
// - Mantém a lógica/visual anterior com accordion manual (sem Bootstrap.Collapse)
// - Cards KPI no topo e grid de programas com “Resumo rápido” + “Explorar indicadores”
// - Inclui o recurso KhanMigo

// 1) Segurança e sessão
require_once 'includes/header_security.php';
check_auth();

// 2) Conexão e cabeçalho
require_once 'db.php';
$page_title = "Indicadores RED'S por Escola";
require_once 'includes/header.php';

// 3) Variáveis
$erro_db = null;
$escolas_disp = [];
$school_info_data = null;
$total_matriculados_oficial = 0;
$school_id = null;
$reds_data = [];
$latest_red_date = [];
$escola_selecionada = filter_input(INPUT_GET, 'escola', FILTER_SANITIZE_SPECIAL_CHARS);
$usuario_cargo_atual = $_SESSION['usuario_cargo'] ?? 'view';

// 4) Funções
function to_float_locale($v) {
    if ($v === null || $v === '') return null;
    if (is_float($v) || is_int($v)) return (float)$v;
    $s = preg_replace('/\s/u', '', (string)$v);
    $posC = strrpos($s, ',');
    $posD = strrpos($s, '.');
    if ($posC !== false && $posD === false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($posD !== false && $posC === false) {
        $s = str_replace(',', '', $s);
    } elseif ($posD !== false && $posC !== false) {
        if ($posD > $posC) { $s = str_replace(',', '', $s); }
        else { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
    }
    return is_numeric($s) ? (float)$s : null;
}
function format_compacto_ptbr(float $num, int $maxDec = 4, int $minDec = 0): string {
    $s = number_format($num, $maxDec, ',', '.');
    $s = preg_replace('/(,\d*?)0+$/u', '$1', $s);
    $s = rtrim($s, ',');
    if ($minDec > 0 && strpos($s, ',') === false) { $s .= ',' . str_repeat('0', $minDec); }
    return $s;
}
function formatar_valor_red($key, $value) {
    if ($value === null || $value === '') return '<span class="text-muted small">N/D</span>';
    $k = strtolower($key);
    $num = to_float_locale($value);
    if (str_contains($k, 'percent') || strpos($k, '%') !== false) {
        return is_numeric($num) ? format_compacto_ptbr((float)$num, 2) . '%' : htmlspecialchars((string)$value);
    }
    if (str_contains($k, 'indice')) {
        return is_numeric($num) ? format_compacto_ptbr((float)$num, 4) : htmlspecialchars((string)$value);
    }
    if (is_numeric($num)) {
        $temDecimal = (floor($num) != $num);
        return $temDecimal ? format_compacto_ptbr((float)$num, 2) : number_format($num, 0, ',', '.');
    }
    return htmlspecialchars((string)$value);
}

// 5) Carrega escolas
try {
    $stmt_escolas = $pdo->query("SELECT nome FROM school_info ORDER BY nome ASC");
    $escolas_disp = $stmt_escolas->fetchAll(PDO::FETCH_COLUMN);
    if (empty($escolas_disp)) $erro_db = "Nenhuma escola encontrada na tabela de referência (school_info).";
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar lista de escolas: C-RED-FLT-001.";
    error_log("Erro em escola_detalhes_reds.php (Filtro Escolas): " . $e->getMessage());
}

// 6) Busca dados da escola selecionada
if (!empty($escola_selecionada) && !$erro_db) {
    try {
        $stmt_info = $pdo->prepare("SELECT id, total_matriculados FROM school_info WHERE nome = :nome_escola");
        $stmt_info->execute([':nome_escola' => $escola_selecionada]);
        $school_info_data = $stmt_info->fetch(PDO::FETCH_ASSOC);
        $school_id = $school_info_data ? (int)$school_info_data['id'] : null;
        $total_matriculados_oficial = $school_info_data ? (int)($school_info_data['total_matriculados'] ?? 0) : 0;

        if (!$school_id) {
            $erro_db = "Escola '" . htmlspecialchars($escola_selecionada) . "' não encontrada na base de dados.";
        } else {
            $tabelas_red = [
                'Programação Paraná' => [
                    'tabela' => 'red_programacao_parana',
                    'cols'   => ['total_estudantes','acerto_exercicios_percent','indice_exercicios_realizados','projetos_realizados','acessos_periodo_percent','frequencia_percent'],
                    'header_class' => 'bg-primary'
                ],
                'Khan Academy' => [
                    'tabela' => 'red_khan_academy',
                    'cols'   => ['total_alunos','total_minutos','tempo_uso_medio_min','acessos_periodo_percent','habilidades_trabalhadas','habilidades_progresso','habilidades_progredidas'],
                    'header_class' => 'bg-info'
                ],
                // NOVO: KhanMigo
                'KhanMigo' => [
                    'tabela' => 'red_khanmigo',
                    'cols'   => ['alunos_com_licenca','alunos_ativados','uso_percent','total_interacoes','media_interacoes'],
                    'header_class' => 'bg-secondary'
                ],
                'Matemática Paraná' => [
                    'tabela' => 'red_matematica_parana',
                    'cols'   => ['alunos','tarefas_concluidas','indice_tarefas_concluidas','acessos_periodo_percent','score_acertos','tempo_uso_minutos'],
                    'header_class' => 'bg-success'
                ],
                'Robótica Paraná' => [
                    'tabela' => 'red_robotica_parana',
                    'cols'   => ['alunos_matriculados','questoes_respondidas','indice_respostas','questoes_corretas','indice_acertos','acessos_unicos_percent'],
                    'header_class' => 'bg-warning'
                ],
                'Desafio Paraná' => [
                    'tabela' => 'red_desafio_parana',
                    'cols'   => ['alunos','questoes_respondidas','indice_respostas','questoes_corretas','indice_acertos','acessos_unicos_percent'],
                    'header_class' => 'bg-danger'
                ],
                'Inglês Paraná Teens' => [
                    'tabela' => 'red_ingles_teens',
                    'cols'   => ['alunos','licoes_realizadas','indice_licoes_realizadas','acessos_periodo_percent','certificados_totais'],
                    'header_class' => 'bg-secondary'
                ],
                'Inglês Paraná High' => [
                    'tabela' => 'red_ingles_high',
                    'cols'   => ['alunos','licoes_realizadas','indice_licoes_realizadas','acessos_periodo_percent','certificados_totais'],
                    'header_class' => 'bg-dark'
                ],
                'Leia Paraná' => [
                    'tabela' => 'red_leia_parana',
                    'cols'   => ['alunos','acessos_periodo_percent','atividades_realizadas','indice_atividades_realizadas','livros_concluidos','indice_livros_concluidos','acertos_livros_percent'],
                    'header_class' => 'bg-primary'
                ],
                'Redação Paraná' => [
                    'tabela' => 'red_redacao_parana',
                    'cols'   => ['alunos','redacoes_propostas','redacoes_pendentes','redacoes_concluidas','indice_semanal_concluidas','redacoes_inativas_percent','corrigidas_ia_percent'],
                    'header_class' => 'bg-info'
                ]
            ];

            foreach ($tabelas_red as $nome_amigavel => $config) {
                $tabela = $config['tabela'];
                $colunas_select = implode(', ', $config['cols']);
                $sql_red = "SELECT data_referencia, {$colunas_select}
                            FROM {$tabela}
                            WHERE school_info_id = :school_id
                            ORDER BY data_referencia DESC
                            LIMIT 1";
                $stmt_red = $pdo->prepare($sql_red);
                $stmt_red->execute([':school_id' => $school_id]);
                $data = $stmt_red->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    $reds_data[$nome_amigavel] = $data + ['__header_class' => ($config['header_class'] ?? 'bg-light')];
                    $latest_red_date[$nome_amigavel] = $data['data_referencia'];
                }
            }
        }
    } catch (Exception $e) {
        if (!$erro_db) $erro_db = "Erro: " . $e->getMessage();
    } catch (PDOException $e) {
        if (!$erro_db) $erro_db = "Erro de Base de Dados ao buscar detalhes: C-RED-DET-001.";
        error_log("Erro em escola_detalhes_reds.php: " . $e->getMessage());
    }
}
?>

<div class="row mb-3">
  <div class="col-12">
    <h1 class="mb-2">Indicadores RED'S por Escola</h1>
    <p class="text-muted mb-0">Painel consolidado de desempenho por programa educacional</p>
  </div>
</div>

<div class="card mb-4 shadow-sm border-0">
  <div class="card-body">
    <form method="GET" action="escola_detalhes_reds.php" class="row g-3 align-items-end">
        <div class="col-md-8">
            <label for="escola_select" class="form-label fw-semibold">Selecione a Escola:</label>
            <select class="form-select shadow-sm" id="escola_select" name="escola" required>
                <option value="" <?= empty($escola_selecionada) ? 'selected' : ''; ?> disabled>-- Escolha uma escola --</option>
                <?php foreach ($escolas_disp as $escola): ?>
                    <option value="<?= htmlspecialchars($escola); ?>" <?= ($escola_selecionada == $escola) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($escola); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label invisible">.</label>
            <button type="submit" class="btn btn-primary w-100 shadow-sm">
                <i class="fas fa-chart-bar me-1"></i> Ver Indicadores RED'S
            </button>
        </div>
    </form>
  </div>
</div>

<?php if ($erro_db): ?>
  <div class="alert alert-danger"><?= $erro_db; ?></div>
<?php endif; ?>

<?php if (!empty($escola_selecionada) && $school_id && !$erro_db): ?>

  <div class="row mb-4 align-items-center">
      <div class="col-md-8">
          <h2 class="display-6 border-bottom pb-2 mb-0"><?= htmlspecialchars($escola_selecionada); ?></h2>
      </div>
      <div class="col-md-4">
          <?php if ($total_matriculados_oficial > 0): ?>
              <div class="card border-info shadow-sm">
                  <div class="card-body text-center p-2">
                      <h6 class="card-title text-info mb-1">Total Matriculados (Oficial)</h6>
                      <p class="card-text fs-4 fw-bold mb-0"><?= number_format($total_matriculados_oficial, 0, ',', '.'); ?></p>
                  </div>
              </div>
          <?php endif; ?>
      </div>
  </div>

  <div class="row mb-4 text-center g-3">
      <div class="col-md-4">
          <div class="card border-secondary shadow-sm h-100">
              <div class="card-body p-2">
                  <h6 class="text-muted mb-1">Última Atualização Geral</h6>
                  <p class="fs-6 fw-bold mb-0"><?= !empty($latest_red_date) ? date('d/m/Y', strtotime(max($latest_red_date))) : 'N/D'; ?></p>
              </div>
          </div>
      </div>
      <div class="col-md-4">
          <div class="card border-success shadow-sm h-100">
              <div class="card-body p-2">
                  <h6 class="text-success mb-1">Programas com Dados</h6>
                  <p class="fs-4 fw-bold mb-0"><?= count($reds_data); ?></p>
              </div>
          </div>
      </div>
      <div class="col-md-4">
          <div class="card border-primary shadow-sm h-100">
              <div class="card-body p-2">
                  <h6 class="text-primary mb-1">Matriculados Informados</h6>
                  <p class="fs-5 fw-bold mb-0"><?= $total_matriculados_oficial > 0 ? number_format($total_matriculados_oficial, 0, ',', '.') : 'N/D'; ?></p>
              </div>
          </div>
      </div>
  </div>

  <h4 class="mb-3">Indicadores RED'S (Dados Mais Recentes por Programa)</h4>

  <?php if (empty($reds_data)): ?>
      <div class="alert alert-info text-center my-3">Nenhum indicador RED'S registrado recentemente para esta escola.</div>
  <?php else: ?>
      <div id="redsAccordion" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
          <?php foreach ($reds_data as $red_nome => $metrics): ?>
              <?php
                  $rawHeaderClass = $metrics['__header_class'] ?? 'bg-light';
                  $headerClass = htmlspecialchars($rawHeaderClass, ENT_QUOTES, 'UTF-8');
                  $headerTextClass = preg_match('/\b(bg-light|bg-warning|bg-info|bg-white|bg-body(?:-tertiary)?|bg-secondary-subtle|bg-success-subtle|bg-danger-subtle|bg-warning-subtle|bg-info-subtle|bg-primary-subtle)\b/i', $rawHeaderClass)
                      ? 'text-dark'
                      : 'text-white';
                  $headerTextClassEscaped = htmlspecialchars($headerTextClass, ENT_QUOTES, 'UTF-8');
                  $collapseId = 'resourceCollapse_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($red_nome));
                  $dataReferencia = $latest_red_date[$red_nome] ?? ($metrics['data_referencia'] ?? null);
                  $dataReferenciaLabel = $dataReferencia ? date('d/m/Y', strtotime($dataReferencia)) : 'N/D';

                  $metricEntries = [];
                  foreach ($metrics as $key => $value) {
                      if ($key === 'data_referencia' || $key === '__header_class') continue;
                      $displayValue = formatar_valor_red($key, $value);
                      $metricEntries[] = [
                          'key'   => $key,
                          'label' => ucwords(str_replace('_', ' ', $key)),
                          'value' => $displayValue,
                          'plain' => strip_tags($displayValue)
                      ];
                  }
                  $previewEntries = array_slice($metricEntries, 0, 3);
                  $remainingCount = max(0, count($metricEntries) - count($previewEntries));
              ?>
              <div class="col">
                  <div class="card resource-card h-100 shadow-sm border-0">
                      <div class="card-header <?= $headerClass; ?> <?= $headerTextClassEscaped; ?> py-2">
                          <div class="d-flex justify-content-between align-items-center gap-2">
                              <button type="button"
                                      class="resource-title btn btn-link <?= $headerTextClassEscaped; ?> text-start p-0 fw-semibold"
                                      data-target="#<?= $collapseId; ?>"
                                      aria-expanded="false">
                                  <i class="fas fa-layer-group me-2"></i><?= htmlspecialchars($red_nome); ?>
                              </button>
                              <span class="badge bg-dark bg-opacity-50 text-white small">
                                  Atualizado: <?= $dataReferenciaLabel; ?>
                              </span>
                          </div>
                      </div>

                      <div class="card-body pb-2">
                          <p class="text-muted text-uppercase small fw-semibold mb-2">Resumo rápido</p>
                          <?php if (!empty($previewEntries)): ?>
                              <ul class="list-unstyled mb-1 small">
                                  <?php foreach ($previewEntries as $entry): ?>
                                      <li class="d-flex justify-content-between">
                                          <span class="text-muted"><?= htmlspecialchars($entry['label']); ?></span>
                                          <span class="fw-semibold"><?= htmlspecialchars($entry['plain']); ?></span>
                                      </li>
                                  <?php endforeach; ?>
                              </ul>
                          <?php else: ?>
                              <p class="text-muted small mb-1">Nenhum indicador disponível para pré-visualização.</p>
                          <?php endif; ?>
                          <?php if ($remainingCount > 0): ?>
                              <p class="text-muted small mb-0">
                                  +<?= $remainingCount; ?> indicador<?= $remainingCount > 1 ? 'es' : ''; ?> adicionais
                              </p>
                          <?php endif; ?>
                      </div>

                      <div class="card-body pt-0">
                          <button type="button"
                                  class="btn btn-outline-primary btn-sm resource-expand-toggle w-100"
                                  data-target="#<?= $collapseId; ?>"
                                  aria-expanded="false"
                                  data-expand-label="Explorar indicadores"
                                  data-collapse-label="Recolher indicadores">
                              <span class="toggle-icon" aria-hidden="true">
                                  <i class="fa-solid fa-angle-down"></i>
                              </span>
                              <span class="toggle-label">Explorar indicadores</span>
                          </button>

                          <div class="collapse resource-expanded mt-2" id="<?= $collapseId; ?>">
                              <?php if (!empty($metricEntries)): ?>
                                  <ul class="list-group list-group-flush small">
                                      <?php foreach ($metricEntries as $entry): ?>
                                          <li class="list-group-item px-2 py-1 d-flex justify-content-between align-items-center">
                                              <span class="text-muted"><?= htmlspecialchars($entry['label']); ?></span>
                                              <span class="fw-semibold"><?= $entry['value']; ?></span>
                                          </li>
                                      <?php endforeach; ?>
                                  </ul>
                              <?php else: ?>
                                  <p class="text-muted small mb-0">Nenhum indicador detalhado disponível para este programa.</p>
                              <?php endif; ?>

                              <?php if (in_array($usuario_cargo_atual, ['admin', 'colaborador'])): ?>
                                  <a href="upload_csv.php" class="btn btn-link btn-sm w-100 mt-2 p-0">
                                      <i class="fa-solid fa-file-arrow-up me-1"></i>
                                      Atualizar dados deste programa
                                  </a>
                              <?php endif; ?>
                          </div>
                      </div>
                  </div>
              </div>
          <?php endforeach; ?>
      </div>
  <?php endif; ?>

  <p class="text-muted mt-3 small">
      * Os indicadores RED'S são inseridos periodicamente pela equipe técnica do NRE.
  </p>

<?php elseif (empty($escola_selecionada) && !$erro_db): ?>
  <div class="alert alert-primary text-center">
      Selecione uma escola no menu acima para visualizar os indicadores RED'S.
  </div>
<?php endif; ?>

<script>
// Accordion manual (sem Bootstrap.Collapse)
document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('.resource-card');

    function fecharOutros(excecao) {
        document.querySelectorAll('.resource-expanded.show').forEach((el) => {
            if (el === excecao) return;
            el.classList.remove('show');

            const card = el.closest('.resource-card');
            if (!card) return;

            const toggle = card.querySelector('.resource-expand-toggle');
            const header = card.querySelector('.resource-title');

            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
                const label = toggle.querySelector('.toggle-label');
                const icon = toggle.querySelector('.toggle-icon i');
                const expandText = toggle.getAttribute('data-expand-label') || 'Explorar indicadores';
                if (label) label.textContent = expandText;
                if (icon) { icon.classList.add('fa-angle-down'); icon.classList.remove('fa-angle-up'); }
            }
            if (header) header.setAttribute('aria-expanded', 'false');
        });
    }

    cards.forEach((card) => {
        const collapse = card.querySelector('.resource-expanded');
        if (!collapse || !collapse.id) return;

        const selector = '#' + collapse.id;
        const toggleButton = card.querySelector('.resource-expand-toggle');
        const headerButton = card.querySelector('.resource-title');

        if (toggleButton && !toggleButton.getAttribute('data-target')) toggleButton.setAttribute('data-target', selector);
        if (headerButton && !headerButton.getAttribute('data-target')) headerButton.setAttribute('data-target', selector);

        const expandLabel = (toggleButton && toggleButton.getAttribute('data-expand-label')) || 'Explorar indicadores';
        const collapseLabel = (toggleButton && toggleButton.getAttribute('data-collapse-label')) || 'Recolher indicadores';
        const iconElement = toggleButton ? toggleButton.querySelector('.toggle-icon i') : null;
        const labelElement = toggleButton ? toggleButton.querySelector('.toggle-label') : null;

        function setState(isOpen) {
            if (isOpen) collapse.classList.add('show'); else collapse.classList.remove('show');
            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', String(isOpen));
                if (labelElement) labelElement.textContent = isOpen ? collapseLabel : expandLabel;
                if (iconElement) {
                    iconElement.classList.toggle('fa-angle-down', !isOpen);
                    iconElement.classList.toggle('fa-angle-up', isOpen);
                }
            }
            if (headerButton) headerButton.setAttribute('aria-expanded', String(isOpen));
        }

        function handleClick(e) {
            e.preventDefault();
            const isOpen = collapse.classList.contains('show');
            fecharOutros(collapse);
            setState(!isOpen);
        }

        if (toggleButton) toggleButton.addEventListener('click', handleClick);
        if (headerButton) headerButton.addEventListener('click', handleClick);

        // inicia fechado
        setState(false);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
