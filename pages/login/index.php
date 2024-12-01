<?php
include('../../config.php');

session_start();

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

        if ($user['password'] === $senha) { 
            $_SESSION['user_id'] = $user['id'];
            header("Location: ../homepage/index.php"); 
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
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>CritMeet</h1><br>
        <form method="POST" action="">
            <input type="text" name="email" placeholder="Email ou Username" required /><br>
            <input type="password" name="senha" placeholder="Senha" required /><br>
            <button type="submit">Entrar</button><br>
        </form>
        <a href="../register/index.php">
            <button type="button">Cadastre-se</button><br>
        </a>
        <button type="button">Google</button>
        
        <?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
