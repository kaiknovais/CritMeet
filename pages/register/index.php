<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Tags/index.php';
session_start();

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $pronouns = trim($_POST['pronouns'] ?? '');
        $preferences = $_POST['preferences'] ?? '';
        
        // Validações básicas
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception('Nome de usuário, email e senha são obrigatórios.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido.');
        }
        
        if ($password !== $confirm_password) {
            throw new Exception('As senhas não coincidem.');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('A senha deve ter pelo menos 6 caracteres.');
        }
        
        // Verificar se o username já existe
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Nome de usuário ou email já existe. Escolha outro.');
        }
        
        // Processar e validar as tags selecionadas
        $preferences = RPGTags::formatUserTags(RPGTags::parseUserTags($preferences));
        
        // Hash da senha
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Inserir usuário no banco de dados (imagem padrão será NULL)
        $insert_query = "INSERT INTO users (username, email, password, name, gender, pronouns, preferences, image) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)";
        $insert_stmt = $mysqli->prepare($insert_query);
        $insert_stmt->bind_param("sssssss", $username, $email, $hashed_password, $name, $gender, $pronouns, $preferences);
        
        if ($insert_stmt->execute()) {
            // Definir mensagem de sucesso na sessão e redirecionar para login
            $_SESSION['registration_success'] = "Conta criada com sucesso! Você pode fazer login agora.";
            header("Location: ../login/");
            exit();
        } else {
            throw new Exception('Erro ao criar a conta. Tente novamente.');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Registrar - CritMeet</title>
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
        .btn-register {
            margin-top: 1rem;
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
        .default-avatar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 4rem;
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
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">CritMeet</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="../login/">Login</a></li>
                <li class="nav-item"><a class="nav-link active" href="../register/">Registrar</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="profile-container">
                <div class="profile-header">
                    
                    <h2 class="mb-2">Criar Nova Conta</h2>
                    <p class="text-muted mb-0">Cadastro básico - você pode completar seu perfil depois</p>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo $error_message; ?></div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Informações Básicas</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="registerForm">
                            <table class="table table-striped profile-table mb-0">
                                <tr>
                                    <th><i class="bi bi-person-circle"></i> Nome de Usuário:</th>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="username" 
                                               placeholder="Escolha um nome de usuário único" 
                                               value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                                               required />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-envelope"></i> Email:</th>
                                    <td>
                                        <input type="email" 
                                               class="form-control" 
                                               name="email" 
                                               placeholder="seu@email.com" 
                                               value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                               required />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-lock"></i> Senha:</th>
                                    <td>
                                        <input type="password" 
                                               class="form-control" 
                                               name="password" 
                                               id="password"
                                               placeholder="Mínimo 6 caracteres" 
                                               required />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-lock-fill"></i> Confirmar Senha:</th>
                                    <td>
                                        <input type="password" 
                                               class="form-control" 
                                               name="confirm_password" 
                                               id="confirm_password"
                                               placeholder="Repita a senha" 
                                               required />
                                        <div class="password-mismatch" id="password-mismatch">
                                            <i class="bi bi-exclamation-triangle"></i> As senhas não coincidem
                                        </div>
                                        <div class="password-match" id="password-match">
                                            <i class="bi bi-check-circle"></i> As senhas coincidem
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-person"></i> Nome Completo:</th>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="name" 
                                               placeholder="Seu nome completo (opcional)" 
                                               value="<?php echo htmlspecialchars($name ?? ''); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-gender-ambiguous"></i> Gênero:</th>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="gender" 
                                               placeholder="Ex: Masculino, Feminino, Não-binário... (opcional)" 
                                               value="<?php echo htmlspecialchars($gender ?? ''); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-chat-quote"></i> Pronomes:</th>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="pronouns" 
                                               placeholder="Ex: ele/dele, ela/dela, elu/delu... (opcional)" 
                                               value="<?php echo htmlspecialchars($pronouns ?? ''); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="bi bi-controller"></i> Preferências de Jogo:</th>
                                    <td>
                                        <div class="mb-2">
                                            <?php 
                                            // Usar as preferências do formulário se houver erro, senão vazio
                                            $current_preferences = isset($preferences) ? $preferences : '';
                                            RPGTags::renderTagSelector($current_preferences, 'preferences'); 
                                            ?>
                                        </div>
                                        <div class="form-text">
                                            Selecione até 5 tags que melhor representem seu estilo de jogo (opcional)
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </div>

                <div class="text-center btn-register">
                    <button type="submit" form="registerForm" class="btn btn-primary btn-lg me-2">
                        <i class="bi bi-person-plus"></i> Criar Conta
                    </button>
                    <a href="../login/" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Voltar ao Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const mismatchDiv = document.getElementById('password-mismatch');
    const matchDiv = document.getElementById('password-match');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (confirmPassword === '' || password === '') {
        // Se não há confirmação ainda, resetar visual
        mismatchDiv.style.display = 'none';
        matchDiv.style.display = 'none';
        passwordInput.classList.remove('is-invalid', 'is-valid');
        confirmPasswordInput.classList.remove('is-invalid', 'is-valid');
        return;
    }
    
    if (password !== confirmPassword) {
        // Senhas não coincidem - ambos ficam vermelhos
        mismatchDiv.style.display = 'block';
        matchDiv.style.display = 'none';
        passwordInput.classList.add('is-invalid');
        passwordInput.classList.remove('is-valid');
        confirmPasswordInput.classList.add('is-invalid');
        confirmPasswordInput.classList.remove('is-valid');
        confirmPasswordInput.setCustomValidity('As senhas não coincidem');
    } else {
        // Senhas coincidem - ambos ficam verdes
        mismatchDiv.style.display = 'none';
        matchDiv.style.display = 'block';
        passwordInput.classList.add('is-valid');
        passwordInput.classList.remove('is-invalid');
        confirmPasswordInput.classList.add('is-valid');
        confirmPasswordInput.classList.remove('is-invalid');
        confirmPasswordInput.setCustomValidity('');
    }
}

// Verificar quando o usuário digita na senha
document.getElementById('password').addEventListener('input', checkPasswordMatch);

// Verificar quando o usuário digita na confirmação de senha
document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

// Validação do formulário antes do envio
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('As senhas não coincidem!');
        return;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('A senha deve ter pelo menos 6 caracteres!');
        return;
    }
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>