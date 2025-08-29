<?php
require_once '../includes/init.php';
require_once 'includes/auth.php';

// Simular o contexto do participantes.php
$acao = $_GET['acao'] ?? 'listar';

// Buscar eventos para os filtros
try {
    $stmt = $pdo->query("SELECT id, nome FROM eventos ORDER BY nome");
    $eventos = $stmt->fetchAll();
} catch (Exception $e) {
    $eventos = [];
}

// Calcular estatísticas básicas
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM participantes");
    $stats = ['total' => $stmt->fetch()['total'] ?? 0, 'inscritos' => 0, 'pagos' => 0, 'presentes' => 0];
} catch (Exception $e) {
    $stats = ['total' => 0, 'inscritos' => 0, 'pagos' => 0, 'presentes' => 0];
}

obter_cabecalho_admin('Debug HTML Structure', 'participantes');
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/participantes-cards.css?v=<?= time() ?>">

<style>
/* Debug styles */
.debug-info {
    background: #f0f9ff;
    border: 2px solid #0ea5e9;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    font-family: monospace;
}

.debug-section {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 4px;
    padding: 0.5rem;
    margin: 0.5rem 0;
}

#participantes-container {
    border: 3px dashed #ef4444 !important;
    min-height: 200px !important;
    background: rgba(239, 68, 68, 0.1) !important;
}

.participantes-grid {
    border: 2px dashed #10b981 !important;
    background: rgba(16, 185, 129, 0.1) !important;
    min-height: 100px !important;
}

.participante-card {
    border: 2px solid #3b82f6 !important;
    background: rgba(59, 130, 246, 0.1) !important;
}
</style>

<div class="debug-info">
    <h3>🔍 Debug HTML Structure - Participantes</h3>
    <p><strong>Ação:</strong> <?= htmlspecialchars($acao) ?></p>
    <p><strong>Condição PHP ($acao === 'listar'):</strong> <?= $acao === 'listar' ? '✅ TRUE' : '❌ FALSE' ?></p>
    <p><strong>Total de eventos:</strong> <?= count($eventos) ?></p>
    <p><strong>Stats total:</strong> <?= $stats['total'] ?></p>
</div>

<?php if ($acao === 'listar'): ?>
    <div class="debug-section">
        <strong>✅ Entrando na seção de listagem (ação === 'listar')</strong>
    </div>
    
    <!-- Estatísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $stats['total'] ?></h3>
            <p>Total de Participantes</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['inscritos'] ?></h3>
            <p>Aguardando Pagamento</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['pagos'] ?></h3>
            <p>Pagos</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['presentes'] ?></h3>
            <p>Presentes</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-section">
        <div class="debug-section">
            <strong>📋 Seção de Filtros</strong>
        </div>
        <div class="filtros-grid">
            <div class="filtro-group">
                <label class="filtro-label">Buscar</label>
                <input type="text" id="filtro-busca" class="filtro-input" placeholder="Nome, CPF ou email...">
            </div>
            
            <div class="filtro-group">
                <label class="filtro-label">Evento</label>
                <select id="filtro-evento" class="filtro-input">
                    <option value="">Todos os eventos</option>
                    <?php foreach ($eventos as $ev): ?>
                        <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filtro-group">
                <label class="filtro-label">Ações</label>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="limparFiltros()" class="btn btn-outline">Limpar</button>
                    <a href="<?= SITE_URL ?>/admin/participantes.php?acao=criar" class="btn btn-primary">Novo Participante</a>
                </div>
            </div>
        </div>
    </div>

    <div class="debug-section">
        <strong>🎯 Contêiner Principal dos Participantes</strong>
        <p>ID: #participantes-container</p>
        <p>Este contêiner deve receber os cards via JavaScript</p>
    </div>
    
    <!-- Grid de Participantes -->
    <div id="participantes-container">
        <div class="debug-section">
            <strong>⏳ Estado Inicial de Loading</strong>
        </div>
        <div class="loading">
            <div class="loading-spinner"></div>
            Carregando participantes...
        </div>
    </div>
    
    <div class="debug-section">
        <strong>📊 Console de Debug JavaScript</strong>
        <div id="debug-console" style="background: #1f2937; color: #f9fafb; padding: 1rem; border-radius: 4px; font-family: monospace; max-height: 300px; overflow-y: auto;"></div>
    </div>

<?php else: ?>
    <div class="debug-section">
        <strong>❌ Não está na seção de listagem</strong>
        <p>Ação atual: <?= htmlspecialchars($acao) ?></p>
        <p>JavaScript não será carregado</p>
    </div>
<?php endif; ?>

<script>
// Interceptar console.log para exibir no HTML
const originalLog = console.log;
const originalError = console.error;
const originalWarn = console.warn;
const debugConsole = document.getElementById('debug-console');

function addToDebugConsole(message, type = 'log') {
    if (debugConsole) {
        const timestamp = new Date().toLocaleTimeString();
        const color = type === 'error' ? '#ef4444' : type === 'warn' ? '#f59e0b' : '#10b981';
        debugConsole.innerHTML += `<div style="color: ${color}; margin: 2px 0;">[${timestamp}] ${message}</div>`;
        debugConsole.scrollTop = debugConsole.scrollHeight;
    }
}

console.log = function(...args) {
    originalLog.apply(console, args);
    addToDebugConsole(args.join(' '), 'log');
};

console.error = function(...args) {
    originalError.apply(console, args);
    addToDebugConsole('ERROR: ' + args.join(' '), 'error');
};

console.warn = function(...args) {
    originalWarn.apply(console, args);
    addToDebugConsole('WARN: ' + args.join(' '), 'warn');
};

// Log inicial
console.log('🚀 Debug HTML Structure carregado');
console.log('📍 Ação PHP: <?= $acao ?>');
console.log('🔍 Condição listar: <?= $acao === 'listar' ? 'true' : 'false' ?>');

// Verificar se o contêiner existe
const container = document.getElementById('participantes-container');
console.log('📦 Contêiner encontrado:', !!container);
if (container) {
    console.log('📏 Dimensões do contêiner:', {
        width: container.offsetWidth,
        height: container.offsetHeight,
        display: getComputedStyle(container).display,
        visibility: getComputedStyle(container).visibility
    });
}

// Verificar se o CSS foi carregado
const participantesGrid = document.querySelector('.participantes-grid');
console.log('🎨 Grid CSS encontrado:', !!participantesGrid);

// Simular criação de um card de teste
function criarCardTeste() {
    console.log('🧪 Criando card de teste...');
    
    if (!container) {
        console.error('❌ Contêiner não encontrado!');
        return;
    }
    
    // Limpar loading
    container.innerHTML = '';
    
    // Criar grid
    const grid = document.createElement('div');
    grid.className = 'participantes-grid';
    
    // Criar card de teste
    const card = document.createElement('div');
    card.className = 'participante-card';
    card.innerHTML = `
        <div class="participante-header">
            <div class="participante-info-principal">
                <h3 class="participante-nome">🧪 CARD DE TESTE</h3>
                <p class="participante-evento">Evento de Teste</p>
                <p class="participante-cpf">123.456.789-00</p>
            </div>
        </div>
        <div class="participante-badges">
            <span class="status-badge status-inscrito">Teste</span>
        </div>
    `;
    
    grid.appendChild(card);
    container.appendChild(grid);
    
    console.log('✅ Card de teste criado!');
    console.log('📊 Cards no DOM:', container.querySelectorAll('.participante-card').length);
}

// Aguardar DOM e criar card de teste
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 DOM carregado');
    
    // Aguardar um pouco e criar card de teste
    setTimeout(() => {
        criarCardTeste();
    }, 1000);
});

// Função para limpar filtros (compatibilidade)
function limparFiltros() {
    console.log('🧹 Limpando filtros...');
}
</script>

<?php obter_rodape_admin(); ?>