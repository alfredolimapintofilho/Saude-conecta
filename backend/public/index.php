<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Responde imediatamente a requisições Preflight do navegador (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../controllers/AuthController.php';

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Roteamento Simples
if ($request_uri === '/api/register' && $method === 'POST') {
    AuthController::register();
} elseif ($request_uri === '/api/login' && $method === 'POST') {
    AuthController::login();
} elseif ($request_uri === '/api/status' && $method === 'GET') {
    echo json_encode(["status" => "online", "projeto" => "Saúde Conecta Patos-PB"]);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Rota não encontrada."]);
}

C:\xampp\htdocs\saude-conecta\