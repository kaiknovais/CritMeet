<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

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

// Aceitar amizade
if (isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ?";
    $stmt = $mysqli->prepare($sql_accept);
    $stmt->bind_param("i", $friendship_id);
    $stmt->execute();
    header("Location: ../friends/index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amigos e Pedidos Pendentes</title>
</head>
<body>

<h2>Meus Amigos</h2>
<ul>
<?php while ($row = $result_friends->fetch_assoc()): ?>
    <li>
        <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)
        <!-- Botão para abrir o chat -->
        <a href="../message/index.php?friend_id=<?php echo $row['id']; ?>">Abrir Chat</a>
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

</body>
</html>
