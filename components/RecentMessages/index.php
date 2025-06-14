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
        
        // Query para buscar a mensagem mais recente de cada chat onde o usuário não foi o remetente
        // e que seja mais nova que a última vez que o usuário visualizou o chat
        $sql_messages = "SELECT 
                            m.content, 
                            m.timestamp, 
                            u.username, 
                            u.name, 
                            c.is_group, 
                            c.name as group_name,
                            c.id as chat_id,
                            CASE 
                                WHEN c.is_group = 1 THEN c.id
                                ELSE (SELECT user_id FROM chat_members cm2 WHERE cm2.chat_id = c.id AND cm2.user_id != ? LIMIT 1)
                            END as chat_identifier
                         FROM messages m
                         JOIN users u ON m.sender_id = u.id
                         JOIN chats c ON m.chat_id = c.id
                         JOIN chat_members cm ON c.id = cm.chat_id
                         WHERE cm.user_id = ? 
                           AND m.sender_id != ?
                           AND m.timestamp > COALESCE(
                               (SELECT last_seen FROM chat_members cm3 
                                WHERE cm3.chat_id = c.id AND cm3.user_id = ?), 
                               '1970-01-01 00:00:00'
                           )
                           AND m.id = (
                               SELECT MAX(m2.id) 
                               FROM messages m2 
                               WHERE m2.chat_id = c.id 
                                 AND m2.sender_id != ?
                                 AND m2.timestamp > COALESCE(
                                     (SELECT last_seen FROM chat_members cm4 
                                      WHERE cm4.chat_id = c.id AND cm4.user_id = ?), 
                                     '1970-01-01 00:00:00'
                                 )
                           )
                         ORDER BY m.timestamp DESC
                         LIMIT 5";
        
        $stmt = $this->mysqli->prepare($sql_messages);
        $stmt->bind_param("iiiiii", 
            $this->user_id, 
            $this->user_id, 
            $this->user_id, 
            $this->user_id, 
            $this->user_id, 
            $this->user_id
        );
        $stmt->execute();
        $result_messages = $stmt->get_result();
        
        while ($row = $result_messages->fetch_assoc()) {
            $recent_messages[] = $row;
        }
        $stmt->close();
        
        return $recent_messages;
    }
    
    /**
     * Atualiza o timestamp de última visualização do usuário no chat
     */
    public function markChatAsRead($chat_id) {
        if (!$this->user_id || !$chat_id) return false;
        
        $sql = "UPDATE chat_members SET last_seen = NOW() WHERE user_id = ? AND chat_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ii", $this->user_id, $chat_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Versão alternativa se não quisermos modificar a estrutura da tabela
     * Usa uma abordagem baseada apenas em timestamp das mensagens
     */
    public function getRecentMessagesSimple() {
        if (!$this->user_id) return [];
        
        $recent_messages = [];
        
        // Busca a mensagem mais recente de cada chat, excluindo mensagens muito antigas (últimas 24h por exemplo)
        $sql_messages = "SELECT 
                            m.content, 
                            m.timestamp, 
                            u.username, 
                            u.name, 
                            c.is_group, 
                            c.name as group_name,
                            c.id as chat_id,
                            CASE 
                                WHEN c.is_group = 1 THEN c.id
                                ELSE (SELECT user_id FROM chat_members cm2 WHERE cm2.chat_id = c.id AND cm2.user_id != ? LIMIT 1)
                            END as chat_identifier
                         FROM messages m
                         JOIN users u ON m.sender_id = u.id
                         JOIN chats c ON m.chat_id = c.id
                         JOIN chat_members cm ON c.id = cm.chat_id
                         WHERE cm.user_id = ? 
                           AND m.sender_id != ?
                           AND m.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                           AND m.id = (
                               SELECT MAX(m2.id) 
                               FROM messages m2 
                               WHERE m2.chat_id = c.id 
                                 AND m2.sender_id != ?
                                 AND m2.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                           )
                         ORDER BY m.timestamp DESC
                         LIMIT 5";
        
        $stmt = $this->mysqli->prepare($sql_messages);
        $stmt->bind_param("iiii", $this->user_id, $this->user_id, $this->user_id, $this->user_id);
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
                position: relative;
            }
            
            .message-preview.unread {
                border-left-color: #28a745;
                background-color: #f0fff4;
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
            
            .unread-indicator {
                position: absolute;
                top: 8px;
                right: 8px;
                width: 8px;
                height: 8px;
                background-color: #28a745;
                border-radius: 50%;
            }
        </style>
        
        <!-- Seção de Mensagens Recentes -->
        <div class="collapse mt-3" id="recentMessages">
            <div class="card card-body">
                <h5><i class="bi bi-chat-dots"></i> Mensagens Não Lidas</h5>
                <?php if (count($recent_messages) > 0): ?>
                    <?php foreach ($recent_messages as $message): ?>
                        <div class="message-preview unread">
                            <div class="unread-indicator"></div>
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
                                    <a href="../groupmessage/?chat_id=<?php echo $message['chat_identifier']; ?>" 
                                       class="btn btn-outline-primary btn-sm"
                                       onclick="markAsRead(<?php echo $message['chat_id']; ?>)">
                                        <i class="bi bi-reply"></i> Responder no Grupo
                                    </a>
                                <?php else: ?>
                                    <a href="../message/?friend_id=<?php echo $message['chat_identifier']; ?>" 
                                       class="btn btn-outline-primary btn-sm"
                                       onclick="markAsRead(<?php echo $message['chat_id']; ?>)">
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
                    <p class="text-muted">
                        <i class="bi bi-check-circle text-success"></i> 
                        Nenhuma mensagem não lida encontrada.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function markAsRead(chatId) {
            // Opcional: fazer uma requisição AJAX para marcar como lido
            fetch('../../components/RecentMessages/mark_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    chat_id: chatId
                })
            }).catch(err => console.log('Erro ao marcar como lido:', err));
        }
        </script>
        <?php
    }
}
?>