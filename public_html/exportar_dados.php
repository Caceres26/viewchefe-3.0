<?php
// JARVIS | NextCore: Digital Solutions
// exportar_dados.php - Módulo de Exportação de Relatórios para CSV

// 1. SEGURANÇA E INÍCIO DA SESSÃO
require_once 'includes/header_security.php'; 
// REQUISITO RBAC: Apenas Admin ou Colaborador podem exportar dados brutos
check_auth(['admin', 'colaborador']); 

require_once 'db.php'; 

// 2. TRATAMENTO DOS FILTROS (Recebidos via GET)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
define('DELIMITER', ';'); // Padrão Excel BR

// 3. CONSULTA SQL PARA EXPORTAÇÃO
$sql = "
    SELECT 
        escola, 
        data_referencia, 
        periodo, 
        matriculados, 
        presentes, 
        faltas, 
        faltas_justificadas,
        criado_em
    FROM 
        school_metrics 
    WHERE 
        data_referencia BETWEEN :data_inicio AND :data_fim
    ORDER BY 
        data_referencia ASC, escola ASC;
";

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim,
];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. TRATAMENTO DE ESTADO VAZIO (Prática NextCore)
    if (empty($dados)) {
        // Redireciona de volta ao dashboard com uma mensagem de erro amigável
        $_SESSION['feedback_export'] = [
            'type' => 'warning', 
            'text' => "Nenhum dado encontrado para exportação no período de {$data_inicio} a {$data_fim}."
        ];
        header("Location: metricas.php"); 
        exit;
    }

    // 5. PREPARAÇÃO DO HEADER HTTP PARA DOWNLOAD FORÇADO
    $nome_arquivo = "nextcore_metricas_{$data_inicio}_a_{$data_fim}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
    
    // Define o BOM (Byte Order Mark) para UTF-8, garantindo acentuação correta no Excel
    echo "\xEF\xBB\xBF"; 

    // Abre o ponteiro de saída
    $output = fopen('php://output', 'w');

    // 6. CABEÇALHO DO CSV (Títulos das Colunas)
    // Pega os nomes das colunas do primeiro resultado
    $headers = array_keys($dados[0]); 
    // Formata para "Nome Amigavel"
    $friendly_headers = array_map(fn($h) => str_replace('_', ' ', strtoupper($h)), $headers);
    fputcsv($output, $friendly_headers, DELIMITER);

    // 7. ESCRITA DOS DADOS (LINHA A LINHA)
    foreach ($dados as $row) {
        fputcsv($output, $row, DELIMITER);
    }

    // 8. FECHAMENTO
    fclose($output);
    exit;

} catch (PDOException $e) {
    // Tratamento de erro DB
    $_SESSION['feedback_export'] = ['type' => 'danger', 'text' => "Erro no DB ao gerar exportação: C-EXP-001."];
    error_log("Erro na exportação de dados: " . $e->getMessage());
    header("Location: metricas.php"); 
    exit;
}
?>