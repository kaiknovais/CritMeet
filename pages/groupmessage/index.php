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
    echo "<script>alert('Aventureiro não autenticado!'); window.location.href='../../pages/login/';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['friend_id'])) {
    echo "<script>alert('Companheiro não encontrado!'); window.location.href='../friends/';</script>";
    exit();
}

$friend_id = $_GET['friend_id'];

// Consultar o username do amigo
$sql_friend = "SELECT username FROM users WHERE id = ?";
$stmt_friend = $mysqli->prepare($sql_friend);
$stmt_friend->bind_param("i", $friend_id);
$stmt_friend->execute();
$stmt_friend->bind_result($friend_username);
$stmt_friend->fetch();
$stmt_friend->close();

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
    $sql_create_chat = "INSERT INTO chats (is_group) VALUES (0)";
    $stmt_create = $mysqli->prepare($sql_create_chat);
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

// Gerenciar "está digitando"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'typing') {
    // Criar tabela temporária se não existir
    $mysqli->query("CREATE TEMPORARY TABLE IF NOT EXISTS typing_status (
        chat_id INT,
        user_id INT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (chat_id, user_id)
    )");
    
    $sql_typing = "REPLACE INTO typing_status (chat_id, user_id) VALUES (?, ?)";
    $stmt_typing = $mysqli->prepare($sql_typing);
    $stmt_typing->bind_param("ii", $chat_id, $user_id);
    $stmt_typing->execute();
    
    echo json_encode(['success' => true]);
    exit();
}

// Verificar quem está digitando
if (isset($_GET['action']) && $_GET['action'] === 'check_typing') {
    $sql_check_typing = "SELECT u.username 
                        FROM typing_status ts
                        JOIN users u ON ts.user_id = u.id
                        WHERE ts.chat_id = ? 
                        AND ts.user_id != ? 
                        AND ts.timestamp > DATE_SUB(NOW(), INTERVAL 3 SECOND)";
    
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
        $sql_insert = "INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)";
        $stmt_insert = $mysqli->prepare($sql_insert);
        $stmt_insert->bind_param("iis", $chat_id, $user_id, $content);
        
        if ($stmt_insert->execute()) {
            // Remover status de digitando
            $mysqli->query("DELETE FROM typing_status WHERE chat_id = $chat_id AND user_id = $user_id");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
    exit();
}

// Se for requisição AJAX para buscar mensagens
if (isset($_GET['action']) && $_GET['action'] === 'get_messages') {
    $sql_messages = "SELECT m.sender_id, m.content, m.timestamp, u.username 
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
            'is_own' => $row['sender_id'] == $user_id
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
    <title>Conversa Privada com <?php echo htmlspecialchars($friend_username); ?> - CritMeet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        :root {
            --rpg-primary: #8B4513;
            --rpg-secondary: #D2691E;
            --rpg-dark: #654321;
            --rpg-light: #F4E4BC;
            --rpg-gold: #FFD700;
            --rpg-red: #B22222;
        }
        
        body {
            background: linear-gradient(135deg, #2C1810 0%, #4A2C17 100%);
            color: var(--rpg-light);
            font-family: 'Cinzel', serif;
            min-height: 100vh;
        }
        
        .chat-header {
            background: linear-gradient(45deg, var(--rpg-dark), var(--rpg-primary));
            border: 2px solid var(--rpg-gold);
            border-radius: 15px 15px 0 0;
            padding: 20px;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        
        .chat-header::before {
            content: "⚔️";
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
        }
        
        .chat-header::after {
            content: "🛡️";
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
        }
        
        .chat-container {
            max-height: 500px;
            overflow-y: auto;
            background: linear-gradient(to bottom, rgba(139, 69, 19, 0.1), rgba(210, 105, 30, 0.1));
            border: 2px solid var(--rpg-secondary);
            border-top: none;
            padding: 20px;
            margin-bottom: 0;
            position: relative;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.3);
        }
        
        .chat-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        /* Scrollbar personalizada */
        .chat-container::-webkit-scrollbar {
            width: 12px;
        }
        
        .chat-container::-webkit-scrollbar-track {
            background: var(--rpg-dark);
            border-radius: 6px;
        }
        
        .chat-container::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, var(--rpg-secondary), var(--rpg-gold));
            border-radius: 6px;
            border: 2px solid var(--rpg-dark);
        }
        
        .chat-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, var(--rpg-gold), var(--rpg-secondary));
        }
        
        .message {
            margin-bottom: 15px;
            padding: 12px 18px;
            border-radius: 15px;
            max-width: 75%;
            position: relative;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 215, 0, 0.3);
            animation: messageAppear 0.3s ease-out;
        }
        
        @keyframes messageAppear {
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
            background: linear-gradient(135deg, var(--rpg-primary), var(--rpg-secondary));
            color: var(--rpg-light);
            margin-left: auto;
            text-align: right;
            border-left: 4px solid var(--rpg-gold);
        }
        
        .message.own::before {
            content: "🗨️";
            position: absolute;
            right: -8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
        }
        
        .message.other {
            background: linear-gradient(135deg, rgba(244, 228, 188, 0.9), rgba(244, 228, 188, 0.7));
            color: var(--rpg-dark);
            border-right: 4px solid var(--rpg-gold);
        }
        
        .message.other::before {
            content: "💬";
            position: absolute;
            left: -8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
        }
        
        .message strong {
            color: var(--rpg-gold);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .message small {
            opacity: 0.8;
            font-style: italic;
        }
        
        .typing-indicator {
            opacity: 0.7;
            font-style: italic;
            color: var(--rpg-gold);
            margin: 10px 0;
            padding: 8px 15px;
            background: rgba(139, 69, 19, 0.3);
            border-radius: 10px;
            border-left: 3px solid var(--rpg-gold);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        .message-form {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: linear-gradient(45deg, var(--rpg-dark), var(--rpg-primary));
            border: 2px solid var(--rpg-gold);
            border-radius: 0 0 15px 15px;
            border-top: none;
            box-shadow: 0 -4px 15px rgba(0,0,0,0.5);
        }
        
        .message-input {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid var(--rpg-secondary);
            border-radius: 25px;
            outline: none;
            background: var(--rpg-light);
            color: var(--rpg-dark);
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .message-input:focus {
            border-color: var(--rpg-gold);
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
            transform: scale(1.02);
        }
        
        .message-button {
            padding: 12px 25px;
            background: linear-gradient(45deg, var(--rpg-gold), #FFA500);
            color: var(--rpg-dark);
            border: 2px solid var(--rpg-secondary);
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-family: inherit;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .message-button::before {
            content: "📜";
            margin-right: 8px;
        }
        
        .message-button:hover {
            background: linear-gradient(45deg, #FFA500, var(--rpg-gold));
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }
        
        .message-button:active {
            transform: translateY(0);
        }
        
        .navbar {
            background: linear-gradient(45deg, var(--rpg-dark), var(--rpg-primary)) !important;
            border-bottom: 3px solid var(--rpg-gold);
        }
        
        .navbar-brand, .nav-link {
            color: var(--rpg-light) !important;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            transition: color 0.3s ease;
        }
        
        .navbar-brand:hover, .nav-link:hover {
            color: var(--rpg-gold) !important;
        }
        
        .chat-wrapper {
            max-width: 900px;
            margin: 30px auto;
            background: rgba(0,0,0,0.2);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        
        .container {
            padding: 0;
        }
        
        h2 {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/">⚔️ CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="../homepage/">🏠 Taverna</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/">👤 Meu Perfil</a></li>
                    <li class="nav-item"><a class="nav-link" href="../rpg_info">🎲 RPG</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">⚡ Mais...</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../settings/">⚙️ Configurações</a></li>
                            <li><a class="dropdown-item" href="../friends/">🤝 Conexões</a></li>
                            <li><a class="dropdown-item" href="../chat/">💬 Chat</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/">🚪 Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/">👑 Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="chat-wrapper">
            <div class="chat-header">
                <h3>🗣️ Conversa Privada com <?php echo htmlspecialchars($friend_username); ?> 🗣️</h3>
                <small>Que a aventura das palavras commence!</small>
            </div>
            
            <!-- Container das mensagens -->
            <div id="messages-container" class="chat-container">
                <!-- Mensagens serão carregadas aqui -->
            </div>
            
            <!-- Indicador de digitação -->
            <div id="typing-indicator" class="typing-indicator" style="display: none;">
                <span id="typing-text"></span>
            </div>

            <!-- Formulário para enviar mensagem -->
            <form id="message-form" class="message-form">
                <input type="text" name="content" class="message-input" placeholder="Digite sua mensagem, aventureiro..." autocomplete="off" required>
                <button type="submit" class="message-button">Enviar</button>
            </form>
        </div>
    </div>

<script>
let lastMessageCount = 0;
let typingTimer = null;
let isTyping = false;

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
                displayMessages(messages);
                lastMessageCount = messages.length;
                scrollToBottom();
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
        
        const messageHtml = `
            <div class="${messageClass}">
                <div><strong>${message.username}:</strong> ${message.content}</div>
                <small>📅 ${timestamp}</small>
            </div>
        `;
        
        container.append(messageHtml);
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
            if (typingUsers.length > 0) {
                $('#typing-text').html(`✍️ ${typingUsers[0]} está digitando...`);
                $('#typing-indicator').show();
            } else {
                $('#typing-indicator').hide();
            }
        }
    });
}

// Função para enviar status de digitação
function sendTypingStatus() {
    if (!isTyping) {
        isTyping = true;
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { 
                action: 'typing',
                friend_id: <?php echo $friend_id; ?>
            },
            dataType: 'json'
        });
    }
    
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        isTyping = false;
    }, 3000);
}

// Função para rolar para o final
function scrollToBottom() {
    const container = $('#messages-container');
    container.scrollTop(container[0].scrollHeight);
}

// Event listener para digitação
$('input[name="content"]').on('keypress keyup', function() {
    sendTypingStatus();
});

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
                    isTyping = false;
                    clearTimeout(typingTimer);
                    loadMessages();
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao enviar mensagem:', error);
                alert('Erro ao enviar mensagem, aventureiro!');
            }
        });
    }
});

// Carregar mensagens inicialmente
loadMessages();

// Atualizar mensagens e verificar digitação a cada 1.5 segundos
setInterval(function() {
    loadMessages();
    checkTyping();
}, 1500);

// Focar no campo de entrada
$('input[name="content"]').focus();

// Easter egg - comandos especiais
$('input[name="content"]').on('keyup', function(e) {
    const content = $(this).val().toLowerCase();
    if (content === '/roll' && e.keyCode === 13) {
        const dice = Math.floor(Math.random() * 20) + 1;
        $(this).val(`🎲 Rolou um D20: ${dice}!`);
    }
});
</script>

    <?php include 'footer.php'; ?>
</body>
</html>