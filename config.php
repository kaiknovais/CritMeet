<?php
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "critmeet";

$mysqli = new mysqli($host, $usuario, $senha, $banco);

if ($mysqli->error) {
    die("Falha ao conectar no Banco de Dados: " . $mysqli->error);
}
?>