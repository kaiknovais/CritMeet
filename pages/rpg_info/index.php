<?php
require_once __DIR__ . '/../../config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

if ($user_id) {
    $query = "SELECT admin FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $is_admin = $row['admin'] == 1;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>CritMeet - Introdução ao RPG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>

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

<div class="container mt-5 mb-5">
    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <h4><i class="bi bi-book"></i> Introdução ao RPG</h4>
        </div>
        <div class="card-body">
            <!-- Abas Bootstrap -->
            <ul class="nav nav-tabs mb-3" id="rpgTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="intro-tab" data-bs-toggle="tab" data-bs-target="#intro" type="button" role="tab">O que é RPG?</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tipos-tab" data-bs-toggle="tab" data-bs-target="#tipos" type="button" role="tab">Tipos de RPG</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sistemas-tab" data-bs-toggle="tab" data-bs-target="#sistemas" type="button" role="tab">Sistemas Populares</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="classes-tab" data-bs-toggle="tab" data-bs-target="#classes" type="button" role="tab">Classes</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="racas-tab" data-bs-toggle="tab" data-bs-target="#racas" type="button" role="tab">Raças</button>
                </li>
            </ul>

            <!-- Conteúdo das Abas -->
            <div class="tab-content" id="rpgTabContent">
                <div class="tab-pane fade show active" id="intro" role="tabpanel">
                    <p>O RPG (Role-Playing Game) é um jogo onde os participantes interpretam personagens em narrativas colaborativas. Cada jogador assume um papel dentro de uma história, guiada por um mestre ou narrador.</p>
                </div>
                <div class="tab-pane fade" id="tipos" role="tabpanel">
                    <ul>
                        <li><strong>RPG de Mesa:</strong> Tradicional, com livros, dados e fichas.</li>
                        <li><strong>RPG Virtual:</strong> Usado em plataformas como Roll20 e Foundry.</li>
                        <li><strong>LARP (Live Action Roleplay):</strong> Os jogadores interpretam fisicamente seus personagens.</li>
                    </ul>
                </div>
                <div class="tab-pane fade" id="sistemas" role="tabpanel">
                    <ul>
                        <li><strong>Dungeons & Dragons (D&D):</strong> Um dos mais conhecidos e jogados mundialmente.</li>
                        <li><strong>Call of Cthulhu:</strong> Focado em horror e investigação.</li>
                        <li><strong>Tormenta:</strong> É um universo de fantasia que abrange romances, histórias em quadrinhos, jogos de RPG em outros produtos.</li>
                        <li><strong>Vampiro: A Máscara:</strong> Parte do Mundo das Trevas, com foco em drama e política.</li>
                    </ul>
                </div>
                <div class="tab-pane fade" id="classes" role="tabpanel">
                    <ul>
                        <li><strong>Guerreiro:</strong> Combate corpo-a-corpo, tank.</li>
                        <li><strong>Mago:</strong> Usuário de magias e feitiços.</li>
                        <li><strong>Ladino:</strong> Furtivo e ágil, especialista em armadilhas e roubos.</li>
                        <li><strong>Clérigo:</strong> Curandeiro com poderes divinos.</li>
                    </ul>
                </div>
                <div class="tab-pane fade" id="racas" role="tabpanel">
                    <ul>
                        <li><strong>Humano:</strong> Versátil e adaptável.</li>
                        <li><strong>Elfo:</strong> Ágil e sábio, com longa vida.</li>
                        <li><strong>Anão:</strong> Forte, teimoso e bom em combate corpo-a-corpo.</li>
                        <li><strong>Orc:</strong> Selvagem, poderoso e temido.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
