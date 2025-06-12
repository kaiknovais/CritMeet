<?php

class FriendRequest {
    private $mysqli;
    private $user_id;
    
    public function __construct($mysqli, $user_id) {
        $this->mysqli = $mysqli;
        $this->user_id = $user_id;
    }
    
    public function getPendingRequests() {
        if (!$this->user_id) return [];
        
        $pending_requests = [];
        $sql_requests = "SELECT f.id, u.username, u.name 
                         FROM friends f 
                         JOIN users u ON f.user_id = u.id 
                         WHERE f.friend_id = ? AND f.status = 'pending'";
        
        $stmt = $this->mysqli->prepare($sql_requests);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result_requests = $stmt->get_result();
        
        while ($row = $result_requests->fetch_assoc()) {
            $pending_requests[] = $row;
        }
        $stmt->close();
        
        return $pending_requests;
    }
    
    public function acceptRequest($friendship_id) {
        $sql_accept = "UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ? AND status = 'pending'";
        $stmt_accept = $this->mysqli->prepare($sql_accept);
        $stmt_accept->bind_param("ii", $friendship_id, $this->user_id);
        
        if ($stmt_accept->execute()) {
            echo "<script>alert('Solicitação de amizade aceita!'); window.location.href='';</script>";
        } else {
            echo "<script>alert('Erro ao aceitar a solicitação.');</script>";
        }
        $stmt_accept->close();
    }
    
    public function rejectRequest($friendship_id) {
        $sql_reject = "DELETE FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'";
        $stmt_reject = $this->mysqli->prepare($sql_reject);
        $stmt_reject->bind_param("ii", $friendship_id, $this->user_id);
        
        if ($stmt_reject->execute()) {
            echo "<script>alert('Solicitação de amizade rejeitada.'); window.location.href='';</script>";
        } else {
            echo "<script>alert('Erro ao rejeitar a solicitação.');</script>";
        }
        $stmt_reject->close();
    }
    
    public function render($pending_requests) {
        ?>
        <style>
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
        </style>
        
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
        <?php
    }
}
?>