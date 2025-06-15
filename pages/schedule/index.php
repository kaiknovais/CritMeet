<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../components/Calendar/index.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = false;

if (!$user_id) {
    header('Location: ../login/');
    exit;
}

// Verificar se é admin
$query = "SELECT admin FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $is_admin = $row['admin'] == 1;
}
$stmt->close();

// Inicializar componente Calendar
$calendar = new Calendar($mysqli, $user_id);

$success_message = '';
$error_message = '';

// Processar criação de nova sessão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_datetime = $_POST['start_datetime'] ?? '';
    $end_datetime = $_POST['end_datetime'] ?? '';
    $max_players = intval($_POST['max_players'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $chat_id = intval($_POST['chat_id'] ?? 0);
    
    // Validações
    if (empty($title) || empty($start_datetime) || empty($end_datetime) || $max_players < 1) {
        $error_message = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (strtotime($start_datetime) <= time()) {
        $error_message = 'A data de início deve ser no futuro.';
    } elseif (strtotime($end_datetime) <= strtotime($start_datetime)) {
        $error_message = 'A data de fim deve ser posterior à data de início.';
    } else {
        // Inserir nova sessão
        $sql = "INSERT INTO sessions (title, description, start_datetime, end_datetime, max_players, current_players, location, creator_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, 'active', NOW())";
        
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssssiisi", $title, $description, $start_datetime, $end_datetime, $max_players, $location, $user_id);
            
            if ($stmt->execute()) {
                $session_id = $mysqli->insert_id;
                
                // Adicionar o criador como membro da sessão
                $member_sql = "INSERT INTO session_members (session_id, user_id, status, joined_at) VALUES (?, ?, 'accepted', NOW())";
                $member_stmt = $mysqli->prepare($member_sql);
                if ($member_stmt) {
                    $member_stmt->bind_param("ii", $session_id, $user_id);
                    $member_stmt->execute();
                    $member_stmt->close();
                }
                
                $success_message = 'Sessão criada com sucesso!';
                
                // Limpar campos do formulário
                $title = $description = $start_datetime = $end_datetime = $location = '';
                $max_players = 0;
            } else {
                $error_message = 'Erro ao criar sessão. Tente novamente.';
            }
            $stmt->close();
        } else {
            $error_message = 'Erro interno. Tente novamente.';
        }
    }
}

// Buscar chats do usuário para dropdown (removido - não usado)
$user_chats = [];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CritMeet - Agendar Sessão</title>
    <link rel="stylesheet" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <style>
        .datetime-input {
            cursor: pointer;
        }
        
        .form-floating .form-control:focus ~ label,
        .form-floating .form-control:not(:placeholder-shown) ~ label {
            opacity: .65;
            transform: scale(.85) translateY(-0.5rem) translateX(0.15rem);
        }
        
        .session-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
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
                    <li class="nav-item"><a class="nav-link active" href="../schedule/">Agendar</a></li>
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

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-calendar-plus"></i> Agendar Nova Sessão</h2>
                    <a href="../homepage/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulário de Criação -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-plus-circle"></i> Detalhes da Sessão</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="title" name="title" 
                                       placeholder="Título da Sessão" required maxlength="100"
                                       value="<?php echo htmlspecialchars($title ?? ''); ?>">
                                <label for="title">Título da Sessão *</label>
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" name="description" 
                                          placeholder="Descrição" style="height: 100px" maxlength="500"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                                <label for="description">Descrição</label>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="datetime-local" class="form-control datetime-input" 
                                               id="start_datetime" name="start_datetime" required
                                               value="<?php echo $start_datetime ?? ''; ?>">
                                        <label for="start_datetime">Data/Hora Início *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="datetime-local" class="form-control datetime-input" 
                                               id="end_datetime" name="end_datetime" required
                                               value="<?php echo $end_datetime ?? ''; ?>">
                                        <label for="end_datetime">Data/Hora Fim *</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="max_players" 
                                               name="max_players" min="1" max="20" required
                                               value="<?php echo $max_players ?? ''; ?>">
                                        <label for="max_players">Máximo de Jogadores *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="location" name="location" 
                                               placeholder="Local" maxlength="200"
                                               value="<?php echo htmlspecialchars($location ?? ''); ?>">
                                        <label for="location">Local (opcional)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="additional_notes" name="additional_notes" 
                                          placeholder="Observações adicionais" style="height: 80px" maxlength="300"><?php echo htmlspecialchars($additional_notes ?? ''); ?></textarea>
                                <label for="additional_notes">Observações Adicionais</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="create_session" class="btn btn-primary btn-lg">
                                    <i class="bi bi-calendar-check"></i> Criar Sessão
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Limpar Formulário
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preview e Calendário -->
            <div class="col-lg-6">
                <!-- Preview da Sessão -->
                <div class="session-preview mb-4" id="sessionPreview" style="display: none;">
                    <h5><i class="bi bi-eye"></i> Preview da Sessão</h5>
                    <div class="preview-content">
                        <h6 id="previewTitle">-</h6>
                        <p id="previewDescription" class="mb-2">-</p>
                        <div class="row small">
                            <div class="col-6">
                                <div><i class="bi bi-calendar3"></i> <span id="previewDate">-</span></div>
                                <div><i class="bi bi-clock"></i> <span id="previewTime">-</span></div>
                            </div>
                            <div class="col-6">
                                <div><i class="bi bi-people"></i> <span id="previewPlayers">-</span> jogadores</div>
                                <div><i class="bi bi-geo-alt"></i> <span id="previewLocation">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendário das Minhas Sessões -->
                <div class="calendar-container">
                    <h5 class="mb-3"><i class="bi bi-calendar3"></i> Minhas Sessões Agendadas</h5>
                    <div id="calendar" style="min-height: 400px;"></div>
                </div>

                <!-- Estatísticas Rápidas -->
                <div class="row mt-3">
                    <div class="col-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h5><?php echo $calendar->getCreatedSessionsCount(); ?></h5>
                                <small>Sessões Criadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5><?php echo $calendar->getActiveSessionsCount(); ?></h5>
                                <small>Sessões Ativas</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    
    <script>
        let calendar;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar calendário
            <?php echo $calendar->getCalendarScript(); ?>
            initializeCalendar();
            
            // Preview dinâmico
            const form = document.querySelector('form');
            const preview = document.getElementById('sessionPreview');
            const fields = {
                title: document.getElementById('title'),
                description: document.getElementById('description'),
                start_datetime: document.getElementById('start_datetime'),
                end_datetime: document.getElementById('end_datetime'),
                max_players: document.getElementById('max_players'),
                location: document.getElementById('location')
            };
            
            function updatePreview() {
                const title = fields.title.value.trim();
                const description = fields.description.value.trim();
                const startDate = fields.start_datetime.value;
                const endDate = fields.end_datetime.value;
                const maxPlayers = fields.max_players.value;
                const location = fields.location.value.trim();
                
                if (title || description || startDate || endDate || maxPlayers || location) {
                    preview.style.display = 'block';
                    
                    document.getElementById('previewTitle').textContent = title || 'Título da Sessão';
                    document.getElementById('previewDescription').textContent = description || 'Sem descrição';
                    
                    if (startDate) {
                        const start = new Date(startDate);
                        document.getElementById('previewDate').textContent = start.toLocaleDateString('pt-BR');
                        
                        if (endDate) {
                            const end = new Date(endDate);
                            document.getElementById('previewTime').textContent = 
                                start.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) + 
                                ' - ' + 
                                end.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                        } else {
                            document.getElementById('previewTime').textContent = 
                                start.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                        }
                    } else {
                        document.getElementById('previewDate').textContent = '-';
                        document.getElementById('previewTime').textContent = '-';
                    }
                    
                    document.getElementById('previewPlayers').textContent = maxPlayers || '0';
                    document.getElementById('previewLocation').textContent = location || 'Local não informado';
                } else {
                    preview.style.display = 'none';
                }
            }
            
            // Adicionar listeners para atualização do preview
            Object.values(fields).forEach(field => {
                field.addEventListener('input', updatePreview);
                field.addEventListener('change', updatePreview);
            });
            
            // Validação de datas
            fields.start_datetime.addEventListener('change', function() {
                const startDate = new Date(this.value);
                const now = new Date();
                
                if (startDate <= now) {
                    this.setCustomValidity('A data de início deve ser no futuro');
                } else {
                    this.setCustomValidity('');
                    
                    // Definir data mínima para o fim
                    const minEnd = new Date(startDate.getTime() + 60000); // +1 minuto
                    fields.end_datetime.min = minEnd.toISOString().slice(0, 16);
                }
            });
            
            fields.end_datetime.addEventListener('change', function() {
                const startDate = new Date(fields.start_datetime.value);
                const endDate = new Date(this.value);
                
                if (endDate <= startDate) {
                    this.setCustomValidity('A data de fim deve ser posterior à data de início');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Definir data mínima inicial
            const now = new Date();
            const minDateTime = new Date(now.getTime() + 60000); // +1 minuto
            fields.start_datetime.min = minDateTime.toISOString().slice(0, 16);
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>