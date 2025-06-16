<?php
require_once __DIR__ . '/../../config.php';
session_start();

// Configurar cabeçalhos CORS e JSON com melhor controle de erros
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://critmeet.com.br');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Função para log de erros detalhado
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    error_log("[$timestamp] Report API Error: $message $contextStr");
}

// Função para resposta JSON padronizada
function jsonResponse($success, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Tratar requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError('Método HTTP inválido', ['method' => $_SERVER['REQUEST_METHOD']]);
    jsonResponse(false, 'Método não permitido', [], 405);
}

// Verificar se é requisição AJAX (mais flexível)
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    isset($_SERVER['CONTENT_TYPE']) && 
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
);

if (!$isAjax) {
    logError('Requisição não-AJAX detectada');
    jsonResponse(false, 'Acesso negado', [], 403);
}

// Verificar se usuário está logado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    logError('Usuário não autenticado', ['session' => isset($_SESSION['user_id'])]);
    jsonResponse(false, 'Usuário não autenticado', [], 401);
}

$reporter_id = (int)$_SESSION['user_id'];

// Validar entrada JSON
$inputRaw = file_get_contents('php://input');
if (empty($inputRaw)) {
    logError('Corpo da requisição vazio');
    jsonResponse(false, 'Dados não fornecidos', [], 400);
}

$input = json_decode($inputRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logError('JSON inválido', ['error' => json_last_error_msg(), 'raw' => substr($inputRaw, 0, 100)]);
    jsonResponse(false, 'Formato de dados inválido', [], 400);
}

// Validar campos obrigatórios
if (!isset($input['reported_id']) || !isset($input['reason'])) {
    logError('Campos obrigatórios ausentes', ['input_keys' => array_keys($input)]);
    jsonResponse(false, 'Dados incompletos (ID do usuário e motivo são obrigatórios)', [], 400);
}

$reported_id = filter_var($input['reported_id'], FILTER_VALIDATE_INT);
$reason = trim($input['reason']);

// Validações específicas
if ($reported_id === false || $reported_id <= 0) {
    jsonResponse(false, 'ID do usuário inválido', [], 400);
}

if (empty($reason)) {
    jsonResponse(false, 'O motivo da denúncia é obrigatório', [], 400);
}

if (strlen($reason) < 10) {
    jsonResponse(false, 'O motivo deve ter pelo menos 10 caracteres', [], 400);
}

if (strlen($reason) > 1000) {
    jsonResponse(false, 'O motivo não pode exceder 1000 caracteres', [], 400);
}

// Verificar auto-denúncia
if ($reporter_id === $reported_id) {
    jsonResponse(false, 'Você não pode denunciar a si mesmo', [], 400);
}

// Verificar conexão com banco
if (!$mysqli || $mysqli->connect_error) {
    logError('Erro de conexão com banco', ['error' => $mysqli->connect_error ?? 'mysqli não definido']);
    jsonResponse(false, 'Erro interno do servidor', [], 500);
}

try {
    // Verificar se o usuário denunciado existe
    $check_user = "SELECT id, username FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($check_user);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta de verificação do usuário: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $reported_id);
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar consulta de verificação do usuário: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        jsonResponse(false, 'Usuário não encontrado', [], 404);
    }
    
    $user_data = $result->fetch_assoc();
    $stmt->close();

    // Verificar denúncia duplicada (últimas 24 horas)
    $check_existing = "SELECT id, created_at FROM reports 
                      WHERE reporter_id = ? AND reported_id = ? 
                      AND status = 'pending' 
                      AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $stmt = $mysqli->prepare($check_existing);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta de duplicação: ' . $mysqli->error);
    }

    $stmt->bind_param("ii", $reporter_id, $reported_id);
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar consulta de duplicação: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $stmt->close();
        jsonResponse(false, 'Você já possui uma denúncia pendente para este usuário nas últimas 24 horas', [], 409);
    }
    $stmt->close();

    // Inserir a denúncia
    $insert_report = "INSERT INTO reports (reporter_id, reported_id, reason, status, created_at) 
                     VALUES (?, ?, ?, 'pending', NOW())";
    
    $stmt = $mysqli->prepare($insert_report);
    if (!$stmt) {
        throw new Exception('Erro ao preparar inserção da denúncia: ' . $mysqli->error);
    }

    $stmt->bind_param("iis", $reporter_id, $reported_id, $reason);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar inserção da denúncia: ' . $stmt->error);
    }

    $report_id = $mysqli->insert_id;
    $stmt->close();

    // Log de sucesso
    error_log("Denúncia criada com sucesso - ID: $report_id, Reporter: $reporter_id, Reported: $reported_id (" . $user_data['username'] . ")");

    // Resposta de sucesso
    jsonResponse(true, 'Denúncia enviada com sucesso! A equipe de moderação irá analisar.', [
        'report_id' => $report_id,
        'reported_user' => $user_data['username']
    ]);

} catch (Exception $e) {
    logError('Exceção durante processamento', [
        'message' => $e->getMessage(),
        'reporter_id' => $reporter_id,
        'reported_id' => $reported_id
    ]);
    jsonResponse(false, 'Erro interno do servidor', [], 500);
} finally {
    // Fechar conexão se ainda estiver aberta
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if ($mysqli) {
        $mysqli->close();
    }
}
?>