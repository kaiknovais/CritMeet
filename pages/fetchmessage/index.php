<?php
include('../../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "Usuário não autenticado.";
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['chat_id'])) {
    echo "ID do chat não fornecido.";
    exit();
}

$chat_id = $_GET['chat_id'];

$sql_messages = "SELECT sender_id, content, timestamp 
                 FROM messages 
                 WHERE chat_id = ? 
                 ORDER BY timestamp ASC";
$stmt = $mysqli->prepare($sql_messages);
$stmt->bind_param("i", $chat_id);
$stmt->execute();
$result_messages = $stmt->get_result();

while ($row = $result_messages->fetch_assoc()) {
    echo "<p><strong>" . htmlspecialchars($row['sender_id']) . ":</strong> " . 
         htmlspecialchars($row['content']) . " <small>(" . $row['timestamp'] . ")</small></p>";
}
?>
