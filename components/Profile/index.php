<?php
include('../../config.php');
session_start();

$user_id = $_SESSION['user_id'];

$sql = "SELECT name, image FROM users WHERE id='$user_id'";
$result = $mysqli->query($sql);
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <title>Perfil do Usuário</title>
</head>
<body>
    <div class="profile">
        <?php if (!empty($user['image'])): ?>
            <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" />
        <?php else: ?>
            <img src="default-avatar.png" alt="Imagem de Perfil Padrão" /> 
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
    </div>
</body>
</html>