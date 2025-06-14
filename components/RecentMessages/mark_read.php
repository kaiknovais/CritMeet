<?php
// components/RecentMessages/mark_read.php

require_once __DIR__ . '/../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$chat_id = $input['chat_id'] ?? null;

if (!$chat_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Chat ID é obrigatório']);
    exit;
}

// Verificar se o usuário é membro do chat
$check_sql = "SELECT id FROM chat_members WHERE user_id = ? AND chat_id = ?";
$check_stmt = $mysqli->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $chat_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}
$check_stmt->close();

// Atualizar o timestamp de última visualização
$update_sql = "UPDATE chat_members SET last_seen = NOW() WHERE user_id = ? AND chat_id = ?";
$update_stmt = $mysqli->prepare($update_sql);
$update_stmt->bind_param("ii", $user_id, $chat_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Marcado como lido']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao marcar como lido']);
}

$update_stmt->close();
?>