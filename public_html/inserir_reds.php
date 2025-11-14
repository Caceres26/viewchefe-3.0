<?php 
// JARVIS | NextCore: Digital Solutions
// inserir_reds.php - Formulário para Inserção de Dados RED'S (v5.1 - UI/UX Otimizada)
// AJUSTES: Lógica de CSV em Lote mantida; UI/UX aprimorada, Delimitador 'Auto' padrão, Limpeza de tags **.

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php';
check_auth(['admin', 'colaborador_nre']);

// 2. INCLUSÃO DA CONEXÃO
require_once 'db.php';

// 2.1 Helpers de semana (Opção A)
function week_anchor_monday(string $dateYmd): string {
    $dt = DateTime::createFromFormat('Y-m-d', $dateYmd) ?: new DateTime($dateYmd);
    $isoDow = (int)$dt->format('N'); // 1..7 (Mon..Sun)
    if ($isoDow !== 1) {
        $dt->modify('-' . ($isoDow - 1) . ' days');
    }
    return $dt->format('Y-m-d');
}
function week_label_iso(string $dateYmd): string {
    $dt = new DateTime($dateYmd);
    return 'Semana ' . $dt->format('W') . '/' . $dt->format('o');
}

// 3. MAPEAMENTO DE PROGRAMAS RED
$red_programs_config = [
    'Programação Paraná' => ['tabela' => 'red_programacao_parana', 'campos' => ['total_estudantes','total_exercicios','acerto_exercicios_percent','indice_exercicios_realizados','projetos_realizados','acessos_periodo','acessos_periodo_percent','frequencia_percent']],
    'Robótica Paraná' => ['tabela' => 'red_robotica_parana', 'campos' => ['alunos_matriculados','disciplinas_qtd','atividades_qtd','atribuicao_esperada','questoes_respondidas','indice_respostas','questoes_corretas','indice_acertos','acessos_unicos','acessos_unicos_percent','turmas_qtd']],
    'Matemática Paraná' => ['tabela' => 'red_matematica_parana', 'campos' => ['alunos','tarefas_concluidas','indice_tarefas_concluidas','acessos_periodo','acessos_periodo_percent','score_acertos','tempo_uso_minutos']],
    'Khan Academy' => ['tabela' => 'red_khan_academy', 'campos' => ['total_alunos','total_minutos','tempo_uso_medio_min','acessos_periodo','acessos_periodo_percent','habilidades_trabalhadas','habilidades_progresso','habilidades_progredidas']],
    'KhanMigo' => ['tabela' => 'red_khanmigo', 'campos' => ['alunos_com_licenca','alunos_ativados','uso_percent','total_interacoes','media_interacoes']],
    'Desafio Paraná' => ['tabela' => 'red_desafio_parana', 'campos' => ['alunos','atividades_qtd','semanas_qtd','atribuicao_esperada','questoes_respondidas','indice_respostas','questoes_corretas','indice_acertos','acessos_unicos','acessos_unicos_percent']],
    'Inglês Paraná Teens' => ['tabela' => 'red_ingles_teens', 'campos' => ['turmas_qtd','alunos','licoes_realizadas','indice_licoes_realizadas','acessos_periodo','acessos_periodo_percent','certificados_totais']],
    'Inglês Paraná High' => ['tabela' => 'red_ingles_high', 'campos' => ['turmas_qtd','alunos','licoes_realizadas','indice_licoes_realizadas','acessos_periodo','acessos_periodo_percent','certificados_totais']],
    'Leia Paraná' => ['tabela' => 'red_leia_parana', 'campos' => ['alunos','acessos','acessos_periodo_percent','atividades_realizadas','indice_atividades_realizadas','livros_concluidos','indice_livros_concluidos','acertos_livros_percent']],
    'Redação Paraná' => ['tabela' => 'red_redacao_parana', 'campos' => ['alunos','redacoes_propostas','redacoes_pendentes','redacoes_rascunho','redacoes_correcao_online','redacoes_enviadas_correcao','redacoes_devolvidas_reescrita','redacoes_concluidas','indice_semanal_concluidas','redacoes_inativas','redacoes_inativas_percent','corrigidas_ia','corrigidas_ia_percent','redacoes_treinadas','alunos_altas_habilidades','alunos_deficiencia','cod_mec']],
];

// =======================================================
// Download de Template CSV por Programa RED (Template Geral NRE pré-populado)
// =======================================================
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $red = $_GET['red_name'] ?? '';
    if (!isset($red_programs_config[$red])) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Programa inválido.";
        exit;
    }
    $sep = $_GET['sep'] ?? ';';
    $allowedSep = [',', ';', "\t"];
    if (!in_array($sep, $allowedSep, true)) $sep = ';';
    $useBom = isset($_GET['bom']) ? ($_GET['bom'] == '1') : true;

    // --- NOVO: BUSCA DE ESCOLAS PARA O TEMPLATE ---
    $escolas_template = [];
    try {
        // Assume as colunas cod_mec e nome
        $stmt_escolas_template = $pdo->query("SELECT cod_mec, nome FROM school_info ORDER BY nome ASC");
        $escolas_template = $stmt_escolas_template->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Erro ao carregar lista de escolas para o template. Detalhe: C-RED-TMP-DB-002";
        error_log("DOWNLOAD TEMPLATE ERRO: " . $e->getMessage());
        exit;
    }
    // ---------------------------------------------
    
    $cols = $red_programs_config[$red]['campos'] ?? [];
    $slug = preg_replace('/[^a-z0-9]+/i', '_', $red);
    $filename = "template_{$slug}.csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    if ($useBom) fwrite($out, "\xEF\xBB\xBF");
    
    // CABEÇALHO DO TEMPLATE: cod_mec e nome_escola fixos + campos de métrica
    $header_cols = array_merge(['cod_mec', 'nome_escola'], $cols);
    if ($sep === "\t") fwrite($out, implode("\t", $header_cols) . "\r\n");
    else fputcsv($out, $header_cols, $sep);

    // PREENCHIMENTO DAS LINHAS COM DADOS FIXOS DA ESCOLA
    foreach ($escolas_template as $esc) {
        // Usa 'cod_mec' e 'nome' do DB para pré-popular
        $line = [$esc['cod_mec'], $esc['nome']]; 
        // Adiciona colunas vazias para os campos de métrica
        for ($i = 0; $i < count($cols); $i++) {
            $line[] = '';
        }
        if ($sep === "\t") fwrite($out, implode("\t", $line) . "\r\n");
        else fputcsv($out, $line, $sep);
    }
    
    fclose($out);
    exit;
}

// 4. ESTRUTURA E VARIÁVEIS DE TELA
$page_title = "Inserir Indicadores RED'S";
require_once 'includes/header.php';

$feedback_mensagem = [];
$csv_preview = null;
$csv_error = false;

// Configurações de upload CSV (inalteradas)
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '6M');
ini_set('max_file_uploads', '3');

const REDS_CSV_MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB
const REDS_CSV_ALLOWED_EXTENSIONS = ['csv'];
const REDS_CSV_ALLOWED_MIME = ['text/csv', 'application/vnd.ms-excel', 'text/plain', 'application/csv'];
const REDS_CSV_PREVIEW_LIMIT = 50;

// Funções Helpers (inalteradas)
function reds_csv_strip_bom(string $value): string {
    if (substr($value, 0, 3) === "\xEF\xBB\xBF") return substr($value, 3);
    return $value;
}
function reds_csv_sanitize_field(string $value): string {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return trim($value);
}
function reds_csv_limpar_staging(): void {
    if (!empty($_SESSION['reds_csv_staging']['path']) && is_file($_SESSION['reds_csv_staging']['path'])) {
        @unlink($_SESSION['reds_csv_staging']['path']);
    }
    unset($_SESSION['reds_csv_staging']);
}
function reds_csv_gc_temp(int $maxAgeSeconds = 86400): void {
    $dir = sys_get_temp_dir(); $now = time();
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'reds_*.csv') as $file) {
        if (is_file($file) && ($now - @filemtime($file)) > $maxAgeSeconds) @unlink($file);
    }
}
function reds_csv_detect_delimiter(string $sample): string {
    $delimiters = [';', ',', "\t"]; $counts = [];
    foreach ($delimiters as $delimiter) $counts[$delimiter] = substr_count($sample, $delimiter);
    arsort($counts); return key($counts);
}

/**
 * GERA PRÉ-VISUALIZAÇÃO DE ARQUIVO CSV (AJUSTADO PARA LOTE)
 * O $expectedHeaders deve conter APENAS os campos de métrica (cod_mec e nome_escola são adicionados internamente).
 */
function reds_csv_generate_preview(string $path, string $delimiter, array $expectedHeaders, array &$feedback): ?array {
    if (!is_readable($path)) { $feedback[] = ['type' => 'danger', 'text' => 'Arquivo temporário indisponível para pré-visualização.']; return null; }
    $handle = fopen($path, 'r');
    if ($handle === false) { $feedback[] = ['type' => 'danger', 'text' => 'Não foi possível abrir o arquivo CSV para pré-visualização.']; return null; }
    $headersRaw = fgetcsv($handle, 0, $delimiter);
    if ($headersRaw === false) { fclose($handle); $feedback[] = ['type' => 'danger', 'text' => 'Arquivo CSV vazio ou inválido.']; return null; }
    if (isset($headersRaw[0]) && is_string($headersRaw[0])) $headersRaw[0] = reds_csv_strip_bom($headersRaw[0]);

    // --- NOVO: Headers Esperados incluem as 2 colunas fixas ---
    array_unshift($expectedHeaders, 'nome_escola');
    array_unshift($expectedHeaders, 'cod_mec');
    // -----------------------------------------------------------------

    $headersNormalized = array_map(fn($h) => strtolower(reds_csv_sanitize_field((string)$h)), $headersRaw);
    $expectedNormalized = array_map(fn($h) => strtolower($h), $expectedHeaders);

    if ($headersNormalized !== $expectedNormalized) {
        fclose($handle);
        $feedback[] = ['type' => 'danger', 'text' => 'Erro de Cabeçalho: Estrutura inesperada. Utilize o template fornecido (inclui cod_mec e nome_escola).'];
        return null;
    }

    $rows = []; $total = 0;
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $total++;
        if (count($data) !== count($headersRaw)) { fclose($handle); $feedback[] = ['type' => 'danger', 'text' => 'Linha com quantidade de colunas divergente do cabeçalho.']; return null; }
        if (isset($data[0]) && is_string($data[0])) $data[0] = reds_csv_strip_bom($data[0]);
        
        // Ignora linhas que parecem ser apenas colunas de cabeçalho repetidas
        if (strtolower(reds_csv_sanitize_field($data[0])) === 'cod_mec') continue;
        
        $rows[] = array_map('reds_csv_sanitize_field', $data);
        if (count($rows) >= REDS_CSV_PREVIEW_LIMIT) break;
    }
    fclose($handle);
    if ($total === 0) { $feedback[] = ['type' => 'danger', 'text' => 'Nenhuma linha de dados encontrada no arquivo CSV.']; return null; }

    return ['headers' => $headersRaw, 'rows' => $rows, 'total_rows' => $total];
}

reds_csv_gc_temp();

$erro_db = null;
$escolas_info_disp = [];
$today_date = date('Y-m-d');

try {
    // Mantemos a busca para o MODO MANUAL
    $stmt_escolas = $pdo->query("SELECT id, nome FROM school_info ORDER BY nome ASC");
    $escolas_info_disp = $stmt_escolas->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($escolas_info_disp)) $erro_db = "Nenhuma escola encontrada na tabela de referência (school_info). Não é possível inserir dados.";
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar lista de escolas: C-RED-INS-FLT-001.";
    error_log("Erro em inserir_reds.php (Filtro Escolas): " . $e->getMessage());
}

// 4. PROCESSAMENTO DO FORMULÁRIO (POST)
$mode = $_POST['mode'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erro_db) {

    if ($mode === 'csv_discard') {
        reds_csv_limpar_staging();
        $feedback_mensagem[] = ['type' => 'info', 'text' => 'Pré-visualização descartada.'];

    } elseif ($mode === 'csv_preview') {

        // --- MODO CSV: IGNORAMOS A ESCOLA SELECIONADA NO CONTEXTO ---
        $selectedSchool = null; // IGNORED FOR NRE BATCH UPLOAD
        $selectedDate = filter_input(INPUT_POST, 'csv_data_referencia', FILTER_SANITIZE_SPECIAL_CHARS);
        $selectedRed = filter_input(INPUT_POST, 'csv_red_name', FILTER_SANITIZE_SPECIAL_CHARS);
        // AJUSTE: Definição de 'auto' como valor padrão no PHP
        $delimiterChoice = $_POST['csv_delimiter'] ?? 'auto'; 

        // Validação da Data e Programa RED (inalteradas)
        if (empty($selectedDate) || !DateTime::createFromFormat('Y-m-d', $selectedDate)) { $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Informe uma data de referência válida para o upload.']; $csv_error = true; }
        if (empty($selectedRed) || !isset($red_programs_config[$selectedRed])) { $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Selecione um programa RED válido para o upload.']; $csv_error = true; }
        if (!in_array($delimiterChoice, [';', ',', '\t', 'auto'], true)) $delimiterChoice = 'auto'; // Garante valor seguro

        // Validação de Arquivo (inalteradas)
        $arquivo = $_FILES['csv_file'] ?? null;
        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Selecione um arquivo CSV.']; $csv_error = true; }
        elseif ($arquivo['error'] !== UPLOAD_ERR_OK) { $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Falha no upload (código ' . $arquivo['error'] . ').']; $csv_error = true; }
        elseif (($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > REDS_CSV_MAX_UPLOAD_SIZE) { $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Arquivo excede 5MB. Ajuste o arquivo e tente novamente.']; $csv_error = true; }
        
        if (!$csv_error) {
            $fname = $arquivo['name'] ?? '';
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $tmpPath = $arquivo['tmp_name'] ?? null;
            $mime = $tmpPath && is_file($tmpPath) ? (@mime_content_type($tmpPath) ?: ($arquivo['type'] ?? '')) : '';
            if (!in_array($ext, REDS_CSV_ALLOWED_EXTENSIONS, true) || !in_array($mime, REDS_CSV_ALLOWED_MIME, true)) {
                $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Tipo de arquivo inválido. Envie um .csv'];
                $csv_error = true;
            }
        }

        if (!$csv_error && !empty($tmpPath)) {
            $stagingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('reds_' . bin2hex(random_bytes(8)) . '.csv');
            if (!move_uploaded_file($tmpPath, $stagingPath)) {
                $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Não foi possível armazenar o arquivo temporário para pré-visualização.'];
                $csv_error = true;
            } else {
                if (!empty($_SESSION['reds_csv_staging'])) reds_csv_limpar_staging();
                // AJUSTE: Força detecção se 'auto' ou valor inválido
                $delimiterFinal = ($delimiterChoice === 'auto') ? reds_csv_detect_delimiter(file_get_contents($stagingPath, false, null, 0, 2048) ?: '') : $delimiterChoice;
                
                $expectedHeaders = $red_programs_config[$selectedRed]['campos'] ?? [];
                $preview = reds_csv_generate_preview($stagingPath, $delimiterFinal, $expectedHeaders, $feedback_mensagem);

                if ($preview) {
                    // Normaliza a data para a segunda-feira ISO
                    $data_ref_anchor = week_anchor_monday($selectedDate);
                    $_SESSION['reds_csv_staging'] = [
                        'path' => $stagingPath,
                        'red' => $selectedRed,
                        'data_ref' => $selectedDate,
                        'data_ref_anchor' => $data_ref_anchor,
                        'delimiter' => $delimiterFinal,
                        'created_at' => time(),
                        'original_name' => $fname,
                    ];
                    $csv_preview = $preview;
                    $feedback_mensagem[] = ['type' => 'info', 'text' => 'A data informada será registrada como ' . date('d/m/Y', strtotime($data_ref_anchor)) . ' (' . week_label_iso($data_ref_anchor) . ').'];
                } else {
                    @unlink($stagingPath);
                    reds_csv_limpar_staging();
                }
            }
        }

    } elseif ($mode === 'csv_import') {

        $staging = $_SESSION['reds_csv_staging'] ?? null;
        if (!$staging || empty($staging['path']) || !is_file($staging['path'])) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Nenhum arquivo em pré-visualização para importar.'];
        } else {
            $selectedRed = $staging['red'];
            if (!isset($red_programs_config[$selectedRed])) {
                $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Configuração do programa RED não encontrada.'];
                reds_csv_limpar_staging();
            } else {
                // --- AJUSTE: Otimização da Leitura do Preview ---
                $expectedHeaders = $red_programs_config[$selectedRed]['campos'];
                $preview = reds_csv_generate_preview($staging['path'], $staging['delimiter'], $expectedHeaders, $feedback_mensagem);
                
                if ($preview) {
                    $config = $red_programs_config[$selectedRed];
                    $tabela_destino = $config['tabela'];
                    $metric_fields = $config['campos']; // Somente os campos de métrica
                    $rows = $preview['rows'];
                    $imported_count = 0;
                    
                    if (empty($rows)) {
                        $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Nenhuma linha válida encontrada para importação.'];
                    } else {
                        try {
                            $pdo->beginTransaction();
                            
                            // Preparar o SQL de busca do school_info_id pelo cod_mec
                            $stmt_school_id = $pdo->prepare("SELECT id FROM school_info WHERE cod_mec = :cod_mec");

                            // Preparar a consulta de inserção/upsert
                            $colunas_db = ['school_info_id', 'data_referencia'];
                            $placeholders = [':school_id', ':data_ref'];
                            $update_parts = [];
                            
                            foreach ($metric_fields as $campo) {
                                $colunas_db[] = $campo;
                                $placeholders[] = ':' . $campo;
                                $update_parts[] = "{$campo} = VALUES({$campo})";
                            }

                            $sql_insert = "
                                INSERT INTO {$tabela_destino} (" . implode(', ', $colunas_db) . ") 
                                VALUES (" . implode(', ', $placeholders) . ")
                                ON DUPLICATE KEY UPDATE 
                                    " . implode(', ', $update_parts) . "
                            ";
                            $stmt_insert = $pdo->prepare($sql_insert);
                            
                            foreach ($rows as $row_idx => $row) {
                                // Coluna 0 (cod_mec)
                                $cod_mec = $row[0] ?? null; 
                                
                                if (empty($cod_mec)) {
                                    $feedback_mensagem[] = ['type' => 'warning', 'text' => "Linha " . ($row_idx + 2) . ": Código MEC ausente. Linha ignorada."];
                                    continue;
                                }
                                
                                // 1. Encontrar o ID interno da escola
                                $stmt_school_id->execute([':cod_mec' => $cod_mec]);
                                $school_id_interno = $stmt_school_id->fetchColumn();
                                
                                if (!$school_id_interno) {
                                    $feedback_mensagem[] = ['type' => 'warning', 'text' => "Linha " . ($row_idx + 2) . ": Código MEC '{$cod_mec}' não encontrado. Linha ignorada."];
                                    continue;
                                }

                                // 2. Montar os valores para a inserção
                                $valores = [
                                    ':school_id' => $school_id_interno,
                                    ':data_ref'  => $staging['data_ref_anchor'],
                                ];
                                
                                // Mapear os valores do CSV para as métricas (começa na coluna 2 do CSV: cod_mec=0, nome_escola=1, metrica1=2)
                                foreach ($metric_fields as $metric_idx => $campo) {
                                    $valor = $row[$metric_idx + 2] ?? null; 
                                    $placeholder_key = ':' . $campo;
                                    $valores[$placeholder_key] = ($valor === '' ? null : $valor);
                                }

                                // 3. Executar a inserção/upsert
                                $stmt_insert->execute($valores);
                                $imported_count++;
                            } // Fim do loop de linhas

                            if ($imported_count > 0) {
                                $pdo->commit();
                                $feedback_mensagem[] = ['type' => 'success', 'text' => "Importação em lote concluída: <strong>{$imported_count}</strong> registro(s) inserido(s)/atualizado(s). Semana registrada: " . date('d/m/Y', strtotime($staging['data_ref_anchor'])) . ' (' . week_label_iso($staging['data_ref_anchor']) . ').'];
                            } else {
                                $pdo->rollBack();
                                $feedback_mensagem[] = ['type' => 'info', 'text' => "Nenhuma linha válida importada. Verifique os códigos MEC e dados preenchidos."];
                            }

                            reds_csv_limpar_staging();
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $feedback_mensagem[] = ['type' => 'danger', 'text' => 'Falha crítica na importação. Detalhe: C-RED-INS-LOT-002: ' . $e->getMessage()];
                            reds_csv_limpar_staging();
                        }
                    }
                } else {
                    reds_csv_limpar_staging();
                }
            }
        }

    }

    // INSERÇÃO MANUAL (inalterada)
    if (empty($mode) || $mode === 'manual') {
        $school_id = filter_input(INPUT_POST, 'school_info_id', FILTER_VALIDATE_INT);
        $data_ref = filter_input(INPUT_POST, 'data_referencia', FILTER_SANITIZE_SPECIAL_CHARS);
        $red_nome_selecionado = filter_input(INPUT_POST, 'red_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $metrics = $_POST['metrics'] ?? [];

        if (!$school_id || !isset($escolas_info_disp[$school_id])) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Escola selecionada inválida."];
        } elseif (empty($data_ref) || !DateTime::createFromFormat('Y-m-d', $data_ref)) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Data de referência inválida."];
        } elseif (empty($red_nome_selecionado) || !isset($red_programs_config[$red_nome_selecionado])) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Programa RED selecionado inválido."];
        } elseif (empty($metrics) || !is_array($metrics)) {
            $feedback_mensagem[] = ['type' => 'danger', 'text' => "Nenhum dado de métrica recebido."];
        } else {
            try {
                $config = $red_programs_config[$red_nome_selecionado];
                $tabela_destino = $config['tabela'];
                $campos_esperados = $config['campos'];

                $data_ref_anchor = week_anchor_monday($data_ref);

                $colunas_db = ['school_info_id', 'data_referencia'];
                $placeholders = [':school_id', ':data_ref'];
                $update_parts = [];
                $valores = [':school_id' => $school_id, ':data_ref' => $data_ref_anchor];

                foreach ($campos_esperados as $campo) {
                    if (isset($metrics[$campo])) {
                        $colunas_db[] = $campo;
                        $placeholder_key = ':' . $campo;
                        $placeholders[] = $placeholder_key;
                        $valores[$placeholder_key] = ($metrics[$campo] === '') ? null : $metrics[$campo];
                        $update_parts[] = "{$campo} = VALUES({$campo})";
                    }
                }

                if (count($colunas_db) <= 2) throw new Exception("Nenhum valor de métrica válido foi fornecido.");
                
                // AJUSTE: Usar UPSERT (ON DUPLICATE KEY UPDATE) no modo manual
                $sql_insert = "INSERT INTO {$tabela_destino} (" . implode(', ', $colunas_db) . ")
                               VALUES (" . implode(', ', $placeholders) . ")
                               ON DUPLICATE KEY UPDATE 
                                    " . implode(', ', $update_parts);
                                    
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute($valores);

                $feedback_mensagem[] = ['type' => 'success', 'text' => "Dados inseridos/atualizados. Semana registrada: " . date('d/m/Y', strtotime($data_ref_anchor)) . " (" . week_label_iso($data_ref_anchor) . ")."];
                $_POST = [];

            } catch (Exception $e) {
                $feedback_mensagem[] = ['type' => 'danger', 'text' => "Erro: " . $e->getMessage()];
            } catch (PDOException $e) {
                $erro_db_insert = "Erro de DB ao inserir: C-RED-INS-001.";
                $feedback_mensagem[] = ['type' => 'danger', 'text' => $erro_db_insert . " Detalhe: " . $e->getMessage()];
                error_log("Erro em inserir_reds.php (INSERT): " . $e->getMessage());
            }
        }
    }
}

// Reabre staging (se houver) para renderizar prévia
$reds_csv_staging = $_SESSION['reds_csv_staging'] ?? null;
if (!$csv_preview && $reds_csv_staging && is_file($reds_csv_staging['path'])) {
    $expectedHeaders = $red_programs_config[$reds_csv_staging['red']]['campos'] ?? [];
    $csv_preview = reds_csv_generate_preview($reds_csv_staging['path'], $reds_csv_staging['delimiter'], $expectedHeaders, $feedback_mensagem);
    if (!$csv_preview) { reds_csv_limpar_staging(); $reds_csv_staging = null; }
}

$erro_db = $erro_db ?? null;
$today_date = $today_date ?? date('Y-m-d');
$csv_context_date = $reds_csv_staging['data_ref_anchor'] ?? ($reds_csv_staging['data_ref'] ?? ($_POST['csv_data_referencia'] ?? $today_date));
$csv_context_red = $reds_csv_staging['red'] ?? ($_POST['csv_red_name'] ?? null);
$csv_delimiter_choice = $reds_csv_staging['delimiter'] ?? ($_POST['csv_delimiter'] ?? 'auto'); // AJUSTE: Delimitador 'auto' padrão
// Campos de contexto de escola
$ctx_school_value = $_POST['school_info_id'] ?? null;
$ctx_date_value = $_POST['data_referencia'] ?? $csv_context_date ?? $today_date;
$ctx_red_value = $_POST['red_name'] ?? $csv_context_red ?? null;

$mode = $mode ?? null;
$csvAccordionOpen = in_array($mode, ['csv_preview', 'csv_import', 'csv_discard'], true) || $reds_csv_staging || $csv_preview;
$manualButtonClass = $csvAccordionOpen ? 'accordion-button collapsed' : 'accordion-button';
$manualCollapseClass = 'accordion-collapse collapse' . ($csvAccordionOpen ? '' : ' show');
$manualAriaExpanded = $csvAccordionOpen ? 'false' : 'true';
$csvButtonClass = $csvAccordionOpen ? 'accordion-button' : 'accordion-button collapsed';
$csvCollapseClass = 'accordion-collapse collapse' . ($csvAccordionOpen ? ' show' : '');
$csvAriaExpanded = $csvAccordionOpen ? 'true' : 'false';
?>

<h1 class="mb-2">Inserção de Indicadores RED’s</h1>
<p class="text-muted mb-4">Selecione o contexto e escolha o modo (Manual ou CSV).</p>

<?php if ($erro_db): ?>
    <div class="alert alert-danger"><?= $erro_db; ?></div>
<?php else: ?>

    <?php foreach ($feedback_mensagem as $msg): ?>
        <div class="alert alert-<?= $msg['type']; ?> alert-dismissible fade show" role="alert">
            <?= $msg['text']; // Mantido sem htmlspecialchars() pois já é sanitizado, exceto para <strong> que usamos agora para negrito. ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form id="ctx-form" class="row g-3">
                <div class="col-md-4">
                    <label for="ctx_school" class="form-label fw-bold">1. Escola <small class="text-muted">(Apenas para modo Manual)</small></label>
                    <select class="form-select shadow-sm" id="ctx_school" name="school_info_id">
                        <option value="" <?= empty($ctx_school_value) ? 'selected' : ''; ?>>-- Selecione para Inserção Manual --</option>
                        <?php foreach ($escolas_info_disp as $id => $nome): ?>
                            <option value="<?= $id; ?>" <?= ($ctx_school_value == $id) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($nome); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="ctx_data" class="form-label fw-bold">
                        2. Data de Referência <small class="text-muted">(qualquer dia da semana)</small>
                    </label>
                    <input type="date" class="form-control shadow-sm" id="ctx_data" name="data_referencia"
                            value="<?= htmlspecialchars($ctx_date_value ?? $today_date); ?>" required>
                    <div class="form-text" id="ctx_data_hint" style="display:none;"></div>
                </div>

                <div class="col-md-5">
                    <label for="ctx_red" class="form-label fw-bold">3. Programa RED</label>
                    <select class="form-select shadow-sm" id="ctx_red" name="red_name" required>
                        <option value="" disabled <?= empty($ctx_red_value) ? 'selected' : ''; ?>>-- Escolha um programa --</option>
                        <?php foreach (array_keys($red_programs_config) as $red_name): ?>
                            <option value="<?= htmlspecialchars($red_name); ?>" <?= ($ctx_red_value === $red_name) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($red_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="accordion" id="accordionInsertModes">

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingManual">
                <button class="<?= $manualButtonClass; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseManual" aria-expanded="<?= $manualAriaExpanded; ?>" aria-controls="collapseManual">
                    Inserção Manual (Individual)
                </button>
            </h2>
            <div id="collapseManual" class="<?= $manualCollapseClass; ?>" aria-labelledby="headingManual" data-bs-parent="#accordionInsertModes">
                <div class="accordion-body">
                    <form method="POST" action="inserir_reds.php" id="form-manual" class="needs-validation" novalidate>
                        <input type="hidden" name="mode" value="manual">
                        <input type="hidden" name="school_info_id" id="manual_school">
                        <input type="hidden" name="data_referencia" id="manual_data">
                        <input type="hidden" name="red_name" id="manual_red">

                        <div id="manual-fields-container" class="mt-2">
                            <div class="alert alert-info mb-0">Selecione Escola, Data e Programa para carregar os campos.</div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3 w-100" id="btn-save-manual" disabled>
                            <i class="fas fa-save me-1"></i>Salvar Manualmente
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingCSV">
                <button class="<?= $csvButtonClass; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCSV" aria-expanded="<?= $csvAriaExpanded; ?>" aria-controls="collapseCSV">
                    Importar por Planilha (CSV - Lote NRE)
                </button>
            </h2>
            <div id="collapseCSV" class="<?= $csvCollapseClass; ?>" aria-labelledby="headingCSV" data-bs-parent="#accordionInsertModes">
                <div class="accordion-body">

                    <form method="POST" action="inserir_reds.php" id="form-csv-preview" enctype="multipart/form-data" class="mb-3">
                        <input type="hidden" name="mode" value="csv_preview">
                        <input type="hidden" name="csv_data_referencia" id="csv_data">
                        <input type="hidden" name="csv_red_name" id="csv_red">

                        <!-- LINHA AJUSTADA PARA ALINHAMENTO -->
                        <div class="row g-3">

                            <div class="col-md-5"> 
                                <label class="form-label fw-bold" for="csv_file">Arquivo CSV</label>
                                <div class="d-flex flex-column">
                                    <input type="file" class="form-control shadow-sm" name="csv_file" id="csv_file" accept=".csv" disabled>
                                </div>
                                <div class="form-text small">O CSV deve conter <b>cod_mec</b> e <b>nome_escola</b> pré-preenchidos.</div>
                            </div>
                            
                            <div class="col-md-3"> 
                                <label class="form-label fw-bold" for="csv_delimiter">Delimitador</label>
                                <select class="form-select shadow-sm" id="csv_delimiter" name="csv_delimiter" disabled>
                                    <option value="auto" <?= ($csv_delimiter_choice === 'auto' || empty($csv_delimiter_choice)) ? 'selected' : ''; ?>>Detectar automaticamente</option>
                                    <option value=";" <?= ($csv_delimiter_choice === ';') ? 'selected' : ''; ?>>Ponto e vírgula (;)</option>
                                    <option value="," <?= ($csv_delimiter_choice === ',') ? 'selected' : ''; ?>>Vírgula (,)</option>
                                    <option value="\t" <?= ($csv_delimiter_choice === "\t") ? 'selected' : ''; ?>>Tabulação</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Label “fake” para alinhar verticalmente com os inputs acima -->
                                <label class="form-label fw-bold d-none d-md-block">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-secondary flex-grow-1" id="btn-preview" disabled>
                                        <i class="fas fa-search me-1"></i>Pré-visualizar
                                    </button>
                                    <a class="btn btn-outline-primary flex-grow-1" id="btn-template" href="#" target="_blank" tabindex="-1" aria-disabled="true">
                                        <i class="fas fa-download me-1"></i>Baixar Template
                                    </a>
                                </div>
                            </div>

                        </div>
                    </form>

                    <?php if (!empty($_SESSION['reds_csv_staging'])): ?>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body d-flex align-items-center justify-content-between flex-column flex-lg-row gap-3">
                                <div class="text-center text-lg-start">
                                    <strong>Pré-visualização pronta.</strong>
                                    <span class="text-muted ms-lg-2 d-block d-lg-inline">Importe os registros válidos ou descarte para recomeçar.</span>
                                </div>
                                <div class="d-flex gap-2 flex-column flex-sm-row">
                                    <form method="POST" action="inserir_reds.php">
                                        <input type="hidden" name="mode" value="csv_import">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-file-import me-1"></i>Importar Registros Válidos
                                        </button>
                                    </form>
                                    <form method="POST" action="inserir_reds.php">
                                        <input type="hidden" name="mode" value="csv_discard">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="fas fa-trash-alt me-1"></i>Descartar Prévia
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($csv_preview && $reds_csv_staging): ?>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-transparent border-0">
                                <h2 class="h5 mb-1">Pré-visualização do CSV</h2>
                                <p class="small text-muted mb-0">
                                    <?= htmlspecialchars($reds_csv_staging['original_name'] ?? 'Arquivo carregado'); ?> — <?= (int) $csv_preview['total_rows']; ?> linha(s) encontrada(s).
                                </p>
                                <span class="badge text-bg-light mt-2">Programa: <?= htmlspecialchars($reds_csv_staging['red']); ?> · Data: <?= htmlspecialchars($reds_csv_staging['data_ref_anchor'] ?? $reds_csv_staging['data_ref']); ?> (<?= htmlspecialchars(week_label_iso($reds_csv_staging['data_ref_anchor'] ?? $reds_csv_staging['data_ref'])); ?>)</span>
                            </div>
                            <div class="card-body pt-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Cód. MEC</th> 
                                                <th>Nome Escola</th> 
                                                <?php 
                                                $metric_headers = $red_programs_config[$reds_csv_staging['red']]['campos'] ?? []; 
                                                foreach ($metric_headers as $header): 
                                                ?>
                                                    <th><?= htmlspecialchars($header); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($csv_preview['rows'] as $index => $row): ?>
                                                <tr class="<?= $index === 0 ? 'table-info' : ''; ?>">
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?= htmlspecialchars($value); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($csv_preview['total_rows'] > count($csv_preview['rows'])): ?>
                                    <p class="small text-muted mt-2 mb-0">Prévia limitada às primeiras <?= count($csv_preview['rows']); ?> linhas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-light border small">
                        <div class="fw-bold mb-1">Instruções do CSV (Importação Lote NRE)</div>
                        <ul class="mb-0">
                            <li>Use o <b>template</b> baixado, que já contém <b>cod_mec</b> e <b>nome_escola</b> pré-preenchidos.</li>
                            <li>A importação processará <b>todas as linhas</b> preenchidas.</li>
                            <li>Se um registro (Escola + Semana) já existir, ele será <b>atualizado</b> (Upsert).</li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>

    </div>

<?php endif; ?>

<script>
/** Configurações vinda do PHP **/
const redProgramsConfig = <?= json_encode($red_programs_config); ?>;
const previousMetrics = <?= json_encode($_POST['metrics'] ?? []); ?>;

/** Estado do contexto **/
function ctxReady() {
    const date = document.getElementById('ctx_data')?.value;
    const red = document.getElementById('ctx_red')?.value;
    return Boolean(date && red);
}
function ctxReadyManual() {
    const school = document.getElementById('ctx_school')?.value;
    return Boolean(school && ctxReady());
}
function syncContextToForms() {
    const school = document.getElementById('ctx_school')?.value || '';
    const date = document.getElementById('ctx_data')?.value || '';
    const red = document.getElementById('ctx_red')?.value || '';

    const readyCSV = ctxReady();
    const readyManual = ctxReadyManual();
    
    // CSV forms (dependem apenas de Data e RED)
    ['csv'].forEach(prefix => {
        const d = document.getElementById(prefix + '_data');
        const r = document.getElementById(prefix + '_red');
        if (d) d.value = date;
        if (r) r.value = red;
    });

    // Manual forms (dependem de Escola, Data e RED)
    ['manual'].forEach(prefix => {
        const s = document.getElementById(prefix + '_school');
        const d = document.getElementById(prefix + '_data');
        const r = document.getElementById(prefix + '_red');
        if (s) s.value = school;
        if (d) d.value = date;
        if (r) r.value = red;
    });


    const btnManual = document.getElementById('btn-save-manual');
    const csvFile = document.getElementById('csv_file');
    const csvDelimiter = document.getElementById('csv_delimiter');
    const btnPreview = document.getElementById('btn-preview');
    const templateButton = document.getElementById('btn-template');

    if (btnManual) btnManual.disabled = !readyManual;
    if (csvFile) csvFile.disabled = !readyCSV;
    if (csvDelimiter) csvDelimiter.disabled = !readyCSV;
    if (btnPreview) btnPreview.disabled = !readyCSV;

    if (templateButton) {
        if (readyCSV) {
            const redName = encodeURIComponent(red);
            templateButton.href = 'inserir_reds.php?action=download_template&red_name=' + redName + '&sep=%3B&bom=1';
            templateButton.setAttribute('aria-disabled', 'false');
            templateButton.removeAttribute('tabindex');
        } else {
            templateButton.href = '#';
            templateButton.setAttribute('aria-disabled', 'true');
            templateButton.setAttribute('tabindex', '-1');
        }
    }
}
function weekAnchorLabel(ymd) {
    if (!ymd) return '';
    const base = new Date(ymd + 'T00:00:00');
    if (Number.isNaN(base.getTime())) return '';
    const day = base.getDay(); // 0=Dom..6=Sáb
    const diff = (day === 0 ? 6 : day - 1);
    const monday = new Date(base);
    monday.setDate(base.getDate() - diff);
    return monday.toLocaleDateString('pt-BR');
}
function renderDataHint() {
    const input = document.getElementById('ctx_data');
    const hint = document.getElementById('ctx_data_hint');
    if (!hint) return;
    const value = input?.value;
    if (value) {
        hint.style.display = 'block';
        hint.textContent = 'Será registrada como: ' + weekAnchorLabel(value) + ' (Segunda-feira da semana).';
    } else {
        hint.style.display = 'none';
        hint.textContent = '';
    }
}
function loadManualFields() {
    const container = document.getElementById('manual-fields-container');
    if (!container) return;
    container.innerHTML = '';
    if (!ctxReadyManual()) {
        container.innerHTML = '<div class="alert alert-info mb-0">Selecione Escola, Data e Programa para carregar os campos.</div>';
        return;
    }
    const red = document.getElementById('ctx_red')?.value;
    if (!red || !redProgramsConfig[red]) {
        container.innerHTML = '<div class="alert alert-warning mb-0">Programa RED não configurado.</div>';
        return;
    }
    const campos = redProgramsConfig[red].campos || [];
    if (!campos.length) {
        container.innerHTML = '<div class="alert alert-warning mb-0">Não há campos configurados para este programa.</div>';
        return;
    }
    const grid = document.createElement('div');
    grid.className = 'row g-3';
    campos.forEach((fieldName) => {
        const col = document.createElement('div'); col.className = 'col-md-4';
        const label = document.createElement('label'); label.className = 'form-label'; label.htmlFor = 'metric_' + fieldName;
        let friendly = fieldName.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        if (fieldName.includes('percent')) friendly += ' (%)';
        if (fieldName.includes('indice')) friendly += ' (Índice)';
        if (fieldName.endsWith('_min') || fieldName.includes('minutos')) friendly += ' (Minutos)';
        label.textContent = friendly;

        const input = document.createElement('input');
        input.type = 'text'; input.className = 'form-control form-control-sm shadow-sm';
        input.id = 'metric_' + fieldName; input.name = 'metrics[' + fieldName + ']'; input.placeholder = 'Insira o valor';
        if (previousMetrics && Object.prototype.hasOwnProperty.call(previousMetrics, fieldName)) input.value = previousMetrics[fieldName];

        col.appendChild(label); col.appendChild(input); grid.appendChild(col);
    });
    container.appendChild(grid);
}
function initContextSync() {
    syncContextToForms(); loadManualFields(); renderDataHint();
    ['ctx_school','ctx_data','ctx_red'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => { syncContextToForms(); loadManualFields(); renderDataHint(); });
    });
}
function initManualValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(form => {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
            form.classList.add('was-validated');
        }, false);
    });
}

/** Nova proteção do botão de template **/
function initTemplateButtonGuard() {
    const templateButton = document.getElementById('btn-template');
    if (!templateButton) return;

    templateButton.addEventListener('click', function (e) {
        const red = document.getElementById('ctx_red')?.value;
        const date = document.getElementById('ctx_data')?.value;

        if (!red) {
            e.preventDefault();
            alert('Selecione um programa RED para baixar o template do CSV.');
            return;
        }

        // Opcional: exigir data também
        if (!date) {
            e.preventDefault();
            alert('Selecione uma data de referência antes de baixar o template.');
            return;
        }
    });
}

/** Fallback de Accordion (inalterado) **/
function initAccordionFallback() {
    const hasBootstrap = (typeof bootstrap !== 'undefined' && bootstrap.Collapse);
    if (hasBootstrap) return; 

    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetSel = this.getAttribute('data-bs-target');
            if (!targetSel) return;
            const target = document.querySelector(targetSel);
            const parentSel = target?.getAttribute('data-bs-parent');
            const isShown = target?.classList.contains('show');

            if (parentSel) {
                document.querySelectorAll(parentSel + ' .accordion-collapse').forEach(p => {
                    if (p !== target) {
                        p.classList.remove('show');
                        const headerBtn = p.previousElementSibling?.querySelector('.accordion-button');
                        if (headerBtn) {
                            headerBtn.classList.add('collapsed');
                            headerBtn.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            }
            if (target) {
                if (isShown) {
                    target.classList.remove('show');
                    this.classList.add('collapsed');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    target.classList.add('show');
                    this.classList.remove('collapsed');
                    this.setAttribute('aria-expanded', 'true');
                }
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initContextSync();
    initManualValidation();
    initAccordionFallback();
    initTemplateButtonGuard();
});
</script>

<?php 
require_once 'includes/footer.php'; 
?>
