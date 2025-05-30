<?php
include('../../config.php');
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/");
    exit();
}

// Verifica se o ID do usuário a ser excluído foi passado via GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_delete = $_GET['id'];

    // Prepara a consulta SQL para excluir o usuário
    if ($stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?")) {
        $stmt->bind_param("i", $user_id_to_delete);

        // Executa a exclusão
        if ($stmt->execute()) {
            // Redireciona automaticamente para a página de administração
            header("Location: ../../pages/admin/");
            exit();
        } else {
            // Caso ocorra um erro, exibe mensagem e redireciona para a página de administração
            echo "<p>Erro ao excluir o usuário: " . $stmt->error . "</p>";
            header("Location: ../../pages/admin/");
            exit();
        }

    } else {
        // Caso ocorra erro na preparação da consulta SQL
        echo "<p>Erro na preparação do SQL: " . $mysqli->error . "</p>";
        header("Location: ../../pages/admin/");
        exit();
    }
} else {
    // Caso o ID não seja válido ou não seja fornecido
    echo "<p>ID inválido ou não fornecido.</p>";
    header("Location: ../../pages/admin/");
    exit();
}
?>