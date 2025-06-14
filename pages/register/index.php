<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/tags/index.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card mt-4">
                    <div class="card-header">
                        <h1 class="text-center mb-0">CritMeet - Registro</h1>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required />
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="Nome" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required />
                            </div>

                            <div class="mb-3">
                                <label for="gender" class="form-label">Gênero</label>
                                <input type="text" class="form-control" id="gender" name="gender" 
                                       placeholder="Gênero" 
                                       value="<?php echo isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : ''; ?>" 
                                       required />
                            </div>

                            <div class="mb-3">
                                <label for="pronouns" class="form-label">Pronomes</label>
                                <input type="text" class="form-control" id="pronouns" name="pronouns" 
                                       placeholder="Ex: ele/dele, ela/dela, elu/delu" 
                                       value="<?php echo isset($_POST['pronouns']) ? htmlspecialchars($_POST['pronouns']) : ''; ?>" 
                                       required />
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required />
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Senha" required />
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmar Senha</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirmar Senha" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Preferências de RPG</label>
                                <p class="form-text">Selecione até 5 tags que representem suas preferências de jogo:</p>
                                <?php 
                                $selected_preferences = isset($_POST['preferences']) ? $_POST['preferences'] : '';
                                RPGTags::renderTagSelector($selected_preferences, 'preferences'); 
                                ?>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Registrar
                                </button>
                                <a href="../login/" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Voltar ao Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST["username"]);
        $name = trim($_POST["name"]);
        $gender = trim($_POST["gender"]);
        $pronouns = trim($_POST["pronouns"]);
        $email = trim($_POST["email"]);
        $password = $_POST["password"];
        $preferences = isset($_POST["preferences"]) ? $_POST["preferences"] : '';

        // Validações básicas
        if ($password !== $_POST["confirm_password"]) {
            echo "<script>alert('As senhas não coincidem.');</script>";
        } else if (strlen($password) < 6) {
            echo "<script>alert('A senha deve ter pelo menos 6 caracteres.');</script>";
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "<script>alert('Email inválido.');</script>";
        } else {
            // Hash da senha
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $mysqli->prepare("INSERT INTO users (username, name, gender, pronouns, email, password, preferences) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $name, $gender, $pronouns, $email, $hashed_password, $preferences);

            try {
                if ($stmt->execute()) {
                    echo "<script>alert('Cadastro realizado com sucesso!'); window.location.href='../login/';</script>";
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) { 
                    // Verificar se é email ou username duplicado
                    if (strpos($e->getMessage(), 'email') !== false) {
                        echo "<script>alert('Erro: O e-mail já está cadastrado.');</script>";
                    } else if (strpos($e->getMessage(), 'username') !== false) {
                        echo "<script>alert('Erro: O username já está em uso.');</script>";
                    } else {
                        echo "<script>alert('Erro: Dados já cadastrados.');</script>";
                    }
                } else {
                    echo "<script>alert('Erro ao cadastrar: " . addslashes($e->getMessage()) . "');</script>";
                }
            } finally {
                $stmt->close();
            }
        }
    }
    ?>
</body>
</html>