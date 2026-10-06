<?php

class Usuario
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }


    // =========================================================
    // VERIFICAR SE CPF EXISTE
    // =========================================================

    public function cpfExiste($cpf)
    {
        $sql = "
            SELECT id
            FROM usuarios
            WHERE cpf = :cpf
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':cpf' => $cpf
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }


    // =========================================================
    // VERIFICAR SE E-MAIL EXISTE
    // =========================================================

    public function emailExiste($email)
    {
        $sql = "
            SELECT id
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':email' => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }


    // =========================================================
    // CRIAR USUÁRIO
    // =========================================================

    public function criar($dados)
    {
        // Criptografa a senha
        $senhaHash = password_hash(
            $dados['senha'],
            PASSWORD_DEFAULT
        );


        $sql = "
            INSERT INTO usuarios
            (
                nome_completo,
                cpf,
                data_nascimento,
                email,
                telefone,
                cartao_sus,
                senha_hash,
                tipo_usuario
            )
            VALUES
            (
                :nome_completo,
                :cpf,
                :data_nascimento,
                :email,
                :telefone,
                :cartao_sus,
                :senha_hash,
                :tipo_usuario
            )
        ";


        $stmt = $this->db->prepare($sql);


        $stmt->execute([

            ':nome_completo' =>
                $dados['nomeCompleto'],

            ':cpf' =>
                $dados['cpf'],

            ':data_nascimento' =>
                $dados['dataNascimento'],

            ':email' =>
                $dados['email'],

            ':telefone' =>
                $dados['telefone'],

            ':cartao_sus' =>
                $dados['cartaoSus'],

            ':senha_hash' =>
                $senhaHash,

            ':tipo_usuario' =>
                'cidadao'
        ]);


        return $this->db->lastInsertId();
    }


    // =========================================================
    // BUSCAR USUÁRIO POR E-MAIL
    // =========================================================

    public function buscarPorEmail($email)
    {
        $sql = "
            SELECT
                id,
                nome_completo,
                cpf,
                data_nascimento,
                email,
                telefone,
                cartao_sus,
                senha_hash,
                criado_em,
                tipo_usuario
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ";


        $stmt = $this->db->prepare($sql);


        $stmt->execute([
            ':email' => $email
        ]);


        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // =========================================================
    // AUTENTICAR POR E-MAIL
    // =========================================================

    public function autenticarPorEmail($email, $senha)
    {
        $usuario = $this->buscarPorEmail($email);


        // E-mail não encontrado
        if (!$usuario) {
            return false;
        }


        // Verificar se existe senha_hash
        if (
            !isset($usuario['senha_hash']) ||
            empty($usuario['senha_hash'])
        ) {
            return false;
        }


        // Verificar senha
        if (
            !password_verify(
                $senha,
                $usuario['senha_hash']
            )
        ) {
            return false;
        }


        return $usuario;
    }


    // =========================================================
    // BUSCAR POR CPF
    // =========================================================

    public function buscarPorCpf($cpf)
    {
        $sql = "
            SELECT
                id,
                nome_completo,
                cpf,
                data_nascimento,
                email,
                telefone,
                cartao_sus,
                senha_hash,
                criado_em,
                tipo_usuario
            FROM usuarios
            WHERE cpf = :cpf
            LIMIT 1
        ";


        $stmt = $this->db->prepare($sql);


        $stmt->execute([
            ':cpf' => $cpf
        ]);


        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // =========================================================
    // ALTERAR SENHA
    // =========================================================

    public function alterarSenha($id, $novaSenha)
    {
        // Criptografa a nova senha
        $senhaHash = password_hash(
            $novaSenha,
            PASSWORD_DEFAULT
        );


        $sql = "
            UPDATE usuarios
            SET senha_hash = :senha_hash
            WHERE id = :id
        ";


        $stmt = $this->db->prepare($sql);


        return $stmt->execute([

            ':senha_hash' =>
                $senhaHash,

            ':id' =>
                $id
        ]);
    }
}