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
        SELECT p.*, e.nome as evento_nome, pag.status as pagamento_status
        FROM participantes p
        JOIN eventos e ON p.evento_id = e.id
        LEFT JOIN pagamentos pag ON p.id = pag.participante_id
        WHERE p.evento_id = ?
        ORDER BY p.status DESC, p.nome ASC
    ", [$evento_selecionado]);
}

obter_cabecalho_admin($titulo, $pagina);
?>

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
                <div class="scanner-container">
                    <video id="scanner-video" autoplay playsinline></video>
                    <div class="scanner-overlay">
                        <div class="scanner-frame"></div>
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
                </div>
                <div class="manual-checkin">
                    <h4>Check-in Manual</h4>
                    <input type="text" id="manual-qr" placeholder="Cole o código QR aqui..." class="form-input">
                    <button onclick="processarCheckinManual()" class="btn btn-outline">
                        <i class="icon-check"></i> Processar
                    </button>
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
<script>
// Variáveis globais
let scanner = null;
let scannerActive = false;

document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
    setupScanner();
});

function selecionarEvento() {
    const select = document.getElementById('evento-select');
    if (select.value) {
        window.location.href = `checkin.php?evento=${select.value}`;
    }
}

function setupScanner() {
    const video = document.getElementById('scanner-video');
    const startBtn = document.getElementById('start-scanner');
    const stopBtn = document.getElementById('stop-scanner');
    const result = document.getElementById('scanner-result');

    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', stopScanner);

    async function startScanner() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            
            video.srcObject = stream;
            video.play();
            
            scannerActive = true;
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-flex';
            
            // Iniciar detecção de QR Code
            requestAnimationFrame(detectQRCode);
            
        } catch (err) {
            showToast('Erro ao acessar a câmera: ' + err.message, 'error');
        }
    }

    function stopScanner() {
        if (video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
        
        scannerActive = false;
        startBtn.style.display = 'inline-flex';
        stopBtn.style.display = 'none';
        result.innerHTML = '';
    }

    function detectQRCode() {
        if (!scannerActive) return;

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            
            if (code) {
                result.innerHTML = `<div class="qr-detected">QR Code detectado!</div>`;
                processarQRCode(code.data);
                stopScanner();
            }
        }
        
        requestAnimationFrame(detectQRCode);
    }
}

function processarQRCode(qrData) {
    try {
        const data = JSON.parse(qrData);
        
        if (data.tipo === 'checkin' && data.participante_id && data.token) {
            fazerCheckinPorQR(data.participante_id, data.token);
        } else {
            showToast('QR Code inválido para check-in', 'error');
        }
    } catch (error) {
        showToast('Erro ao processar QR Code: ' + error.message, 'error');
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