<?php
include('../../config.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar</title>
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>CritMeet</h1><br>
        <form method="POST" action="">
            <input type="text" name="name" placeholder="Nome" required /><br>
            <input type="text" name="gender" placeholder="Gênero" required /><br>
            <input type="text" name="pronouns" placeholder="Pronomes" required /><br>
            <input type="email" name="email" placeholder="Email" required /><br>
            <input type="password" name="password" placeholder="Senha" required /><br>
            <input type="password" name="confirm_password" placeholder="Confirmar Senha" required /><br>
            <button type="submit">Registrar</button><br>
        </form>
        <button onclick="window.location.href='../login/index.php'">Voltar</button><br>
    </div>
    <?php include 'footer.php'; ?>
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = $_POST["name"];
        $gender = $_POST["gender"];
        $pronouns = $_POST["pronouns"];
        $email = $_POST["email"];
        $password = $_POST["password"];

        $sql = "INSERT INTO users (name, gender, pronouns, email, password) 
                VALUES ('$name', '$gender', '$pronouns', '$email', '$password')";
        if ($mysqli->query($sql) === TRUE) {
            echo "Cadastro realizado com sucesso!";
        } else {
            echo "Erro ao cadastrar: " . $mysqli->error;
        }
    }
    ?>
</body>
</html>