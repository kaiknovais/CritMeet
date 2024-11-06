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
        header("Location: ../../login/index.php?message=Conta excluída com sucesso.");
        exit();
    } else {
        echo "Erro ao excluir a conta: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações</title>
    <script>
        function confirmDelete() {
            const confirmation = confirm("Tem certeza que deseja deletar sua conta?");
            if (confirmation) {
                document.getElementById('delete_form').submit(); // Envia o formulário para deletar a conta
            }
        }
    </script>
</head>
<body>
    <div>
        <h1>CritMeet</h1><br>
        <h2>Configurações</h2>
        <button type="button" onclick="confirmDelete()">Deletar Conta</button><br>
        
        <form id="delete_form" method="POST" action="">
            <input type="hidden" name="delete_user" value="1">
        </form>
    </div>
</body>
</html>