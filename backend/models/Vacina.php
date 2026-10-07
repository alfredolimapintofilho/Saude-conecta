<?php

declare(strict_types=1);

class Vacina
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista todas as vacinas de um usuário.
     */
    public function listarPorUsuario(int $usuarioId): array
    {
        $sql = "
            SELECT
                id,
                usuario_id,
                grupo,
                vacina,
                data_aplicacao,
                dose,
                lote,
                estrategia,
                cnes,
                estabelecimento,
                municipio,
                uf,
                observacao,
                criado_em,
                atualizado_em
            FROM vacinacoes
            WHERE usuario_id = :usuario_id
            ORDER BY data_aplicacao DESC, id DESC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':usuario_id' => $usuarioId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma vacinação específica.
     */
    public function buscarPorId(
        int $id,
        int $usuarioId
    ): ?array {
        $sql = "
            SELECT *
            FROM vacinacoes
            WHERE id = :id
              AND usuario_id = :usuario_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    /**
     * Verifica se já existe exatamente o mesmo registro.
     */
    public function existeRegistro(
        int $usuarioId,
        array $dados
    ): bool {
        $sql = "
            SELECT id
            FROM vacinacoes
            WHERE usuario_id = :usuario_id
              AND grupo = :grupo
              AND vacina = :vacina
              AND data_aplicacao = :data_aplicacao
              AND COALESCE(dose, '') = COALESCE(:dose, '')
              AND COALESCE(lote, '') = COALESCE(:lote, '')
              AND COALESCE(cnes, '') = COALESCE(:cnes, '')
              AND COALESCE(estabelecimento, '') = COALESCE(:estabelecimento, '')
              AND COALESCE(municipio, '') = COALESCE(:municipio, '')
              AND COALESCE(uf, '') = COALESCE(:uf, '')
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':grupo' => $dados['grupo'] ?? '',
            ':vacina' => $dados['vacina'] ?? '',
            ':data_aplicacao' => $dados['data_aplicacao'] ?? null,
            ':dose' => $dados['dose'] ?? null,
            ':lote' => $dados['lote'] ?? null,
            ':cnes' => $dados['cnes'] ?? null,
            ':estabelecimento' => $dados['estabelecimento'] ?? null,
            ':municipio' => $dados['municipio'] ?? null,
            ':uf' => $dados['uf'] ?? null,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Cadastra uma vacinação.
     */
    public function cadastrar(array $dados): int
    {
        $sql = "
            INSERT INTO vacinacoes (
                usuario_id,
                grupo,
                vacina,
                data_aplicacao,
                dose,
                lote,
                estrategia,
                cnes,
                estabelecimento,
                municipio,
                uf,
                observacao
            ) VALUES (
                :usuario_id,
                :grupo,
                :vacina,
                :data_aplicacao,
                :dose,
                :lote,
                :estrategia,
                :cnes,
                :estabelecimento,
                :municipio,
                :uf,
                :observacao
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':usuario_id' => $dados['usuario_id'],
            ':grupo' => $dados['grupo'],
            ':vacina' => $dados['vacina'],
            ':data_aplicacao' => $dados['data_aplicacao'],
            ':dose' => $dados['dose'] ?? null,
            ':lote' => $dados['lote'] ?? null,
            ':estrategia' => $dados['estrategia'] ?? null,
            ':cnes' => $dados['cnes'] ?? null,
            ':estabelecimento' => $dados['estabelecimento'] ?? null,
            ':municipio' => $dados['municipio'] ?? null,
            ':uf' => $dados['uf'] ?? null,
            ':observacao' => $dados['observacao'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza uma vacinação.
     */
    public function atualizar(
        int $id,
        int $usuarioId,
        array $dados
    ): bool {
        $sql = "
            UPDATE vacinacoes
            SET
                grupo = :grupo,
                vacina = :vacina,
                data_aplicacao = :data_aplicacao,
                dose = :dose,
                lote = :lote,
                estrategia = :estrategia,
                cnes = :cnes,
                estabelecimento = :estabelecimento,
                municipio = :municipio,
                uf = :uf,
                observacao = :observacao
            WHERE id = :id
              AND usuario_id = :usuario_id
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId,
            ':grupo' => $dados['grupo'],
            ':vacina' => $dados['vacina'],
            ':data_aplicacao' => $dados['data_aplicacao'],
            ':dose' => $dados['dose'] ?? null,
            ':lote' => $dados['lote'] ?? null,
            ':estrategia' => $dados['estrategia'] ?? null,
            ':cnes' => $dados['cnes'] ?? null,
            ':estabelecimento' => $dados['estabelecimento'] ?? null,
            ':municipio' => $dados['municipio'] ?? null,
            ':uf' => $dados['uf'] ?? null,
            ':observacao' => $dados['observacao'] ?? null,
        ]);
    }

    /**
     * Exclui uma vacinação.
     */
    public function excluir(
        int $id,
        int $usuarioId
    ): bool {
        $sql = "
            DELETE FROM vacinacoes
            WHERE id = :id
              AND usuario_id = :usuario_id
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId
        ]);
    }

    /**
     * Conta quantas vacinações existem.
     */
    public function contarPorUsuario(
        int $usuarioId
    ): int {
        $sql = "
            SELECT COUNT(*)
            FROM vacinacoes
            WHERE usuario_id = :usuario_id
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':usuario_id' => $usuarioId
        ]);

        return (int) $stmt->fetchColumn();
    }
}