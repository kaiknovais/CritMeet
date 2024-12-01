<?php
include('../../config.php');
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null;

if ($user_id) {
    $query = "SELECT * FROM users WHERE id = ?";
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? $user['name'];
    $gender = $_POST['gender'] ?? $user['gender'];
    $pronouns = $_POST['pronouns'] ?? $user['pronouns'];
    $preferences = $_POST['preferences'] ?? $user['preferences'];

    if (!empty($_FILES['image']['tmp_name'])) {
        $image = file_get_contents($_FILES['image']['tmp_name']);
        $image = base64_encode($image);
        $sql = "UPDATE users SET name = ?, gender = ?, pronouns = ?, preferences = ?, image = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('sssssi', $name, $gender, $pronouns, $preferences, $image, $user_id);
    } else {
        $sql = "UPDATE users SET name = ?, gender = ?, pronouns = ?, preferences = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ssssi', $name, $gender, $pronouns, $preferences, $user_id);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Informações do usuário atualizadas com sucesso!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Erro ao atualizar as informações do usuário.');</script>";
    }
}
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
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/index.php">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="../homepage/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/index.php">Meu Perfil</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Mais...
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../settings/index.php">Configurações</a></li>
                            <li><a class="dropdown-item" href="#">Conexões</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/index.php">Logout</a></li>
                            <!-- Apenas para administradores -->
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/index.php">Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container text-center">
        <?php if (!empty($user['image'])): ?>
            <h3>Imagem de Perfil</h3>
            <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" style="max-width: 200px; max-height: 200px;"/><br>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="file" name="image" accept="image/*" /><br>
            <input type="text" name="name" placeholder="Nome" required /><br>
            <input type="text" name="gender" placeholder="Gênero" required /><br>
            <input type="text" name="pronouns" placeholder="Pronomes" required /><br>
            <input type="text" name="preferences" placeholder="Preferências de Jogo" required ><br>
            <button type="submit">Confirmar Alterações</button><br>
        </form>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>