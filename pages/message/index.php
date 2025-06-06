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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat com <?php echo htmlspecialchars($friend_username); ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .chat-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 10px;
            max-width: 70%;
        }
        
        .message.own {
            background-color: #007bff;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        
        .message.other {
            background-color: #e9ecef;
            color: #333;
        }
        
        .message-form {
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .message-button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Mais...
                        </a>
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
        <h2>Chat com <?php echo htmlspecialchars($friend_username); ?></h2>
        
        <!-- Container das mensagens -->
        <div id="messages-container" class="chat-container">
            <!-- Mensagens serão carregadas aqui -->
        </div>

        <!-- Formulário para enviar mensagem -->
        <form id="message-form" class="message-form">
            <input type="text" name="content" class="message-input" placeholder="Digite sua mensagem..." autocomplete="off" required >
            <button type="submit" class="message-button">Enviar</button>
        </form>
    </div>

<script>
let lastMessageCount = 0;

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
                <small>${timestamp}</small>
            </div>
        `;
        
        container.append(messageHtml);
    });
}

// Função para rolar para o final
function scrollToBottom() {
    const container = $('#messages-container');
    container.scrollTop(container[0].scrollHeight);
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
                    loadMessages(); // Recarregar mensagens imediatamente
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao enviar mensagem:', error);
                alert('Erro ao enviar mensagem');
            }
        });
    }
});

// Carregar mensagens inicialmente
loadMessages();

// Atualizar mensagens a cada 2 segundos
setInterval(loadMessages, 2000);

// Focar no campo de entrada
$('input[name="content"]').focus();
</script>

    <?php include 'footer.php'; ?>
</body>
</html>