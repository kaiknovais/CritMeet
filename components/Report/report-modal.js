// components/Report/report-modal.js
class ReportModal {
    constructor() {
        this.modal = null;
        this.init();
    }

    init() {
        // Criar o modal HTML
        this.createModal();
        // Adicionar event listeners
        this.bindEvents();
    }

    createModal() {
        const modalHTML = `
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
        </div>`;

        // Adicionar o modal ao body se não existir
        if (!document.getElementById('reportModal')) {
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        this.modal = new bootstrap.Modal(document.getElementById('reportModal'));
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
            const response = await fetch('../../components/Report/', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
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