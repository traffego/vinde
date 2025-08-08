// JavaScript da página de pagamento

// Timer de expiração
let tempoRestante = 0;

function inicializarTimer(tempoInicial) {
    tempoRestante = tempoInicial;
    
    function atualizarTimer() {
        if (tempoRestante <= 0) {
            const container = document.getElementById('timer-container');
            const text = document.getElementById('timer-text');
            if (container && text) {
                container.className = 'timer-expiracao expirado';
                text.innerHTML = '⚠️ Código PIX expirado - Recarregue a página para gerar um novo';
            }
            return;
        }
        
        const minutos = Math.floor(tempoRestante / 60);
        const segundos = tempoRestante % 60;
        const countdown = document.getElementById('countdown');
        if (countdown) {
            countdown.textContent = String(minutos).padStart(2, '0') + ':' + String(segundos).padStart(2, '0');
        }
        
        tempoRestante--;
    }
    
    // Atualizar timer a cada segundo
    setInterval(atualizarTimer, 1000);
}

// Função para copiar código PIX (melhorada)
function copiarPix(btn) {
    const pixEl = document.getElementById('pix-code');
    if (!pixEl) {
        alert('Código PIX não encontrado');
        return;
    }
    
    let pixCode = (pixEl.textContent || pixEl.innerText || '').trim();
    
    // Remover quebras de linha e espaços extras
    pixCode = pixCode.replace(/\s+/g, '').replace(/\r?\n|\r/g, '');
    
    if (!pixCode || pixCode.length < 50) {
        alert('Código PIX inválido ou muito curto');
        return;
    }
    
    // Validação básica do formato PIX
    if (!pixCode.startsWith('00020101') && !pixCode.startsWith('00020126')) {
        alert('Código PIX com formato inválido');
        return;
    }
    
    // Tentar copiar usando API moderna
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pixCode)
            .then(() => {
                feedbackCopiado(btn);
                console.log('PIX copiado:', pixCode.substring(0, 50) + '...');
            })
            .catch(err => {
                console.error('Erro ao copiar:', err);
                copiarPixFallback(pixCode, btn);
            });
    } else {
        copiarPixFallback(pixCode, btn);
    }
}

// Fallback para navegadores antigos
function copiarPixFallback(pixCode, btn) {
    const area = document.createElement('textarea');
    area.value = pixCode;
    area.setAttribute('readonly', '');
    area.style.position = 'absolute';
    area.style.left = '-9999px';
    area.style.opacity = '0';
    document.body.appendChild(area);
    
    try {
        area.select();
        area.setSelectionRange(0, 99999); // Para mobile
        const success = document.execCommand('copy');
        if (success) {
            feedbackCopiado(btn);
        } else {
            alert('Não foi possível copiar automaticamente. Por favor, selecione e copie manualmente.');
        }
    } catch (err) {
        console.error('Erro no fallback:', err);
        alert('Erro ao copiar. Tente selecionar o código manualmente.');
    } finally {
        document.body.removeChild(area);
    }
}

function feedbackCopiado(btn) {
    if (!btn) return;
    const textoOriginal = btn.textContent;
    btn.textContent = '✅ Copiado!';
    btn.classList.add('copiado');
    setTimeout(function() {
        btn.textContent = textoOriginal;
        btn.classList.remove('copiado');
    }, 2000);
}

// Função para validar código PIX
function validarCodigoPix() {
    const pixEl = document.getElementById('pix-code');
    const resultEl = document.getElementById('pix-validation-result');
    
    if (!pixEl || !resultEl) return;
    
    let pixCode = (pixEl.textContent || pixEl.innerText || '').trim();
    pixCode = pixCode.replace(/\s+/g, '').replace(/\r?\n|\r/g, '');
    
    // Limpar resultado anterior
    resultEl.innerHTML = '';
    
    if (!pixCode) {
        resultEl.innerHTML = '<span style="color: #dc3545;">❌ Código PIX não encontrado</span>';
        return;
    }
    
    // Validações básicas
    const validacoes = [];
    
    // 1. Tamanho mínimo
    if (pixCode.length < 50) {
        validacoes.push('❌ Código muito curto (mínimo 50 caracteres)');
    } else {
        validacoes.push('✅ Tamanho adequado (' + pixCode.length + ' caracteres)');
    }
    
    // 2. Formato inicial
    if (pixCode.startsWith('00020101') || pixCode.startsWith('00020126')) {
        validacoes.push('✅ Formato inicial correto');
    } else {
        validacoes.push('❌ Formato inicial inválido');
    }
    
    // 3. Verificar se contém identificador PIX
    if (pixCode.includes('BR.GOV.BCB.PIX')) {
        validacoes.push('✅ Identificador PIX encontrado');
    } else {
        validacoes.push('❌ Identificador PIX não encontrado');
    }
    
    // 4. Verificar país (BR)
    if (pixCode.includes('5802BR')) {
        validacoes.push('✅ Código de país correto (BR)');
    } else {
        validacoes.push('❌ Código de país não encontrado');
    }
    
    // 5. CRC (últimos 4 caracteres devem ser hexadecimais)
    const crc = pixCode.slice(-4);
    if (/^[0-9A-F]{4}$/i.test(crc)) {
        validacoes.push('✅ CRC com formato correto (' + crc + ')');
    } else {
        validacoes.push('❌ CRC com formato inválido');
    }
    
    // Mostrar resultados
    const temErros = validacoes.some(v => v.includes('❌'));
    const corGeral = temErros ? '#dc3545' : '#28a745';
    const statusGeral = temErros ? '⚠️ Código com problemas' : '✅ Código PIX válido';
    
    resultEl.innerHTML = `
        <div style="color: ${corGeral}; font-weight: bold; margin-bottom: 5px;">
            ${statusGeral}
        </div>
        <div style="font-size: 11px; line-height: 1.3;">
            ${validacoes.join('<br>')}
        </div>
    `;
}

// Função para verificar status do pagamento
function verificarPagamento() {
    const spinner = document.getElementById('loading-spinner');
    const btn = event.target;
    
    spinner.style.display = 'block';
    btn.disabled = true;
    
    fetch(window.SITE_URL + '/api/verificar_pagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            inscricao_id: window.INSCRICAO_ID,
            pagamento_id: window.PAGAMENTO_ID
        })
    })
    .then(response => response.json())
    .then(data => {
        spinner.style.display = 'none';
        btn.disabled = false;
        
        if (data.success && data.pago) {
            // Pagamento confirmado - redirecionar
            window.location.href = window.SITE_URL + '/confirmacao.php?inscricao=' + window.INSCRICAO_ID;
        } else {
            // Mostrar resultado
            alert(data.message || 'Pagamento ainda não foi identificado. Tente novamente em alguns instantes.');
        }
    })
    .catch(error => {
        spinner.style.display = 'none';
        btn.disabled = false;
        alert('Erro ao verificar pagamento. Tente novamente.');
    });
}

// Verificação automática a cada 30 segundos
setInterval(function() {
    verificarPagamento();
}, 30000);

// Geração local do QR Code PIX a partir do payload
function gerarQrPixCanvas() {
    const canvas = document.getElementById('qr-canvas-pix');
    if (!canvas) return;
    const payload = window.PIX_PAYLOAD || '';
    if (!payload) return;
    
    if (typeof QRCode !== 'undefined') {
        QRCode.toCanvas(canvas, payload, {
            width: 250,
            margin: 2,
            color: { dark: '#000000', light: '#FFFFFF' }
        }, function(err){ /* noop */ });
    }
}

// Inicialização quando DOM carrega
document.addEventListener('DOMContentLoaded', function() {
    const img = document.getElementById('qr-code-img');
    const shouldDraw = !img || img.style.display === 'none';
    if (shouldDraw) {
        const wrapper = document.getElementById('qr-canvas-wrapper');
        if (wrapper) wrapper.style.display = 'inline-block';
        gerarQrPixCanvas();
    }
    
    // Adicionar funcionalidade de clique no código PIX para seleção
    const pixCodeEl = document.getElementById('pix-code');
    if (pixCodeEl) {
        pixCodeEl.addEventListener('click', function() {
            // Selecionar todo o texto do código PIX
            if (window.getSelection && document.createRange) {
                const range = document.createRange();
                range.selectNodeContents(pixCodeEl);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            } else if (document.body.createTextRange) {
                // Fallback para IE
                const range = document.body.createTextRange();
                range.moveToElementText(pixCodeEl);
                range.select();
            }
            
            // Feedback visual
            pixCodeEl.style.backgroundColor = '#e3f2fd';
            setTimeout(() => {
                pixCodeEl.style.backgroundColor = '#f8f9fa';
            }, 1000);
        });
        
        // Validar código automaticamente ao carregar
        setTimeout(validarCodigoPix, 500);
    }
    
    // Inicializar timer se existe
    if (window.TEMPO_EXPIRACAO) {
        inicializarTimer(window.TEMPO_EXPIRACAO);
    }
});
