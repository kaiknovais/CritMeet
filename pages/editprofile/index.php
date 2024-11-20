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
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Profile Edit</title>
</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <div>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <a class="navbar-brand" href="../homepage/index.php">
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
          <a class="nav-link active" aria-current="page" href="../homepage/index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="../../components/Profile/index.php">Meu Perfil</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            mais...
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="../settings/index.php">Configurações</a></li>
            <li><a class="dropdown-item" href="#">Another action</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../../components/Logout/index.php">Logout</a></li>
          </ul>
        </li>

      </ul>
      <form class="d-flex" role="search">
        <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
        <button class="btn btn-outline-success" type="submit">Search</button>
      </form>
    </div>
  </div>
</nav>

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