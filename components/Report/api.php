<?php
// components/Report/api.php - API para processar denúncias
require_once __DIR__ . '/../../config.php';
session_start();

// Apenas requisições AJAX são permitidas
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Apenas método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

header('Content-Type: application/json');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$reporter_id = $_SESSION['user_id'];

// Validar dados recebidos
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['reported_id']) || !isset($input['reason'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$reported_id = (int)$input['reported_id'];
$reason = trim($input['reason']);

// Validações
if ($reported_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do usuário inválido']);
    exit;
}

if (empty($reason) || strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'O motivo deve ter pelo menos 10 caracteres']);
    exit;
}

if (strlen($reason) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'O motivo não pode exceder 1000 caracteres']);
    exit;
}

// Verificar se não está tentando denunciar a si mesmo
if ($reporter_id == $reported_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Você não pode denunciar a si mesmo']);
    exit;
}

// Verificar se o usuário denunciado existe
$check_user = "SELECT id FROM users WHERE id = ?";
$stmt = $mysqli->prepare($check_user);
$stmt->bind_param("i", $reported_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}
$stmt->close();

// Verificar se já existe uma denúncia pendente do mesmo usuário para o mesmo alvo
$check_existing = "SELECT id FROM reports WHERE reporter_id = ? AND reported_id = ? AND status = 'pending'";
$stmt = $mysqli->prepare($check_existing);
$stmt->bind_param("ii", $reporter_id, $reported_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Você já possui uma denúncia pendente para este usuário']);
    exit;
}
$stmt->close();

// Inserir a denúncia
$insert_report = "INSERT INTO reports (reporter_id, reported_id, reason, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $mysqli->prepare($insert_report);
$stmt->bind_param("iis", $reporter_id, $reported_id, $reason);

if ($stmt->execute()) {
    $report_id = $mysqli->insert_id;
    $stmt->close();
   
    echo json_encode([
        'success' => true,
        'message' => 'Denúncia enviada com sucesso! A equipe de moderação irá analisar.',
        'report_id' => $report_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

$mysqli->close();
?>