<?php
  $conexao = "localhost";
  $usuario = "root";
  $senha = "";
  $banco = "sistema_login";

  $conexao=mysqli_connect($conexao, $usuario, $senha, $banco);

  if (!$conexao) {

    die("Falha na conexão: " . mysqli_connect_error());
  }


?>
