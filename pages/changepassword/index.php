<?php
require_once __DIR__ . '/../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

if ($user_id) {
    $query = "SELECT admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $is_admin = $row['admin'] == 1; // Define como true se o usuário for admin
    }
    $stmt->close();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
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
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="../homepage/">CritMeet</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link " href="../homepage/">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="../Profile/">Meu Perfil</a></li>
                <li class="nav-item"><a class="nav-link active" href="../rpg_info">RPG</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Mais...</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../settings/">Configurações</a></li>
                        <li><a class="dropdown-item" href="../friends/">Conexões</a></li>
                        <li><a class="dropdown-item" href="../chat/">Chat</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../components/Logout/">Logout</a></li>
                        <?php if ($is_admin): ?>
                            <li><a class="dropdown-item text-danger" href="../admin/">Lista de Usuários</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
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

        <form method="POST" action="">
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

    <?php include 'footer.php'; ?>
</body>
</html>
