
<?php

class AdotecaClient
{
    private string $endpoint = 'https://api.adoteca.com.br/mcp';
    private ?string $sessionId = null;
    private int $requestId = 0;

    private function requisitar(array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'A extensão cURL não está habilitada no PHP.'
            );
        }

        $metodo = $payload['method'] ?? '';

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream'
        ];

        if ($this->sessionId !== null) {
            $headers[] = 'Mcp-Session-Id: ' . $this->sessionId;
        }

        $corpoEnvio = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        $curl = curl_init($this->endpoint);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $corpoEnvio,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => true
        ]);

        $resposta = curl_exec($curl);

        if ($resposta === false) {
            $erro = curl_error($curl);
            curl_close($curl);

            throw new RuntimeException(
                'Falha de conexão com a Adoteca: ' . $erro
            );
        }

        $codigo = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        $tamanhoCabecalho = (int) curl_getinfo(
            $curl,
            CURLINFO_HEADER_SIZE
        );

        $cabecalhos = substr(
            $resposta,
            0,
            $tamanhoCabecalho
        );

        $corpo = trim(substr(
            $resposta,
            $tamanhoCabecalho
        ));

        curl_close($curl);

        // Guarda o identificador de sessão, quando enviado.
        if (preg_match(
            '/^Mcp-Session-Id:\s*(.+)$/im',
            $cabecalhos,
            $match
        )) {
            $this->sessionId = trim($match[1]);
        }

        // Notificações MCP não precisam devolver um corpo.
        if (
            $codigo === 202 &&
            $corpo === '' &&
            str_starts_with($metodo, 'notifications/')
        ) {
            return [];
        }

        if ($codigo < 200 || $codigo >= 300) {
            throw new RuntimeException(
                'Erro HTTP ' . $codigo .
                ' ao chamar ' . $metodo . '. Resposta: ' .
                substr($corpo, 0, 300)
            );
        }

        // JSON convencional.
        if ($corpo !== '') {
            $dados = json_decode($corpo, true);

            if (is_array($dados)) {
                return $dados;
            }
        }

        // Respostas SSE: pode haver mais de um evento.
        $eventos = preg_split('/\r?\n/', $corpo);
        $dadosEvento = [];
        $respostasSse = [];

        foreach ($eventos as $linha) {
            if (str_starts_with($linha, 'data:')) {
                $dadosEvento[] = ltrim(substr($linha, 5));
            }

            if (trim($linha) === '' && $dadosEvento) {
                $textoEvento = implode("\n", $dadosEvento);
                $evento = json_decode($textoEvento, true);

                if (is_array($evento)) {
                    $respostasSse[] = $evento;
                }

                $dadosEvento = [];
            }
        }

        if ($dadosEvento) {
            $evento = json_decode(
                implode("\n", $dadosEvento),
                true
            );

            if (is_array($evento)) {
                $respostasSse[] = $evento;
            }
        }

        if ($respostasSse) {
            // Procura a resposta JSON-RPC que corresponde à requisição.
            $idEsperado = $payload['id'] ?? null;

            foreach ($respostasSse as $evento) {
                if (
                    isset($evento['id']) &&
                    $evento['id'] === $idEsperado
                ) {
                    return $evento;
                }
            }

            return end($respostasSse);
        }

        throw new RuntimeException(
            'Resposta sem conteúdo interpretável. HTTP: ' .
            $codigo . '; método: ' . $metodo .
            '. Verifique o protocolo MCP exigido pelo servidor.'
        );
    }

    public function buscarPets(
        string $cidade = 'Patos',
        string $estado = 'PB',
        string $especie = '',
        int $limite = 24
    ): array {
        // 1. Inicializa a sessão MCP.
        $inicio = $this->requisitar([
            'jsonrpc' => '2.0',
            'id' => ++$this->requestId,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new stdClass(),
                'clientInfo' => [
                    'name' => 'Saude-Conecta',
                    'version' => '1.0.0'
                ]
            ]
        ]);

        if (isset($inicio['error'])) {
            throw new RuntimeException(
                $inicio['error']['message'] ??
                'Não foi possível inicializar a sessão MCP.'
            );
        }

        // 2. A notificação pode responder HTTP 202 sem corpo.
        $this->requisitar([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
            'params' => new stdClass()
        ]);

        // 3. Define os filtros da busca.
        $argumentos = [
            'city' => $cidade,
            'state' => $estado,
            'limit' => max(1, min(24, $limite)),
            'page' => 1
        ];

        if (in_array($especie, ['dog', 'cat'], true)) {
            $argumentos['species'] = $especie;
        }

        // 4. Solicita os anúncios.
        $resposta = $this->requisitar([
            'jsonrpc' => '2.0',
            'id' => ++$this->requestId,
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_pets',
                'arguments' => $argumentos
            ]
        ]);

        if (isset($resposta['error'])) {
            throw new RuntimeException(
                $resposta['error']['message'] ??
                'A busca de animais retornou um erro.'
            );
        }

        if (!isset($resposta['result'])) {
            throw new RuntimeException(
                'A API respondeu, mas não retornou o campo result. ' .
                'Resposta recebida: ' .
                substr(json_encode($resposta), 0, 500)
            );
        }

        if (!empty($resposta['result']['isError'])) {
            throw new RuntimeException(
                'A ferramenta search_pets retornou um erro.'
            );
        }

        return $resposta['result'];
    }
}