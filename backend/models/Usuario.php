<?php

class Usuario {
    private $conn;
    private $table_name = "usuarios";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Verificar se CPF já está cadastrado
    public function cpfExiste($cpf) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE cpf = :cpf LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":cpf", $cpf);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Verificar se Email já está cadastrado
    public function emailExiste($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Cadastrar Cidadão
    public function criar($dados) {
        try {
            $this->conn->beginTransaction();

            // Inserir Usuário
            $query = "INSERT INTO " . $this->table_name . " 
                        (cpf, nome_completo, email, senha_hash, telefone, data_nascimento, cartao_sus, tipo_usuario) 
                      VALUES 
                        (:cpf, :nome_completo, :email, :senha_hash, :telefone, :data_nascimento, :cartao_sus, 'cidadao')";

            $stmt = $this->conn->prepare($query);

            $senha_hash = password_hash($dados['senha'], PASSWORD_BCRYPT);
            $cartao_sus = !empty($dados['cartaoSus']) ? $dados['cartaoSus'] : null;

            $stmt->bindParam(":cpf", $dados['cpf']);
            $stmt->bindParam(":nome_completo", $dados['nomeCompleto']);
            $stmt->bindParam(":email", $dados['email']);
            $stmt->bindParam(":senha_hash", $senha_hash);
            $stmt->bindParam(":telefone", $dados['telefone']);
            $stmt->bindParam(":data_nascimento", $dados['dataNascimento']);
            $stmt->bindParam(":cartao_sus", $cartao_sus);

            $stmt->execute();
            $usuario_id = $this->conn->lastInsertId();

            // Registrar consentimento LGPD
            $query_lgpd = "INSERT INTO lgpd_termos_consentimento (usuario_id, termo_versao, ip_acesso) VALUES (:usuario_id, '1.0', :ip)";
            $stmt_lgpd = $this->conn->prepare($query_lgpd);
            $ip_acesso = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            $stmt_lgpd->bindParam(":usuario_id", $usuario_id);
            $stmt_lgpd->bindParam(":ip", $ip_acesso);
            $stmt_lgpd->execute();

            $this->conn->commit();
            return $usuario_id;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Buscar Usuário por CPF para Login
    public function buscarPorCpf($cpf) {
        $query = "SELECT id, cpf, nome_completo, email, senha_hash, tipo_usuario FROM " . $this->table_name . " WHERE cpf = :cpf LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":cpf", $cpf);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }
}