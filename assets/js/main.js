/**
 * VINDE - Sistema de Eventos Católicos
 * JavaScript Principal
 */

// Inicialização global
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Inicialização principal da aplicação
 */
function initializeApp() {
    // Inicializar componentes
    initializeMobileMenu();
    initializeMessages();
    initializeForms();
    initializeMasks();
    initializeTooltips();
    initializeScrollEffects();
    
    console.log('Vinde System - Initialized');
}

/**
 * Menu mobile responsivo
 */
function initializeMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const navPrincipal = document.querySelector('.nav-principal');
    
    if (menuToggle && navPrincipal) {
        menuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            navPrincipal.classList.toggle('mobile-open');
            
            // Animação das barras do hambúrguer
            const spans = this.querySelectorAll('span');
            if (this.classList.contains('active')) {
                spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
            } else {
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            }
        });
        
        // Fechar menu ao clicar em link
        const navLinks = navPrincipal.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                menuToggle.classList.remove('active');
                navPrincipal.classList.remove('mobile-open');
            });
        });
    }
}

/**
 * Sistema de mensagens
 */
function initializeMessages() {
    const mensagens = document.querySelectorAll('.mensagem');
    
    mensagens.forEach(mensagem => {
        const botaoFechar = mensagem.querySelector('.mensagem-fechar');
        
        if (botaoFechar) {
            botaoFechar.addEventListener('click', function() {
                mensagem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                mensagem.style.opacity = '0';
                mensagem.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    mensagem.remove();
                }, 300);
            });
        }
        
        // Auto-remover mensagens após 5 segundos
        if (!mensagem.classList.contains('mensagem-error')) {
            setTimeout(() => {
                if (mensagem.parentNode) {
                    mensagem.style.transition = 'opacity 0.3s ease';
                    mensagem.style.opacity = '0';
                    setTimeout(() => {
                        if (mensagem.parentNode) {
                            mensagem.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }
    });
}

/**
 * Validação e melhorias de formulários
 */
function initializeForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Validação em tempo real
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    validateField(this);
                }
            });
        });
        
        // Submit do formulário
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showMessage('Por favor, corrija os erros no formulário.', 'error');
                
                // Focar no primeiro campo com erro
                const firstError = form.querySelector('.error');
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });
}

/**
 * Validação de campo individual
 */
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const name = field.name;
    let isValid = true;
    let message = '';
    
    // Remover classes de erro anteriores
    field.classList.remove('error', 'success');
    removeFieldMessage(field);
    
    // Validação básica de campo obrigatório
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        message = 'Este campo é obrigatório.';
    }
    
    // Validações específicas por tipo/nome
    if (value && isValid) {
        switch (name) {
            case 'email':
                if (!isValidEmail(value)) {
                    isValid = false;
                    message = 'Digite um email válido.';
                }
                break;
                
            case 'cpf':
                if (!isValidCPF(value)) {
                    isValid = false;
                    message = 'Digite um CPF válido.';
                }
                break;
                
            case 'whatsapp':
            case 'telefone':
                if (!isValidPhone(value)) {
                    isValid = false;
                    message = 'Digite um telefone válido.';
                }
                break;
                
            case 'idade':
                const idade = parseInt(value);
                if (idade < 12 || idade > 120) {
                    isValid = false;
                    message = 'Idade deve estar entre 12 e 120 anos.';
                }
                break;
        }
    }
    
    // Aplicar resultado da validação
    if (isValid) {
        field.classList.add('success');
    } else {
        field.classList.add('error');
        showFieldMessage(field, message, 'error');
    }
    
    return isValid;
}

/**
 * Máscaras de input
 */
function initializeMasks() {
    // Máscara de CPF
    const cpfInputs = document.querySelectorAll('input[name="cpf"]');
    cpfInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = maskCPF(this.value);
        });
    });
    
    // Máscara de telefone
    const phoneInputs = document.querySelectorAll('input[name="whatsapp"], input[name="telefone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = maskPhone(this.value);
        });
    });
    
    // Máscara de CEP
    const cepInputs = document.querySelectorAll('input[name="cep"]');
    cepInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = maskCEP(this.value);
        });
    });
}

/**
 * Tooltips e dicas
 */
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            showTooltip(this, this.dataset.tooltip);
        });
        
        element.addEventListener('mouseleave', function() {
            hideTooltip();
        });
    });
}

/**
 * Efeitos de scroll
 */
function initializeScrollEffects() {
    // Scroll suave para âncoras
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Animações on scroll
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    
    if (animatedElements.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                }
            });
        }, { threshold: 0.1 });
        
        animatedElements.forEach(element => {
            observer.observe(element);
        });
    }
}

/**
 * Funções utilitárias de validação
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function isValidCPF(cpf) {
    cpf = cpf.replace(/[^\d]/g, '');
    
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }
    
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(cpf.charAt(i)) * (10 - i);
    }
    
    let remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) remainder = 0;
    if (remainder !== parseInt(cpf.charAt(9))) return false;
    
    sum = 0;
    for (let i = 0; i < 10; i++) {
        sum += parseInt(cpf.charAt(i)) * (11 - i);
    }
    
    remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) remainder = 0;
    if (remainder !== parseInt(cpf.charAt(10))) return false;
    
    return true;
}

function isValidPhone(phone) {
    const cleaned = phone.replace(/[^\d]/g, '');
    return cleaned.length >= 10 && cleaned.length <= 11;
}

/**
 * Funções de máscara
 */
function maskCPF(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})/, '$1-$2')
        .replace(/(-\d{2})\d+?$/, '$1');
}

function maskPhone(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2')
        .replace(/(-\d{4})\d+?$/, '$1');
}

function maskCEP(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{5})(\d)/, '$1-$2')
        .replace(/(-\d{3})\d+?$/, '$1');
}

/**
 * Sistema de mensagens customizado
 */
function showMessage(text, type = 'info') {
    // Remover mensagens existentes
    const existingMessages = document.querySelectorAll('.toast-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Criar nova mensagem
    const messageElement = document.createElement('div');
    messageElement.className = `toast-message toast-${type}`;
    messageElement.innerHTML = `
        <div class="toast-content">
            <span class="toast-text">${text}</span>
            <button class="toast-close">&times;</button>
        </div>
    `;
    
    // Adicionar estilos se não existirem
    if (!document.querySelector('#toast-styles')) {
        const styles = document.createElement('style');
        styles.id = 'toast-styles';
        styles.textContent = `
            .toast-message {
                position: fixed;
                top: 20px;
                right: 20px;
                min-width: 300px;
                max-width: 500px;
                padding: 16px;
                border-radius: 8px;
                box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
                z-index: 10000;
                animation: slideInRight 0.3s ease;
            }
            
            .toast-success {
                background-color: #d1fae5;
                border-left: 4px solid #059669;
                color: #065f46;
            }
            
            .toast-error {
                background-color: #fee2e2;
                border-left: 4px solid #dc2626;
                color: #991b1b;
            }
            
            .toast-warning {
                background-color: #fef3c7;
                border-left: 4px solid #d97706;
                color: #92400e;
            }
            
            .toast-info {
                background-color: #dbeafe;
                border-left: 4px solid #1e40af;
                color: #1e3a8a;
            }
            
            .toast-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .toast-close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                opacity: 0.6;
                transition: opacity 0.2s ease;
            }
            
            .toast-close:hover {
                opacity: 1;
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @media (max-width: 480px) {
                .toast-message {
                    left: 20px;
                    right: 20px;
                    min-width: auto;
                }
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Adicionar ao DOM
    document.body.appendChild(messageElement);
    
    // Event listener para fechar
    const closeButton = messageElement.querySelector('.toast-close');
    closeButton.addEventListener('click', () => {
        messageElement.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => messageElement.remove(), 300);
    });
    
    // Auto-remover após alguns segundos
    setTimeout(() => {
        if (messageElement.parentNode) {
            messageElement.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => {
                if (messageElement.parentNode) {
                    messageElement.remove();
                }
            }, 300);
        }
    }, type === 'error' ? 8000 : 4000);
}

/**
 * Mensagens de campo específico
 */
function showFieldMessage(field, message, type) {
    const existingMessage = field.parentNode.querySelector('.field-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    const messageElement = document.createElement('div');
    messageElement.className = `field-message field-message-${type}`;
    messageElement.textContent = message;
    
    // Adicionar estilos se não existirem
    if (!document.querySelector('#field-message-styles')) {
        const styles = document.createElement('style');
        styles.id = 'field-message-styles';
        styles.textContent = `
            .field-message {
                font-size: 0.875rem;
                margin-top: 4px;
                padding: 4px 8px;
                border-radius: 4px;
            }
            
            .field-message-error {
                background-color: #fee2e2;
                color: #991b1b;
                border-left: 3px solid #dc2626;
            }
            
            .field-message-success {
                background-color: #d1fae5;
                color: #065f46;
                border-left: 3px solid #059669;
            }
            
            input.error, select.error, textarea.error {
                border-color: #dc2626 !important;
                box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            }
            
            input.success, select.success, textarea.success {
                border-color: #059669 !important;
            }
        `;
        document.head.appendChild(styles);
    }
    
    field.parentNode.insertBefore(messageElement, field.nextSibling);
}

function removeFieldMessage(field) {
    const existingMessage = field.parentNode.querySelector('.field-message');
    if (existingMessage) {
        existingMessage.remove();
    }
}

/**
 * Sistema de tooltip
 */
function showTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    
    // Adicionar estilos se não existirem
    if (!document.querySelector('#tooltip-styles')) {
        const styles = document.createElement('style');
        styles.id = 'tooltip-styles';
        styles.textContent = `
            .tooltip {
                position: absolute;
                background-color: #374151;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 0.875rem;
                white-space: nowrap;
                z-index: 10000;
                pointer-events: none;
                opacity: 0;
                animation: fadeIn 0.2s ease forwards;
            }
            
            @keyframes fadeIn {
                to { opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    // Armazenar referência para remoção
    element._tooltip = tooltip;
}

function hideTooltip() {
    const tooltips = document.querySelectorAll('.tooltip');
    tooltips.forEach(tooltip => {
        tooltip.style.animation = 'fadeIn 0.2s ease reverse';
        setTimeout(() => tooltip.remove(), 200);
    });
}

/**
 * Utilitários gerais
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, delay) {
    let timeoutId;
    let lastExecTime = 0;
    return function (...args) {
        const currentTime = Date.now();
        if (currentTime - lastExecTime > delay) {
            func.apply(this, args);
            lastExecTime = currentTime;
        } else {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(this, args);
                lastExecTime = Date.now();
            }, delay - (currentTime - lastExecTime));
        }
    };
}

// Expor funções globalmente quando necessário
window.VindeUtils = {
    showMessage,
    validateField,
    maskCPF,
    maskPhone,
    maskCEP,
    isValidEmail,
    isValidCPF,
    isValidPhone,
    debounce,
    throttle
}; 