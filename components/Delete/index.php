<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_SESSION['user_id'];

    if ($stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?")) {
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            session_destroy();
            header("Location: ../../pages/home/");
            exit();
        } else {
            echo "Erro ao excluir a conta: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Erro na preparação do SQL: " . $mysqli->error;
    }
}


$mysqli->close();
?>