<?php
// Inclua o arquivo de configuração do banco de dados
include('../../config.php');
session_start();

// Verifique se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit();
}

// Prepare a consulta SQL para deletar o usuário
if (isset($_POST['delete_user'])) {
    $user_id = $_SESSION['user_id'];

    // Prepare a consulta SQL
    $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    // Execute a consulta
    if ($stmt->execute()) {
        // Destrua a sessão e redirecione para a página de login
        session_destroy();
        header("Location: ../login/index.php?message=Conta excluída com sucesso.");
        exit();
    } else {
        echo "Erro ao excluir a conta: " . $stmt->error;
    }
}
?>