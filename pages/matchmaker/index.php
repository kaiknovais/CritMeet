<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Location/index.php';
require_once __DIR__ . '/../../components/Tags/index.php';
require_once __DIR__ . '/../../components/FriendRequest/index.php';
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
$friendRequest = new FriendRequest($mysqli, $user_id);

// Processar atualização de localização
if (isset($_POST['action']) && $_POST['action'] === 'update_location') {
    $location->handleLocationUpdate();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- Adicionar amigo ---
if (isset($_POST['add_friend'])) {
    $friend_id = $_POST['friend_id'];
    $response = ['success' => false, 'message' => ''];

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
        if ($stmt_add->execute()) {
            $response = ['success' => true, 'message' => 'Pedido de amizade enviado!'];
        } else {
            $response = ['success' => false, 'message' => 'Erro ao enviar pedido de amizade.'];
        }
        $stmt_add->close();
    } else {
        $response = ['success' => false, 'message' => 'Você já é amigo ou há um pedido pendente.'];
    }
    $stmt_check->close();
    
    // Resposta AJAX
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

// Aceitar/Rejeitar solicitações
if (isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    $friendRequest->acceptRequest($friendship_id);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['reject_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    $friendRequest->rejectRequest($friendship_id);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Obter perfil do usuário atual
$user_query = "SELECT username, name, preferences FROM users WHERE id = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();
$current_user_tags = RPGTags::parseUserTags($current_user['preferences'] ?? '');

// Parâmetros de busca
$search_filters = [
    'distance' => min(50, max(5, intval($_GET['distance'] ?? 20))), // Limitado a 50km
    'city' => $_GET['city'] ?? '',
    'state' => $_GET['state'] ?? '',
    'tags' => $_GET['tags'] ?? '',
    'similar_preferences' => isset($_GET['similar_preferences']) ? 1 : 0
];

// Construir query de busca - buscar usuários que não são amigos e não têm pedidos pendentes
$search_results = [];
$base_query = "SELECT u.id, u.username, u.name, u.preferences, u.gender, u.pronouns, u.image,
                      ul.latitude, ul.longitude, ul.city, ul.state, ul.address";

$from_clause = " FROM users u 
                 LEFT JOIN user_locations ul ON u.id = ul.user_id 
                 WHERE u.id != ? 
                 AND u.id NOT IN (
                     SELECT CASE 
                         WHEN f.user_id = ? THEN f.friend_id 
                         ELSE f.user_id 
                     END 
                     FROM friends f 
                     WHERE (f.user_id = ? OR f.friend_id = ?) 
                     AND f.status IN ('accepted', 'pending')
                 )";

$params = [$user_id, $user_id, $user_id, $user_id];
$param_types = "iiii";

// Filtros de localização - apenas usuários com coordenadas
if ($current_location && $search_filters['distance'] > 0) {
    $from_clause .= " AND ul.latitude IS NOT NULL AND ul.longitude IS NOT NULL";
    $from_clause .= " AND (
        6371 * acos(
            cos(radians(?)) * cos(radians(ul.latitude)) * 
            cos(radians(ul.longitude) - radians(?)) + 
            sin(radians(?)) * sin(radians(ul.latitude))
        )
    ) <= ?";
    
    $params[] = $current_location['latitude'];
    $params[] = $current_location['longitude'];
    $params[] = $current_location['latitude'];
    $params[] = $search_filters['distance'];
    $param_types .= "dddd";
}

// Filtro por cidade/estado
if (!empty($search_filters['city'])) {
    $from_clause .= " AND ul.city LIKE ?";
    $params[] = "%" . $search_filters['city'] . "%";
    $param_types .= "s";
}

if (!empty($search_filters['state'])) {
    $from_clause .= " AND ul.state LIKE ?";
    $params[] = "%" . $search_filters['state'] . "%";
    $param_types .= "s";
}

// Filtro por tags específicas
if (!empty($search_filters['tags'])) {
    $search_tags = array_map('trim', explode(',', $search_filters['tags']));
    $tag_conditions = [];
    foreach ($search_tags as $tag) {
        $tag_conditions[] = "u.preferences LIKE ?";
        $params[] = "%" . $tag . "%";
        $param_types .= "s";
    }
    if (!empty($tag_conditions)) {
        $from_clause .= " AND (" . implode(" OR ", $tag_conditions) . ")";
    }
}

// Filtro por preferências similares
if ($search_filters['similar_preferences'] && !empty($current_user_tags)) {
    $similar_conditions = [];
    foreach ($current_user_tags as $tag) {
        $similar_conditions[] = "u.preferences LIKE ?";
        $params[] = "%" . $tag . "%";
        $param_types .= "s";
    }
    if (!empty($similar_conditions)) {
        $from_clause .= " AND (" . implode(" OR ", $similar_conditions) . ")";
    }
}

$order_clause = " ORDER BY u.name ASC LIMIT 20";

$full_query = $base_query . $from_clause . $order_clause;

$search_stmt = $mysqli->prepare($full_query);
if (!empty($params)) {
    $search_stmt->bind_param($param_types, ...$params);
}
$search_stmt->execute();
$search_results = $search_stmt->get_result();

// Obter solicitações pendentes
$pending_requests = $friendRequest->getPendingRequests();

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
    
    return 6371 * $c;
}

// Função para calcular compatibilidade de tags
function calculateTagCompatibility($user_tags, $other_tags) {
    if (empty($user_tags) || empty($other_tags)) return 0;
    
    $user_tags = array_map('strtolower', $user_tags);
    $other_tags = array_map('strtolower', RPGTags::parseUserTags($other_tags));
    
    $intersection = array_intersect($user_tags, $other_tags);
    $union = array_unique(array_merge($user_tags, $other_tags));
    
    return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Jogadores - CritMeet</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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
        
        .toggle-section {
            margin-bottom: 20px;
        }
        
        .player-card {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .player-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .compatibility-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .distance-info {
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .player-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
        }
        
        .player-image-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }
        
        .btn-add-friend {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-add-friend:hover {
            background: linear-gradient(45deg, #218838, #1e7e34);
            transform: scale(1.05);
        }
        
        .btn-add-friend:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert-floating {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
        
        .tag-preview {
            max-height: 60px;
            overflow: hidden;
            position: relative;
        }
        
        .tag-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(transparent, white);
        }
    </style>
</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Navbar -->
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
                            <li><a class="dropdown-item active" href="../matchmaker/">Buscar Jogadores</a></li>
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
        <h2><i class="bi bi-search"></i> Buscar Jogadores</h2>
        
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
                <button type="button" class="btn btn-warning w-100" data-bs-toggle="collapse" data-bs-target="#locationSection" aria-expanded="false" aria-controls="locationSection">
                    <i class="bi bi-geo-alt"></i> Localização
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-info w-100" data-bs-toggle="collapse" data-bs-target="#searchFilters" aria-expanded="false" aria-controls="searchFilters">
                    <i class="bi bi-funnel"></i> Filtros
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success w-100" data-bs-toggle="collapse" data-bs-target="#searchResults" aria-expanded="true" aria-controls="searchResults">
                    <i class="bi bi-people"></i> Resultados
                </button>
            </div>
        </div>
        
        <!-- Solicitações de Amizade Pendentes -->
        <?php $friendRequest->render($pending_requests); ?>
        
        <!-- Seção de Localização -->
        <?php $location->render($current_location); ?>
        
        <!-- Filtros de Busca -->
        <div class="collapse" id="searchFilters">
            <div class="search-filters">
                <h5><i class="bi bi-funnel"></i> Filtros de Busca</h5>
                <form method="GET" id="searchForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="distance" class="form-label">Distância Máxima: <span id="distanceValue"><?= $search_filters['distance'] ?></span> km</label>
                            <input type="range" class="form-range" id="distance" name="distance" 
                                   min="5" max="50" step="5" value="<?= $search_filters['distance'] ?>"
                                   oninput="document.getElementById('distanceValue').textContent = this.value">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="similar_preferences" name="similar_preferences" 
                                       <?= $search_filters['similar_preferences'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="similar_preferences">
                                    <strong>Buscar por preferências similares às minhas</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="city" class="form-label">Cidade:</label>
                            <input type="text" class="form-control" id="city" name="city" 
                                   placeholder="Digite uma cidade..." value="<?= htmlspecialchars($search_filters['city']) ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="state" class="form-label">Estado:</label>
                            <input type="text" class="form-control" id="state" name="state" 
                                   placeholder="Digite um estado..." value="<?= htmlspecialchars($search_filters['state']) ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="tags" class="form-label">Tags específicas:</label>
                            <input type="text" class="form-control" id="tags" name="tags" 
                                   placeholder="Ex: D&D 5e, Horror, Narrativo..." value="<?= htmlspecialchars($search_filters['tags']) ?>">
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Resultados da Busca -->
        <div class="collapse show" id="searchResults">
            <div class="search-results">
                <h4><i class="bi bi-people"></i> Jogadores Encontrados (<?= $search_results->num_rows ?>)</h4>
                
                <?php if ($search_results->num_rows === 0): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Nenhum jogador encontrado com os filtros aplicados.
                        <?php if (!$current_location): ?>
                            <br><strong>Dica:</strong> Defina sua localização para encontrar jogadores próximos!
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php while ($player = $search_results->fetch_assoc()): ?>
                            <?php
                            $distance = calculateDistance($current_location, $player['latitude'], $player['longitude']);
                            $compatibility = calculateTagCompatibility($current_user_tags, $player['preferences']);
                            $player_tags = RPGTags::parseUserTags($player['preferences']);
                            ?>
                            
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="player-card">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="me-3">
                                            <?php if ($player['image']): ?>
                                                <img src="data:image/jpeg;base64,<?= $player['image'] ?>" alt="Foto de perfil" class="player-image">
                                            <?php else: ?>
                                                <div class="player-image-placeholder">
                                                    <i class="bi bi-person-fill"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($player['name']) ?></h6>
                                            <small class="text-muted">@<?= htmlspecialchars($player['username']) ?></small>
                                            
                                            <?php if ($compatibility > 0): ?>
                                                <div class="mt-1">
                                                    <span class="compatibility-badge">
                                                        <?= round($compatibility) ?>% compatível
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($distance !== null): ?>
                                        <div class="distance-info mb-2">
                                            <i class="bi bi-geo-alt"></i> <?= round($distance, 1) ?> km de distância
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($player['city'] || $player['state']): ?>
                                        <div class="text-muted mb-2">
                                            <i class="bi bi-pin-map"></i>
                                            <?= htmlspecialchars($player['city'] ?? 'Cidade não informada') ?>, 
                                            <?= htmlspecialchars($player['state'] ?? 'Estado não informado') ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($player_tags)): ?>
                                        <div class="tag-preview mb-3">
                                            <small class="text-muted">Preferências:</small>
                                            <div>
                                                <?php foreach (array_slice($player_tags, 0, 3) as $tag): ?>
                                                    <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($tag) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($player_tags) > 3): ?>
                                                    <span class="badge bg-outline-secondary">+<?= count($player_tags) - 3 ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-primary btn-sm" onclick="showPlayerProfile(<?= $player['id'] ?>)">
                                            <i class="bi bi-eye"></i> Ver Perfil
                                        </button>
                                        <button class="btn btn-success btn-sm btn-add-friend" onclick="addFriend(<?= $player['id'] ?>, this)" data-player-id="<?= $player['id'] ?>">
                                            <i class="bi bi-person-plus"></i> Adicionar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Perfil -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Perfil do Jogador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="profileModalBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
         // ===== FUNÇÃO PARA MOSTRAR PERFIL DE JOGADOR =====
function showPlayerProfile(userId) {
    const modal = new bootstrap.Modal(document.getElementById('profileModal'));
    const modalBody = document.getElementById('profileModalBody');
    
    // Mostrar loading
    modalBody.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Fazer requisição AJAX para obter dados do perfil
    fetch(`../../components/ViewProfile/ajax.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-4 text-center">
                            ${data.user.image ? 
                                `<img src="data:image/jpeg;base64,${data.user.image}" alt="Foto de perfil" class="img-fluid rounded mb-3">` : 
                                `<div class="bg-secondary rounded mb-3 d-flex align-items-center justify-content-center" style="height: 150px; width: 150px; margin: 0 auto;">
                                    <i class="bi bi-person-fill text-white" style="font-size: 3rem;"></i>
                                </div>`
                            }
                        </div>
                        <div class="col-md-8">
                            <h4>${data.user.name}</h4>
                            <p class="text-muted">@${data.user.username}</p>
                            <table class="table table-sm">
                                <tr><th>Gênero:</th><td>${data.user.gender || 'Não informado'}</td></tr>
                                <tr><th>Pronomes:</th><td>${data.user.pronouns || 'Não informado'}</td></tr>
                                <tr><th>Preferências:</th><td>${data.user.preferences || 'Não informado'}</td></tr>
                            </table>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="friend_id" value="${data.user.id}">
                                <button type="submit" name="add_friend" class="btn btn-success">
                                    <i class="bi bi-person-plus"></i> Enviar Solicitação de Amizade
                                </button>
                            </form>
                        </div>
                    </div>
                `;
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Erro ao carregar perfil: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Erro ao carregar perfil.
                </div>
            `;
        });
}

// ===== FUNÇÃO PARA ADICIONAR AMIGO (VERSÃO MELHORADA COM AJAX) =====
function addFriend(friendId, buttonElement) {
    // Desabilitar botão durante o processamento
    buttonElement.disabled = true;
    const originalText = buttonElement.innerHTML;
    buttonElement.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';
    
    // Criar dados do formulário
    const formData = new FormData();
    formData.append('add_friend', '1');
    formData.append('friend_id', friendId);
    formData.append('ajax', '1');
    
    // Fazer requisição AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Sucesso - atualizar botão
            buttonElement.innerHTML = '<i class="bi bi-check"></i> Enviado!';
            buttonElement.classList.remove('btn-success');
            buttonElement.classList.add('btn-outline-success');
            
            // Mostrar alerta de sucesso
            showAlert(data.message, 'success');
        } else {
            // Erro - restaurar botão
            buttonElement.innerHTML = originalText;
            buttonElement.disabled = false;
            
            // Mostrar alerta de erro
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        // Erro na requisição - restaurar botão
        buttonElement.innerHTML = originalText;
        buttonElement.disabled = false;
        
        showAlert('Erro ao enviar solicitação de amizade.', 'danger');
    });
}

// ===== FUNÇÃO PARA MOSTRAR ALERTAS FLUTUANTES =====
function showAlert(message, type = 'info') {
    // Remover alertas existentes
    const existingAlerts = document.querySelectorAll('.alert-floating');
    existingAlerts.forEach(alert => alert.remove());
    
    // Criar novo alerta
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-floating`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Adicionar ao body
    document.body.appendChild(alertDiv);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// ===== FUNÇÃO PARA ATUALIZAR VALOR DO SLIDER DE DISTÂNCIA =====
function updateDistanceValue(value) {
    document.getElementById('distanceValue').textContent = value;
}

// ===== INICIALIZAÇÃO QUANDO O DOM CARREGAR =====
document.addEventListener('DOMContentLoaded', function() {
    // Configurar slider de distância
    const distanceSlider = document.getElementById('distance');
    if (distanceSlider) {
        distanceSlider.addEventListener('input', function() {
            updateDistanceValue(this.value);
        });
    }
    
    // Configurar auto-submit do formulário de filtros (opcional)
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        // Aplicar filtros automaticamente após mudanças (com debounce)
        let timeout;
        const inputs = searchForm.querySelectorAll('input[type="text"], input[type="range"], input[type="checkbox"]');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    // Opcional: submeter automaticamente após 1 segundo de inatividade
                    // searchForm.submit();
                }, 1000);
            });
        });
    }
    
    // Configurar tooltips do Bootstrap (se houver)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Configurar popovers do Bootstrap (se houver)
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// ===== FUNÇÃO PARA FILTRAR RESULTADOS EM TEMPO REAL (OPCIONAL) =====
function filterResults() {
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        // Mostrar loading nos resultados
        const resultsContainer = document.querySelector('.search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = `
                <div class="text-center my-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Buscando...</span>
                    </div>
                    <p class="mt-2">Buscando jogadores...</p>
                </div>
            `;
        }
        
        // Submeter formulário
        searchForm.submit();
    }
}

// ===== FUNÇÃO PARA LIMPAR FILTROS =====
function clearFilters() {
    window.location.href = window.location.pathname;
}

// ===== FUNÇÃO PARA EXPANDIR/RECOLHER SEÇÕES =====
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        const collapse = new bootstrap.Collapse(section);
        collapse.toggle();
    }
}

// ===== FUNÇÃO PARA COPIAR LINK DO PERFIL =====
function copyProfileLink(userId) {
    const link = `${window.location.origin}/pages/profile/?id=${userId}`;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(link).then(() => {
            showAlert('Link do perfil copiado!', 'success');
        }).catch(() => {
            showAlert('Erro ao copiar link.', 'danger');
        });
    } else {
        // Fallback para navegadores mais antigos
        const textArea = document.createElement('textarea');
        textArea.value = link;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showAlert('Link do perfil copiado!', 'success');
        } catch (err) {
            showAlert('Erro ao copiar link.', 'danger');
        }
        document.body.removeChild(textArea);
    }
}

// ===== FUNÇÃO PARA REPORTAR USUÁRIO (SE NECESSÁRIO) =====
function reportUser(userId) {
    if (confirm('Tem certeza de que deseja reportar este usuário?')) {
        const formData = new FormData();
        formData.append('action', 'report_user');
        formData.append('user_id', userId);
        
        fetch('../../components/Report/index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Usuário reportado com sucesso.', 'success');
            } else {
                showAlert('Erro ao reportar usuário.', 'danger');
            }
        })
        .catch(error => {
            showAlert('Erro ao reportar usuário.', 'danger');
        });
    }
}

// ===== FUNÇÃO PARA ANIMAÇÕES SUAVES =====
function smoothScrollTo(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// ===== FUNÇÃO PARA LAZY LOADING DE IMAGENS (SE NECESSÁRIO) =====
function setupLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback para navegadores sem suporte
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}
    </script>
</body>
</html>