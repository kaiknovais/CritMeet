<?php
include('../../config.php');

session_start();
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $gender = $_POST["gender"];
    $pronouns = $_POST["pronouns"];
    $preferences = $_POST["preferences"];
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image = $_FILES['image']['tmp_name'];
        $imageData = file_get_contents($image);
        $base64 = base64_encode($imageData);

        $sql = "UPDATE users SET name='$name', gender='$gender', pronouns='$pronouns', preferences='$preferences', image='$base64' WHERE id='$user_id'";
    } else {
        $sql = "UPDATE users SET name='$name', gender='$gender', pronouns='$pronouns', preferences='$preferences' WHERE id='$user_id'";
    }

    if ($mysqli->query($sql) === TRUE) {
        echo "Perfil atualizado com sucesso!";
    } else {
        echo "Erro ao atualizar perfil: " . $mysqli->error;
    }
}

$sql = "SELECT name, gender, pronouns, preferences, image FROM users WHERE id='$user_id'";
$result = $mysqli->query($sql);
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Edit</title>
</head>
<body>
    <div>
        <h1>CritMeet</h1><br>

        <!-- Exibir a imagem se existir -->
        <?php if (!empty($user['image'])): ?>
            <h2>Imagem de Perfil</h2>
            <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" style="max-width: 200px; max-height: 200px;"/><br>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="file" name="image" accept="image/*" required /><br>
            <input type="text" name="name" placeholder="Nome" value="<?php echo htmlspecialchars($user['name']); ?>" required /><br>
            <input type="text" name="gender" placeholder="Gênero" value="<?php echo htmlspecialchars($user['gender']); ?>" required /><br>
            <input type="text" name="pronouns" placeholder="Pronomes" value="<?php echo htmlspecialchars($user['pronouns']); ?>" required /><br>
            <input type="text" name="preferences" placeholder="Preferências de Jogo" value="<?php echo htmlspecialchars($user['preferences']); ?>" required /><br>
            <button type="submit">Confirmar Alterações</button><br>
            <button onclick="window.location.href='../settings/index.php'">Voltar</button><br>
        </form>
    </div>
</body>
</html>