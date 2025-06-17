<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Tags/index.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;
$user = null;

// Criar diretório de uploads se não existir
$upload_dir = __DIR__ . '/../../uploads/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($user_id) {
    $query = "SELECT * FROM users WHERE id = ?";
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

// Função para processar upload de imagem
function processImageUpload($file, $user_id, $upload_dir) {
    if (empty($file['tmp_name'])) {
        return null;
    }
    
    // Validar tipo de arquivo
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('Tipo de arquivo não permitido. Use apenas JPEG, PNG, GIF ou WebP.');
    }
    
    // Validar tamanho (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Máximo 5MB permitido.');
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Redimensionar imagem se necessário
    $resized_image = resizeImage($file['tmp_name'], $file_type, 400, 400);
    
    if (imagejpeg($resized_image, $filepath, 85)) {
        imagedestroy($resized_image);
        return $filename;
    } else {
        throw new Exception('Erro ao salvar a imagem.');
    }
}

// Função para redimensionar imagem
function resizeImage($source_path, $mime_type, $max_width, $max_height) {
    // Criar imagem a partir do arquivo
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $source_image = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            $source_image = imagecreatefromwebp($source_path);
            break;
        default:
            throw new Exception('Tipo de imagem não suportado.');
    }
    
    $original_width = imagesx($source_image);
    $original_height = imagesy($source_image);
    
    // Verificar se as dimensões são válidas
    if ($original_width <= 0 || $original_height <= 0) {
        imagedestroy($source_image);
        throw new Exception('Imagem com dimensões inválidas.');
    }
    
    // Calcular novas dimensões mantendo proporção
    $ratio = min($max_width / $original_width, $max_height / $original_height);
    $new_width = (int) round($original_width * $ratio);
    $new_height = (int) round($original_height * $ratio);
    
    // Garantir que as dimensões são válidas (mínimo 1px)
    $new_width = max(1, $new_width);
    $new_height = max(1, $new_height);
    
    // Criar nova imagem redimensionada
    $resized_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preservar transparência para PNG e GIF
    if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);
        $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
        imagefill($resized_image, 0, 0, $transparent);
    }
    
    imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
    imagedestroy($source_image);
    
    return $resized_image;
}

// Função para deletar imagem antiga
function deleteOldImage($old_image_path) {
    if (!empty($old_image_path) && file_exists($old_image_path)) {
        unlink($old_image_path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = $_POST['name'] ?? $user['name'];
        $gender = $_POST['gender'] ?? $user['gender'];
        $pronouns = $_POST['pronouns'] ?? $user['pronouns'];
        $preferences = $_POST['preferences'] ?? '';
        
        // Processar e validar as tags selecionadas
        $preferences = RPGTags::formatUserTags(RPGTags::parseUserTags($preferences));
        
        $new_image_filename = null;
        
        // Processar upload de nova imagem
        if (!empty($_FILES['image']['tmp_name'])) {
            $new_image_filename = processImageUpload($_FILES['image'], $user_id, $upload_dir);
            
            // Deletar imagem antiga se existir e não for base64
            if (!empty($user['image']) && !preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $user['image'])) {
                deleteOldImage($upload_dir . $user['image']);
            }
        }
        
        // Atualizar banco de dados
        if ($new_image_filename) {
            $sql = "UPDATE users SET name = ?, gender = ?, pronouns = ?, preferences = ?, image = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('sssssi', $name, $gender, $pronouns, $preferences, $new_image_filename, $user_id);
        } else {
            $sql = "UPDATE users SET name = ?, gender = ?, pronouns = ?, preferences = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssi', $name, $gender, $pronouns, $preferences, $user_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "Informações do usuário atualizadas com sucesso!";
            // Recarregar dados do usuário
            $query = "SELECT * FROM users WHERE id = ?";
            $stmt2 = $mysqli->prepare($query);
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $user = $row;
            }
        } else {
            $error_message = "Erro ao atualizar as informações do usuário.";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
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
    <title>Editar Perfil</title>
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
        
        /* Estilos específicos para edição */
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
            background: #007bff;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            margin: 0.5rem 0;
            transition: background-color 0.2s ease;
            border: none;
        }
        
        .file-input-wrapper:hover {
            background: #0056b3;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .form-control {
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .alert {
            border-radius: 0.375rem;
            margin-bottom: 1rem;
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
                    <img src="<?php echo getProfileImageUrl($user['image']); ?>" 
                         alt="Imagem de Perfil" 
                         class="profile-image" 
                         id="imagePreview"
                         onerror="this.src='default-avatar.png'" />
                    
                    <h2 class="mb-2">Editar Perfil</h2>
                    <p class="text-muted mb-0">Atualize suas informações pessoais</p>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div><?php echo $success_message; ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo $error_message; ?></div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Informações do Perfil</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <table class="table table-striped profile-table mb-0">
                                <tr>
                                    <th><i class="bi bi-camera-fill"></i> Imagem de Perfil:</th>
                                    <td>
                                        <label class="file-input-wrapper">
                                            <i class="bi bi-upload"></i> Escolher Nova Imagem
                                            <input type="file" name="image" accept="image/*" id="imageInput" />
                                        </label>
                                        <div class="form-text">
                                            Formatos aceitos: JPEG, PNG, GIF, WebP (máximo 5MB)
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-person"></i> Nome Completo:</th>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="name" 
                                               placeholder="Seu nome completo" 
                                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-gender-ambiguous"></i> Gênero:</th>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="gender" 
                                               placeholder="Ex: Masculino, Feminino, Não-binário..." 
                                               value="<?php echo htmlspecialchars($user['gender'] ?? ''); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-chat-quote"></i> Pronomes:</th>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="pronouns" 
                                               placeholder="Ex: ele/dele, ela/dela, elu/delu..." 
                                               value="<?php echo htmlspecialchars($user['pronouns'] ?? ''); ?>" />
                                        <div class="form-text">
                                            Como você gostaria de ser referido/a durante as sessões?
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-controller"></i> Preferências de Jogo:</th>
                                    <td>
                                        <div class="mb-2">
                                            <?php 
                                            // Usar as preferências atuais do usuário
                                            $current_preferences = isset($user['preferences']) ? $user['preferences'] : '';
                                            RPGTags::renderTagSelector($current_preferences, 'preferences'); 
                                            ?>
                                        </div>
                                        <div class="form-text">
                                            Selecione até 5 tags que melhor representem seu estilo de jogo
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </div>

                <div class="text-center btn-edit">
                    <button type="submit" form="editForm" class="btn btn-primary btn-lg me-2">
                        <i class="bi bi-check-lg"></i> Salvar Alterações
                    </button>
                    <a href="../Profile/" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview da imagem antes do upload
document.getElementById('imageInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        // Validar tamanho do arquivo
        if (file.size > 5 * 1024 * 1024) {
            alert('Arquivo muito grande! Máximo permitido: 5MB');
            this.value = '';
            return;
        }
        
        // Validar tipo do arquivo
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Tipo de arquivo não permitido! Use apenas JPEG, PNG, GIF ou WebP.');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Adicionar ID ao formulário para o botão submit funcionar
document.querySelector('form').id = 'editForm';
</script>

<?php include 'footer.php'; ?>
</body>
</html>