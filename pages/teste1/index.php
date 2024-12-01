<?php
include('../../config.php');
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Processo de pesquisa de usuários
$search_query = '';
if (isset($_POST['search'])) {
    $search_query = $_POST['search'];
    $sql = "SELECT id, username, name FROM users WHERE username LIKE ? AND id != ?";
    $stmt = $mysqli->prepare($sql);
    $search_term = "%$search_query%";
    $stmt->bind_param("si", $search_term, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT id, username, name FROM users WHERE id != ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Adicionar amigo
if (isset($_POST['add_friend'])) {
    $friend_id = $_POST['friend_id'];

    // Verifica se já existe um relacionamento de amizade entre os usuários
    $sql_check = "SELECT id FROM friends WHERE (user_id = ? AND friend_id = ? OR user_id = ? AND friend_id = ?) AND status = 'accepted'";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        echo "<script>alert('Você já é amigo dessa pessoa.');</script>";
    } else {
        // Se não for amigo, envia o pedido de amizade
        $sql_add = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
        $stmt_add = $mysqli->prepare($sql_add);
        $stmt_add->bind_param("ii", $user_id, $friend_id);
        $stmt_add->execute();
        echo "<script>alert('Pedido de amizade enviado!');</script>";
    }
}

?>

<div class="container mt-4">
    <h3>Resultados da Busca</h3>
    <ul>
        <?php while ($row = $result->fetch_assoc()): ?>
            <li>
                <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)

                <?php 
                // Verificar se já existe pedido de amizade pendente
                $sql_check_pending = "SELECT id FROM friends WHERE (user_id = ? AND friend_id = ? OR user_id = ? AND friend_id = ?) AND status = 'pending'";
                $stmt_check_pending = $mysqli->prepare($sql_check_pending);
                $stmt_check_pending->bind_param("iiii", $user_id, $row['id'], $row['id'], $user_id);
                $stmt_check_pending->execute();
                $stmt_check_pending->store_result();

                // Verifica se já são amigos
                $sql_check_accepted = "SELECT id FROM friends WHERE (user_id = ? AND friend_id = ? OR user_id = ? AND friend_id = ?) AND status = 'accepted'";
                $stmt_check_accepted = $mysqli->prepare($sql_check_accepted);
                $stmt_check_accepted->bind_param("iiii", $user_id, $row['id'], $row['id'], $user_id);
                $stmt_check_accepted->execute();
                $stmt_check_accepted->store_result();

                if ($stmt_check_pending->num_rows == 0 && $stmt_check_accepted->num_rows == 0) {
                ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="friend_id" value="<?php echo $row['id']; ?>">
                        <button class="btn btn-primary" type="submit" name="add_friend">Adicionar amigo</button>
                    </form>
                <?php
                } elseif ($stmt_check_accepted->num_rows > 0) {
                    echo "<span>Já são amigos!</span>";
                } else {
                    echo "<span>Pedido pendente...</span>";
                }
                ?>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
