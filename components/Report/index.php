<?php
// components/Report/index.php
require_once __DIR__ . '/../../config.php';
session_start();

// Se for uma requisição AJAX para processar a denúncia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    
    // Verificar se usuário está logado
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }
    
    $reporter_id = $_SESSION['user_id'];
    
    // Validar dados recebidos
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['reported_id']) || !isset($input['reason'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        exit;
    }
    
    $reported_id = (int)$input['reported_id'];
    $reason = trim($input['reason']);
    
    // Validações
    if ($reported_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do usuário inválido']);
        exit;
    }
    
    if (empty($reason) || strlen($reason) < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O motivo deve ter pelo menos 10 caracteres']);
        exit;
    }
    
    if (strlen($reason) > 1000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O motivo não pode exceder 1000 caracteres']);
        exit;
    }
    
    // Verificar se não está tentando denunciar a si mesmo
    if ($reporter_id == $reported_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Você não pode denunciar a si mesmo']);
        exit;
    }
    
    // Verificar se o usuário denunciado existe
    $check_user = "SELECT id FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($check_user);
    $stmt->bind_param("i", $reported_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    $stmt->close();
    
    // Verificar se já existe uma denúncia pendente do mesmo usuário para o mesmo alvo
    $check_existing = "SELECT id FROM reports WHERE reporter_id = ? AND reported_id = ? AND status = 'pending'";
    $stmt = $mysqli->prepare($check_existing);
    $stmt->bind_param("ii", $reporter_id, $reported_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Você já possui uma denúncia pendente para este usuário']);
        exit;
    }
    $stmt->close();
    
    // Inserir a denúncia
    $insert_report = "INSERT INTO reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($insert_report);
    $stmt->bind_param("iis", $reporter_id, $reported_id, $reason);
    if ($stmt->execute()) {
        $report_id = $mysqli->insert_id;
        $stmt->close();
       
        echo json_encode([
            'success' => true,
            'message' => 'Denúncia enviada com sucesso',
            'report_id' => $report_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    }
    $mysqli->close();
    exit;
}

// Se não for AJAX, retorna o HTML com o modal e JavaScript
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Denúncias</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Sistema de Denúncias</h2>
        
        <!-- Exemplo de botão para abrir o modal -->
        <button type="button" class="btn btn-danger" onclick="showReportModal(123, 'UsuarioTeste')">
            <i class="bi bi-flag"></i> Denunciar Usuário
        </button>
    </div>

    <!-- Modal HTML -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalLabel">
                        <i class="bi bi-flag"></i> Denunciar Usuário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="reportAlert"></div>
                    <p class="text-muted mb-3">
                        Descreva o motivo da denúncia. Esta informação será analisada pela equipe de moderação.
                    </p>
                    <form id="reportForm">
                        <input type="hidden" id="reportedUserId" name="reported_id">
                        <div class="mb-3">
                            <label for="reportReason" class="form-label">Motivo da denúncia:</label>
                            <textarea 
                                class="form-control" 
                                id="reportReason" 
                                name="reason" 
                                rows="4" 
                                placeholder="Descreva detalhadamente o motivo da denúncia (mínimo 10 caracteres)"
                                maxlength="1000"
                                required
                            ></textarea>
                            <div class="form-text">
                                <span id="charCount">0</span>/1000 caracteres
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="submitReport" disabled>
                        <i class="bi bi-flag"></i> Enviar Denúncia
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript do Modal -->
    <script>
    class ReportModal {
        constructor() {
            this.modal = null;
            this.init();
        }

        init() {
            // O modal já existe no HTML, apenas inicializar
            this.modal = new bootstrap.Modal(document.getElementById('reportModal'));
            // Adicionar event listeners
            this.bindEvents();
        }

        bindEvents() {
            const reasonTextarea = document.getElementById('reportReason');
            const charCount = document.getElementById('charCount');
            const submitBtn = document.getElementById('submitReport');
            
            // Contador de caracteres
            reasonTextarea.addEventListener('input', () => {
                const length = reasonTextarea.value.length;
                charCount.textContent = length;
                
                // Habilitar/desabilitar botão baseado no comprimento
                submitBtn.disabled = length < 10;
                
                // Mudar cor baseado no comprimento
                if (length < 10) {
                    charCount.className = 'text-danger';
                } else if (length > 900) {
                    charCount.className = 'text-warning';
                } else {
                    charCount.className = 'text-success';
                }
            });

            // Enviar denúncia
            submitBtn.addEventListener('click', () => {
                this.submitReport();
            });

            // Limpar formulário quando modal fechar
            document.getElementById('reportModal').addEventListener('hidden.bs.modal', () => {
                this.clearForm();
            });
        }

        show(userId, username = '') {
            document.getElementById('reportedUserId').value = userId;
            
            // Atualizar título com o nome do usuário se fornecido
            if (username) {
                document.getElementById('reportModalLabel').innerHTML = 
                    `<i class="bi bi-flag"></i> Denunciar ${username}`;
            }
            
            this.clearForm();
            this.modal.show();
        }

        clearForm() {
            document.getElementById('reportForm').reset();
            document.getElementById('charCount').textContent = '0';
            document.getElementById('charCount').className = '';
            document.getElementById('submitReport').disabled = true;
            this.clearAlert();
        }

        showAlert(message, type = 'danger') {
            const alertDiv = document.getElementById('reportAlert');
            alertDiv.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        }

        clearAlert() {
            document.getElementById('reportAlert').innerHTML = '';
        }

        async submitReport() {
            const submitBtn = document.getElementById('submitReport');
            const formData = {
                reported_id: parseInt(document.getElementById('reportedUserId').value),
                reason: document.getElementById('reportReason').value.trim()
            };

            // Validação do lado cliente
            if (!formData.reported_id || formData.reported_id <= 0) {
                this.showAlert('ID do usuário inválido');
                return;
            }

            if (formData.reason.length < 10) {
                this.showAlert('O motivo deve ter pelo menos 10 caracteres');
                return;
            }

            // Desabilitar botão durante envio
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    this.showAlert('Denúncia enviada com sucesso! A equipe de moderação irá analisar.', 'success');
                    
                    // Fechar modal após 2 segundos
                    setTimeout(() => {
                        this.modal.hide();
                    }, 2000);
                } else {
                    this.showAlert(result.message || 'Erro ao enviar denúncia');
                }
            } catch (error) {
                console.error('Erro:', error);
                this.showAlert('Erro de conexão. Tente novamente.');
            } finally {
                // Reabilitar botão
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-flag"></i> Enviar Denúncia';
            }
        }
    }

    // Função global para facilitar o uso
    function showReportModal(userId, username = '') {
        if (!window.reportModal) {
            window.reportModal = new ReportModal();
        }
        window.reportModal.show(userId, username);
    }

    // Inicializar quando DOM estiver pronto
    document.addEventListener('DOMContentLoaded', () => {
        window.reportModal = new ReportModal();
    });
    </script>
</body>
</html>