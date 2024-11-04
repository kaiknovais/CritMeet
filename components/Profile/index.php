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
    <title>Perfil do Usuário</title>
    <style>
        body {
            font-family: Arial, sans-serif; /* Fonte padrão */
            background-color: #f4f4f4; /* Cor de fundo suave */
            margin: 0;
            padding: 20px;
        }
        .profile {
            position: fixed; /* Muda para fixed para permanecer no canto ao rolar a página */
            top: 20px; /* Distância do topo */
            right: 20px; /* Distância da direita */
            display: flex;
            align-items: center;
            background-color: white; /* Fundo branco para o perfil */
            border-radius: 8px; /* Bordas arredondadas */
            padding: 10px; /* Espaçamento interno */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Sombra */
            width: 150px; /* Largura fixa */
        }
        .profile img {
            max-width: 60px; /* Imagem aumentada para 60px */
            max-height: 60px; /* Imagem aumentada para 60px */
            border-radius: 50%; 
            margin-right: 10px; 
        }
        h2 {
            margin: 0; /* Remove margem padrão */
            font-size: 20px; /* Tamanho da fonte do nome ajustado */
        }
    </style>
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