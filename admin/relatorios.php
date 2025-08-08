<?php
require_once '../includes/init.php';

// Verificar login
requer_login();

$evento_id = $_GET['evento'] ?? null;
$tipo_relatorio = $_GET['tipo'] ?? 'participantes';
$formato = $_GET['formato'] ?? null;

// Gerar relatório se solicitado
if ($formato && $evento_id) {
    gerar_relatorio($evento_id, $tipo_relatorio, $formato);
    exit;
}

// Buscar eventos para seleção
$eventos = buscar_todos("
    SELECT e.id, e.nome, e.data_inicio, e.local,
           COUNT(i.id) AS total_participantes,
           SUM(CASE WHEN p.status = 'presente' THEN 1 ELSE 0 END) AS total_presentes,
           SUM(CASE WHEN pg.status = 'pago' THEN pg.valor ELSE 0 END) AS total_arrecadado
    FROM eventos e
    LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status != 'cancelada'
    LEFT JOIN participantes p ON i.participante_id = p.id
    LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
    GROUP BY e.id
    ORDER BY e.data_inicio DESC
");

// Dados do evento selecionado
$evento_info = null;
$participantes = [];
$estatisticas = [];

if ($evento_id) {
    $evento_info = buscar_um("SELECT * FROM eventos WHERE id = ?", [$evento_id]);
    
    $participantes = buscar_todos("
        SELECT 
            p.id,
            p.nome, p.cpf, p.whatsapp, p.email, p.idade, p.cidade, p.estado,
            i.status AS status_inscricao,
            i.data_inscricao AS criado_em,
            pg.status AS pagamento_status, pg.valor, pg.pago_em,
            e.nome AS evento_nome
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        WHERE i.evento_id = ? AND i.status != 'cancelada'
        ORDER BY p.nome
    ", [$evento_id]);
    
    $estatisticas = obter_estatisticas_checkin($evento_id);
}

/**
 * Gerar relatório em PDF ou Excel
 */
function gerar_relatorio($evento_id, $tipo, $formato) {
    $evento = buscar_um("SELECT * FROM eventos WHERE id = ?", [$evento_id]);
    if (!$evento) {
        die('Evento não encontrado');
    }
    
    switch ($tipo) {
        case 'participantes':
            gerar_relatorio_participantes($evento, $formato);
            break;
        case 'presenca':
            gerar_relatorio_presenca($evento, $formato);
            break;
        case 'financeiro':
            gerar_relatorio_financeiro($evento, $formato);
            break;
        case 'checkin':
            gerar_relatorio_checkin($evento, $formato);
            break;
        default:
            die('Tipo de relatório inválido');
    }
}

/**
 * Relatório de participantes
 */
function gerar_relatorio_participantes($evento, $formato) {
    $participantes = buscar_todos("
        SELECT 
            p.nome, p.cpf, p.whatsapp, p.email, p.idade, p.cidade, p.estado,
            i.status AS status_inscricao,
            pg.status as pagamento_status, pg.valor, pg.pago_em,
            i.data_inscricao AS criado_em
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        WHERE i.evento_id = ? AND i.status != 'cancelada'
        ORDER BY p.nome
    ", [$evento['id']]);
    
    if ($formato === 'pdf') {
        gerar_pdf_participantes($evento, $participantes);
    } else {
        gerar_excel_participantes($evento, $participantes);
    }
}

/**
 * Gerar PDF de participantes
 */
function gerar_pdf_participantes($evento, $participantes) {
    // Simulação de geração de PDF (em produção usaria FPDF ou similar)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="participantes-' . $evento['slug'] . '.pdf"');
    
    // Aqui seria o código para gerar PDF real
    // Por enquanto, vamos criar um HTML que simula o PDF
    $html = gerarHTMLRelatorio($evento, $participantes, 'Relatório de Participantes');
    
    // Converter HTML para PDF usando uma biblioteca
    echo $html;
}

/**
 * Gerar Excel de participantes
 */
function gerar_excel_participantes($evento, $participantes) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="participantes-' . $evento['slug'] . '.xls"');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"><title>Relatório de Participantes</title></head>';
    echo '<body>';
    
    echo '<h1>Relatório de Participantes</h1>';
    echo '<h2>' . htmlspecialchars($evento['nome']) . '</h2>';
    echo '<p>Data: ' . formatar_data($evento['data_inicio']) . '</p>';
    echo '<p>Local: ' . htmlspecialchars($evento['local']) . '</p>';
    echo '<p>Gerado em: ' . date('d/m/Y H:i') . '</p>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Nome</th>';
    echo '<th>CPF</th>';
    echo '<th>WhatsApp</th>';
    echo '<th>Email</th>';
    echo '<th>Idade</th>';
    echo '<th>Cidade</th>';
    echo '<th>Status</th>';
    echo '<th>Pagamento</th>';
    echo '<th>Valor</th>';
    echo '<th>Data Inscrição</th>';
    echo '</tr>';
    
    foreach ($participantes as $p) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($p['nome']) . '</td>';
        echo '<td>' . formatarCpf($p['cpf']) . '</td>';
        echo '<td>' . formatarTelefone($p['whatsapp']) . '</td>';
        echo '<td>' . htmlspecialchars($p['email']) . '</td>';
        echo '<td>' . $p['idade'] . '</td>';
        echo '<td>' . htmlspecialchars($p['cidade']) . '</td>';
        echo '<td>' . ucfirst($p['status']) . '</td>';
        echo '<td>' . ucfirst($p['pagamento_status'] ?? 'N/A') . '</td>';
        echo '<td>R$ ' . formatar_moeda($p['valor'] ?? 0) . '</td>';
        echo '<td>' . formatar_data_hora($p['criado_em']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
}

/**
 * Gerar HTML para relatório
 */
function gerarHTMLRelatorio($evento, $dados, $titulo) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= $titulo ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #1e40af; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .header { border-bottom: 2px solid #1e40af; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?= $titulo ?></h1>
            <h2><?= htmlspecialchars($evento['nome']) ?></h2>
            <p><strong>Data:</strong> <?= formatar_data($evento['data_inicio']) ?></p>
            <p><strong>Local:</strong> <?= htmlspecialchars($evento['local']) ?></p>
            <p><strong>Gerado em:</strong> <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>WhatsApp</th>
                    <th>Email</th>
                    <th>Idade</th>
                    <th>Cidade/Estado</th>
                    <th>Status</th>
                    <th>Data Inscrição</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nome']) ?></td>
                    <td><?= formatarCpf($p['cpf']) ?></td>
                    <td><?= formatarTelefone($p['whatsapp']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= $p['idade'] ?></td>
                    <td><?= htmlspecialchars($p['cidade']) ?>/<?= $p['estado'] ?></td>
                    <td><?= ucfirst($p['status']) ?></td>
                    <td><?= formatar_data_hora($p['criado_em']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; font-size: 12px; color: #666;">
            <p>Relatório gerado pelo Sistema Vinde - Eventos Católicos</p>
            <p>Total de registros: <?= count($dados) ?></p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

obter_cabecalho_admin('Relatórios', 'relatorios');
?>

<div class="relatorios-container">
    <!-- Seleção de Evento -->
    <div class="evento-selector">
        <h3>📊 Gerar Relatórios</h3>
        <form method="GET" class="evento-form">
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Selecionar Evento</label>
                    <select name="evento" class="form-select-admin" onchange="this.form.submit()">
                        <option value="">Escolha um evento...</option>
                        <?php foreach ($eventos as $ev): ?>
                            <option value="<?= $ev['id'] ?>" <?= $evento_id == $ev['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['nome']) ?> - <?= formatar_data($ev['data_inicio']) ?>
                                (<?= $ev['total_participantes'] ?> participantes)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($evento_id && $evento_info): ?>
        
        <!-- Resumo do Evento -->
        <div class="evento-resumo">
            <h3><?= htmlspecialchars($evento_info['nome']) ?></h3>
            <div class="resumo-stats">
                <div class="stat-item">
                    <strong><?= $estatisticas['total_inscritos'] ?></strong>
                    <span>Inscritos</span>
                </div>
                <div class="stat-item">
                    <strong><?= $estatisticas['total_presentes'] ?></strong>
                    <span>Presentes</span>
                </div>
                <div class="stat-item">
                    <strong><?= $estatisticas['percentual_presenca'] ?>%</strong>
                    <span>Taxa Presença</span>
                </div>
                <div class="stat-item">
                    <strong>R$ <?= formatar_moeda(array_sum(array_column($participantes, 'valor')) ?? 0) ?></strong>
                    <span>Total Arrecadado</span>
                </div>
            </div>
        </div>

        <!-- Tipos de Relatórios -->
        <div class="relatorios-tipos">
            <h3>📋 Tipos de Relatórios Disponíveis</h3>
            
            <div class="relatorio-grid">
                <!-- Relatório de Participantes -->
                <div class="relatorio-card">
                    <h4>👥 Lista de Participantes</h4>
                    <p>Lista completa com dados pessoais, contato e status de cada participante.</p>
                    <div class="relatorio-actions">
                        <a href="?evento=<?= $evento_id ?>&tipo=participantes&formato=pdf" 
                           class="btn btn-primary" target="_blank">
                            📄 PDF
                        </a>
                        <a href="?evento=<?= $evento_id ?>&tipo=participantes&formato=excel" 
                           class="btn btn-success" target="_blank">
                            📊 Excel
                        </a>
                    </div>
                </div>
                
                <!-- Relatório de Presença -->
                <div class="relatorio-card">
                    <h4>✅ Controle de Presença</h4>
                    <p>Lista de check-ins realizados com horários e responsáveis.</p>
                    <div class="relatorio-actions">
                        <a href="?evento=<?= $evento_id ?>&tipo=presenca&formato=pdf" 
                           class="btn btn-primary" target="_blank">
                            📄 PDF
                        </a>
                        <a href="?evento=<?= $evento_id ?>&tipo=presenca&formato=excel" 
                           class="btn btn-success" target="_blank">
                            📊 Excel
                        </a>
                    </div>
                </div>
                
                <!-- Relatório Financeiro -->
                <div class="relatorio-card">
                    <h4>💰 Relatório Financeiro</h4>
                    <p>Resumo de pagamentos, valores arrecadados e status financeiro.</p>
                    <div class="relatorio-actions">
                        <a href="?evento=<?= $evento_id ?>&tipo=financeiro&formato=pdf" 
                           class="btn btn-primary" target="_blank">
                            📄 PDF
                        </a>
                        <a href="?evento=<?= $evento_id ?>&tipo=financeiro&formato=excel" 
                           class="btn btn-success" target="_blank">
                            📊 Excel
                        </a>
                    </div>
                </div>
                
                <!-- Relatório de Check-in -->
                <div class="relatorio-card">
                    <h4>🎯 Estatísticas de Check-in</h4>
                    <p>Análise detalhada de presença, horários e padrões de comparecimento.</p>
                    <div class="relatorio-actions">
                        <a href="?evento=<?= $evento_id ?>&tipo=checkin&formato=pdf" 
                           class="btn btn-primary" target="_blank">
                            📄 PDF
                        </a>
                        <a href="?evento=<?= $evento_id ?>&tipo=checkin&formato=excel" 
                           class="btn btn-success" target="_blank">
                            📊 Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visualização Rápida dos Dados -->
        <div class="dados-preview">
            <h3>👁️ Visualização dos Dados</h3>
            
            <div class="preview-tabs">
                <button class="tab-btn active" onclick="showTab('participantes-tab')">Participantes</button>
                <button class="tab-btn" onclick="showTab('estatisticas-tab')">Estatísticas</button>
                <button class="tab-btn" onclick="showTab('checkins-tab')">Check-ins</button>
            </div>
            
            <!-- Tab Participantes -->
            <div id="participantes-tab" class="tab-content active">
                <div class="table-responsive">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Contato</th>
                                <th>Status</th>
                                <th>Pagamento</th>
                                <th>Data Inscrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($participantes, 0, 10) as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                        <br><small><?= htmlspecialchars($p['cidade']) ?>, <?= $p['estado'] ?></small>
                                    </td>
                                    <td>
                                        📱 <?= formatarTelefone($p['whatsapp']) ?><br>
                                        📧 <?= htmlspecialchars($p['email']) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge-admin status-<?= $p['status'] ?>">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($p['valor'] > 0): ?>
                                            <span class="status-badge-admin status-<?= $p['pagamento_status'] ?>">
                                                <?= ucfirst($p['pagamento_status']) ?>
                                            </span>
                                            <br>R$ <?= formatar_moeda($p['valor']) ?>
                                        <?php else: ?>
                                            <span class="status-badge-admin status-gratuito">Gratuito</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatar_data($p['criado_em']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (count($participantes) > 10): ?>
                        <p class="preview-note">
                            Mostrando 10 de <?= count($participantes) ?> participantes. 
                            Gere o relatório completo para ver todos os dados.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab Estatísticas -->
            <div id="estatisticas-tab" class="tab-content">
                <div class="stats-visual">
                    <div class="chart-container">
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="stats-list">
                        <div class="stat-detail">
                            <h4>Status dos Participantes</h4>
                            <ul>
                                <li>Inscritos: <?= $estatisticas['total_inscritos'] - $estatisticas['total_pagos'] - $estatisticas['total_presentes'] ?></li>
                                <li>Pagos: <?= $estatisticas['total_pagos'] - $estatisticas['total_presentes'] ?></li>
                                <li>Presentes: <?= $estatisticas['total_presentes'] ?></li>
                            </ul>
                        </div>
                        
                        <div class="stat-detail">
                            <h4>Análise Financeira</h4>
                            <?php
                            $total_previsto = count($participantes) * ($evento_info['valor'] ?? 0);
                            $total_arrecadado = array_sum(array_map(function($p) {
                                return $p['pagamento_status'] === 'pago' ? $p['valor'] : 0;
                            }, $participantes));
                            ?>
                            <ul>
                                <li>Valor Previsto: R$ <?= formatar_moeda($total_previsto) ?></li>
                                <li>Valor Arrecadado: R$ <?= formatar_moeda($total_arrecadado) ?></li>
                                <li>Pendente: R$ <?= formatar_moeda($total_previsto - $total_arrecadado) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Check-ins -->
            <div id="checkins-tab" class="tab-content">
                <?php
                $checkins_hoje = buscar_todos("
                    SELECT p.nome, p.checkin_timestamp, p.checkin_operador
                    FROM inscricoes i
                    JOIN participantes p ON i.participante_id = p.id
                    WHERE i.evento_id = ? AND i.status != 'cancelada' AND p.status = 'presente'
                    AND DATE(p.checkin_timestamp) = CURDATE()
                    ORDER BY p.checkin_timestamp DESC
                    LIMIT 20
                ", [$evento_id]);
                ?>
                
                <h4>Check-ins de Hoje (<?= count($checkins_hoje) ?>)</h4>
                
                <?php if (empty($checkins_hoje)): ?>
                    <p>Nenhum check-in realizado hoje.</p>
                <?php else: ?>
                    <div class="checkins-list">
                        <?php foreach ($checkins_hoje as $checkin): ?>
                            <div class="checkin-item-preview">
                                <strong><?= htmlspecialchars($checkin['nome']) ?></strong>
                                <span class="checkin-time"><?= date('H:i', strtotime($checkin['checkin_timestamp'])) ?></span>
                                <small>por <?= htmlspecialchars($checkin['checkin_operador']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="checkin-stats">
                    <h4>Estatísticas de Presença</h4>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $estatisticas['percentual_presenca'] ?>%"></div>
                        <span class="progress-text"><?= $estatisticas['percentual_presenca'] ?>% de presença</span>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        
        <div class="no-evento">
            <h3>Selecione um evento para gerar relatórios</h3>
            <p>Escolha um evento na lista acima para visualizar as opções de relatórios disponíveis.</p>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gerenciar tabs
function showTab(tabId) {
    // Esconder todas as tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remover active de todos os botões
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar tab selecionada
    document.getElementById(tabId).classList.add('active');
    
    // Ativar botão correspondente
    event.target.classList.add('active');
}

// Gerar gráfico de status
<?php if ($evento_id && !empty($estatisticas)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Presentes', 'Pagos', 'Inscritos'],
            datasets: [{
                data: [
                    <?= $estatisticas['total_presentes'] ?>,
                    <?= $estatisticas['total_pagos'] - $estatisticas['total_presentes'] ?>,
                    <?= $estatisticas['total_inscritos'] - $estatisticas['total_pagos'] ?>
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php
obter_rodape_admin();
?> 