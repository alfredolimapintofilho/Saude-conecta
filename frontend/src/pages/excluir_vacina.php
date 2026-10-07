<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/models/Vacina.php';


$usuario = $_SESSION['usuario'];

$usuarioId = null;

if (is_array($usuario)) {

    if (isset($usuario['id'])) {

        $usuarioId = (int) $usuario['id'];

    } elseif (isset($usuario['id_usuario'])) {

        $usuarioId =
            (int) $usuario['id_usuario'];
    }
}


if (!$usuarioId) {

    die(
        'Erro: usuário não identificado.'
    );

}


$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (!$id) {

    header(
        'Location: cartao_vacinacao.php'
    );

    exit;
}


try {

    $vacinaModel =
        new Vacina($conn);


    $vacina =
        $vacinaModel->buscarPorId(
            $id,
            $usuarioId
        );


    if (!$vacina) {

        die(
            'Registro de vacinação não encontrado.'
        );

    }


    $vacinaModel->excluir(
        $id,
        $usuarioId
    );


    header(
        'Location: cartao_vacinacao.php'
    );

    exit;


} catch (Throwable $e) {

    die(
        'Erro ao excluir vacinação: '
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );

}