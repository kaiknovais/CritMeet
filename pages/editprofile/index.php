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
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
            margin: 10px 0;
        }
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        .form-group {
            margin: 15px 0;
        }
        .form-control {
            margin: 5px 0;
        }
        .alert {
            margin: 15px 0;
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
            <div class="col-md-8 col-lg-6">
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="text-center mb-0">Editar Perfil</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <div class="text-center">
                            <img src="<?php echo getProfileImageUrl($user['image']); ?>" 
                                 alt="Imagem de Perfil" 
                                 class="image-preview" 
                                 id="imagePreview" />
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-group text-center">
                                <label class="file-input-wrapper">
                                    <i class="bi bi-camera"></i> Escolher Nova Imagem
                                    <input type="file" name="image" accept="image/*" id="imageInput" />
                                </label>
                                <div class="form-text">Formatos aceitos: JPEG, PNG, GIF, WebP (máximo 5MB)</div>
                            </div>

                            <div class="form-group">
                                <label for="name" class="form-label">Nome:</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name"
                                       name="name" 
                                       placeholder="Nome" 
                                       value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" />
                            </div>

                            <div class="form-group">
                                <label for="gender" class="form-label">Gênero:</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="gender"
                                       name="gender" 
                                       placeholder="Gênero" 
                                       value="<?php echo htmlspecialchars($user['gender'] ?? ''); ?>" />
                            </div>

                            <div class="form-group">
                                <label for="pronouns" class="form-label">Pronomes:</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="pronouns"
                                       name="pronouns" 
                                       placeholder="Ex: ele/dele, ela/dela, elu/delu" 
                                       value="<?php echo htmlspecialchars($user['pronouns'] ?? ''); ?>" />
                            </div>

                            <div class="form-group">
                                <label class="form-label">Preferências de RPG:</label>
                                <p class="form-text">Selecione até 5 tags que representem suas preferências de jogo:</p>
                                <?php 
                                // Usar as preferências atuais do usuário
                                $current_preferences = isset($user['preferences']) ? $user['preferences'] : '';
                                RPGTags::renderTagSelector($current_preferences, 'preferences'); 
                                ?>
                            </div>

                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Confirmar Alterações
                                </button>
                                <a href="../Profile/" class="btn btn-secondary ms-2">
                                    <i class="bi bi-arrow-left"></i> Voltar ao Perfil
                                </a>
                            </div>
                        </form>
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
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>