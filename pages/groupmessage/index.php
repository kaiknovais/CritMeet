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

if (!isset($_GET['group_id'])) {
    echo "<script>alert('Guilda não encontrada!'); window.location.href='../friends/';</script>";
    exit();
}

$group_id = $_GET['group_id'];

// Função para exibir imagem do perfil
function getProfileImageUrl($image_data) {
    if (empty($image_data)) {
        return 'default-avatar.png';
    }
    
    if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $image_data)) {
        return 'data:image/jpeg;base64,' . $image_data;
    } else {
        return '../../uploads/profiles/' . $image_data;
    }
}

// Verificar se o usuário é membro do grupo
$sql_check_member = "SELECT COUNT(*) as is_member FROM chat_members WHERE chat_id = ? AND user_id = ?";
$stmt_check = $mysqli->prepare($sql_check_member);
$stmt_check->bind_param("ii", $group_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$check_data = $result_check->fetch_assoc();

if ($check_data['is_member'] == 0) {
    echo "<script>alert('Você não faz parte desta guilda!'); window.location.href='../friends/';</script>";
    exit();
}

// Consultar informações do grupo
$sql_group = "SELECT name, description, creator_id FROM chats WHERE id = ? AND is_group = 1";
$stmt_group = $mysqli->prepare($sql_group);
$stmt_group->bind_param("i", $group_id);
$stmt_group->execute();
$result_group = $stmt_group->get_result();
$group_data = $result_group->fetch_assoc();

if (!$group_data) {
    echo "<script>alert('Guilda não encontrada!'); window.location.href='../friends/';</script>";
    exit();
}

$group_name = $group_data['name'];
$group_description = $group_data['description'];
$group_creator_id = $group_data['creator_id'];
$is_group_creator = ($user_id == $group_creator_id);

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

// Contar membros do grupo
$sql_count_members = "SELECT COUNT(*) as member_count FROM chat_members WHERE chat_id = ?";
$stmt_count = $mysqli->prepare($sql_count_members);
$stmt_count->bind_param("i", $group_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$count_data = $result_count->fetch_assoc();
$member_count = $count_data['member_count'];

$chat_id = $group_id; // Para compatibilidade com o código de mensagens

// Gerenciar "está digitando"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'typing') {
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

// Se for requisição AJAX para buscar membros do grupo
if (isset($_GET['action']) && $_GET['action'] === 'get_members') {
    $sql_members = "SELECT u.id, u.username, u.image 
                    FROM chat_members cm
                    JOIN users u ON cm.user_id = u.id 
                    WHERE cm.chat_id = ?
                    ORDER BY u.username ASC";
    
    $stmt_members = $mysqli->prepare($sql_members);
    $stmt_members->bind_param("i", $group_id);
    $stmt_members->execute();
    $result_members = $stmt_members->get_result();
    
    $members = [];
    while ($row = $result_members->fetch_assoc()) {
        $members[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'avatar' => $row['image'],
            'is_creator' => $row['id'] == $group_creator_id
        ];
    }
    
    echo json_encode($members);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Guilda: <?php echo htmlspecialchars($group_name); ?> - CritMeet</title>
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
            --rpg-green: #228B22;
        }

        /* Reset básico para evitar problemas de layout */
        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background: linear-gradient(135deg, #2C1810 0%, #4A2C17 100%);
            color: var(--rpg-light);
            font-family: 'Cinzel', serif;
        }

        /* Layout principal em flexbox */
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
            background: rgba(0,0,0,0.3);
            border: 2px solid var(--rpg-gold);
            border-radius: 15px;
            overflow: hidden;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
        }

        /* Header do chat fixo */
        .chat-header {
            flex-shrink: 0;
            background: linear-gradient(45deg, var(--rpg-dark), var(--rpg-primary));
            padding: 15px 20px;
            border-bottom: 2px solid var(--rpg-gold);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 10;
            position: relative;
        }

        .chat-header::before {
            content: "⚔️";
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }

        .chat-header::after {
            content: "🛡️";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }

        /* Container de mensagens com rolagem */
        .chat-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px;
            background: linear-gradient(to bottom, rgba(139, 69, 19, 0.1), rgba(210, 105, 30, 0.1));
            display: flex;
            flex-direction: column;
            min-height: 0;
            position: relative;
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

        /* Input fixo na parte inferior */
        .message-form-container {
            flex-shrink: 0;
            padding: 20px;
            background: linear-gradient(45deg, var(--rpg-dark), var(--rpg-primary));
            border-top: 2px solid var(--rpg-gold);
        }

        /* Estilos dos avatares no header */
        .group-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--rpg-gold), #FFA500);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--rpg-dark);
            font-weight: bold;
            font-size: 20px;
            border: 3px solid var(--rpg-light);
            flex-shrink: 0;
        }

        .group-info {
            flex: 1;
            min-width: 0;
        }

        .group-info h4 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--rpg-gold);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }

        .group-info small {
            font-size: 0.85rem;
            opacity: 0.9;
            display: block;
            margin-top: 2px;
        }

        /* Botão de membros */
        .members-btn {
            background: linear-gradient(45deg, var(--rpg-gold), #FFA500);
            color: var(--rpg-dark);
            border: 2px solid var(--rpg-secondary);
            border-radius: 20px;
            padding: 8px 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .members-btn:hover {
            background: linear-gradient(45deg, #FFA500, var(--rpg-gold));
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }

        /* Indicador de digitação */
        .typing-indicator {
            opacity: 0.8;
            font-style: italic;
            color: var(--rpg-gold);
            margin: 10px 0;
            padding: 10px 18px;
            background: rgba(139, 69, 19, 0.4);
            border-radius: 15px;
            border-left: 4px solid var(--rpg-gold);
            animation: pulse 2s infinite;
            backdrop-filter: blur(5px);
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 0.8; 
                transform: scale(1);
            }
            50% { 
                opacity: 1; 
                transform: scale(1.02);
            }
        }

        /* Estilos das mensagens */
        .message {
            margin-bottom: 20px;
            padding: 12px 20px;
            border-radius: 20px;
            max-width: 85%;
            animation: messageAppear 0.4s ease-out;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            flex-shrink: 0;
            position: relative;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 215, 0, 0.2);
        }

        @keyframes messageAppear {
            from { 
                opacity: 0; 
                transform: translateY(15px) scale(0.95);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1);
            }
        }

        .message.own {
            background: linear-gradient(135deg, var(--rpg-primary), var(--rpg-secondary));
            color: var(--rpg-light);
            align-self: flex-end;
            flex-direction: row-reverse;
            border-bottom-right-radius: 8px;
            border-left: 4px solid var(--rpg-gold);
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
        }

        .message.other {
            background: linear-gradient(135deg, rgba(244, 228, 188, 0.95), rgba(244, 228, 188, 0.8));
            color: var(--rpg-dark);
            align-self: flex-start;
            border-bottom-left-radius: 8px;
            border-right: 4px solid var(--rpg-gold);
            box-shadow: 0 4px 15px rgba(244, 228, 188, 0.2);
        }

        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.4);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .message-avatar-default {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--rpg-secondary), var(--rpg-gold));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--rpg-dark);
            font-weight: bold;
            font-size: 14px;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.4);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .message.own .message-avatar,
        .message.own .message-avatar-default {
            border-color: rgba(255,255,255,0.6);
        }

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-username {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 4px;
            opacity: 0.9;
        }

        .message.own .message-username {
            color: var(--rpg-gold);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .message.other .message-username {
            color: var(--rpg-primary);
        }

        .message-text {
            line-height: 1.5;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .message-timestamp {
            font-size: 0.7rem;
            opacity: 0.7;
            font-style: italic;
        }

        /* Formulário de mensagem */
        .message-form {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        .message-input {
            flex: 1;
            padding: 15px 25px;
            border: 2px solid var(--rpg-secondary);
            border-radius: 30px;
            outline: none;
            background: var(--rpg-light);
            color: var(--rpg-dark);
            font-family: inherit;
            font-size: 14px;
            min-width: 0;
            transition: all 0.3s ease;
        }

        .message-input:focus {
            border-color: var(--rpg-gold);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
            transform: scale(1.02);
        }

        .message-input::placeholder {
            color: rgba(101, 67, 33, 0.6);
            font-style: italic;
        }

        .message-button {
            padding: 15px 20px;
            background: linear-gradient(45deg, var(--rpg-gold), #FFA500);
            color: var(--rpg-dark);
            border: 2px solid var(--rpg-secondary);
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            font-family: inherit;
            transition: all 0.3s ease;
            flex-shrink: 0;
            min-width: 60px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .message-button::before {
            content: "📜";
            margin-right: 6px;
            font-size: 16px;
        }

        .message-button:hover {
            background: linear-gradient(45deg, #FFA500, var(--rpg-gold));
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
        }

        .message-button:active {
            transform: translateY(-1px);
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

        /* Modal de membros */
        .modal-content {
            background: linear-gradient(135deg, var(--rpg-dark), var(--rpg-primary));
            color: var(--rpg-light);
            border: 2px solid var(--rpg-gold);
            border-radius: 15px;
        }

        .modal-header {
            border-bottom: 2px solid var(--rpg-gold);
        }

        .modal-title {
            color: var(--rpg-gold);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            background: rgba(244, 228, 188, 0.1);
            border-radius: 10px;
            margin-bottom: 8px;
            border: 1px solid rgba(255, 215, 0, 0.2);
            transition: all 0.3s ease;
        }

        .member-item:hover {
            background: rgba(244, 228, 188, 0.2);
            transform: translateX(5px);
        }

        .member-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--rpg-gold);
        }

        .member-avatar-default {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--rpg-secondary), var(--rpg-gold));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--rpg-dark);
            font-weight: bold;
            font-size: 12px;
            border: 2px solid var(--rpg-gold);
        }

        .creator-badge {
            background: linear-gradient(45deg, var(--rpg-gold), #FFA500);
            color: var(--rpg-dark);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: auto;
        }

        /* Responsividade para mobile */
        @media (max-width: 768px) {
            .chat-page-container {
                padding: 5px;
            }
            
            .chat-header {
                padding: 12px 15px;
                gap: 10px;
            }

            .chat-header::before,
            .chat-header::after {
                font-size: 16px;
            }
            
            .group-avatar {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .group-info h4 {
                font-size: 1.1rem;
            }

            .members-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .message {
                max-width: 90%;
                padding: 10px 15px;
                gap: 10px;
                margin-bottom: 15px;
            }
            
            .message-text {
                font-size: 0.9rem;
            }
            
            .message-input {
                font-size: 16px;
                padding: 12px 20px;
            }
            
            .message-button {
                min-width: 50px;
                height: 46px;
                padding: 12px 15px;
            }

            .message-button::before {
                font-size: 14px;
                margin-right: 4px;
            }
            
            .message-avatar,
            .message-avatar-default {
                width: 30px;
                height: 30px;
                font-size: 12px;
            }
            
            .chat-container {
                padding: 15px;
            }
            
            .message-form-container {
                padding: 15px;
            }
        }

        /* Ajustes para telas muito pequenas */
        @media (max-width: 480px) {
            .group-info h4 {
                font-size: 1rem;
            }

            .group-info small {
                font-size: 0.75rem;
            }

            .message-username {
                font-size: 0.8rem;
            }
            
            .message-timestamp {
                font-size: 0.65rem;
            }
        }

        /* Easter egg styles */
        .dice-roll {
            color: var(--rpg-gold);
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            animation: diceGlow 2s ease-in-out;
        }

        @keyframes diceGlow {
            0%, 100% { text-shadow: 1px 1px 2px rgba(0,0,0,0.8); }
            50% { text-shadow: 0 0 10px var(--rpg-gold), 1px 1px 2px rgba(0,0,0,0.8); }
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