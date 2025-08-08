<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Verificar login
requer_login_participante();

$participante = obter_participante_logado();
$csrf_token = gerar_csrf_token();

// Verificar se sistema foi migrado
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

if ($tabela_inscricoes_existe && function_exists('obter_inscricoes_participante')) {
    // Sistema novo - usar tabela inscricoes
    $eventos = obter_inscricoes_participante($participante['id']);
} else {
    // Sistema antigo - buscar diretamente na tabela participantes
    $eventos = buscar_todos("
        SELECT 
            p.*,
            e.nome as evento_nome,
            e.slug,
            e.data_inicio,
            e.data_fim,
            e.horario_inicio,
            e.horario_fim,
            e.local,
            e.cidade,
            e.valor,
            e.imagem,
            pg.status as pagamento_status,
            p.id as participante_id,
            p.evento_id,
            p.status,
            p.checkin_timestamp,
            e.nome as nome,
            e.data_inicio,
            e.local
        FROM participantes p 
        INNER JOIN eventos e ON p.evento_id = e.id 
        LEFT JOIN pagamentos pg ON p.id = pg.participante_id 
        WHERE p.cpf = ? 
        ORDER BY e.data_inicio DESC
    ", [$participante['cpf']]);
}

// Função para formatar status
function formatar_status($status) {
    $badges = [
        'inscrito' => '<span class="status-badge status-inscrito">Inscrito</span>',
        'pago' => '<span class="status-badge status-pago">Pago</span>',
        'presente' => '<span class="status-badge status-presente">Presente</span>',
        'cancelado' => '<span class="status-badge status-cancelado">Cancelado</span>'
    ];
    
    return $badges[$status] ?? $status;
}

// Função para formatar status de pagamento
function formatar_status_pagamento($status, $valor) {
    if ($valor == 0) {
        return '<span class="status-badge status-gratuito">Gratuito</span>';
    }
    
    $badges = [
        'pendente' => '<span class="status-badge status-pendente">Pendente</span>',
        'pago' => '<span class="status-badge status-pago">Pago</span>',
        'cancelado' => '<span class="status-badge status-cancelado">Cancelado</span>',
        'estornado' => '<span class="status-badge status-estornado">Estornado</span>'
    ];
    
    return $badges[$status] ?? '<span class="status-badge status-pendente">Pendente</span>';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Eventos - Área do Participante</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        .participante-area {
            min-height: 100vh;
            background: #f8f9fa;
        }

        .header-participante {
            background: white;
            border-bottom: 1px solid #e1e5e9;
            padding: 16px 0;
            margin-bottom: 30px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--cor-primaria);
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: var(--cor-texto-principal);
        }

        .user-actions {
            font-size: 14px;
            color: var(--cor-texto-secundario);
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-texto-principal);
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--cor-texto-secundario);
            font-size: 16px;
        }

        .eventos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .evento-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .evento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .evento-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--cor-primaria), var(--cor-primaria-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
        }

        .evento-content {
            padding: 24px;
        }

        .evento-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--cor-texto-principal);
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .evento-info {
            margin-bottom: 16px;
        }

        .evento-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--cor-texto-secundario);
        }

        .evento-status {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-inscrito { background: #e3f2fd; color: #1976d2; }
        .status-pago { background: #e8f5e8; color: #2e7d32; }
        .status-presente { background: #f3e5f5; color: #7b1fa2; }
        .status-cancelado { background: #ffebee; color: #c62828; }
        .status-pendente { background: #fff3e0; color: #f57c00; }
        .status-gratuito { background: #e8f5e8; color: #2e7d32; }
        .status-estornado { background: #ffebee; color: #c62828; }

        .evento-actions {
            display: flex;
            gap: 12px;
        }

        .btn-qr {
            flex: 1;
            background: var(--cor-primaria);
            color: white;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-qr:hover {
            background: var(--cor-primaria-dark);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: var(--cor-texto-principal);
            padding: 12px 16px;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--cor-texto-secundario);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Modal QR Code */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 400px;
            width: 100%;
            margin: auto;
            margin-top: 10vh;
            padding: 30px;
            text-align: center;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--cor-texto-principal);
            margin-bottom: 8px;
        }

        .qr-container {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .qr-info {
            margin-top: 16px;
            padding: 16px;
            background: #e3f2fd;
            border-radius: 8px;
            font-size: 14px;
            color: #1976d2;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-close {
            flex: 1;
            background: #6c757d;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-download {
            flex: 1;
            background: var(--cor-primaria);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .eventos-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="participante-area">
    <header class="header-participante">
        <div class="header-content">
            <div class="header-title">Área do Participante</div>
            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($participante['nome']) ?></div>
                    <div class="user-actions">
                        <a href="logout.php" class="btn-logout">Sair</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Meus Eventos</h1>
            <p class="page-subtitle">Aqui estão todos os eventos nos quais você está inscrito</p>
        </div>

        <?php if (empty($eventos)): ?>
            <div class="empty-state">
                <div class="empty-icon">🎫</div>
                <h3>Nenhum evento encontrado</h3>
                <p>Você ainda não está inscrito em nenhum evento.</p>
                <a href="<?= SITE_URL ?>" class="btn-secondary">Ver eventos disponíveis</a>
            </div>
        <?php else: ?>
            <div class="eventos-grid">
                <?php foreach ($eventos as $evento): ?>
                    <div class="evento-card">
                        <div class="evento-image">
                            <?php if ($evento['imagem']): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= htmlspecialchars($evento['imagem']) ?>" 
                                     alt="<?= htmlspecialchars($evento['nome']) ?>"
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                🎉
                            <?php endif; ?>
                        </div>
                        
                        <div class="evento-content">
                            <h3 class="evento-title"><?= htmlspecialchars($evento['nome']) ?></h3>
                            
                            <div class="evento-info">
                                <div class="evento-info-item">
                                    📅 <?= date('d/m/Y', strtotime($evento['data_inicio'])) ?>
                                    <?php if ($evento['horario_inicio']): ?>
                                        às <?= date('H:i', strtotime($evento['horario_inicio'])) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="evento-info-item">
                                    📍 <?= htmlspecialchars($evento['local']) ?>
                                </div>
                                <?php if ($evento['valor'] > 0): ?>
                                    <div class="evento-info-item">
                                        💰 R$ <?= number_format($evento['valor'], 2, ',', '.') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evento-status">
                                <?= formatar_status($evento['status']) ?>
                                <?= formatar_status_pagamento($evento['pagamento_status'], $evento['valor']) ?>
                                <?php if ($evento['checkin_timestamp']): ?>
                                    <span class="status-badge status-presente">Check-in: <?= date('d/m/Y H:i', strtotime($evento['checkin_timestamp'])) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evento-actions">
                                <?php
                                $inscricaoId = $evento['inscricao_id'] ?? null;
                                $statusInscricao = $evento['status'];
                                $pagamentoStatus = $evento['pagamento_status'] ?? null;
                                $valorEvento = $evento['valor'] ?? 0;
                                ?>

                                <?php if ($statusInscricao === 'aprovada' && $inscricaoId): ?>
                                    <button class="btn-qr" onclick="mostrarQR(<?= (int)$participante['id'] ?>, <?= (int)$evento['evento_id'] ?>, '<?= htmlspecialchars($evento['nome']) ?>')">
                                        📱 Ver QR Code
                                    </button>
                                    <a href="<?= SITE_URL ?>/confirmacao.php?inscricao=<?= (int)$inscricaoId ?>" class="btn-secondary" target="_blank">📄 Comprovante</a>
                                <?php endif; ?>

                                <?php if ($inscricaoId && $valorEvento > 0 && $pagamentoStatus === 'pendente' && $statusInscricao === 'pendente'): ?>
                                    <a href="<?= SITE_URL ?>/pagamento.php?inscricao=<?= (int)$inscricaoId ?>" class="btn-secondary">💳 Pagar agora</a>
                                <?php endif; ?>

                                <?php if ($inscricaoId && $statusInscricao === 'pendente'): ?>
                                    <button class="btn-secondary" onclick="cancelarInscricao(<?= (int)$inscricaoId ?>)">❌ Cancelar</button>
                                <?php endif; ?>

                                <a href="<?= SITE_URL ?>/evento.php?slug=<?= $evento['slug'] ?>" class="btn-secondary" target="_blank">
                                    ℹ️ Detalhes
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal QR Code -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="qrModalTitle">QR Code para Check-in</h3>
                <p id="qrModalSubtitle"></p>
            </div>
            <div class="qr-container">
                <div id="qrcode"></div>
            </div>
            <div class="qr-info">
                <strong>Como usar:</strong><br>
                Apresente este QR Code na entrada do evento para fazer seu check-in.
            </div>
            <div class="modal-actions">
                <button class="btn-close" onclick="fecharModal()">Fechar</button>
                <button class="btn-download" onclick="baixarQR()">Baixar QR</button>
            </div>
        </div>
    </div>

    <script>
        let qrCanvas = null;
        let currentEventName = '';
        const CSRF_TOKEN = '<?= $csrf_token ?>';

        async function mostrarQR(participanteId, eventoId, eventoNome) {
            try {
                // Buscar dados do QR
                const response = await fetch('qr.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        participante_id: participanteId,
                        evento_id: eventoId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Limpar QR anterior
                    document.getElementById('qrcode').innerHTML = '';
                    
                    // Gerar novo QR
                    qrCanvas = await QRCode.toCanvas(result.qr_data, {
                        width: 200,
                        margin: 2,
                        color: {
                            dark: '#333333',
                            light: '#FFFFFF'
                        }
                    });
                    
                    document.getElementById('qrcode').appendChild(qrCanvas);
                    document.getElementById('qrModalTitle').textContent = 'QR Code para Check-in';
                    document.getElementById('qrModalSubtitle').textContent = eventoNome;
                    currentEventName = eventoNome;
                    
                    document.getElementById('qrModal').style.display = 'flex';
                } else {
                    alert('Erro ao gerar QR Code: ' + result.message);
                }
            } catch (error) {
                alert('Erro ao carregar QR Code');
            }
        }

        function fecharModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        function baixarQR() {
            if (qrCanvas) {
                const link = document.createElement('a');
                link.download = `qr-checkin-${currentEventName.replace(/[^a-zA-Z0-9]/g, '-')}.png`;
                link.href = qrCanvas.toDataURL();
                link.click();
            }
        }

        // Fechar modal clicando fora
        document.getElementById('qrModal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });

        async function cancelarInscricao(inscricaoId) {
            if (!confirm('Tem certeza que deseja cancelar sua inscrição?')) return;
            try {
                const resp = await fetch('cancelar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ inscricao_id: inscricaoId, csrf_token: CSRF_TOKEN })
                });
                const data = await resp.json();
                if (data.success) {
                    alert('Inscrição cancelada com sucesso.');
                    location.reload();
                } else {
                    alert(data.message || 'Não foi possível cancelar sua inscrição.');
                }
            } catch (e) {
                alert('Erro ao cancelar. Tente novamente.');
            }
        }
    </script>
</body>
</html> 