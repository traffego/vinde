/**
 * Scanner de QR Code Melhorado para Check-in
 * Sistema Vinde - Eventos Católicos
 */

class VindeQRScanner {
    constructor() {
        this.isScanning = false;
        this.video = null;
        this.canvas = null;
        this.stream = null;
        this.animationFrame = null;
        this.onQRDetected = null;
        this.onError = null;
        
        // Configurações
        this.config = {
            video: {
                facingMode: 'environment', // Câmera traseira
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            scanInterval: 100, // ms entre scans
            beepOnSuccess: true,
            vibrationOnSuccess: true
        };
        
        this.initializeLibraries();
    }
    
    async initializeLibraries() {
        // Carregar jsQR se não estiver disponível
        if (typeof jsQR === 'undefined') {
            try {
                await this.loadScript('https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js');
                console.log('✅ jsQR carregado com sucesso');
            } catch (error) {
                console.warn('⚠️ Erro ao carregar jsQR:', error);
            }
        }
    }
    
    loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    /**
     * Inicializar scanner em um elemento
     */
    async initialize(videoElement, options = {}) {
        this.video = typeof videoElement === 'string' 
            ? document.getElementById(videoElement) 
            : videoElement;
            
        if (!this.video) {
            throw new Error('Elemento de vídeo não encontrado');
        }
        
        // Configurar callbacks
        this.onQRDetected = options.onQRDetected || this.defaultOnQRDetected;
        this.onError = options.onError || this.defaultOnError;
        
        // Criar canvas para processamento
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
        
        console.log('🎯 Scanner QR inicializado');
    }
    
    /**
     * Iniciar scanner
     */
    async startScanning() {
        if (this.isScanning) {
            console.warn('⚠️ Scanner já está ativo');
            return;
        }
        
        try {
            console.log('🚀 Iniciando scanner...');
            
            // Verificar se getUserMedia está disponível
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Câmera não suportada neste navegador');
            }
            
            // Solicitar acesso à câmera
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: this.config.video,
                audio: false
            });
            
            // Configurar vídeo
            this.video.srcObject = this.stream;
            this.video.setAttribute('playsinline', true);
            
            await new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    this.video.play();
                    resolve();
                };
            });
            
            // Configurar canvas
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
            
            this.isScanning = true;
            this.scanLoop();
            
            console.log('✅ Scanner iniciado com sucesso');
            
        } catch (error) {
            console.error('❌ Erro ao iniciar scanner:', error);
            this.onError(error);
            throw error;
        }
    }
    
    /**
     * Parar scanner
     */
    stopScanning() {
        if (!this.isScanning) return;
        
        console.log('⏹️ Parando scanner...');
        
        this.isScanning = false;
        
        // Parar stream de vídeo
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        // Cancelar animation frame
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
            this.animationFrame = null;
        }
        
        // Limpar vídeo
        if (this.video) {
            this.video.srcObject = null;
        }
        
        console.log('✅ Scanner parado');
    }
    
    /**
     * Loop principal de scanning
     */
    scanLoop() {
        if (!this.isScanning) return;
        
        try {
            if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
                // Desenhar frame atual no canvas
                this.ctx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
                
                // Obter dados da imagem
                const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
                
                // Tentar detectar QR Code
                if (typeof jsQR !== 'undefined') {
                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                    
                    if (code && code.data) {
                        console.log('🎯 QR Code detectado:', code.data);
                        this.onQRCodeDetected(code.data);
                        return; // Para o scanner após detectar
                    }
                }
            }
        } catch (error) {
            console.error('❌ Erro no loop de scan:', error);
        }
        
        // Continuar scanning
        this.animationFrame = requestAnimationFrame(() => this.scanLoop());
    }
    
    /**
     * Processar QR Code detectado
     */
    onQRCodeDetected(data) {
        try {
            // Tocar som de sucesso
            if (this.config.beepOnSuccess) {
                this.playBeep();
            }
            
            // Vibrar (se suportado)
            if (this.config.vibrationOnSuccess && navigator.vibrate) {
                navigator.vibrate(200);
            }
            
            // Parar scanner
            this.stopScanning();
            
            // Callback personalizado
            this.onQRDetected(data);
            
        } catch (error) {
            console.error('❌ Erro ao processar QR Code:', error);
            this.onError(error);
        }
    }
    
    /**
     * Scanner via upload de arquivo
     */
    async scanFromFile(file) {
        return new Promise((resolve, reject) => {
            try {
                const reader = new FileReader();
                
                reader.onload = (e) => {
                    const img = new Image();
                    
                    img.onload = () => {
                        try {
                            // Criar canvas temporário
                            const tempCanvas = document.createElement('canvas');
                            const tempCtx = tempCanvas.getContext('2d');
                            
                            tempCanvas.width = img.width;
                            tempCanvas.height = img.height;
                            
                            // Desenhar imagem
                            tempCtx.drawImage(img, 0, 0);
                            
                            // Obter dados da imagem
                            const imageData = tempCtx.getImageData(0, 0, img.width, img.height);
                            
                            // Tentar detectar QR Code
                            if (typeof jsQR !== 'undefined') {
                                const code = jsQR(imageData.data, imageData.width, imageData.height);
                                
                                if (code && code.data) {
                                    console.log('🎯 QR Code detectado no arquivo:', code.data);
                                    resolve(code.data);
                                } else {
                                    reject(new Error('Nenhum QR Code encontrado na imagem'));
                                }
                            } else {
                                reject(new Error('Biblioteca de detecção não disponível'));
                            }
                            
                        } catch (error) {
                            reject(error);
                        }
                    };
                    
                    img.onerror = () => reject(new Error('Erro ao carregar imagem'));
                    img.src = e.target.result;
                };
                
                reader.onerror = () => reject(new Error('Erro ao ler arquivo'));
                reader.readAsDataURL(file);
                
            } catch (error) {
                reject(error);
            }
        });
    }
    
    /**
     * Tocar som de beep
     */
    playBeep() {
        try {
            // Criar AudioContext se não existir
            if (!this.audioContext) {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            // Criar oscilador para beep
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            oscillator.frequency.value = 800; // Hz
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.1, this.audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.2);
            
            oscillator.start(this.audioContext.currentTime);
            oscillator.stop(this.audioContext.currentTime + 0.2);
            
        } catch (error) {
            console.warn('⚠️ Não foi possível tocar som:', error);
        }
    }
    
    /**
     * Callbacks padrão
     */
    defaultOnQRDetected(data) {
        console.log('📱 QR Code detectado:', data);
        alert('QR Code detectado: ' + data);
    }
    
    defaultOnError(error) {
        console.error('❌ Erro no scanner:', error);
        alert('Erro: ' + error.message);
    }
    
    /**
     * Verificar suporte à câmera
     */
    static async checkCameraSupport() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return false;
            }
            
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.some(device => device.kind === 'videoinput');
            
        } catch (error) {
            console.warn('⚠️ Erro ao verificar suporte à câmera:', error);
            return false;
        }
    }
    
    /**
     * Listar câmeras disponíveis
     */
    static async getAvailableCameras() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.filter(device => device.kind === 'videoinput');
        } catch (error) {
            console.warn('⚠️ Erro ao listar câmeras:', error);
            return [];
        }
    }
}

// Disponibilizar globalmente
window.VindeQRScanner = VindeQRScanner;

console.log('📱 VindeQRScanner carregado com sucesso');
