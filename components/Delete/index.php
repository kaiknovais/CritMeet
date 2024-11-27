<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

if (isset($_POST['delete_user'])) {
    $user_id = $_SESSION['user_id'];

    $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        session_destroy();
        header("Location: ../../login/index.php");
        exit();
    } else {
        echo "Erro ao excluir a conta: " . $stmt->error;
    }
}
?>