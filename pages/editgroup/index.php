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
    echo "<script>alert('ID do grupo não fornecido.'); window.location.href='../groups/index.php';</script>";
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
    echo "<script>alert('Você não tem permissão para editar este grupo.'); window.location.href='../groups/index.php';</script>";
    exit();
}

// Consultar informações do grupo
$sql_group = "SELECT name, creator_id FROM chats WHERE id = ?";
$stmt = $mysqli->prepare($sql_group);
$stmt->bind_param("i", $chat_id);
$stmt->execute();
$stmt->bind_result($group_name, $creator_id);
$stmt->fetch();
$stmt->close();

// Consultar membros do grupo
$sql_members = "SELECT u.id, u.username, u.name, cm.role 
                FROM users u 
                JOIN chat_members cm ON u.id = cm.user_id 
                WHERE cm.chat_id = ?";
$stmt = $mysqli->prepare($sql_members);
$stmt->bind_param("i", $chat_id);
$stmt->execute();
$result_members = $stmt->get_result();

// Consultar o criador do grupo
$sql_creator = "SELECT username, name FROM users WHERE id = ?";
$stmt_creator = $mysqli->prepare($sql_creator);
$stmt_creator->bind_param("i", $creator_id);
$stmt_creator->execute();
$stmt_creator->bind_result($creator_username, $creator_name);
$stmt_creator->fetch();
$stmt_creator->close();

// Função para excluir o grupo (somente para o criador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group'])) {
    if ($user_id == $creator_id) {
        // Deletar o grupo e todos os membros
        $sql_delete_group = "DELETE FROM chats WHERE id = ?";
        $stmt_delete_group = $mysqli->prepare($sql_delete_group);
        $stmt_delete_group->bind_param("i", $chat_id);
        $stmt_delete_group->execute();

        // Deletar os membros do grupo
        $sql_delete_members = "DELETE FROM chat_members WHERE chat_id = ?";
        $stmt_delete_members = $mysqli->prepare($sql_delete_members);
        $stmt_delete_members->bind_param("i", $chat_id);
        $stmt_delete_members->execute();

        echo "<script>alert('Grupo excluído com sucesso.'); window.location.href='../chat/index.php';</script>";
        exit();
    } else {
        echo "<script>alert('Somente o criador do grupo pode excluí-lo.');</script>";
    }
}

// Função para promover um membro a administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_member_id'])) {
    $promote_member_id = intval($_POST['promote_member_id']);

    // Verifica se o usuário está no grupo
    $sql_check_member = "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?";
    $stmt_check_member = $mysqli->prepare($sql_check_member);
    $stmt_check_member->bind_param("ii", $chat_id, $promote_member_id);
    $stmt_check_member->execute();
    $stmt_check_member->store_result();
    
    if ($stmt_check_member->num_rows > 0) {
        // Promove o membro para administrador
        $sql_promote_member = "UPDATE chat_members SET role = 'admin' WHERE chat_id = ? AND user_id = ?";
        $stmt_promote_member = $mysqli->prepare($sql_promote_member);
        $stmt_promote_member->bind_param("ii", $chat_id, $promote_member_id);
        $stmt_promote_member->execute();
        echo "<script>alert('Membro promovido a administrador.');</script>";
    } else {
        echo "<script>alert('O usuário não é membro deste grupo.');</script>";
    }
}

// Função para rebaixar um administrador para membro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demote_member_id'])) {
    $demote_member_id = intval($_POST['demote_member_id']);

    // Verifica se o usuário está no grupo e é um administrador
    $sql_check_admin = "SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ? AND role = 'admin'";
    $stmt_check_admin = $mysqli->prepare($sql_check_admin);
    $stmt_check_admin->bind_param("ii", $chat_id, $demote_member_id);
    $stmt_check_admin->execute();
    $stmt_check_admin->store_result();
    
    if ($stmt_check_admin->num_rows > 0) {
        // Rebaixa o administrador para membro
        $sql_demote_member = "UPDATE chat_members SET role = 'member' WHERE chat_id = ? AND user_id = ?";
        $stmt_demote_member = $mysqli->prepare($sql_demote_member);
        $stmt_demote_member->bind_param("ii", $chat_id, $demote_member_id);
        $stmt_demote_member->execute();
        echo "<script>alert('Administrador rebaixado para membro.');</script>";
    } else {
        echo "<script>alert('O usuário não é um administrador neste grupo.');</script>";
    }
}

// Adicionar membro a partir do username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_member_username'])) {
    $new_member_username = trim($_POST['new_member_username']);
    
    // Verifica se o username existe
    $sql_check_user = "SELECT id FROM users WHERE username = ?";
    $stmt_check_user = $mysqli->prepare($sql_check_user);
    $stmt_check_user->bind_param("s", $new_member_username);
    $stmt_check_user->execute();
    $stmt_check_user->store_result();
    
    if ($stmt_check_user->num_rows > 0) {
        $stmt_check_user->bind_result($new_member_id);
        $stmt_check_user->fetch();
        
        // Verifica se o usuário já é membro do grupo
        $sql_check_member = "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?";
        $stmt_check_member = $mysqli->prepare($sql_check_member);
        $stmt_check_member->bind_param("ii", $chat_id, $new_member_id);
        $stmt_check_member->execute();
        $stmt_check_member->store_result();
        
        if ($stmt_check_member->num_rows == 0) {
            // Adiciona o novo membro ao grupo
            $sql_add_member = "INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'member')";
            $stmt_add_member = $mysqli->prepare($sql_add_member);
            $stmt_add_member->bind_param("ii", $chat_id, $new_member_id);
            $stmt_add_member->execute();
            echo "<script>alert('Novo membro adicionado com sucesso.');</script>";
        } else {
            echo "<script>alert('O usuário já é membro deste grupo.');</script>";
        }
    } else {
        echo "<script>alert('Usuário não encontrado.');</script>";
    }
}

// Remover membro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member_id'])) {
    $remove_member_id = intval($_POST['remove_member_id']);
    
    // Verifica se o usuário a ser removido está no grupo
    $sql_check_member = "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?";
    $stmt_check_member = $mysqli->prepare($sql_check_member);
    $stmt_check_member->bind_param("ii", $chat_id, $remove_member_id);
    $stmt_check_member->execute();
    $stmt_check_member->store_result();
    
    if ($stmt_check_member->num_rows > 0) {
        // Remover membro do grupo
        $sql_remove_member = "DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?";
        $stmt_remove_member = $mysqli->prepare($sql_remove_member);
        $stmt_remove_member->bind_param("ii", $chat_id, $remove_member_id);
        $stmt_remove_member->execute();
        echo "<script>alert('Membro removido com sucesso.');</script>";
    } else {
        echo "<script>alert('O usuário não é membro deste grupo.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Grupo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <h2>Editar Grupo</h2>
        <form method="POST">
            <div class="mb-3">
                <label for="group_name" class="form-label">Nome do Grupo</label>
                <input type="text" class="form-control" name="group_name" value="<?php echo htmlspecialchars($group_name); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Atualizar Nome</button>
        </form>

        <h3>Adicionar Novo Membro</h3>
        <form method="POST">
            <div class="mb-3">
                <input type="text" name="new_member_username" class="form-control" placeholder="Username do Usuário" required>
            </div>
            <button type="submit" class="btn btn-success">Adicionar</button>
        </form>

        <h3>Criador do Grupo</h3>
        <div class="alert alert-info">
            <strong><?php echo htmlspecialchars($creator_name); ?> (<?php echo htmlspecialchars($creator_username); ?>)</strong> - Criador do grupo
        </div>

        <?php if ($user_id == $creator_id): ?>
        <form method="POST">
            <button type="submit" name="delete_group" class="btn btn-danger mb-3">Excluir Grupo</button>
        </form>
        <?php endif; ?>

        <h3>Lista de Membros</h3>
        <ul class="list-group">
            <?php while ($row = $result_members->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>) 
                        <?php if ($row['role'] == 'admin'): ?>
                            <span class="badge bg-warning">Admin</span>
                        <?php endif; ?>
                        <?php if ($row['id'] == $creator_id): ?>
                            <span class="badge bg-success">Criador</span>
                        <?php endif; ?>
                    </span>

                    <!-- Promover / Rebaixar Admin -->
                    <?php if ($row['id'] != $creator_id): ?>
                        <?php if ($row['role'] != 'admin'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="promote_member_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-warning btn-sm">Promover</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="demote_member_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Rebaixar</button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="remove_member_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Remover</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
