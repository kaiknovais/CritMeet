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

// Adicionar amigo
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

// Obter solicitações pendentes
$pending_requests = $friendRequest->getPendingRequests();

// Parâmetros de busca
$search_type = $_GET['search_type'] ?? 'nearby';
$search_username = $_GET['username'] ?? '';
$specific_tags = $_GET['tags'] ?? '';
$distance_limit = min(100, max(5, intval($_GET['distance'] ?? 20)));

// Resultados da busca
$search_results = [];

// Base query - excluir usuários que já são amigos ou têm pedidos pendentes
$base_exclusion = " AND u.id NOT IN (
    SELECT CASE 
        WHEN f.user_id = ? THEN f.friend_id 
        ELSE f.user_id 
    END 
    FROM friends f 
    WHERE (f.user_id = ? OR f.friend_id = ?) 
    AND f.status IN ('accepted', 'pending')
)";

if ($search_type === 'username' && !empty($search_username)) {
    // Busca por username específico
    $sql = "SELECT u.id, u.username, u.name, u.preferences, u.gender, u.pronouns, u.image,
                   ul.latitude, ul.longitude, ul.city, ul.state
            FROM users u 
            LEFT JOIN user_locations ul ON u.id = ul.user_id 
            WHERE u.id != ? AND u.username LIKE ?" . $base_exclusion . "
            ORDER BY u.username ASC LIMIT 10";
    
    $stmt = $mysqli->prepare($sql);
    $search_param = "%" . $search_username . "%";
    $stmt->bind_param("isiii", $user_id, $search_param, $user_id, $user_id, $user_id);
    
} elseif ($search_type === 'nearby' && $current_location) {
    // Busca por jogadores próximos
    $sql = "SELECT u.id, u.username, u.name, u.preferences, u.gender, u.pronouns, u.image,
                   ul.latitude, ul.longitude, ul.city, ul.state,
                   (6371 * acos(
                       cos(radians(?)) * cos(radians(ul.latitude)) * 
                       cos(radians(ul.longitude) - radians(?)) + 
                       sin(radians(?)) * sin(radians(ul.latitude))
                   )) as distance
            FROM users u 
            JOIN user_locations ul ON u.id = ul.user_id 
            WHERE u.id != ? 
            AND ul.latitude IS NOT NULL 
            AND ul.longitude IS NOT NULL" . $base_exclusion . "
            HAVING distance <= ?
            ORDER BY distance ASC LIMIT 20";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("dddiiiii", 
        $current_location['latitude'], 
        $current_location['longitude'], 
        $current_location['latitude'], 
        $user_id, 
        $user_id, $user_id, $user_id,
        $distance_limit
    );
    
} elseif ($search_type === 'similar' && !empty($current_user_tags)) {
    // Busca por preferências similares
    $tag_conditions = [];
    $params = [$user_id];
    $param_types = "i";
    
    foreach ($current_user_tags as $tag) {
        $tag_conditions[] = "u.preferences LIKE ?";
        $params[] = "%" . $tag . "%";
        $param_types .= "s";
    }
    
    $sql = "SELECT u.id, u.username, u.name, u.preferences, u.gender, u.pronouns, u.image,
                   ul.latitude, ul.longitude, ul.city, ul.state
            FROM users u 
            LEFT JOIN user_locations ul ON u.id = ul.user_id 
            WHERE u.id != ? 
            AND (" . implode(" OR ", $tag_conditions) . ")" . $base_exclusion . "
            ORDER BY u.name ASC LIMIT 20";
    
    // Adicionar parâmetros de exclusão
    $params[] = $user_id;
    $params[] = $user_id;
    $params[] = $user_id;
    $param_types .= "iii";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    
} elseif ($search_type === 'specific' && !empty($specific_tags)) {
    // Busca por tags específicas
    $search_tags = array_map('trim', explode(',', $specific_tags));
    $tag_conditions = [];
    $params = [$user_id];
    $param_types = "i";
    
    foreach ($search_tags as $tag) {
        if (!empty($tag)) {
            $tag_conditions[] = "u.preferences LIKE ?";
            $params[] = "%" . $tag . "%";
            $param_types .= "s";
        }
    }
    
    if (!empty($tag_conditions)) {
        $sql = "SELECT u.id, u.username, u.name, u.preferences, u.gender, u.pronouns, u.image,
                       ul.latitude, ul.longitude, ul.city, ul.state
                FROM users u 
                LEFT JOIN user_locations ul ON u.id = ul.user_id 
                WHERE u.id != ? 
                AND (" . implode(" OR ", $tag_conditions) . ")" . $base_exclusion . "
                ORDER BY u.name ASC LIMIT 20";
        
        // Adicionar parâmetros de exclusão
        $params[] = $user_id;
        $params[] = $user_id;
        $params[] = $user_id;
        $param_types .= "iii";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
    }
}

// Executar busca se houver statement preparado
if (isset($stmt)) {
    $stmt->execute();
    $search_results = $stmt->get_result();
    $stmt->close();
}

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

// Función para calcular compatibilidade
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        .search-card {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .filter-tabs .nav-link {
            border-radius: 25px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .filter-tabs .nav-link.active {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border-color: transparent;
        }
        
        .player-card {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .player-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .player-image {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
        }
        
        .player-image-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(45deg, #dee2e6, #adb5bd);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.8rem;
        }
        
        .compatibility-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .distance-badge {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
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
        
        .alert-floating {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
        
        .search-type-description {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
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
        <div class="row">
            <div class="col-md-3">
                <!-- Solicitações Pendentes -->
                <div class="search-card">
                    <h6 class="notification-badge">
                        <i class="bi bi-person-plus"></i> Solicitações
                        <?php if (count($pending_requests) > 0): ?>
                            <span class="badge bg-danger"><?php echo count($pending_requests); ?></span>
                        <?php endif; ?>
                    </h6>
                    
                    <?php if (count($pending_requests) > 0): ?>
                        <?php foreach ($pending_requests as $request): ?>
                            <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div>
                                    <small class="fw-bold"><?= htmlspecialchars($request['name']) ?></small>
                                    <br><small class="text-muted">@<?= htmlspecialchars($request['username']) ?></small>
                                </div>
                                <div>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="friendship_id" value="<?= $request['id'] ?>">
                                        <button type="submit" name="accept_friend" class="btn btn-success btn-sm">✓</button>
                                        <button type="submit" name="reject_friend" class="btn btn-danger btn-sm">✗</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Nenhuma solicitação pendente</p>
                    <?php endif; ?>
                </div>

                <!-- Localização -->
                <?php if ($current_location): ?>
                    <div class="search-card">
                        <h6><i class="bi bi-geo-alt-fill text-success"></i> Sua Localização</h6>
                        <p class="small mb-2">
                            <strong><?= htmlspecialchars($current_location['city']) ?></strong><br>
                            <?= htmlspecialchars($current_location['state']) ?>
                        </p>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#locationModal">
                            <i class="bi bi-pencil"></i> Alterar
                        </button>
                    </div>
                <?php else: ?>
                    <div class="search-card">
                        <h6><i class="bi bi-geo-alt text-warning"></i> Localização</h6>
                        <p class="text-muted small">Defina sua localização para encontrar jogadores próximos</p>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#locationModal">
                            <i class="bi bi-plus"></i> Definir
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-9">
                <!-- Filtros de Busca -->
                <div class="search-card">
                    <h4><i class="bi bi-search"></i> Buscar Jogadores</h4>
                    
                    <!-- Tabs de Filtro -->
                    <ul class="nav nav-pills filter-tabs justify-content-center mb-4" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'nearby' ? 'active' : '' ?>" href="?search_type=nearby">
                                <i class="bi bi-geo-alt"></i> Próximos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'similar' ? 'active' : '' ?>" href="?search_type=similar">
                                <i class="bi bi-heart"></i> Similares
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'specific' ? 'active' : '' ?>" href="?search_type=specific">
                                <i class="bi bi-tags"></i> Específicos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'username' ? 'active' : '' ?>" href="?search_type=username">
                                <i class="bi bi-person-search"></i> Username
                            </a>
                        </li>
                    </ul>

                    <!-- Formulários de Busca -->
                    <?php if ($search_type === 'nearby'): ?>
                        <div class="search-type-description">
                            <strong><i class="bi bi-info-circle"></i> Jogadores Próximos:</strong> 
                            <?php if ($current_location): ?>
                                Encontre jogadores perto de <?= htmlspecialchars($current_location['city']) ?>, <?= htmlspecialchars($current_location['state']) ?>
                            <?php else: ?>
                                <span class="text-warning">Configure sua localização primeiro!</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($current_location): ?>
                            <form method="GET" class="row g-3">
                                <input type="hidden" name="search_type" value="nearby">
                                <div class="col-md-6">
                                    <label class="form-label">Distância Máxima: <span id="distValue"><?= $distance_limit ?></span> km</label>
                                    <input type="range" class="form-range" name="distance" min="5" max="100" step="5" 
                                           value="<?= $distance_limit ?>" oninput="document.getElementById('distValue').textContent = this.value">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                    <?php elseif ($search_type === 'similar'): ?>
                        <div class="search-type-description">
                            <strong><i class="bi bi-info-circle"></i> Preferências Similares:</strong> 
                            <?php if (!empty($current_user_tags)): ?>
                                Baseado nas suas tags: <?= implode(', ', array_slice($current_user_tags, 0, 3)) ?><?= count($current_user_tags) > 3 ? '...' : '' ?>
                            <?php else: ?>
                                <span class="text-warning">Configure suas preferências no perfil primeiro!</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($current_user_tags)): ?>
                            <form method="GET">
                                <input type="hidden" name="search_type" value="similar">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Buscar Jogadores Similares
                                </button>
                            </form>
                        <?php endif; ?>

                    <?php elseif ($search_type === 'specific'): ?>
                        <div class="search-type-description">
                            <strong><i class="bi bi-info-circle"></i> Tags Específicas:</strong> 
                            Busque por preferências específicas (ex: D&D 5e, Horror, Narrativo)
                        </div>
                        
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="search_type" value="specific">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="tags" 
                                       placeholder="Digite as tags separadas por vírgula..." 
                                       value="<?= htmlspecialchars($specific_tags) ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                            </div>
                        </form>

                    <?php elseif ($search_type === 'username'): ?>
                        <div class="search-type-description">
                            <strong><i class="bi bi-info-circle"></i> Busca por Username:</strong> 
                            Encontre um jogador específico pelo nome de usuário
                        </div>
                        
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="search_type" value="username">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="username" 
                                       placeholder="Digite o username..." 
                                       value="<?= htmlspecialchars($search_username) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Resultados -->
                <div class="search-card">
                    <h5><i class="bi bi-people"></i> Resultados da Busca 
                        <?php if (isset($search_results)): ?>
                            (<?= $search_results->num_rows ?>)
                        <?php endif; ?>
                    </h5>
                    
                    <?php if (!isset($search_results) || $search_results->num_rows === 0): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">
                                <?php if (!isset($search_results)): ?>
                                    Selecione um filtro e faça uma busca para encontrar jogadores!
                                <?php else: ?>
                                    Nenhum jogador encontrado com os critérios selecionados.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php while ($player = $search_results->fetch_assoc()): ?>
                                <?php
                                $distance = calculateDistance($current_location, $player['latitude'], $player['longitude']);
                                $compatibility = calculateTagCompatibility($current_user_tags, $player['preferences']);
                                $player_tags = RPGTags::parseUserTags($player['preferences']);
                                ?>
                                
                                <div class="col-md-6 col-xl-4 mb-3">
                                    <div class="player-card h-100">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="me-3">
                                                <?php if ($player['image']): ?>
                                                    <img src="data:image/jpeg;base64,<?= $player['image'] ?>" alt="Foto" class="player-image">
                                                <?php else: ?>
                                                    <div class="player-image-placeholder">
                                                        <i class="bi bi-person-fill"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($player['name']) ?></h6>
                                                <small class="text-muted">@<?= htmlspecialchars($player['username']) ?></small>
                                                
                                                <div class="mt-2">
                                                    <?php if ($distance !== null): ?>
                                                        <span class="distance-badge me-1">
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