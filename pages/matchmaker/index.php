<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Tags/index.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/Login/');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = false;

// Verificar admin
$query = "SELECT admin FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $is_admin = $row['admin'] == 1;
}
$stmt->close();

class SimpleMatchmaker {
    private $mysqli;
    private $user_id;
    private $max_distance = 50;
    private $rejected_users = [];
    
    public function __construct($mysqli, $user_id) {
        $this->mysqli = $mysqli;
        $this->user_id = $user_id;
        $this->loadRejectedUsers();
    }

    private function loadRejectedUsers() {
        if (!isset($_SESSION['rejected_users'])) {
            $_SESSION['rejected_users'] = [];
        }
        
        // Limpar rejeitados antigos (mais de 24 horas)
        $current_time = time();
        foreach ($_SESSION['rejected_users'] as $user_id => $timestamp) {
            if ($current_time - $timestamp > 86400) { // 24 horas = 86400 segundos
                unset($_SESSION['rejected_users'][$user_id]);
            }
        }
        $this->rejected_users = array_keys($_SESSION['rejected_users']);
    }

    public function rejectUser($target_user_id) {
        $_SESSION['rejected_users'][$target_user_id] = time();
        $this->rejected_users[] = $target_user_id;
        
        return ['success' => true, 'message' => 'Usuário removido da lista temporariamente.'];
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
    
    public function getExcludedUsers() {
        $excluded = [$this->user_id];
        
        // Adicionar usuários rejeitados temporariamente
        $excluded = array_merge($excluded, $this->rejected_users);
        
        // Amigos aceitos
        $sql1 = "SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'
                 UNION
                 SELECT user_id FROM friends WHERE friend_id = ? AND status = 'accepted'";
        $stmt1 = $this->mysqli->prepare($sql1);
        $stmt1->bind_param("ii", $this->user_id, $this->user_id);
        $stmt1->execute();
        $result1 = $stmt1->get_result();
        
        while ($row = $result1->fetch_assoc()) {
            $excluded[] = array_values($row)[0];
        }
        
        // Solicitações pendentes
        $sql2 = "SELECT friend_id FROM friends WHERE user_id = ? AND status = 'pending'
                 UNION
                 SELECT user_id FROM friends WHERE friend_id = ? AND status = 'pending'";
        $stmt2 = $this->mysqli->prepare($sql2);
        $stmt2->bind_param("ii", $this->user_id, $this->user_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        while ($row = $result2->fetch_assoc()) {
            $excluded[] = array_values($row)[0];
        }
        
        return array_unique($excluded);
    }
    
    public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371;
        
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
        return count($intersection);
    }
    
    public function getNextMatch() {
        $current_location = $this->getCurrentUserLocation();
        
        if (!$current_location) {
            return ['error' => 'Você precisa definir sua localização primeiro!'];
        }
        
        $user_preferences = $this->getCurrentUserPreferences();
        $excluded_users = $this->getExcludedUsers();
        
        $sql = "SELECT u.id, u.username, u.name, u.gender, u.pronouns, u.preferences, u.image,
                       ul.latitude, ul.longitude, ul.city, ul.state
                FROM users u
                JOIN user_locations ul ON u.id = ul.user_id
                WHERE u.id NOT IN (" . str_repeat('?,', count($excluded_users) - 1) . "?)
                ORDER BY RAND()
                LIMIT 1";
        
        $stmt = $this->mysqli->prepare($sql);
        $types = str_repeat('i', count($excluded_users));
        $stmt->bind_param($types, ...$excluded_users);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $distance = $this->calculateDistance(
                $current_location['latitude'],
                $current_location['longitude'],
                $row['latitude'],
                $row['longitude']
            );
            
            if ($distance <= $this->max_distance) {
                $target_tags = RPGTags::parseUserTags($row['preferences']);
                $common_tags = $this->calculateTagSimilarity($user_preferences, $target_tags);
                
                return [
                    'user' => $row,
                    'distance' => round($distance, 1),
                    'common_tags' => $common_tags,
                    'tags' => $target_tags
                ];
            }
        }
        
        return null;
    }
    
    public function sendFriendRequest($target_user_id) {
        $check_sql = "SELECT id FROM friends WHERE 
                      (user_id = ? AND friend_id = ?) OR 
                      (user_id = ? AND friend_id = ?)";
        $check_stmt = $this->mysqli->prepare($check_sql);
        $check_stmt->bind_param("iiii", $this->user_id, $target_user_id, $target_user_id, $this->user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            return ['success' => false, 'message' => 'Já existe uma solicitação com este usuário.'];
        }
        
        $insert_sql = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
        $insert_stmt = $this->mysqli->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $this->user_id, $target_user_id);
        
        if ($insert_stmt->execute()) {
            return ['success' => true, 'message' => 'Solicitação enviada!'];
        } else {
            return ['success' => false, 'message' => 'Erro ao enviar solicitação.'];
        }
    }
}



function getProfileImageUrl($image_data) {
    if (empty($image_data)) {
        return '../../assets/default-avatar.png';
    }
    
    if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $image_data)) {
        return 'data:image/jpeg;base64,' . $image_data;
    } else {
        return '../../uploads/profiles/' . $image_data;
    }
}

$matchmaker = new SimpleMatchmaker($mysqli, $user_id);

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'get_next_match':
                $match = $matchmaker->getNextMatch();
                echo json_encode($match);
                exit();
                
            case 'send_friend_request':
                $target_user_id = intval($_POST['target_user_id']);
                $response = $matchmaker->sendFriendRequest($target_user_id);
                echo json_encode($response);
                exit();
                case 'reject_user':
                    $target_user_id = intval($_POST['target_user_id']);
                    $response = $matchmaker->rejectUser($target_user_id);
                    echo json_encode($response);
                    exit();
        
        }
    }
}

// Buscar primeiro match para carregamento inicial
$initial_match = $matchmaker->getNextMatch();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matchmaker - CritMeet</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .matchmaker-container {
            max-width: 400px;
            margin: 2rem auto;
            padding: 1rem;
        }
        
        .match-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .match-card.swipe-left {
            transform: translateX(-100%) rotate(-30deg);
            opacity: 0;
        }
        
        .match-card.swipe-right {
            transform: translateX(100%) rotate(30deg);
            opacity: 0;
        }
        
        .match-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        
        .match-info {
            padding: 1.5rem;
        }
        
        .match-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .match-details {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .common-tags {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .tag-badge {
            display: inline-block;
            background: #2196f3;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin: 0.1rem;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 2rem;
            padding: 1rem;
        }
        
        .btn-pass {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #f44336;
            border: none;
            color: white;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        
        .btn-like {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #4caf50;
            border: none;
            color: white;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        
        .btn-pass:hover, .btn-like:hover {
            transform: scale(1.1);
        }
        
        .no-matches {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

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
                <li class="nav-item"><a class="nav-link active" href="../matchmaker/">Matchmaker</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Mais...</a>
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

<div class="container">
    <div class="matchmaker-container">
        <div class="text-center mb-3">
            <h2><i class="bi bi-people-fill"></i> Encontrar Companheiros</h2>
            <p class="text-muted">Descubra jogadores próximos a você</p>
        </div>
        
        <div id="matchCard">
            <?php if (isset($initial_match['error'])): ?>
                <div class="alert alert-warning text-center">
                    <i class="bi bi-geo-alt"></i><br>
                    <?php echo $initial_match['error']; ?>
                    <br><br>
                    <a href="../settings/" class="btn btn-primary">Definir Localização</a>
                </div>
            <?php elseif ($initial_match): ?>
                <div class="match-card" id="currentMatch" data-user-id="<?php echo $initial_match['user']['id']; ?>">
                    <img src="<?php echo getProfileImageUrl($initial_match['user']['image']); ?>" 
                         alt="Foto de perfil" class="match-image">
                    
                    <div class="match-info">
                        <div class="match-name">
                            <?php echo htmlspecialchars($initial_match['user']['name'] ?: $initial_match['user']['username']); ?>
                        </div>
                        
                        <div class="match-details">
                            <div><i class="bi bi-geo-alt"></i> <?php echo $initial_match['distance']; ?>km de distância</div>
                            <div><i class="bi bi-person"></i> <?php echo htmlspecialchars($initial_match['user']['gender']); ?>
                                <?php if ($initial_match['user']['pronouns']): ?>
                                    | <?php echo htmlspecialchars($initial_match['user']['pronouns']); ?>
                                <?php endif; ?>
                            </div>
                            <div><i class="bi bi-geo"></i> <?php echo htmlspecialchars($initial_match['user']['city']); ?>, <?php echo htmlspecialchars($initial_match['user']['state']); ?></div>
                        </div>
                        
                        <?php if (!empty($initial_match['tags'])): ?>
                            <div class="common-tags">
                                <small class="text-muted">
                                    <i class="bi bi-controller"></i> 
                                    <?php echo $initial_match['common_tags']; ?> preferências em comum:
                                </small>
                                <div class="mt-2">
                                    <?php foreach (array_slice($initial_match['tags'], 0, 5) as $tag): ?>
                                        <span class="tag-badge"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($initial_match['tags']) > 5): ?>
                                        <span class="tag-badge bg-secondary">+<?php echo count($initial_match['tags']) - 5; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- BOTÕES PARA O MATCH INICIAL -->
                <div class="action-buttons">
                    <button class="btn-pass" onclick="passUser()" title="Passar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button class="btn-like" onclick="likeUser()" title="Enviar Solicitação">
                        <i class="bi bi-heart-fill"></i>
                    </button>
                </div>
                
            <?php else: ?>
                <div class="no-matches">
                    <i class="bi bi-search" style="font-size: 3rem; opacity: 0.5;"></i>
                    <h4>Nenhum match encontrado</h4>
                    <p>Não há mais usuários na sua região no momento.</p>
                    <button class="btn btn-primary" onclick="loadNextMatch()">
                        <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="loading" id="loading" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Carregando...</span>
    </div>
    <p>Buscando próximo match...</p>
</div>

<script>
let isLoading = false;

function showLoading() {
    document.getElementById('loading').style.display = 'block';
    isLoading = true;
}

function hideLoading() {
    document.getElementById('loading').style.display = 'none';
    isLoading = false;
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 4000);
}

function loadNextMatch() {
    if (isLoading) return;
    
    showLoading();
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_next_match'
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data && data.error) {
            document.getElementById('matchCard').innerHTML = `
                <div class="alert alert-warning text-center">
                    <i class="bi bi-geo-alt"></i><br>
                    ${data.error}
                    <br><br>
                    <a href="../settings/" class="btn btn-primary">Definir Localização</a>
                </div>
            `;
        } else if (data) {
            displayMatch(data);
        } else {
            document.getElementById('matchCard').innerHTML = `
                <div class="no-matches">
                    <i class="bi bi-search" style="font-size: 3rem; opacity: 0.5;"></i>
                    <h4>Nenhum match encontrado</h4>
                    <p>Não há mais usuários na sua região no momento.</p>
                    <button class="btn btn-primary" onclick="loadNextMatch()">
                        <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                    </button>
                </div>
            `;
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showAlert('Erro ao carregar próximo match', 'danger');
    });
}

function displayMatch(match) {
    const imageUrl = match.user.image ? 
        (match.user.image.includes('data:image') ? match.user.image : `../../uploads/profiles/${match.user.image}`) :
        '../../assets/default-avatar.png';
    
    let tagsHtml = '';
    if (match.tags && match.tags.length > 0) {
        const visibleTags = match.tags.slice(0, 5);
        const remainingCount = match.tags.length - 5;
        
        tagsHtml = `
            <div class="common-tags">
                <small class="text-muted">
                    <i class="bi bi-controller"></i> 
                    ${match.common_tags} preferências em comum:
                </small>
                <div class="mt-2">
                    ${visibleTags.map(tag => `<span class="tag-badge">${tag}</span>`).join('')}
                    ${remainingCount > 0 ? `<span class="tag-badge bg-secondary">+${remainingCount}</span>` : ''}
                </div>
            </div>
        `;
    }
    
    document.getElementById('matchCard').innerHTML = `
        <div class="match-card" id="currentMatch" data-user-id="${match.user.id}">
            <!-- conteúdo do card -->
        </div>
        
        <!-- OS BOTÕES DEVEM ESTAR AQUI DENTRO -->
        <div class="action-buttons">
            <button class="btn-pass" onclick="passUser()" title="Passar">
                <i class="bi bi-x-lg"></i>
            </button>
            <button class="btn-like" onclick="likeUser()" title="Enviar Solicitação">
                <i class="bi bi-heart-fill"></i>
            </button>
        </div>
    `;
}

function passUser() {
    if (isLoading) return;
    
    const matchCard = document.getElementById('currentMatch');
    if (!matchCard) return;
    
    const userId = matchCard.dataset.userId;
    
    // Adicionar usuário à lista de rejeitados
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=reject_user&target_user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            matchCard.classList.add('swipe-left');
            setTimeout(() => {
                loadNextMatch();
            }, 300);
        } else {
            console.error('Erro ao rejeitar usuário:', data.message);
            // Mesmo com erro, continue com o próximo match
            matchCard.classList.add('swipe-left');
            setTimeout(() => {
                loadNextMatch();
            }, 300);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Mesmo com erro, continue com o próximo match
        matchCard.classList.add('swipe-left');
        setTimeout(() => {
            loadNextMatch();
        }, 300);
    });
}

function likeUser() {
    if (isLoading) return;
    
    const matchCard = document.getElementById('currentMatch');
    if (!matchCard) return;
    
    const userId = matchCard.dataset.userId;
    
    showLoading();
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=send_friend_request&target_user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            matchCard.classList.add('swipe-right');
            showAlert(data.message, 'success');
            setTimeout(() => {
                loadNextMatch();
            }, 300);
        } else {
            showAlert(data.message, 'warning');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showAlert('Erro ao enviar solicitação', 'danger');
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (isLoading) return;
    
    if (e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'A') {
        passUser();
    } else if (e.key === 'ArrowRight' || e.key === 'd' || e.key === 'D') {
        likeUser();
    }
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>