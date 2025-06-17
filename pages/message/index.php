<?php
require_once __DIR__ . '/../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

if ($user_id) {
    $query = "SELECT admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $is_admin = $row['admin'] == 1;
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['friend_id'])) {
    echo "<script>alert('ID do amigo não fornecido.'); window.location.href='../friends/';</script>";
    exit();
}

$friend_id = $_GET['friend_id'];

// Função para exibir imagem do perfil (mesma do profile.php)
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

// Consultar informações do amigo (username e avatar)
$sql_friend = "SELECT username, image FROM users WHERE id = ?";
$stmt_friend = $mysqli->prepare($sql_friend);
$stmt_friend->bind_param("i", $friend_id);
$stmt_friend->execute();
$result_friend = $stmt_friend->get_result();
$friend_data = $result_friend->fetch_assoc();
$friend_username = $friend_data['username'];
$friend_avatar = $friend_data['image'];
$stmt_friend->close();

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

// Encontrar ou criar chat
$sql_find_chat = "SELECT DISTINCT c.id as chat_id 
                  FROM chats c
                  JOIN chat_members cm1 ON c.id = cm1.chat_id
                  JOIN chat_members cm2 ON c.id = cm2.chat_id
                  WHERE c.is_group = 0 
                  AND cm1.user_id = ? 
                  AND cm2.user_id = ?";

$stmt = $mysqli->prepare($sql_find_chat);
$stmt->bind_param("ii", $user_id, $friend_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Criar novo chat
    $sql_create_chat = "INSERT INTO chats (is_group, creator_id) VALUES (0, ?)";
    $stmt_create = $mysqli->prepare($sql_create_chat);
    $stmt_create->bind_param("i", $user_id);
    $stmt_create->execute();
    $chat_id = $stmt_create->insert_id;
    
    // Adicionar membros
    $sql_add_member = "INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?)";
    $stmt_add = $mysqli->prepare($sql_add_member);
    
    $stmt_add->bind_param("ii", $chat_id, $user_id);
    $stmt_add->execute();
    
    $stmt_add->bind_param("ii", $chat_id, $friend_id);
    $stmt_add->execute();
} else {
    $row = $result->fetch_assoc();
    $chat_id = $row['chat_id'];
}

// Se for requisição AJAX para enviar mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $content = trim($_POST['content'] ?? '');
    
    if (!empty($content)) {
        $sql_insert = "INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)";
        $stmt_insert = $mysqli->prepare($sql_insert);
        $stmt_insert->bind_param("iis", $chat_id, $user_id, $content);
        
        if ($stmt_insert->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
    exit();
}

// Se for requisição AJAX para buscar mensagens
if (isset($_GET['action']) && $_GET['action'] === 'get_messages') {
    $sql_messages = "SELECT m.sender_id, m.content, m.timestamp, u.username, u.image 
                     FROM messages m
                     JOIN users u ON m.sender_id = u.id 
                     WHERE m.chat_id = ? 
                     ORDER BY m.timestamp ASC";
    
    $stmt_messages = $mysqli->prepare($sql_messages);
    $stmt_messages->bind_param("i", $chat_id);
    $stmt_messages->execute();
    $result_messages = $stmt_messages->get_result();
    
    $messages = [];
    while ($row = $result_messages->fetch_assoc()) {
        $messages[] = [
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
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Chat com <?php echo htmlspecialchars($friend_username); ?></title>
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
    min-height: 0; /* Importante para flexbox funcionar corretamente */
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
    gap: 10px;
    z-index: 10;
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
    min-height: 0; /* Força o container a não crescer além do disponível */
}

/* Input fixo na parte inferior */
.message-form-container {
    flex-shrink: 0;
    padding: 15px;
    background: white;
    border-top: 1px solid #e9ecef;
}

/* Estilos dos avatares */
.friend-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #007bff;
    flex-shrink: 0;
}

.default-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #007bff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 16px;
    border: 2px solid #007bff;
    flex-shrink: 0;
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
    flex-shrink: 0; /* Impede que mensagens encolham */
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
    background-color: #e9ecef;
    color: #333;
    align-self: flex-start;
    border-bottom-left-radius: 5px;
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.3);
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
        font-size: 16px; /* Evita zoom no iOS */
        padding: 10px 15px;
    }
    
    .message-button {
        width: 44px;
        height: 44px;
        padding: 10px;
    }
    
    .friend-avatar,
    .default-avatar {
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
}

/* Ajustes para telas muito pequenas */
@media (max-width: 480px) {
    .message-username {
        font-size: 0.75rem;
    }
    
    .message-timestamp {
        font-size: 0.65rem;
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
                    <?php 
                    $friend_avatar_url = getProfileImageUrl($friend_avatar);
                    if ($friend_avatar_url !== 'default-avatar.png'): 
                    ?>
                        <img src="<?php echo htmlspecialchars($friend_avatar_url); ?>" 
                             alt="Avatar de <?php echo htmlspecialchars($friend_username); ?>" 
                             class="friend-avatar"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="default-avatar" style="display: none;">
                            <?php echo strtoupper(substr($friend_username, 0, 1)); ?>
                        </div>
                    <?php else: ?>
                        <div class="default-avatar">
                            <?php echo strtoupper(substr($friend_username, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h5>Chat com <?php echo htmlspecialchars($friend_username); ?></h5>
                        <small class="text-muted">Online</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let lastMessageCount = 0;
        let shouldScrollToBottom = true;

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

        // Função para obter URL do avatar
        function getProfileImageUrl(imageData) {
            if (!imageData) {
                return null;
            }
            
            if (/^[a-zA-Z0-9\/\r\n+]*={0,2}$/.test(imageData)) {
                return 'data:image/jpeg;base64,' + imageData;
            } else {
                return '../../uploads/profiles/' + imageData;
            }
        }

        // Função para carregar mensagens
        function loadMessages() {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: { 
                    action: 'get_messages',
                    friend_id: <?php echo $friend_id; ?>
                },
                dataType: 'json',
                success: function(messages) {
                    if (messages.length !== lastMessageCount) {
                        const wasAtBottom = isUserAtBottom();
                        displayMessages(messages);
                        lastMessageCount = messages.length;
                        
                        // Rolar para baixo se estava no final ou se é nova mensagem própria
                        if (wasAtBottom || (messages.length > 0 && messages[messages.length - 1].is_own)) {
                            setTimeout(() => scrollToBottom(true), 100);
                        }
                        
                        updateScrollIndicators();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar mensagens:', error);
                }
            });
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
                    avatarHtml = `<img src="${avatarUrl}" alt="Avatar de ${message.username}" class="message-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
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
                            <div class="message-text">${message.content}</div>
                            <div class="message-timestamp">${timestamp}</div>
                        </div>
                    </div>
                `;
                
                container.append(messageHtml);
            });
        }

        // Enviar mensagem
        $('#message-form').on('submit', function(e) {
            e.preventDefault();
            
            const content = $('input[name="content"]').val().trim();
            
            if (content) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { 
                        action: 'send_message',
                        content: content,
                        friend_id: <?php echo $friend_id; ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('input[name="content"]').val('');
                            shouldScrollToBottom = true;
                            loadMessages();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro ao enviar mensagem:', error);
                        alert('Erro ao enviar mensagem');
                    }
                });
            }
        });

        // Enviar mensagem com Enter
        $('input[name="content"]').on('keypress', function(e) {
            if (e.which === 13) {
                $('#message-form').submit();
            }
        });

        // Inicialização
        $(document).ready(function() {
            loadMessages();
            $('input[name="content"]').focus();
            
            // Carregar mensagens periodicamente
            setInterval(loadMessages, 2000);
            
            // Scroll inicial após um pequeno delay
            setTimeout(() => {
                scrollToBottom(true);
                updateScrollIndicators();
            }, 500);
        });
    </script>
</body>
</html>