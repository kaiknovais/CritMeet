<?php
include('../../config.php');
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Variáveis para mensagens
$success_message = '';
$error_message = '';

// Processa o formulário de alteração de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura os dados do formulário e remove espaços em branco
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Verificar se as senhas novas coincidem
    if ($new_password !== $confirm_password) {
        $error_message = "As senhas novas não coincidem.";
    } else {
        // Buscar a senha atual do banco de dados
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error_message = "Usuário não encontrado.";
        } else {
            $user = $result->fetch_assoc();

            // Verificar se a senha atual fornecida é válida
            if ($current_password !== $user['password']) {
                $error_message = "A senha atual está incorreta.";
            } else {
                // A senha atual está correta, agora vamos atualizar a senha
                // Atualizar a senha no banco de dados
                $sql_update = "UPDATE users SET password = ? WHERE id = ?";
                $stmt_update = $mysqli->prepare($sql_update);
                $stmt_update->bind_param("si", $new_password, $user_id);

                if ($stmt_update->execute()) {
                    $success_message = "Senha alterada com sucesso!";
                } else {
                    $error_message = "Ocorreu um erro ao tentar alterar a senha. Tente novamente.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha</title>
    <link rel="stylesheet" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <h2>Alterar Senha</h2>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <div class="mb-3">
                <label for="current_password" class="form-label">Senha Atual</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label">Nova Senha</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">Alterar Senha</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
