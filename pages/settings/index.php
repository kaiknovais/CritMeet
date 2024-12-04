<?php
include '../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

// Verifica no banco de dados se o usuário é admin
if ($user_id) {
    $query = "SELECT admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $is_admin = $row['admin'] == 1; // Define como true se o usuário for admin
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Configs</title>
    <script>
        function confirmDelete() {
            const confirmation = confirm("Tem certeza que deseja deletar sua conta?");
            if (confirmation) {
                const accountName = prompt("Por favor, insira o nome da sua conta para confirmar a exclusão:");
                if (accountName) {
                    window.location.href = "../../components/Delete/index.php";
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
            <a class="navbar-brand" href="../homepage/index.php">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="../homepage/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/index.php">Meu Perfil</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Mais...
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../settings/index.php">Configurações</a></li>
                            <li><a class="dropdown-item" href="../friends/index.php">Conexões</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../components/Logout/index.php">Logout</a></li>
                            <li><a class="dropdown-item" href="../chat/index.php">Chat</a></li>
                            <?php if ($is_admin): ?>
                                <li><a class="dropdown-item text-danger" href="../admin/index.php">Lista de Usuários</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>

                <form class="d-flex" action="../friends" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Buscar amigos..." aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Buscar</button>
                </form>
            </div>
        </div>
    </nav>

<div class="container">
  <div class="row">
    <div class="col"><a href="../editprofile/index.php"> <button type="button">Editar Perfil</button></a><br></div>
    
    <div class="col">
      <button type="button" data-bs-toggle="collapse" data-bs-target="#notificacoes" aria-expanded="false" aria-controls="collapseExample">
        Notificações
      </button>
      <div class="collapse" id="notificacoes">
      <div class="card card-body">
        Some placeholder content for the collapse component. This panel is hidden by default but revealed when the user activates the relevant trigger.
      </div>
      </div>  
    <br></div>
        
    <div class="col"><a href="../changepassword"> <button type="button">Configurações de Segurança</button></a><br></div>

  </div>
  <div class="row">
    <div class="col-8"><button type="button">Suporte e Ajuda</button><br></div>
    <div class="col-4">
    <form method="post" action="../../components/Delete/index.php">
      <button type="submit" name="delete_user">Excluir Conta</button>
    </form>
    <br></div>
  </div>
</div>

<?php include 'footer.php'; ?>

</body>

</html>
