<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Exemplo de Rota Básica
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($request_uri === '/api/status') {
    echo json_encode([
        "status" => "online",
        "projeto" => "Saúde Conecta Patos-PB",
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Rota não encontrada"]);
}