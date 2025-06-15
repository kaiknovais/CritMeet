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

// Obter perfil do usuário atual - CORRIGIDO: removido 'bio' da query
$user_query = "SELECT username, name, preferences FROM users WHERE id = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();
$current_user_tags = RPGTags::parseUserTags($current_user['preferences'] ?? '');

// Parâmetros de busca
$search_filters = [
    'distance' => $_GET['distance'] ?? 50, // km
    'tags' => $_GET['tags'] ?? '',
    'match_type' => $_GET['match_type'] ?? 'similar', // similar, specific, nearby, all
    'city' => $_GET['city'] ?? '',
    'state' => $_GET['state'] ?? '',
    'sort' => $_GET['sort'] ?? 'compatibility' // compatibility, distance, recent
];

// Construir query de busca - CORRIGIDO: removido 'bio' da query
$search_results = [];
$base_query = "SELECT u.id, u.username, u.name, u.preferences, u.created_at,
                      ul.latitude, ul.longitude, ul.city, ul.state, ul.address, ul.updated_at as location_updated";

$from_clause = " FROM users u 
                 LEFT JOIN user_locations ul ON u.id = ul.user_id 
                 WHERE u.id != ? AND u.active = 1";

$params = [$user_id];
$param_types = "i";

// Filtros de localização
if ($current_location && $search_filters['distance'] > 0) {
    $from_clause .= " HAVING (
        6371 * acos(
            cos(radians(?)) * cos(radians(ul.latitude)) * 
            cos(radians(ul.longitude) - radians(?)) + 
            sin(radians(?)) * sin(radians(ul.latitude))
        )
    ) <= ? OR ul.latitude IS NULL";
    
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

$order_clause = " ORDER BY ";
switch ($search_filters['sort']) {
    case 'distance':
        if ($current_location) {
            $order_clause .= "(
                6371 * acos(
                    cos(radians(" . $current_location['latitude'] . ")) * cos(radians(ul.latitude)) * 
                    cos(radians(ul.longitude) - radians(" . $current_location['longitude'] . ")) + 
                    sin(radians(" . $current_location['latitude'] . ")) * sin(radians(ul.latitude))
                )
            ) ASC";
        } else {
            $order_clause .= "u.created_at DESC";
        }
        break;
    case 'recent':
        $order_clause .= "u.created_at DESC";
        break;
    default: // compatibility
        $order_clause .= "u.name ASC";
}

$full_query = $base_query . $from_clause . $order_clause . " LIMIT 50";

$search_stmt = $mysqli->prepare($full_query);
if (!empty($params)) {
    $search_stmt->bind_param($param_types, ...$params);
}
$search_stmt->execute();
$search_results = $search_stmt->get_result();

// Função para calcular compatibilidade de tags
function calculateTagCompatibility($user_tags, $other_tags) {
    if (empty($user_tags) || empty($other_tags)) return 0;
    
    $intersection = array_intersect($user_tags, $other_tags);
    $union = array_unique(array_merge($user_tags, $other_tags));
    
    return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
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
    
    return 6371 * $c; // Distância em km
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Jogadores - CritMeet</title>
    <link rel="stylesheet" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        .player-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .player-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .compatibility-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            border-radius: 20px;
            padding: 5px 12px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .compatibility-high { background: #28a745; }
        .compatibility-medium { background: #ffc107; color: #000; }
        .compatibility-low { background: #dc3545; }
        
        .distance-info {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .no-results {
            text-align: center;
            padding: 50px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            color: #6c757d;
        }
        
        .match-type-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .match-type-btn {
            padding: 8px 16px;
            border: 2px solid #007bff;
            background: transparent;
            color: #007bff;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .match-type-btn.active,
        .match-type-btn:hover {
            background: #007bff;
            color: white;
        }
        
        .player-tags {
            margin-top: 15px;
        }
        
        .player-tags .badge {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .location-status {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .search-filters {
                padding: 15px;
            }
            
            .player-card {
                padding: 15px;
            }
            
            .compatibility-badge {
                position: static;
                display: inline-block;
                margin-bottom: 10px;
            }
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
                            <li><a class="dropdown-item" href="../searchplayers/">Buscar Jogadores</a></li>
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
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-people-fill"></i> Buscar Jogadores
                    <button class="btn btn-primary btn-sm ms-2" data-bs-toggle="collapse" data-bs-target="#locationSection">
                        <i class="bi bi-geo-alt"></i> Definir Localização
                    </button>
                </h2>
                
                <!-- Seção de Localização (Colapsável) -->
                <?php $location->render($current_location); ?>
                
                <!-- Filtros de Busca -->
                <div class="search-filters">
                    <form method="GET" id="searchForm">
                        <div class="row">
                            <div class="col-12">
                                <h5><i class="bi bi-funnel"></i> Filtros de Busca</h5>
                            </div>
                        </div>
                        
                        <!-- Tipo de Match -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Tipo de Busca:</label>
                                <div class="match-type-selector">
                                    <input type="radio" class="btn-check" name="match_type" id="match_similar" value="similar" <?= $search_filters['match_type'] === 'similar' ? 'checked' : '' ?>>
                                    <label class="match-type-btn" for="match_similar">
                                        <i class="bi bi-heart"></i> Gostos Similares
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="match_type" id="match_nearby" value="nearby" <?= $search_filters['match_type'] === 'nearby' ? 'checked' : '' ?>>
                                    <label class="match-type-btn" for="match_nearby">
                                        <i class="bi bi-geo-alt"></i> Próximos
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="match_type" id="match_all" value="all" <?= $search_filters['match_type'] === 'all' ? 'checked' : '' ?>>
                                    <label class="match-type-btn" for="match_all">
                                        <i class="bi bi-people"></i> Todos
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Distância -->
                            <div class="col-md-6 mb-3">
                                <label for="distance" class="form-label">
                                    <i class="bi bi-compass"></i> 
                                    Distância Máxima: <span id="distanceValue"><?= $search_filters['distance'] ?></span> km
                                </label>
                                <input type="range" class="form-range" id="distance" name="distance" 
                                       min="5" max="500" step="5" value="<?= $search_filters['distance'] ?>"
                                       oninput="document.getElementById('distanceValue').textContent = this.value">
                            </div>
                            
                            <!-- Ordenação -->
                            <div class="col-md-6 mb-3">
                                <label for="sort" class="form-label"><i class="bi bi-sort-down"></i> Ordenar por:</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="compatibility" <?= $search_filters['sort'] === 'compatibility' ? 'selected' : '' ?>>Compatibilidade</option>
                                    <option value="distance" <?= $search_filters['sort'] === 'distance' ? 'selected' : '' ?>>Distância</option>
                                    <option value="recent" <?= $search_filters['sort'] === 'recent' ? 'selected' : '' ?>>Mais Recentes</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Cidade -->
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label"><i class="bi bi-building"></i> Cidade:</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       placeholder="Digite uma cidade..." value="<?= htmlspecialchars($search_filters['city']) ?>">
                            </div>
                            
                            <!-- Estado -->
                            <div class="col-md-6 mb-3">
                                <label for="state" class="form-label"><i class="bi bi-map"></i> Estado:</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       placeholder="Digite um estado..." value="<?= htmlspecialchars($search_filters['state']) ?>">
                            </div>
                        </div>
                        
                        <!-- Tags Específicas -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label"><i class="bi bi-tags"></i> Filtrar por Tags Específicas:</label>
                                <input type="text" class="form-control" name="tags" 
                                       placeholder="Digite tags separadas por vírgula (ex: D&D 5e, Fantasia Medieval)"
                                       value="<?= htmlspecialchars($search_filters['tags']) ?>">
                                <small class="form-text text-muted">
                                    Deixe em branco para buscar por compatibilidade geral
                                </small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-search"></i> Buscar Jogadores
                                </button>
                                <a href="?" class="btn btn-outline-secondary btn-lg ms-2">
                                    <i class="bi bi-arrow-clockwise"></i> Limpar Filtros
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Resultados da Busca -->
                <div class="search-results">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4><i class="bi bi-person-lines-fill"></i> Jogadores Encontrados</h4>
                        <span class="badge bg-primary fs-6">
                            <?= $search_results->num_rows ?> resultados
                        </span>
                    </div>
                    
                    <?php if ($search_results->num_rows === 0): ?>
                        <div class="no-results">
                            <i class="bi bi-search" style="font-size: 3rem; margin-bottom: 20px;"></i>
                            <h5>Nenhum jogador encontrado</h5>
                            <p>Tente ajustar os filtros de busca ou expandir a área de pesquisa.</p>
                            <?php if (!$current_location): ?>
                                <p class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Dica:</strong> Defina sua localização para encontrar jogadores próximos!
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php while ($player = $search_results->fetch_assoc()): ?>
                                <?php
                                $player_tags = RPGTags::parseUserTags($player['preferences'] ?? '');
                                $compatibility = calculateTagCompatibility($current_user_tags, $player_tags);
                                $distance = calculateDistance($current_location, $player['latitude'], $player['longitude']);
                                
                                // Determinar classe de compatibilidade
                                $comp_class = 'compatibility-low';
                                if ($compatibility >= 70) $comp_class = 'compatibility-high';
                                elseif ($compatibility >= 40) $comp_class = 'compatibility-medium';
                                ?>
                                
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="player-card position-relative">
                                        <!-- Badge de Compatibilidade -->
                                        <?php if (!empty($current_user_tags) && !empty($player_tags)): ?>
                                            <div class="compatibility-badge <?= $comp_class ?>">
                                                <?= round($compatibility) ?>% Match
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Informações de Distância -->
                                        <?php if ($distance !== null): ?>
                                            <div class="distance-info">
                                                <i class="bi bi-geo-alt"></i>
                                                <?= round($distance, 1) ?> km de distância
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Informações do Jogador -->
                                        <h5 class="mb-2">
                                            <?= htmlspecialchars($player['name']) ?>
                                            <small class="text-white-50">(@<?= htmlspecialchars($player['username']) ?>)</small>
                                        </h5>
                                        
                                        <!-- Localização -->
                                        <?php if ($player['city'] || $player['state']): ?>
                                            <div class="location-status">
                                                <i class="bi bi-pin-map"></i>
                                                <?= htmlspecialchars($player['city'] ?? 'Cidade não informada') ?>, 
                                                <?= htmlspecialchars($player['state'] ?? 'Estado não informado') ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Preferences como Bio - CORRIGIDO: usando preferences -->
                                        <?php if (!empty($player['preferences'])): ?>
                                            <p class="mt-3 mb-2">
                                                <strong>Preferências:</strong> 
                                                <?= htmlspecialchars(substr($player['preferences'], 0, 150)) ?>
                                                <?= strlen($player['preferences']) > 150 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Tags do Jogador -->
                                        <?php if (!empty($player_tags)): ?>
                                            <div class="player-tags">
                                                <h6><i class="bi bi-tags"></i> Preferências:</h6>
                                                <?php 
                                                $displayed_tags = array_slice($player_tags, 0, 5);
                                                $remaining_count = count($player_tags) - 5;
                                                
                                                foreach ($displayed_tags as $tag): 
                                                    $is_common = in_array($tag, $current_user_tags);
                                                ?>
                                                    <span class="badge <?= $is_common ? 'bg-warning text-dark' : 'bg-light text-dark' ?> me-1 mb-1">
                                                        <?= $is_common ? '⭐ ' : '' ?><?= htmlspecialchars($tag) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                
                                                <?php if ($remaining_count > 0): ?>
                                                    <span class="badge bg-secondary">+<?= $remaining_count ?> mais</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Ações -->
                                        <div class="d-flex gap-2 mt-3">
                                            <a href="../Profile/?id=<?= $player['id'] ?>" class="btn btn-light btn-sm">
                                                <i class="bi bi-person"></i> Ver Perfil
                                            </a>
                                            <a href="../chat/?user=<?= $player['id'] ?>" class="btn btn-success btn-sm">
                                                <i class="bi bi-chat-dots"></i> Conversar
                                            </a>
                                        </div>
                                        
                                        <!-- Data de Cadastro -->
                                        <small class="text-white-50 d-block mt-2">
                                            <i class="bi bi-calendar"></i>
                                            Membro desde <?= date('M/Y', strtotime($player['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-4 bg-dark text-white text-center">
        <p>&copy; <?php echo date("Y"); ?> CritMeet - Conectando Jogadores de RPG</p>
    </footer>

    <script>
        // Auto-submit do formulário quando os radio buttons mudarem
        document.querySelectorAll('input[name="match_type"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.getElementById('searchForm').submit();
            });
        });
        
        // Submit automático quando o select de ordenação mudar
        document.getElementById('sort').addEventListener('change', function() {
            document.getElementById('searchForm').submit();
        });
        
        // Debounce para os campos de texto
        let timeout;
        function debounceSubmit() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                document.getElementById('searchForm').submit();
            }, 1000);
        }
        
        document.getElementById('city').addEventListener('input', debounceSubmit);
        document.getElementById('state').addEventListener('input', debounceSubmit);
        
        // Smooth scroll para resultados após busca
        if (window.location.search) {
            setTimeout(function() {
                document.querySelector('.search-results').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 500);
        }
    </script>
</body>
</html>