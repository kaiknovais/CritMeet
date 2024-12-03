<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/index.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['friend_id'])) {
    echo "<script>alert('ID do amigo não fornecido.'); window.location.href='../friends/index.php';</script>";
    exit();
}

$friend_id = $_GET['friend_id'];

// Consultar o username do amigo
$sql_friend = "SELECT username FROM users WHERE id = ?";
$stmt_friend = $mysqli->prepare($sql_friend);
$stmt_friend->bind_param("i", $friend_id);
$stmt_friend->execute();
$stmt_friend->bind_result($friend_username);
$stmt_friend->fetch();
$stmt_friend->close();

// Verificar se já existe um chat entre o usuário e o amigo
$sql_check_chat = "SELECT cm.chat_id 
                   FROM chat_members cm 
                   JOIN chats c ON cm.chat_id = c.id 
                   WHERE (cm.user_id = ? AND cm.chat_id IN 
                          (SELECT chat_id FROM chat_members WHERE user_id = ?)) 
                   OR (cm.user_id = ? AND cm.chat_id IN 
                       (SELECT chat_id FROM chat_members WHERE user_id = ?))";
$stmt = $mysqli->prepare($sql_check_chat);
$stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $sql_create_chat = "INSERT INTO chats (is_group) VALUES (0)";
    $stmt_create = $mysqli->prepare($sql_create_chat);
    $stmt_create->execute();
    $chat_id = $stmt_create->insert_id;
    
    $sql_add_user = "INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?)";
    $stmt_add_user = $mysqli->prepare($sql_add_user);
    
    $stmt_add_user->bind_param("ii", $chat_id, $user_id);
    $stmt_add_user->execute();

    $stmt_add_user->bind_param("ii", $chat_id, $friend_id);
    $stmt_add_user->execute();
} else {
    $row = $result->fetch_assoc();
    $chat_id = $row['chat_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);

    if (!empty($content)) {
        $sql_insert = "INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)";
        $stmt_insert = $mysqli->prepare($sql_insert);
        $stmt_insert->bind_param("iis", $chat_id, $user_id, $content);
        $stmt_insert->execute();
    }
}

// Consultar mensagens e os nomes de usuário associados ao sender_id
$sql_messages = "SELECT m.sender_id, m.content, m.timestamp, u.username 
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id 
                 WHERE m.chat_id = ? 
                 ORDER BY m.timestamp ASC";
$stmt_messages = $mysqli->prepare($sql_messages);
$stmt_messages->bind_param("i", $chat_id);
$stmt_messages->execute();
$result_messages = $stmt_messages->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat com <?php echo htmlspecialchars($friend_username); ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/index.php">Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/index.php">Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>

                <form class="d-flex" action="../search" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Buscar amigos..." aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Buscar</button>
                </form>
            </div>
        </div>
    </nav>

<h2>Chat com <?php echo htmlspecialchars($friend_username); ?></h2>

<!-- Exibir mensagens -->
<div id="messages">
    <?php while ($row = $result_messages->fetch_assoc()): ?>
        <?php
        // Formatando o timestamp para exibir apenas a hora
        $formatted_time = date('H:i', strtotime($row['timestamp']));
        ?>
        <p><strong><?php echo htmlspecialchars($row['username']); ?>:</strong> 
        <?php echo htmlspecialchars($row['content']); ?> 
        <small>(<?php echo $formatted_time; ?>)</small></p>
    <?php endwhile; ?>
</div>

<!-- Formulário para enviar mensagens -->
<form id="chatForm">
    <textarea name="content" required></textarea>
    <button type="submit">Enviar</button>
</form>

<script>
// Função para carregar as mensagens em tempo real
function loadMessages() {
    $.ajax({
        url: '../fetchmessage/index.php', // Script PHP para buscar as mensagens
        type: 'GET',
        data: { chat_id: <?php echo $chat_id; ?> },
        success: function(data) {
            $('#messages').html(data);
        },
        error: function() {
            alert('Erro ao carregar mensagens.');
        }
    });
}

// Enviar a mensagem via AJAX
$('#chatForm').on('submit', function(e) {
    e.preventDefault();
    
    var content = $("textarea[name='content']").val();
    
    $.ajax({
        url: '', // Enviar para o mesmo script
        type: 'POST',
        data: { content: content },
        success: function() {
            $("textarea[name='content']").val('');
            loadMessages(); // Recarregar as mensagens
        },
        error: function() {
            alert('Erro ao enviar mensagem.');
        }
    });
});

// Carregar as mensagens de forma contínua
setInterval(loadMessages, 1000); // Atualiza a cada 1 segundo

// Inicialmente carrega as mensagens
loadMessages();
</script>

<?php include 'footer.php'; ?>
</body>
</html>
