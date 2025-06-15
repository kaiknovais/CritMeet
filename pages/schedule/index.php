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

// Buscar chats em grupo do usuário (apenas grupos onde é membro)
$user_chats = [];
$chat_query = "SELECT c.id, c.name, c.image, COUNT(cm.user_id) as member_count 
               FROM chats c 
               INNER JOIN chat_members cm ON c.id = cm.chat_id 
               WHERE c.is_group = 1 
               AND c.id IN (
                   SELECT chat_id FROM chat_members WHERE user_id = ?
               )
               GROUP BY c.id, c.name, c.image
               ORDER BY c.name";
$stmt = $mysqli->prepare($chat_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_chats[] = $row;
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
    if (empty($title) || empty($start_datetime) || empty($end_datetime) || $max_players < 1 || $chat_id == 0) {
        $error_message = 'Por favor, preencha todos os campos obrigatórios, incluindo o grupo.';
    } elseif (strtotime($start_datetime) <= time()) {
        $error_message = 'A data de início deve ser no futuro.';
    } elseif (strtotime($end_datetime) <= strtotime($start_datetime)) {
        $error_message = 'A data de fim deve ser posterior à data de início.';
    } else {
        // Verificar se o usuário é membro do chat/grupo selecionado
        $member_check = "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?";
        $stmt = $mysqli->prepare($member_check);
        $stmt->bind_param("ii", $chat_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error_message = 'Você não tem permissão para criar sessões neste grupo.';
        } else {
            $stmt->close();
            
            // Iniciar transação
            $mysqli->begin_transaction();
            
            try {
                // Inserir nova sessão (sem current_players, será calculado via query)
                $sql = "INSERT INTO sessions (title, description, start_datetime, end_datetime, max_players, location, creator_id, chat_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("ssssisii", $title, $description, $start_datetime, $end_datetime, $max_players, $location, $user_id, $chat_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Erro ao criar sessão');
                }
                
                $session_id = $mysqli->insert_id;
                $stmt->close();
                
                // Buscar todos os membros do chat/grupo
                $members_query = "SELECT user_id FROM chat_members WHERE chat_id = ?";
                $stmt = $mysqli->prepare($members_query);
                $stmt->bind_param("i", $chat_id);
                $stmt->execute();
                $members_result = $stmt->get_result();
                
                // Adicionar todos os membros do grupo como membros da sessão
                $member_sql = "INSERT INTO session_members (session_id, user_id, status, joined_at) VALUES (?, ?, ?, NOW())";
                $member_stmt = $mysqli->prepare($member_sql);
                
                while ($member = $members_result->fetch_assoc()) {
                    $member_user_id = $member['user_id'];
                    // Criador automaticamente aceita, outros ficam como 'pending'
                    $status = ($member_user_id == $user_id) ? 'accepted' : 'pending';
                    
                    $member_stmt->bind_param("iis", $session_id, $member_user_id, $status);
                    if (!$member_stmt->execute()) {
                        throw new Exception('Erro ao adicionar membros à sessão');
                    }
                }
                
                $stmt->close();
                $member_stmt->close();
                
                // Confirmar transação
                $mysqli->commit();
                
                $success_message = 'Sessão criada com sucesso! Todos os membros do grupo foram convidados.';
                
                // Limpar campos do formulário
                $title = $description = $start_datetime = $end_datetime = $location = '';
                $max_players = 0;
                $chat_id = 0;
                
            } catch (Exception $e) {
                // Reverter transação em caso de erro
                $mysqli->rollback();
                $error_message = 'Erro ao criar sessão: ' . $e->getMessage();
            }
        }
    }
}
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
        
        .chat-option {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chat-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
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
                    <h2><i class="bi bi-calendar-plus"></i> Agendar Nova Sessão em Grupo</h2>
                    <a href="../homepage/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>

        <?php if (empty($user_chats)): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Você precisa estar em pelo menos um grupo para criar sessões.</strong>
                <br>Vá para <a href="../chat/" class="alert-link">Chat</a> e crie ou junte-se a um grupo primeiro.
            </div>
        <?php endif; ?>

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
                        <h5><i class="bi bi-plus-circle"></i> Detalhes da Sessão em Grupo</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" <?php echo empty($user_chats) ? 'style="display:none;"' : ''; ?>>
                            <!-- Seleção do Grupo -->
                            <div class="form-floating mb-3">
                                <select class="form-select" id="chat_id" name="chat_id" required>
                                    <option value="">Selecione um grupo...</option>
                                    <?php foreach ($user_chats as $chat): ?>
                                        <option value="<?php echo $chat['id']; ?>" 
                                                <?php echo (isset($chat_id) && $chat_id == $chat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($chat['name']); ?> 
                                            (<?php echo $chat['member_count']; ?> membros)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="chat_id">Grupo para a Sessão *</label>
                            </div>

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

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Importante:</strong> Todos os membros do grupo selecionado serão automaticamente convidados para esta sessão.
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="create_session" class="btn btn-primary btn-lg">
                                    <i class="bi bi-calendar-check"></i> Criar Sessão em Grupo
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
                        <div class="mt-2">
                            <div><i class="bi bi-chat-dots"></i> <span id="previewGroup">Grupo: -</span></div>
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
                chat_id: document.getElementById('chat_id'),
                title: document.getElementById('title'),
                description: document.getElementById('description'),
                start_datetime: document.getElementById('start_datetime'),
                end_datetime: document.getElementById('end_datetime'),
                max_players: document.getElementById('max_players'),
                location: document.getElementById('location')
            };
            
            function updatePreview() {
                const chatId = fields.chat_id.value;
                const title = fields.title.value.trim();
                const description = fields.description.value.trim();
                const startDate = fields.start_datetime.value;
                const endDate = fields.end_datetime.value;
                const maxPlayers = fields.max_players.value;
                const location = fields.location.value.trim();
                
                if (title || description || startDate || endDate || maxPlayers || location || chatId) {
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
                    
                    // Mostrar nome do grupo selecionado
                    const selectedChat = fields.chat_id.options[fields.chat_id.selectedIndex];
                    document.getElementById('previewGroup').textContent = 
                        chatId ? 'Grupo: ' + selectedChat.text : 'Grupo: Nenhum selecionado';
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