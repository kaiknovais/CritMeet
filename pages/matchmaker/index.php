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
        if ($stmt_add->execute()) {
            echo "<script>alert('Pedido de amizade enviado!'); window.location.href='';</script>";
        } else {
            echo "<script>alert('Erro ao enviar pedido de amizade.');</script>";
        }
        $stmt_add->close();
    } else {
        echo "<script>alert('Você já é amigo ou há um pedido pendente.'); window.location.href='';</script>";
    }
    $stmt_check->close();
}

// Aceitar/Rejeitar solicitações
if (isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    $friendRequest->acceptRequest($friendship_id);
}

if (isset($_POST['reject_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    $friendRequest->rejectRequest($friendship_id);
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
    'distance' => $_GET['distance'] ?? 50,
    'city' => $_GET['city'] ?? '',
    'state' => $_GET['state'] ?? ''
];

// Construir query de busca - buscar usuários que não são amigos e não têm pedidos pendentes
$search_results = [];
$base_query = "SELECT u.id, u.username, u.name, u.preferences, u.gender, u.pronouns, u.image,
                      ul.latitude, ul.longitude, ul.city, ul.state, ul.address";

$from_clause = " FROM users u 
                 LEFT JOIN user_locations ul ON u.id = ul.user_id 
                 LEFT JOIN friends f1 ON (f1.user_id = ? AND f1.friend_id = u.id)
                 LEFT JOIN friends f2 ON (f2.user_id = u.id AND f2.friend_id = ?)
                 WHERE u.id != ? 
                 AND f1.id IS NULL 
                 AND f2.id IS NULL";

$params = [$user_id, $user_id, $user_id];
$param_types = "iii";

// Filtros de localização
if ($current_location && $search_filters['distance'] > 0) {
    $from_clause .= " AND (
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
    
    <style>
        .player-card {
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            background-color: #fff;
        }
        
        .search-filters {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .modal-body img {
            max-width: 150px;
            border-radius: 8px;
        }
        
        .distance-info {
            color: #6c757d;
            font-size: 0.9rem;
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
                            <li><a class="dropdown-item" href="../matchmaker/">Buscar Jogadores</a></li>
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
        <h2>Buscar Jogadores</h2>
        
        <!-- Botão para mostrar solicitações pendentes -->
        <?php if (count($pending_requests) > 0): ?>
            <button class="btn btn-warning mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#friendRequests">
                <i class="bi bi-person-plus"></i> Solicitações Pendentes (<?php echo count($pending_requests); ?>)
            </button>
        <?php endif; ?>
        
        <!-- Solicitações de Amizade Pendentes -->
        <?php $friendRequest->render($pending_requests); ?>
        
        <!-- Botão para definir localização -->
        <button class="btn btn-primary mb-3" data-bs-toggle="collapse" data-bs-target="#locationSection">
            <i class="bi bi-geo-alt"></i> Definir Localização
        </button>
        
        <!-- Seção de Localização -->
        <?php $location->render($current_location); ?>
        
        <!-- Filtros de Busca -->
        <div class="search-filters">
            <h5>Filtros de Busca</h5>
            <form method="GET">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="distance" class="form-label">Distância Máxima: <span id="distanceValue"><?= $search_filters['distance'] ?></span> km</label>
                        <input type="range" class="form-range" id="distance" name="distance" 
                               min="5" max="500" step="5" value="<?= $search_filters['distance'] ?>"
                               oninput="document.getElementById('distanceValue').textContent = this.value">
                    </div>
                    
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
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <a href="?" class="btn btn-secondary ms-2">
                    <i class="bi bi-arrow-clockwise"></i> Limpar
                </a>
            </form>
        </div>
        
        <!-- Resultados da Busca -->
        <div class="search-results">
            <h4>Jogadores Encontrados (<?= $search_results->num_rows ?>)</h4>
            
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
                        ?>
                        
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="player-card">
                                <h6><?= htmlspecialchars($player['name']) ?></h6>
                                <small class="text-muted">@<?= htmlspecialchars($player['username']) ?></small>
                                
                                <?php if ($distance !== null): ?>
                                    <div class="distance-info mt-1">
                                        <i class="bi bi-geo-alt"></i> <?= round($distance, 1) ?> km de distância
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($player['city'] || $player['state']): ?>
                                    <div class="text-muted mt-1">
                                        <i class="bi bi-pin-map"></i>
                                        <?= htmlspecialchars($player['city'] ?? 'Cidade não informada') ?>, 
                                        <?= htmlspecialchars($player['state'] ?? 'Estado não informado') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary btn-sm" onclick="showPlayerProfile(<?= $player['id'] ?>)">
                                        <i class="bi bi-eye"></i> Ver Perfil
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="friend_id" value="<?= $player['id'] ?>">
                                        <button type="submit" name="add_friend" class="btn btn-success btn-sm">
                                            <i class="bi bi-person-plus"></i> Adicionar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
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

    <footer class="mt-5 py-4 bg-dark text-white text-center">
        <p>&copy; <?php echo date("Y"); ?> CritMeet - Conectando Jogadores de RPG</p>
    </footer>

    <script>
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
    </script>
</body>
</html>