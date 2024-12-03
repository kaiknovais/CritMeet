<?php
include('../../config.php');
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Pesquisa de usuários ---
$searchQuery = $_GET['search'];
$search_query = '';
$search_result = [];
if (isset($_POST['search'])) {
    $search_query = $_POST['search'];
    $sql = "SELECT id, username, name FROM users WHERE username LIKE ? AND id != ?";
    $stmt = $mysqli->prepare($sql);
    $search_term = "%$search_query%";
    $stmt->bind_param("si", $search_term, $user_id);
    $stmt->execute();
    $search_result = $stmt->get_result();
}

// --- Adicionar amigo ---
if (isset($_POST['add_friend'])) {
    $friend_id = $_POST['friend_id'];

    // Verifica se já existe relação de amizade ou pedido pendente
    $sql_check = "SELECT id FROM friends WHERE (user_id = ? AND friend_id = ? OR user_id = ? AND friend_id = ?) AND status IN ('accepted', 'pending')";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows == 0) {
        $sql_add = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
        $stmt_add = $mysqli->prepare($sql_add);
        $stmt_add->bind_param("ii", $user_id, $friend_id);
        $stmt_add->execute();
        echo "<script>alert('Pedido de amizade enviado!');</script>";
    } else {
        echo "<script>alert('Você já é amigo ou há um pedido pendente.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Amigos</title>
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
                            <li><a class="dropdown-item" href="../friends/index.php">Conexões</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/index.php">Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/index.php">Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>

                <form class="d-flex" action="../search" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Buscar amigos..." aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Buscar</button>
                </form>
            </div>
        </div>
    </nav>

    <h1>Buscar Amigos</h1>
    <form method="POST">
        <input type="text" name="search" placeholder="Buscar usuário" value="<?php echo htmlspecialchars($search_query); ?>" style="width: 100%; padding: 8px;">
        <button type="submit" style="margin-top: 10px;">Pesquisar</button>
    </form>

    <div class="search-results" style="margin-top: 20px;">
        <ul>
            <?php if (!empty($search_result)): ?>
                <?php while ($search = $search_result->fetch_assoc()): ?>
                    <li>
                        <?php echo htmlspecialchars($search['name']); ?> (<?php echo htmlspecialchars($search['username']); ?>)
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="friend_id" value="<?php echo $search['id']; ?>">
                            <button type="submit" name="add_friend">Adicionar</button>
                        </form>
                    </li>
                <?php endwhile; ?>
            <?php else: ?>
                <li>Nenhum usuário encontrado.</li>
            <?php endif; ?>
        </ul>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
