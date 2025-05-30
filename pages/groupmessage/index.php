<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$chat_id = $_GET['chat_id'] ?? null;

if (!$chat_id) {
    echo "<script>alert('ID do grupo não fornecido.'); window.location.href='../group/';</script>";
    exit();
}

// Verificar se o usuário é membro do grupo
$sql_check_member = "SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ?";
$stmt = $mysqli->prepare($sql_check_member);
$stmt->bind_param("ii", $chat_id, $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if (!$role) {
    echo "<script>alert('Você não é membro deste grupo.'); window.location.href='../group/';</script>";
    exit();
}

// Enviar mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $sql_send_message = "INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($sql_send_message);
        $stmt->bind_param("iis", $chat_id, $user_id, $message);
        $stmt->execute();
    }
}

// Recuperar mensagens
$sql_messages = "SELECT m.id, m.sender_id, m.content, m.timestamp, u.username 
                 FROM messages m 
                 JOIN users u ON m.sender_id = u.id 
                 WHERE m.chat_id = ? 
                 ORDER BY m.timestamp ASC";
$stmt = $mysqli->prepare($sql_messages);
$stmt->bind_param("i", $chat_id);
$stmt->execute();
$result_messages = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat do Grupo</title>
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
            <a class="navbar-brand" href="../homepage/">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="../homepage/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/">Meu Perfil</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Mais...
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../settings/">Configurações</a></li>
                            <li><a class="dropdown-item" href="../friends/">Conexões</a></li>
                            <li><a class="dropdown-item" href="../chat/">Chat</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/">Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/">Lista de Usuários</a></li>
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
<h3>Chat do Grupo</h3>

<div class="container mt-4 chat-container">
    <div class="row">
        <!-- Mostrar mensagens -->
        <ul id="messages-list" class="chat-messages">
            <?php while ($row = $result_messages->fetch_assoc()): ?>
                <li>
                    <strong><?php echo htmlspecialchars($row['username']); ?>:</strong>
                    <?php echo htmlspecialchars($row['content']); ?>
                    <br>
                    <small><?php echo $row['timestamp']; ?></small>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>
</div>
<!-- Enviar mensagem -->
<form id="message-form" class="message-form" method="POST">
    <textarea name="message" class="message-input" placeholder="Digite sua mensagem" required></textarea>
    <button type="submit" class="message-button">Enviar</button>
</form>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Função para carregar novas mensagens
function loadMessages() {
    $.get('../groupfetch/?chat_id=<?php echo $chat_id; ?>', function(data) {
        var messages = JSON.parse(data);
        var messagesList = $('#messages-list');
        messagesList.empty(); // Limpar mensagens antigas

        // Adicionar as novas mensagens
        messages.forEach(function(message) {
            messagesList.append('<li><strong>' + message.username + ':</strong> ' + message.content + '<br><small>' + message.timestamp + '</small></li>');
        });
    });
}

// Enviar mensagem via AJAX
$('#message-form').submit(function(event) {
    event.preventDefault(); // Impede o envio padrão do formulário

    var message = $('textarea[name="message"]').val();
    $.post('../groupmessage/?chat_id=<?php echo $chat_id; ?>', { message: message }, function() {
        loadMessages(); // Carregar novas mensagens após enviar
        $('textarea[name="message"]').val(''); // Limpar campo de texto
    });
});

// Carregar mensagens a cada 5 segundos
setInterval(loadMessages, 1000);
</script>
<?php include 'footer.php'; ?>
</body>
</html>
