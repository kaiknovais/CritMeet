<?php
// pages/Profile/index.php - Versão corrigida
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Tags/index.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null; 

// Verificar se o usuário está logado
if (!$user_id) {
    header('Location: ../../pages/Login/');
    exit;
}

// Buscar dados do usuário
$query = "SELECT username, name, image, gender, pronouns, preferences, admin FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $user = $row;
    $is_admin = $row['admin'] == 1; 
} else {
    // Se não encontrar o usuário, redirecionar para login
    header('Location: ../../pages/Login/');
    exit;
}
$stmt->close();

// Função para exibir imagem do perfil
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
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil de Usuário</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .profile-image {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #dee2e6;
            margin-bottom: 1rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            color: black;
        }
        .profile-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profile-table th {
            background: #f8f9fa;
            font-weight: 600;
            width: 30%;
            padding: 1rem;
            border: none;
        }
        .profile-table td {
            padding: 1rem;
            border: none;
            word-wrap: break-word;
        }
        .profile-table tr:not(:last-child) {
            border-bottom: 1px solid #dee2e6;
        }
        .btn-edit {
            margin-top: 1rem;
        }
        .admin-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: flex-start;
        }
        .preference-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            margin: 0.1rem;
            background-color: #007bff;
            color: white;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .no-preferences {
            color: #6c757d;
            font-style: italic;
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
                        <li><a class="dropdown-item active" href="../settings/">
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

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="profile-container">
                <div class="profile-header">
                    <img src="<?php echo getProfileImageUrl($user['image'] ?? ''); ?>" 
                         alt="Imagem de Perfil" 
                         class="profile-image" 
                         onerror="this.src='default-avatar.png'" />
                    
                    <h2 class="mb-2">
                        <?php echo htmlspecialchars($user['username'] ?? 'Usuário'); ?>
                        <?php if ($is_admin): ?>
                            <span class="badge bg-danger admin-badge ms-2">
                                <i class="bi bi-shield-check"></i> Admin
                            </span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (!empty($user['name']) && $user['name'] !== $user['username']): ?>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($user['name']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-circle"></i> Informações do Perfil</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped profile-table mb-0">
                            <tr>
                                <th><i class="bi bi-gender-ambiguous"></i> Gênero:</th>
                                <td><?php echo !empty($user['gender']) ? htmlspecialchars($user['gender']) : '<span class="text-muted">Não informado</span>'; ?></td>
                            </tr>
                            <tr>
                                <th><i class="bi bi-chat-quote"></i> Pronomes:</th>
                                <td><?php echo !empty($user['pronouns']) ? htmlspecialchars($user['pronouns']) : '<span class="text-muted">Não informado</span>'; ?></td>
                            </tr>
                            <tr>
                                <th><i class="bi bi-controller"></i> Preferências de Jogo:</th>
                                <td>
                                    <div class="tags-container">
                                        <?php 
                                        if (!empty($user['preferences'])) {
                                            RPGTags::renderTagsDisplay($user['preferences'], 10);
                                        } else {
                                            echo '<span class="no-preferences">Nenhuma preferência informada</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="text-center btn-edit">
                    <a href="../editprofile/" class="btn btn-primary btn-lg">
                        <i class="bi bi-pencil-square"></i> Editar Perfil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>