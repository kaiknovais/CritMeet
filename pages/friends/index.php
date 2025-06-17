<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/ViewAvatar/index.php';

// Verificar se a sessão já está ativa antes de iniciar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está autenticado
$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null;

if (!$user_id) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
    exit();
}

// Resto do código permanece igual...
// Verificar se o usuário é administrador e buscar dados do usuário
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

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Pesquisa de usuários ---
$search_query = '';
$search_result = [];
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $sql = "SELECT id, username, name, image FROM users WHERE (username LIKE ? OR name LIKE ?) AND id != ?";
    $stmt = $mysqli->prepare($sql);
    $search_term = "%$search_query%";
    $stmt->bind_param("ssi", $search_term, $search_term, $user_id);
    $stmt->execute();
    $search_result = $stmt->get_result();
}

// --- Adicionar amigo ---
if (isset($_POST['add_friend'])) {
    $friend_id = $_POST['friend_id'];

    // Verifica se já existe relação de amizade ou pedido pendente
    $sql_check = "SELECT id FROM friends WHERE (user_id = ? AND friend_id = ? OR user_id = ? AND friend_id = ?) AND status IN ('accepted', 'pending')";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows == 0) {
        $sql_add = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
        $stmt_add = $mysqli->prepare($sql_add);
        $stmt_add->bind_param("ii", $user_id, $friend_id);
        $stmt_add->execute();
        $message = "Pedido de amizade enviado com sucesso!";
        $alert_type = "success";
    } else {
        $message = "Você já é amigo desta pessoa ou há um pedido pendente.";
        $alert_type = "warning";
    }
}

// --- Aceitar amizade ---
if (isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];

    $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ? AND status = 'pending'";
    $stmt_accept = $mysqli->prepare($sql_accept);
    $stmt_accept->bind_param("ii", $friendship_id, $user_id);
    if ($stmt_accept->execute() && $stmt_accept->affected_rows > 0) {
        $message = "Solicitação de amizade aceita!";
        $alert_type = "success";
    } else {
        $message = "Erro ao aceitar a solicitação.";
        $alert_type = "danger";
    }
}

// --- Rejeitar amizade ---
if (isset($_POST['reject_friend'])) {
    $friendship_id = $_POST['friendship_id'];

    $sql_reject = "DELETE FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'";
    $stmt_reject = $mysqli->prepare($sql_reject);
    $stmt_reject->bind_param("ii", $friendship_id, $user_id);
    if ($stmt_reject->execute() && $stmt_reject->affected_rows > 0) {
        $message = "Solicitação de amizade rejeitada.";
        $alert_type = "info";
    } else {
        $message = "Erro ao rejeitar a solicitação.";
        $alert_type = "danger";
    }
}

// --- Remover amigo ---
if (isset($_POST['remove_friend'])) {
    $friend_id = $_POST['friend_id'];

    $sql_remove = "DELETE FROM friends WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) AND status = 'accepted'";
    $stmt_remove = $mysqli->prepare($sql_remove);
    $stmt_remove->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    if ($stmt_remove->execute() && $stmt_remove->affected_rows > 0) {
        $message = "Amigo removido com sucesso.";
        $alert_type = "info";
    } else {
        $message = "Erro ao remover amigo.";
        $alert_type = "danger";
    }
}

// Consultar amigos aceitos
$sql_friends = "SELECT u.id, u.username, u.name, u.image
                FROM friends f 
                JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id) 
                WHERE (f.user_id = ? OR f.friend_id = ?) 
                AND f.status = 'accepted' 
                AND u.id != ?
                ORDER BY u.name, u.username";
$stmt = $mysqli->prepare($sql_friends);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result_friends = $stmt->get_result();

// Consultar solicitações pendentes recebidas
$sql_pending = "SELECT f.id, u.id as user_id, u.username, u.name, u.image
                FROM friends f 
                JOIN users u ON f.user_id = u.id 
                WHERE f.friend_id = ? AND f.status = 'pending'
                ORDER BY f.created_at DESC";
$stmt = $mysqli->prepare($sql_pending);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_pending = $stmt->get_result();

// Consultar solicitações pendentes enviadas
$sql_sent = "SELECT f.id, u.id as user_id, u.username, u.name, u.image
             FROM friends f 
             JOIN users u ON f.friend_id = u.id 
             WHERE f.user_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC";
$stmt = $mysqli->prepare($sql_sent);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_sent = $stmt->get_result();

// Verificar se há relação de amizade para cada resultado da pesquisa
function getFriendshipStatus($mysqli, $user_id, $friend_id) {
    $sql = "SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['status'];
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexões - CritMeet</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
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
        
        /* Compatibilidade com o estilo do CritMeet Homepage */
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
        
        .toggle-section {
            margin-bottom: 20px;
        }
        
        /* Estilo dos componentes principais */
        .component-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .component-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .component-header i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .component-header h4 {
            margin: 0;
            color: #333;
        }
        
        /* Estilo dos cards de usuário */
        .user-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color 0.2s ease;
        }
        
        .user-item:hover {
            background: #e9ecef;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 2px;
        }
        
        .user-username {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .user-status {
            color: #6c757d;
            font-size: 0.8rem;
            font-style: italic;
        }
        
        /* Botões de ação */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            font-size: 0.9rem;
            padding: 6px 12px;
        }
        
        /* Seção de busca */
        .search-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .search-form {
            position: relative;
        }
        
        /* Estados vazios */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .empty-state h5 {
            margin-bottom: 10px;
        }
        
        /* Badges de status */
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 15px;
        }
        
        /* Tabs personalizadas */
        .nav-pills .nav-link {
            color: #6c757d;
            border-radius: 25px;
            padding: 8px 20px;
            margin-right: 8px;
            margin-bottom: 8px;
            background: white;
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
        }
        
        .nav-pills .nav-link:hover {
            background: #f8f9fa;
            color: #495057;
        }
        
        .nav-pills .nav-link.active {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .nav-pills .nav-link .badge {
            margin-left: 5px;
        }
        
        /* Responsividade melhorada */
        @media (max-width: 768px) {
            .toggle-section .col-md-3 {
                margin-bottom: 10px;
            }
            
            .user-item {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .user-info {
                justify-content: flex-start;
            }
            
            .action-buttons {
                justify-content: center;
                width: 100%;
            }
            
            .action-buttons .btn {
                flex: 1;
                min-width: 120px;
            }
            
            .nav-pills {
                flex-direction: column;
            }
            
            .nav-pills .nav-link {
                margin-right: 0;
                text-align: center;
            }
        }
        
        /* Alertas customizados */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <!-- Navbar corrigida -->
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
                    <li class="nav-item"><a class="nav-link active" href="../friends">Conexões</a></li>
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
                            <li><a class="dropdown-item" href="../settings/">
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
        <!-- Alert Messages -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $alert_type === 'success' ? 'check-circle' : ($alert_type === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Seção de Busca -->
        <div class="search-section">
            <div class="component-header">
                <i class="bi bi-search text-primary"></i>
                <h4>Buscar Pessoas</h4>
            </div>
            <form method="GET" class="search-form">
                <div class="input-group">
                    <input type="text" name="search" class="form-control form-control-lg" 
                           placeholder="Buscar por nome ou nome de usuário..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <!-- Resultados da Pesquisa -->
            <?php if (!empty($search_query)): ?>
                <div class="mt-4">
                    <h6 class="mb-3 text-muted">Resultados para "<?php echo htmlspecialchars($search_query); ?>":</h6>
                    <?php if ($search_result && $search_result->num_rows > 0): ?>
                        <?php while ($search = $search_result->fetch_assoc()): ?>
                            <?php $friendship_status = getFriendshipStatus($mysqli, $user_id, $search['id']); ?>
                            <div class="user-item">
                                <div class="user-info">
                                    <?php renderViewAvatar($search['id'], 'medium'); ?>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($search['name'] ?: $search['username']); ?></div>
                                        <div class="user-username">@<?php echo htmlspecialchars($search['username']); ?></div>
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <?php if ($friendship_status === null): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="friend_id" value="<?php echo $search['id']; ?>">
                                            <button type="submit" name="add_friend" class="btn btn-primary btn-sm">
                                                <i class="bi bi-person-plus"></i> Adicionar
                                            </button>
                                        </form>
                                    <?php elseif ($friendship_status === 'pending'): ?>
                                        <span class="badge bg-warning status-badge">
                                            <i class="bi bi-clock"></i> Pendente
                                        </span>
                                    <?php elseif ($friendship_status === 'accepted'): ?>
                                        <span class="badge bg-success status-badge">
                                            <i class="bi bi-check-circle"></i> Amigos
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-search"></i>
                            <p>Nenhum usuário encontrado com esse termo.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Botões de Toggle (estilo homepage) -->
        <div class="row text-center toggle-section">
            <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#friendsSection" aria-expanded="true" aria-controls="friendsSection">
                    <i class="bi bi-people-fill"></i> Meus Amigos
                    <?php if ($result_friends->num_rows > 0): ?>
                        <span class="badge"><?php echo $result_friends->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-info w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#pendingSection" aria-expanded="false" aria-controls="pendingSection">
                    <i class="bi bi-person-plus"></i> Solicitações
                    <?php if ($result_pending->num_rows > 0): ?>
                        <span class="badge"><?php echo $result_pending->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-warning w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#sentSection" aria-expanded="false" aria-controls="sentSection">
                    <i class="bi bi-clock"></i> Enviadas
                    <?php if ($result_sent->num_rows > 0): ?>
                        <span class="badge"><?php echo $result_sent->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Seção de Amigos -->
        <div class="collapse show" id="friendsSection">
            <div class="component-section">
                <div class="component-header">
                    <i class="bi bi-people-fill text-primary"></i>
                    <h4>Meus Amigos</h4>
                </div>
                <?php if ($result_friends->num_rows > 0): ?>
                    <?php while ($friend = $result_friends->fetch_assoc()): ?>
                        <div class="user-item">
                            <div class="user-info">
                                <?php renderViewAvatar($friend['id'], 'medium'); ?>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($friend['name'] ?: $friend['username']); ?></div>
                                    <div class="user-username">@<?php echo htmlspecialchars($friend['username']); ?></div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="../chat/?friend_id=<?php echo $friend['id']; ?>" class="btn btn-success btn-sm">
                                    <i class="bi bi-chat"></i> Conversar
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja remover este amigo?')">
                                    <input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
                                    <button type="submit" name="remove_friend" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-person-dash"></i> Remover
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-people"></i>
                        <h5>Nenhum amigo ainda</h5>
                        <p>Use a busca acima para encontrar pessoas e adicionar como amigos!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção de Solicitações Pendentes -->
        <div class="collapse" id="pendingSection">
            <div class="component-section">
                <div class="component-header">
                    <i class="bi bi-person-plus text-info"></i>
                    <h4>Solicitações Recebidas</h4>
                </div>
                <?php if ($result_pending->num_rows > 0): ?>
                    <?php while ($pending = $result_pending->fetch_assoc()): ?>
                        <div class="user-item">
                            <div class="user-info">
                                <?php renderViewAvatar($pending['user_id'], 'medium'); ?>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($pending['name'] ?: $pending['username']); ?></div>
                                    <div class="user-username">@<?php echo htmlspecialchars($pending['username']); ?></div>
                                    <div class="user-status">Quer ser seu amigo</div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="friendship_id" value="<?php echo $pending['id']; ?>">
                                    <button type="submit" name="accept_friend" class="btn btn-success btn-sm">
                                        <i class="bi bi-check"></i> Aceitar
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="friendship_id" value="<?php echo $pending['id']; ?>">
                                    <button type="submit" name="reject_friend" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-x"></i> Rejeitar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-person-plus"></i>
                        <h5>Nenhuma solicitação pendente</h5>
                        <p>Você não possui solicitações de amizade no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção de Solicitações Enviadas -->
        <div class="collapse" id="sentSection">
            <div class="component-section">
                <div class="component-header">
                    <i class="bi bi-clock text-warning"></i>
                    <h4>Solicitações Enviadas</h4>
                </div>
                <?php if ($result_sent->num_rows > 0): ?>
                    <?php while ($sent = $result_sent->fetch_assoc()): ?>
                        <div class="user-item">
                            <div class="user-info">
                                <?php renderViewAvatar($sent['user_id'], 'medium'); ?>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($sent['name'] ?: $sent['username']); ?></div>
                                    <div class="user-username">@<?php echo htmlspecialchars($sent['username']); ?></div>
                                    <div class="user-status">Aguardando resposta...</div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <span class="badge bg-warning status-badge">
                                    <i class="bi bi-clock"></i> Pendente
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-clock"></i>
                        <h5>Nenhuma solicitação pendente</h5>
                        <p>Você não possui solicitações de amizade no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção de Solicitações Enviadas -->
        <div class="collapse" id="sentSection">
            <div class="component-section">
                <div class="component-header">
                    <i class="bi bi-clock text-warning"></i>
                    <h4>Solicitações Enviadas</h4>
                </div>
                <?php if ($result_sent->num_rows > 0): ?>
                    <?php while ($sent = $result_sent->fetch_assoc()): ?>
                        <div class="user-item">
                            <div class="user-info">
                                <?php renderViewAvatar($sent['user_id'], 'medium'); ?>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($sent['name'] ?: $sent['username']); ?></div>
                                    <div class="user-username">@<?php echo htmlspecialchars($sent['username']); ?></div>
                                    <div class="user-status">Aguardando resposta...</div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <span class="badge bg-warning status-badge">
                                    <i class="bi bi-clock"></i> Pendente
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-clock"></i>
                        <h5>Nenhuma solicitação enviada</h5>
                        <p>Você não enviou nenhuma solicitação de amizade.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include ViewAvatar Modal -->
    <?php includeViewAvatarModal(); ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-hide alerts -->
    <script>
        // Auto-hide success alerts after 5 seconds
        setTimeout(function() {
            const successAlerts = document.querySelectorAll('.alert-success');
            successAlerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>