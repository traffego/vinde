<?php
/**
 * Gerador de Links Públicos para QR Codes
 * Permite aos administradores gerar links diretos para os participantes
 */

require_once '../includes/init.php';

// Verificar autenticação de admin
requer_login('admin');

$evento_selecionado = null;
$participantes = [];
$links_gerados = [];

// Buscar eventos
$eventos = buscar_todos("SELECT id, nome, data_inicio FROM eventos ORDER BY data_inicio DESC");

// Processar formulário
if ($_POST) {
    $evento_id = (int)($_POST['evento_id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    
    if ($evento_id > 0) {
        $evento_selecionado = buscar_um("SELECT * FROM eventos WHERE id = ?", [$evento_id]);
        
        // Verificar se existe tabela inscricoes
        $tabela_inscricoes_existe = false;
        try {
            $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
            $tabela_inscricoes_existe = $teste_tabela !== false;
        } catch (Exception $e) {
            $tabela_inscricoes_existe = false;
        }
        
        if ($tabela_inscricoes_existe) {
            // Sistema novo
            $participantes = buscar_todos("
                SELECT 
                    p.id,
                    p.nome,
                    p.cpf,
                    p.whatsapp,
                    p.email,
                    i.status as status_inscricao,
                    pg.status as pagamento_status
                FROM participantes p
                JOIN inscricoes i ON i.participante_id = p.id
                LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
                WHERE i.evento_id = ? AND i.status IN ('pendente', 'aprovada')
                ORDER BY p.nome
            ", [$evento_id]);
        } else {
            // Sistema antigo
            $participantes = buscar_todos("
                SELECT 
                    p.id,
                    p.nome,
                    p.cpf,
                    p.whatsapp,
                    p.email,
                    p.status,
                    pg.status as pagamento_status
                FROM participantes p
                LEFT JOIN pagamentos pg ON pg.participante_id = p.id
                WHERE p.evento_id = ? AND p.status != 'cancelado'
                ORDER BY p.nome
            ", [$evento_id]);
        }
        
        // Gerar links se solicitado
        if ($acao === 'gerar_links') {
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                       '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
            
            foreach ($participantes as $participante) {
                $cpf = preg_replace('/[^0-9]/', '', $participante['cpf']);
                $whatsapp = preg_replace('/[^0-9]/', '', $participante['whatsapp']);
                
                $link = $base_url . '/qr-publico.php?' . http_build_query([
                    'cpf' => $cpf,
                    'whatsapp' => $whatsapp,
                    'evento' => $evento_id
                ]);
                
                $whatsapp_text = urlencode(
                    "🎫 Olá {$participante['nome']}!\n\n" .
                    "Seu QR Code para o evento *{$evento_selecionado['nome']}* está pronto!\n\n" .
                    "📱 Acesse seu QR Code: {$link}\n\n" .
                    "💡 *Dica:* Salve este link nos favoritos do seu celular para acesso rápido!\n\n" .
                    "📅 Data: " . date('d/m/Y', strtotime($evento_selecionado['data_inicio']))
                );
                
                // Adicionar código do país (+55) se não estiver presente
                $whatsapp_formatado = $whatsapp;
                if (!str_starts_with($whatsapp, '55')) {
                    $whatsapp_formatado = '55' . $whatsapp;
                }
                
                $whatsapp_url = "https://wa.me/{$whatsapp_formatado}?text={$whatsapp_text}";
                
                $links_gerados[] = [
                    'participante' => $participante,
                    'link_qr' => $link,
                    'whatsapp_url' => $whatsapp_url
                ];
            }
        }
    }
}

obter_cabecalho_admin('Gerar Links QR Públicos', 'admin');
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>🔗 Gerador de Links QR Públicos</h1>
        <p>Gere links diretos para que os participantes acessem seus QR Codes sem login</p>
    </div>
    
    <!-- Seleção de Evento -->
    <div class="card">
        <h3>1. Selecionar Evento</h3>
        <form method="POST">
            <div class="form-group">
                <label for="evento_id">Evento:</label>
                <select name="evento_id" id="evento_id" class="form-control" required>
                    <option value="">Selecione um evento...</option>
                    <?php foreach ($eventos as $evento): ?>
                        <option value="<?= $evento['id'] ?>" 
                                <?= ($evento_selecionado && $evento_selecionado['id'] == $evento['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($evento['nome']) ?> 
                            (<?= date('d/m/Y', strtotime($evento['data_inicio'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="acao" value="listar" class="btn btn-primary">
                📋 Listar Participantes
            </button>
        </form>
    </div>
    
    <?php if ($evento_selecionado && !empty($participantes)): ?>
        <!-- Lista de Participantes -->
        <div class="card">
            <div class="card-header">
                <h3>2. Participantes do Evento</h3>
                <div class="card-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="evento_id" value="<?= $evento_selecionado['id'] ?>">
                        <button type="submit" name="acao" value="gerar_links" class="btn btn-success">
                            🚀 Gerar Todos os Links
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= count($participantes) ?></div>
                    <div class="stat-label">Total de Participantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($participantes, function($p) { return ($p['status_inscricao'] ?? $p['status']) === 'aprovada'; })) ?></div>
                    <div class="stat-label">Inscrições Aprovadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($participantes, function($p) { return ($p['pagamento_status'] ?? '') === 'pago'; })) ?></div>
                    <div class="stat-label">Pagamentos Confirmados</div>
                </div>
            </div>
            
            <?php if (empty($links_gerados)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>WhatsApp</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participantes as $participante): ?>
                                <tr>
                                    <td><?= htmlspecialchars($participante['nome']) ?></td>
                                    <td><?= htmlspecialchars($participante['cpf']) ?></td>
                                    <td><?= htmlspecialchars($participante['whatsapp']) ?></td>
                                    <td>
                                        <?php 
                                        $status = $participante['status_inscricao'] ?? $participante['status'];
                                        $status_class = $status === 'aprovada' ? 'success' : 'warning';
                                        $status_text = $status === 'aprovada' ? 'Aprovada' : ucfirst($status);
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $cpf_limpo = preg_replace('/[^0-9]/', '', $participante['cpf']);
                                        $whatsapp_limpo = preg_replace('/[^0-9]/', '', $participante['whatsapp']);
                                        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                                                   '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
                                        $link_individual = $base_url . '/qr-publico.php?' . http_build_query([
                                            'cpf' => $cpf_limpo,
                                            'whatsapp' => $whatsapp_limpo,
                                            'evento' => $evento_selecionado['id']
                                        ]);
                                        ?>
                                        <a href="<?= $link_individual ?>" target="_blank" class="btn btn-sm btn-outline">
                                            👁️ Ver QR
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($links_gerados)): ?>
        <!-- Links Gerados -->
        <div class="card">
            <div class="card-header">
                <h3>3. Links Gerados</h3>
                <div class="card-actions">
                    <button onclick="copiarTodosLinks()" class="btn btn-outline">
                        📋 Copiar Todos
                    </button>
                    <button onclick="baixarCSV()" class="btn btn-outline">
                        📊 Baixar CSV
                    </button>
                </div>
            </div>
            
            <div class="alert alert-success">
                ✅ <strong><?= count($links_gerados) ?> links gerados com sucesso!</strong><br>
                Agora você pode enviar individualmente ou em massa via WhatsApp.
            </div>
            
            <div class="links-grid">
                <?php foreach ($links_gerados as $item): ?>
                    <div class="link-card">
                        <div class="link-header">
                            <strong><?= htmlspecialchars($item['participante']['nome']) ?></strong>
                            <span class="link-cpf"><?= htmlspecialchars($item['participante']['cpf']) ?></span>
                        </div>
                        
                        <div class="link-url">
                            <input type="text" value="<?= htmlspecialchars($item['link_qr']) ?>" 
                                   class="form-control" readonly onclick="this.select()">
                        </div>
                        
                        <div class="link-actions">
                            <button onclick="copiarLink('<?= htmlspecialchars($item['link_qr']) ?>')" 
                                    class="btn btn-sm btn-outline">
                                📋 Copiar
                            </button>
                            <a href="<?= $item['whatsapp_url'] ?>" target="_blank" 
                               class="btn btn-sm btn-success">
                                📱 WhatsApp
                            </a>
                            <a href="<?= $item['link_qr'] ?>" target="_blank" 
                               class="btn btn-sm btn-primary">
                                👁️ Testar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Instruções -->
    <div class="card">
        <h3>💡 Como Usar</h3>
        <div class="instructions">
            <div class="instruction-item">
                <span class="instruction-number">1</span>
                <div>
                    <strong>Selecione o evento</strong><br>
                    Escolha o evento para o qual deseja gerar os links de QR Code.
                </div>
            </div>
            
            <div class="instruction-item">
                <span class="instruction-number">2</span>
                <div>
                    <strong>Gere os links</strong><br>
                    Clique em "Gerar Todos os Links" para criar URLs personalizadas para cada participante.
                </div>
            </div>
            
            <div class="instruction-item">
                <span class="instruction-number">3</span>
                <div>
                    <strong>Envie aos participantes</strong><br>
                    Use os botões de WhatsApp para enviar mensagens personalizadas ou copie os links individualmente.
                </div>
            </div>
            
            <div class="instruction-item">
                <span class="instruction-number">4</span>
                <div>
                    <strong>Participantes acessam sem login</strong><br>
                    Os participantes podem acessar seus QR Codes diretamente, sem precisar fazer login no sistema.
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #e9ecef;
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #495057;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9em;
    margin-top: 5px;
}

.links-grid {
    display: grid;
    gap: 15px;
    margin-top: 20px;
}

.link-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
}

.link-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.link-cpf {
    color: #6c757d;
    font-size: 0.9em;
}

.link-url {
    margin-bottom: 10px;
}

.link-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.instructions {
    display: grid;
    gap: 20px;
}

.instruction-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.instruction-number {
    background: #007bff;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

@media (max-width: 768px) {
    .link-actions {
        flex-direction: column;
    }
    
    .link-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<script>
function copiarLink(link) {
    navigator.clipboard.writeText(link).then(() => {
        showToast('Link copiado!', 'success');
    }).catch(() => {
        // Fallback para navegadores mais antigos
        const textarea = document.createElement('textarea');
        textarea.value = link;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Link copiado!', 'success');
    });
}

function copiarTodosLinks() {
    const links = [];
    document.querySelectorAll('.link-url input').forEach(input => {
        links.push(input.value);
    });
    
    const texto = links.join('\n');
    navigator.clipboard.writeText(texto).then(() => {
        showToast('Todos os links copiados!', 'success');
    });
}

function baixarCSV() {
    const dados = [];
    const cards = document.querySelectorAll('.link-card');
    
    dados.push(['Nome', 'CPF', 'Link QR Code']); // Cabeçalho
    
    cards.forEach(card => {
        const nome = card.querySelector('.link-header strong').textContent;
        const cpf = card.querySelector('.link-cpf').textContent;
        const link = card.querySelector('.link-url input').value;
        dados.push([nome, cpf, link]);
    });
    
    const csv = dados.map(linha => linha.map(campo => `"${campo}"`).join(',')).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'links-qr-codes.csv';
    link.click();
}

function showToast(message, type = 'info') {
    // Implementação simples de toast
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 5px;
        z-index: 9999;
        font-weight: 500;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php obter_rodape_admin(); ?>