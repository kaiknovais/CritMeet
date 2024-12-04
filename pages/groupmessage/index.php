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
    echo "<script>alert('ID do grupo não fornecido.'); window.location.href='../group/index.php';</script>";
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
    echo "<script>alert('Você não é membro deste grupo.'); window.location.href='../group/index.php';</script>";
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
</head>
<body>
<h2>Chat do Grupo</h2>

<!-- Mostrar mensagens -->
<ul id="messages-list">
    <?php while ($row = $result_messages->fetch_assoc()): ?>
        <li>
            <strong><?php echo htmlspecialchars($row['username']); ?>:</strong>
            <?php echo htmlspecialchars($row['content']); ?>
            <br>
            <small><?php echo $row['timestamp']; ?></small>
        </li>
    <?php endwhile; ?>
</ul>

<!-- Enviar mensagem -->
<form id="message-form" method="POST">
    <textarea name="message" placeholder="Digite sua mensagem" required></textarea>
    <button type="submit">Enviar</button>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Função para carregar novas mensagens
function loadMessages() {
    $.get('../groupfetch/index.php?chat_id=<?php echo $chat_id; ?>', function(data) {
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
    $.post('../groupmessage/index.php?chat_id=<?php echo $chat_id; ?>', { message: message }, function() {
        loadMessages(); // Carregar novas mensagens após enviar
        $('textarea[name="message"]').val(''); // Limpar campo de texto
    });
});

// Carregar mensagens a cada 5 segundos
setInterval(loadMessages, 1000);
</script>
</body>
</html>
