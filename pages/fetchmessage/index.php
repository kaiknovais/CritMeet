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

// Consultar mensagens com o username do sender_id
$sql_messages = "SELECT m.sender_id, m.content, m.timestamp, u.username
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 WHERE m.chat_id = ? 
                 ORDER BY m.timestamp ASC";
$stmt = $mysqli->prepare($sql_messages);
$stmt->bind_param("i", $chat_id);
$stmt->execute();
$result_messages = $stmt->get_result();

while ($row = $result_messages->fetch_assoc()) {
    // Formatando o timestamp para exibir apenas a hora
    $formatted_time = date('H:i', strtotime($row['timestamp']));

    echo "<p><strong>" . htmlspecialchars($row['username']) . ":</strong> " . 
         htmlspecialchars($row['content']) . " <small>(" . $formatted_time . ")</small></p>";
}
?>
