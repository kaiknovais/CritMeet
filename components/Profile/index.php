<?php
include('../../config.php');
session_start();

$user_id = $_SESSION['user_id'];

// Fetch user data
$sql = "SELECT name, image, gender, pronouns, preferences FROM users WHERE id='$user_id'";
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

<nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="../../pages/homepage/">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-dice-5-fill" viewBox="0 0 16 16">
                <path d="M3 0a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V3a3 3 0 0 0-3-3zm2.5 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m8 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M12 13.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3M5.5 12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M8 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3"/>
            </svg>
            CritMeet
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="../../pages/homepage/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../components/Profile/">Meu Perfil</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        mais...
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../../pages/settings/">Configurações</a></li>
                        <li><a class="dropdown-item" href="../../pages/friends/">Conexões</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../components/Logout/">Logout</a></li>
                    </ul>
                </li>
            </ul>
            <form class="d-flex" role="search" action="../search" method="GET">
                <input class="form-control me-2" type="search" placeholder="Bucar amigos" aria-label="Search">
                <button class="button2" type="submit">Buscar</button>
            </form>
        </div>
    </div>
</nav>

<div class="profile-container">
    <?php if (!empty($user['image'])): ?>
        <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" />
    <?php else: ?>
        <img src="default-avatar.png" alt="Imagem de Perfil Padrão" />
    <?php endif; ?>
    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
    <table class="table profile-table">
        <tr>
            <th>Gênro:</th>
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
