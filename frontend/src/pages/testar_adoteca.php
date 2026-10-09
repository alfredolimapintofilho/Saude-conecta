
<?php

require_once __DIR__ .
    '/../../../backend/services/AdotecaClient.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $adoteca = new AdotecaClient();

    $resultado = $adoteca->buscarPets(
        'Patos',
        'PB',
        '',
        10
    );

    echo "INTEGRAÇÃO COM A ADOTECA\n";
    echo "========================\n\n";
    echo "A requisição foi processada.\n\n";

    echo json_encode(
        $resultado,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_THROW_ON_ERROR
    );

} catch (Throwable $e) {
    http_response_code(502);

    echo "A INTEGRAÇÃO AINDA NÃO FOI CONFIRMADA\n";
    echo "=====================================\n\n";

    echo "Tipo do erro: " .
        get_class($e) . "\n";

    echo "Mensagem: " .
        $e->getMessage() . "\n\n";

    echo "Verifique também:\n";
    echo "- Se o Apache e o PHP do XAMPP estão funcionando.\n";
    echo "- Se a extensão cURL está habilitada no PHP.\n";
    echo "- Se o endereço da API está acessível.\n";
    echo "- Se o protocolo MCP exigido pela API corresponde ao cliente.\n";

    error_log(
        '[Saude-Conecta / Adoteca] ' .
        get_class($e) . ': ' . $e->getMessage()
    );
}