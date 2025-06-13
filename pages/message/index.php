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

// Criar tabela user_typing se não existir
$create_typing_table = "CREATE TABLE IF NOT EXISTS user_typing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_chat_user (chat_id, user_id),
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$mysqli->query($create_typing_table);

// Gerenciar indicador de "está digitando"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'typing') {
    $is_typing = $_POST['is_typing'] ?? false;
    
    if ($is_typing) {
        // Criar/atualizar registro de digitação
        $sql_typing = "INSERT INTO user_typing (chat_id, user_id, timestamp) VALUES (?, ?, NOW()) 
                       ON DUPLICATE KEY UPDATE timestamp = NOW()";
        $stmt_typing = $mysqli->prepare($sql_typing);
        $stmt_typing->bind_param("ii", $chat_id, $user_id);
        $stmt_typing->execute();
    } else {
        // Remover registro de digitação
        $sql_remove_typing = "DELETE FROM user_typing WHERE chat_id = ? AND user_id = ?";
        $stmt_remove = $mysqli->prepare($sql_remove_typing);
        $stmt_remove->bind_param("ii", $chat_id, $user_id);
        $stmt_remove->execute();
    }
    
    echo json_encode(['success' => true]);
    exit();
}

// Verificar quem está digitando
if (isset($_GET['action']) && $_GET['action'] === 'check_typing') {
    $sql_check_typing = "SELECT u.username FROM user_typing ut 
                         JOIN users u ON ut.user_id = u.id 
                         WHERE ut.chat_id = ? AND ut.user_id != ? 
                         AND ut.timestamp > DATE_SUB(NOW(), INTERVAL 5 SECOND)";
    
    $stmt_check = $mysqli->prepare($sql_check_typing);
    $stmt_check->bind_param("ii", $chat_id, $user_id);
    $stmt_check->execute();
    $result_typing = $stmt_check->get_result();
    
    $typing_users = [];
    while ($row = $result_typing->fetch_assoc()) {
        $typing_users[] = $row['username'];
    }
    
    echo json_encode($typing_users);
    exit();
}

// Se for requisição AJAX para enviar mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $content = trim($_POST['content'] ?? '');
    
    if (!empty($content)) {
        // Primeiro, remover indicador de digitação
        $sql_remove_typing = "DELETE FROM user_typing WHERE chat_id = ? AND user_id = ?";
        $stmt_remove = $mysqli->prepare($sql_remove_typing);
        $stmt_remove->bind_param("ii", $chat_id, $user_id);
        $stmt_remove->execute();
        
        // Depois, inserir mensagem
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
/* Estilos melhorados para o chat - Mobile First */
.chat-header {
    background: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 10px;
    border-radius: 8px 8px 0 0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.friend-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #007bff;
    flex-shrink: 0;
}

.default-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #007bff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
    border: 2px solid #007bff;
    flex-shrink: 0;
}

.chat-container {
    height: calc(100vh - 200px);
    min-height: 300px;
    max-height: none;
    overflow-y: auto;
    padding: 10px;
    background: #f8f9fa;
    scroll-behavior: smooth;
    position: relative;
}

.message {
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 15px;
    max-width: 85%;
    animation: slideIn 0.3s ease-out;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    word-wrap: break-word;
    word-break: break-word;
    hyphens: auto;
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
    margin-left: auto;
    flex-direction: row-reverse;
    border-bottom-right-radius: 5px;
}

.message.other {
    background-color: #e9ecef;
    color: #333;
    border-bottom-left-radius: 5px;
}

.message-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.message-avatar-default {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 10px;
    flex-shrink: 0;
}

.message-content {
    flex: 1;
    min-width: 0;
}

.message-username {
    font-weight: 600;
    font-size: 0.75rem;
    margin-bottom: 2px;
    opacity: 0.8;
}

.message-text {
    line-height: 1.4;
    font-size: 0.9rem;
}

.message-timestamp {
    font-size: 0.65rem;
    opacity: 0.7;
    margin-top: 3px;
}

.message-form {
    display: flex;
    gap: 8px;
    padding: 10px 15px;
    background: white;
    border-top: 1px solid #e9ecef;
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.message-input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 20px;
    outline: none;
    font-size: 14px;
    transition: border-color 0.3s ease;
    min-width: 0;
}

.message-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.message-button {
    padding: 10px 15px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    flex-shrink: 0;
    min-width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    
}

.message-button:hover {
    background: #0056b3;
}

.typing-indicator {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
    padding: 6px 12px;
    border-radius: 15px;
    margin: 8px 10px;
    font-style: italic;
    animation: pulse 2s infinite;
    max-width: 180px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
}

@keyframes pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}

.typing-dots::after {
    content: '';
    animation: ellipsis 1.5s infinite;
}

@keyframes ellipsis {
    0% { content: ''; }
    25% { content: '.'; }
    50% { content: '..'; }
    75% { content: '...'; }
    100% { content: ''; }
}

.chat-wrapper {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    margin: 10px;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 120px);
}

/* Scrollbar personalizada para mobile */
.chat-container::-webkit-scrollbar {
    width: 4px;
}

.chat-container::-webkit-scrollbar-track {
    background: transparent;
}

.chat-container::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 2px;
}

.chat-container::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.3);
}

/* Header do chat responsivo */
.chat-header h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.chat-header small {
    font-size: 0.75rem;
}

/* Container principal responsivo */
.container {
    padding: 0;
    margin: 0;
    max-width: 100%;
}

/* Ajustes específicos para telas muito pequenas */
@media (max-width: 480px) {
    .chat-wrapper {
        margin: 5px;
        height: calc(100vh - 110px);
        border-radius: 0;
        border-left: none;
        border-right: none;
    }
    
    .message {
        max-width: 90%;
        padding: 6px 10px;
    }
    
    .message-text {
        font-size: 0.85rem;
    }
    
    .message-input {
        font-size: 16px; /* Evita zoom no iOS */
        padding: 8px 12px;
    }
    
    .message-button {
        min-width: 40px;
        height: 40px;
        padding: 8px 12px;
    }
    
    .friend-avatar,
    .default-avatar {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }
    
    .chat-header {
        padding: 10px 12px;
    }
    
    .chat-header h5 {
        font-size: 1rem;
    }
}

/* Ajustes para telas maiores (tablet) */
@media (min-width: 601px) {
    .chat-wrapper {
        margin: 20px auto;
        max-width: 800px;
        height: 600px;
    }
    
    .chat-container {
        height: calc(600px - 160px);
        max-height: 440px;
    }
    
    .message {
        max-width: 70%;
    }
    
    .container {
        padding: 0 15px;
    }
}

/* Fixes para problemas de layout */
.message-form {
    box-sizing: border-box;
}

.message-input {
    box-sizing: border-box;
}

/* Evitar problemas com viewport em mobile */
html {
    -webkit-text-size-adjust: 100%;
}

body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

/* Melhorar legibilidade em telas pequenas */
@media (max-width: 400px) {
    .message-username {
        font-size: 0.7rem;
    }
    
    .message-timestamp {
        font-size: 0.6rem;
    }
    
    .typing-indicator {
        font-size: 0.75rem;
        max-width: 150px;
    }
}
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="../homepage/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/">Meu Perfil</a></li>
                    <li class="nav-item"><a class="nav-link" href="../rpg_info">RPG</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">Mais...</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../settings/">Configurações</a></li>
                            <li><a class="dropdown-item" href="../friends/">Conexões</a></li>
                            <li><a class="dropdown-item" href="../chat/">Chat</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/">Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/">Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="chat-wrapper">
            <!-- Cabeçalho do chat com avatar do amigo -->
            <div class="chat-header">
                <?php if ($friend_avatar): ?>
                    <img src="<?php echo htmlspecialchars($friend_avatar); ?>" alt="Avatar" class="friend-avatar">
                <?php else: ?>
                    <div class="default-avatar">
                        <?php echo strtoupper(substr($friend_username, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h5 class="mb-1">Chat com <?php echo htmlspecialchars($friend_username); ?></h5>
                    <small class="text-muted">Online</small>
                </div>
            </div>

            <!-- Container das mensagens -->
            <div id="messages-container" class="chat-container">
                <!-- Mensagens serão carregadas aqui -->
            </div>

            <!-- Indicador de digitação -->
            <div id="typing-indicator" class="typing-indicator" style="display: none; margin: 0 15px;">
                <i class="bi bi-three-dots"></i>
                <span class="typing-text"></span>
            </div>

            <!-- Formulário para enviar mensagem -->
            <div style="padding: 15px;">
                <form id="message-form" class="message-form">
                    <input type="text" name="content" class="message-input" placeholder="Digite sua mensagem..." autocomplete="off" required>
                    <button type="submit" class="message-button">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let lastMessageCount = 0;
        let typingTimer;
        let isTyping = false;
        let shouldScrollToBottom = true;

        // Função para verificar se o usuário está no final do chat
        function isUserAtBottom() {
            const container = $('#messages-container');
            const scrollTop = container.scrollTop();
            const scrollHeight = container[0].scrollHeight;
            const clientHeight = container.height();
            return scrollTop + clientHeight >= scrollHeight - 30;
        }

        // Função para rolar para o final
        function scrollToBottom(force = false) {
            if (force || shouldScrollToBottom) {
                const container = $('#messages-container');
                container.stop().animate({
                    scrollTop: container[0].scrollHeight
                }, 200);
            }
        }

        // Detectar quando o usuário rola manualmente
        $('#messages-container').on('scroll', function() {
            shouldScrollToBottom = isUserAtBottom();
        });

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
                        
                        // Rolar para baixo se estava no final ou se é uma nova mensagem própria
                        if (wasAtBottom || (messages.length > 0 && messages[messages.length - 1].is_own)) {
                            scrollToBottom(true);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar mensagens:', error);
                }
            });
        }

        // Função para verificar quem está digitando
        function checkTyping() {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: { 
                    action: 'check_typing',
                    friend_id: <?php echo $friend_id; ?>
                },
                dataType: 'json',
                success: function(typingUsers) {
                    const indicator = $('#typing-indicator');
                    if (typingUsers.length > 0) {
                        const userName = typingUsers[0];
                        indicator.find('.typing-text').html(`${userName} está digitando<span class="typing-dots"></span>`);
                        indicator.show();
                        if (shouldScrollToBottom) {
                            scrollToBottom();
                        }
                    } else {
                        indicator.hide();
                    }
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
                if (message.avatar) {
                    avatarHtml = `<img src="${message.avatar}" alt="Avatar" class="message-avatar">`;
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

        // Função para enviar indicador de digitação
        function sendTypingIndicator(typing) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: { 
                    action: 'typing',
                    is_typing: typing,
                    friend_id: <?php echo $friend_id; ?>
                },
                dataType: 'json'
            });
        }

        // Gerenciar indicador de digitação
        $('input[name="content"]').on('input', function() {
            if (!isTyping) {
                isTyping = true;
                sendTypingIndicator(true);
            }
            
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                isTyping = false;
                sendTypingIndicator(false);
            }, 2000);
        });

        // Enviar mensagem
        $('#message-form').on('submit', function(e) {
            e.preventDefault();
            
            const content = $('input[name="content"]').val().trim();
            
            if (content) {
                // Parar indicador de digitação
                clearTimeout(typingTimer);
                isTyping = false;
                sendTypingIndicator(false);
                
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

        // Carregar mensagens inicialmente
        loadMessages();

        // Atualizar mensagens e verificar digitação
        setInterval(function() {
            loadMessages();
            checkTyping();
        }, 1500);

        // Focar no campo de entrada
        $('input[name="content"]').focus();

        // Parar indicador de digitação quando sair da página
        $(window).on('beforeunload', function() {
            if (isTyping) {
                sendTypingIndicator(false);
            }
        });

        // Inicializar scroll
        setTimeout(function() {
            scrollToBottom(true);
        }, 500);
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>