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
    <link rel="stylesheet" href="../../styles.css">
</head>
<body>
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
</body>
</html>
