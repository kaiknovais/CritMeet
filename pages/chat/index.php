<?php
require_once __DIR__ . '/../../config.php';
session_start();

// Verifica se o usuário está autenticado
$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

if (!$user_id) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
    exit();
}

// Verificar se o usuário é administrador
$query = "SELECT admin FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $is_admin = $row['admin'] == 1;
}
$stmt->close();

// Função para obter URL da imagem de perfil
function getProfileImageUrl($image_data) {
    if (empty($image_data)) {
        return null;
    }
    
    // Verificar se é base64 (dados antigos)
    if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $image_data)) {
        return 'data:image/jpeg;base64,' . $image_data;
    } else {
        // É um nome de arquivo
        $file_path = '../../uploads/profiles/' . $image_data;
        if (file_exists($file_path)) {
            return $file_path;
        }
    }
    return null;
}

// Função para obter URL da imagem do grupo
function getGroupImageUrl($image_data) {
    if (empty($image_data)) {
        return null;
    }
    $file_path = '../../uploads/groups/' . $image_data;
    if (file_exists($file_path)) {
        return $file_path;
    }
    return null;
}

// Consultar amigos aceitos
$sql_friends = "SELECT u.id, u.username, u.name, u.image
                FROM friends f 
                JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id) 
                WHERE (f.user_id = ? OR f.friend_id = ?) 
                AND f.status = 'accepted' 
                AND u.id != ?";
$stmt = $mysqli->prepare($sql_friends);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result_friends = $stmt->get_result();
$friends = [];
while ($row = $result_friends->fetch_assoc()) {
    $friends[] = $row;
}

// Consultar grupos em que o usuário participa
$sql_groups = "SELECT c.id, c.name, c.creator_id, c.image,
               (SELECT COUNT(*) FROM chat_members WHERE chat_id = c.id) as member_count,
               (SELECT COUNT(*) FROM messages WHERE chat_id = c.id) as message_count
               FROM chats c 
               JOIN chat_members cm ON c.id = cm.chat_id 
               WHERE cm.user_id = ? AND c.is_group = 1
               ORDER BY c.created_at DESC";
$stmt = $mysqli->prepare($sql_groups);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_groups = $stmt->get_result();
$groups = [];
while ($row = $result_groups->fetch_assoc()) {
    $groups[] = $row;
}

// Criar grupo com membros
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    $selected_friends = $_POST['selected_friends'] ?? [];

    if (!empty($group_name)) {
        // Verificar se já existe um grupo com o mesmo nome
        $sql_check_group = "SELECT id FROM chats WHERE name = ?";
        $stmt_check_group = $mysqli->prepare($sql_check_group);
        $stmt_check_group->bind_param("s", $group_name);
        $stmt_check_group->execute();
        $stmt_check_group->store_result();

        if ($stmt_check_group->num_rows > 0) {
            echo "<script>alert('Já existe um grupo com esse nome. Escolha outro nome.');</script>";
        } else {
            // Iniciar transação
            $mysqli->begin_transaction();
            
            try {
                // Criar novo grupo
                $sql_create_group = "INSERT INTO chats (is_group, name, creator_id) VALUES (1, ?, ?)";
                $stmt_create_group = $mysqli->prepare($sql_create_group);
                $stmt_create_group->bind_param("si", $group_name, $user_id);
                $stmt_create_group->execute();
                $chat_id = $stmt_create_group->insert_id;

                // Adicionar criador como admin
                $sql_add_member = "INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'admin')";
                $stmt_member = $mysqli->prepare($sql_add_member);
                $stmt_member->bind_param("ii", $chat_id, $user_id);
                $stmt_member->execute();

                // Adicionar amigos selecionados
                if (!empty($selected_friends)) {
                    $sql_add_friend = "INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'member')";
                    $stmt_add_friend = $mysqli->prepare($sql_add_friend);
                    
                    foreach ($selected_friends as $friend_id) {
                        $friend_id = intval($friend_id);
                        if ($friend_id > 0) {
                            $stmt_add_friend->bind_param("ii", $chat_id, $friend_id);
                            $stmt_add_friend->execute();
                        }
                    }
                }

                $mysqli->commit();
                echo "<script>alert('Grupo criado com sucesso!'); window.location.href='';</script>";
            } catch (Exception $e) {
                $mysqli->rollback();
                echo "<script>alert('Erro ao criar o grupo. Tente novamente.');</script>";
            }
        }
    } else {
        echo "<script>alert('O nome do grupo não pode estar vazio.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CritMeet - Chats e Grupos</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        .chat-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .friend-avatar, .group-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        
        .group-avatar {
            background: linear-gradient(135deg, #007bff, #0056b3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .toggle-section {
            margin-bottom: 20px;
        }
        
        .notification-badge {
            position: relative;
        }
        
        .notification-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        
        .friend-checkbox {
            margin-right: 10px;
        }
        
        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .friend-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .friend-item:last-child {
            border-bottom: none;
        }
        
        .stats {
            font-size: 0.85rem;
            color: #6c757d;
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

    <div class="container mt-4">
        <!-- Botões de Toggle -->
        <div class="row text-center toggle-section">
            <div class="col-md-6">
                <button type="button" class="btn btn-primary w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#friendsSection" aria-expanded="false" aria-controls="friendsSection">
                    <i class="bi bi-person-heart"></i> Meus Amigos
                    <?php if (count($friends) > 0): ?>
                        <span class="badge"><?php echo count($friends); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-6">
                <button type="button" class="btn btn-success w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#groupsSection" aria-expanded="false" aria-controls="groupsSection">
                    <i class="bi bi-people"></i> Meus Grupos
                    <?php if (count($groups) > 0): ?>
                        <span class="badge"><?php echo count($groups); ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Seção de Amigos -->
        <div class="collapse" id="friendsSection">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-heart"></i> Meus Amigos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($friends)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-person-x" style="font-size: 2rem;"></i>
                            <p class="mt-2">Você ainda não tem amigos adicionados.</p>
                            <a href="../friends/" class="btn btn-outline-primary">Adicionar Amigos</a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($friends as $friend): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card chat-card h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <?php 
                                            $friend_avatar_url = getProfileImageUrl($friend['image']);
                                            if ($friend_avatar_url): 
                                            ?>
                                                <img src="<?php echo htmlspecialchars($friend_avatar_url); ?>" alt="Avatar" class="friend-avatar me-3" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="friend-avatar me-3 bg-secondary d-flex align-items-center justify-content-center" style="display: none;">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="friend-avatar me-3 bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($friend['name']); ?></h6>
                                                <p class="card-text text-muted mb-2">@<?php echo htmlspecialchars($friend['username']); ?></p>
                                                <a href="../message/?friend_id=<?php echo $friend['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-chat-dots"></i> Abrir Chat
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Seção de Grupos -->
        <div class="collapse" id="groupsSection">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Meus Grupos</h5>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                        <i class="bi bi-plus-circle"></i> Novo Grupo
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($groups)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                            <p class="mt-2">Você ainda não participa de nenhum grupo.</p>
                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                                Criar Primeiro Grupo
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($groups as $group): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card chat-card h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <?php 
                                            $group_image_url = getGroupImageUrl($group['image']);
                                            if ($group_image_url): 
                                            ?>
                                                <img src="<?php echo htmlspecialchars($group_image_url); ?>" alt="Grupo" class="group-avatar me-3" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="group-avatar me-3" style="display: none;">
                                                    <?php echo strtoupper(substr($group['name'], 0, 2)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="group-avatar me-3">
                                                    <?php echo strtoupper(substr($group['name'], 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($group['name']); ?></h6>
                                                <p class="card-text stats mb-2">
                                                    <i class="bi bi-people"></i> <?php echo $group['member_count']; ?> membros •
                                                    <i class="bi bi-chat"></i> <?php echo $group['message_count']; ?> mensagens
                                                </p>
                                                <div class="d-flex gap-2">
                                                    <a href="../groupmessage/?group_id=<?php echo $group['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-chat-dots"></i> Entrar
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Criar Grupo -->
    <div class="modal fade" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createGroupModalLabel">
                        <i class="bi bi-people"></i> Criar Novo Grupo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="group_name" class="form-label">Nome do Grupo *</label>
                            <input type="text" class="form-control" id="group_name" name="group_name" placeholder="Digite o nome do grupo" maxlength="100" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adicionar Amigos ao Grupo</label>
                            <?php if (empty($friends)): ?>
                                <div class="text-muted">
                                    <i class="bi bi-info-circle"></i> Você precisa ter amigos para adicioná-los ao grupo.
                                    <a href="../friends/" class="btn btn-link btn-sm p-0">Adicionar amigos</a>
                                </div>
                            <?php else: ?>
                                <div class="form-text mb-2">Selecione os amigos que deseja adicionar (opcional):</div>
                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($friends as $friend): ?>
                                        <div class="friend-item d-flex align-items-center">
                                            <input type="checkbox" class="form-check-input friend-checkbox" 
                                                   id="friend_<?php echo $friend['id']; ?>" 
                                                   name="selected_friends[]" 
                                                   value="<?php echo $friend['id']; ?>">
                                            <label class="form-check-label d-flex align-items-center w-100" for="friend_<?php echo $friend['id']; ?>">
                                                <?php 
                                                $modal_friend_avatar_url = getProfileImageUrl($friend['image']);
                                                if ($modal_friend_avatar_url): 
                                                ?>
                                                    <img src="<?php echo htmlspecialchars($modal_friend_avatar_url); ?>" alt="Avatar" class="friend-avatar me-2" style="width: 30px; height: 30px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="friend-avatar me-2 bg-secondary d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 0.8rem; display: none;">
                                                        <i class="bi bi-person text-white"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="friend-avatar me-2 bg-secondary d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                        <i class="bi bi-person text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($friend['name']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($friend['username']); ?></small>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="bi bi-lightbulb"></i> Você pode adicionar mais membros depois de criar o grupo.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="create_group" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Criar Grupo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Expandir automaticamente as seções se houver conteúdo
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($friends)): ?>
                new bootstrap.Collapse(document.getElementById('friendsSection'), {show: true});
            <?php endif; ?>
            
            <?php if (!empty($groups)): ?>
                new bootstrap.Collapse(document.getElementById('groupsSection'), {show: true});
            <?php endif; ?>
        });
        
        // Feedback visual para seleção de amigos
        document.querySelectorAll('.friend-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.nextElementSibling;
                if (this.checked) {
                    label.classList.add('bg-light');
                } else {
                    label.classList.remove('bg-light');
                }
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>