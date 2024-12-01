<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Consultar amigos aceitos
$sql = "SELECT u.username, u.name 
        FROM friends f 
        JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id) 
        WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Amigos</title>
</head>
<body>

<h2>Meus Amigos</h2>
<ul>
<?php while ($row = $result->fetch_assoc()): ?>
    <li>
        <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)
    </li>
<?php endwhile; ?>
</ul>

</body>
</html>
