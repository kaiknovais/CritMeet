<?php

class RecentMessages {
    private $mysqli;
    private $user_id;
    
    public function __construct($mysqli, $user_id) {
        $this->mysqli = $mysqli;
        $this->user_id = $user_id;
    }
    
    public function getRecentMessages() {
        if (!$this->user_id) return [];
        
        $recent_messages = [];
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
        
        $stmt = $this->mysqli->prepare($sql_messages);
        $stmt->bind_param("iii", $this->user_id, $this->user_id, $this->user_id);
        $stmt->execute();
        $result_messages = $stmt->get_result();
        
        while ($row = $result_messages->fetch_assoc()) {
            $recent_messages[] = $row;
        }
        $stmt->close();
        
        return $recent_messages;
    }
    
    public function render($recent_messages) {
        ?>
        <style>
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
        </style>
        
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
        <?php
    }
}
?>