/**
 * QR Code Scanner usando WebRTC
 * Sistema de Inscrições Católicas - Vinde
 */

// Importar biblioteca jsQR do CDN
if (typeof jsQR === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
    script.onload = function() {
        console.log('jsQR library loaded successfully');
    };
    script.onerror = function() {
        console.error('Failed to load jsQR library');
    };
    document.head.appendChild(script);
}

class QRScanner {
    constructor(videoElement, canvasElement, options = {}) {
        this.video = videoElement;
        this.canvas = canvasElement;
        this.context = this.canvas.getContext('2d');
        this.stream = null;
        this.isScanning = false;
        this.animationFrame = null;
        
        // Opções
        this.options = {
            facingMode: options.facingMode || 'environment',
            width: options.width || 640,
            height: options.height || 480,
            onSuccess: options.onSuccess || function(data) { console.log('QR Code detected:', data); },
            onError: options.onError || function(error) { console.error('Scanner error:', error); },
            continuous: options.continuous !== false // default true
        };
        
        this.setupCanvas();
    }
    
    setupCanvas() {
        this.canvas.width = this.options.width;
        this.canvas.height = this.options.height;
    }
    
    async getAvailableCameras() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.filter(device => device.kind === 'videoinput');
        } catch (error) {
            this.options.onError('Erro ao listar câmeras: ' + error.message);
            return [];
        }
    }
    
    async start(deviceId = null) {
        try {
            if (this.isScanning) {
                this.stop();
            }
            
            const constraints = {
                video: {
                    width: { ideal: this.options.width },
                    height: { ideal: this.options.height }
                }
            };
            
            if (deviceId) {
                constraints.video.deviceId = { exact: deviceId };
            } else {
                constraints.video.facingMode = this.options.facingMode;
            }
            
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = this.stream;
            
            await new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    this.video.play();
                    resolve();
                };
            });
            
            this.isScanning = true;
            this.scan();
            
            return true;
            
        } catch (error) {
            this.options.onError('Erro ao iniciar scanner: ' + error.message);
            return false;
        }
    }
    
    stop() {
        this.isScanning = false;
        
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
            this.animationFrame = null;
        }
        
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        if (this.video.srcObject) {
            this.video.srcObject = null;
        }
    }
    
    scan() {
        if (!this.isScanning) return;
        
        if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
            // Ajustar canvas para o tamanho do vídeo
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
            
            // Desenhar frame do vídeo no canvas
            this.context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
            
            // Obter dados da imagem
            const imageData = this.context.getImageData(0, 0, this.canvas.width, this.canvas.height);
            
            // Detectar QR Code usando jsQR
            if (typeof jsQR !== 'undefined') {
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: "dontInvert",
                });
                
                if (code) {
                    this.options.onSuccess(code.data, code);
                    
                    if (!this.options.continuous) {
                        this.stop();
                        return;
                    }
                    
                    // Para modo contínuo, aguarda um pouco antes de escanear novamente
                    setTimeout(() => {
                        if (this.isScanning) {
                            this.animationFrame = requestAnimationFrame(() => this.scan());
                        }
                    }, 1000);
                    return;
                }
            }
        }
        
        // Continuar escaneando
        this.animationFrame = requestAnimationFrame(() => this.scan());
    }
    
    // Capturar uma foto do frame atual
    captureFrame() {
        if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
            this.context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
            return this.canvas.toDataURL('image/png');
        }
        return null;
    }
    
    // Verificar se o navegador suporta getUserMedia
    static isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
}

// Função utilitária para criar um scanner simples
function createQRScanner(videoId, canvasId, options = {}) {
    const video = document.getElementById(videoId);
    const canvas = document.getElementById(canvasId);
    
    if (!video || !canvas) {
        console.error('Elementos de vídeo ou canvas não encontrados');
        return null;
    }
    
    return new QRScanner(video, canvas, options);
}

// Função para validar QR Code de check-in
function validateCheckinQR(qrData) {
    try {
        const data = JSON.parse(qrData);
        
        // Verificar estrutura básica
        if (!data.tipo || data.tipo !== 'checkin') {
            return { valid: false, error: 'QR Code não é de check-in' };
        }
        
        const requiredFields = ['participante_id', 'token', 'evento_id', 'nome'];
        for (const field of requiredFields) {
            if (!data[field]) {
                return { valid: false, error: `Campo obrigatório ausente: ${field}` };
            }
        }
        
        return { valid: true, data: data };
        
    } catch (error) {
        return { valid: false, error: 'QR Code inválido ou corrompido' };
    }
}

// Função para decodificar PIX
function decodePIXQR(qrData) {
    try {
        // Função básica para decodificar EMV do PIX
        const payload = qrData;
        const result = {};
        
        let pos = 0;
        while (pos < payload.length) {
            const id = payload.substring(pos, pos + 2);
            const length = parseInt(payload.substring(pos + 2, pos + 4));
            const value = payload.substring(pos + 4, pos + 4 + length);
            
            switch (id) {
                case '00':
                    result.payloadFormatIndicator = value;
                    break;
                case '26':
                    result.merchantAccountInfo = value;
                    break;
                case '52':
                    result.merchantCategoryCode = value;
                    break;
                case '53':
                    result.transactionCurrency = value;
                    break;
                case '54':
                    result.transactionAmount = parseFloat(value);
                    break;
                case '58':
                    result.countryCode = value;
                    break;
                case '59':
                    result.merchantName = value;
                    break;
                case '60':
                    result.merchantCity = value;
                    break;
            }
            
            pos += 4 + length;
        }
        
        return { valid: true, data: result };
        
    } catch (error) {
        return { valid: false, error: 'Erro ao decodificar PIX' };
    }
}

// Exportar para uso global
window.QRScanner = QRScanner;
window.createQRScanner = createQRScanner;
window.validateCheckinQR = validateCheckinQR;
window.decodePIXQR = decodePIXQR;

// CSS para overlay do scanner (injetado dinamicamente)
const scannerCSS = `
.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.scanner-overlay::after {
    content: '';
    width: 200px;
    height: 200px;
    border: 2px solid #ffffff;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
}

.scanner-line {
    position: absolute;
    width: 200px;
    height: 2px;
    background: #ff0000;
    animation: scanLine 2s linear infinite;
}

@keyframes scanLine {
    0% { top: 0; }
    50% { top: 196px; }
    100% { top: 0; }
}

.scanner-area {
    position: relative;
    display: inline-block;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.scanner-controls {
    margin: 20px 0;
    text-align: center;
}

.scanner-controls button,
.scanner-controls select {
    margin: 0 10px;
}

.scanner-status {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
    margin: 10px 0;
    font-weight: 500;
}

.result-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
}

.result-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
}

.scan-result {
    margin: 20px 0;
    border-radius: 8px;
    overflow: hidden;
}
`;

// Injetar CSS se não existir
if (!document.getElementById('qr-scanner-styles')) {
    const style = document.createElement('style');
    style.id = 'qr-scanner-styles';
    style.textContent = scannerCSS;
    document.head.appendChild(style);
}

console.log('QR Scanner library initialized'); 