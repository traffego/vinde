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
function verificarPagamento(btnEl, mostrarAlerta = true) {
    const spinner = document.getElementById('loading-spinner');
    const btn = btnEl || document.getElementById('btn-verificar-pagamento');
    const statusDiv = document.getElementById('status-verificacao') || criarStatusDiv();

    if (spinner) spinner.style.display = 'block';
    if (btn) btn.disabled = true;

    // Feedback visual sutil para verificação automática
    if (!mostrarAlerta) {
        statusDiv.innerHTML = '<small style="color: #6c757d;">🔄 Verificando pagamento...</small>';
    }

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
        if (spinner) spinner.style.display = 'none';
        if (btn) btn.disabled = false;
        
        if (data.success && data.pago) {
            // Pagamento confirmado - redirecionar
            statusDiv.innerHTML = '<small style="color: #28a745;">✅ Pagamento confirmado! Redirecionando...</small>';
            setTimeout(() => {
                window.location.href = window.SITE_URL + '/confirmacao.php?inscricao=' + window.INSCRICAO_ID;
            }, 1000);
        } else {
            // Para verificação manual (com botão), mostrar alerta
            if (mostrarAlerta) {
                alert(data.message || 'Pagamento ainda não foi identificado. Tente novamente em alguns instantes.');
                statusDiv.innerHTML = '';
            } else {
                // Para verificação automática, apenas feedback sutil
                const agora = new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                statusDiv.innerHTML = '<small style="color: #6c757d;">⏱️ Última verificação: ' + agora + '</small>';
            }
        }
    })
    .catch(error => {
        if (spinner) spinner.style.display = 'none';
        if (btn) btn.disabled = false;
        
        if (mostrarAlerta) {
            alert('Erro ao verificar pagamento. Tente novamente.');
        } else {
            statusDiv.innerHTML = '<small style="color: #dc3545;">⚠️ Erro na verificação automática</small>';
        }
    });
}

// Função para criar div de status se não existir
function criarStatusDiv() {
    let statusDiv = document.getElementById('status-verificacao');
    if (!statusDiv) {
        statusDiv = document.createElement('div');
        statusDiv.id = 'status-verificacao';
        statusDiv.style.textAlign = 'center';
        statusDiv.style.marginTop = '10px';
        statusDiv.style.minHeight = '20px';
        
        // Inserir após o botão de verificar
        const btnVerificar = document.querySelector('.btn-verificar');
        if (btnVerificar && btnVerificar.parentNode) {
            btnVerificar.parentNode.insertBefore(statusDiv, btnVerificar.nextSibling);
        }
    }
    return statusDiv;
}

// Verificação automática a cada 30 segundos (SILENCIOSA)
setInterval(function() {
    verificarPagamento(null, false); // false = não mostrar alertas
}, 30000);

// Geração local do QR Code PIX a partir do payload
function gerarQrPixCanvas() {
    const canvas = document.getElementById('qr-canvas-pix');
    if (!canvas) return;
    const payload = (window.PIX_PAYLOAD || '').trim();
    if (!payload) return;

    // Garantir que o wrapper fique visível quando for usar o canvas
    const wrapper = document.getElementById('qr-canvas-wrapper');
    if (wrapper && wrapper.style.display === 'none') {
        wrapper.style.display = 'inline-block';
    }

    // Tentar com diferentes exposições globais da lib
    const QRGlobal = (typeof QRCode !== 'undefined') ? QRCode : (typeof qrcode !== 'undefined' ? qrcode : null);
    if (QRGlobal && typeof QRGlobal.toCanvas === 'function') {
        try {
            QRGlobal.toCanvas(canvas, payload, {
                width: 260,
                margin: 1,
                color: { dark: '#000000', light: '#FFFFFF' }
            }, function(){ /* noop */ });
            return;
        } catch (e) {
            console.error('Falha ao desenhar QR no canvas:', e);
        }
    }
}

// Inicialização quando DOM carrega
document.addEventListener('DOMContentLoaded', function() {
    const img = document.getElementById('qr-code-img');
    // Sempre tente desenhar no canvas (serve como fallback ou como primário)
    const wrapper = document.getElementById('qr-canvas-wrapper');
    if (wrapper && (!img || img.style.display === 'none')) {
        wrapper.style.display = 'inline-block';
    }
    // Desenhar QR de qualquer forma
    setTimeout(gerarQrPixCanvas, 50);
    
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
    } else {
        // CORREÇÃO: Se não há tempo de expiração, verificar se PIX não foi gerado
        const qrLoading = document.getElementById('qr-loading');
        const pixCode = document.getElementById('pix-code');
        
        if (qrLoading || (!pixCode || !pixCode.textContent.trim())) {
            console.log('PIX não gerado na primeira carga - aguardando...');
            
            // Aguardar 5 segundos e recarregar se PIX ainda não existir
            setTimeout(function() {
                const pixCodeCheck = document.getElementById('pix-code');
                const qrImg = document.getElementById('qr-code-img');
                
                if ((!pixCodeCheck || !pixCodeCheck.textContent.trim()) && 
                    (!qrImg || qrImg.style.display === 'none')) {
                    console.log('PIX ainda não gerado após 5s - recarregando página...');
                    window.location.reload();
                }
            }, 5000);
        }
    }
});
