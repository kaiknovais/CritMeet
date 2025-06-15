<?php
session_start();
require_once '../../config.php';
require_once '../../components/Tags/index.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

class Matchmaker {
    private $mysqli;
    private $user_id;
    private $max_distance = 50; // 50km limite
    
    public function __construct($mysqli, $user_id) {
        $this->mysqli = $mysqli;
        $this->user_id = $user_id;
    }
    
    public function getCurrentUserLocation() {
        $sql = "SELECT latitude, longitude FROM user_locations WHERE user_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        
        return null;
    }
    
    public function getCurrentUserPreferences() {
        $sql = "SELECT preferences FROM users WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return RPGTags::parseUserTags($row['preferences']);
        }
        
        return [];
    }
    
    public function getFriendsList() {
        $friends = [];
        
        // Buscar amigos onde o usuário atual é user_id
        $sql1 = "SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'";
        $stmt1 = $this->mysqli->prepare($sql1);
        $stmt1->bind_param("i", $this->user_id);
        $stmt1->execute();
        $result1 = $stmt1->get_result();
        
        while ($row = $result1->fetch_assoc()) {
            $friends[] = $row['friend_id'];
        }
        
        // Buscar amigos onde o usuário atual é friend_id
        $sql2 = "SELECT user_id FROM friends WHERE friend_id = ? AND status = 'accepted'";
        $stmt2 = $this->mysqli->prepare($sql2);
        $stmt2->bind_param("i", $this->user_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        while ($row = $result2->fetch_assoc()) {
            $friends[] = $row['user_id'];
        }
        
        return array_unique($friends);
    }
    
    public function getPendingFriendRequests() {
        $pending = [];
        
        // Solicitações enviadas
        $sql1 = "SELECT friend_id FROM friends WHERE user_id = ? AND status = 'pending'";
        $stmt1 = $this->mysqli->prepare($sql1);
        $stmt1->bind_param("i", $this->user_id);
        $stmt1->execute();
        $result1 = $stmt1->get_result();
        
        while ($row = $result1->fetch_assoc()) {
            $pending[] = $row['friend_id'];
        }
        
        // Solicitações recebidas
        $sql2 = "SELECT user_id FROM friends WHERE friend_id = ? AND status = 'pending'";
        $stmt2 = $this->mysqli->prepare($sql2);
        $stmt2->bind_param("i", $this->user_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        while ($row = $result2->fetch_assoc()) {
            $pending[] = $row['user_id'];
        }
        
        return array_unique($pending);
    }
    
    public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371; // Raio da Terra em km
        
        $lat1_rad = deg2rad($lat1);
        $lat2_rad = deg2rad($lat2);
        $delta_lat = deg2rad($lat2 - $lat1);
        $delta_lon = deg2rad($lon2 - $lon1);
        
        $a = sin($delta_lat/2) * sin($delta_lat/2) +
             cos($lat1_rad) * cos($lat2_rad) *
             sin($delta_lon/2) * sin($delta_lon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earth_radius * $c;
    }
    
    public function calculateTagSimilarity($user_tags, $target_tags) {
        if (empty($user_tags) || empty($target_tags)) {
            return 0;
        }
        
        $intersection = array_intersect($user_tags, $target_tags);
        $union = array_unique(array_merge($user_tags, $target_tags));
        
        return count($intersection) / count($union);
    }
    
    public function findMatches($search_tags = [], $sort_by = 'distance', $min_similarity = 0) {
        $current_location = $this->getCurrentUserLocation();
        
        if (!$current_location) {
            return ['error' => 'Você precisa definir sua localização primeiro!'];
        }
        
        $user_preferences = $this->getCurrentUserPreferences();
        $friends_list = $this->getFriendsList();
        $pending_requests = $this->getPendingFriendRequests();
        
        // Criar lista de usuários a excluir (amigos + solicitações pendentes + usuário atual)
        $exclude_users = array_merge($friends_list, $pending_requests, [$this->user_id]);
        
        // Construir query base
        $sql = "SELECT u.id, u.username, u.name, u.gender, u.pronouns, u.preferences, u.image,
                       ul.latitude, ul.longitude, ul.city, ul.state, ul.updated_at
                FROM users u
                JOIN user_locations ul ON u.id = ul.user_id
                WHERE u.id NOT IN (" . str_repeat('?,', count($exclude_users) - 1) . "?)";
        
        // Adicionar filtro de tags se especificado
        if (!empty($search_tags)) {
            $tag_conditions = [];
            foreach ($search_tags as $tag) {
                $tag_conditions[] = "u.preferences LIKE ?";
            }
            $sql .= " AND (" . implode(" OR ", $tag_conditions) . ")";
        }
        
        $stmt = $this->mysqli->prepare($sql);
        
        // Bind parameters
        $types = str_repeat('i', count($exclude_users));
        $params = $exclude_users;
        
        if (!empty($search_tags)) {
            $types .= str_repeat('s', count($search_tags));
            foreach ($search_tags as $tag) {
                $params[] = "%{$tag}%";
            }
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $matches = [];
        
        while ($row = $result->fetch_assoc()) {
            $distance = $this->calculateDistance(
                $current_location['latitude'],
                $current_location['longitude'],
                $row['latitude'],
                $row['longitude']
            );
            
            // Filtrar por distância máxima
            if ($distance > $this->max_distance) {
                continue;
            }
            
            $target_tags = RPGTags::parseUserTags($row['preferences']);
            $similarity = $this->calculateTagSimilarity($user_preferences, $target_tags);
            
            // Filtrar por similaridade mínima
            if ($similarity < $min_similarity) {
                continue;
            }
            
            $matches[] = [
                'user' => $row,
                'distance' => round($distance, 1),
                'similarity' => round($similarity * 100, 1),
                'tags' => $target_tags
            ];
        }
        
        // Ordenar resultados
        switch ($sort_by) {
            case 'similarity':
                usort($matches, function($a, $b) {
                    return $b['similarity'] <=> $a['similarity'];
                });
                break;
            case 'distance':
            default:
                usort($matches, function($a, $b) {
                    return $a['distance'] <=> $b['distance'];
                });
                break;
        }
        
        return $matches;
    }
    
    public function sendFriendRequest($target_user_id) {
        // Verificar se já existe uma solicitação
        $check_sql = "SELECT id FROM friends WHERE 
                      (user_id = ? AND friend_id = ?) OR 
                      (user_id = ? AND friend_id = ?)";
        $check_stmt = $this->mysqli->prepare($check_sql);
        $check_stmt->bind_param("iiii", $this->user_id, $target_user_id, $target_user_id, $this->user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            return ['success' => false, 'message' => 'Já existe uma solicitação de amizade com este usuário.'];
        }
        
        // Criar nova solicitação
        $insert_sql = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
        $insert_stmt = $this->mysqli->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $this->user_id, $target_user_id);
        
        if ($insert_stmt->execute()) {
            return ['success' => true, 'message' => 'Solicitação de amizade enviada!'];
        } else {
            return ['success' => false, 'message' => 'Erro ao enviar solicitação de amizade.'];
        }
    }
}

// Instanciar a classe
$matchmaker = new Matchmaker($mysqli, $user_id);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_friend_request':
                $target_user_id = intval($_POST['target_user_id']);
                $response = $matchmaker->sendFriendRequest($target_user_id);
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
                break;
        }
    }
}

// Processar filtros de busca
$search_tags = isset($_GET['tags']) ? explode(',', $_GET['tags']) : [];
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'distance';
$min_similarity = isset($_GET['min_similarity']) ? floatval($_GET['min_similarity']) / 100 : 0;

// Buscar matches
$matches = $matchmaker->findMatches($search_tags, $sort_by, $min_similarity);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matchmaker - Encontrar Companheiros de RPG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .matchmaker-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            text-align: center;
        }
        
        .match-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .match-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .distance-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            font-weight: bold;
        }
        
        .similarity-badge {
            background: linear-gradient(45deg, #007bff, #6f42c1);
            color: white;
            font-weight: bold;
        }
        
        .filters-panel {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-send-request {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border: none;
            color: white;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-send-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(238,90,36,0.4);
            color: white;
        }
        
        .no-matches {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-matches i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="matchmaker-header">
        <div class="container">
            <h1><i class="bi bi-people-fill"></i> Matchmaker RPG</h1>
            <p class="lead">Encontre companheiros de RPG próximos a você com interesses similares</p>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Filtros de Busca -->
        <div class="filters-panel">
            <h5><i class="bi bi-funnel"></i> Filtros de Busca</h5>
            <form method="GET" id="filtersForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Ordenar por:</label>
                        <select name="sort" class="form-select">
                            <option value="distance" <?= $sort_by === 'distance' ? 'selected' : '' ?>>Distância</option>
                            <option value="similarity" <?= $sort_by === 'similarity' ? 'selected' : '' ?>>Similaridade</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Similaridade mínima:</label>
                        <select name="min_similarity" class="form-select">
                            <option value="0" <?= $min_similarity == 0 ? 'selected' : '' ?>>Qualquer</option>
                            <option value="20" <?= $min_similarity == 0.2 ? 'selected' : '' ?>>20%+</option>
                            <option value="40" <?= $min_similarity == 0.4 ? 'selected' : '' ?>>40%+</option>
                            <option value="60" <?= $min_similarity == 0.6 ? 'selected' : '' ?>>60%+</option>
                            <option value="80" <?= $min_similarity == 0.8 ? 'selected' : '' ?>>80%+</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Buscar por tags:</label>
                        <input type="text" name="tags" class="form-control" 
                               value="<?= htmlspecialchars(implode(',', $search_tags)) ?>"
                               placeholder="Ex: D&D 5e, Horror, Online">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpar Filtros
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <?php if (isset($matches['error'])): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= $matches['error'] ?>
                <hr>
                <p class="mb-0">
                    <a href="../Location/" class="btn btn-primary">
                        <i class="bi bi-geo-alt"></i> Definir Localização
                    </a>
                </p>
            </div>
        <?php elseif (empty($matches)): ?>
            <div class="no-matches">
                <i class="bi bi-search"></i>
                <h3>Nenhum match encontrado</h3>
                <p>Tente ajustar os filtros ou verificar se há outros usuários na sua região.</p>
                <a href="?" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                </a>
            </div>
        <?php else: ?>
            <!-- Estatísticas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <h3><?= count($matches) ?></h3>
                        <p class="mb-0">Matches Encontrados</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h3><?= $max_distance ?> km</h3>
                        <p class="mb-0">Raio de Busca</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h3><?= $min_similarity * 100 ?>%</h3>
                        <p class="mb-0">Similaridade Mínima</p>
                    </div>
                </div>
            </div>

            <!-- Resultados -->
            <div class="row">
                <?php foreach ($matches as $match): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card match-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?= $match['user']['image'] ? '../../' . $match['user']['image'] : '../../assets/default-avatar.png' ?>" 
                                         alt="Avatar" class="match-avatar me-3">
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($match['user']['name']) ?></h5>
                                        <p class="text-muted mb-0">@<?= htmlspecialchars($match['user']['username']) ?></p>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($match['user']['gender']) ?>
                                            <?php if ($match['user']['pronouns']): ?>
                                                | <?= htmlspecialchars($match['user']['pronouns']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge distance-badge me-2">
                                        <i class="bi bi-geo-alt"></i> <?= $match['distance'] ?> km
                                    </span>
                                    <span class="badge similarity-badge">
                                        <i class="bi bi-heart"></i> <?= $match['similarity'] ?>% compatível
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-geo"></i> <?= htmlspecialchars($match['user']['city']) ?>, <?= htmlspecialchars($match['user']['state']) ?>
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="mb-2">Preferências:</h6>
                                    <div class="tags-container">
                                        <?php if (!empty($match['tags'])): ?>
                                            <?php foreach (array_slice($match['tags'], 0, 3) as $tag): ?>
                                                <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($tag) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($match['tags']) > 3): ?>
                                                <span class="badge bg-light text-dark">+<?= count($match['tags']) - 3 ?> mais</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nenhuma preferência definida</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <button class="btn btn-send-request w-100" 
                                        onclick="sendFriendRequest(<?= $match['user']['id'] ?>, this)">
                                    <i class="bi bi-person-plus"></i> Enviar Solicitação de Amizade
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <p class="mt-2">Enviando solicitação...</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function sendFriendRequest(targetUserId, button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';
            button.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'send_friend_request');
            formData.append('target_user_id', targetUserId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '<i class="bi bi-check-circle"></i> Solicitação Enviada!';
                    button.classList.remove('btn-send-request');
                    button.classList.add('btn-success');
                    
                    // Mostrar mensagem de sucesso
                    showAlert(data.message, 'success');
                } else {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                button.innerHTML = originalText;
                button.disabled = false;
                showAlert('Erro ao enviar solicitação. Tente novamente.', 'danger');
            });
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.insertBefore(alertDiv, document.body.firstChild);
            
            // Auto-remover após 5 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Auto-submit do formulário quando mudanças são feitas
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filtersForm');
            const selects = form.querySelectorAll('select');
            
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    form.submit();
                });
            });
        });
    </script>
</body>
</html>