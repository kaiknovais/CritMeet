<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/ViewAvatar/index.php';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .connections-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 15px;
        }
        
        .search-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .user-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.25rem;
        }
        
        .user-username {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #dee2e6;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .btn-group-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .search-form {
            position: relative;
        }
        
        .search-form .form-control {
            padding-right: 3rem;
        }
        
        .search-form .btn {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            border-radius: 0 0.375rem 0.375rem 0;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .tab-content {
            margin-top: 2rem;
        }
        
        .nav-pills .nav-link {
            color: #6c757d;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            margin-right: 0.5rem;
        }
        
        .nav-pills .nav-link.active {
            background-color: #007bff;
        }
        
        @media (max-width: 768px) {
            .connections-container {
                margin: 1rem auto;
                padding: 0 10px;
            }
            
            .search-card {
                padding: 1.5rem;
            }
            
            .user-card {
                padding: 1rem;
            }
            
            .btn-group-actions {
                flex-direction: column;
            }
            
            .btn-group-actions .btn {
                width: 100%;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/">
                <i class="bi bi-dice-6"></i> CritMeet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="../homepage/"><i class="bi bi-house"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/"><i class="bi bi-person"></i> Meu Perfil</a></li>
                    <li class="nav-item"><a class="nav-link" href="../rpg_info"><i class="bi bi-dice-5"></i> RPG</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i> Mais...
                            <?php
                            $pending_count = $result_pending->num_rows;
                            if ($pending_count > 0) {
                                echo '<span class="notification-badge">' . $pending_count . '</span>';
                            }
                            ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../settings/"><i class="bi bi-gear"></i> Configurações</a></li>
                            <li><a class="dropdown-item active" href="../friends/"><i class="bi bi-people"></i> Conexões</a></li>
                            <li><a class="dropdown-item" href="../chat/"><i class="bi bi-chat"></i> Chat</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/"><i class="bi bi-shield-check"></i> Administração</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="connections-container">
        <!-- Alert Messages -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $alert_type === 'success' ? 'check-circle' : ($alert_type === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-card">
            <h2 class="section-title">
                <i class="bi bi-search"></i> Buscar Pessoas
            </h2>
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

            <!-- Search Results -->
            <?php if (!empty($search_query)): ?>
                <div class="mt-4">
                    <h5 class="mb-3">Resultados da pesquisa:</h5>
                    <?php if ($search_result && $search_result->num_rows > 0): ?>
                        <?php while ($search = $search_result->fetch_assoc()): ?>
                            <?php $friendship_status = getFriendshipStatus($mysqli, $user_id, $search['id']); ?>
                            <div class="user-card">
                                <div class="user-info">
                                    <?php renderViewAvatar($search['id'], 'medium'); ?>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($search['name'] ?: $search['username']); ?></div>
                                        <div class="user-username">@<?php echo htmlspecialchars($search['username']); ?></div>
                                    </div>
                                    <div class="btn-group-actions">
                                        <?php if ($friendship_status === null): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="friend_id" value="<?php echo $search['id']; ?>">
                                                <button type="submit" name="add_friend" class="btn btn-primary">
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

        <!-- Tabs Navigation -->
        <ul class="nav nav-pills justify-content-center mb-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#friends-tab">
                    <i class="bi bi-people-fill"></i> Meus Amigos
                    <span class="badge bg-primary ms-2"><?php echo $result_friends->num_rows; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pending-tab">
                    <i class="bi bi-person-plus"></i> Solicitações
                    <?php if ($result_pending->num_rows > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $result_pending->num_rows; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#sent-tab">
                    <i class="bi bi-clock"></i> Enviadas
                    <?php if ($result_sent->num_rows > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $result_sent->num_rows; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Friends Tab -->
            <div class="tab-pane fade show active" id="friends-tab">
                <div class="search-card">
                    <h3 class="section-title">
                        <i class="bi bi-people-fill"></i> Meus Amigos
                    </h3>
                    <?php if ($result_friends->num_rows > 0): ?>
                        <?php while ($friend = $result_friends->fetch_assoc()): ?>
                            <div class="user-card">
                                <div class="user-info">
                                    <?php renderViewAvatar($friend['id'], 'medium'); ?>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($friend['name'] ?: $friend['username']); ?></div>
                                        <div class="user-username">@<?php echo htmlspecialchars($friend['username']); ?></div>
                                    </div>
                                    <div class="btn-group-actions">
                                        <a href="../chat/?friend_id=<?php echo $friend['id']; ?>" class="btn btn-success">
                                            <i class="bi bi-chat"></i> Conversar
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja remover este amigo?')">
                                            <input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
                                            <button type="submit" name="remove_friend" class="btn btn-outline-danger">
                                                <i class="bi bi-person-dash"></i> Remover
                                            </button>
                                        </form>
                                    </div>
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

            <!-- Pending Requests Tab -->
            <div class="tab-pane fade" id="pending-tab">
                <div class="search-card">
                    <h3 class="section-title">
                        <i class="bi bi-person-plus"></i> Solicitações Recebidas
                    </h3>
                    <?php if ($result_pending->num_rows > 0): ?>
                        <?php while ($pending = $result_pending->fetch_assoc()): ?>
                            <div class="user-card">
                                <div class="user-info">
                                    <?php renderViewAvatar($pending['user_id'], 'medium'); ?>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($pending['name'] ?: $pending['username']); ?></div>
                                        <div class="user-username">@<?php echo htmlspecialchars($pending['username']); ?></div>
                                        <small class="text-muted">Quer ser seu amigo</small>
                                    </div>
                                    <div class="btn-group-actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="friendship_id" value="<?php echo $pending['id']; ?>">
                                            <button type="submit" name="accept_friend" class="btn btn-success">
                                                <i class="bi bi-check"></i> Aceitar
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="friendship_id" value="<?php echo $pending['id']; ?>">
                                            <button type="submit" name="reject_friend" class="btn btn-outline-danger">
                                                <i class="bi bi-x"></i> Rejeitar
                                            </button>
                                        </form>
                                    </div>
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

            <!-- Sent Requests Tab -->
            <div class="tab-pane fade" id="sent-tab">
                <div class="search-card">
                    <h3 class="section-title">
                        <i class="bi bi-clock"></i> Solicitações Enviadas
                    </h3>
                    <?php if ($result_sent->num_rows > 0): ?>
                        <?php while ($sent = $result_sent->fetch_assoc()): ?>
                            <div class="user-card">
                                <div class="user-info">
                                    <?php renderViewAvatar($sent['user_id'], 'medium'); ?>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($sent['name'] ?: $sent['username']); ?></div>
                                        <div class="user-username">@<?php echo htmlspecialchars($sent['username']); ?></div>
                                        <small class="text-muted">Aguardando resposta...</small>
                                    </div>
                                    <div class="btn-group-actions">
                                        <span class="badge bg-warning status-badge">
                                            <i class="bi bi-clock"></i> Pendente
                                        </span>
                                    </div>
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