<?php
require_once '../includes/init.php';

// Verificar se é admin
if (!esta_logado()) {
    redirecionar(SITE_URL . '/admin/login.php');
}

$titulo = 'Check-in de Participantes';
$pagina = 'checkin';

// Buscar eventos ativos para seleção
$eventos = buscar_todos("
    SELECT id, nome, data_inicio, local 
    FROM eventos 
    WHERE status = 'ativo' 
    AND data_inicio >= CURDATE() 
    ORDER BY data_inicio ASC
");

$evento_selecionado = $_GET['evento'] ?? '';
$participantes = [];

if ($evento_selecionado) {
    $participantes = buscar_todos("
        SELECT 
            p.*, 
            e.nome as evento_nome, 
            pg.status as pagamento_status,
            i.status AS status_inscricao,
            i.id AS inscricao_id
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        WHERE i.evento_id = ? AND i.status != 'cancelada'
        ORDER BY p.status DESC, p.nome ASC
    ", [$evento_selecionado]);
}

obter_cabecalho_admin($titulo, $pagina);
?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/qr-scanner.css">

<div class="admin-container">
    <div class="admin-header">
        <h1>📱 Check-in de Participantes</h1>
        <p>Escaneie o QR Code dos participantes para confirmar presença</p>
    </div>

    <!-- Seleção de Evento -->
    <div class="checkin-controls">
        <div class="event-selector">
            <label for="evento-select">Selecione o evento:</label>
            <select id="evento-select" onchange="selecionarEvento()">
                <option value="">-- Escolha um evento --</option>
                <?php foreach ($eventos as $evento): ?>
                    <option value="<?= $evento['id'] ?>" <?= $evento_selecionado == $evento['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($evento['nome']) ?> - <?= formatar_data($evento['data_inicio']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($evento_selecionado): ?>
    <div class="checkin-content">
        <!-- Scanner QR Code -->
        <div class="qr-scanner-section">
            <div class="scanner-card">
                <h3>📷 Scanner QR Code</h3>
                
                <!-- Abas de Método -->
                <div class="scanner-tabs">
                    <button class="tab-btn active" data-tab="camera">📷 Câmera</button>
                    <button class="tab-btn" data-tab="upload">📤 Upload</button>
                    <button class="tab-btn" data-tab="manual">✍️ Manual</button>
                </div>
                
                <!-- Scanner por Câmera -->
                <div id="tab-camera" class="tab-content active">
                    <div class="scanner-container">
                        <video id="scanner-video" autoplay playsinline muted></video>
                        <div class="scanner-overlay">
                            <div class="scanner-frame"></div>
                            <div class="scanner-instructions">
                                <p>Posicione o QR Code dentro do quadro</p>
                            </div>
                        </div>
                        <div id="scanner-result" class="scanner-result"></div>
                    </div>
                    <div class="scanner-controls">
                        <button id="start-scanner" class="btn btn-primary">
                            <i class="icon-camera"></i> Iniciar Scanner
                        </button>
                        <button id="stop-scanner" class="btn btn-secondary" style="display: none;">
                            <i class="icon-stop"></i> Parar Scanner
                        </button>
                        <button id="switch-camera" class="btn btn-outline" style="display: none;">
                            <i class="icon-refresh"></i> Trocar Câmera
                        </button>
                    </div>
                </div>
                
                <!-- Upload de Arquivo -->
                <div id="tab-upload" class="tab-content">
                    <div class="upload-area">
                        <input type="file" id="qr-file-input" accept="image/*" style="display: none;">
                        <div class="upload-dropzone" onclick="document.getElementById('qr-file-input').click()">
                            <div class="upload-icon">📤</div>
                            <p>Clique para selecionar uma imagem</p>
                            <p><small>ou arraste e solte aqui</small></p>
                        </div>
                        <div id="upload-result" class="upload-result"></div>
                    </div>
                </div>
                
                <!-- Input Manual -->
                <div id="tab-manual" class="tab-content">
                    <div class="manual-checkin">
                        <h4>Dados do QR Code</h4>
                        <textarea id="manual-qr" placeholder="Cole o código QR (JSON) aqui..." class="form-textarea" rows="4"></textarea>
                        <button onclick="processarCheckinManual()" class="btn btn-primary">
                            <i class="icon-check"></i> Processar Check-in
                        </button>
                        
                        <div class="manual-separator">ou</div>
                        
                        <h4>Busca por Participante</h4>
                        <div class="manual-search">
                            <input type="text" id="search-participante" placeholder="Nome, CPF ou email..." class="form-input">
                            <button onclick="buscarParticipante()" class="btn btn-outline">
                                <i class="icon-search"></i> Buscar
                            </button>
                        </div>
                        <div id="search-results" class="search-results"></div>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Lista de Participantes -->
        <div class="participants-section">
            <div class="participants-header">
                <h3>👥 Lista de Participantes</h3>
                <div class="participants-stats">
                    <?php
                    $total = count($participantes);
                    $presentes = array_filter($participantes, fn($p) => $p['status'] === 'presente');
                    $count_presentes = count($presentes);
                    ?>
                    <span class="stat">Total: <?= $total ?></span>
                    <span class="stat present">Presentes: <?= $count_presentes ?></span>
                    <span class="stat pending">Pendentes: <?= $total - $count_presentes ?></span>
                </div>
            </div>

            <div class="participants-filters">
                <input type="text" id="search-participant" placeholder="Buscar participante..." class="form-input">
                <select id="filter-status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="presente">Presentes</option>
                    <option value="pago">Aguardando check-in</option>
                    <option value="inscrito">Não pagos</option>
                </select>
            </div>

            <div class="participants-list" id="participants-list">
                <?php foreach ($participantes as $participante): ?>
                    <div class="participant-item <?= $participante['status'] ?>" data-id="<?= $participante['id'] ?>" data-name="<?= strtolower($participante['nome']) ?>" data-status="<?= $participante['status'] ?>">
                        <div class="participant-avatar">
                            <?php if ($participante['status'] === 'presente'): ?>
                                <i class="icon-check-circle"></i>
                            <?php else: ?>
                                <span><?= strtoupper(substr($participante['nome'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="participant-info">
                            <h4><?= htmlspecialchars($participante['nome']) ?></h4>
                            <p><?= htmlspecialchars($participante['email']) ?></p>
                            <div class="participant-meta">
                                <span class="status-badge <?= $participante['status'] ?>">
                                    <?= ucfirst($participante['status']) ?>
                                </span>
                                <?php if ($participante['checkin_timestamp']): ?>
                                    <span class="checkin-time">
                                        Check-in: <?= formatar_data_hora($participante['checkin_timestamp']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="participant-actions">
                            <?php if ($participante['status'] !== 'presente'): ?>
                                <button onclick="fazerCheckin(<?= $participante['id'] ?>)" class="btn btn-sm btn-success">
                                    <i class="icon-check"></i> Check-in
                                </button>
                            <?php else: ?>
                                <button onclick="desfazerCheckin(<?= $participante['id'] ?>)" class="btn btn-sm btn-warning">
                                    <i class="icon-undo"></i> Desfazer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Toast para notificações -->
<div id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="<?= SITE_URL ?>/assets/js/qr-scanner-melhorado.js"></script>
<script>
// Variáveis globais
let vindeScanner = null;
let scannerActive = false;
let currentCameraIndex = 0;
let availableCameras = [];

document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
    setupScanner();
    setupTabs();
    setupFileUpload();
});

function selecionarEvento() {
    const select = document.getElementById('evento-select');
    if (select.value) {
        window.location.href = `checkin.php?evento=${select.value}`;
    }
}

async function setupScanner() {
    const video = document.getElementById('scanner-video');
    const startBtn = document.getElementById('start-scanner');
    const stopBtn = document.getElementById('stop-scanner');
    const switchBtn = document.getElementById('switch-camera');
    const result = document.getElementById('scanner-result');

    // Verificar suporte à câmera
    const hasCamera = await VindeQRScanner.checkCameraSupport();
    if (!hasCamera) {
        result.innerHTML = '<div class="qr-error">Câmera não disponível neste dispositivo</div>';
        startBtn.disabled = true;
        return;
    }

    // Obter câmeras disponíveis
    availableCameras = await VindeQRScanner.getAvailableCameras();
    if (availableCameras.length > 1) {
        switchBtn.style.display = 'inline-flex';
    }

    // Inicializar scanner
    vindeScanner = new VindeQRScanner();
    await vindeScanner.initialize(video, {
        onQRDetected: processarQRCode,
        onError: (error) => {
            showToast('Erro no scanner: ' + error.message, 'error');
            console.error('Scanner error:', error);
        }
    });

    // Event listeners
    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', stopScanner);
    switchBtn.addEventListener('click', switchCamera);

    async function startScanner() {
        try {
            result.innerHTML = '<div class="qr-loading">Iniciando scanner...</div>';
            
            await vindeScanner.startScanning();
            
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-flex';
            result.innerHTML = '<div class="qr-scanning">🎯 Procurando QR Code...</div>';
            
            scannerActive = true;
            showToast('Scanner iniciado! Aponte para um QR Code', 'info');
            
        } catch (err) {
            showToast('Erro ao iniciar scanner: ' + err.message, 'error');
            result.innerHTML = '<div class="qr-error">Erro: ' + err.message + '</div>';
        }
    }

    function stopScanner() {
        vindeScanner.stopScanning();
        
        startBtn.style.display = 'inline-flex';
        stopBtn.style.display = 'none';
        result.innerHTML = '';
        
        scannerActive = false;
        showToast('Scanner parado', 'info');
    }

    async function switchCamera() {
        if (availableCameras.length <= 1) return;
        
        currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
        
        // Parar scanner atual
        if (scannerActive) {
            stopScanner();
            
            // Aguardar um pouco e reiniciar com nova câmera
            setTimeout(() => {
                vindeScanner.config.video.deviceId = availableCameras[currentCameraIndex].deviceId;
                startScanner();
            }, 500);
        }
    }
}

function processarQRCode(qrData) {
    try {
        const data = JSON.parse(qrData);
        
        if (data.tipo === 'checkin' && data.participante_id && data.token) {
            fazerCheckinPorQR(data.participante_id, data.token);
        } else {
            showToast('QR Code inválido para check-in', 'error');
            console.log('Dados do QR Code:', data);
        }
    } catch (error) {
        showToast('Erro ao processar QR Code: ' + error.message, 'error');
        console.error('Erro ao fazer parse do QR Code:', error);
        console.log('QR Data bruto:', qrData);
    }
}

// Configurar abas
function setupTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Remover classe ativa de todas as abas
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Ativar aba selecionada
            this.classList.add('active');
            document.getElementById(`tab-${targetTab}`).classList.add('active');
            
            // Parar scanner se mudou de aba
            if (targetTab !== 'camera' && scannerActive) {
                vindeScanner.stopScanning();
                document.getElementById('start-scanner').style.display = 'inline-flex';
                document.getElementById('stop-scanner').style.display = 'none';
                scannerActive = false;
            }
        });
    });
}

// Configurar upload de arquivo
function setupFileUpload() {
    const fileInput = document.getElementById('qr-file-input');
    const dropzone = document.querySelector('.upload-dropzone');
    const uploadResult = document.getElementById('upload-result');
    
    // Upload via input
    fileInput.addEventListener('change', handleFileUpload);
    
    // Drag and drop
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });
    
    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload({ target: { files: files } });
        }
    });
    
    async function handleFileUpload(event) {
        const file = event.target.files[0];
        
        if (!file) return;
        
        if (!file.type.startsWith('image/')) {
            showToast('Por favor, selecione uma imagem', 'error');
            return;
        }
        
        uploadResult.innerHTML = '<div class="upload-loading">📤 Processando imagem...</div>';
        
        try {
            const qrData = await vindeScanner.scanFromFile(file);
            
            uploadResult.innerHTML = '<div class="upload-success">✅ QR Code detectado!</div>';
            processarQRCode(qrData);
            
        } catch (error) {
            console.error('Erro ao processar arquivo:', error);
            uploadResult.innerHTML = '<div class="upload-error">❌ ' + error.message + '</div>';
            showToast('Erro ao processar imagem: ' + error.message, 'error');
        }
    }
}

function processarCheckinManual() {
    const manualInput = document.getElementById('manual-qr');
    const qrData = manualInput.value.trim();
    
    if (!qrData) {
        showToast('Digite ou cole o código QR', 'warning');
        return;
    }
    
    processarQRCode(qrData);
    manualInput.value = '';
}

// Buscar participante por nome/CPF
async function buscarParticipante() {
    const searchInput = document.getElementById('search-participante');
    const searchResults = document.getElementById('search-results');
    const query = searchInput.value.trim();
    
    if (!query) {
        showToast('Digite um nome, CPF ou email para buscar', 'warning');
        return;
    }
    
    if (query.length < 3) {
        showToast('Digite pelo menos 3 caracteres', 'warning');
        return;
    }
    
    searchResults.innerHTML = '<div class="search-loading">🔍 Buscando...</div>';
    
    try {
        const response = await fetch('api/checkin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'search_participant',
                query: query,
                evento_id: document.getElementById('evento-select').value
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.participants && result.participants.length > 0) {
            let html = '<div class="search-results-list">';
            
            result.participants.forEach(p => {
                const statusClass = p.status === 'presente' ? 'presente' : 'pendente';
                const statusText = p.status === 'presente' ? 'Presente' : 'Pendente';
                
                html += `
                    <div class="search-result-item">
                        <div class="participant-info">
                            <strong>${p.nome}</strong><br>
                            <small>${p.email}</small><br>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </div>
                        <div class="participant-actions">
                            ${p.status !== 'presente' ? 
                                `<button onclick="fazerCheckin(${p.id})" class="btn btn-sm btn-success">Check-in</button>` :
                                `<button onclick="desfazerCheckin(${p.id})" class="btn btn-sm btn-warning">Desfazer</button>`
                            }
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            searchResults.innerHTML = html;
            
        } else {
            searchResults.innerHTML = '<div class="search-empty">Nenhum participante encontrado</div>';
        }
        
    } catch (error) {
        console.error('Erro na busca:', error);
        searchResults.innerHTML = '<div class="search-error">Erro na busca</div>';
        showToast('Erro ao buscar participante', 'error');
    }
}

function fazerCheckinPorQR(participanteId, token) {
    fetch('api/checkin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'checkin_qr',
            participante_id: participanteId,
            token: token
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Check-in realizado: ${data.participante_nome}`, 'success');
            atualizarParticipanteNaLista(participanteId, 'presente');
        } else {
            showToast(data.message || 'Erro ao realizar check-in', 'error');
        }
    })
    .catch(error => {
        showToast('Erro na comunicação: ' + error.message, 'error');
    });
}

function fazerCheckin(participanteId) {
    if (!confirm('Confirmar check-in deste participante?')) return;
    
    fetch('api/checkin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'checkin_manual',
            participante_id: participanteId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Check-in realizado: ${data.participante_nome}`, 'success');
            atualizarParticipanteNaLista(participanteId, 'presente');
        } else {
            showToast(data.message || 'Erro ao realizar check-in', 'error');
        }
    })
    .catch(error => {
        showToast('Erro na comunicação: ' + error.message, 'error');
    });
}

function desfazerCheckin(participanteId) {
    if (!confirm('Desfazer check-in deste participante?')) return;
    
    fetch('api/checkin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'undo_checkin',
            participante_id: participanteId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Check-in desfeito: ${data.participante_nome}`, 'success');
            atualizarParticipanteNaLista(participanteId, 'pago');
        } else {
            showToast(data.message || 'Erro ao desfazer check-in', 'error');
        }
    })
    .catch(error => {
        showToast('Erro na comunicação: ' + error.message, 'error');
    });
}

function atualizarParticipanteNaLista(participanteId, novoStatus) {
    const item = document.querySelector(`[data-id="${participanteId}"]`);
    if (item) {
        item.className = `participant-item ${novoStatus}`;
        item.dataset.status = novoStatus;
        
        const avatar = item.querySelector('.participant-avatar');
        const statusBadge = item.querySelector('.status-badge');
        const actions = item.querySelector('.participant-actions');
        
        if (novoStatus === 'presente') {
            avatar.innerHTML = '<i class="icon-check-circle"></i>';
            statusBadge.textContent = 'Presente';
            statusBadge.className = 'status-badge presente';
            actions.innerHTML = `
                <button onclick="desfazerCheckin(${participanteId})" class="btn btn-sm btn-warning">
                    <i class="icon-undo"></i> Desfazer
                </button>
            `;
        } else {
            const nome = item.querySelector('h4').textContent;
            avatar.innerHTML = `<span>${nome.charAt(0).toUpperCase()}</span>`;
            statusBadge.textContent = 'Pago';
            statusBadge.className = 'status-badge pago';
            actions.innerHTML = `
                <button onclick="fazerCheckin(${participanteId})" class="btn btn-sm btn-success">
                    <i class="icon-check"></i> Check-in
                </button>
            `;
        }
        
        // Atualizar estatísticas
        atualizarEstatisticas();
    }
}

function atualizarEstatisticas() {
    const items = document.querySelectorAll('.participant-item');
    const presentes = document.querySelectorAll('.participant-item.presente').length;
    const total = items.length;
    
    document.querySelector('.stat.present').textContent = `Presentes: ${presentes}`;
    document.querySelector('.stat.pending').textContent = `Pendentes: ${total - presentes}`;
}

function initializeFilters() {
    const searchInput = document.getElementById('search-participant');
    const statusFilter = document.getElementById('filter-status');
    
    if (searchInput) {
        searchInput.addEventListener('input', filtrarParticipantes);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filtrarParticipantes);
    }
}

function filtrarParticipantes() {
    const searchTerm = document.getElementById('search-participant')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('filter-status')?.value || '';
    const items = document.querySelectorAll('.participant-item');
    
    items.forEach(item => {
        const name = item.dataset.name;
        const status = item.dataset.status;
        
        const matchName = name.includes(searchTerm);
        const matchStatus = !statusFilter || status === statusFilter;
        
        item.style.display = matchName && matchStatus ? 'flex' : 'none';
    });
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()">&times;</button>
        </div>
    `;
    
    document.getElementById('toast-container').appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}
</script>

<?php obter_rodape_admin(); ?> 