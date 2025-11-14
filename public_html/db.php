<?php
// JARVIS | NextCore: Digital Solutions
// db.php (v2.1 - Limpo, sem espaços)

// === CONFIGURAÇÃO DE CONEXÃO ===
$host = 'localhost';
$banco = 'u807583559_appchefe';
$usuario = 'u807583559_dbchefe';
$senha = '@Nextcore2025@';

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$banco;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $usuario, $senha, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    die("Erro Interno de Sistema. Contate o suporte. Código: C-DB-001");
}
// (Nenhum espaço ou linha extra abaixo)
?>