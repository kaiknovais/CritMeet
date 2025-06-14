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
    <title>Editar Perfil - CritMeet</title>
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .image-preview:hover {
            transform: scale(1.05);
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 10px 0;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,123,255,0.3);
        }
        
        .file-input-wrapper:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.4);
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .form-group {
            margin: 20px 0;
        }
        
        .form-control {
            margin: 5px 0;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .alert {
            margin: 15px 0;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 20px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,123,255,0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.4);
        }
        
        .btn-secondary {
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
        }
        
        .preferences-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            border: 1px solid #dee2e6;
        }
        
        .preferences-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 5px;
        }
        
        /* Animações */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card {
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .card-body {
                padding: 20px;
            }
            
            .preferences-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/">
                <i class="bi bi-dice-6"></i> CritMeet
            </a>
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
            <div class="col-md-10 col-lg-8">
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="text-center mb-0">
                            <i class="bi bi-person-gear"></i> Editar Perfil
                        </h3>
                        <p class="text-center mb-0 mt-2 opacity-75">
                            Personalize suas informações e preferências de RPG
                        </p>
                    </div>
                    <div class="card-body">
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

                        <div class="text-center">
                            <img src="<?php echo getProfileImageUrl($user['image']); ?>" 
                                 alt="Imagem de Perfil" 
                                 class="image-preview" 
                                 id="imagePreview" />
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-group text-center">
                                <label class="file-input-wrapper">
                                    <i class="bi bi-camera-fill"></i> Escolher Nova Imagem
                                    <input type="file" name="image" accept="image/*" id="imageInput" />
                                </label>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i>
                                    Formatos aceitos: JPEG, PNG, GIF, WebP (máximo 5MB)
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="form-label">
                                            <i class="bi bi-person"></i> Nome Completo:
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="name"
                                               name="name" 
                                               placeholder="Seu nome completo" 
                                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" />
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="gender" class="form-label">
                                            <i class="bi bi-gender-ambiguous"></i> Gênero:
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="gender"
                                               name="gender" 
                                               placeholder="Ex: Masculino, Feminino, Não-binário..." 
                                               value="<?php echo htmlspecialchars($user['gender'] ?? ''); ?>" />
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="pronouns" class="form-label">
                                    <i class="bi bi-chat-quote"></i> Pronomes:
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="pronouns"
                                       name="pronouns" 
                                       placeholder="Ex: ele/dele, ela/dela, elu/delu, they/them..." 
                                       value="<?php echo htmlspecialchars($user['pronouns'] ?? ''); ?>" />
                                <div class="form-text">
                                    Como você gostaria de ser referido/a durante as sessões?
                                </div>
                            </div>

                            <div class="preferences-section">
                                <div class="preferences-title">
                                    <i class="bi bi-controller"></i> 
                                    Preferências de RPG
                                </div>
                                <p class="form-text mb-3">
                                    <i class="bi bi-lightbulb"></i>
                                    Selecione até 5 tags que melhor representem seu estilo de jogo e preferências. 
                                    Isso ajudará outros jogadores a encontrarem você!
                                </p>
                                <?php 
                                // Usar as preferências atuais do usuário
                                $current_preferences = isset($user['preferences']) ? $user['preferences'] : '';
                                RPGTags::renderTagSelector($current_preferences, 'preferences'); 
                                ?>
                            </div>

                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-check-lg"></i> Salvar Alterações
                                </button>
                                <a href="../Profile/" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Cancelar
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
        
        // Adicionar tooltips aos campos
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips do Bootstrap se disponível
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>