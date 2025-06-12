<?php
require_once __DIR__ . '/../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

if ($user_id) {
    $query = "SELECT admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $is_admin = $row['admin'] == 1; // Define como true se o usuário for admin
    }
    $stmt->close();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Pesquisa de usuários ---
$search_query = '';
$search_result = [];
if (isset($_GET['search'])) {  // Mudança de POST para GET
    $search_query = $_GET['search'];
    $sql = "SELECT id, username, name FROM users WHERE username LIKE ? AND id != ?";
    $stmt = $mysqli->prepare($sql);
    $search_term = "$search_query%";
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

// --- Aceitar amizade ---
if (isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];

    // Verificar se o pedido está pendente e pertence ao usuário atual
    $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ? AND status = 'pending'";
    $stmt_accept = $mysqli->prepare($sql_accept);
    $stmt_accept->bind_param("ii", $friendship_id, $user_id);
    if ($stmt_accept->execute()) {
        echo "<script>alert('Solicitação de amizade aceita!');</script>";
    } else {
        echo "<script>alert('Erro ao aceitar a solicitação.');</script>";
    }
}

// Consultar amigos aceitos
$sql_friends = "SELECT u.id, u.username, u.name 
                FROM friends f 
                JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id) 
                WHERE (f.user_id = ? OR f.friend_id = ?) 
                AND f.status = 'accepted' 
                AND u.id != ?";
$stmt = $mysqli->prepare($sql_friends);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result_friends = $stmt->get_result();

// Consultar solicitações pendentes
$sql_pending = "SELECT f.id, u.username, u.name 
                FROM friends f 
                JOIN users u ON f.user_id = u.id 
                WHERE f.friend_id = ? AND f.status = 'pending'";
$stmt = $mysqli->prepare($sql_pending);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_pending = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Amigos</title>
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
                <li class="nav-item"><a class="nav-link" href="../Profile/">Meu Perfil</a></li>
                <li class="nav-item"><a class="nav-link " href="../rpg_info">RPG</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">Mais...</a>
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

    <h3>Buscar Amigos</h3>
    <form method="GET">
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

    <h2>Meus Amigos</h2>
    <ul>
        <?php while ($row = $result_friends->fetch_assoc()): ?>
            <li>
                <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)
                <a href="../../components/ViewProfile/?id=<?= $row['id'] ?>"><button class="view">Ver Perfil</button></a>
            </li>
        <?php endwhile; ?>
    </ul>

    <h2>Solicitações de Amizade Pendentes</h2>
    <ul>
        <?php while ($row = $result_pending->fetch_assoc()): ?>
            <li>
                <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="friendship_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="accept_friend">Aceitar</button>
                </form>
            </li>
        <?php endwhile; ?>
    </ul>

    <?php include 'footer.php'; ?>
</body>
</html>
