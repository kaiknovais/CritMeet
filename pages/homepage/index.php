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

// Buscar mensagens recentes (últimas 5)
$recent_messages = [];
if ($user_id) {
    $sql_messages = "SELECT m.content, m.timestamp, u.username, u.name, c.is_group, c.name as group_name,
                     CASE 
                         WHEN c.is_group = 1 THEN c.id
                         ELSE (SELECT user_id FROM chat_members cm WHERE cm.chat_id = c.id AND cm.user_id != ? LIMIT 1)
                     END as chat_identifier
                     FROM messages m
                     JOIN users u ON m.sender_id = u.id
                     JOIN chats c ON m.chat_id = c.id
                     JOIN chat_members cm ON c.id = cm.chat_id
                     WHERE cm.user_id = ? AND m.sender_id != ?
                     ORDER BY m.timestamp DESC
                     LIMIT 5";
    
    $stmt = $mysqli->prepare($sql_messages);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result_messages = $stmt->get_result();
    
    while ($row = $result_messages->fetch_assoc()) {
        $recent_messages[] = $row;
    }
    $stmt->close();
}

// Buscar solicitações de amizade pendentes
$pending_requests = [];
if ($user_id) {
    $sql_requests = "SELECT f.id, u.username, u.name 
                     FROM friends f 
                     JOIN users u ON f.user_id = u.id 
                     WHERE f.friend_id = ? AND f.status = 'pending'";
    
    $stmt = $mysqli->prepare($sql_requests);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_requests = $stmt->get_result();
    
    while ($row = $result_requests->fetch_assoc()) {
        $pending_requests[] = $row;
    }
    $stmt->close();
}

// Aceitar solicitação de amizade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    
    $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ? AND status = 'pending'";
    $stmt_accept = $mysqli->prepare($sql_accept);
    $stmt_accept->bind_param("ii", $friendship_id, $user_id);
    
    if ($stmt_accept->execute()) {
        echo "<script>alert('Solicitação de amizade aceita!'); window.location.href='';</script>";
    } else {
        echo "<script>alert('Erro ao aceitar a solicitação.');</script>";
    }
    $stmt_accept->close();
}

// Rejeitar solicitação de amizade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_friend'])) {
    $friendship_id = $_POST['friendship_id'];
    
    $sql_reject = "DELETE FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'";
    $stmt_reject = $mysqli->prepare($sql_reject);
    $stmt_reject->bind_param("ii", $friendship_id, $user_id);
    
    if ($stmt_reject->execute()) {
        echo "<script>alert('Solicitação de amizade rejeitada.'); window.location.href='';</script>";
    } else {
        echo "<script>alert('Erro ao rejeitar a solicitação.');</script>";
    }
    $stmt_reject->close();
}
?>
<?php
    // Obtém o IP do usuário
    $ip = file_get_contents('https://api64.ipify.org');

    
    // Faz a requisição para a API de geolocalização
    $api_url = "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,lat,lon,query";
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Inicial</title>
    <link rel="stylesheet" type="text/css" href="../../../assets/mobile.css" media="screen and (max-width: 600px)">
    <link rel="stylesheet" type="text/css" href="../../../assets/desktop.css" media="screen and (min-width: 601px)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet">
    
    <style>
        .notification-badge {
            position: relative;
        }
        
        .notification-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        
        .message-preview {
            border-left: 3px solid #007bff;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .message-preview .message-sender {
            font-weight: bold;
            color: #007bff;
        }
        
        .message-preview .message-content {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .message-preview .message-time {
            font-size: 0.8rem;
            color: #adb5bd;
            margin-top: 5px;
        }
        
        .friend-request {
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background-color: #fff;
        }
        
        .friend-request .request-actions {
            margin-top: 10px;
        }
        
        .friend-request .btn {
            margin-right: 5px;
        }
        
        .toggle-section {
            margin-bottom: 20px;
        }
        
        .calendar-container {
            min-height: 400px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../homepage/">CritMeet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="../homepage/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../Profile/">Meu Perfil</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Mais...
                        </a>
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

                <form class="d-flex" action="../friends" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Buscar amigos..." aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Buscar</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Botões de Toggle -->
        <div class="row text-center toggle-section">
            <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#friendRequests" aria-expanded="false" aria-controls="friendRequests">
                    <i class="bi bi-person-plus"></i> Solicitações de Amizade
                    <?php if (count($pending_requests) > 0): ?>
                        <span class="badge"><?php echo count($pending_requests); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-info w-100 notification-badge" data-bs-toggle="collapse" data-bs-target="#recentMessages" aria-expanded="false" aria-controls="recentMessages">
                    <i class="bi bi-chat-dots"></i> Mensagens Recentes
                    <?php if (count($recent_messages) > 0): ?>
                        <span class="badge"><?php echo count($recent_messages); ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-success w-100" data-bs-toggle="collapse" data-bs-target="#scheduledSessions" aria-expanded="false" aria-controls="scheduledSessions">
                    <i class="bi bi-calendar-event"></i> Sessões Agendadas
                </button>
            </div>
        </div>

        <!-- Seção de Solicitações de Amizade -->
        <div class="collapse mt-3" id="friendRequests">
            <div class="card card-body">
                <h5><i class="bi bi-person-plus"></i> Solicitações de Amizade Pendentes</h5>
                <?php if (count($pending_requests) > 0): ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="friend-request">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($request['name']); ?></strong>
                                    <br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($request['username']); ?></small>
                                </div>
                                <div class="request-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="friendship_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="accept_friend" class="btn btn-success btn-sm">
                                            <i class="bi bi-check-lg"></i> Aceitar
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="friendship_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="reject_friend" class="btn btn-danger btn-sm">
                                            <i class="bi bi-x-lg"></i> Rejeitar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Nenhuma solicitação de amizade pendente.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção de Mensagens Recentes -->
        <div class="collapse mt-3" id="recentMessages">
            <div class="card card-body">
                <h5><i class="bi bi-chat-dots"></i> Mensagens Recentes</h5>
                <?php if (count($recent_messages) > 0): ?>
                    <?php foreach ($recent_messages as $message): ?>
                        <div class="message-preview">
                            <div class="message-sender">
                                <?php if ($message['is_group']): ?>
                                    <i class="bi bi-people"></i> <?php echo htmlspecialchars($message['group_name']); ?> - <?php echo htmlspecialchars($message['name']); ?>
                                <?php else: ?>
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($message['name']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="message-content">
                                <?php 
                                $content = htmlspecialchars($message['content']);
                                echo strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content;
                                ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('d/m/Y H:i', strtotime($message['timestamp'])); ?>
                            </div>
                            <div class="mt-2">
                                <?php if ($message['is_group']): ?>
                                    <a href="../groupmessage/?chat_id=<?php echo $message['chat_identifier']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-reply"></i> Responder no Grupo
                                    </a>
                                <?php else: ?>
                                    <a href="../message/?friend_id=<?php echo $message['chat_identifier']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-reply"></i> Responder
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="../chat/" class="btn btn-primary">
                            <i class="bi bi-chat-square-text"></i> Ver Todos os Chats
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Nenhuma mensagem recente encontrada.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seção do Calendário -->
        <div class="collapse mt-3" id="scheduledSessions">
            <div class="card card-body">
                <h5><i class="bi bi-calendar-event"></i> Minhas Sessões Agendadas</h5>
                <div id="calendar" class="calendar-container"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        let calendar;
        let calendarInitialized = false;

        document.addEventListener('DOMContentLoaded', function () {
            // Inicializar calendário quando a seção for mostrada
            const calendarCollapse = document.getElementById('scheduledSessions');
            
            calendarCollapse.addEventListener('shown.bs.collapse', function () {
                if (!calendarInitialized) {
                    initializeCalendar();
                    calendarInitialized = true;
                } else if (calendar) {
                    // Re-renderizar se já foi inicializado
                    setTimeout(() => {
                        calendar.render();
                        calendar.updateSize();
                    }, 100);
                }
            });

            // Re-renderizar quando a janela for redimensionada
            window.addEventListener('resize', function() {
                if (calendar && calendarInitialized) {
                    setTimeout(() => {
                        calendar.updateSize();
                    }, 100);
                }
            });
        });

        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            if (calendarEl && !calendar) {
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    locale: 'pt-br',
                    height: 'auto',
                    aspectRatio: 1.8,
                    contentHeight: 400,
                    events: [
                        // Aqui você pode adicionar eventos do banco de dados
                        {
                            title: 'Sessão de RPG',
                            start: '2025-06-10T19:00:00',
                            end: '2025-06-10T23:00:00',
                            backgroundColor: '#28a745',
                            borderColor: '#28a745'
                        },
                        {
                            title: 'Campanha D&D',
                            start: '2025-06-15T20:00:00',
                            end: '2025-06-15T24:00:00',
                            backgroundColor: '#007bff',
                            borderColor: '#007bff'
                        }
                    ],
                    eventClick: function(info) {
                        alert('Evento: ' + info.event.title + '\nData: ' + info.event.start.toLocaleDateString('pt-BR'));
                    },
                    dayMaxEvents: 3,
                    moreLinkClick: 'popover',
                    eventDisplay: 'block',
                    displayEventTime: true,
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    }
                });
                
                calendar.render();
            }
        }

        // Auto-refresh para mensagens (opcional)
        setInterval(function() {
            // Aqui você pode adicionar código para atualizar as notificações em tempo real
            // Por exemplo, fazer uma requisição AJAX para buscar novas mensagens
        }, 30000); // Atualiza a cada 30 segundos
    </script>
        <div class="container">
        <h2>Sua localização</h2>
        <?php if ($data['status'] === 'success') { ?>
            <p><strong>País:</strong> <?php echo $data['country']; ?></p>
            <p><strong>Estado:</strong> <?php echo $data['regionName']; ?></p>
            <p><strong>Cidade:</strong> <?php echo $data['city']; ?></p>
            <iframe 
                width="100%" 
                height="300" 
                src="https://maps.google.com/maps?q=<?php echo $data['lat']; ?>,<?php echo $data['lon']; ?>&z=12&output=embed">
            </iframe>
        <?php } else { ?>
            <p>Não foi possível obter a localização.</p>
        <?php } ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>