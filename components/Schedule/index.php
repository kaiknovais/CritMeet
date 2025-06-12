<?php
class Schedule {
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
        
        // Query para buscar sessões do usuário
        // Adapte conforme sua estrutura de banco de dados
        $sql = "SELECT s.id, s.title, s.description, s.start_datetime, s.end_datetime, 
                       s.max_players, s.current_players, s.status, s.location,
                       u.name as creator_name, u.username as creator_username
                FROM sessions s 
                JOIN users u ON s.creator_id = u.id 
                WHERE s.id IN (
                    SELECT session_id FROM session_participants WHERE user_id = ? AND status = 'accepted'
                ) OR s.creator_id = ?
                ORDER BY s.start_datetime ASC";
        
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $this->user_id, $this->user_id);
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
                       u.name as creator_name, u.username as creator_username
                FROM sessions s 
                JOIN users u ON s.creator_id = u.id 
                WHERE (s.id IN (
                    SELECT session_id FROM session_participants WHERE user_id = ? AND status = 'accepted'
                ) OR s.creator_id = ?)
                AND s.start_datetime >= NOW()
                AND s.start_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND s.status = 'active'
                ORDER BY s.start_datetime ASC
                LIMIT ?";
        
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iii", $this->user_id, $this->user_id, $limit);
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
     * Alias for getUpcomingSessions() - for compatibility with homepage
     */
    public function getScheduled($limit = 5) {
        return $this->getUpcomingSessions($limit);
    }
    
    /**
     * Formata as sessões para o FullCalendar
     */
    public function getSessionsForCalendar() {
        $sessions = $this->getUserSessions();
        $events = [];
        
        foreach ($sessions as $session) {
            $color = $this->getSessionColor($session['status']);
            
            $events[] = [
                'id' => $session['id'],
                'title' => $session['title'],
                'start' => $session['start_datetime'],
                'end' => $session['end_datetime'],
                'backgroundColor' => $color['bg'],
                'borderColor' => $color['border'],
                'extendedProps' => [
                    'description' => $session['description'],
                    'creator' => $session['creator_name'],
                    'location' => $session['location'],
                    'players' => $session['current_players'] . '/' . $session['max_players'],
                    'status' => $session['status']
                ]
            ];
        }
        
        return $events;
    }
    
    /**
     * Define cores para diferentes status de sessão
     */
    private function getSessionColor($status) {
        switch ($status) {
            case 'active':
                return ['bg' => '#007bff', 'border' => '#007bff'];
            case 'full':
                return ['bg' => '#28a745', 'border' => '#28a745'];
            case 'cancelled':
                return ['bg' => '#dc3545', 'border' => '#dc3545'];
            case 'completed':
                return ['bg' => '#6c757d', 'border' => '#6c757d'];
            default:
                return ['bg' => '#007bff', 'border' => '#007bff'];
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
                <h5><i class="bi bi-calendar-event"></i> Minhas Sessões Agendadas</h5>
                
                <!-- Sessões Próximas -->
                <?php if (!empty($upcomingSessions)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6><i class="bi bi-clock"></i> Próximas Sessões (7 dias)</h6>
                            <div class="row">
                                <?php foreach ($upcomingSessions as $session): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 border-start border-primary border-3">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($session['title']); ?></h6>
                                                <p class="card-text small text-muted">
                                                    <?php echo htmlspecialchars(substr($session['description'], 0, 60)) . (strlen($session['description']) > 60 ? '...' : ''); ?>
                                                </p>
                                                <div class="small">
                                                    <div><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($session['start_datetime'])); ?></div>
                                                    <div><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($session['start_datetime'])); ?> - <?php echo date('H:i', strtotime($session['end_datetime'])); ?></div>
                                                    <div><i class="bi bi-people"></i> <?php echo $session['current_players']; ?>/<?php echo $session['max_players']; ?> jogadores</div>
                                                    <?php if ($session['location']): ?>
                                                        <div><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($session['location']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-<?php echo $session['status'] === 'active' ? 'primary' : ($session['status'] === 'full' ? 'success' : 'secondary'); ?>">
                                                        <?php echo ucfirst($session['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <a href="../sessions/view.php?id=<?php echo $session['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye"></i> Ver Detalhes
                                                </a>
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
                    </div>
                <?php endif; ?>
                
                <!-- Calendário Completo -->
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="text-center mt-3">
                    <a href="../sessions/create.php" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle"></i> Nova Sessão
                    </a>
                    <a href="../sessions/" class="btn btn-primary">
                        <i class="bi bi-list"></i> Todas as Sessões
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Generic render method for compatibility
     */
    public function render($sessions = null) {
        if ($sessions === null) {
            $this->renderCalendarSection();
        } else {
            // Custom render with provided sessions
            ?>
            <div class="collapse mt-3" id="scheduledSessions">
                <div class="card card-body">
                    <h5><i class="bi bi-calendar-event"></i> Sessões Agendadas</h5>
                    
                    <?php if (!empty($sessions)): ?>
                        <div class="row">
                            <?php foreach ($sessions as $session): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100 border-start border-primary border-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($session['title']); ?></h6>
                                            <p class="card-text small text-muted">
                                                <?php echo htmlspecialchars(substr($session['description'], 0, 60)) . (strlen($session['description'] ?? '') > 60 ? '...' : ''); ?>
                                            </p>
                                            <div class="small">
                                                <div><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($session['start_datetime'])); ?></div>
                                                <div><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($session['start_datetime'])); ?> - <?php echo date('H:i', strtotime($session['end_datetime'])); ?></div>
                                                <div><i class="bi bi-people"></i> <?php echo $session['current_players']; ?>/<?php echo $session['max_players']; ?> jogadores</div>
                                                <?php if (!empty($session['location'])): ?>
                                                    <div><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($session['location']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="badge bg-<?php echo $session['status'] === 'active' ? 'primary' : ($session['status'] === 'full' ? 'success' : 'secondary'); ?>">
                                                    <?php echo ucfirst($session['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="../sessions/view.php?id=<?php echo $session['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye"></i> Ver Detalhes
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Não há sessões agendadas.
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="../sessions/create.php" class="btn btn-success me-2">
                            <i class="bi bi-plus-circle"></i> Nova Sessão
                        </a>
                        <a href="../sessions/" class="btn btn-primary">
                            <i class="bi bi-list"></i> Todas as Sessões
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }
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
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
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
                    
                    let modalContent = `
                        <div class='modal fade' id='eventModal' tabindex='-1'>
                            <div class='modal-dialog'>
                                <div class='modal-content'>
                                    <div class='modal-header'>
                                        <h5 class='modal-title'>\${event.title}</h5>
                                        <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                                    </div>
                                    <div class='modal-body'>
                                        <p><strong>Data:</strong> \${event.start.toLocaleDateString('pt-BR')}</p>
                                        <p><strong>Horário:</strong> \${event.start.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})} - \${event.end.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}</p>
                                        <p><strong>Criador:</strong> \${props.creator}</p>
                                        <p><strong>Jogadores:</strong> \${props.players}</p>
                                        \${props.location ? '<p><strong>Local:</strong> ' + props.location + '</p>' : ''}
                                        \${props.description ? '<p><strong>Descrição:</strong> ' + props.description + '</p>' : ''}
                                        <p><strong>Status:</strong> <span class='badge bg-primary'>\${props.status}</span></p>
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
                dateClick: function(info) {
                    if (confirm('Deseja criar uma nova sessão para ' + info.dateStr + '?')) {
                        window.location.href = '../sessions/create.php?date=' + info.dateStr;
                    }
                },
                eventDidMount: function(info) {
                    // Adicionar tooltip
                    const event = info.event;
                    const props = event.extendedProps;
                    
                    info.el.setAttribute('data-bs-toggle', 'tooltip');
                    info.el.setAttribute('data-bs-placement', 'top');
                    info.el.setAttribute('title', `\${event.title} - \${props.players} jogadores`);
                    
                    new bootstrap.Tooltip(info.el);
                }
            });
            
            calendar.render();
        }";
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
                    SELECT session_id FROM session_participants WHERE user_id = ? AND status = 'accepted'
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
}
?>