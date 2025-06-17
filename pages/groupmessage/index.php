<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/ViewAvatar/index.php';

// Verificar se a sessão já foi iniciada antes de chamar session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está autenticado
$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null;

// Buscar dados completos do usuário
if ($user_id) {
    $query = "SELECT username, name, image, admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $user = $row;
        $is_admin = $row['admin'] == 1;
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../pages/login/");
    exit();
}

$user_id = $_SESSION['user_id'];

// Aceitar tanto group_id quanto chat_id para compatibilidade
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
if (!$group_id) {
    $group_id = filter_input(INPUT_GET, 'chat_id', FILTER_VALIDATE_INT);
}

if (!$group_id) {
    header("Location: ../chat/");
    exit();
}

// Função para exibir imagem do perfil
function getProfileImageUrl($image_data) {
    if (empty($image_data)) {
        return 'default-avatar.png';
    }
    
    // Verificar se é base64 (dados antigos)
    if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $image_data)) {
        return 'data:image/jpeg;base64,' . $image_data;
    } else {
        // É um nome de arquivo
        return '../../uploads/profiles/' . $image_data;
    }
}

function getGroupImageUrl($image_data) {
    // Se não há dados de imagem, retorna null
    if (empty($image_data)) {
        return null;
    }
    
    // Limpar o nome do arquivo de caracteres especiais
    $image_data = trim($image_data);
    
    // Construir o caminho absoluto usando __DIR__
    $file_path = __DIR__ . '/../../uploads/groups/' . $image_data;
    
    // Verificar se o arquivo existe
    if (file_exists($file_path) && is_file($file_path)) {
        // Retornar o caminho relativo para o navegador
        return '../../uploads/groups/' . $image_data;
    }
    
    // Se arquivo não existe, retorna null
    return null;
}

// Verificar se o usuário é membro do grupo
$sql_check_member = "SELECT cm.role, c.name, c.creator_id, c.image 
                     FROM chat_members cm 
                     JOIN chats c ON cm.chat_id = c.id 
                     WHERE cm.chat_id = ? AND cm.user_id = ? AND c.is_group = 1";
$stmt_check = $mysqli->prepare($sql_check_member);
$stmt_check->bind_param("ii", $group_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    header("Location: ../chat/");
    exit();
}

$member_data = $result_check->fetch_assoc();
$user_role = $member_data['role'];
$group_name = $member_data['name'];
$group_image = $member_data['image'];
$creator_id = $member_data['creator_id'];
$is_creator = ($creator_id == $user_id);
$stmt_check->close();



// Processar outras ações do grupo
// Processar upload de imagem do grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['group_image']) && ($user_role === 'admin' || $is_creator)) {
    $upload_dir = __DIR__ . '/../../uploads/groups/';
    
    // Criar diretório se não existir
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['group_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Verificar erros no upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = "Erro no upload: " . $file['error'];
    } 
    // Verificar tipo e tamanho do arquivo
    else if (in_array($file['type'], $allowed_types) && $file['size'] <= 5000000 && $file['size'] > 0) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Remover imagem anterior se existir
            if (!empty($group_image)) {
                $old_image_path = $upload_dir . basename($group_image);
                if (file_exists($old_image_path)) {
                    @unlink($old_image_path);
                }
            }
            
            // Gerar nome único para o novo arquivo
            $new_filename = 'group_' . $group_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Atualizar no banco de dados
                $sql_update_image = "UPDATE chats SET image = ? WHERE id = ?";
                $stmt_update_image = $mysqli->prepare($sql_update_image);
                
                if ($stmt_update_image) {
                    $stmt_update_image->bind_param("si", $new_filename, $group_id);
                    
                    if ($stmt_update_image->execute()) {
                        // Atualizar a variável local e a sessão
                        $group_image = $new_filename;
                        $_SESSION['upload_success'] = "Imagem do grupo atualizada com sucesso!";
                    } else {
                        $_SESSION['upload_error'] = "Erro ao atualizar banco de dados: " . $stmt_update_image->error;
                        @unlink($upload_path); // Remover arquivo se o BD falhar
                    }
                    $stmt_update_image->close();
                } else {
                    $_SESSION['upload_error'] = "Erro na preparação da consulta: " . $mysqli->error;
                }
            } else {
                $_SESSION['upload_error'] = "Falha ao mover o arquivo para o diretório de upload.";
            }
        } else {
            $_SESSION['upload_error'] = "Extensão de arquivo não permitida.";
        }
    } else {
        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['upload_error'] = "Tipo de arquivo não permitido.";
        } else if ($file['size'] > 5000000) {
            $_SESSION['upload_error'] = "Arquivo muito grande. Tamanho máximo: 5MB.";
        } else {
            $_SESSION['upload_error'] = "Arquivo inválido ou vazio.";
        }
    }
    
    // Redirecionar para evitar resubmissão
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Obter informações dos membros do grupo
$sql_members = "SELECT u.id, u.username, u.image, cm.role 
                FROM users u 
                JOIN chat_members cm ON u.id = cm.user_id 
                WHERE cm.chat_id = ? 
                ORDER BY cm.role DESC, u.username ASC";
$stmt_members = $mysqli->prepare($sql_members);
$stmt_members->bind_param("i", $group_id);
$stmt_members->execute();
$result_members = $stmt_members->get_result();

$group_members = [];
$member_count = 0;
while ($row = $result_members->fetch_assoc()) {
    $group_members[] = $row;
    $member_count++;
}
$stmt_members->close();

// Consultar dados do usuário atual
$sql_current_user = "SELECT username, image FROM users WHERE id = ?";
$stmt_current = $mysqli->prepare($sql_current_user);
$stmt_current->bind_param("i", $user_id);
$stmt_current->execute();
$result_current = $stmt_current->get_result();
$current_user_data = $result_current->fetch_assoc();
$current_username = $current_user_data['username'];
$current_avatar = $current_user_data['image'];
$stmt_current->close();

// Se for requisição AJAX para enviar mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $content = trim($_POST['content'] ?? '');
    
    if (!empty($content)) {
        // Verificar se não há mensagem duplicada recente (últimos 2 segundos)
        $sql_check_duplicate = "SELECT id FROM messages 
                                WHERE chat_id = ? AND sender_id = ? AND content = ? 
                                AND timestamp > DATE_SUB(NOW(), INTERVAL 2 SECOND)";
        $stmt_check = $mysqli->prepare($sql_check_duplicate);
        $stmt_check->bind_param("iis", $group_id, $user_id, $content);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows == 0) {
            // Não há duplicata, pode inserir
            $sql_insert = "INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)";
            $stmt_insert = $mysqli->prepare($sql_insert);
            $stmt_insert->bind_param("iis", $group_id, $user_id, $content);
            
            if ($stmt_insert->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao inserir mensagem']);
            }
            $stmt_insert->close();
        } else {
            // Mensagem duplicada detectada
            echo json_encode(['success' => true, 'duplicate' => true]);
        }
        $stmt_check->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Conteúdo vazio']);
    }
    exit();
}

// Se for requisição AJAX para buscar mensagens
if (isset($_GET['action']) && $_GET['action'] === 'get_messages') {
    // Adicione esta linha para debug:
    error_log("Buscando mensagens para grupo: " . $group_id);
    
    $sql_messages = "SELECT m.sender_id, m.content, m.timestamp, u.username, u.image 
                     FROM messages m
                     JOIN users u ON m.sender_id = u.id 
                     WHERE m.chat_id = ? 
                     ORDER BY m.timestamp ASC";
    $stmt_messages = $mysqli->prepare($sql_messages);
    $stmt_messages->bind_param("i", $group_id);
    $stmt_messages->execute();
    $result_messages = $stmt_messages->get_result();
    
    $messages = [];
    while ($row = $result_messages->fetch_assoc()) {
        $messages[] = [
            'sender_id' => $row['sender_id'],
            'username' => $row['username'],
            'content' => $row['content'],
            'timestamp' => $row['timestamp'],
            'is_own' => $row['sender_id'] == $user_id,
            'avatar' => $row['image']
        ];
    }
    
    echo json_encode($messages);
    exit();
}

// Se for requisição AJAX para obter membros
if (isset($_GET['action']) && $_GET['action'] === 'get_members') {
    $members_list = [];
    foreach ($group_members as $member) {
        $members_list[] = [
            'id' => $member['id'],
            'username' => $member['username'],
            'avatar' => $member['image'],
            'role' => $member['role'],
            'is_creator' => ($member['id'] == $creator_id)
        ];
    }
    echo json_encode($members_list);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Grupo: <?php echo htmlspecialchars($group_name ?: 'Chat em Grupo'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
/* Reset básico para evitar problemas de layout */
* {
    box-sizing: border-box;
}

html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

/* Layout principal em flexbox - FUNDAMENTAL para funcionar */
.main-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    width: 100%;
}

/* Navbar fixa no topo */
.navbar {
    flex-shrink: 0;
    z-index: 1000;
}

/* Container do chat ocupa o espaço restante */
.chat-page-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 10px;
    min-height: 0;
}

/* Wrapper do chat com altura fixa */
.chat-wrapper {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
}

/* Header do chat fixo */
.chat-header {
    flex-shrink: 0;
    background: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 10;
}

.chat-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.chat-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

/* Container de mensagens com rolagem */
.chat-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 15px;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

/* Input fixo na parte inferior */
.message-form-container {
    flex-shrink: 0;
    padding: 15px;
    background: white;
    border-top: 1px solid #e9ecef;
}

/* Estilos dos avatares */
.group-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #007bff, #28a745);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 16px;
    border: 2px solid #007bff;
    flex-shrink: 0;
}

.profile-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.5);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.username-text {
    color: rgba(255,255,255,0.9);
    font-weight: 500;
    font-size: 0.9rem;
}

/* Estilos das mensagens */
.message {
    margin-bottom: 15px;
    padding: 10px 15px;
    border-radius: 18px;
    max-width: 85%;
    animation: slideIn 0.3s ease-out;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    word-wrap: break-word;
    word-break: break-word;
    hyphens: auto;
    flex-shrink: 0;
}

@keyframes slideIn {
    from { 
        opacity: 0; 
        transform: translateY(10px);
    }
    to { 
        opacity: 1; 
        transform: translateY(0);
    }
}

.message.own {
    background-color: #007bff;
    color: white;
    align-self: flex-end;
    flex-direction: row-reverse;
    border-bottom-right-radius: 5px;
}

.message.other {
    background-color:rgb(165, 178, 196);
    color: #333;
    align-self: flex-start;
    border-bottom-left-radius: 5px;
    border: 1px solid rgba(0, 123, 255, 0.1);
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.3);
    cursor: pointer;
}

.message-avatar-default {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.3);
    cursor: pointer;
}

.message.own .message-avatar,
.message.own .message-avatar-default {
    border-color: rgba(255,255,255,0.5);
}

.message-content {
    flex: 1;
    min-width: 0;
}

.message-username {
    font-weight: 600;
    font-size: 0.8rem;
    margin-bottom: 3px;
    opacity: 0.8;
}

.message-text {
    line-height: 1.4;
    font-size: 0.95rem;
    margin-bottom: 3px;
}

.message-timestamp {
    font-size: 0.7rem;
    opacity: 0.7;
}

/* Formulário de mensagem */
.message-form {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}

.message-input {
    flex: 1;
    padding: 12px 18px;
    border: 1px solid #ddd;
    border-radius: 25px;
    outline: none;
    font-size: 14px;
    min-width: 0;
}

.message-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.message-button {
    padding: 12px 18px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.message-button:hover {
    background: #0056b3;
}

/* Botões de ação do grupo */
.group-action-btn {
    background: none;
    border: none;
    color: #6c757d;
    font-size: 1.2rem;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
}

.group-action-btn:hover {
    background: rgba(0, 123, 255, 0.1);
    color: #007bff;
}

/* Modal de membros */
.members-modal .modal-body {
    max-height: 400px;
    overflow-y: auto;
}

.member-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.member-item:last-child {
    border-bottom: none;
}

.member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
}

.member-avatar-default {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 16px;
    border: 2px solid #e9ecef;
}

.member-info {
    flex: 1;
    min-width: 0;
}

.member-username {
    font-weight: 600;
    margin: 0;
}

.member-role {
    font-size: 0.8rem;
    color: #6c757d;
    margin: 0;
}

.admin-badge {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 12px;
}

.creator-badge {
    background: #ffc107;
    color: #000;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 12px;
}

/* Scrollbar personalizada */
.chat-container::-webkit-scrollbar {
    width: 8px;
}

.chat-container::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.05);
    border-radius: 4px;
}

.chat-container::-webkit-scrollbar-thumb {
    background: rgba(0, 123, 255, 0.3);
    border-radius: 4px;
}

.chat-container::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 123, 255, 0.5);
}

/* Header responsivo */
.chat-header h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-header small {
    font-size: 0.75rem;
}

/* Responsividade para mobile */
@media (max-width: 768px) {
    .chat-page-container {
        padding: 5px;
    }
    
    .message {
        max-width: 90%;
        padding: 8px 12px;
        gap: 8px;
    }
    
    .message-text {
        font-size: 0.9rem;
    }
    
    .message-input {
        font-size: 16px;
        padding: 10px 15px;
    }
    
    .message-button {
        width: 44px;
        height: 44px;
        padding: 10px;
    }
    
    .group-avatar {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }
    
    .message-avatar,
    .message-avatar-default {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
    
    .chat-header {
        padding: 10px 12px;
    }
    
    .chat-header h5 {
        font-size: 1rem;
    }
    
    .chat-container {
        padding: 10px;
    }
    
    .message-form-container {
        padding: 10px;
    }
    
    .group-action-btn {
        font-size: 1rem;
        padding: 6px;
    }
}

.member-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.member-actions .btn {
    font-size: 0.75rem;
    padding: 4px 8px;
}

.group-avatar img {
    border: 2px solid #007bff;
}

/* Ajustes para telas muito pequenas */
@media (max-width: 480px) {
    .message-username {
        font-size: 0.75rem;
    }
    
    .message-timestamp {
        font-size: 0.65rem;
    }
    
    .chat-header-left {
        gap: 8px;
    }
    
    .chat-header-right {
        gap: 5px;
    }
}

/* Animação para novas mensagens */
.message.new-message {
    animation: newMessage 0.5s ease-out;
}

@keyframes newMessage {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Indicadores visuais de scroll */
.chat-container.has-scroll-top::before {
    content: '';
    position: sticky;
    top: 0;
    display: block;
    height: 20px;
    background: linear-gradient(to bottom, rgba(248,249,250,0.9), transparent);
    margin: -15px -15px 0 -15px;
    z-index: 1;
}

.chat-container.has-scroll-bottom::after {
    content: '';
    position: sticky;
    bottom: 0;
    display: block;
    height: 20px;
    background: linear-gradient(to top, rgba(248,249,250,0.9), transparent);
    margin: 0 -15px -15px -15px;
    z-index: 1;
}

.message-avatar.clickable-avatar {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.message-avatar.clickable-avatar:hover {
    transform: scale(1.1);
    border-color: #007bff !important;
}
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Navbar fixa -->
        <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="../homepage/">CritMeet</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="../homepage/">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="../matchmaker/">Matchmaker</a></li>
                <li class="nav-item"><a class="nav-link" href="../rpg_info">RPG</a></li>
                <li class="nav-item"><a class="nav-link" href="../friends">Conexões</a></li>
                <li class="nav-item"><a class="nav-link" href="../chat">Chat</a></li>
            </ul>
            
            <!-- Seção do usuário -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <div class="user-info">
                            <img src="<?php echo getProfileImageUrl($user['image'] ?? ''); ?>" 
                                 alt="Avatar" 
                                 class="profile-avatar" 
                                 onerror="this.src='default-avatar.png'" />
                            <span class="username-text"><?php echo htmlspecialchars($user['username'] ?? 'Usuário'); ?></span>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../Profile/">
                            <i class="bi bi-person-circle"></i> Meu Perfil
                        </a></li>
                        <li><a class="dropdown-item active" href="../settings/">
                            <i class="bi bi-gear"></i> Configurações
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($is_admin): ?>
                            <li><a class="dropdown-item text-danger" href="../admin/">
                                <i class="bi bi-shield-check"></i> Painel Admin
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger" href="../../components/Logout/">
                            <i class="bi bi-box-arrow-right"></i> Sair
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

        <!-- Container principal do chat -->
        <div class="chat-page-container">
            <div class="chat-wrapper">
                <!-- Cabeçalho do chat -->
                <div class="chat-header">
    <div class="chat-header-left">
        <div class="group-avatar">
            <?php 
            $group_image_url = getGroupImageUrl($group_image);
            if ($group_image_url): 
            ?>
                <img src="<?php echo htmlspecialchars($group_image_url); ?>" 
                     alt="Grupo" 
                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'bi bi-people-fill\'></i>';">
            <?php else: ?>
                <i class="bi bi-people-fill"></i>
            <?php endif; ?>
        </div>
        <div>
            <h5><?php echo htmlspecialchars($group_name ?: 'Chat em Grupo'); ?></h5>
            <small class="text-muted"><?php echo $member_count; ?> membros</small>
        </div>
    </div>
    <div class="chat-header-right">
        <button class="group-action-btn" type="button" data-bs-toggle="modal" data-bs-target="#membersModal" title="Ver membros">
            <i class="bi bi-people"></i>
        </button>
        <?php if ($user_role === 'admin' || $is_creator): ?>
            <button class="group-action-btn" type="button" data-bs-toggle="modal" data-bs-target="#settingsModal" title="Configurações do grupo">
                <i class="bi bi-gear"></i>
            </button>
        <?php endif; ?>
    </div>
</div>

                <!-- Container das mensagens com rolagem -->
                <div id="messages-container" class="chat-container">
                    <!-- Mensagens serão carregadas aqui -->
                </div>

                <!-- Formulário fixo na parte inferior -->
                <div class="message-form-container">
                    <form id="message-form" class="message-form">
                        <input type="text" name="content" class="message-input" placeholder="Digite sua mensagem..." autocomplete="off" required>
                        <button type="submit" class="message-button">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Membros -->
    <div class="modal fade" id="membersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Membros do Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="members-list">
                <?php foreach ($group_members as $member): ?>
                <div class="member-item">
                    <?php 
                    $member_avatar_url = getProfileImageUrl($member['image']);
                    if ($member_avatar_url): 
                    ?>
                        <img src="<?php echo $member_avatar_url; ?>" alt="Avatar" class="member-avatar">
                    <?php else: ?>
                        <div class="member-avatar-default"><?php echo strtoupper($member['username'][0]); ?></div>
                    <?php endif; ?>
                    
                    <div class="member-info">
                        <p class="member-username"><?php echo htmlspecialchars($member['username']); ?></p>
                        <p class="member-role">
                            <?php if ($member['id'] == $creator_id): ?>
                                <span class="creator-badge">Criador</span>
                            <?php elseif ($member['role'] == 'admin'): ?>
                                <span class="admin-badge">Admin</span>
                            <?php else: ?>
                                Membro
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (($user_role === 'admin' || $is_creator) && $member['id'] != $creator_id): ?>
                    <div class="member-actions">
                        <?php if ($member['role'] != 'admin'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                <button type="submit" name="promote_member" class="btn btn-outline-primary btn-sm">Promover</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                <button type="submit" name="demote_member" class="btn btn-outline-secondary btn-sm">Rebaixar</button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Remover este membro?');">
                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                            <button type="submit" name="remove_member" class="btn btn-outline-danger btn-sm">Remover</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

    <!-- Modal de Configurações -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configurações do Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Upload de imagem -->
                <div class="mb-3">
                    <label class="form-label">Imagem do Grupo</label>
                    <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                        <input type="file" name="group_image" class="form-control" accept="image/*">
                        <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                    </form>
                </div>
                
                <!-- Alterar nome -->
                <div class="mb-3">
                    <label class="form-label">Nome do Grupo</label>
                    <form method="POST" class="d-flex gap-2">
                        <input type="text" name="group_name" class="form-control" value="<?php echo htmlspecialchars($group_name); ?>" maxlength="100">
                        <button type="submit" name="update_group_name" class="btn btn-primary btn-sm">Salvar</button>
                    </form>
                </div>
                
                <!-- Adicionar membro -->
                <div class="mb-3">
                    <label class="form-label">Adicionar Membro</label>
                    <form method="POST" class="d-flex gap-2">
                        <input type="text" name="new_member_username" class="form-control" placeholder="Username">
                        <button type="submit" name="add_member" class="btn btn-success btn-sm">Adicionar</button>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let lastMessageCount = 0;
        let shouldScrollToBottom = true;
        let isSubmitting = false
        const groupId = <?php echo $group_id; ?>;
        const currentUserId = <?php echo $user_id; ?>;

        // Função para verificar se o usuário está no final do chat
        function isUserAtBottom() {
            const container = $('#messages-container')[0];
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            return scrollTop + clientHeight >= scrollHeight - 30;
        }

        // Função para rolar para o final
        function scrollToBottom(force = false) {
            if (force || shouldScrollToBottom) {
                const container = $('#messages-container')[0];
                container.scrollTop = container.scrollHeight;
            }
        }

        // Detectar quando o usuário rola manualmente
        $('#messages-container').on('scroll', function() {
            shouldScrollToBottom = isUserAtBottom();
            updateScrollIndicators();
        });

        // Atualizar indicadores de scroll
        function updateScrollIndicators() {
            const container = $('#messages-container')[0];
            const hasScrollTop = container.scrollTop > 20;
            const hasScrollBottom = container.scrollTop + container.clientHeight < container.scrollHeight - 20;
            
            const $container = $('#messages-container');
            $container.toggleClass('has-scroll-top', hasScrollTop);
            $container.toggleClass('has-scroll-bottom', hasScrollBottom);
        }
        
        function getGroupImageUrl($image_data) {
    if (empty($image_data)) {
        return null;
    }
    
    // Verificar se já é um caminho completo ou relativo
    if (strpos($image_data, 'http') === 0 || strpos($image_data, '/') === 0) {
        return $image_data;
    }
    
    // Retornar caminho relativo padrão
    return '../../uploads/groups/' . $image_data;
}
        // Função para obter URL do avatar
        function getProfileImageUrl(imageData) {
    if (!imageData) {
        return null;
    }
    
    // Verificar se é base64
    if (/^[a-zA-Z0-9\/\r\n+]*={0,2}$/.test(imageData)) {
        return 'data:image/jpeg;base64,' + imageData;
    } else {
        // É um nome de arquivo
        return '../../uploads/profiles/' + imageData;
    }
}


        // Função para carregar mensagens
        let loadMessagesTimeout = null;

function loadMessages() {
    // Cancelar carregamento anterior se ainda estiver pendente
    if (loadMessagesTimeout) {
        clearTimeout(loadMessagesTimeout);
    }
    
    loadMessagesTimeout = setTimeout(function() {
        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: { 
                action: 'get_messages',
                group_id: groupId
            },
            dataType: 'json',
            timeout: 5000,
            success: function(messages) {
                if (messages.length !== lastMessageCount) {
                    const wasAtBottom = isUserAtBottom();
                    displayMessages(messages);
                    lastMessageCount = messages.length;
                    
                    if (wasAtBottom || (messages.length > 0 && messages[messages.length - 1].is_own)) {
                        setTimeout(() => scrollToBottom(true), 100);
                    }
                    
                    updateScrollIndicators();
                }
            },
            error: function(xhr, status, error) {
                if (status !== 'timeout') {
                    console.error('Erro ao carregar mensagens:', error);
                }
            },
            complete: function() {
                loadMessagesTimeout = null;
            }
        });
    }, 100); // Debounce de 100ms
}

        // Função para exibir mensagens
        function displayMessages(messages) {
    const container = $('#messages-container');
    container.empty();
    
    messages.forEach(function(message) {
        const messageClass = message.is_own ? 'message own' : 'message other';
        const timestamp = new Date(message.timestamp).toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        let avatarHtml = '';
        const avatarUrl = getProfileImageUrl(message.avatar);
        
        if (avatarUrl) {
            avatarHtml = `<img src="${avatarUrl}" 
                              alt="Avatar de ${message.username}" 
                              class="message-avatar clickable-avatar" 
                              data-user-id="${message.sender_id}"
                              onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                          <div class="message-avatar-default" style="display: none;">${message.username.charAt(0).toUpperCase()}</div>`;
        } else {
            const initial = message.username.charAt(0).toUpperCase();
            avatarHtml = `<div class="message-avatar-default">${initial}</div>`;
        }
        
        const messageHtml = `
            <div class="${messageClass}">
                ${avatarHtml}
                <div class="message-content">
                    <div class="message-username">${message.username}</div>
                    <div class="message-text">${escapeHtml(message.content)}</div>
                    <div class="message-timestamp">${timestamp}</div>
                </div>
            </div>
        `;
        
        container.append(messageHtml);
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

        // Enviar mensagem
        $('#message-form').on('submit', function(e) {
    e.preventDefault();
    
    // Prevenir múltiplos envios
    if (isSubmitting) {
        return false;
    }
    
    const content = $('input[name="content"]').val().trim();
    
    if (content) {
        isSubmitting = true; // Bloquear novos envios
        const $submitBtn = $('.message-button');
        const $input = $('input[name="content"]');
        
        // Desabilitar botão e input durante o envio
        $submitBtn.prop('disabled', true);
        $input.prop('disabled', true);
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { 
                action: 'send_message',
                content: content,
                group_id: groupId
            },
            dataType: 'json',
            timeout: 10000, // Timeout de 10 segundos
            success: function(response) {
                if (response.success) {
                    if (!response.duplicate) {
                        $input.val(''); // Limpar apenas se não for duplicata
                        shouldScrollToBottom = true;
                        loadMessages();
                    }
                } else {
                    console.error('Erro do servidor:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao enviar mensagem:', error);
                // Em caso de erro, não limpar o input para não perder a mensagem
            },
            complete: function() {
                // Reabilitar controles após o envio (sucesso ou erro)
                isSubmitting = false;
                $submitBtn.prop('disabled', false);
                $input.prop('disabled', false);
                $input.focus();
            }
        });
    }
});

        // Enviar mensagem com Enter
        $('input[name="content"]').on('keypress', function(e) {
    if (e.which === 13 && !isSubmitting) {
        e.preventDefault(); // Prevenir comportamento padrão
        $('#message-form').submit();
    }
});

        // Inicialização
        $(document).ready(function() {
    loadMessages();
    $('input[name="content"]').focus();
    
    // Carregar mensagens a cada 3 segundos em vez de 2
    setInterval(loadMessages, 3000);
    
    setTimeout(() => {
        scrollToBottom(true);
        updateScrollIndicators();
    }, 500);
});

</script>
</body>
</html>