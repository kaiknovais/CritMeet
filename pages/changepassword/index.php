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

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/login/';</script>";
    exit();
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

// Variáveis para mensagens
$success_message = '';
$error_message = '';

// Processa o formulário de alteração de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Captura os dados do formulário e remove espaços em branco
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validações básicas
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("Todos os campos são obrigatórios.");
        }

        // Verificar se as senhas novas coincidem
        if ($new_password !== $confirm_password) {
            throw new Exception("As senhas novas não coincidem.");
        }

        // Verificar se a nova senha tem pelo menos 6 caracteres
        if (strlen($new_password) < 6) {
            throw new Exception("A nova senha deve ter pelo menos 6 caracteres.");
        }

        // Buscar a senha atual do banco de dados
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Usuário não encontrado.");
        }

        $user_data = $result->fetch_assoc();

        // Verificar se a senha atual fornecida é válida (suporta hash e texto plano)
        $password_valid = false;
        
        if (password_verify($current_password, $user_data['password'])) {
            // Senha com hash - método correto
            $password_valid = true;
        } elseif ($user_data['password'] === $current_password) {
            // Senha em texto plano (para compatibilidade com dados antigos)
            $password_valid = true;
        }

        if (!$password_valid) {
            throw new Exception("A senha atual está incorreta.");
        }

        // A senha atual está correta, agora vamos atualizar a senha
        // Gerar hash da nova senha
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Atualizar a senha no banco de dados com hash
        $sql_update = "UPDATE users SET password = ? WHERE id = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param("si", $hashed_new_password, $user_id);

        if ($stmt_update->execute()) {
            $success_message = "Senha alterada com sucesso!";
        } else {
            throw new Exception("Ocorreu um erro ao tentar alterar a senha. Tente novamente.");
        }

        $stmt_update->close();
        $stmt->close();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - CritMeet</title>
    <link rel="stylesheet" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
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
        
        .password-mismatch {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .password-match {
            color: #198754;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        
        .form-control.is-valid {
            border-color: #198754;
        }
        
        .password-strength {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #198754; }
        
        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-bottom: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-1px);
        }
        
        .main-content {
            min-height: calc(100vh - 200px);
            padding: 2rem 0;
        }
        
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 12px;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <!-- Bootstrap JS -->
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

    <!-- Conteúdo Principal -->
    <div class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="card">
                        <div class="card-header text-white">
                            <h4 class="mb-0 text-center">
                                <i class="bi bi-shield-lock me-2"></i>Alterar Senha
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger d-flex align-items-center mb-4">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div><?= htmlspecialchars($error_message) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success d-flex align-items-center mb-4">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <div><?= htmlspecialchars($success_message) ?></div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" id="changePasswordForm">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">
                                        <i class="bi bi-lock me-1"></i>Senha Atual
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="current_password" 
                                           name="current_password" 
                                           placeholder="Digite sua senha atual"
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">
                                        <i class="bi bi-lock-fill me-1"></i>Nova Senha
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="new_password" 
                                           name="new_password" 
                                           placeholder="Digite sua nova senha"
                                           required>
                                    <div class="password-strength" id="password-strength"></div>
                                    <div class="form-text">A senha deve ter pelo menos 6 caracteres.</div>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">
                                        <i class="bi bi-shield-check me-1"></i>Confirmar Nova Senha
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           placeholder="Confirme sua nova senha"
                                           required>
                                    <div class="password-mismatch" id="password-mismatch">
                                        <i class="bi bi-exclamation-triangle me-1"></i>As senhas não coincidem
                                    </div>
                                    <div class="password-match" id="password-match">
                                        <i class="bi bi-check-circle me-1"></i>As senhas coincidem
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-shield-check me-2"></i>Alterar Senha
                                    </button>
                                    <a href="../settings/" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Voltar às Configurações
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function checkPasswordStrength(password) {
        const strengthDiv = document.getElementById('password-strength');
        const newPasswordInput = document.getElementById('new_password');
        
        if (password.length === 0) {
            strengthDiv.textContent = '';
            newPasswordInput.classList.remove('is-invalid', 'is-valid');
            return;
        }
        
        let strength = 0;
        let feedback = [];
        
        // Critérios de força da senha
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        if (password.length < 6) {
            strengthDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Muito curta (mínimo 6 caracteres)';
            strengthDiv.className = 'password-strength strength-weak';
            newPasswordInput.classList.add('is-invalid');
            newPasswordInput.classList.remove('is-valid');
        } else if (strength < 3) {
            strengthDiv.innerHTML = '<i class="bi bi-shield-exclamation me-1"></i>Senha fraca';
            strengthDiv.className = 'password-strength strength-weak';
            newPasswordInput.classList.add('is-invalid');
            newPasswordInput.classList.remove('is-valid');
        } else if (strength < 5) {
            strengthDiv.innerHTML = '<i class="bi bi-shield-check me-1"></i>Senha média';
            strengthDiv.className = 'password-strength strength-medium';
            newPasswordInput.classList.remove('is-invalid', 'is-valid');
        } else {
            strengthDiv.innerHTML = '<i class="bi bi-shield-fill-check me-1"></i>Senha forte';
            strengthDiv.className = 'password-strength strength-strong';
            newPasswordInput.classList.add('is-valid');
            newPasswordInput.classList.remove('is-invalid');
        }
    }

    function checkPasswordMatch() {
        const password = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const mismatchDiv = document.getElementById('password-mismatch');
        const matchDiv = document.getElementById('password-match');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        if (confirmPassword === '' || password === '') {
            // Se não há confirmação ainda, resetar visual
            mismatchDiv.style.display = 'none';
            matchDiv.style.display = 'none';
            confirmPasswordInput.classList.remove('is-invalid', 'is-valid');
            return;
        }
        
        if (password !== confirmPassword) {
            // Senhas não coincidem
            mismatchDiv.style.display = 'block';
            matchDiv.style.display = 'none';
            confirmPasswordInput.classList.add('is-invalid');
            confirmPasswordInput.classList.remove('is-valid');
            confirmPasswordInput.setCustomValidity('As senhas não coincidem');
        } else {
            // Senhas coincidem
            mismatchDiv.style.display = 'none';
            matchDiv.style.display = 'block';
            confirmPasswordInput.classList.add('is-valid');
            confirmPasswordInput.classList.remove('is-invalid');
            confirmPasswordInput.setCustomValidity('');
        }
    }

    // Event listeners
    document.getElementById('new_password').addEventListener('input', function() {
        checkPasswordStrength(this.value);
        checkPasswordMatch();
    });

    document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

    // Validação do formulário antes do envio
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        const currentPassword = document.querySelector('input[name="current_password"]').value;
        const newPassword = document.querySelector('input[name="new_password"]').value;
        const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
        
        if (!currentPassword || !newPassword || !confirmPassword) {
            e.preventDefault();
            alert('Todos os campos são obrigatórios!');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('As senhas novas não coincidem!');
            return;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('A nova senha deve ter pelo menos 6 caracteres!');
            return;
        }
        
        if (currentPassword === newPassword) {
            e.preventDefault();
            alert('A nova senha deve ser diferente da senha atual!');
            return;
        }
    });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>