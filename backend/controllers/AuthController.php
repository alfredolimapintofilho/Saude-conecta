<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';

class AuthController {

    public static function register() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validações de campos obrigatórios
        if (
            empty($data['cpf']) || empty($data['nomeCompleto']) || 
            empty($data['email']) || empty($data['senha']) || 
            empty($data['telefone']) || empty($data['dataNascimento'])
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Todos os campos obrigatórios devem ser preenchidos."]);
            return;
        }

        if (empty($data['aceitaLgpd'])) {
            http_response_code(400);
            echo json_encode(["message" => "Você precisa aceitar os termos da LGPD para se cadastrar."]);
            return;
        }

        $db = Database::getConnection();
        $usuario = new Usuario($db);

        // Limpa formatação do CPF (remove pontos e traços)
        $cpfLimpo = preg_replace('/\D/', '', $data['cpf']);
        $data['cpf'] = $cpfLimpo;

        if (strlen($cpfLimpo) !== 11) {
            http_response_code(400);
            echo json_encode(["message" => "CPF inválido."]);
            return;
        }

        // Verifica duplicidade
        if ($usuario->cpfExiste($cpfLimpo)) {
            http_response_code(409);
            echo json_encode(["message" => "O CPF informado já está cadastrado."]);
            return;
        }

        if ($usuario->emailExiste($data['email'])) {
            http_response_code(409);
            echo json_encode(["message" => "O e-mail informado já está cadastrado."]);
            return;
        }

        // Tenta cadastrar
        $novoId = $usuario->criar($data);

        if ($novoId) {
            http_response_code(201);
            echo json_encode([
                "message" => "Cadastro realizado com sucesso!",
                "usuario_id" => $novoId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno ao cadastrar cidadão. Tente novamente."]);
        }
    }

    public static function login() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['cpf']) || empty($data['senha'])) {
            http_response_code(400);
            echo json_encode(["message" => "Informe o CPF e a senha."]);
            return;
        }

        $db = Database::getConnection();
        $usuarioModel = new Usuario($db);

        $cpfLimpo = preg_replace('/\D/', '', $data['cpf']);
        $user = $usuarioModel->buscarPorCpf($cpfLimpo);

        if (!$user || !password_verify($data['senha'], $user['senha_hash'])) {
            http_response_code(401);
            echo json_encode(["message" => "CPF ou senha incorretos."]);
            return;
        }

        // Em uma aplicação real, aqui gera-se um Token JWT
        http_response_code(200);
        echo json_encode([
            "message" => "Login realizado com sucesso!",
            "usuario" => [
                "id" => $user['id'],
                "nome" => $user['nome_completo'],
                "email" => $user['email'],
                "tipo" => $user['tipo_usuario']
            ]
        ]);
    }
}