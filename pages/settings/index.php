<?php
require_once __DIR__ . '/../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null;

// Verifica no banco de dados se o usuário é admin e busca dados do usuário
if ($user_id) {
    $query = "SELECT username, name, image, admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $user = $row;
        $is_admin = $row['admin'] == 1;
    }
    $stmt->close();
}

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
    <title>Configuração</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
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
    </style>
    <script>
        function confirmDelete() {
            const confirmation = confirm("Tem certeza que deseja deletar sua conta?");
            if (confirmation) {
                const accountName = prompt("Por favor, insira o nome da sua conta para confirmar a exclusão:");
                if (accountName) {
                    window.location.href = "../../components/Delete/";
                } else {
                    alert("Nome da conta não pode ser vazio.");
                }
            }
        }
    </script>
</head>

<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    <div>
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

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">
                <i class="bi bi-gear"></i> Configurações
            </h2>
        </div>
    </div>
    
    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-person-gear display-4 text-primary mb-3"></i>
                    <h5 class="card-title">Editar Perfil</h5>
                    <p class="card-text">Altere suas informações pessoais, foto e preferências</p>
                    <a href="../editprofile/" class="btn btn-primary">
                        <i class="bi bi-pencil-square"></i> Editar
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-bell display-4 text-info mb-3"></i>
                    <h5 class="card-title">Notificações</h5>
                    <p class="card-text">Configure suas preferências de notificações</p>
                    <button type="button" class="btn btn-info" data-bs-toggle="collapse" data-bs-target="#notificacoes">
                        <i class="bi bi-gear"></i> Configurar
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-shield-lock display-4 text-warning mb-3"></i>
                    <h5 class="card-title">Segurança</h5>
                    <p class="card-text">Altere sua senha e configurações de segurança</p>
                    <a href="../changepassword" class="btn btn-warning">
                        <i class="bi bi-key"></i> Configurar
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-question-circle display-4 text-success mb-3"></i>
                    <h5 class="card-title">Suporte e Ajuda</h5>
                    <p class="card-text">Encontre respostas para suas dúvidas</p>
                    <button type="button" class="btn btn-success">
                        <i class="bi bi-life-preserver"></i> Acessar
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-danger">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle display-4 text-danger mb-3"></i>
                    <h5 class="card-title text-danger">Zona de Perigo</h5>
                    <p class="card-text">Ações irreversíveis da conta</p>
                    <form method="post" action="../../components/Delete/">
                        <button type="submit" name="delete_user" class="btn btn-outline-danger">
                            <i class="bi bi-trash"></i> Excluir Conta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção colapsável de notificações -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="collapse" id="notificacoes">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-bell"></i> Configurações de Notificações</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notifMensagens" checked>
                            <label class="form-check-label" for="notifMensagens">
                                Notificações de mensagens
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notifAmizades" checked>
                            <label class="form-check-label" for="notifAmizades">
                                Solicitações de amizade
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notifRPG">
                            <label class="form-check-label" for="notifRPG">
                                Convites para RPG
                            </label>
                        </div>
                        <button type="button" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salvar Configurações
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

</body>

</html>