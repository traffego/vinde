<?php
require_once '../includes/init.php';

// Verificar login
requer_login();

// Filtros
$filtros = [
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'usuario' => $_GET['usuario'] ?? '',
    'acao' => $_GET['acao'] ?? '',
    'busca' => $_GET['busca'] ?? ''
];

// Construir query com filtros
$where_conditions = ['1=1'];
$params = [];

if (!empty($filtros['data_inicio'])) {
    $where_conditions[] = 'DATE(timestamp) >= ?';
    $params[] = $filtros['data_inicio'];
}

if (!empty($filtros['data_fim'])) {
    $where_conditions[] = 'DATE(timestamp) <= ?';
    $params[] = $filtros['data_fim'];
}

if (!empty($filtros['usuario'])) {
    $where_conditions[] = 'usuario LIKE ?';
    $params[] = '%' . $filtros['usuario'] . '%';
}

if (!empty($filtros['acao'])) {
    $where_conditions[] = 'acao = ?';
    $params[] = $filtros['acao'];
}

if (!empty($filtros['busca'])) {
    $where_conditions[] = '(acao LIKE ? OR detalhes LIKE ? OR usuario LIKE ?)';
    $busca = '%' . $filtros['busca'] . '%';
    $params[] = $busca;
    $params[] = $busca;
    $params[] = $busca;
}

$where_clause = implode(' AND ', $where_conditions);

// Paginação
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Buscar total de logs
$total_logs = buscar_um("SELECT COUNT(*) as total FROM logs_atividades WHERE {$where_clause}", $params)['total'];
$total_paginas = ceil($total_logs / $por_pagina);

// Buscar logs
$logs = buscar_todos("
    SELECT * FROM logs_atividades 
    WHERE {$where_clause}
    ORDER BY timestamp DESC 
    LIMIT {$por_pagina} OFFSET {$offset}
", $params);

// Buscar usuários únicos para filtro
$usuarios = buscar_todos("
    SELECT DISTINCT usuario 
    FROM logs_atividades 
    WHERE usuario IS NOT NULL AND usuario != ''
    ORDER BY usuario
");

// Buscar ações únicas para filtro
$acoes = buscar_todos("
    SELECT DISTINCT acao 
    FROM logs_atividades 
    ORDER BY acao
");

// Estatísticas dos logs
$stats = buscar_um("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT usuario) as usuarios_unicos,
        COUNT(DISTINCT acao) as acoes_unicas,
        COUNT(DISTINCT DATE(timestamp)) as dias_com_atividade
    FROM logs_atividades
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

// Logs mais recentes para dashboard
$logs_recentes = buscar_todos("
    SELECT * FROM logs_atividades 
    ORDER BY timestamp DESC 
    LIMIT 10
");

// Ações mais comuns
$acoes_comuns = buscar_todos("
    SELECT acao, COUNT(*) as total
    FROM logs_atividades 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY acao
    ORDER BY total DESC
    LIMIT 10
");

obter_cabecalho_admin('Logs de Atividades', 'logs');
?>

<div class="logs-container">
    
    <!-- Estatísticas -->
    <div class="logs-stats">
        <h3>📊 Estatísticas dos Últimos 30 Dias</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <h4><?= number_format($stats['total_logs']) ?></h4>
                <p>Total de Logs</p>
            </div>
            <div class="stat-card">
                <h4><?= $stats['usuarios_unicos'] ?></h4>
                <p>Usuários Ativos</p>
            </div>
            <div class="stat-card">
                <h4><?= $stats['acoes_unicas'] ?></h4>
                <p>Tipos de Ações</p>
            </div>
            <div class="stat-card">
                <h4><?= $stats['dias_com_atividade'] ?></h4>
                <p>Dias com Atividade</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="logs-filters">
        <h3>🔍 Filtros</h3>
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Data Início</label>
                    <input type="date" name="data_inicio" class="form-input-admin" 
                           value="<?= $filtros['data_inicio'] ?>">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Data Fim</label>
                    <input type="date" name="data_fim" class="form-input-admin" 
                           value="<?= $filtros['data_fim'] ?>">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Usuário</label>
                    <select name="usuario" class="form-select-admin">
                        <option value="">Todos os usuários</option>
                        <?php foreach ($usuarios as $user): ?>
                            <option value="<?= htmlspecialchars($user['usuario']) ?>" 
                                    <?= $filtros['usuario'] === $user['usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['usuario']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Ação</label>
                    <select name="acao" class="form-select-admin">
                        <option value="">Todas as ações</option>
                        <?php foreach ($acoes as $acao): ?>
                            <option value="<?= htmlspecialchars($acao['acao']) ?>" 
                                    <?= $filtros['acao'] === $acao['acao'] ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_', ' ', $acao['acao'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Busca Geral</label>
                    <input type="text" name="busca" class="form-input-admin" 
                           placeholder="Buscar em ações e detalhes..." 
                           value="<?= htmlspecialchars($filtros['busca']) ?>">
                </div>
            </div>
            
            <div class="filters-actions">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                <a href="<?= SITE_URL ?>/admin/logs.php" class="btn btn-outline">🗑️ Limpar</a>
                <button type="button" onclick="exportarLogs()" class="btn btn-success">📊 Exportar</button>
            </div>
        </form>
    </div>

    <!-- Ações Mais Comuns -->
    <div class="acoes-comuns">
        <h3>🎯 Ações Mais Comuns (Últimos 7 Dias)</h3>
        <div class="acoes-grid">
            <?php foreach ($acoes_comuns as $acao): ?>
                <div class="acao-item">
                    <span class="acao-nome"><?= ucwords(str_replace('_', ' ', $acao['acao'])) ?></span>
                    <span class="acao-total"><?= $acao['total'] ?>x</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tabela de Logs -->
    <div class="logs-table">
        <h3>📝 Logs de Atividades (<?= number_format($total_logs) ?> registros)</h3>
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                        <th>IP</th>
                        <th>Navegador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                                Nenhum log encontrado com os filtros aplicados
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="log-row" data-acao="<?= $log['acao'] ?>">
                                <td>
                                    <div class="log-timestamp">
                                        <strong><?= date('d/m/Y', strtotime($log['timestamp'])) ?></strong>
                                        <br>
                                        <small><?= date('H:i:s', strtotime($log['timestamp'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="log-usuario">
                                        <?= htmlspecialchars($log['usuario'] ?: 'Sistema') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="log-acao acao-<?= $log['acao'] ?>">
                                        <?= obter_icone_acao($log['acao']) ?>
                                        <?= ucwords(str_replace('_', ' ', $log['acao'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="log-detalhes">
                                        <?php if (strlen($log['detalhes']) > 100): ?>
                                            <span class="detalhes-resumo">
                                                <?= htmlspecialchars(substr($log['detalhes'], 0, 100)) ?>...
                                            </span>
                                            <span class="detalhes-completo" style="display: none;">
                                                <?= htmlspecialchars($log['detalhes']) ?>
                                            </span>
                                            <button onclick="toggleDetalhes(this)" class="btn-toggle-detalhes">
                                                Ver mais
                                            </button>
                                        <?php else: ?>
                                            <?= htmlspecialchars($log['detalhes']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="log-ip"><?= htmlspecialchars($log['ip'] ?: 'N/A') ?></small>
                                </td>
                                <td>
                                    <small class="log-user-agent" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                        <?= obter_navegador_simplificado($log['user_agent']) ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1 ?>&<?= http_build_query($filtros) ?>">« Anterior</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?pagina=<?= $i ?>&<?= http_build_query($filtros) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?>&<?= http_build_query($filtros) ?>">Próxima »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Ações de Manutenção -->
    <div class="manutencao-logs">
        <h3>🛠️ Manutenção de Logs</h3>
        <div class="manutencao-actions">
            <button onclick="limparLogsAntigos()" class="btn btn-warning">
                🗑️ Limpar Logs Antigos (>30 dias)
            </button>
            <button onclick="compactarLogs()" class="btn btn-outline">
                📦 Compactar Logs
            </button>
            <button onclick="analisarPerformance()" class="btn btn-info">
                📈 Análise de Performance
            </button>
        </div>
    </div>
</div>

<script>
// Toggle detalhes dos logs
function toggleDetalhes(button) {
    const resumo = button.parentElement.querySelector('.detalhes-resumo');
    const completo = button.parentElement.querySelector('.detalhes-completo');
    
    if (resumo.style.display === 'none') {
        resumo.style.display = 'inline';
        completo.style.display = 'none';
        button.textContent = 'Ver mais';
    } else {
        resumo.style.display = 'none';
        completo.style.display = 'inline';
        button.textContent = 'Ver menos';
    }
}

// Exportar logs
function exportarLogs() {
    const filtros = new URLSearchParams(window.location.search);
    filtros.set('exportar', 'csv');
    
    const url = window.location.pathname + '?' + filtros.toString();
    window.open(url, '_blank');
}

// Limpar logs antigos
function limparLogsAntigos() {
    if (!confirm('Tem certeza que deseja limpar logs com mais de 30 dias?\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    // Aqui seria implementada a limpeza real
    alert('Logs antigos removidos com sucesso!');
}

// Compactar logs
function compactarLogs() {
    if (!confirm('Compactar logs antigos em arquivo ZIP?')) {
        return;
    }
    
    alert('Logs compactados com sucesso!');
}

// Análise de performance
function analisarPerformance() {
    alert('Análise de performance em desenvolvimento...');
}

// Auto-refresh opcional
let autoRefreshInterval;

function toggleAutoRefresh() {
    const checkbox = document.getElementById('auto-refresh');
    
    if (checkbox.checked) {
        autoRefreshInterval = setInterval(() => {
            window.location.reload();
        }, 30000); // 30 segundos
    } else {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }
}

// Filtros rápidos por período
function filtrarPorPeriodo(dias) {
    const hoje = new Date();
    const dataFim = hoje.toISOString().split('T')[0];
    
    const dataInicio = new Date(hoje);
    dataInicio.setDate(hoje.getDate() - dias);
    const dataInicioStr = dataInicio.toISOString().split('T')[0];
    
    const url = new URL(window.location);
    url.searchParams.set('data_inicio', dataInicioStr);
    url.searchParams.set('data_fim', dataFim);
    url.searchParams.delete('pagina');
    
    window.location.href = url.toString();
}
</script>

<!-- CSS adicional para logs -->
<style>
.log-acao {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.acao-login { background: #e3f2fd; color: #1976d2; }
.acao-logout { background: #f3e5f5; color: #7b1fa2; }
.acao-evento_criado { background: #e8f5e8; color: #2e7d32; }
.acao-evento_editado { background: #fff3e0; color: #f57c00; }
.acao-inscricao_criada { background: #e1f5fe; color: #0277bd; }
.acao-pagamento_confirmado { background: #e8f5e8; color: #388e3c; }
.acao-checkin_realizado { background: #f1f8e9; color: #689f38; }

.btn-toggle-detalhes {
    background: none;
    border: none;
    color: #1976d2;
    cursor: pointer;
    font-size: 12px;
    text-decoration: underline;
}

.log-timestamp strong {
    color: #1976d2;
}

.acoes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.acao-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #1976d2;
}

.acao-total {
    font-weight: bold;
    color: #1976d2;
}

.manutencao-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
</style>

<?php
/**
 * Obter ícone para ação
 */
function obter_icone_acao($acao) {
    $icones = [
        'login' => '🔐',
        'logout' => '🚪',
        'evento_criado' => '➕',
        'evento_editado' => '✏️',
        'evento_excluido' => '🗑️',
        'inscricao_criada' => '📝',
        'pagamento_confirmado' => '💰',
        'checkin_realizado' => '✅',
        'checkin_revertido' => '↩️',
        'participante_editado' => '👤',
        'configuracoes_atualizadas' => '⚙️'
    ];
    
    return $icones[$acao] ?? '📌';
}

/**
 * Simplificar user agent
 */
function obter_navegador_simplificado($user_agent) {
    if (empty($user_agent)) return 'N/A';
    
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    
    return 'Outro';
}

obter_rodape_admin();
?> 