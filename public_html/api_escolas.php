<?php
// JARVIS | NextCore: Digital Solutions
// api_escolas.php - Endpoint de API para carregar lista de escolas

require_once 'includes/header_security.php';
// check_auth(); // Recomenda-se manter esta linha, se a lista de escolas for sensível
require_once 'db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Consulta mínima para obter apenas a lista
    $stmt = $pdo->query("SELECT DISTINCT escola FROM school_metrics ORDER BY escola ASC");
    $escolas = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    
    echo json_encode($escolas);

} catch(PDOException $e) {
    // Loga o erro, mas retorna um array vazio ou mensagem de erro padrão
    error_log("API ESCOLAS: ".$e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao buscar dados no servidor.']);
}