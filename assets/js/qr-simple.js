/**
 * QR Code Generator Simples para Vinde
 * Usa APIs externas para gerar QR codes quando bibliotecas não estão disponíveis
 */

class VindeQR {
    constructor() {
        this.apiUrls = [
            'https://api.qrserver.com/v1/create-qr-code/',
            'https://chart.googleapis.com/chart'
        ];
    }

    /**
     * Gerar QR Code usando API externa
     */
    async generateQR(data, options = {}) {
        const defaultOptions = {
            size: 200,
            format: 'png',
            errorCorrectionLevel: 'M'
        };
        
        const opts = { ...defaultOptions, ...options };
        
        try {
            // Tentar primeira API (QR Server)
            const url = `${this.apiUrls[0]}?size=${opts.size}x${opts.size}&data=${encodeURIComponent(data)}&format=${opts.format}`;
            
            return {
                success: true,
                url: url,
                data: data
            };
            
        } catch (error) {
            console.error('Erro ao gerar QR Code:', error);
            
            // Fallback para Google Charts
            try {
                const fallbackUrl = `${this.apiUrls[1]}?chs=${opts.size}x${opts.size}&cht=qr&chl=${encodeURIComponent(data)}`;
                
                return {
                    success: true,
                    url: fallbackUrl,
                    data: data
                };
            } catch (fallbackError) {
                return {
                    success: false,
                    error: 'Não foi possível gerar QR Code'
                };
            }
        }
    }

    /**
     * Renderizar QR Code em um elemento HTML
     */
    async renderTo(element, data, options = {}) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        
        if (!element) {
            throw new Error('Elemento não encontrado');
        }
        
        const result = await this.generateQR(data, options);
        
        if (result.success) {
            // Limpar elemento
            element.innerHTML = '';
            element.classList.add('qr-loading');
            
            // Criar imagem
            const img = document.createElement('img');
            img.src = result.url;
            img.alt = 'QR Code';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
            img.style.display = 'block';
            img.style.margin = '0 auto';
            
            // Adicionar eventos
            img.onload = () => {
                element.classList.remove('qr-loading');
                element.classList.add('qr-loaded');
                console.log('QR Code carregado com sucesso');
            };
            
            img.onerror = () => {
                console.error('Erro ao carregar imagem do QR Code');
                element.innerHTML = '<div class="qr-error">Erro ao carregar QR Code</div>';
                element.classList.remove('qr-loading');
                element.classList.add('qr-error');
            };
            
            element.appendChild(img);
            
            return result;
        } else {
            element.innerHTML = '<div class="qr-error">Erro: ' + result.error + '</div>';
            element.classList.add('qr-error');
            throw new Error(result.error);
        }
    }

    /**
     * Criar canvas com QR Code (para download)
     */
    async toCanvas(data, options = {}) {
        const result = await this.generateQR(data, options);
        
        if (!result.success) {
            throw new Error(result.error);
        }
        
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                canvas.width = img.width;
                canvas.height = img.height;
                
                // Fundo branco
                ctx.fillStyle = 'white';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Desenhar QR Code
                ctx.drawImage(img, 0, 0);
                
                resolve(canvas);
            };
            
            img.onerror = () => {
                reject(new Error('Erro ao carregar imagem do QR Code'));
            };
            
            img.src = result.url;
        });
    }

    /**
     * Baixar QR Code como PNG
     */
    async download(data, filename = 'qrcode.png', options = {}) {
        try {
            const canvas = await this.toCanvas(data, options);
            
            // Criar link de download
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            
            // Simular clique
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            return true;
        } catch (error) {
            console.error('Erro ao baixar QR Code:', error);
            return false;
        }
    }
}

// Instância global
window.VindeQR = new VindeQR();

// Compatibilidade com código existente
window.QRCode = {
    toCanvas: async function(element, data, options = {}) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        
        const result = await window.VindeQR.renderTo(element, data, {
            size: options.width || 200
        });
        
        return element.querySelector('img');
    }
};

// Função de conveniência para check-in
window.gerarQRCheckin = async function(participanteId, eventoId, elementId) {
    try {
        // Buscar dados do QR via API
        const response = await fetch('/participante/qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                participante_id: participanteId,
                evento_id: eventoId
            })
        });

        const result = await response.json();

        if (result.success) {
            await window.VindeQR.renderTo(elementId, result.qr_data, {
                size: 200
            });
            
            return result.qr_data;
        } else {
            throw new Error(result.message || 'Erro ao gerar QR Code');
        }
    } catch (error) {
        console.error('Erro ao gerar QR Code de check-in:', error);
        
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = '<div class="qr-error">Erro ao gerar QR Code</div>';
        }
        
        throw error;
    }
};

// CSS para os estados do QR Code
const qrStyles = `
.qr-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    position: relative;
}

.qr-loading::before {
    content: 'Gerando QR Code...';
    color: #6c757d;
    font-size: 14px;
    position: absolute;
    z-index: 1;
}

.qr-loaded {
    text-align: center;
    padding: 10px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    min-height: auto;
}

.qr-loaded img {
    max-width: 100% !important;
    height: auto !important;
    display: block !important;
    margin: 0 auto !important;
}

.qr-error {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    background: #f8d7da;
    border: 2px dashed #f5c6cb;
    border-radius: 8px;
    color: #721c24;
    font-size: 14px;
    text-align: center;
}

/* Garantir que QR Code seja visível */
#qr-canvas {
    min-height: 50px;
    background: transparent;
}

#qr-canvas img {
    max-width: 100% !important;
    height: auto !important;
    display: block !important;
    margin: 0 auto !important;
}
`;

// Adicionar estilos ao documento
if (!document.getElementById('vinde-qr-styles')) {
    const style = document.createElement('style');
    style.id = 'vinde-qr-styles';
    style.textContent = qrStyles;
    document.head.appendChild(style);
}

console.log('Vinde QR Generator carregado com sucesso');
