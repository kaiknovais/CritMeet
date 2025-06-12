<?php
require_once __DIR__ . '/../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null; 

if ($user_id) {
    $query = "SELECT username, image, gender, pronouns, preferences, admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $user = $row;
        $is_admin = $row['admin'] == 1; 
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil de Usuário</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <li class="nav-item"><a class="nav-link active" href="../Profile/">Meu Perfil</a></li>
                <li class="nav-item"><a class="nav-link" href="../rpg_info">RPG</a></li>
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

<div class="profile-container">
    <?php if (!empty($user['image'])): ?>
        <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" class="profile-image" />
    <?php else: ?>
        <img src="default-avatar.png" alt="Imagem de Perfil Padrão" class="profile-image" />
    <?php endif; ?>
    <h2><?php echo htmlspecialchars($user['username']); ?> <?php if ($is_admin): ?><span class="badge bg-danger">Admin</span><?php endif; ?></h2>
    <table class="table profile-table">
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

<?php include 'footer.php'; ?>
</body>
</html>
