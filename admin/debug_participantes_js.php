<?php
require_once '../includes/init.php';
requer_login();

// Simular o mesmo contexto do participantes.php
$acao = $_GET['acao'] ?? 'listar';
$participante_id = $_GET['id'] ?? null;
$evento_id = $_GET['evento'] ?? null;

// Eventos para filtro
$eventos = buscar_todos("SELECT id, nome FROM eventos ORDER BY data_inicio DESC");

// Verificar se sistema foi migrado
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

// Estatísticas
if ($tabela_inscricoes_existe) {
    $stats = buscar_um("
        SELECT 
            COUNT(DISTINCT i.id) as total,
            SUM(CASE WHEN pag.status IS NULL OR pag.status = 'pendente' THEN 1 ELSE 0 END) as inscritos,
            SUM(CASE WHEN pag.status = 'pago' THEN 1 ELSE 0 END) as pagos,
            SUM(CASE WHEN p.status = 'presente' THEN 1 ELSE 0 END) as presentes
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        LEFT JOIN pagamentos pag ON pag.inscricao_id = i.id
        WHERE i.status != 'cancelada'
    ");
} else {
    $stats = buscar_um("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'inscrito' THEN 1 ELSE 0 END) as inscritos,
            SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pagos,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes
        FROM participantes
        WHERE status != 'cancelado'
    ");
}

echo obter_cabecalho_admin('Participantes - Debug JS');
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/participantes-cards.css">

<div class="admin-container">
    <div class="admin-header">
        <h1>🐛 Debug JavaScript - Participantes</h1>
        <p><strong>Ação:</strong> <?= htmlspecialchars($acao) ?></p>
        <p><strong>Condição JavaScript:</strong> <?= $acao === 'listar' ? '✅ VERDADEIRA' : '❌ FALSA' ?></p>
    </div>

    <!-- Console de Debug -->
    <div style="background: #000; color: #0f0; padding: 15px; margin: 20px 0; border-radius: 5px; font-family: monospace; max-height: 300px; overflow-y: auto;" id="debug-console">
        <h3 style="color: #fff; margin-top: 0;">📊 Console de Debug:</h3>
        <div id="console-output"></div>
    </div>

    <!-- Seção de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= number_format($stats['total'] ?? 0) ?></h3>
            <p>Total</p>
        </div>
        <div class="stat-card">
            <h3><?= number_format($stats['inscritos'] ?? 0) ?></h3>
            <p>Aguardando Pagamento</p>
        </div>
        <div class="stat-card">
            <h3><?= number_format($stats['pagos'] ?? 0) ?></h3>
            <p>Pagos</p>
        </div>
        <div class="stat-card">
            <h3><?= number_format($stats['presentes'] ?? 0) ?></h3>
            <p>Presentes</p>
        </div>
    </div>

    <!-- Container dos Participantes -->
    <div id="participantes-container" class="participantes-container">
        <div class="loading-container">
            <div class="loading-spinner"></div>
            <p>Carregando participantes...</p>
        </div>
    </div>

    <!-- Botões de Teste -->
    <div style="margin: 20px 0; text-align: center;">
        <button onclick="testarCarregamento()" style="padding: 10px 20px; margin: 5px; background: #007cba; color: white; border: none; border-radius: 5px;">🔄 Testar Carregamento Manual</button>
        <button onclick="verificarVariaveis()" style="padding: 10px 20px; margin: 5px; background: #28a745; color: white; border: none; border-radius: 5px;">🔍 Verificar Variáveis</button>
        <button onclick="verificarDOM()" style="padding: 10px 20px; margin: 5px; background: #ffc107; color: black; border: none; border-radius: 5px;">🏗️ Verificar DOM</button>
        <button onclick="limparConsole()" style="padding: 10px 20px; margin: 5px; background: #dc3545; color: white; border: none; border-radius: 5px;">🗑️ Limpar Console</button>
    </div>
</div>

<script>
// Interceptar console.log para exibir no HTML
const originalLog = console.log;
const originalError = console.error;
const originalWarn = console.warn;

function addToDebugConsole(message, type = 'log') {
    const output = document.getElementById('console-output');
    const timestamp = new Date().toLocaleTimeString();
    const color = type === 'error' ? '#f00' : type === 'warn' ? '#ff0' : '#0f0';
    output.innerHTML += `<div style="color: ${color}; margin: 2px 0;">[${timestamp}] ${type.toUpperCase()}: ${message}</div>`;
    output.scrollTop = output.scrollHeight;
}

console.log = function(...args) {
    originalLog.apply(console, args);
    addToDebugConsole(args.join(' '), 'log');
};

console.error = function(...args) {
    originalError.apply(console, args);
    addToDebugConsole(args.join(' '), 'error');
};

console.warn = function(...args) {
    originalWarn.apply(console, args);
    addToDebugConsole(args.join(' '), 'warn');
};

function limparConsole() {
    document.getElementById('console-output').innerHTML = '';
}

function testarCarregamento() {
    console.log('🧪 Teste manual de carregamento iniciado...');
    if (typeof carregarParticipantes === 'function') {
        carregarParticipantes(true);
    } else {
        console.error('❌ Função carregarParticipantes não encontrada!');
    }
}

function verificarVariaveis() {
    console.log('🔍 Verificando variáveis globais...');
    console.log('participantesData:', typeof participantesData, participantesData);
    console.log('filtrosAtivos:', typeof filtrosAtivos, filtrosAtivos);
    console.log('offsetAtual:', typeof offsetAtual, offsetAtual);
    console.log('carregandoMais:', typeof carregandoMais, carregandoMais);
    console.log('temMaisParticipantes:', typeof temMaisParticipantes, temMaisParticipantes);
}

function verificarDOM() {
    console.log('🏗️ Verificando estrutura do DOM...');
    const container = document.getElementById('participantes-container');
    console.log('Container encontrado:', !!container);
    if (container) {
        console.log('Container innerHTML length:', container.innerHTML.length);
        console.log('Container children:', container.children.length);
    }
    
    const grid = document.querySelector('.participantes-grid');
    console.log('Grid encontrada:', !!grid);
    if (grid) {
        console.log('Grid children:', grid.children.length);
    }
}

// Interceptar erros não tratados
window.addEventListener('error', function(e) {
    console.error('❌ Erro JavaScript:', e.message, 'em', e.filename, 'linha', e.lineno);
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('❌ Promise rejeitada:', e.reason);
});

console.log('🚀 Debug JS carregado, console interceptado');

// Variáveis globais (copiadas do participantes.php original)
let participantesData = [];
let filtrosAtivos = {};
let offsetAtual = 0;
let carregandoMais = false;
let temMaisParticipantes = true;

console.log('✅ Variáveis globais inicializadas');

// Funções auxiliares
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatarCpf(cpf) {
    return cpf ? cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '';
}

function formatarMoeda(valor) {
    return parseFloat(valor).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function removerLoadingIndicator() {
    const loading = document.querySelector('.loading-indicator');
    if (loading) loading.remove();
}

function adicionarLoadingIndicator() {
    // Implementação simplificada
}

// CÓDIGO JAVASCRIPT ORIGINAL DO PARTICIPANTES.PHP

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 DOMContentLoaded disparado!');
    console.log('🎯 Ação atual:', '<?= $acao ?>');
    console.log('🎯 Condição ($acao === "listar"):', <?= $acao === 'listar' ? 'true' : 'false' ?>);
    
    <?php if ($acao === 'listar'): ?>
        console.log('✅ Condição PHP verdadeira - executando JavaScript...');
        carregarParticipantes(true); // Sempre resetar quando filtrar
        console.log('✅ carregarParticipantes(true) chamado');
    <?php else: ?>
        console.log('❌ Condição PHP falsa - JavaScript NÃO será executado');
    <?php endif; ?>
});

// Carregar participantes via AJAX (função original)
function carregarParticipantes(resetar = true) {
    console.log('🚀 carregarParticipantes chamada, resetar:', resetar);
    
    if (carregandoMais) {
        console.log('⏸️ Já carregando, abortando...');
        return;
    }
    
    carregandoMais = true;
    console.log('🔄 carregandoMais = true');
    
    const container = document.getElementById('participantes-container');
    console.log('📦 Container encontrado:', !!container);
    
    if (resetar) {
        console.log('🔄 Resetando dados...');
        offsetAtual = 0;
        temMaisParticipantes = true;
        participantesData = [];
        container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Carregando participantes...</p></div>';
    }
    
    // Construir query string com filtros
    const params = new URLSearchParams(filtrosAtivos);
    params.append('offset', offsetAtual);
    
    const url = `<?= SITE_URL ?>/admin/api/participantes.php?${params}`;
    console.log('🌐 URL da requisição:', url);
    
    fetch(url)
        .then(response => {
            console.log('📡 Response recebido, status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📊 Data recebido:', data);
            
            if (data.sucesso) {
                const novosParticipantes = data.participantes || [];
                console.log('👥 Novos participantes:', novosParticipantes.length);
                
                if (resetar) {
                    participantesData = novosParticipantes;
                } else {
                    participantesData = [...participantesData, ...novosParticipantes];
                }
                
                console.log('🔄 Dados atualizados:', participantesData);
                
                temMaisParticipantes = data.tem_mais || false;
                offsetAtual = data.proximo_offset || offsetAtual;
                
                console.log('🎨 Chamando renderizarParticipantes...');
                renderizarParticipantes(resetar ? participantesData : novosParticipantes, resetar);
            } else {
                throw new Error(data.erro || 'Erro desconhecido');
            }
        })
        .catch(error => {
            console.error('❌ Erro ao carregar participantes:', error);
            if (resetar) {
                container.innerHTML = `
                    <div class="no-results">
                        <h3>Erro ao carregar participantes</h3>
                        <p>Tente recarregar a página</p>
                    </div>
                `;
            }
        })
        .finally(() => {
            console.log('🏁 Finalizando carregamento...');
            carregandoMais = false;
            removerLoadingIndicator();
        });
}

// Renderizar grid de participantes (função original)
function renderizarParticipantes(participantes, resetar = true) {
    console.log('🎨 renderizarParticipantes chamada:', participantes.length, 'participantes, resetar:', resetar);
    
    const container = document.getElementById('participantes-container');
    
    if (participantes.length === 0 && resetar) {
        console.log('📭 Nenhum participante encontrado');
        container.innerHTML = `
            <div class="no-results">
                <h3>Nenhum participante encontrado</h3>
                <p>Tente ajustar os filtros ou adicionar um novo participante</p>
            </div>
        `;
        return;
    }
    
    let grid = container.querySelector('.participantes-grid');
    console.log('🏗️ Grid existente encontrada:', !!grid);
    
    if (resetar || !grid) {
        console.log('🏗️ Criando nova grid');
        grid = document.createElement('div');
        grid.className = 'participantes-grid';
        container.innerHTML = '';
        container.appendChild(grid);
    }
    
    // Se for reset, adicionar todos. Se não, adicionar apenas os novos participantes
    participantes.forEach((p, index) => {
        console.log(`👤 Renderizando participante ${index + 1}/${participantes.length}:`, p.nome);
        const card = criarCardParticipante(p);
        grid.appendChild(card);
        console.log(`✅ Card criado para:`, p.nome);
    });
    
    console.log('🎉 Renderização concluída:', participantes.length, 'participantes. Reset:', resetar);
    
    // Adicionar loading indicator se há mais para carregar
    if (temMaisParticipantes && !carregandoMais) {
        adicionarLoadingIndicator();
    }
}

// Criar card individual do participante (função original simplificada)
function criarCardParticipante(p) {
    console.log('🏗️ Criando card para:', p.nome);
    
    const card = document.createElement('div');
    card.className = 'participante-card';
    
    // Status do participante
    const statusParticipante = p.status || 'inscrito';
    const statusPagamento = p.pagamento_status || 'pendente';
    
    // Formatar data de criação
    let dataCriacao = '';
    if (p.criado_em) {
        const data = new Date(p.criado_em);
        dataCriacao = data.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    card.innerHTML = `
        <div class="participante-header">
            <div class="participante-info-principal">
                <h3 class="participante-nome">${escapeHtml(p.nome)}</h3>
                <div class="participante-evento-info">
                    <p class="participante-evento">${escapeHtml(p.evento_nome || 'Sem evento')}</p>
                    ${dataCriacao ? `<p class="participante-data-criacao">Criado: ${dataCriacao}</p>` : ''}
                </div>
                <p class="participante-cpf">${formatarCpf(p.cpf)}</p>
            </div>
        </div>
        
        <div class="participante-badges">
            <span class="status-badge status-${statusParticipante}">
                ${ucfirst(statusParticipante)}
            </span>
            <span class="status-badge status-pagamento-${statusPagamento}">
                ${p.valor > 0 ? `R$ ${formatarMoeda(p.valor)} - ${ucfirst(statusPagamento)}` : 'Gratuito'}
            </span>
        </div>
    `;
    
    console.log('✅ Card HTML criado para:', p.nome);
    return card;
}

console.log('📋 Todas as funções carregadas');
</script>

<?php echo obter_rodape_admin(); ?>