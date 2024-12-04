<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$chat_id = $_GET['chat_id'] ?? null;

if (!$chat_id) {
    echo "<script>alert('ID do grupo não fornecido.'); window.location.href='group.php';</script>";
    exit();
}

// Verificar se o usuário é criador ou admin do grupo
$sql_check_role = "SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ?";
$stmt = $mysqli->prepare($sql_check_role);
$stmt->bind_param("ii", $chat_id, $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'admin') {
    echo "<script>alert('Você não tem permissão para editar este grupo.'); window.location.href='group.php';</script>";
    exit();
}

// Atualizar nome do grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    $group_name = trim($_POST['group_name']);

    if (!empty($group_name)) {
        $sql_update_name = "UPDATE chats SET name = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql_update_name);
        $stmt->bind_param("si", $group_name, $chat_id);
        $stmt->execute();
    }
}

// Adicionar membro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_member_id'])) {
    $new_member_id = intval($_POST['new_member_id']);

    $sql_add_member = "INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'member')";
    $stmt = $mysqli->prepare($sql_add_member);
    $stmt->bind_param("ii", $chat_id, $new_member_id);
    $stmt->execute();
}

// Remover membro
if (isset($_GET['remove_member_id'])) {
    $remove_member_id = intval($_GET['remove_member_id']);

    $sql_remove_member = "DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?";
    $stmt = $mysqli->prepare($sql_remove_member);
    $stmt->bind_param("ii", $chat_id, $remove_member_id);
    $stmt->execute();
}

// Listar membros
$sql_members = "SELECT u.id, u.username, cm.role 
                FROM users u 
                JOIN chat_members cm ON u.id = cm.user_id 
                WHERE cm.chat_id = ?";
$stmt_members = $mysqli->prepare($sql_members);
$stmt_members->bind_param("i", $chat_id);
$stmt_members->execute();
$result_members = $stmt_members->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Grupo</title>
</head>
<body>
<h2>Editar Grupo</h2>

<!-- Formulário para editar o nome -->
<form method="POST">
    <input type="text" name="group_name" placeholder="Novo Nome do Grupo" required>
    <button type="submit">Atualizar Nome</button>
</form>

<!-- Listar membros -->
<h3>Membros</h3>
<ul>
    <?php while ($row = $result_members->fetch_assoc()): ?>
        <li>
            <?php echo htmlspecialchars($row['username']); ?> (<?php echo $row['role']; ?>)
            <?php if ($row['role'] !== 'admin' || $row['id'] !== $user_id): ?>
                <a href="?chat_id=<?php echo $chat_id; ?>&remove_member_id=<?php echo $row['id']; ?>">Remover</a>
            <?php endif; ?>
        </li>
    <?php endwhile; ?>
</ul>

<!-- Adicionar novo membro -->
<h3>Adicionar Membro</h3>
<form method="POST">
    <input type="number" name="new_member_id" placeholder="ID do Novo Membro" required>
    <button type="submit">Adicionar</button>
</form>
</body>
</html>
