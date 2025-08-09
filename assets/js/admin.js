/**
 * VINDE - Admin Panel JavaScript
 * Funcionalidades específicas do painel administrativo
 */

// Inicialização do admin
document.addEventListener('DOMContentLoaded', function() {
    initializeAdminPanel();
});

/**
 * Inicialização principal do painel admin
 */
function initializeAdminPanel() {
    initializeAdminSidebar();
    initializeAdminTables();
    initializeAdminForms();
    initializeAdminFilters();
    initializeAdminModals();
    initializeAdminCharts();
    initializeCacheBtn();
    
    console.log('Vinde Admin Panel - Initialized');
}

/**
 * Funcionalidades da sidebar
 */
function initializeAdminSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            
            // Criar overlay se não existir
            if (!overlay && window.innerWidth <= 768) {
                const newOverlay = document.createElement('div');
                newOverlay.className = 'sidebar-overlay';
                document.body.appendChild(newOverlay);
                
                newOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    newOverlay.remove();
                });
            }
        });
    }
    
    // Marcar link ativo
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath || 
            currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
    
    // Fechar sidebar ao clicar em link (mobile)
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('mobile-open');
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) overlay.remove();
            }
        });
    });
}

/**
 * Funcionalidades das tabelas
 */
function initializeAdminTables() {
    // Confirmação para exclusões
    const deleteButtons = document.querySelectorAll('.btn-table.delete, .btn-delete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemName = this.dataset.name || 'este item';
            const confirmMessage = `Tem certeza que deseja excluir "${itemName}"? Esta ação não pode ser desfeita.`;
            
            if (confirm(confirmMessage)) {
                // Se for um link, navegar para a URL
                if (this.tagName === 'A') {
                    window.location.href = this.href;
                } else if (this.tagName === 'BUTTON') {
                    // Se for um botão, submeter o formulário pai
                    const form = this.closest('form');
                    if (form) form.submit();
                }
            }
        });
    });
    
    // Seleção múltipla em tabelas
    const selectAllCheckbox = document.querySelector('#selectAll');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    
    if (selectAllCheckbox && itemCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
        });
        
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
                selectAllCheckbox.checked = checkedCount === itemCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < itemCheckboxes.length;
                updateBulkActions();
            });
        });
    }
    
    // Ordenação de tabelas
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.dataset.column;
            const currentOrder = this.dataset.order || 'asc';
            const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            
            // Atualizar URL com parâmetros de ordenação
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('order', newOrder);
            window.location.href = url.toString();
        });
    });
}

/**
 * Atualizar ações em lote
 */
function updateBulkActions() {
    const checkedItems = document.querySelectorAll('.item-checkbox:checked');
    const bulkActions = document.querySelector('.bulk-actions');
    
    if (bulkActions) {
        if (checkedItems.length > 0) {
            bulkActions.style.display = 'flex';
            bulkActions.querySelector('.selected-count').textContent = checkedItems.length;
        } else {
            bulkActions.style.display = 'none';
        }
    }
}

/**
 * Funcionalidades dos formulários
 */
function initializeAdminForms() {
    // Auto-save de rascunhos
    const forms = document.querySelectorAll('.admin-form[data-autosave]');
    
    forms.forEach(form => {
        const formId = form.dataset.autosave;
        
        // Carregar dados salvos
        loadFormDraft(form, formId);
        
        // Salvar automaticamente
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', debounce(() => {
                saveFormDraft(form, formId);
            }, 1000));
        });
        
        // Limpar draft ao submeter
        form.addEventListener('submit', () => {
            clearFormDraft(formId);
        });
    });
    
    // Validação avançada
    const requiredFields = document.querySelectorAll('.required');
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
    });
    
    // Preview de imagens
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            previewImage(this);
        });
    });
    
    // Contadores de caracteres
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(textarea => {
        createCharacterCounter(textarea);
    });
}

/**
 * Salvar rascunho do formulário
 */
function saveFormDraft(form, formId) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    localStorage.setItem(`form_draft_${formId}`, JSON.stringify(data));
    
    // Mostrar indicador de salvamento
    showSaveIndicator();
}

/**
 * Carregar rascunho do formulário
 */
function loadFormDraft(form, formId) {
    const savedData = localStorage.getItem(`form_draft_${formId}`);
    
    if (savedData) {
        const data = JSON.parse(savedData);
        
        Object.keys(data).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field && !field.value) {
                field.value = data[key];
            }
        });
        
        // Mostrar aviso sobre rascunho
        showDraftNotice();
    }
}

/**
 * Limpar rascunho do formulário
 */
function clearFormDraft(formId) {
    localStorage.removeItem(`form_draft_${formId}`);
}

/**
 * Mostrar indicador de salvamento
 */
function showSaveIndicator() {
    // Remover indicadores existentes
    const existing = document.querySelector('.save-indicator');
    if (existing) existing.remove();
    
    const indicator = document.createElement('div');
    indicator.className = 'save-indicator';
    indicator.innerHTML = '💾 Rascunho salvo automaticamente';
    indicator.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #d1fae5;
        color: #065f46;
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => indicator.remove(), 300);
    }, 2000);
}

/**
 * Mostrar aviso sobre rascunho
 */
function showDraftNotice() {
    const notice = document.createElement('div');
    notice.className = 'draft-notice';
    notice.innerHTML = `
        <p>📝 Rascunho recuperado automaticamente.</p>
        <button onclick="this.parentElement.remove()">Dispensar</button>
    `;
    notice.style.cssText = `
        background: #fef3c7;
        color: #92400e;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        border-left: 4px solid #d97706;
        display: flex;
        justify-content: space-between;
        align-items: center;
    `;
    
    const form = document.querySelector('.admin-form');
    if (form) {
        form.insertBefore(notice, form.firstChild);
    }
}

/**
 * Preview de imagem
 */
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            let preview = input.parentNode.querySelector('.image-preview');
            
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'image-preview';
                input.parentNode.appendChild(preview);
            }
            
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 10px;">
                <button type="button" onclick="removeImagePreview(this)" style="display: block; margin-top: 5px; color: #dc2626;">Remover</button>
            `;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Remover preview de imagem
 */
function removeImagePreview(button) {
    const preview = button.parentNode;
    const input = preview.parentNode.querySelector('input[type="file"]');
    
    input.value = '';
    preview.remove();
}

/**
 * Criar contador de caracteres
 */
function createCharacterCounter(textarea) {
    const maxLength = textarea.getAttribute('maxlength');
    const counter = document.createElement('div');
    counter.className = 'character-counter';
    counter.style.cssText = 'text-align: right; font-size: 12px; color: #6b7280; margin-top: 5px;';
    
    function updateCounter() {
        const remaining = maxLength - textarea.value.length;
        counter.textContent = `${textarea.value.length}/${maxLength} caracteres`;
        counter.style.color = remaining < 50 ? '#dc2626' : '#6b7280';
    }
    
    textarea.addEventListener('input', updateCounter);
    textarea.parentNode.appendChild(counter);
    updateCounter();
}

/**
 * Funcionalidades dos filtros
 */
function initializeAdminFilters() {
    const filterForm = document.querySelector('.admin-filters form');
    
    if (filterForm) {
        const inputs = filterForm.querySelectorAll('input, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                // Auto-submit após pequeno delay
                clearTimeout(this.filterTimeout);
                this.filterTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500);
            });
        });
        
        // Botão limpar filtros
        const clearButton = filterForm.querySelector('.clear-filters');
        if (clearButton) {
            clearButton.addEventListener('click', function(e) {
                e.preventDefault();
                inputs.forEach(input => {
                    input.value = '';
                });
                filterForm.submit();
            });
        }
    }
}

/**
 * Funcionalidades dos modais
 */
function initializeAdminModals() {
    // Criar modal container se não existir
    if (!document.querySelector('#modal-container')) {
        const container = document.createElement('div');
        container.id = 'modal-container';
        container.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;
        document.body.appendChild(container);
    }
    
    // Links que abrem modais
    const modalTriggers = document.querySelectorAll('[data-modal]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalType = this.dataset.modal;
            const modalData = this.dataset;
            
            openModal(modalType, modalData);
        });
    });
}

/**
 * Abrir modal
 */
function openModal(type, data = {}) {
    const container = document.querySelector('#modal-container');
    let modalContent = '';
    
    switch (type) {
        case 'confirm':
            modalContent = createConfirmModal(data);
            break;
        case 'info':
            modalContent = createInfoModal(data);
            break;
        case 'form':
            modalContent = createFormModal(data);
            break;
        default:
            return;
    }
    
    container.innerHTML = modalContent;
    container.style.display = 'flex';
    
    // Fechar modal ao clicar fora
    container.addEventListener('click', function(e) {
        if (e.target === container) {
            closeModal();
        }
    });
    
    // ESC para fechar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

/**
 * Fechar modal
 */
function closeModal() {
    const container = document.querySelector('#modal-container');
    container.style.display = 'none';
    container.innerHTML = '';
}

/**
 * Criar modal de confirmação
 */
function createConfirmModal(data) {
    return `
        <div class="modal-content" style="background: white; border-radius: 12px; padding: 24px; max-width: 400px; width: 90%;">
            <h3 style="margin: 0 0 16px 0; color: #374151;">${data.title || 'Confirmação'}</h3>
            <p style="margin: 0 0 24px 0; color: #6b7280;">${data.message || 'Tem certeza desta ação?'}</p>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="closeModal()" class="btn btn-outline">Cancelar</button>
                <button onclick="window.location.href='${data.url}'" class="btn btn-primary">Confirmar</button>
            </div>
        </div>
    `;
}

/**
 * Gráficos simples para dashboard
 */
function initializeAdminCharts() {
    // Gráfico de progresso circular
    const progressCircles = document.querySelectorAll('.progress-circle');
    
    progressCircles.forEach(circle => {
        const percentage = circle.dataset.percentage;
        const circumference = 2 * Math.PI * 40; // raio = 40
        
        circle.innerHTML = `
            <svg width="100" height="100" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="40" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                <circle cx="50" cy="50" r="40" fill="none" stroke="#1e40af" stroke-width="8"
                        stroke-dasharray="${circumference}" 
                        stroke-dashoffset="${circumference - (percentage / 100) * circumference}"
                        transform="rotate(-90 50 50)"/>
                <text x="50" y="55" text-anchor="middle" font-size="14" font-weight="bold" fill="#374151">
                    ${percentage}%
                </text>
            </svg>
        `;
    });
    
    // Gráfico de barras simples
    const barCharts = document.querySelectorAll('.bar-chart');
    
    barCharts.forEach(chart => {
        const data = JSON.parse(chart.dataset.data || '[]');
        const maxValue = Math.max(...data.map(item => item.value));
        
        chart.innerHTML = data.map(item => `
            <div class="bar-item" style="display: flex; align-items: center; margin-bottom: 8px;">
                <span style="width: 100px; font-size: 12px; color: #6b7280;">${item.label}</span>
                <div style="flex: 1; background: #f3f4f6; height: 20px; border-radius: 10px; margin: 0 10px; overflow: hidden;">
                    <div style="width: ${(item.value / maxValue) * 100}%; height: 100%; background: #1e40af; transition: width 0.5s ease;"></div>
                </div>
                <span style="font-size: 12px; font-weight: 500; color: #374151;">${item.value}</span>
            </div>
        `).join('');
    });
}

/**
 * Validação de campo específica
 */
function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name;
    let isValid = true;
    let message = '';
    
    // Validações específicas do admin
    switch (fieldName) {
        case 'limite_participantes':
            if (value && (parseInt(value) < 1 || parseInt(value) > 10000)) {
                isValid = false;
                message = 'Limite deve estar entre 1 e 10.000 participantes';
            }
            break;
            
        case 'valor':
            if (value && (parseFloat(value) < 0 || parseFloat(value) > 99999)) {
                isValid = false;
                message = 'Valor deve estar entre R$ 0,00 e R$ 99.999,00';
            }
            break;
            
        case 'data_inicio':
            if (value && new Date(value) < new Date().setHours(0,0,0,0)) {
                isValid = false;
                message = 'Data de início não pode ser no passado';
            }
            break;
    }
    
    // Aplicar resultado da validação
    const container = field.parentNode;
    const existingError = container.querySelector('.field-error');
    
    if (existingError) existingError.remove();
    
    field.classList.remove('error', 'success');
    
    if (!isValid) {
        field.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        errorDiv.style.cssText = 'color: #dc2626; font-size: 12px; margin-top: 4px;';
        container.appendChild(errorDiv);
    } else if (value) {
        field.classList.add('success');
    }
    
    return isValid;
}

/**
 * Utilitários do admin
 */
const AdminUtils = {
    // Copiar para clipboard
    copyToClipboard: function(text) {
        navigator.clipboard.writeText(text).then(() => {
            VindeUtils.showMessage('Copiado para a área de transferência!', 'success');
        });
    },
    
    // Exportar dados
    exportData: function(data, filename, format = 'csv') {
        let content = '';
        let mimeType = '';
        
        if (format === 'csv') {
            const headers = Object.keys(data[0]);
            content = headers.join(',') + '\n';
            content += data.map(row => headers.map(header => row[header]).join(',')).join('\n');
            mimeType = 'text/csv';
        } else if (format === 'json') {
            content = JSON.stringify(data, null, 2);
            mimeType = 'application/json';
        }
        
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    },
    
    // Formatar número
    formatNumber: function(num) {
        return new Intl.NumberFormat('pt-BR').format(num);
    },
    
    // Formatar moeda
    formatCurrency: function(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }
};

// Expor utilitários globalmente
window.AdminUtils = AdminUtils;

/**
 * Inicializar funcionalidade do botão de cache
 */
function initializeCacheBtn() {
    // Adicionar funcionalidade AJAX ao botão flutuante
    const cacheFloatBtn = document.querySelector('.cache-float-btn');
    if (cacheFloatBtn) {
        cacheFloatBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearCacheAjax();
        });
    }
    
    // Adicionar funcionalidade ao formulário da página de cache
    const cacheForm = document.getElementById('cache-form');
    if (cacheForm) {
        cacheForm.addEventListener('submit', function(e) {
            e.preventDefault();
            clearCacheAjax();
        });
    }
}

/**
 * Limpar cache via AJAX
 */
function clearCacheAjax() {
    // Feedback visual imediato
    showCacheLoading();
    
    // Fazer requisição AJAX
    const baseUrl = window.location.pathname.includes('/admin/') ? 
        window.location.origin + window.location.pathname.split('/admin/')[0] + '/admin/limpar_cache.php' :
        window.location.origin + '/admin/limpar_cache.php';
    
    fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'limpar_cache=1&ajax=1'
    })
    .then(response => response.json())
    .then(data => {
        hideCacheLoading();
        showCacheResult(data);
        
        // Forçar reload de CSS/JS após 1 segundo
        setTimeout(() => {
            reloadPageAssets();
        }, 1000);
    })
    .catch(error => {
        hideCacheLoading();
        showCacheError('Erro ao limpar cache: ' + error.message);
    });
}

/**
 * Mostrar loading do cache
 */
function showCacheLoading() {
    const floatBtn = document.querySelector('.cache-float-btn');
    const formBtn = document.getElementById('btn-limpar');
    
    if (floatBtn) {
        floatBtn.innerHTML = '⏳';
        floatBtn.style.animation = 'spin 1s linear infinite';
        floatBtn.style.pointerEvents = 'none';
    }
    
    if (formBtn) {
        formBtn.innerHTML = '🔄 Limpando...';
        formBtn.disabled = true;
        formBtn.classList.add('loading');
    }
    
    // Adicionar animação de spin
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
}

/**
 * Esconder loading do cache
 */
function hideCacheLoading() {
    const floatBtn = document.querySelector('.cache-float-btn');
    const formBtn = document.getElementById('btn-limpar');
    
    if (floatBtn) {
        floatBtn.innerHTML = '🧹';
        floatBtn.style.animation = 'pulse-cache 3s infinite';
        floatBtn.style.pointerEvents = 'auto';
    }
    
    if (formBtn) {
        formBtn.innerHTML = '🧹 Limpar Cache Agora';
        formBtn.disabled = false;
        formBtn.classList.remove('loading');
    }
}

/**
 * Mostrar resultado da limpeza
 */
function showCacheResult(data) {
    const message = data.sucesso ? 
        `✅ Cache limpo com sucesso!\n\n${data.acoes.join('\n')}` :
        `❌ Erro: ${data.mensagem}`;
    
    // Toast notification
    showToast(message, data.sucesso ? 'success' : 'error');
    
    // Se estiver na página de cache, atualizar o conteúdo
    const acoesDiv = document.querySelector('.acoes-executadas');
    if (acoesDiv && data.acoes) {
        acoesDiv.innerHTML = `
            <h3>Ações executadas:</h3>
            <ul>
                ${data.acoes.map(acao => `<li>${acao}</li>`).join('')}
            </ul>
        `;
        acoesDiv.style.display = 'block';
    }
}

/**
 * Mostrar erro do cache
 */
function showCacheError(message) {
    showToast(`❌ ${message}`, 'error');
}

/**
 * Forçar reload de assets (CSS/JS)
 */
function reloadPageAssets() {
    const timestamp = new Date().getTime();
    
    // Recarregar CSS
    const cssLinks = document.querySelectorAll('link[rel="stylesheet"]');
    cssLinks.forEach(link => {
        const href = link.href.split('?')[0];
        link.href = `${href}?v=${timestamp}`;
    });
    
    // Recarregar JS (opcional - pode causar problemas)
    // const jsScripts = document.querySelectorAll('script[src]');
    // jsScripts.forEach(script => {
    //     const newScript = document.createElement('script');
    //     const src = script.src.split('?')[0];
    //     newScript.src = `${src}?v=${timestamp}`;
    //     script.parentNode.replaceChild(newScript, script);
    // });
}

/**
 * Mostrar toast notification
 */
function showToast(message, type = 'info') {
    // Remover toasts existentes
    const existingToasts = document.querySelectorAll('.cache-toast');
    existingToasts.forEach(toast => toast.remove());
    
    // Criar novo toast
    const toast = document.createElement('div');
    toast.className = `cache-toast cache-toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <pre>${message}</pre>
            <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    // Adicionar estilos inline
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 400px;
        background: ${type === 'success' ? '#f0f9f4' : '#fef2f2'};
        border: 1px solid ${type === 'success' ? '#86efac' : '#fca5a5'};
        color: ${type === 'success' ? '#065f46' : '#dc2626'};
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    // Adicionar ao DOM
    document.body.appendChild(toast);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
    
    // Adicionar animações CSS se não existirem
    if (!document.querySelector('#toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .toast-content {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 10px;
            }
            .toast-content pre {
                margin: 0;
                font-family: inherit;
                white-space: pre-wrap;
                font-size: 13px;
                line-height: 1.4;
            }
            .toast-close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                opacity: 0.7;
            }
            .toast-close:hover {
                opacity: 1;
                background: rgba(0,0,0,0.1);
            }
        `;
        document.head.appendChild(style);
    }
} 