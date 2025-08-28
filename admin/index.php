<?php
require_once '../includes/init.php';

// Verificar login
requer_login();

// Buscar estatísticas para o dashboard
$total_eventos = contar_registros('eventos', ['status' => STATUS_ATIVO]);
// No novo modelo, contabilizamos inscrições ativas (pendente/aprovada)
$inscricoes_ativas_row = buscar_um("SELECT COUNT(*) AS total FROM inscricoes WHERE status IN ('pendente','aprovada')");
$total_participantes = $inscricoes_ativas_row['total'] ?? 0;
$total_pagamentos_pendentes = contar_registros('pagamentos', ['status' => PAGAMENTO_PENDENTE]);

// Receita total
$receita_query = buscar_um("
    SELECT SUM(valor) as total 
    FROM pagamentos 
    WHERE status = 'pago'
");
$receita_total = $receita_query['total'] ?? 0;

// Eventos próximos
$eventos_proximos = buscar_todos("
    SELECT e.*, COUNT(i.id) AS total_inscritos
    FROM eventos e
    LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente','aprovada')
    WHERE e.status = 'ativo' 
    AND e.data_inicio >= CURDATE()
    GROUP BY e.id
    ORDER BY e.data_inicio ASC
    LIMIT 5
");

// Inscrições recentes
$inscricoes_recentes = buscar_todos("
    SELECT 
        p.nome,
        e.nome AS evento_nome,
        i.status,
        i.data_inscricao AS criado_em
    FROM inscricoes i
    JOIN participantes p ON i.participante_id = p.id
    JOIN eventos e ON i.evento_id = e.id
    ORDER BY i.data_inscricao DESC
    LIMIT 10
");

// Atividades recentes do log
$atividades_recentes = buscar_todos("
    SELECT * FROM logs_atividades
    ORDER BY timestamp DESC
    LIMIT 10
");

obter_cabecalho_admin('Dashboard', 'dashboard');
?>

<div class="dashboard">
    <!-- Ações Rápidas -->
    <div class="quick-actions">
        <h2>Ações Rápidas</h2>
        <div class="actions-grid">
            <a href="<?= SITE_URL ?>/admin/eventos.php?acao=novo" class="action-card">
                <div class="action-icon">➕</div>
                <h3>Novo Evento</h3>
                <p>Criar um novo evento católico</p>
            </a>
            
            <a href="<?= SITE_URL ?>/admin/checkin.php" class="action-card">
                <div class="action-icon">📱</div>
                <h3>Check-in</h3>
                <p>Fazer check-in via QR Code</p>
            </a>
            
            <a href="<?= SITE_URL ?>/admin/gerar-links-qr.php" class="action-card">
                <div class="action-icon">🔗</div>
                <h3>Links QR</h3>
                <p>Gerar links públicos para QR Codes</p>
            </a>
            
            <a href="<?= SITE_URL ?>/admin/relatorios.php" class="action-card">
                <div class="action-icon">📊</div>
                <h3>Relatórios</h3>
                <p>Gerar relatórios e estatísticas</p>
            </a>
            
            <a href="<?= SITE_URL ?>/admin/configuracoes.php" class="action-card">
                <div class="action-icon">⚙️</div>
                <h3>Configurações</h3>
                <p>Configurar sistema e PIX</p>
            </a>
            
            <a href="<?= SITE_URL ?>/admin/limpar_cache.php" class="action-card cache-action">
                <div class="action-icon">🧹</div>
                <h3>Limpar Cache</h3>
                <p>Force atualizações estéticas</p>
            </a>
        </div>
    </div>
    
    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <h3>Eventos Ativos</h3>
                <div class="stat-number"><?= $total_eventos ?></div>
                <p class="stat-description">eventos em andamento</p>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3>Participantes</h3>
                <div class="stat-number"><?= $total_participantes ?></div>
                <p class="stat-description">inscritos confirmados</p>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <h3>Pagamentos Pendentes</h3>
                <div class="stat-number"><?= $total_pagamentos_pendentes ?></div>
                <p class="stat-description">aguardando confirmação</p>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <h3>Receita Total</h3>
                <div class="stat-number"><?= formatar_dinheiro($receita_total) ?></div>
                <p class="stat-description">pagamentos confirmados</p>
            </div>
        </div>
    </div>
    
    <!-- Grid Principal -->
    <div class="dashboard-grid">
        <!-- Eventos Próximos -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2>Próximos Eventos</h2>
                <a href="<?= SITE_URL ?>/admin/eventos.php" class="btn btn-outline">Ver Todos</a>
            </div>
            <div class="card-content">
                <?php if (empty($eventos_proximos)): ?>
                    <div class="empty-state">
                        <p>Nenhum evento programado</p>
                        <a href="<?= SITE_URL ?>/admin/eventos.php?acao=novo" class="btn btn-primary">Criar Evento</a>
                    </div>
                <?php else: ?>
                    <div class="eventos-lista">
                        <?php foreach ($eventos_proximos as $evento): ?>
                            <div class="evento-item">
                                <div class="evento-info">
                                    <h4><?= htmlspecialchars($evento['nome']) ?></h4>
                                    <p class="evento-data">
                                        📅 <?= formatar_data($evento['data_inicio']) ?>
                                        📍 <?= htmlspecialchars($evento['cidade']) ?>
                                    </p>
                                    <p class="evento-inscritos">
                                        👥 <?= $evento['total_inscritos'] ?>/<?= $evento['limite_participantes'] ?> inscritos
                                    </p>
                                </div>
                                <div class="evento-acoes">
                                    <a href="<?= SITE_URL ?>/admin/participantes.php?evento=<?= $evento['id'] ?>" 
                                       class="btn-mini">Ver Participantes</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Inscrições Recentes -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2>Inscrições Recentes</h2>
                <a href="<?= SITE_URL ?>/admin/participantes.php" class="btn btn-outline">Ver Todas</a>
            </div>
            <div class="card-content">
                <?php if (empty($inscricoes_recentes)): ?>
                    <div class="empty-state">
                        <p>Nenhuma inscrição recente</p>
                    </div>
                <?php else: ?>
                    <div class="inscricoes-lista">
                        <?php foreach ($inscricoes_recentes as $inscricao): ?>
                            <div class="inscricao-item">
                                <div class="inscricao-info">
                                    <h4><?= htmlspecialchars($inscricao['nome']) ?></h4>
                                    <p class="inscricao-evento"><?= htmlspecialchars($inscricao['evento_nome']) ?></p>
                                    <p class="inscricao-data">
                                        🕐 <?= formatar_data_hora($inscricao['criado_em']) ?>
                                    </p>
                                </div>
                                <div class="inscricao-status">
                                    <span class="status-badge status-<?= $inscricao['status'] ?>">
                                        <?= ucfirst($inscricao['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Atividades do Sistema -->
        <div class="dashboard-card full-width">
            <div class="card-header">
                <h2>Atividades Recentes</h2>
                <a href="<?= SITE_URL ?>/admin/logs.php" class="btn btn-outline">Ver Histórico</a>
            </div>
            <div class="card-content">
                <?php if (empty($atividades_recentes)): ?>
                    <div class="empty-state">
                        <p>Nenhuma atividade registrada</p>
                    </div>
                <?php else: ?>
                    <div class="atividades-lista">
                        <?php foreach ($atividades_recentes as $atividade): ?>
                            <div class="atividade-item">
                                <div class="atividade-info">
                                    <p class="atividade-acao"><?= htmlspecialchars($atividade['acao']) ?></p>
                                    <?php if ($atividade['detalhes']): ?>
                                        <p class="atividade-detalhes"><?= htmlspecialchars($atividade['detalhes']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="atividade-meta">
                                    <p class="atividade-usuario">👤 <?= htmlspecialchars($atividade['usuario']) ?></p>
                                    <p class="atividade-data">🕐 <?= formatar_data_hora($atividade['timestamp']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos específicos do dashboard */
.dashboard {
    padding: var(--espaco-lg);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--espaco-lg);
    margin-bottom: var(--espaco-2xl);
}

.stat-card {
    background: var(--cor-branco);
    border-radius: var(--borda-radius-grande);
    padding: var(--espaco-lg);
    box-shadow: var(--sombra-pequena);
    display: flex;
    align-items: center;
    gap: var(--espaco-md);
    border-left: 4px solid;
}

.stat-card.primary { border-left-color: var(--cor-primaria); }
.stat-card.success { border-left-color: var(--cor-sucesso); }
.stat-card.warning { border-left-color: var(--cor-aviso); }
.stat-card.info { border-left-color: var(--cor-info); }

.stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}

.stat-content h3 {
    font-size: 0.875rem;
    color: var(--cor-cinza-medio);
    margin-bottom: var(--espaco-xs);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--cor-cinza-escuro);
    margin-bottom: var(--espaco-xs);
}

.stat-description {
    font-size: 0.875rem;
    color: var(--cor-cinza-medio);
    margin: 0;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: var(--espaco-lg);
    margin-bottom: var(--espaco-2xl);
}

.dashboard-card {
    background: var(--cor-branco);
    border-radius: var(--borda-radius-grande);
    box-shadow: var(--sombra-pequena);
    overflow: hidden;
}

.dashboard-card.full-width {
    grid-column: 1 / -1;
}

.card-header {
    padding: var(--espaco-lg);
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--cor-cinza-escuro);
}

.card-content {
    padding: var(--espaco-lg);
}

.empty-state {
    text-align: center;
    padding: var(--espaco-xl);
    color: var(--cor-cinza-medio);
}

.eventos-lista,
.inscricoes-lista,
.atividades-lista {
    display: flex;
    flex-direction: column;
    gap: var(--espaco-md);
}

.evento-item,
.inscricao-item,
.atividade-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: var(--espaco-md);
    background: var(--cor-cinza-claro);
    border-radius: var(--borda-radius);
}

.evento-info h4,
.inscricao-info h4 {
    margin: 0 0 var(--espaco-xs) 0;
    color: var(--cor-cinza-escuro);
}

.evento-data,
.evento-inscritos,
.inscricao-evento,
.inscricao-data,
.atividade-detalhes {
    margin: 0;
    font-size: 0.875rem;
    color: var(--cor-cinza-medio);
}

.btn-mini {
    font-size: 0.75rem;
    padding: var(--espaco-xs) var(--espaco-sm);
    background: var(--cor-primaria);
    color: var(--cor-branco);
    text-decoration: none;
    border-radius: var(--borda-radius);
    white-space: nowrap;
}

.status-badge {
    font-size: 0.75rem;
    padding: var(--espaco-xs) var(--espaco-sm);
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-inscrito { background: #dbeafe; color: #1e40af; }
.status-pago { background: #d1fae5; color: #065f46; }
.status-presente { background: #fef3c7; color: #92400e; }
.status-cancelado { background: #fee2e2; color: #991b1b; }

.atividade-meta {
    text-align: right;
}

.atividade-usuario,
.atividade-data {
    margin: 0;
    font-size: 0.75rem;
    color: var(--cor-cinza-medio);
}

.quick-actions {
    margin-bottom: var(--espaco-2xl);
}

.quick-actions h2 {
    margin-bottom: var(--espaco-lg);
    color: var(--cor-cinza-escuro);
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--espaco-lg);
}

.action-card {
    background: var(--cor-branco);
    border-radius: var(--borda-radius-grande);
    padding: var(--espaco-lg);
    text-decoration: none;
    text-align: center;
    box-shadow: var(--sombra-pequena);
    transition: all 0.3s ease;
    border: 1px solid #f3f4f6;
}

.action-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--sombra-media);
    border-color: var(--cor-primaria);
}

.action-icon {
    font-size: 2rem;
    margin-bottom: var(--espaco-md);
}

.action-card h3 {
    margin: 0 0 var(--espaco-sm) 0;
    color: var(--cor-cinza-escuro);
}

.action-card p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--cor-cinza-medio);
}

@media (max-width: 768px) {
    .dashboard {
        padding: var(--espaco-md);
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .evento-item,
    .inscricao-item,
    .atividade-item {
        flex-direction: column;
        gap: var(--espaco-sm);
    }
}

/* Estilo específico para ação de cache */
.cache-action {
    border: 2px solid #dc2626 !important;
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%) !important;
}

.cache-action:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 8px 25px rgba(220, 38, 38, 0.25) !important;
    border-color: #b91c1c !important;
}

.cache-action .action-icon {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.cache-action h3 {
    color: #dc2626 !important;
}

.cache-action p {
    color: #7f1d1d !important;
}

/* Botão flutuante de cache */
.cache-float-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: white;
    font-size: 1.5rem;
    z-index: 1000;
    transition: all 0.3s ease;
    animation: pulse 2s infinite;
}

.cache-float-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.6);
    color: white;
    text-decoration: none;
}

@keyframes pulse {
    0% { box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4); }
    50% { box-shadow: 0 4px 20px rgba(220, 38, 38, 0.6); }
    100% { box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4); }
}

.cache-float-btn:active {
    transform: scale(0.95);
}
</style>

<?php
obter_rodape_admin();
?>