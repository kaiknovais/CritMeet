<?php
class Calendar {
    private $mysqli;
    private $user_id;
    
    public function __construct($mysqli, $user_id) {
        $this->mysqli = $mysqli;
        $this->user_id = $user_id;
    }
    
    /**
     * Busca as sessões agendadas do usuário
     */
    public function getUserSessions() {
        $sessions = [];
        
        if (!$this->user_id) {
            return $sessions;
        }
        
        $sql = "SELECT s.id, s.title, s.description, s.start_datetime, s.end_datetime, 
                       s.max_players, s.current_players, s.status, s.location,
                       u.name as creator_name, u.username as creator_username, u.id as creator_id,
                       c.name as chat_name, c.id as chat_id,
                       CASE 
                           WHEN s.creator_id = ? THEN 'creator'
                           WHEN EXISTS(SELECT 1 FROM session_members WHERE session_id = s.id AND user_id = ? AND status = 'accepted') THEN 'member'
                           ELSE 'none'
                       END as user_relation
                FROM sessions s 
                JOIN users u ON s.creator_id = u.id 
                LEFT JOIN chats c ON s.chat_id = c.id
                WHERE s.id IN (
                    SELECT session_id FROM session_members WHERE user_id = ? AND status = 'accepted'
                ) OR s.creator_id = ?
                ORDER BY s.start_datetime ASC";
        
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiii", $this->user_id, $this->user_id, $this->user_id, $this->user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $sessions[] = $row;
            }
            $stmt->close();
        }
        
        return $sessions;
    }
    
    /**
     * Busca sessões próximas (próximos 7 dias)
     */
    public function getUpcomingSessions($limit = 5) {
        $sessions = [];
        
        if (!$this->user_id) {
            return $sessions;
        }
        
        $sql = "SELECT s.id, s.title, s.description, s.start_datetime, s.end_datetime, 
                       s.max_players, s.current_players, s.status, s.location,
                       u.name as creator_name, u.username as creator_username, u.id as creator_id,
                       c.name as chat_name, c.id as chat_id,
                       CASE 
                           WHEN s.creator_id = ? THEN 'creator'
                           WHEN EXISTS(SELECT 1 FROM session_members WHERE session_id = s.id AND user_id = ? AND status = 'accepted') THEN 'member'
                           ELSE 'none'
                       END as user_relation
                FROM sessions s 
                JOIN users u ON s.creator_id = u.id 
                LEFT JOIN chats c ON s.chat_id = c.id
                WHERE (s.id IN (
                    SELECT session_id FROM session_members WHERE user_id = ? AND status = 'accepted'
                ) OR s.creator_id = ?)
                AND s.start_datetime >= NOW()
                AND s.start_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND s.status = 'active'
                ORDER BY s.start_datetime ASC
                LIMIT ?";
        
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiiii", $this->user_id, $this->user_id, $this->user_id, $this->user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $sessions[] = $row;
            }
            $stmt->close();
        }
        
        return $sessions;
    }
    
    /**
     * Formata as sessões para o FullCalendar
     */
    public function getSessionsForCalendar() {
        $sessions = $this->getUserSessions();
        $events = [];
        
        foreach ($sessions as $session) {
            $color = $this->getSessionColor($session['status'], $session['user_relation']);
            
            $events[] = [
                'id' => $session['id'],
                'title' => $session['title'],
                'start' => $session['start_datetime'],
                'end' => $session['end_datetime'],
                'backgroundColor' => $color['bg'],
                'borderColor' => $color['border'],
                'textColor' => $color['text'],
                'extendedProps' => [
                    'description' => $session['description'],
                    'creator' => $session['creator_name'],
                    'location' => $session['location'],
                    'players' => $session['current_players'] . '/' . $session['max_players'],
                    'status' => $session['status'],
                    'user_relation' => $session['user_relation'],
                    'chat_name' => $session['chat_name'],
                    'chat_id' => $session['chat_id']
                ]
            ];
        }
        
        return $events;
    }
    
    /**
     * Define cores para diferentes status de sessão e relação do usuário
     */
    private function getSessionColor($status, $user_relation = 'none') {
        // Cores baseadas na relação do usuário
        if ($user_relation === 'creator') {
            return ['bg' => '#ffc107', 'border' => '#ffb300', 'text' => '#000'];
        } elseif ($user_relation === 'member') {
            return ['bg' => '#28a745', 'border' => '#20c997', 'text' => '#fff'];
        }
        
        // Cores baseadas no status
        switch ($status) {
            case 'active':
                return ['bg' => '#007bff', 'border' => '#0056b3', 'text' => '#fff'];
            case 'full':
                return ['bg' => '#17a2b8', 'border' => '#138496', 'text' => '#fff'];
            case 'cancelled':
                return ['bg' => '#dc3545', 'border' => '#c82333', 'text' => '#fff'];
            case 'completed':
                return ['bg' => '#6c757d', 'border' => '#545b62', 'text' => '#fff'];
            default:
                return ['bg' => '#007bff', 'border' => '#0056b3', 'text' => '#fff'];
        }
    }
    
    /**
     * Renderiza a seção do calendário
     */
    public function renderCalendarSection() {
        $upcomingSessions = $this->getUpcomingSessions();
        ?>
        <div class="collapse mt-3" id="scheduledSessions">
            <div class="card card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="bi bi-calendar-event"></i> Minhas Sessões Agendadas</h5>
                    <a href="../schedule/" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Agendar Nova Sessão
                    </a>
                </div>
                
                <!-- Sessões Próximas -->
                <?php if (!empty($upcomingSessions)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6><i class="bi bi-clock-history"></i> Próximas Sessões (7 dias)</h6>
                            <div class="row">
                                <?php foreach ($upcomingSessions as $session): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 <?php echo $this->getCardBorderClass($session['user_relation']); ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($session['title']); ?></h6>
                                                    <span class="badge bg-<?php echo $this->getStatusBadgeClass($session['status']); ?>">
                                                        <?php echo $this->getStatusText($session['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="card-text small text-muted mb-2">
                                                    <?php echo htmlspecialchars(substr($session['description'], 0, 60)) . (strlen($session['description']) > 60 ? '...' : ''); ?>
                                                </p>
                                                
                                                <div class="small mb-2">
                                                    <div class="mb-1"><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($session['start_datetime'])); ?></div>
                                                    <div class="mb-1"><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($session['start_datetime'])); ?> - <?php echo date('H:i', strtotime($session['end_datetime'])); ?></div>
                                                    <div class="mb-1"><i class="bi bi-people"></i> <?php echo $session['current_players']; ?>/<?php echo $session['max_players']; ?> jogadores</div>
                                                    <div class="mb-1"><i class="bi bi-person"></i> <?php echo htmlspecialchars($session['creator_name']); ?></div>
                                                    <?php if ($session['chat_name']): ?>
                                                        <div class="mb-1"><i class="bi bi-chat-dots"></i> <?php echo htmlspecialchars($session['chat_name']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($session['location']): ?>
                                                        <div><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($session['location']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mt-2">
                                                    <?php if ($session['user_relation'] === 'creator'): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-star-fill"></i> Criador
                                                        </span>
                                                    <?php elseif ($session['user_relation'] === 'member'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle"></i> Participando
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <div class="d-flex gap-2">
                                                    <a href="../sessions/view.php?id=<?php echo $session['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                                        <i class="bi bi-eye"></i> Ver Detalhes
                                                    </a>
                                                    <?php if ($session['user_relation'] === 'creator'): ?>
                                                        <a href="../sessions/edit.php?id=<?php echo $session['id']; ?>" class="btn btn-outline-warning btn-sm">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Você não tem sessões agendadas para os próximos 7 dias.
                        <a href="../schedule/" class="alert-link">Agendar uma nova sessão</a>
                    </div>
                <?php endif; ?>
                
                <!-- Calendário Completo -->
                <div class="calendar-container mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6><i class="bi bi-calendar3"></i> Calendário de Sessões</h6>
                        <div class="d-flex gap-2">
                            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Criadas por mim</span>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Participando</span>
                        </div>
                    </div>
                    <div id="calendar" style="min-height: 400px;"></div>
                </div>
                
                <!-- Resumo de Estatísticas -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="card-title text-primary"><?php echo $this->getActiveSessionsCount(); ?></h5>
                                <p class="card-text small">Sessões Ativas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="card-title text-warning"><?php echo $this->getCreatedSessionsCount(); ?></h5>
                                <p class="card-text small">Sessões Criadas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="card-title text-success"><?php echo count($upcomingSessions); ?></h5>
                                <p class="card-text small">Próximas 7 dias</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Retorna JavaScript para inicializar o calendário
     */
    public function getCalendarScript() {
        $events = json_encode($this->getSessionsForCalendar());
        
        return "
        // Inicializar calendário
        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            if (!calendarEl) return;
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia',
                    list: 'Lista'
                },
                height: 'auto',
                events: $events,
                eventClick: function(info) {
                    const event = info.event;
                    const props = event.extendedProps;
                    
                    // Determinar ícone baseado na relação do usuário
                    let relationIcon = '';
                    if (props.user_relation === 'creator') {
                        relationIcon = '<i class=\"bi bi-star-fill text-warning\"></i> ';
                    } else if (props.user_relation === 'member') {
                        relationIcon = '<i class=\"bi bi-check-circle text-success\"></i> ';
                    }
                    
                    let modalContent = `
                        <div class='modal fade' id='eventModal' tabindex='-1'>
                            <div class='modal-dialog'>
                                <div class='modal-content'>
                                    <div class='modal-header'>
                                        <h5 class='modal-title'>\${relationIcon}\${event.title}</h5>
                                        <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                                    </div>
                                    <div class='modal-body'>
                                        <div class=\"row\">
                                            <div class=\"col-sm-4\"><strong>Data:</strong></div>
                                            <div class=\"col-sm-8\">\${event.start.toLocaleDateString('pt-BR')}</div>
                                        </div>
                                        <div class=\"row\">
                                            <div class=\"col-sm-4\"><strong>Horário:</strong></div>
                                            <div class=\"col-sm-8\">\${event.start.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})} - \${event.end.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}</div>
                                        </div>
                                        <div class=\"row\">
                                            <div class=\"col-sm-4\"><strong>Criador:</strong></div>
                                            <div class=\"col-sm-8\">\${props.creator}</div>
                                        </div>
                                        <div class=\"row\">
                                            <div class=\"col-sm-4\"><strong>Jogadores:</strong></div>
                                            <div class=\"col-sm-8\">\${props.players}</div>
                                        </div>
                                        \${props.chat_name ? '<div class=\"row\"><div class=\"col-sm-4\"><strong>Grupo:</strong></div><div class=\"col-sm-8\">' + props.chat_name + '</div></div>' : ''}
                                        \${props.location ? '<div class=\"row\"><div class=\"col-sm-4\"><strong>Local:</strong></div><div class=\"col-sm-8\">' + props.location + '</div></div>' : ''}
                                        \${props.description ? '<div class=\"row mt-2\"><div class=\"col-12\"><strong>Descrição:</strong><br>' + props.description + '</div></div>' : ''}
                                        <div class=\"row mt-2\">
                                            <div class=\"col-sm-4\"><strong>Status:</strong></div>
                                            <div class=\"col-sm-8\"><span class='badge bg-primary'>\${props.status}</span></div>
                                        </div>
                                    </div>
                                    <div class='modal-footer'>
                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Fechar</button>
                                        <a href='../sessions/view.php?id=\${event.id}' class='btn btn-primary'>Ver Detalhes</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remove modal anterior se existir
                    const existingModal = document.getElementById('eventModal');
                    if (existingModal) {
                        existingModal.remove();
                    }
                    
                    // Adiciona novo modal
                    document.body.insertAdjacentHTML('beforeend', modalContent);
                    const modal = new bootstrap.Modal(document.getElementById('eventModal'));
                    modal.show();
                },
                eventDidMount: function(info) {
                    // Adicionar tooltip
                    const event = info.event;
                    const props = event.extendedProps;
                    
                    let relationText = '';
                    if (props.user_relation === 'creator') {
                        relationText = ' (Criador)';
                    } else if (props.user_relation === 'member') {
                        relationText = ' (Participando)';
                    }
                    
                    info.el.setAttribute('data-bs-toggle', 'tooltip');
                    info.el.setAttribute('data-bs-placement', 'top');
                    info.el.setAttribute('title', `\${event.title}\${relationText} - \${props.players} jogadores`);
                    
                    new bootstrap.Tooltip(info.el);
                }
            });
            
            calendar.render();
        }";
    }
    
    /**
     * Classe CSS para a borda do card baseada na relação do usuário
     */
    private function getCardBorderClass($user_relation) {
        switch ($user_relation) {
            case 'creator':
                return 'border-warning border-2';
            case 'member':
                return 'border-success border-2';
            default:
                return 'border-primary border-2';
        }
    }
    
    /**
     * Classe CSS para o badge de status
     */
    private function getStatusBadgeClass($status) {
        switch ($status) {
            case 'active':
                return 'primary';
            case 'full':
                return 'info';
            case 'cancelled':
                return 'danger';
            case 'completed':
                return 'secondary';
            default:
                return 'primary';
        }
    }
    
    /**
     * Texto do status
     */
    private function getStatusText($status) {
        switch ($status) {
            case 'active':
                return 'Ativa';
            case 'full':
                return 'Lotada';
            case 'cancelled':
                return 'Cancelada';
            case 'completed':
                return 'Concluída';
            default:
                return 'Ativa';
        }
    }
    
    /**
     * Conta o número de sessões ativas do usuário
     */
    public function getActiveSessionsCount() {
        if (!$this->user_id) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM sessions s 
                WHERE (s.id IN (
                    SELECT session_id FROM session_members WHERE user_id = ? AND status = 'accepted'
                ) OR s.creator_id = ?)
                AND s.start_datetime >= NOW()
                AND s.status = 'active'";
        
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $this->user_id, $this->user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['count'];
        }
        
        return 0;
    }
    
    /**
     * Conta o número de sessões criadas pelo usuário
     */
    public function getCreatedSessionsCount() {
        if (!$this->user_id) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM sessions s 
                WHERE s.creator_id = ?
                AND s.start_datetime >= NOW()
                AND s.status IN ('active', 'full')";
        
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $this->user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['count'];
        }
        
        return 0;
    }
}
?>