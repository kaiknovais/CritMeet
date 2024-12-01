<?php
include('../../config.php');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Processo de pesquisa de usuários
$search_query = '';
$result = [];

if (isset($_POST['search']) && strlen($_POST['search']) > 2) {
    // Recupera o termo de pesquisa
    $search_query = $_POST['search'];

    // Consulta para buscar pelo 'username', com correspondência parcial
    $sql = "SELECT username, name FROM users WHERE username LIKE CONCAT('%', ?, '%') AND id != ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("si", $search_query, $user_id);  // Bind para 'username' parcial e 'user_id'
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Caso a pesquisa não tenha sido realizada ou o termo seja muito curto
    $sql = "SELECT username, name FROM users WHERE id != ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $user_id);  // Apenas passando o user_id
    $stmt->execute();
    $result = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>リンゴジュース</title>
    <link rel="stylesheet" href="../../assets/desktop.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/index.php">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="../homepage/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/index.php">Meu Perfil</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Mais...
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../settings/index.php">Configurações</a></li>
                            <li><a class="dropdown-item" href="#">Conexões</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/index.php ">Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/index.php">Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
                <form class="d-flex" method="POST" action="" onsubmit="showModal(); return false;">
                    <input class="form-control me-2" type="search" name="search" placeholder="Buscar amigos..." aria-label="Search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <button class="btn btn-outline-success" type="submit">Buscar</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="modal fade" id="searchResultsModal" tabindex="-1" aria-labelledby="searchResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchResultsModalLabel">Resultados da Busca</h5>
 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($result->num_rows > 0): ?>
                        <ul class="list-group">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <li class="list-group-item">
                                    <a href="#"><?php echo htmlspecialchars($row['username']); ?></a>
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
                                            <button class="btn btn-primary btn-sm" type="submit" name="add_friend">Adicionar amigo</button>
                                        </form>
                                    <?php
                                    } elseif ($stmt_check_accepted->num_rows > 0) {
                                        echo "<span class='badge bg-success'>Já são amigos!</span>";
                                    } else {
                                        echo "<span class='badge bg-warning'>Pedido pendente...</span>";
                                    }
                                    ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>Nenhum usuário encontrado com o nome de usuário "<?php echo htmlspecialchars($search_query); ?>".</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function showModal() {
            $('#searchResultsModal').modal('show');
        }
    </script>
</body>
</html>