<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Verificar login
requer_login_participante();

$participante = obter_participante_logado();

// Buscar eventos do participante
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

// Filtrar apenas eventos aprovados
$eventos_aprovados = array_filter($eventos, function($evento) {
    $status = $evento['status_inscricao'] ?? $evento['status'];
    return $status === 'aprovada';
});

// Se há apenas um evento, redirecionar diretamente
if (count($eventos_aprovados) === 1) {
    $evento = reset($eventos_aprovados);
    $evento_id = $evento['evento_id'];
    header("Location: meu-qr.php?evento=" . $evento_id);
    exit;
}

// Se foi especificado um evento
$evento_selecionado = null;
if (isset($_GET['evento'])) {
    $evento_id = (int)$_GET['evento'];
    foreach ($eventos_aprovados as $evento) {
        if ($evento['evento_id'] == $evento_id) {
            $evento_selecionado = $evento;
            break;
        }
    }
}

// Gerar QR code se evento selecionado
$qr_data = null;
if ($evento_selecionado) {
    try {
        $qr_data = gerar_qr_checkin($participante['id'], $evento_selecionado['evento_id']);
    } catch (Exception $e) {
        error_log("Erro ao gerar QR: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu QR Code - Check-in</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="<?= SITE_URL ?>/assets/js/qr-simple.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-primaria-hover) 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header-simple {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-title {
            color: white;
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .main-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }

        .qr-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }

        .evento-info {
            margin-bottom: 25px;
        }

        .evento-nome {
            font-size: 20px;
            font-weight: 700;
            color: var(--cor-texto-principal);
            margin-bottom: 8px;
        }

        .evento-detalhes {
            color: var(--cor-texto-secundario);
            font-size: 14px;
            line-height: 1.5;
        }

        .qr-container {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin: 25px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 220px;
        }

        .qr-loading {
            color: var(--cor-texto-secundario);
            font-size: 16px;
        }

        .qr-instructions {
            background: #e3f2fd;
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
            color: #1976d2;
            font-size: 14px;
            line-height: 1.5;
        }

        .qr-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-action {
            flex: 1;
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--cor-primaria);
            color: white;
        }

        .btn-primary:hover {
            background: var(--cor-primaria-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: var(--cor-texto-principal);
            border: 1px solid #e1e5e9;
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .btn-whatsapp {
            background: #25d366;
            color: white;
        }

        .btn-whatsapp:hover {
            background: #20b954;
        }

        .evento-selector {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            max-width: 500px;
            width: 100%;
        }

        .selector-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--cor-texto-principal);
            margin-bottom: 20px;
        }

        .eventos-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .evento-item {
            background: #f8f9fa;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .evento-item:hover {
            border-color: var(--cor-primaria);
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .evento-item-nome {
            font-weight: 600;
            color: var(--cor-texto-principal);
            margin-bottom: 4px;
        }

        .evento-item-info {
            font-size: 13px;
            color: var(--cor-texto-secundario);
        }

        .no-events {
            text-align: center;
            padding: 40px 20px;
            color: white;
        }

        .no-events-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.7;
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 15px;
            }
            
            .qr-card, .evento-selector {
                padding: 20px;
            }
            
            .qr-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header-simple">
        <h1 class="header-title">🎫 Meu QR Code</h1>
        <a href="index.php" class="btn-back">← Voltar</a>
    </header>

    <main class="main-container">
        <?php if (empty($eventos_aprovados)): ?>
            <div class="no-events">
                <div class="no-events-icon">🎫</div>
                <h2>Nenhum evento encontrado</h2>
                <p>Você não possui eventos aprovados para check-in.</p>
                <a href="index.php" class="btn-action btn-secondary">Ver meus eventos</a>
            </div>
        <?php elseif (!$evento_selecionado): ?>
            <div class="evento-selector">
                <h2 class="selector-title">Selecione o evento</h2>
                <div class="eventos-list">
                    <?php foreach ($eventos_aprovados as $evento): ?>
                        <a href="meu-qr.php?evento=<?= $evento['evento_id'] ?>" class="evento-item">
                            <div class="evento-item-nome"><?= htmlspecialchars($evento['nome']) ?></div>
                            <div class="evento-item-info">
                                📅 <?= date('d/m/Y', strtotime($evento['data_inicio'])) ?>
                                <?php if ($evento['horario_inicio']): ?>
                                    às <?= date('H:i', strtotime($evento['horario_inicio'])) ?>
                                <?php endif; ?>
                                <br>
                                📍 <?= htmlspecialchars($evento['local']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="qr-card">
                <div class="evento-info">
                    <div class="evento-nome"><?= htmlspecialchars($evento_selecionado['nome']) ?></div>
                    <div class="evento-detalhes">
                        📅 <?= date('d/m/Y', strtotime($evento_selecionado['data_inicio'])) ?>
                        <?php if ($evento_selecionado['horario_inicio']): ?>
                            às <?= date('H:i', strtotime($evento_selecionado['horario_inicio'])) ?>
                        <?php endif; ?>
                        <br>
                        📍 <?= htmlspecialchars($evento_selecionado['local']) ?>
                    </div>
                </div>

                <div class="qr-container" id="qr-container">
                    <?php if ($qr_data): ?>
                        <div id="qrcode"></div>
                    <?php else: ?>
                        <div class="qr-loading">❌ Erro ao gerar QR Code</div>
                    <?php endif; ?>
                </div>

                <div class="qr-instructions">
                    <strong>📱 Como usar:</strong><br>
                    Apresente este QR Code na entrada do evento para fazer seu check-in automaticamente.
                </div>

                <div class="qr-actions">
                    <button class="btn-action btn-primary" onclick="baixarQR()">
                        📥 Baixar PNG
                    </button>
                    <a href="qr-imagem.php?evento=<?= $evento_selecionado['evento_id'] ?>&download=1" class="btn-action btn-secondary">
                        💾 Download Direto
                    </a>
                    <button class="btn-action btn-whatsapp" onclick="compartilharWhatsApp()">
                        📱 WhatsApp
                    </button>
                    <button class="btn-action btn-secondary" onclick="window.print()">
                        🖨️ Imprimir
                    </button>
                </div>
                
                <div style="margin-top: 15px; text-align: center;">
                    <small style="color: var(--cor-texto-secundario); font-size: 12px;">
                        💡 <strong>Dica:</strong> Salve este QR no seu celular para acesso offline!
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const qrData = <?= $qr_data ? json_encode($qr_data) : 'null' ?>;
        const eventoNome = <?= $evento_selecionado ? json_encode($evento_selecionado['nome']) : 'null' ?>;

        // Gerar QR Code se temos dados
        if (qrData) {
            window.addEventListener('DOMContentLoaded', async function() {
                try {
                    await window.VindeQR.renderTo('qrcode', qrData, {
                        size: 200
                    });
                } catch (error) {
                    console.error('Erro ao gerar QR:', error);
                    document.getElementById('qr-container').innerHTML = 
                        '<div class="qr-loading">❌ Erro ao exibir QR Code</div>';
                }
            });
        }

        async function baixarQR() {
            if (qrData && eventoNome) {
                try {
                    const filename = `qr-checkin-${eventoNome.replace(/[^a-zA-Z0-9]/g, '-')}.png`;
                    await window.VindeQR.download(qrData, filename, { size: 300 });
                } catch (error) {
                    console.error('Erro ao baixar QR:', error);
                    alert('Erro ao baixar QR Code');
                }
            }
        }

        function compartilharWhatsApp() {
            if (eventoNome) {
                const texto = `🎫 Aqui está meu QR Code para check-in no evento "${eventoNome}"!\n\nAcesse: ${window.location.href}`;
                const url = `https://wa.me/?text=${encodeURIComponent(texto)}`;
                window.open(url, '_blank');
            }
        }

        // Adicionar suporte a gestos para mobile
        if ('serviceWorker' in navigator) {
            // Preparar para PWA futuro
        }
    </script>
</body>
</html>