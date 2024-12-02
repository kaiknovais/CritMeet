<?php
include('../../config.php');
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Consultar pedidos de amizade pendentes ---
$sql_pending = "SELECT f.id, u.username, u.name 
                FROM friends f 
                JOIN users u ON f.user_id = u.id 
                WHERE f.friend_id = ? AND f.status = 'pending'";
$stmt_pending = $mysqli->prepare($sql_pending);
$stmt_pending->bind_param("i", $user_id);
$stmt_pending->execute();
$pending_result = $stmt_pending->get_result();

// --- Confirmar amizade ---
if (isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ?";
    $stmt_accept = $mysqli->prepare($sql_accept);
    $stmt_accept->bind_param("i", $friendship_id);
    $stmt_accept->execute();
    echo "<script>alert('Amizade confirmada!'); window.location.reload();</script>";
}

// --- Consultar lista de amigos ---
$sql_friends = "SELECT u.username, u.name 
                FROM friends f 
                JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id) 
                WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?";
$stmt_friends = $mysqli->prepare($sql_friends);
$stmt_friends->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_friends->execute();
$friends_result = $stmt_friends->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amigos</title>
    <link rel="stylesheet" href="../../styles.css">
</head>
<body>
    <h1>Amigos</h1>
    <!-- Lista de Amigos -->
    <section>
        <h2>Meus Amigos</h2>
        <ul>
            <?php while ($friend = $friends_result->fetch_assoc()): ?>
                <li>
                    <?php echo htmlspecialchars($friend['name']); ?> (<?php echo htmlspecialchars($friend['username']); ?>)
                </li>
            <?php endwhile; ?>
        </ul>
    </section>

    <!-- Solicitações de Amizade -->
    <section>
        <h2>Solicitações de Amizade Pendentes</h2>
        <ul>
            <?php while ($pending = $pending_result->fetch_assoc()): ?>
                <li>
                    <?php echo htmlspecialchars($pending['name']); ?> (<?php echo htmlspecialchars($pending['username']); ?>)
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="friendship_id" value="<?php echo $pending['id']; ?>">
                        <button type="submit" name="accept_friend">Aceitar</button>
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    </section>
</body>
</html>
