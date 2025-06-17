<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Location/index.php';
require_once __DIR__ . '/../../components/FriendRequest/index.php';
require_once __DIR__ . '/../../components/RecentMessages/index.php';
require_once __DIR__ . '/../../components/Calendar/index.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null; // Adicionar esta variável

// Buscar dados completos do usuário (incluindo username, name, image)
if ($user_id) {
    $query = "SELECT username, name, image, admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $user = $row; // Armazenar todos os dados do usuário
        $is_admin = $row['admin'] == 1;
    }
    $stmt->close();
}

// Função para exibir imagem do perfil (adicionar esta função)
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

// Inicializar componentes
$location = new Location($mysqli, $user_id);
$friendRequest = new FriendRequest($mysqli, $user_id);
$recentMessages = new RecentMessages($mysqli, $user_id);
$calendar = new Calendar($mysqli, $user_id);

// Processar requisições POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_location':
                $location->handleLocationUpdate();
                break;
        }
    }
    
    if (isset($_POST['accept_friend'])) {
        $friendRequest->acceptRequest($_POST['friendship_id']);
    }
    
    if (isset($_POST['reject_friend'])) {
        $friendRequest->rejectRequest($_POST['friendship_id']);
    }
}

// Obter dados dos componentes
$current_location = $location->getCurrentLocation();
$pending_requests = $friendRequest->getPendingRequests();
$recent_messages = $recentMessages->getRecentMessages();
$scheduled_sessions = $calendar->getUpcomingSessions();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CritMeet - Página Inicial</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        /* Estilos da navbar */
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
        
        /* Estilos existentes da homepage */
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
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Navbar corrigida -->
    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="../homepage/">Home</a></li>
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
        <!-- Botões de Toggle -->
        <div class="row text-center toggle-section">
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#friendRequests" aria-expanded="false" aria-controls="friendRequests">
                    <i class="bi bi-person-plus"></i> Solicitações
                    <?php if (count($pending_requests) > 0): ?>
                        <span class="badge"><?php echo count($pending_requests); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-info w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#recentMessages" aria-expanded="false" aria-controls="recentMessages">
                    <i class="bi bi-chat-dots"></i> Mensagens
                    <?php if (count($recent_messages) > 0): ?>
                        <span class="badge"><?php echo count($recent_messages); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-3">
            <button type="button" class="btn btn-success w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#scheduledSessions" aria-expanded="false" aria-controls="scheduledSessions">
                    <i class="bi bi-calendar-event"></i> Sessões
                    <?php if (count($scheduled_sessions) > 0): ?>
                        <span class="badge"><?php echo count($scheduled_sessions); ?></span>
                    <?php endif; ?>                
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-warning w-100" data-bs-toggle="collapse" data-bs-target="#mapSection" aria-expanded="false" aria-controls="mapSection">
                    <i class="bi bi-geo-alt"></i> Localização
                </button>
            </div>
        </div>

        <!-- Componentes -->
        <?php $friendRequest->render($pending_requests); ?>
        <?php $recentMessages->render($recent_messages); ?>
        <?php $calendar->renderCalendarSection(); ?>
        <?php $location->render($current_location); ?>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="components/js/homepage.js"></script>
       
    <!-- Calendar Script -->
    <script>
        let calendar;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize calendar when the scheduled sessions section is shown
            document.getElementById('scheduledSessions').addEventListener('shown.bs.collapse', function () {
                if (!calendar) {
                    <?php echo $calendar->getCalendarScript(); ?>
                    initializeCalendar();
                }
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>