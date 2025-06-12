<?php
require_once __DIR__ . '/../../config.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>CritMeet</h1><br>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required /><br>
            <input type="text" name="name" placeholder="Nome" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required /><br>
            <input type="text" name="gender" placeholder="Gênero" value="<?php echo isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : ''; ?>" required /><br>
            <input type="text" name="pronouns" placeholder="Pronomes" value="<?php echo isset($_POST['pronouns']) ? htmlspecialchars($_POST['pronouns']) : ''; ?>" required /><br>
            <input type="email" name="email" placeholder="Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required /><br>
            <input type="password" name="password" placeholder="Senha" required /><br>
            <input type="password" name="confirm_password" placeholder="Confirmar Senha" required /><br>
            <button type="submit">Registrar</button><br>
            <a href="../login/">
                <button type="button">Voltar</button><br>
            </a>
        </form>

    </div>
    <?php include 'footer.php'; ?>
    
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST["username"];
        $name = $_POST["name"];
        $gender = $_POST["gender"];
        $pronouns = $_POST["pronouns"];
        $email = $_POST["email"];
        $password = $_POST["password"];

        if ($password !== $_POST["confirm_password"]) {
            echo "<script>alert('As senhas não coincidem.');</script>";
            exit();
        }

        $stmt = $mysqli->prepare("INSERT INTO users (username, name, gender, pronouns, email, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $name, $gender, $pronouns, $email, $password);

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
