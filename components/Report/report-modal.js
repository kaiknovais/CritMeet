class ReportModal {
    constructor() {
        this.modal = null;
        this.isInitialized = false;
        this.isSubmitting = false;
        this.retryCount = 0;
        this.maxRetries = 3;
        this.init();
    }

    init() {
        if (this.isInitialized) return;
        
        this.createModal();
        this.bindEvents();
        this.isInitialized = true;
    }

    createModal() {
        // Verificar se modal já existe
        const existingModal = document.getElementById('reportModal');
        if (existingModal) {
            this.modal = new bootstrap.Modal(existingModal);
            return;
        }

        const modalHTML = `
        <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="reportModalLabel">
                            <i class="bi bi-flag"></i> Denunciar Usuário
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="reportAlert"></div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Importante:</strong> Denúncias falsas podem resultar em punições. 
                            Descreva detalhadamente o motivo da denúncia.
                        </div>
                        <form id="reportForm">
                            <input type="hidden" id="reportedUserId" name="reported_id">
                            <div class="mb-3">
                                <label for="reportReason" class="form-label fw-bold">
                                    Motivo da denúncia: <span class="text-danger">*</span>
                                </label>
                                <textarea 
                                    class="form-control" 
                                    id="reportReason" 
                                    name="reason" 
                                    rows="5" 
                                    placeholder="Descreva detalhadamente o comportamento inadequado ou violação das regras (mínimo 10 caracteres)..."
                                    maxlength="1000"
                                    required
                                    autocomplete="off"
                                ></textarea>
                                <div class="form-text d-flex justify-content-between">
                                    <span>Mínimo: 10 caracteres</span>
                                    <span id="charCount" class="fw-bold">0</span>/1000
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmReport" required>
                                    <label class="form-check-label" for="confirmReport">
                                        Confirmo que li as informações acima e que esta denúncia é verdadeira
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-danger" id="submitReport" disabled>
                            <i class="bi bi-flag"></i> Enviar Denúncia
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = new bootstrap.Modal(document.getElementById('reportModal'));
    }

    bindEvents() {
        const reasonTextarea = document.getElementById('reportReason');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('submitReport');
        const confirmCheck = document.getElementById('confirmReport');
        
        // Evitar múltiplos event listeners
        if (reasonTextarea.hasAttribute('data-report-listener')) return;
        reasonTextarea.setAttribute('data-report-listener', 'true');
        
        // Função para validar e atualizar estado do botão
        const validateForm = () => {
            const reasonLength = reasonTextarea.value.length;
            const isConfirmed = confirmCheck.checked;
            const isValid = reasonLength >= 10 && isConfirmed && !this.isSubmitting;
            
            submitBtn.disabled = !isValid;
            
            // Atualizar contador de caracteres
            charCount.textContent = reasonLength;
            
            if (reasonLength < 10) {
                charCount.className = 'fw-bold text-danger';
            } else if (reasonLength > 900) {
                charCount.className = 'fw-bold text-warning';
            } else {
                charCount.className = 'fw-bold text-success';
            }
        };

        // Event listeners
        reasonTextarea.addEventListener('input', validateForm);
        reasonTextarea.addEventListener('paste', () => setTimeout(validateForm, 10));
        confirmCheck.addEventListener('change', validateForm);

        // Enviar denúncia
        submitBtn.addEventListener('click', () => this.submitReport());

        // Limpar formulário quando modal fechar
        document.getElementById('reportModal').addEventListener('hidden.bs.modal', () => {
            this.clearForm();
        });

        // Tecla Enter + Ctrl/Cmd para enviar
        reasonTextarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                if (!submitBtn.disabled) {
                    this.submitReport();
                }
            }
        });

        // Escapar modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal._isShown) {
                this.modal.hide();
            }
        });
    }

    show(userId, username = '') {
        if (!this.modal) {
            console.error('Modal não foi inicializado corretamente');
            return;
        }

        if (!userId || userId <= 0) {
            console.error('ID de usuário inválido:', userId);
            return;
        }

        document.getElementById('reportedUserId').value = userId;
        
        // Atualizar título com o nome do usuário
        const modalLabel = document.getElementById('reportModalLabel');
        if (username) {
            modalLabel.innerHTML = `<i class="bi bi-flag"></i> Denunciar ${this.escapeHtml(username)}`;
        } else {
            modalLabel.innerHTML = '<i class="bi bi-flag"></i> Denunciar Usuário';
        }
        
        this.clearForm();
        this.modal.show();
        
        // Focar no textarea após o modal abrir
        setTimeout(() => {
            document.getElementById('reportReason').focus();
        }, 500);
    }

    clearForm() {
        const form = document.getElementById('reportForm');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('submitReport');
        
        if (form) form.reset();
        if (charCount) {
            charCount.textContent = '0';
            charCount.className = 'fw-bold';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-flag"></i> Enviar Denúncia';
        }
        
        this.isSubmitting = false;
        this.retryCount = 0;
        this.clearAlert();
    }

    showAlert(message, type = 'danger', autoHide = false) {
        const alertDiv = document.getElementById('reportAlert');
        if (!alertDiv) return;
        
        const alertId = 'alert-' + Date.now();
        const iconClass = type === 'success' ? 'bi-check-circle' : 
                         type === 'warning' ? 'bi-exclamation-triangle' : 'bi-x-circle';
        
        alertDiv.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                <i class="bi ${iconClass}"></i> ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        
        if (autoHide && type === 'success') {
            setTimeout(() => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 3000);
        }
    }

    clearAlert() {
        const alertDiv = document.getElementById('reportAlert');
        if (alertDiv) alertDiv.innerHTML = '';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getApiUrl() {
        const currentPath = window.location.pathname;
        const pathSegments = currentPath.split('/').filter(Boolean);
        
        // Determinar a profundidade baseada na estrutura de pastas
        let depth = 0;
        
        if (pathSegments.includes('pages')) {
            const pagesIndex = pathSegments.indexOf('pages');
            depth = pathSegments.length - pagesIndex - 1;
        } else if (pathSegments.includes('components')) {
            depth = 1;
        }
        
        const basePath = '../'.repeat(depth);
        return `${basePath}components/Report/api.php`;
    }

    async submitReport() {
        if (this.isSubmitting) return;
        
        const submitBtn = document.getElementById('submitReport');
        const formData = {
            reported_id: parseInt(document.getElementById('reportedUserId').value),
            reason: document.getElementById('reportReason').value.trim()
        };

        // Validação final do lado cliente
        if (!formData.reported_id || formData.reported_id <= 0) {
            this.showAlert('ID do usuário inválido. Tente recarregar a página.');
            return;
        }

        if (formData.reason.length < 10) {
            this.showAlert('O motivo deve ter pelo menos 10 caracteres.');
            document.getElementById('reportReason').focus();
            return;
        }

        if (formData.reason.length > 1000) {
            this.showAlert('O motivo não pode exceder 1000 caracteres.');
            return;
        }

        if (!document.getElementById('confirmReport').checked) {
            this.showAlert('Você deve confirmar que a denúncia é verdadeira.');
            return;
        }

        // Estado de carregamento
        this.isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';

        try {
            const apiUrl = this.getApiUrl();
            console.log('Enviando denúncia para:', apiUrl);
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData),
                credentials: 'same-origin'
            });

            // Verificar se a resposta é JSON válida
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error(`Resposta inválida do servidor (${response.status})`);
            }

            const result = await response.json();

            if (response.ok && result.success) {
                this.showAlert(result.message || 'Denúncia enviada com sucesso!', 'success', true);
                
                // Desabilitar formulário
                document.getElementById('reportForm').style.opacity = '0.6';
                document.getElementById('reportForm').style.pointerEvents = 'none';
                
                // Fechar modal após 3 segundos
                setTimeout(() => {
                    this.modal.hide();
                }, 3000);
                
                this.retryCount = 0;
            } else {
                // Erro do servidor
                let errorMessage = result.message || 'Erro desconhecido do servidor';
                
                if (response.status === 409) {
                    errorMessage = 'Você já possui uma denúncia pendente para este usuário';
                } else if (response.status === 401) {
                    errorMessage = 'Sessão expirada. Por favor, faça login novamente';
                } else if (response.status >= 500) {
                    errorMessage = 'Erro interno do servidor. Tente novamente em alguns minutos';
                }
                
                this.showAlert(errorMessage);
            }
        } catch (error) {
            console.error('Erro ao enviar denúncia:', error);
            
            this.retryCount++;
            
            if (this.retryCount < this.maxRetries) {
                this.showAlert(`Erro de conexão. Tentativa ${this.retryCount}/${this.maxRetries}. Tentando novamente...`, 'warning');
                
                // Retry com delay exponencial
                const delay = Math.pow(2, this.retryCount) * 1000;
                setTimeout(() => {
                    this.submitReport();
                }, delay);
                return;
            } else {
                this.showAlert('Erro de conexão. Verifique sua internet e tente novamente em alguns minutos.');
            }
        } finally {
            if (this.retryCount >= this.maxRetries || !this.isSubmitting) {
                // Restaurar botão apenas se não for retry
                this.isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-flag"></i> Enviar Denúncia';
            }
        }
    }
}

// Função global para facilitar o uso
window.showReportModal = function(userId, username = '') {
    if (!window.reportModal) {
        window.reportModal = new ReportModal();
    }
    window.reportModal.show(userId, username);
};

// Inicializar automaticamente quando Bootstrap estiver disponível
document.addEventListener('DOMContentLoaded', () => {
    // Aguardar Bootstrap carregar
    const initModal = () => {
        if (typeof bootstrap !== 'undefined') {
            if (!window.reportModal) {
                window.reportModal = new ReportModal();
            }
        } else {
            setTimeout(initModal, 100);
        }
    };
    
    initModal();
});

// Exportar para uso como módulo (se necessário)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReportModal;
}