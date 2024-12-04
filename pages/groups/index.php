<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Criação de grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    $group_name = trim($_POST['group_name']);

    if (!empty($group_name)) {
        $sql_create_group = "INSERT INTO chats (is_group, name, creator_id) VALUES (1, ?, ?)";
        $stmt = $mysqli->prepare($sql_create_group);
        $stmt->bind_param("si", $group_name, $user_id);
        $stmt->execute();
        $chat_id = $stmt->insert_id;

        // Adicionar criador como admin
        $sql_add_member = "INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'admin')";
        $stmt_member = $mysqli->prepare($sql_add_member);
        $stmt_member->bind_param("ii", $chat_id, $user_id);
        $stmt_member->execute();
    }
}

// Consultar grupos em que o usuário participa
$sql_groups = "SELECT c.id, c.name, c.creator_id 
               FROM chats c 
               JOIN chat_members cm ON c.id = cm.chat_id 
               WHERE cm.user_id = ? AND c.is_group = 1";
$stmt = $mysqli->prepare($sql_groups);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos</title>
</head>
<body>
<h2>Meus Grupos</h2>

<!-- Listar Grupos -->
<ul>
    <?php while ($row = $result->fetch_assoc()): ?>
        <li>
            <?php echo htmlspecialchars($row['name']); ?>
            <a href="../groupmessage/index.php?chat_id=<?php echo $row['id']; ?>">Entrar no Chat</a>
            <?php if ($row['creator_id'] == $user_id): ?>
                <a href="../editgroup/index.php?chat_id=<?php echo $row['id']; ?>">Editar</a>
            <?php endif; ?>
        </li>
    <?php endwhile; ?>
</ul>

<!-- Criar Grupo -->
<h3>Criar Novo Grupo</h3>
<form method="POST">
    <input type="text" name="group_name" placeholder="Nome do Grupo" required>
    <button type="submit">Criar</button>
</form>
</body>
</html>
