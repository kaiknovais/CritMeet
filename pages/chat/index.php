<?php
include '../../config.php';
session_start();

// Verifica se o usuário está autenticado
$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

if (!$user_id) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

// Verificar se o usuário é administrador
$query = "SELECT admin FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $is_admin = $row['admin'] == 1;
}
$stmt->close();

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

// Consultar grupos em que o usuário participa
$sql_groups = "SELECT c.id, c.name, c.creator_id 
               FROM chats c 
               JOIN chat_members cm ON c.id = cm.chat_id 
               WHERE cm.user_id = ? AND c.is_group = 1";
$stmt = $mysqli->prepare($sql_groups);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_groups = $stmt->get_result();

// Criar grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    $group_name = trim($_POST['group_name']);

    if (!empty($group_name)) {
        // Verificar se já existe um grupo com o mesmo nome
        $sql_check_group = "SELECT id FROM chats WHERE name = ?";
        $stmt_check_group = $mysqli->prepare($sql_check_group);
        $stmt_check_group->bind_param("s", $group_name);
        $stmt_check_group->execute();
        $stmt_check_group->store_result();

        if ($stmt_check_group->num_rows > 0) {
            // Caso já exista um grupo com o mesmo nome, exibir um alerta
            echo "<script>alert('Já existe um grupo com esse nome. Escolha outro nome.');</script>";
        } else {
            // Criar novo grupo
            $sql_create_group = "INSERT INTO chats (is_group, name, creator_id) VALUES (1, ?, ?)";
            $stmt_create_group = $mysqli->prepare($sql_create_group);
            $stmt_create_group->bind_param("si", $group_name, $user_id);
            $stmt_create_group->execute();
            $chat_id = $stmt_create_group->insert_id;

            // Adicionar criador como admin
            $sql_add_member = "INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'admin')";
            $stmt_member = $mysqli->prepare($sql_add_member);
            $stmt_member->bind_param("ii", $chat_id, $user_id);
            $stmt_member->execute();

            // Mensagem de sucesso
            echo "<script>alert('Grupo criado com sucesso!'); window.location.href='index.php';</script>";
        }
    } else {
        echo "<script>alert('O nome do grupo não pode estar vazio.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rede Social - Chats e Grupos</title>
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

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
                            <li><a class="dropdown-item" href="../friends/index.php">Conexões</a></li>
                            <li><a class="dropdown-item" href="../chat/index.php">Chat</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/index.php">Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/index.php">Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>

                <form class="d-flex" action="../friends" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Buscar amigos..." aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Buscar</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <h3>Meus Amigos</h3>
                <ul class="list-group">
                    <?php while ($row = $result_friends->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)</span>
                            <a class="btn btn-link btn-sm" href="../message/index.php?friend_id=<?php echo $row['id']; ?>">Abrir Chat</a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>

            <div class="col-md-8">
                <h3>Meus Grupos</h3>
                <ul class="list-group">
                    <?php while ($row = $result_groups->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($row['name']); ?></span>
                            <a class="btn btn-link btn-sm" href="../groupmessage/index.php?chat_id=<?php echo $row['id']; ?>">Entrar no Chat</a>
                            <?php if ($row['creator_id'] == $user_id || $is_admin): ?>
                                <a class="btn btn-warning btn-sm" href="../editgroup/index.php?chat_id=<?php echo $row['id']; ?>">Editar</a>
                            <?php endif; ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
                
                <h2>Criar Novo Grupo</h2>
                <form method="POST" class="mt-3">
                    <div class="mb-3">
                        <input type="text" name="group_name" class="form-control" placeholder="Nome do Grupo" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Criar</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
