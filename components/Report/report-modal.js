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
                    <div class="modal-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%); border-bottom: 1px solid rgba(255,255,255,0.2);">
                        <h5 class="modal-title text-white" id="reportModalLabel">
                            <i class="bi bi-flag-fill me-2"></i> Denunciar admin
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="background-color: #fafafa;">
                        <div id="reportAlert"></div>
                        <div class="alert alert-light border-start border-4 border-info" style="background-color: #f8f9fa; border-color: #0dcaf0 !important;">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-info me-2" style="font-size: 1.2em;"></i>
                                <div>
                                    <strong class="text-dark">Importante:</strong> 
                                    <span class="text-muted">Denúncias falsas podem resultar em punições. Descreva detalhadamente o motivo da denúncia.</span>
                                </div>
                            </div>
                        </div>
                        <form id="reportForm">
                            <input type="hidden" id="reportedUserId" name="reported_id">
                            <div class="mb-3">
                                <label for="reportReason" class="form-label fw-bold text-dark">
                                    Motivo da denúncia: <span style="color: #dc3545;">*</span>
                                </label>
                                <textarea 
                                    class="form-control border-2" 
                                    id="reportReason" 
                                    name="reason" 
                                    rows="5" 
                                    placeholder="Descreva detalhadamente o comportamento inadequado ou violação das regras (mínimo 10 caracteres)..."
                                    maxlength="1000"
                                    required
                                    autocomplete="off"
                                    style="resize: vertical; min-height: 120px; border-color: #dee2e6; focus: border-color: #0d6efd;"
                                ></textarea>
                                <div class="form-text d-flex justify-content-between mt-2">
                                    <small class="text-muted">Mínimo: 10 caracteres</small>
                                    <small id="charCount" class="fw-bold">15/1000</small>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" id="submitReport" style="background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%); border: none; box-shadow: 0 2px 4px rgba(255,107,107,0.3);">
                            <i class="bi bi-send me-1"></i> Enviando...
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
        
        // Verificar se os elementos existem
        if (!reasonTextarea || !charCount || !submitBtn) {
            console.error('Elementos do modal não encontrados');
            return;
        }
        
        // Evitar múltiplos event listeners
        if (reasonTextarea.hasAttribute('data-report-listener')) return;
        reasonTextarea.setAttribute('data-report-listener', 'true');
        
        // Função para validar e atualizar estado do botão
        const validateForm = () => {
            const reasonLength = reasonTextarea.value.trim().length;
            const isValid = reasonLength >= 10 && !this.isSubmitting;
            
            submitBtn.disabled = !isValid;
            
            // Atualizar contador de caracteres
            charCount.textContent = `${reasonLength}/1000`;
            
            if (reasonLength < 10) {
                charCount.className = 'fw-bold';
                charCount.style.color = '#dc3545';
            } else if (reasonLength > 900) {
                charCount.className = 'fw-bold';
                charCount.style.color = '#fd7e14';
            } else {
                charCount.className = 'fw-bold';
                charCount.style.color = '#198754';
            }

            // Efeito visual no botão quando válido
            if (isValid) {
                submitBtn.style.transform = 'scale(1.02)';
                submitBtn.style.transition = 'all 0.2s ease';
            } else {
                submitBtn.style.transform = 'scale(1)';
            }
        };

        // Event listeners
        reasonTextarea.addEventListener('input', validateForm);
        reasonTextarea.addEventListener('paste', () => setTimeout(validateForm, 10));

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

        // Melhorar UX do textarea
        reasonTextarea.addEventListener('focus', () => {
            reasonTextarea.style.borderColor = '#0d6efd';
            reasonTextarea.style.boxShadow = '0 0 0 0.2rem rgba(13, 110, 253, 0.25)';
        });

        reasonTextarea.addEventListener('blur', () => {
            reasonTextarea.style.borderColor = '#dee2e6';
            reasonTextarea.style.boxShadow = 'none';
        });
    }

    show(userId, username = '') {
        if (!this.modal) {
            console.error('Modal não foi inicializado corretamente');
            return;
        }

        if (!userId || userId <= 0) {
            console.error('ID de usuário inválido:', userId);
            this.showAlert('Erro: ID de usuário inválido', 'danger');
            return;
        }

        document.getElementById('reportedUserId').value = userId;
        
        // Atualizar título com o nome do usuário
        const modalLabel = document.getElementById('reportModalLabel');
        if (username) {
            modalLabel.innerHTML = `<i class="bi bi-flag-fill me-2"></i> Denunciar ${this.escapeHtml(username)}`;
        } else {
            modalLabel.innerHTML = '<i class="bi bi-flag-fill me-2"></i> Denunciar admin';
        }
        
        this.clearForm();
        this.modal.show();
        
        // Focar no textarea após o modal abrir
        setTimeout(() => {
            const textarea = document.getElementById('reportReason');
            if (textarea) {
                textarea.focus();
                textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 500);
    }

    clearForm() {
        const form = document.getElementById('reportForm');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('submitReport');
        const textarea = document.getElementById('reportReason');
        
        if (form) form.reset();
        if (charCount) {
            charCount.textContent = '0/1000';
            charCount.className = 'fw-bold';
            charCount.style.color = '#6c757d';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar Denúncia';
            submitBtn.style.transform = 'scale(1)';
        }
        if (textarea) {
            textarea.style.borderColor = '#dee2e6';
            textarea.style.boxShadow = 'none';
        }
        
        // Restaurar opacidade do form
        const formElement = document.getElementById('reportForm');
        if (formElement) {
            formElement.style.opacity = '1';
            formElement.style.pointerEvents = 'auto';
        }
        
        this.isSubmitting = false;
        this.retryCount = 0;
        this.clearAlert();
    }

    showAlert(message, type = 'danger', autoHide = false) {
        const alertDiv = document.getElementById('reportAlert');
        if (!alertDiv) return;
        
        const alertId = 'alert-' + Date.now();
        const iconClass = type === 'success' ? 'bi-check-circle-fill' : 
                         type === 'warning' ? 'bi-exclamation-triangle-fill' : 
                         type === 'info' ? 'bi-info-circle-fill' : 'bi-x-circle-fill';
        
        const alertColors = {
            success: 'alert-success border-success',
            warning: 'alert-warning border-warning', 
            info: 'alert-info border-info',
            danger: 'alert-light border-danger text-danger'
        };

        alertDiv.innerHTML = `
            <div class="alert ${alertColors[type]} alert-dismissible fade show border-start border-4 mb-3" role="alert" id="${alertId}">
                <i class="bi ${iconClass} me-2"></i> ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        
        if (autoHide && type === 'success') {
            setTimeout(() => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 4000);
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

    // FIXED: Método melhorado para detectar URL da API
    getApiUrl() {
        const currentPath = window.location.pathname;
        const baseUrl = window.location.origin;
        
        // Debug: log para verificar o caminho atual
        console.log('Caminho atual:', currentPath);
        console.log('URL base:', baseUrl);
        
        // Tentar diferentes caminhos baseados na estrutura atual
        let apiPath;
        
        // Se estamos na raiz do projeto
        if (currentPath === '/' || currentPath === '/index.php') {
            apiPath = '/components/Report/api.php';
        }
        // Se estamos em uma subpasta (ex: /pages/...)
        else if (currentPath.includes('/pages/')) {
            apiPath = '/components/Report/api.php';
        }
        // Se estamos em uma pasta de componentes
        else if (currentPath.includes('/components/')) {
            apiPath = '/components/Report/api.php';
        }
        // Fallback para caminho relativo
        else {
            apiPath = '/components/Report/api.php';
        }
        
        const fullUrl = baseUrl + apiPath;
        console.log('URL da API construída:', fullUrl);
        
        return fullUrl;
    }

    async submitReport() {
        if (this.isSubmitting) return;
        
        const submitBtn = document.getElementById('submitReport');
        const reasonTextarea = document.getElementById('reportReason');
        
        const formData = {
            reported_id: parseInt(document.getElementById('reportedUserId').value),
            reason: reasonTextarea.value.trim()
        };

        // Validação final do lado cliente
        if (!formData.reported_id || formData.reported_id <= 0) {
            this.showAlert('ID do usuário inválido. Tente recarregar a página.', 'danger');
            return;
        }

        if (formData.reason.length < 10) {
            this.showAlert('O motivo deve ter pelo menos 10 caracteres.', 'warning');
            reasonTextarea.focus();
            return;
        }

        if (formData.reason.length > 1000) {
            this.showAlert('O motivo não pode exceder 1000 caracteres.', 'warning');
            return;
        }

        // Estado de carregamento
        this.isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
        
        // Limpar alertas anteriores
        this.clearAlert();

        try {
            const apiUrl = this.getApiUrl();
            console.log('Enviando denúncia para:', apiUrl);
            console.log('Dados enviados:', formData);
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                body: JSON.stringify(formData),
                credentials: 'same-origin'
            });

            console.log('Status da resposta:', response.status);
            console.log('Headers da resposta:', response.headers);

            let result;
            try {
                const responseText = await response.text();
                console.log('Resposta bruta do servidor:', responseText);
                
                // Verificar se a resposta é JSON válido
                if (responseText.trim().startsWith('{') || responseText.trim().startsWith('[')) {
                    result = JSON.parse(responseText);
                } else {
                    throw new Error('Resposta não é JSON válido: ' + responseText.substring(0, 100));
                }
            } catch (parseError) {
                console.error('Erro ao parsear JSON:', parseError);
                throw new Error('Resposta inválida do servidor. Verifique se a API está funcionando corretamente.');
            }

            if (response.ok && result.success) {
                this.showAlert(result.message || 'Denúncia enviada com sucesso!', 'success', true);
                
                // Desabilitar formulário
                const formElement = document.getElementById('reportForm');
                formElement.style.opacity = '0.7';
                formElement.style.pointerEvents = 'none';
                
                // Atualizar botão para sucesso
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Enviado!';
                submitBtn.style.background = 'linear-gradient(135deg, #198754 0%, #20c997 100%)';
                
                // Fechar modal após delay
                setTimeout(() => {
                    this.modal.hide();
                }, 3500);
                
                this.retryCount = 0;
            } else {
                // Tratar diferentes códigos de erro
                let errorMessage = result.message || 'Erro desconhecido do servidor';
                let alertType = 'danger';
                
                if (response.status === 409) {
                    errorMessage = 'Você já possui uma denúncia pendente para este usuário nas últimas 24 horas';
                    alertType = 'info';
                } else if (response.status === 401) {
                    errorMessage = 'Sessão expirada. Por favor, faça login novamente';
                    alertType = 'warning';
                    // Opcional: redirecionar para login
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 3000);
                } else if (response.status === 404) {
                    errorMessage = 'Usuário não encontrado ou API não disponível';
                    alertType = 'warning';
                } else if (response.status === 403) {
                    errorMessage = 'Acesso negado. Verifique se você está logado';
                    alertType = 'warning';
                } else if (response.status >= 500) {
                    errorMessage = 'Erro interno do servidor. Tente novamente em alguns minutos';
                    alertType = 'danger';
                }
                
                this.showAlert(errorMessage, alertType);
            }
        } catch (error) {
            console.error('Erro detalhado:', error);
            
            this.retryCount++;
            
            // Verificar tipos específicos de erro
            if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                if (this.retryCount < this.maxRetries) {
                    this.showAlert(`Erro de rede. Tentativa ${this.retryCount}/${this.maxRetries}...`, 'warning');
                    
                    // Retry com delay exponencial
                    const delay = Math.min(Math.pow(2, this.retryCount) * 1000, 5000);
                    setTimeout(() => {
                        this.submitReport();
                    }, delay);
                    return;
                } else {
                    this.showAlert('Erro de conexão persistente. Verifique sua conexão com a internet e tente novamente.', 'danger');
                }
            } else if (error.message.includes('CORS')) {
                this.showAlert('Erro de CORS. Verifique a configuração do servidor.', 'danger');
            } else if (error.message.includes('JSON')) {
                this.showAlert('Erro de formato de dados. A API pode estar retornando dados inválidos.', 'danger');
            } else {
                this.showAlert('Erro inesperado: ' + error.message, 'danger');
            }
        } finally {
            if (this.retryCount >= this.maxRetries || !this.isSubmitting) {
                this.isSubmitting = false;
                if (submitBtn.innerHTML.includes('Enviando')) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar Denúncia';
                    submitBtn.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%)';
                }
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

// Inicializar automaticamente quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    const initModal = () => {
        if (typeof bootstrap !== 'undefined') {
            if (!window.reportModal) {
                window.reportModal = new ReportModal();
                console.log('ReportModal inicializado com sucesso');
            }
        } else {
            console.warn('Bootstrap ainda não carregado, tentando novamente...');
            setTimeout(initModal, 200);
        }
    };
    
    initModal();
});

// Exportar para uso como módulo (se necessário)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReportModal;
}