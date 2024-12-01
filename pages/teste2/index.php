<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Consultar pedidos de amizade pendentes
$sql = "SELECT f.id, u.username, u.name 
        FROM friends f 
        JOIN users u ON f.user_id = u.id 
        WHERE f.friend_id = ? AND f.status = 'pending'";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Confirmar amizade
if (isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ?";
    $stmt = $mysqli->prepare($sql_accept);
    $stmt->bind_param("i", $friendship_id);
    $stmt->execute();
    header('Location: ' . $_SERVER['PHP_SELF']);
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Pedido de Amizade</title>
</head>
<body>

<h2>Pedidos de Amizade Pendentes</h2>
<ul>
<?php while ($row = $result->fetch_assoc()): ?>
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
