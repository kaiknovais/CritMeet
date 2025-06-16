<?php
// components/ViewAvatar/index.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Tags/index.php';

class ViewAvatar {
    private $mysqli;
    private $current_user_id;
    
    public function __construct($mysqli, $current_user_id) {
        $this->mysqli = $mysqli;
        $this->current_user_id = $current_user_id;
    }
    
    // Processar requisições AJAX
    public function handleAjaxRequest() {
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_user_data') {
            header('Content-Type: application/json');
            
            // Verificar se usuário está logado
            if (!$this->current_user_id) {
                echo json_encode(['success' => false, 'error' => 'Usuário não logado']);
                exit;
            }
            
            // Verificar se ID foi fornecido
            if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }
            
            $user_id = (int)$_GET['id'];
            
            // Não permitir visualizar próprio perfil
            if ($user_id == $this->current_user_id) {
                echo json_encode(['success' => false, 'error' => 'Não é possível visualizar próprio perfil']);
                exit;
            }
            
            $user = $this->getUserData($user_id);
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
                exit;
            }
            
            // Retornar dados do usuário
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => htmlspecialchars($user['username']),
                    'name' => htmlspecialchars($user['name'] ?? ''),
                    'gender' => htmlspecialchars($user['gender'] ?? ''),
                    'pronouns' => htmlspecialchars($user['pronouns'] ?? ''),
                    'preferences' => htmlspecialchars($user['preferences'] ?? ''),
                    'image' => $user['image'] ?? '',
                    'admin' => $user['admin']
                ]
            ]);
            exit;
        }
    }
    
    // Função para buscar dados do usuário
    private function getUserData($user_id) {
        $sql = "SELECT id, username, name, gender, pronouns, preferences, image, admin FROM users WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    // Função para exibir imagem do perfil
    private function getProfileImageUrl($image_data) {
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
    
    // Renderizar o avatar clicável
    public function renderAvatar($user_id, $size = 'medium', $classes = '') {
        $user = $this->getUserData($user_id);
        if (!$user) return '';
        
        $sizes = [
            'small' => '40px',
            'medium' => '60px',
            'large' => '80px'
        ];
        
        $avatar_size = $sizes[$size] ?? $sizes['medium'];
        $image_url = $this->getProfileImageUrl($user['image'] ?? '');
        
        echo "<img src='{$image_url}' 
                   alt='Avatar de " . htmlspecialchars($user['username']) . "' 
                   class='view-avatar clickable-avatar {$classes}' 
                   style='width: {$avatar_size}; height: {$avatar_size}; border-radius: 50%; object-fit: cover; cursor: pointer; border: 2px solid #dee2e6;'
                   data-user-id='{$user_id}' 
                   onerror=\"this.src='default-avatar.png'\" 
                   title='Clique para ver perfil' />";
    }
    
    // Gerar HTML do modal de prévia
    public function generateModalHTML() {
        ?>
        <!-- Modal de Preview do Avatar -->
        <div class="modal fade" id="avatarPreviewModal" tabindex="-1" aria-labelledby="avatarPreviewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="avatarPreviewModalLabel">
                            <i class="bi bi-person-circle"></i> Perfil
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center" id="avatarPreviewContent">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <a href="#" class="btn btn-primary" id="viewFullProfileBtn">
                            <i class="bi bi-eye"></i> Ver Perfil Completo
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .avatar-preview-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #dee2e6;
            margin-bottom: 1rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .preview-username {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .preview-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
        }
        
        .preview-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .preview-info-row:last-child {
            margin-bottom: 0;
        }
        
        .preview-label {
            font-weight: 600;
            color: #495057;
        }
        
        .preview-value {
            color: #6c757d;
        }
        
        .admin-badge-preview {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .clickable-avatar:hover {
            transform: scale(1.05);
            transition: transform 0.2s ease;
            border-color: #007bff !important;
        }
        
        .tags-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            justify-content: center;
        }
        
        .tag-preview {
            display: inline-block;
            padding: 0.2rem 0.4rem;
            background-color: #007bff;
            color: white;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Event listener para avatares clicáveis
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('clickable-avatar')) {
                    const userId = e.target.getAttribute('data-user-id');
                    if (userId) {
                        loadAvatarPreview(userId);
                    }
                }
            });
            
            // Função para carregar preview do avatar
            function loadAvatarPreview(userId) {
                const modal = new bootstrap.Modal(document.getElementById('avatarPreviewModal'));
                const content = document.getElementById('avatarPreviewContent');
                const fullProfileBtn = document.getElementById('viewFullProfileBtn');
                
                // Mostrar loading
                content.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div>';
                
                // Configurar botão de perfil completo
                fullProfileBtn.href = '../../components/ViewProfile/?id=' + userId;
                
                // Mostrar modal
                modal.show();
                
                // Fazer requisição AJAX para buscar dados (mesmo componente)
                fetch('../../components/ViewAvatar/?ajax=get_user_data&id=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayAvatarPreview(data.user);
                        } else {
                            content.innerHTML = '<div class="alert alert-danger">Erro ao carregar perfil</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        content.innerHTML = '<div class="alert alert-danger">Erro ao carregar perfil</div>';
                    });
            }
            
            // Função para exibir preview do avatar
            function displayAvatarPreview(user) {
                const content = document.getElementById('avatarPreviewContent');
                
                let imageUrl = user.image;
                if (!imageUrl) {
                    imageUrl = 'default-avatar.png';
                } else if (imageUrl.match(/^[a-zA-Z0-9\/\r\n+]*={0,2}$/)) {
                    imageUrl = 'data:image/jpeg;base64,' + imageUrl;
                } else {
                    imageUrl = '../../uploads/profiles/' + imageUrl;
                }
                
                let adminBadge = '';
                if (user.admin == 1) {
                    adminBadge = '<span class="badge bg-danger admin-badge-preview ms-2"><i class="bi bi-shield-check"></i> Admin</span>';
                }
                
                let preferences = '';
                if (user.preferences) {
                    const tags = user.preferences.split(',').slice(0, 5); // Mostrar apenas 5 tags
                    preferences = tags.map(tag => `<span class="tag-preview">${tag.trim()}</span>`).join('');
                    if (user.preferences.split(',').length > 5) {
                        preferences += '<span class="text-muted ms-2">+' + (user.preferences.split(',').length - 5) + ' mais</span>';
                    }
                } else {
                    preferences = '<span class="text-muted">Nenhuma preferência</span>';
                }
                
                content.innerHTML = `
                    <img src="${imageUrl}" alt="Avatar" class="avatar-preview-image" onerror="this.src='default-avatar.png'" />
                    <div class="preview-username">
                        ${user.username}${adminBadge}
                    </div>
                    ${user.name && user.name !== user.username ? `<div class="text-muted mb-3">${user.name}</div>` : ''}
                    
                    <div class="preview-info">
                        <div class="preview-info-row">
                            <span class="preview-label"><i class="bi bi-gender-ambiguous"></i> Gênero:</span>
                            <span class="preview-value">${user.gender || 'Não informado'}</span>
                        </div>
                        <div class="preview-info-row">
                            <span class="preview-label"><i class="bi bi-chat-quote"></i> Pronomes:</span>
                            <span class="preview-value">${user.pronouns || 'Não informado'}</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong><i class="bi bi-controller"></i> Preferências:</strong>
                        <div class="tags-preview mt-2">
                            ${preferences}
                        </div>
                    </div>
                `;
            }
        });
        </script>
        <?php
    }
}

// Processar requisições AJAX se necessário
session_start();
if (isset($_GET['ajax'])) {
    $current_user_id = $_SESSION['user_id'] ?? null;
    $avatar = new ViewAvatar($mysqli, $current_user_id);
    $avatar->handleAjaxRequest();
}

// Função auxiliar para uso rápido
function renderViewAvatar($user_id, $size = 'medium', $classes = '') {
    global $mysqli;
    $current_user_id = $_SESSION['user_id'] ?? null;
    
    if (!$current_user_id) return '';
    
    $avatar = new ViewAvatar($mysqli, $current_user_id);
    $avatar->renderAvatar($user_id, $size, $classes);
}

// Função para incluir o modal (chamar uma vez por página)
function includeViewAvatarModal() {
    global $mysqli;
    $current_user_id = $_SESSION['user_id'] ?? null;
    
    if (!$current_user_id) return '';
    
    $avatar = new ViewAvatar($mysqli, $current_user_id);
    $avatar->generateModalHTML();
}
?>