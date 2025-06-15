<?php
require_once __DIR__ . '/../../config.php';

session_start();

// Capturar mensagem de sucesso do registro
$success_message = '';
if (isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); // Remover da sessão após capturar
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST['email'];
    $senha = $_POST['senha'];

    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $login);
    } else {
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $login);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // CORREÇÃO: Verificar se a senha é hash ou texto plano
        if (password_verify($senha, $user['password'])) {
            // Senha com hash - método correto
            $_SESSION['user_id'] = $user['id'];
            header("Location: ../homepage/"); 
            exit();
        } elseif ($user['password'] === $senha) {
            // Senha em texto plano (para compatibilidade com dados antigos)
            // OPCIONAL: Atualizar para hash na próxima oportunidade
            $_SESSION['user_id'] = $user['id'];
            
            // Atualizar senha para hash (recomendado)
            $hashed_password = password_hash($senha, PASSWORD_DEFAULT);
            $update_stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['id']);
            $update_stmt->execute();
            
            header("Location: ../homepage/"); 
            exit();
        } else {
            $error = "Senha incorreta.";
        }
    } else {
        $error = "Usuário não encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>CritMeet</h1><br>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div><?php echo $success_message; ?></div>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="text" name="email" placeholder="Email ou Username" required /><br>
            <input type="password" name="senha" placeholder="Senha" required /><br>
            <button type="submit">Entrar</button><br>
        </form>
        <a href="../register/">
            <button type="button">Cadastre-se</button><br>
        </a>
        <button type="button">Google</button>
        
        <?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>