class ReportModal {
    constructor() {
        this.modal = null;
        this.isInitialized = false;
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
        if (document.getElementById('reportModal')) {
            this.modal = new bootstrap.Modal(document.getElementById('reportModal'));
            return;
        }

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

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = new bootstrap.Modal(document.getElementById('reportModal'));
    }

    bindEvents() {
        const reasonTextarea = document.getElementById('reportReason');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('submitReport');
        
        // Evitar múltiplos event listeners
        if (reasonTextarea.hasAttribute('data-report-listener')) return;
        reasonTextarea.setAttribute('data-report-listener', 'true');
        
        // Contador de caracteres
        reasonTextarea.addEventListener('input', () => {
            const length = reasonTextarea.value.length;
            charCount.textContent = length;
            
            submitBtn.disabled = length < 10;
            
            if (length < 10) {
                charCount.className = 'text-danger';
            } else if (length > 900) {
                charCount.className = 'text-warning';
            } else {
                charCount.className = 'text-success';
            }
        });

        // Enviar denúncia
        submitBtn.addEventListener('click', () => this.submitReport());

        // Limpar formulário quando modal fechar
        document.getElementById('reportModal').addEventListener('hidden.bs.modal', () => {
            this.clearForm();
        });

        // Tecla Enter no textarea não deve submeter
        reasonTextarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                if (!submitBtn.disabled) {
                    this.submitReport();
                }
            }
        });
    }

    show(userId, username = '') {
        if (!this.modal) {
            console.error('Modal não inicializado');
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
    }

    clearForm() {
        const form = document.getElementById('reportForm');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('submitReport');
        
        if (form) form.reset();
        if (charCount) {
            charCount.textContent = '0';
            charCount.className = '';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-flag"></i> Enviar Denúncia';
        }
        this.clearAlert();
    }

    showAlert(message, type = 'danger') {
        const alertDiv = document.getElementById('reportAlert');
        if (!alertDiv) return;
        
        alertDiv.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
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

        if (formData.reason.length > 1000) {
            this.showAlert('O motivo não pode exceder 1000 caracteres');
            return;
        }

        // Estado de carregamento
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';

        try {
            // Detectar caminho correto baseado na URL atual
            const basePath = this.getBasePath();
            const apiUrl = `${basePath}/components/Report/api.php`;
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

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
            console.error('Erro ao enviar denúncia:', error);
            this.showAlert('Erro de conexão. Verifique sua internet e tente novamente.');
        } finally {
            // Restaurar botão
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-flag"></i> Enviar Denúncia';
        }
    }

    getBasePath() {
        const path = window.location.pathname;
        const segments = path.split('/').filter(segment => segment);
        
        // Encontrar o índice de 'pages' ou 'components'
        const pagesIndex = segments.indexOf('pages');
        const componentsIndex = segments.indexOf('components');
        
        if (pagesIndex !== -1) {
            // Está em uma página, voltar ao root
            const depth = segments.length - pagesIndex - 1;
            return '../'.repeat(depth);
        } else if (componentsIndex !== -1) {
            // Está em um componente
            return '../';
        }
        
        // Fallback
        return '../../';
    }
}

// Função global para facilitar o uso
window.showReportModal = function(userId, username = '') {
    if (!window.reportModal) {
        window.reportModal = new ReportModal();
    }
    window.reportModal.show(userId, username);
};

// Inicializar automaticamente se Bootstrap estiver disponível
if (typeof bootstrap !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.reportModal) {
            window.reportModal = new ReportModal();
        }
    });
}