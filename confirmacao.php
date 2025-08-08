<?php
require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

// Verificar se usuário está logado
if (!participante_esta_logado()) {
    redirecionar(SITE_URL . '/participante/login.php');
}

$inscricao_id = $_GET['inscricao'] ?? '';

// Buscar dados da inscrição, participante e evento
$inscricao = [];
$participante = [];
$evento = [];
$pagamento = [];

if ($inscricao_id) {
    // Verificar se tabela inscricoes existe (compatibilidade)
    $tabela_inscricoes_existe = buscar_um("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'inscricoes'
    ");
    
    if ($tabela_inscricoes_existe['count'] > 0) {
        // Sistema novo - usar tabela inscricoes
        $dados = buscar_um("
            SELECT 
                i.*,
                p.nome as participante_nome,
                p.cpf as participante_cpf,
                p.email as participante_email,
                p.whatsapp as participante_whatsapp,
                p.qr_token,
                e.nome as evento_nome,
                e.descricao as evento_descricao,
                e.data_inicio,
                e.data_fim,
                e.horario_inicio,
                e.horario_fim,
                e.local,
                e.endereco,
                e.cidade,
                e.estado,
                e.valor,
                e.imagem,
                e.slug,
                e.programacao,
                e.max_participantes,
                pag.status as pagamento_status,
                pag.valor as pagamento_valor,
                pag.pago_em
            FROM inscricoes i
            JOIN participantes p ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN pagamentos pag ON pag.inscricao_id = i.id
            WHERE i.id = ? AND i.participante_id = ?
        ", [$inscricao_id, $_SESSION['participante_id']]);
        
    } else {
        // Sistema antigo - usar participante_id direto
        $dados = buscar_um("
            SELECT 
                p.id as inscricao_id,
                p.id as participante_id,
                p.evento_id,
                p.status,
                'aprovada' as status_inscricao,
                p.nome as participante_nome,
                p.cpf as participante_cpf,
                p.email as participante_email,
                p.whatsapp as participante_whatsapp,
                p.qr_token,
                e.nome as evento_nome,
                e.descricao as evento_descricao,
                e.data_inicio,
                e.data_fim,
                e.horario_inicio,
                e.horario_fim,
                e.local,
                e.endereco,
                e.cidade,
                e.estado,
                e.valor,
                e.imagem,
                e.slug,
                e.programacao,
                e.max_participantes,
                pag.status as pagamento_status,
                pag.valor as pagamento_valor,
                pag.pago_em
            FROM participantes p
            JOIN eventos e ON p.evento_id = e.id
            LEFT JOIN pagamentos pag ON p.id = pag.participante_id
            WHERE p.id = ?
        ", [$inscricao_id]);
    }
    
    if ($dados) {
        $inscricao = $dados;
        $participante = $dados;
        $evento = $dados;
        $pagamento = $dados;
    }
}

if (!$inscricao) {
    obter_cabecalho('Confirmação não encontrada');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Confirmação não encontrada</h1>
            <p>Os dados de confirmação não foram encontrados ou são inválidos.</p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Verificar se a inscrição está confirmada
$inscricao_confirmada = false;

if (isset($inscricao['status'])) {
    // Sistema novo - verificar status da inscrição
    $inscricao_confirmada = in_array($inscricao['status'], ['pendente', 'aprovada']);
} else {
    // Sistema antigo - sempre confirmada se chegou até aqui
    $inscricao_confirmada = true;
}

// Verificar se o pagamento foi confirmado (ou se é evento gratuito)
$pagamento_ok = ($pagamento['pagamento_status'] === 'pago') || ($evento['valor'] <= 0);

if (!$pagamento_ok && $evento['valor'] > 0) {
    // Redirecionar de volta para pagamento se ainda não foi pago
    redirecionar(SITE_URL . '/pagamento.php?inscricao=' . $inscricao_id);
}

// Gerar QR Token se não existir
if (empty($participante['qr_token'])) {
    $qr_token = bin2hex(random_bytes(16));
    atualizar_registro('participantes', ['qr_token' => $qr_token], ['id' => $_SESSION['participante_id']]);
    $participante['qr_token'] = $qr_token;
}

obter_cabecalho('Confirmação - ' . $evento['evento_nome'], 'confirmacao');
?>

<div class="sympla-page">
    <!-- Header de Sucesso -->
    <div class="success-header">
        <div class="container">
            <div class="success-content">
                <div class="success-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22,4 12,14.01 9,11.01"></polyline>
                    </svg>
                </div>
                <h1>Inscrição confirmada!</h1>
                <p>Você está inscrito no evento. Guarde seu QR Code para o check-in.</p>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="container">
        <div class="sympla-content">
            <!-- Card Principal -->
            <div class="main-card">
                <!-- Evento Info -->
                <div class="event-header">
                    <div class="event-image">
                        <?php if (!empty($evento['imagem'])): ?>
                            <img src="<?= SITE_URL ?>/uploads/<?= $evento['imagem'] ?>" alt="<?= htmlspecialchars($evento['evento_nome']) ?>">
                        <?php else: ?>
                            <div class="event-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="event-info">
                        <h2><?= htmlspecialchars($evento['evento_nome']) ?></h2>
                        <div class="event-details">
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"/>
                                </svg>
                                <span><?= formatar_data($evento['data_inicio']) ?></span>
                                <?php if ($evento['horario_inicio']): ?>
                                    <span>às <?= date('H:i', strtotime($evento['horario_inicio'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <span><?= htmlspecialchars($evento['local']) ?> - <?= htmlspecialchars($evento['cidade']) ?>, <?= $evento['estado'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Participante Info -->
                <div class="participant-section">
                    <h3>Dados do participante</h3>
                    <div class="participant-info">
                        <div class="participant-avatar">
                            <span><?= strtoupper(substr($participante['participante_nome'], 0, 1)) ?></span>
                        </div>
                        <div class="participant-details">
                            <strong><?= htmlspecialchars($participante['participante_nome']) ?></strong>
                            <span><?= htmlspecialchars($participante['participante_email']) ?></span>
                        </div>
                        <div class="participant-status">
                            <span class="status-badge confirmed">Confirmado</span>
                        </div>
                    </div>
                </div>

                <!-- QR Code Section -->
                <div class="qr-section">
                    <h3>QR Code de entrada</h3>
                    <div class="qr-container">
                        <div class="qr-code">
                            <canvas id="qr-canvas"></canvas>
                        </div>
                        <p class="qr-instructions">
                            Apresente este QR Code na entrada do evento para fazer seu check-in
                        </p>
                        <div class="qr-actions">
                            <button onclick="baixarQRCode()" class="btn-qr download">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                                </svg>
                                Baixar QR Code
                            </button>
                            <button onclick="compartilharWhatsApp()" class="btn-qr share">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893A11.821 11.821 0 0020.89 3.488"/>
                                </svg>
                                Compartilhar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Programação -->
                <?php if (!empty($evento['programacao'])): ?>
                    <?php $programacao = json_decode($evento['programacao'], true); ?>
                    <?php if ($programacao): ?>
                        <div class="program-section">
                            <div class="section-header">
                                <h3>Programação</h3>
                                <button class="toggle-btn" onclick="toggleProgram()">
                                    <span>Ver programação</span>
                                    <svg class="toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M7 10l5 5 5-5z"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="program-content" id="program-content">
                                <?php foreach ($programacao as $item): ?>
                                    <div class="program-item">
                                        <?php if (!empty($item['horario'])): ?>
                                            <div class="program-time"><?= htmlspecialchars($item['horario']) ?></div>
                                        <?php endif; ?>
                                        <div class="program-info">
                                            <h4><?= htmlspecialchars($item['titulo']) ?></h4>
                                            <?php if (!empty($item['descricao'])): ?>
                                                <p><?= htmlspecialchars($item['descricao']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['palestrante'])): ?>
                                                <span class="speaker">com <?= htmlspecialchars($item['palestrante']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Informações Adicionais -->
                <div class="info-section">
                    <h3>Informações importantes</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">⏰</div>
                            <div>
                                <strong>Chegue cedo</strong>
                                <p>Recomendamos chegar 30 minutos antes do início</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">📱</div>
                            <div>
                                <strong>QR Code sempre à mão</strong>
                                <p>Salve uma captura de tela ou baixe a imagem</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">🎫</div>
                            <div>
                                <strong>Não precisa imprimir</strong>
                                <p>Apresente o QR Code direto do celular</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Pagamento -->
                <div class="sidebar-card">
                    <h4>Pagamento</h4>
                    <?php if ($evento['valor'] > 0): ?>
                        <div class="payment-status paid">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                            </svg>
                            <span>Pago</span>
                        </div>
                        <div class="payment-amount">R$ <?= number_format($evento['valor'], 2, ',', '.') ?></div>
                        <?php if ($pagamento['pago_em']): ?>
                            <div class="payment-date">Pago em <?= formatar_data($pagamento['pago_em']) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="payment-status free">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <span>Gratuito</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ações -->
                <div class="sidebar-card">
                    <h4>Ações</h4>
                    <div class="action-buttons">
                        <a href="<?= SITE_URL ?>" class="btn-action secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                            </svg>
                            Ver mais eventos
                        </a>
                        <a href="<?= SITE_URL ?>/participante/" class="btn-action secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            Minha área
                        </a>
                        <button onclick="window.print()" class="btn-action secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                            </svg>
                            Imprimir
                        </button>
                    </div>
                </div>

                <!-- Contato -->
                <div class="sidebar-card">
                    <h4>Precisa de ajuda?</h4>
                    <p>Entre em contato conosco</p>
                    <a href="https://wa.me/<?= limpar_telefone(WHATSAPP_CONTATO) ?>" target="_blank" class="btn-action primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893A11.821 11.821 0 0020.89 3.488"/>
                        </svg>
                        WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    gerarQRCode();
});

function gerarQRCode() {
    const dadosQR = {
        tipo: 'checkin',
        inscricao_id: <?= $inscricao_id ?>,
        participante_id: <?= $participante['participante_id'] ?? $inscricao_id ?>,
        token: '<?= $participante['qr_token'] ?>',
        evento_id: <?= $evento['evento_id'] ?? $evento['id'] ?>,
        nome: '<?= htmlspecialchars($participante['participante_nome']) ?>',
        evento: '<?= htmlspecialchars($evento['evento_nome']) ?>'
    };
    
    const qrData = JSON.stringify(dadosQR);
    const canvas = document.getElementById('qr-canvas');
    
    if (canvas) {
        QRCode.toCanvas(canvas, qrData, {
            width: 200,
            margin: 2,
            color: {
                dark: '#333',
                light: '#ffffff'
            }
        }, function(error) {
            if (error) {
                console.error('Erro ao gerar QR Code:', error);
            }
        });
    }
}

function baixarQRCode() {
    const canvas = document.getElementById('qr-canvas');
    const link = document.createElement('a');
    link.download = 'qr-code-<?= $evento['slug'] ?? 'evento' ?>.png';
    link.href = canvas.toDataURL();
    link.click();
}

function compartilharWhatsApp() {
    const texto = `🎉 Inscrição Confirmada!

📅 Evento: <?= htmlspecialchars($evento['evento_nome']) ?>
👤 Participante: <?= htmlspecialchars($participante['participante_nome']) ?>
📍 Local: <?= htmlspecialchars($evento['local']) ?>
📅 Data: <?= formatar_data($evento['data_inicio']) ?>

✅ Minha inscrição foi confirmada com sucesso!

🎫 Acesse o link para ver meu QR Code:
<?= SITE_URL ?>/confirmacao.php?inscricao=<?= $inscricao_id ?>

Nos vemos lá! 🙏`;

    const url = `https://wa.me/?text=${encodeURIComponent(texto)}`;
    window.open(url, '_blank');
}

function toggleProgram() {
    const content = document.getElementById('program-content');
    const icon = document.querySelector('.toggle-icon');
    const btn = document.querySelector('.toggle-btn span');
    
    if (content.style.display === 'none' || !content.style.display) {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
        btn.textContent = 'Ocultar programação';
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        btn.textContent = 'Ver programação';
    }
}

// Simular envio de notificação WhatsApp
<?php
$mensagem_whatsapp = "🎉 Inscrição Confirmada!

Olá {$participante['participante_nome']},

Sua inscrição foi confirmada com sucesso!

📅 Evento: {$evento['evento_nome']}
📍 Local: {$evento['local']}
📅 Data: " . formatar_data($evento['data_inicio']) . "

🎫 Seu QR Code de acesso:
" . SITE_URL . "/confirmacao.php?inscricao={$inscricao_id}

⏰ Chegue com 30min de antecedência
📱 Salve este link no seu celular

Qualquer dúvida, entre em contato!

Paz e Bem! 🙏";

simular_whatsapp($participante['participante_whatsapp'], $mensagem_whatsapp);
?>
</script>

<?php
obter_rodape();
?> 