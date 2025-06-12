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

// Processar atualização de localização
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_location') {
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $accuracy = floatval($_POST['accuracy'] ?? 0);
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $country = $_POST['country'] ?? 'Brasil';

        // Verificar se já existe localização para o usuário
        $check_sql = "SELECT id FROM user_locations WHERE user_id = ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Atualizar localização existente
            $update_sql = "UPDATE user_locations SET latitude = ?, longitude = ?, accuracy = ?, address = ?, city = ?, state = ?, country = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("dddssssi", $latitude, $longitude, $accuracy, $address, $city, $state, $country, $user_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Localização atualizada!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar localização']);
            }
        } else {
            // Inserir nova localização
            $insert_sql = "INSERT INTO user_locations (user_id, latitude, longitude, accuracy, address, city, state, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $mysqli->prepare($insert_sql);
            $insert_stmt->bind_param("idddssss", $user_id, $latitude, $longitude, $accuracy, $address, $city, $state, $country);
            
            if ($insert_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Localização salva!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar localização']);
            }
        }
        exit();
    }
}

// Buscar localização atual do usuário
$current_location = null;
$location_sql = "SELECT latitude, longitude, accuracy, address, city, state, country, updated_at FROM user_locations WHERE user_id = ?";
$location_stmt = $mysqli->prepare($location_sql);
$location_stmt->bind_param("i", $user_id);
$location_stmt->execute();
$location_result = $location_stmt->get_result();
if ($location_result && $location_row = $location_result->fetch_assoc()) {
    $current_location = $location_row;
}

// Buscar mensagens recentes (últimas 5)
$recent_messages = [];
if ($user_id) {
    $sql_messages = "SELECT m.content, m.timestamp, u.username, u.name, c.is_group, c.name as group_name,
                     CASE 
                         WHEN c.is_group = 1 THEN c.id
                         ELSE (SELECT user_id FROM chat_members cm WHERE cm.chat_id = c.id AND cm.user_id != ? LIMIT 1)
                     END as chat_identifier
                     FROM messages m
                     JOIN users u ON m.sender_id = u.id
                     JOIN chats c ON m.chat_id = c.id
                     JOIN chat_members cm ON c.id = cm.chat_id
                     WHERE cm.user_id = ? AND m.sender_id != ?
                     ORDER BY m.timestamp DESC
                     LIMIT 5";
    
    $stmt = $mysqli->prepare($sql_messages);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result_messages = $stmt->get_result();
    
    while ($row = $result_messages->fetch_assoc()) {
        $recent_messages[] = $row;
    }
    $stmt->close();
}

// Buscar solicitações de amizade pendentes
$pending_requests = [];
if ($user_id) {
    $sql_requests = "SELECT f.id, u.username, u.name 
                     FROM friends f 
                     JOIN users u ON f.user_id = u.id 
                     WHERE f.friend_id = ? AND f.status = 'pending'";
    
    $stmt = $mysqli->prepare($sql_requests);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_requests = $stmt->get_result();
    
    while ($row = $result_requests->fetch_assoc()) {
        $pending_requests[] = $row;
    }
    $stmt->close();
}

// Aceitar solicitação de amizade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    
    $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ? AND status = 'pending'";
    $stmt_accept = $mysqli->prepare($sql_accept);
    $stmt_accept->bind_param("ii", $friendship_id, $user_id);
    
    if ($stmt_accept->execute()) {
        echo "<script>alert('Solicitação de amizade aceita!'); window.location.href='';</script>";
    } else {
        echo "<script>alert('Erro ao aceitar a solicitação.');</script>";
    }
    $stmt_accept->close();
}

// Rejeitar solicitação de amizade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    
    $sql_reject = "DELETE FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'";
    $stmt_reject = $mysqli->prepare($sql_reject);
    $stmt_reject->bind_param("ii", $friendship_id, $user_id);
    
    if ($stmt_reject->execute()) {
        echo "<script>alert('Solicitação de amizade rejeitada.'); window.location.href='';</script>";
    } else {
        echo "<script>alert('Erro ao rejeitar a solicitação.');</script>";
    }
    $stmt_reject->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CritMeet - Página Inicial</title>
    <link rel="stylesheet" type="text/css" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
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
        
        .message-preview {
            border-left: 3px solid #007bff;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .message-preview .message-sender {
            font-weight: bold;
            color: #007bff;
        }
        
        .message-preview .message-content {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .message-preview .message-time {
            font-size: 0.8rem;
            color: #adb5bd;
            margin-top: 5px;
        }
        
        .friend-request {
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background-color: #fff;
        }
        
        .friend-request .request-actions {
            margin-top: 10px;
        }
        
        .friend-request .btn {
            margin-right: 5px;
        }
        
        .toggle-section {
            margin-bottom: 20px;
        }
        
        .calendar-container {
            min-height: 400px;
        }

        /* Estilos do mapa */
        #map {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .location-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .btn-location {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
            border: none;
            color: white;
            font-weight: bold;
        }
        
        .btn-location:hover {
            background: linear-gradient(45deg, #00f2fe, #4facfe);
            color: white;
        }

        .controls-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .location-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="../homepage/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/">Meu Perfil</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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

                <form class="d-flex" action="../friends" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Buscar amigos..." aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Buscar</button>
                </form>
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
                <button type="button" class="btn btn-success w-100" data-bs-toggle="collapse" data-bs-target="#scheduledSessions" aria-expanded="false" aria-controls="scheduledSessions">
                    <i class="bi bi-calendar-event"></i> Sessões
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-warning w-100" data-bs-toggle="collapse" data-bs-target="#mapSection" aria-expanded="false" aria-controls="mapSection">
                    <i class="bi bi-geo-alt"></i> Localização
                </button>
            </div>
        </div>

        <!-- Seção de Solicitações de Amizade -->
        <div class="collapse mt-3" id="friendRequests">
            <div class="card card-body">
                <h5><i class="bi bi-person-plus"></i> Solicitações de Amizade Pendentes</h5>
                <?php if (count($pending_requests) > 0): ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="friend-request">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($request['name']); ?></strong>
                                    <br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($request['username']); ?></small>
                                </div>
                                <div class="request-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="friendship_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="accept_friend" class="btn btn-success btn-sm">
                                            <i class="bi bi-check-lg"></i> Aceitar
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="friendship_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="reject_friend" class="btn btn-danger btn-sm">
                                            <i class="bi bi-x-lg"></i> Rejeitar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Nenhuma solicitação de amizade pendente.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção de Mensagens Recentes -->
        <div class="collapse mt-3" id="recentMessages">
            <div class="card card-body">
                <h5><i class="bi bi-chat-dots"></i> Mensagens Recentes</h5>
                <?php if (count($recent_messages) > 0): ?>
                    <?php foreach ($recent_messages as $message): ?>
                        <div class="message-preview">
                            <div class="message-sender">
                                <?php if ($message['is_group']): ?>
                                    <i class="bi bi-people"></i> <?php echo htmlspecialchars($message['group_name']); ?> - <?php echo htmlspecialchars($message['name']); ?>
                                <?php else: ?>
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($message['name']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="message-content">
                                <?php 
                                $content = htmlspecialchars($message['content']);
                                echo strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content;
                                ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('d/m/Y H:i', strtotime($message['timestamp'])); ?>
                            </div>
                            <div class="mt-2">
                                <?php if ($message['is_group']): ?>
                                    <a href="../groupmessage/?chat_id=<?php echo $message['chat_identifier']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-reply"></i> Responder no Grupo
                                    </a>
                                <?php else: ?>
                                    <a href="../message/?friend_id=<?php echo $message['chat_identifier']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-reply"></i> Responder
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="../chat/" class="btn btn-primary">
                            <i class="bi bi-chat-square-text"></i> Ver Todos os Chats
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Nenhuma mensagem recente encontrada.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção do Calendário -->
        <div class="collapse mt-3" id="scheduledSessions">
            <div class="card card-body">
                <h5><i class="bi bi-calendar-event"></i> Minhas Sessões Agendadas</h5>
                <div id="calendar" class="calendar-container"></div>
            </div>
        </div>

        <!-- Seção da Localização (Apenas do Usuário) -->
        <div class="collapse mt-3" id="mapSection">
            <div class="card card-body">
                <h5><i class="bi bi-geo-alt"></i> Minha Localização</h5>
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Informações de Localização -->
                        <div class="location-card">
                            <h6>📍 Sua Localização Atual</h6>
                            <div id="location-status">
                                <?php if ($current_location): ?>
                                    <p class="mb-1"><strong>Endereço:</strong> <?php echo htmlspecialchars($current_location['address'] ?: 'Não especificado'); ?></p>
                                    <p class="mb-1"><strong>Cidade:</strong> <?php echo htmlspecialchars($current_location['city'] ?: 'N/A'); ?>, <?php echo htmlspecialchars($current_location['state'] ?: 'N/A'); ?></p>
                                    <p class="mb-0"><small>Última atualização: <?php echo date('d/m/Y H:i', strtotime($current_location['updated_at'])); ?></small></p>
                                <?php else: ?>
                                    <p class="mb-0">Localização não definida. Clique em "Obter Localização" para definir sua posição.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Controles -->
                        <div class="controls-panel">
                            <div class="row align-items-center">
                                <div class="col-md-12 text-center">
                                    <button id="get-location" class="btn btn-location btn-lg">
                                        <i class="bi bi-geo-alt-fill"></i> Obter Minha Localização
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Mapa -->
                        <div id="map"></div>
                    </div>

                    <!-- Informações de Privacidade -->
                    <div class="col-md-4">
                        <div class="location-info">
                            <h6><i class="bi bi-shield-check"></i> Privacidade e Segurança</h6>
                            <p class="small text-muted">
                                <strong>🔐 Sua localização é privada:</strong><br>
                                • Apenas você pode ver sua posição no mapa<br>
                                • Sua localização não é compartilhada com outros usuários<br>
                                • Os dados são usados apenas para suas funcionalidades pessoais
                            </p>
                            
                            <h6 class="mt-4"><i class="bi bi-info-circle"></i> Como usar</h6>
                            <p class="small text-muted">
                                • Clique em "Obter Minha Localização" para definir sua posição<br>
                                • Use esta informação para organizar sessões presenciais<br>
                                • Encontre amigos através da busca por cidade/região
                            </p>

                            <?php if ($current_location): ?>
                                <div class="mt-4 p-3 bg-light rounded">
                                    <h6><i class="bi bi-bookmark-check"></i> Localização Salva</h6>
                                    <p class="small mb-1">
                                        <strong>Coordenadas:</strong><br>
                                        Lat: <?php echo number_format($current_location['latitude'], 6); ?><br>
                                        Lng: <?php echo number_format($current_location['longitude'], 6); ?>
                                    </p>
                                    <p class="small mb-0">
                                        <strong>Precisão:</strong> ±<?php echo intval($current_location['accuracy']); ?>m
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    
    <script>
        // Variáveis do mapa
        let map;
        let userMarker;
        let currentPosition = null;
        let mapInitialized = false;
        
        // Variáveis do calendário
        let calendar;
        let calendarInitialized = false;

        document.addEventListener('DOMContentLoaded', function () {
            // Inicializar mapa quando a seção for mostrada
            const mapCollapse = document.getElementById('mapSection');
            mapCollapse.addEventListener('shown.bs.collapse', function () {
                if (!mapInitialized) {
                    initMap();
                    mapInitialized = true;
                } else if (map) {
                    setTimeout(() => {
                        map.invalidateSize();
                    }, 100);
                }
            });

            // Inicializar calendário quando a seção for mostrada
            const calendarCollapse = document.getElementById('scheduledSessions');
            calendarCollapse.addEventListener('shown.bs.collapse', function () {
                if (!calendarInitialized) {
                    initializeCalendar();
                    calendarInitialized = true;
                } else if (calendar) {
                    setTimeout(() => {
                        calendar.render();
                        calendar.updateSize();
                    }, 100);
                }
            });
        });

        // Inicializar mapa
        function initMap() {
            map = L.map('map').setView([-23.5505, -46.6333], 10); // São Paulo como padrão
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            <?php if ($current_location): ?>
                // Se já tem localização, mostrar no mapa
                currentPosition = {
                    lat: <?php echo $current_location['latitude']; ?>,
                    lng: <?php echo $current_location['longitude']; ?>
                };
                map.setView([currentPosition.lat, currentPosition.lng], 15);
                
                userMarker = L.marker([currentPosition.lat, currentPosition.lng], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="background-color: #007bff; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; border: 3px solid white; box-shadow: 0 3px 6px rgba(0,0,0,0.3);">📍</div>`,
                        iconSize: [40, 40],
                        iconAnchor: [20, 20]
                    })
                })
                .addTo(map)
                .bindPopup(`
                    <div class="text-center">
                        <strong>📍 Sua Localização</strong><br>
                        <small><?php echo htmlspecialchars($current_location['city'] ?: 'N/A'); ?>, <?php echo htmlspecialchars($current_location['state'] ?: 'N/A'); ?></small><br>
                        <small class="text-muted">Atualizada em <?php echo date('d/m/Y H:i', strtotime($current_location['updated_at'])); ?></small>
                    </div>
                `)
                .openPopup();
            <?php endif; ?>
        }

        // Obter localização do usuário
        document.getElementById('get-location').addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('Geolocalização não é suportada pelo seu navegador');
                return;
            }
            
            this.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Obtendo localização...';
            this.disabled = true;
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    currentPosition = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };
                    
                    // Atualizar mapa
                    map.setView([currentPosition.lat, currentPosition.lng], 15);
                    
                    if (userMarker) {
                        map.removeLayer(userMarker);
                    }
                    
                    userMarker = L.marker([currentPosition.lat, currentPosition.lng], {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style="background-color: #007bff; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; border: 3px solid white; box-shadow: 0 3px 6px rgba(0,0,0,0.3);">📍</div>`,
                            iconSize: [40, 40],
                            iconAnchor: [20, 20]
                        })
                    })
                    .addTo(map)
                    .bindPopup(`
                        <div class="text-center">
                            <strong>📍 Sua Localização</strong><br>
                            <small>Localização atual obtida</small><br>
                            <small class="text-muted">Precisão: ±${Math.round(currentPosition.accuracy)}m</small>
                        </div>
                    `)
                    .openPopup();
                    
                    // Obter endereço via geocodificação reversa
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${currentPosition.lat}&lon=${currentPosition.lng}`)
                        .then(response => response.json())
                        .then(data => {
                            const address = data.display_name || '';
                            const city = data.address?.city || data.address?.town || data.address?.village || '';
                            const state = data.address?.state || '';
                            const country = data.address?.country || 'Brasil';
                            
                            // Salvar localização no servidor
                            const formData = new FormData();
                            formData.append('action', 'update_location');
                            formData.append('latitude', currentPosition.lat);
                            formData.append('longitude', currentPosition.lng);
                            formData.append('accuracy', currentPosition.accuracy);
                            formData.append('address', address);
                            formData.append('city', city);
                            formData.append('state', state);
                            formData.append('country', country);
                            
                            fetch('', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(result => {
                                if (result.success) {
                                    // Atualizar a interface
                                    document.getElementById('location-status').innerHTML = `
                                        <p class="mb-1"><strong>Endereço:</strong> ${address}</p>
                                        <p class="mb-1"><strong>Cidade:</strong> ${city}, ${state}</p>
                                        <p class="mb-0"><small>Última atualização: Agora mesmo</small></p>
                                    `;
                                    
                                    // Atualizar popup do marcador
                                    userMarker.setPopupContent(`
                                        <div class="text-center">
                                            <strong>📍 Sua Localização</strong><br>
                                            <small>${city}, ${state}</small><br>
                                            <small class="text-muted">Atualizada agora</small>
                                        </div>
                                    `);
                                    
                                    showNotification('✅ Localização atualizada com sucesso!', 'success');
                                } else {
                                    showNotification('❌ Erro ao salvar localização', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                showNotification('❌ Erro ao salvar localização', 'error');
                            });
                        })
                        .catch(error => {
                            console.error('Erro na geocodificação:', error);
                            showNotification('⚠️ Localização obtida, mas não foi possível obter o endereço', 'warning');
                        });
                    
                    // Resetar botão
                    document.getElementById('get-location').innerHTML = '<i class="bi bi-geo-alt-fill"></i> Obter Minha Localização';
                    document.getElementById('get-location').disabled = false;
                },
                function(error) {
                    let errorMessage = 'Erro ao obter localização';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Permissão negada para acessar a localização';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Localização indisponível';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Tempo limite excedido';
                            break;
                    }
                    
                    showNotification('❌ ' + errorMessage, 'error');
                    
                    // Resetar botão
                    document.getElementById('get-location').innerHTML = '<i class="bi bi-geo-alt-fill"></i> Obter Minha Localização';
                    document.getElementById('get-location').disabled = false;
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });

        // Inicializar calendário
        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia'
                },
                height: 'auto',
                events: [
                    // Eventos de exemplo - substitua pelos dados reais do banco
                    {
                        title: 'Sessão de D&D',
                        start: '2025-06-15T19:00:00',
                        end: '2025-06-15T23:00:00',
                        backgroundColor: '#007bff',
                        borderColor: '#007bff'
                    },
                    {
                        title: 'Campanha de Vampiro',
                        start: '2025-06-18T20:00:00',
                        end: '2025-06-18T24:00:00',
                        backgroundColor: '#dc3545',
                        borderColor: '#dc3545'
                    }
                ],
                eventClick: function(info) {
                    alert('Evento: ' + info.event.title + '\nData: ' + info.event.start.toLocaleDateString());
                },
                dateClick: function(info) {
                    if (confirm('Deseja criar uma nova sessão para ' + info.dateStr + '?')) {
                        // Redirecionar para página de criação de sessão
                        window.location.href = '../sessions/create.php?date=' + info.dateStr;
                    }
                }
            });
            
            calendar.render();
        }

        // Função para mostrar notificações
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'} alert-dismissible fade show`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            `;
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Remover automaticamente após 5 segundos
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Atualizar contadores quando há mudanças
        function updateNotificationBadges() {
            // Aqui você pode adicionar lógica para atualizar os badges em tempo real
            // Por exemplo, usando WebSockets ou polling
        }

        // Inicializar tooltips do Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Fazer as seções colapsáveis se comportarem como accordion (opcional)
        const collapseElements = document.querySelectorAll('.collapse');
        collapseElements.forEach(element => {
            element.addEventListener('show.bs.collapse', function () {
                // Opcional: fechar outras seções quando uma for aberta
                // collapseElements.forEach(otherElement => {
                //     if (otherElement !== element && otherElement.classList.contains('show')) {
                //         bootstrap.Collapse.getInstance(otherElement).hide();
                //     }
                // });
            });
        });

        // Atualizar badges periodicamente (polling simples)
        setInterval(function() {
            // Aqui você pode fazer uma requisição AJAX para verificar novos dados
            // e atualizar os badges de notificação
        }, 30000); // A cada 30 segundos
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>