<?php
include('../../config.php');
session_start();

// Verifica se o usuário está logado e se o ID do usuário foi salvo na sessão
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Usuário não autenticado.'); window.location.href='../../pages/admin/login.php';</script>";
    exit();
}

$user_id_logged_in = $_SESSION['user_id'];  // ID do usuário logado

// Consulta o status de admin do usuário logado
$sql_admin_check = "SELECT admin FROM users WHERE id = '$user_id_logged_in'";
$result = $mysqli->query($sql_admin_check);
$user_logged_in = $result->fetch_assoc();

// Verifica se o usuário é um administrador (admin = 1)
if ($user_logged_in['admin'] != 1) {
    echo "<script>alert('Acesso negado. Somente administradores podem editar usuários.'); window.location.href='../../pages/admin/index.php';</script>";
    exit();
}

// Verifica se o ID do usuário a ser visualizado foi passado via GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];

    // Prepara a consulta SQL para buscar as informações do usuário
    $sql = "SELECT * FROM users WHERE id='$user_id'";
    $result = $mysqli->query($sql);
    $user = $result->fetch_assoc();

    // Verifica se o usuário foi encontrado
    if (!$user) {
        echo "<script>alert('Usuário não encontrado.'); window.location.href='../../pages/admin/index.php';</script>";
        exit();
    }
} else {
    // Caso o ID não seja válido ou não seja fornecido
    echo "<script>alert('ID inválido ou não fornecido.'); window.location.href='../../pages/admin/index.php';</script>";
    exit();
}

// Processa o formulário de atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Captura os dados do formulário
    $name = $_POST['name'];
    $gender = $_POST['gender'];
    $pronouns = $_POST['pronouns'];
    $preferences = $_POST['preferences'];
    $is_admin = isset($_POST['admin']) ? 1 : 0;  // Verifica se o checkbox foi marcado para tornar o usuário admin

    // Processa a imagem se for enviada
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $image_data = file_get_contents($_FILES['image']['tmp_name']);
        $image = base64_encode($image_data); // Converte a imagem para base64
    }

    // Atualiza os dados no banco de dados
    $sql_update = "UPDATE users SET 
                    name='$name', 
                    gender='$gender', 
                    pronouns='$pronouns', 
                    preferences='$preferences',
                    admin='$is_admin'";  // Atualiza o status de admin

    // Se houver uma nova imagem, atualiza também
    if ($image) {
        $sql_update .= ", image='$image'";
    }

    // Finaliza a consulta
    $sql_update .= " WHERE id='$user_id'";

    // Executa a atualização
    if ($mysqli->query($sql_update)) {
        echo "<script>alert('Informações atualizadas com sucesso!'); window.location.href='../../pages/admin/index.php';</script>";
    } else {
        echo "<script>alert('Erro ao atualizar as informações.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <title>Editar Informações do Usuário</title>
</head>
<body>

    <div class="container text-center">
        <h2>Editar Informações do Usuário</h2>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php if (!empty($user['image'])): ?>
                <h3>Imagem de Perfil Atual</h3>
                <img src="data:image/jpeg;base64,<?php echo $user['image']; ?>" alt="Imagem de Perfil" style="max-width: 200px; max-height: 200px;" /><br><br>
            <?php endif; ?>
            
            <div class="mb-3">
                <label for="image" class="form-label">Nova Imagem de Perfil</label>
                <input type="file" id="image" name="image" class="form-control" />
            </div>


            <div class="mb-3">
                <label for="name" class="form-label">Nome</label>
                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" />
            </div>
            <div class="mb-3">
                <label for="gender" class="form-label">Gênero</label>
                <input type="text" id="gender" name="gender" class="form-control" value="<?php echo htmlspecialchars($user['gender']); ?>" />
            </div>
            <div class="mb-3">
                <label for="pronouns" class="form-label">Pronomes</label>
                <input type="text" id="pronouns" name="pronouns" class="form-control" value="<?php echo htmlspecialchars($user['pronouns']); ?>" />
            </div>
            <div class="mb-3">
                <label for="preferences" class="form-label">Preferências de Jogo</label>
                <input type="text" id="preferences" name="preferences" class="form-control" value="<?php echo htmlspecialchars($user['preferences']); ?>" />
            </div>

            
            <div class="mb-3">
                <label for="admin" class="form-label">Administrador</label>
                <input type="checkbox" id="admin" name="admin" <?php echo $user['admin'] == 1 ? 'checked' : ''; ?> />
            </div>

            
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <?php include '../NavBack/index.php'; ?>
        </form>
    </div>

</body>
</html>
