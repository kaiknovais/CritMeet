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
            <input type="text" name="name" placeholder="Nome" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required /><br>
            <input type="text" name="gender" placeholder="Gênero" value="<?php echo isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : ''; ?>" required /><br>
            <input type="text" name="pronouns" placeholder="Pronomes" value="<?php echo isset($_POST['pronouns']) ? htmlspecialchars($_POST['pronouns']) : ''; ?>" required /><br>
            <input type="email" name="email" placeholder="Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required /><br>
            <input type="password" name="password" placeholder="Senha" required /><br>
            <input type="password" name="confirm_password" placeholder="Confirmar Senha" required /><br>
            <button type="submit">Registrar</button><br>
        </form>

        <?php
        include '../../components/NavBack/index.php';
        ?>
    </div>
    <?php include 'footer.php'; ?>
    
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = $_POST["name"];
        $gender = $_POST["gender"];
        $pronouns = $_POST["pronouns"];
        $email = $_POST["email"];
        $password = $_POST["password"];

        if ($password !== $_POST["confirm_password"]) {
            echo "<script>alert('As senhas não coincidem.');</script>";
            exit();
        }

        $stmt = $mysqli->prepare("INSERT INTO users (name, gender, pronouns, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $gender, $pronouns, $email, $password);

        try {
            if ($stmt->execute()) {
                echo "Cadastro realizado com sucesso!";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) { 
                echo "Erro: O e-mail já está cadastrado.";
            } else {
                echo "Erro ao cadastrar: " . $e->getMessage();
            }
        } finally {
            $stmt->close(); // Fecha a declaração
        }
    }
    ?>
</body>
</html>

