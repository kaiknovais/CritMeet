<?php
include('../../config.php');
session_start();

if (!isset($_GET['id'])) {
    echo "ID do usuário não fornecido.";
    exit;
}

$user_id = $_GET['id'];

// Atualizando a consulta para incluir id, email e admin
$sql = "SELECT id, email, name, image, gender, pronouns, preferences, admin FROM users WHERE id='$user_id'";
$result = $mysqli->query($sql);

if ($result->num_rows == 0) {
    echo "Usuário não encontrado.";
    exit;
}

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Perfil do Usuário</title>
</head>
<body>
<?php include 'header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<div class="profile-container">
    <?php if (!empty($user['image'])): ?>
        <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" />
    <?php else: ?>
        <img src="default-avatar.png" alt="Imagem de Perfil Padrão" />
    <?php endif; ?>
    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
    <table class="table profile-table">
        <tr>
            <th>ID:</th>
            <td><?php echo htmlspecialchars($user['id']); ?></td>
        </tr>
        <tr>
            <th>Email:</th>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
        </tr>
        <tr>
            <th>Admin:</th>
            <td><?php echo $user['admin'] == 1 ? 'Sim' : 'Não'; ?></td>
        </tr>
        <tr>
            <th>Gênero:</th>
            <td><?php echo htmlspecialchars($user['gender']); ?></td>
        </tr>
        <tr>
            <th>Pronomes:</th>
            <td><?php echo htmlspecialchars($user['pronouns']); ?></td>
        </tr>
        <tr>
            <th>Preferências de Jogo:</th>
            <td><?php echo htmlspecialchars($user['preferences']); ?></td>
        </tr>
    </table>
</div>
<button type="button" onclick="window.history.back()">Voltar</button>
<?php
    include 'footer.php'; ?>
</body>
</html>
