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

$messages = [];

if ($result_messages && $result_messages->num_rows > 0) {
    while ($row = $result_messages->fetch_assoc()) {
        $messages[] = [
            "username" => $row['username'],
            "content" => $row['content'],
            "timestamp" => $row['timestamp']
        ];
    }
}

echo json_encode($messages); // Retorna as mensagens em formato JSON
?>
