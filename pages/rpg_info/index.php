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
    <title>CritMeet - Introdução ao RPG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    </style>
</head>
<body>

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
                <li class="nav-item"><a class="nav-link active" href="../rpg_info">RPG</a></li>
                <li class="nav-item"><a class="nav-link" href="../friends">Conexões</a></li>
                <li class="nav-item"><a class="nav-link" href="../chat">Chat</a></li>
            </ul>
            
            <!-- Seção do usuário -->
            <ul class="navbar-nav">
                <?php if ($user_id && $user): ?>
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
                        <li><a class="dropdown-item" href="../settings/">
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
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="../../components/Login/">Login</a>
                </li>
                <?php endif; ?>
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
                    <h5>O que é RPG?</h5>
                    <p>O RPG (Role-Playing Game) é um jogo onde os participantes interpretam personagens em narrativas colaborativas. Cada jogador assume um papel dentro de uma história, guiada por um mestre ou narrador.</p>
                    <p>É uma forma única de entretenimento que combina storytelling, improvisação e trabalho em equipe para criar experiências memoráveis e únicas.</p>
                </div>
                <div class="tab-pane fade" id="tipos" role="tabpanel">
                    <h5>Tipos de RPG</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-dice-6"></i> RPG de Mesa</h6>
                                    <p class="card-text">Tradicional, com livros, dados e fichas físicas. É a forma mais clássica de jogar RPG.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-laptop"></i> RPG Virtual</h6>
                                    <p class="card-text">Usado em plataformas online como Roll20, Foundry VTT e Discord.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-person-arms-up"></i> LARP</h6>
                                    <p class="card-text">Live Action Roleplay - Os jogadores interpretam fisicamente seus personagens.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="sistemas" role="tabpanel">
                    <h5>Sistemas Populares</h5>
                    <div class="accordion" id="sistemasAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dnd">
                                    <strong>Dungeons & Dragons (D&D)</strong>
                                </button>
                            </h2>
                            <div id="dnd" class="accordion-collapse collapse show" data-bs-parent="#sistemasAccordion">
                                <div class="accordion-body">
                                    Um dos mais conhecidos e jogados mundialmente. Sistema de fantasia medieval com foco em aventura e combate.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cthulhu">
                                    <strong>Call of Cthulhu</strong>
                                </button>
                            </h2>
                            <div id="cthulhu" class="accordion-collapse collapse" data-bs-parent="#sistemasAccordion">
                                <div class="accordion-body">
                                    Focado em horror cósmico e investigação. Baseado nas obras de H.P. Lovecraft.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tormenta">
                                    <strong>Tormenta</strong>
                                </button>
                            </h2>
                            <div id="tormenta" class="accordion-collapse collapse" data-bs-parent="#sistemasAccordion">
                                <div class="accordion-body">
                                    Universo de fantasia brasileiro que abrange romances, histórias em quadrinhos e jogos de RPG.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#vampiro">
                                    <strong>Vampiro: A Máscara</strong>
                                </button>
                            </h2>
                            <div id="vampiro" class="accordion-collapse collapse" data-bs-parent="#sistemasAccordion">
                                <div class="accordion-body">
                                    Parte do Mundo das Trevas, com foco em drama pessoal, política vampírica e horror urbano.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="classes" role="tabpanel">
                    <h5>Classes de Personagem</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-shield"></i> Guerreiro</h6>
                                    <p class="card-text">Especialista em combate corpo-a-corpo, alta resistência e defesa. Atua como tank da equipe.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-stars"></i> Mago</h6>
                                    <p class="card-text">Usuário de magias e feitiços. Alto poder de ataque mágico, mas baixa resistência física.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-eye"></i> Ladino</h6>
                                    <p class="card-text">Furtivo e ágil, especialista em armadilhas, roubos e ataques críticos.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-heart-pulse"></i> Clérigo</h6>
                                    <p class="card-text">Curandeiro com poderes divinos. Especialista em cura e magias de suporte.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="racas" role="tabpanel">
                    <h5>Raças de Personagem</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-person"></i> Humano</h6>
                                    <p class="card-text">Versátil e adaptável. Bônus em todas as habilidades e grande flexibilidade de build.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-tree"></i> Elfo</h6>
                                    <p class="card-text">Ágil e sábio, com longa vida. Especialistas em magia e combate à distância.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-hammer"></i> Anão</h6>
                                    <p class="card-text">Forte, resistente e teimoso. Excelentes em combate corpo-a-corpo e artesanato.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-lightning"></i> Orc</h6>
                                    <p class="card-text">Selvagem, poderoso e temido. Alta força física mas menor inteligência.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>