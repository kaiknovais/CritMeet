<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Location/index.php';
require_once __DIR__ . '/../../components/Tags/index.php';
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

// Inicializar componentes
$location = new Location($mysqli, $user_id);
$current_location = $location->getCurrentLocation();

// Processar atualização de localização
if (isset($_POST['action']) && $_POST['action'] === 'update_location') {
    $location->handleLocationUpdate();
}

// Obter perfil do usuário atual
$user_query = "SELECT username, name, preferences FROM users WHERE id = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();
$current_user_tags = RPGTags::parseUserTags($current_user['preferences'] ?? '');

// Função para calcular distância
function calculateDistance($current_location, $other_lat, $other_lng) {
    if (!$current_location || !$other_lat || !$other_lng) return null;
    
    $lat1 = deg2rad($current_location['latitude']);
    $lon1 = deg2rad($current_location['longitude']);
    $lat2 = deg2rad($other_lat);
    $lon2 = deg2rad($other_lng);
    
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return 6371 * $c; // Distância em km
}

// Função para calcular compatibilidade de tags
function calculateTagCompatibility($user_tags, $other_tags) {
    if (empty($user_tags) || empty($other_tags)) return 0;
    
    $intersection = array_intersect($user_tags, $other_tags);
    $union = array_unique(array_merge($user_tags, $other_tags));
    
    return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
}

// Buscar jogadores próximos
$nearby_players = [];
if ($current_location) {
    $nearby_query = "SELECT u.id, u.username, u.name, u.preferences,
                            ul.latitude, ul.longitude, ul.city, ul.state, ul.address,
                            (6371 * acos(
                                cos(radians(?)) * cos(radians(ul.latitude)) * 
                                cos(radians(ul.longitude) - radians(?)) + 
                                sin(radians(?)) * sin(radians(ul.latitude))
                            )) AS distance
                     FROM users u 
                     JOIN user_locations ul ON u.id = ul.user_id 
                     WHERE u.id != ?
                     HAVING distance <= 50
                     ORDER BY distance ASC
                     LIMIT 6";
    
    $nearby_stmt = $mysqli->prepare($nearby_query);
    $nearby_stmt->bind_param("dddi", 
        $current_location['latitude'], 
        $current_location['longitude'], 
        $current_location['latitude'], 
        $user_id
    );
    $nearby_stmt->execute();
    $nearby_players = $nearby_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Buscar jogadores com gostos similares
$similar_players = [];
if (!empty($current_user_tags)) {
    $similar_query = "SELECT u.id, u.username, u.name, u.preferences,
                             ul.latitude, ul.longitude, ul.city, ul.state
                      FROM users u 
                      LEFT JOIN user_locations ul ON u.id = ul.user_id 
                      WHERE u.id != ? AND u.preferences IS NOT NULL AND u.preferences != ''
                      ORDER BY u.name ASC
                      LIMIT 20";
    
    $similar_stmt = $mysqli->prepare($similar_query);
    $similar_stmt->bind_param("i", $user_id);
    $similar_stmt->execute();
    $all_players = $similar_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calcular compatibilidade e ordenar
    foreach ($all_players as $player) {
        $player_tags = RPGTags::parseUserTags($player['preferences']);
        $compatibility = calculateTagCompatibility($current_user_tags, $player_tags);
        $player['compatibility'] = $compatibility;
        
        if ($compatibility > 30) { // Apenas jogadores com compatibilidade > 30%
            $similar_players[] = $player;
        }
    }
    
    // Ordenar por compatibilidade
    usort($similar_players, function($a, $b) {
        return $b['compatibility'] - $a['compatibility'];
    });
    
    $similar_players = array_slice($similar_players, 0, 6);
}

// Buscar jogadores recentes
$recent_query = "SELECT u.id, u.username, u.name, u.preferences,
                        ul.latitude, ul.longitude, ul.city, ul.state
                 FROM users u 
                 LEFT JOIN user_locations ul ON u.id = ul.user_id 
                 WHERE u.id != ?
                 ORDER BY u.id DESC
                 LIMIT 6";

$recent_stmt = $mysqli->prepare($recent_query);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_players = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encontrar Jogadores - CritMeet</title>
    <link rel="stylesheet" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        .player-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .player-card:hover {
            transform: translateY(-5px);
        }
        
        .compatibility-badge {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .distance-badge {
            background: rgba(255, 193, 7, 0.8);
            color: #000;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .section-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .no-results {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #6c757d;
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
        
        .toggle-section {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
        <!-- Botões de Toggle -->
        <div class="row text-center toggle-section">
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#nearbyPlayers" aria-expanded="false" aria-controls="nearbyPlayers">
                    <i class="bi bi-geo-alt"></i> Jogadores Próximos
                    <?php if (count($nearby_players) > 0): ?>
                        <span class="badge"><?php echo count($nearby_players); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#similarPlayers" aria-expanded="false" aria-controls="similarPlayers">
                    <i class="bi bi-heart"></i> Gostos Similares
                    <?php if (count($similar_players) > 0): ?>
                        <span class="badge"><?php echo count($similar_players); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-info w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#recentPlayers" aria-expanded="false" aria-controls="recentPlayers">
                    <i class="bi bi-clock"></i> Jogadores Recentes
                    <?php if (count($recent_players) > 0): ?>
                        <span class="badge"><?php echo count($recent_players); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-warning w-100" data-bs-toggle="collapse" data-bs-target="#locationSection" aria-expanded="false" aria-controls="locationSection">
                    <i class="bi bi-gear"></i> Configurar Local
                </button>
            </div>
        </div>

        <!-- Seção de Localização -->
        <div class="collapse" id="locationSection">
            <div class="card card-body mb-4">
                <h5><i class="bi bi-geo-alt"></i> Configurar Localização</h5>
                <?php if ($current_location): ?>
                    <p class="text-success">
                        <i class="bi bi-check-circle"></i> 
                        Localização atual: <?= htmlspecialchars($current_location['city']) ?>, <?= htmlspecialchars($current_location['state']) ?>
                    </p>
                <?php else: ?>
                    <p class="text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Configure sua localização para encontrar jogadores próximos
                    </p>
                <?php endif; ?>
                <?php $location->render($current_location); ?>
            </div>
        </div>

        <!-- Jogadores Próximos -->
        <div class="collapse" id="nearbyPlayers">
            <div class="card card-body mb-4">
                <h4 class="section-header">
                    <i class="bi bi-geo-alt text-primary"></i> Jogadores Próximos
                </h4>
                
                <?php if (empty($nearby_players)): ?>
                    <div class="no-results">
                        <?php if (!$current_location): ?>
                            <i class="bi bi-geo-alt" style="font-size: 2rem;"></i>
                            <p>Configure sua localização para encontrar jogadores próximos!</p>
                        <?php else: ?>
                            <i class="bi bi-search" style="font-size: 2rem;"></i>
                            <p>Nenhum jogador encontrado em um raio de 50km da sua localização.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($nearby_players as $player): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="player-card">
                                    <div class="distance-badge">
                                        <i class="bi bi-compass"></i> <?= round($player['distance'], 1) ?>km
                                    </div>
                                    
                                    <h6><?= htmlspecialchars($player['name']) ?></h6>
                                    <small class="text-white-50">@<?= htmlspecialchars($player['username']) ?></small>
                                    
                                    <?php if ($player['city']): ?>
                                        <p class="mt-2 mb-2">
                                            <small><i class="bi bi-pin-map"></i> <?= htmlspecialchars($player['city']) ?>, <?= htmlspecialchars($player['state']) ?></small>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($player['preferences']): ?>
                                        <p class="small">
                                            <?= htmlspecialchars(substr($player['preferences'], 0, 80)) ?>...
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="../../components/ViewProfile/?id=<?= $player['id'] ?>" class="btn btn-light btn-sm">
                                            <i class="bi bi-person"></i> Perfil
                                        </a>
                                        <a href="../chat/?user=<?= $player['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="bi bi-chat-dots"></i> Chat
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jogadores com Gostos Similares -->
        <div class="collapse" id="similarPlayers">
            <div class="card card-body mb-4">
                <h4 class="section-header">
                    <i class="bi bi-heart text-success"></i> Jogadores com Gostos Similares
                </h4>
                
                <?php if (empty($similar_players)): ?>
                    <div class="no-results">
                        <?php if (empty($current_user_tags)): ?>
                            <i class="bi bi-tags" style="font-size: 2rem;"></i>
                            <p>Complete suas preferências no perfil para encontrar jogadores com gostos similares!</p>
                            <a href="../Profile/" class="btn btn-primary">Editar Perfil</a>
                        <?php else: ?>
                            <i class="bi bi-search" style="font-size: 2rem;"></i>
                            <p>Nenhum jogador com gostos similares encontrado no momento.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($similar_players as $player): ?>
                            <?php
                            $player_tags = RPGTags::parseUserTags($player['preferences']);
                            $distance = calculateDistance($current_location, $player['latitude'], $player['longitude']);
                            ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="player-card">
                                    <div class="compatibility-badge">
                                        <i class="bi bi-heart-fill"></i> <?= round($player['compatibility']) ?>% Match
                                    </div>
                                    
                                    <?php if ($distance): ?>
                                        <div class="distance-badge">
                                            <i class="bi bi-compass"></i> <?= round($distance, 1) ?>km
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h6><?= htmlspecialchars($player['name']) ?></h6>
                                    <small class="text-white-50">@<?= htmlspecialchars($player['username']) ?></small>
                                    
                                    <?php if ($player['city']): ?>
                                        <p class="mt-2 mb-2">
                                            <small><i class="bi bi-pin-map"></i> <?= htmlspecialchars($player['city']) ?>, <?= htmlspecialchars($player['state']) ?></small>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Tags em comum -->
                                    <?php 
                                    $common_tags = array_intersect($current_user_tags, $player_tags);
                                    if (!empty($common_tags)): 
                                    ?>
                                        <div class="mt-2">
                                            <small><strong>Em comum:</strong></small><br>
                                            <?php foreach (array_slice($common_tags, 0, 3) as $tag): ?>
                                                <span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($tag) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="../../components/ViewProfile/?id=<?= $player['id'] ?>" class="btn btn-light btn-sm">
                                            <i class="bi bi-person"></i> Perfil
                                        </a>
                                        <a href="../chat/?user=<?= $player['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="bi bi-chat-dots"></i> Chat
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jogadores Recentes -->
        <div class="collapse" id="recentPlayers">
            <div class="card card-body mb-4">
                <h4 class="section-header">
                    <i class="bi bi-clock text-info"></i> Jogadores Recentes
                </h4>
                
                <div class="row">
                    <?php foreach ($recent_players as $player): ?>
                        <?php
                        $distance = calculateDistance($current_location, $player['latitude'], $player['longitude']);
                        ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="player-card">
                                <?php if ($distance): ?>
                                    <div class="distance-badge">
                                        <i class="bi bi-compass"></i> <?= round($distance, 1) ?>km
                                    </div>
                                <?php endif; ?>
                                
                                <h6><?= htmlspecialchars($player['name']) ?></h6>
                                <small class="text-white-50">@<?= htmlspecialchars($player['username']) ?></small>
                                
                                <?php if ($player['city']): ?>
                                    <p class="mt-2 mb-2">
                                        <small><i class="bi bi-pin-map"></i> <?= htmlspecialchars($player['city']) ?>, <?= htmlspecialchars($player['state']) ?></small>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($player['preferences']): ?>
                                    <p class="small">
                                        <?= htmlspecialchars(substr($player['preferences'], 0, 80)) ?>...
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-2 mt-3">
                                    <a href="../../components/ViewProfile/?id=<?= $player['id'] ?>" class="btn btn-light btn-sm">
                                        <i class="bi bi-person"></i> Perfil
                                    </a>
                                    <a href="../chat/?user=<?= $player['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="bi bi-chat-dots"></i> Chat
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>