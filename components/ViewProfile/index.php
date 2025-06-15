<?php
include('../../config.php');
session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido ou inválido']);
    exit;
}

$user_id = (int)$_GET['id'];

// Buscar dados do usuário
$sql = "SELECT id, username, name, gender, pronouns, preferences, image FROM users WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Retornar dados em JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'gender' => $user['gender'],
        'pronouns' => $user['pronouns'],
        'preferences' => $user['preferences'],
        'image' => $user['image']
    ]
]);
?>