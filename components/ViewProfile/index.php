<?php
// components/ViewProfile/index.php - Versão corrigida
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Tags/index.php';
session_start();

$current_user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$current_user = null;
$target_user = null;
$friendship_status = null;

// Verificar se o usuário atual está logado
if (!$current_user_id) {
    header('Location: ../../pages/Login/');
    exit;
}

// Buscar dados do usuário atual para verificar admin
$query = "SELECT admin FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $is_admin = $row['admin'] == 1;
} else {
    header('Location: ../../pages/Login/');
    exit;
}
$stmt->close();

// Verificar se ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../../pages/homepage/');
    exit;
}

$user_id = (int)$_GET['id'];

// Verificar se está tentando ver o próprio perfil
if ($user_id == $current_user_id) {
    header('Location: ../../pages/Profile/');
    exit;
}

// Buscar dados do usuário alvo
$sql = "SELECT id, username, name, gender, pronouns, preferences, image, admin FROM users WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: ../../pages/homepage/');
    exit;
}

$target_user = $result->fetch_assoc();
$target_is_admin = $target_user['admin'] == 1;
$stmt->close();

// Verificar status de amizade
$friendship_query = "SELECT status FROM friends WHERE 
                    (user_id = ? AND friend_id = ?) OR 
                    (user_id = ? AND friend_id = ?)";
$stmt = $mysqli->prepare($friendship_query);
$stmt->bind_param("iiii", $current_user_id, $user_id, $user_id, $current_user_id);
$stmt->execute();
$friendship_result = $stmt->get_result();

if ($friendship_result->num_rows > 0) {
    $friendship_row = $friendship_result->fetch_assoc();
    $friendship_status = $friendship_row['status']; // 'pending' or 'accepted'
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
    <title>Perfil de <?php echo htmlspecialchars($target_user['username']); ?></title>
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
        .btn-action {
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
        .back-btn {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="profile-container">
                <!-- Botão Voltar -->
                <div class="back-btn">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>

                <div class="profile-header">
                    <img src="<?php echo getProfileImageUrl($target_user['image'] ?? ''); ?>" 
                         alt="Imagem de Perfil" 
                         class="profile-image" 
                         onerror="this.src='default-avatar.png'" />
                    
                    <h2 class="mb-2">
                        <?php echo htmlspecialchars($target_user['username'] ?? 'Usuário'); ?>
                        <?php if ($target_is_admin): ?>
                            <span class="badge bg-danger admin-badge ms-2">
                                <i class="bi bi-shield-check"></i> Admin
                            </span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (!empty($target_user['name']) && $target_user['name'] !== $target_user['username']): ?>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($target_user['name']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-person-circle"></i> Informações do Perfil</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped profile-table mb-0">
                            <tr>
                                <th><i class="bi bi-gender-ambiguous"></i> Gênero:</th>
                                <td><?php echo !empty($target_user['gender']) ? htmlspecialchars($target_user['gender']) : '<span class="text-muted">Não informado</span>'; ?></td>
                            </tr>
                            <tr>
                                <th><i class="bi bi-chat-quote"></i> Pronomes:</th>
                                <td><?php echo !empty($target_user['pronouns']) ? htmlspecialchars($target_user['pronouns']) : '<span class="text-muted">Não informado</span>'; ?></td>
                            </tr>
                            <tr>
                                <th><i class="bi bi-controller"></i> Preferências de Jogo:</th>
                                <td>
                                    <div class="tags-container">
                                        <?php 
                                        if (!empty($target_user['preferences'])) {
                                            RPGTags::renderTagsDisplay($target_user['preferences'], 10);
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

                <div class="text-center btn-action">
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <!-- Botão Enviar Mensagem - CORRIGIDO -->
                        <a href="../message/?friend_id=<?php echo $target_user['id']; ?>" class="btn btn-success">
                            <i class="bi bi-chat-dots"></i> Enviar Mensagem
                        </a>
                        
                        <?php if ($friendship_status === null): ?>
                            <!-- Não são amigos, mostrar botão de enviar solicitação -->
                            <a href="../friends/?action=add&user=<?php echo $target_user['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Enviar Solicitação de Amizade
                            </a>
                        <?php endif; ?>
                        
                        <!-- Botão de denúncia - CORRIGIDO -->
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="showReportModal(<?php echo $target_user['id']; ?>, '<?php echo htmlspecialchars($target_user['username'], ENT_QUOTES); ?>')">
                            <i class="bi bi-flag"></i> Denunciar
                        </button>
                        
                        <?php if ($is_admin): ?>
                            <a href="../admin/?action=edit&user=<?php echo $target_user['id']; ?>" class="btn btn-warning">
                                <i class="bi bi-gear"></i> Gerenciar
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($friendship_status === 'pending'): ?>
                        <!-- Mensagem para solicitação pendente -->
                        <div class="mt-3">
                            <p class="text-muted mb-0">
                                <i class="bi bi-clock"></i> Solicitação pendente
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Denúncia -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalLabel">
                    <i class="bi bi-flag"></i> Denunciar Usuário
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="reportAlert"></div>
                <p class="text-muted mb-3">
                    Descreva o motivo da denúncia. Esta informação será analisada pela equipe de moderação.
                </p>
                <form id="reportForm">
                    <input type="hidden" id="reportedUserId" name="reported_id">
                    <div class="mb-3">
                        <label for="reportReason" class="form-label">Motivo da denúncia:</label>
                        <textarea 
                            class="form-control" 
                            id="reportReason" 
                            name="reason" 
                            rows="4" 
                            placeholder="Descreva detalhadamente o motivo da denúncia (mínimo 10 caracteres)"
                            maxlength="1000"
                            required
                        ></textarea>
                        <div class="form-text">
                            <span id="charCount">0</span>/1000 caracteres
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="submitReport" disabled>
                    <i class="bi bi-flag"></i> Enviar Denúncia
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript do Modal de Denúncia -->
<script>
class ReportModal {
    constructor() {
        this.modal = null;
        this.init();
    }

    init() {
        this.modal = new bootstrap.Modal(document.getElementById('reportModal'));
        this.bindEvents();
    }

    bindEvents() {
        const reasonTextarea = document.getElementById('reportReason');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('submitReport');
        
        // Contador de caracteres
        reasonTextarea.addEventListener('input', () => {
            const length = reasonTextarea.value.length;
            charCount.textContent = length;
            
            // Habilitar/desabilitar botão baseado no comprimento
            submitBtn.disabled = length < 10;
            
            // Mudar cor baseado no comprimento
            if (length < 10) {
                charCount.className = 'text-danger';
            } else if (length > 900) {
                charCount.className = 'text-warning';
            } else {
                charCount.className = 'text-success';
            }
        });

        // Enviar denúncia
        submitBtn.addEventListener('click', () => {
            this.submitReport();
        });

        // Limpar formulário quando modal fechar
        document.getElementById('reportModal').addEventListener('hidden.bs.modal', () => {
            this.clearForm();
        });
    }

    show(userId, username = '') {
        document.getElementById('reportedUserId').value = userId;
        
        // Atualizar título com o nome do usuário se fornecido
        if (username) {
            document.getElementById('reportModalLabel').innerHTML = 
                `<i class="bi bi-flag"></i> Denunciar ${username}`;
        }
        
        this.clearForm();
        this.modal.show();
    }

    clearForm() {
        document.getElementById('reportForm').reset();
        document.getElementById('charCount').textContent = '0';
        document.getElementById('charCount').className = '';
        document.getElementById('submitReport').disabled = true;
        this.clearAlert();
    }

    showAlert(message, type = 'danger') {
        const alertDiv = document.getElementById('reportAlert');
        alertDiv.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
    }

    clearAlert() {
        document.getElementById('reportAlert').innerHTML = '';
    }

    async submitReport() {
        const submitBtn = document.getElementById('submitReport');
        const formData = {
            reported_id: parseInt(document.getElementById('reportedUserId').value),
            reason: document.getElementById('reportReason').value.trim()
        };

        // Validação do lado cliente
        if (!formData.reported_id || formData.reported_id <= 0) {
            this.showAlert('ID do usuário inválido');
            return;
        }

        if (formData.reason.length < 10) {
            this.showAlert('O motivo deve ter pelo menos 10 caracteres');
            return;
        }

        // Desabilitar botão durante envio
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';

        try {
            const response = await fetch('../../components/Report/', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Denúncia enviada com sucesso! A equipe de moderação irá analisar.', 'success');
                
                // Fechar modal após 2 segundos
                setTimeout(() => {
                    this.modal.hide();
                }, 2000);
            } else {
                this.showAlert(result.message || 'Erro ao enviar denúncia');
            }
        } catch (error) {
            console.error('Erro:', error);
            this.showAlert('Erro de conexão. Tente novamente.');
        } finally {
            // Reabilitar botão
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-flag"></i> Enviar Denúncia';
        }
    }
}

// Função global para facilitar o uso
function showReportModal(userId, username = '') {
    if (!window.reportModal) {
        window.reportModal = new ReportModal();
    }
    window.reportModal.show(userId, username);
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    window.reportModal = new ReportModal();
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>