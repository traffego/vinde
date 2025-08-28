<?php
/**
 * Acesso público ao QR Code
 * Permite visualizar QR code sem login usando CPF e WhatsApp como identificadores
 * URL: /qr-publico.php?cpf=12345678901&whatsapp=11999999999&evento=123
 */

require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

// Função para limpar e validar parâmetros
function limpar_parametro($valor) {
    return preg_replace('/[^0-9]/', '', $valor);
}

// Obter parâmetros da URL
$cpf = limpar_parametro($_GET['cpf'] ?? '');
$whatsapp = limpar_parametro($_GET['whatsapp'] ?? '');
$evento_id = (int)($_GET['evento'] ?? 0);

// Validações básicas
if (empty($cpf) || empty($whatsapp)) {
    http_response_code(400);
    die('❌ Parâmetros obrigatórios: cpf e whatsapp');
}

if (strlen($cpf) !== 11) {
    http_response_code(400);
    die('❌ CPF deve ter 11 dígitos');
}

if (strlen($whatsapp) < 10 || strlen($whatsapp) > 15) {
    http_response_code(400);
    die('❌ WhatsApp inválido');
}

// Buscar participante pelos identificadores
$participante = null;

// Verificar se existe tabela inscricoes (sistema novo)
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

if ($tabela_inscricoes_existe) {
    // Sistema novo - buscar na tabela inscricoes
    if ($evento_id > 0) {
        // Evento específico
        $participante = buscar_um("
            SELECT 
                p.id,
                p.nome,
                p.cpf,
                p.whatsapp,
                p.qr_token,
                i.id as inscricao_id,
                i.status as status_inscricao,
                e.id as evento_id,
                e.nome as evento_nome,
                e.data_inicio,
                e.horario_inicio,
                e.local,
                e.cidade,
                pg.status as pagamento_status,
                pg.valor as pagamento_valor
            FROM participantes p
            JOIN inscricoes i ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
            WHERE p.cpf = ? AND p.whatsapp = ? AND i.evento_id = ? 
            AND i.status IN ('pendente', 'aprovada')
        ", [$cpf, $whatsapp, $evento_id]);
    } else {
        // Buscar próximo evento do participante
        $participante = buscar_um("
            SELECT 
                p.id,
                p.nome,
                p.cpf,
                p.whatsapp,
                p.qr_token,
                i.id as inscricao_id,
                i.status as status_inscricao,
                e.id as evento_id,
                e.nome as evento_nome,
                e.data_inicio,
                e.horario_inicio,
                e.local,
                e.cidade,
                pg.status as pagamento_status,
                pg.valor as pagamento_valor
            FROM participantes p
            JOIN inscricoes i ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
            WHERE p.cpf = ? AND p.whatsapp = ? 
            AND i.status IN ('pendente', 'aprovada')
            AND e.data_inicio >= CURDATE()
            ORDER BY e.data_inicio ASC, e.horario_inicio ASC
            LIMIT 1
        ", [$cpf, $whatsapp]);
    }
} else {
    // Sistema antigo - buscar na tabela participantes
    if ($evento_id > 0) {
        $participante = buscar_um("
            SELECT 
                p.id,
                p.nome,
                p.cpf,
                p.whatsapp,
                p.qr_token,
                p.evento_id,
                e.nome as evento_nome,
                e.data_inicio,
                e.horario_inicio,
                e.local,
                e.cidade,
                pg.status as pagamento_status,
                pg.valor as pagamento_valor
            FROM participantes p
            JOIN eventos e ON p.evento_id = e.id
            LEFT JOIN pagamentos pg ON pg.participante_id = p.id
            WHERE p.cpf = ? AND p.whatsapp = ? AND p.evento_id = ?
            AND p.status != 'cancelado'
        ", [$cpf, $whatsapp, $evento_id]);
    } else {
        $participante = buscar_um("
            SELECT 
                p.id,
                p.nome,
                p.cpf,
                p.whatsapp,
                p.qr_token,
                p.evento_id,
                e.nome as evento_nome,
                e.data_inicio,
                e.horario_inicio,
                e.local,
                e.cidade,
                pg.status as pagamento_status,
                pg.valor as pagamento_valor
            FROM participantes p
            JOIN eventos e ON p.evento_id = e.id
            LEFT JOIN pagamentos pg ON pg.participante_id = p.id
            WHERE p.cpf = ? AND p.whatsapp = ?
            AND p.status != 'cancelado'
            AND e.data_inicio >= CURDATE()
            ORDER BY e.data_inicio ASC, e.horario_inicio ASC
            LIMIT 1
        ", [$cpf, $whatsapp]);
    }
}

if (!$participante) {
    http_response_code(404);
    die('❌ Participante não encontrado ou não inscrito em eventos válidos');
}

// Gerar QR token se não existir
if (empty($participante['qr_token'])) {
    $qr_token = bin2hex(random_bytes(16));
    executar("UPDATE participantes SET qr_token = ? WHERE id = ?", [$qr_token, $participante['id']]);
    $participante['qr_token'] = $qr_token;
}

// Gerar dados do QR Code
try {
    $qr_data = gerar_qr_checkin($participante['id'], $participante['evento_id']);
    if (!$qr_data) {
        throw new Exception('Erro ao gerar QR Code');
    }
} catch (Exception $e) {
    error_log("Erro ao gerar QR público: " . $e->getMessage());
    http_response_code(500);
    die('❌ Erro interno ao gerar QR Code');
}

// Verificar se é solicitação de imagem direta
if (isset($_GET['img'])) {
    $tamanho = min(500, max(100, (int)($_GET['size'] ?? 300)));
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size={$tamanho}x{$tamanho}&data=" . urlencode($qr_data);
    
    if (isset($_GET['download'])) {
        header('Content-Disposition: attachment; filename="qr-code-' . $participante['nome'] . '.png"');
    }
    
    header('Location: ' . $qr_url);
    exit;
}

// Gerar URL para compartilhamento
$url_atual = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
             '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$whatsapp_text = urlencode("🎫 Meu QR Code para o evento: {$participante['evento_nome']}\n\n📱 Acesse: {$url_atual}");
$whatsapp_url = "https://wa.me/?text={$whatsapp_text}";

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?= htmlspecialchars($participante['evento_nome']) ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qr-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        
        .evento-info {
            margin-bottom: 25px;
        }
        
        .evento-nome {
            font-size: 1.4em;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .participante-nome {
            font-size: 1.1em;
            color: #4a5568;
            margin-bottom: 5px;
        }
        
        .evento-data {
            color: #718096;
            font-size: 0.9em;
        }
        
        .qr-code {
            background: #f7fafc;
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            border: 2px dashed #e2e8f0;
        }
        
        .qr-instructions {
            color: #4a5568;
            font-size: 0.9em;
            margin: 20px 0;
            line-height: 1.5;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9em;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #4299e1;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3182ce;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        
        .btn-outline:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .status-aprovada {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-pendente {
            background: #fef5e7;
            color: #744210;
        }
        
        .footer-info {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 0.8em;
        }
        
        @media (max-width: 480px) {
            .qr-container {
                margin: 10px;
                padding: 20px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <!-- Informações do Evento -->
        <div class="evento-info">
            <div class="evento-nome"><?= htmlspecialchars($participante['evento_nome']) ?></div>
            <div class="participante-nome">👤 <?= htmlspecialchars($participante['nome']) ?></div>
            <?php if ($participante['data_inicio']): ?>
                <div class="evento-data">
                    📅 <?= date('d/m/Y', strtotime($participante['data_inicio'])) ?>
                    <?php if ($participante['horario_inicio']): ?>
                        às <?= date('H:i', strtotime($participante['horario_inicio'])) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($participante['local']): ?>
                <div class="evento-data">📍 <?= htmlspecialchars($participante['local']) ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Status -->
        <?php 
        $status = $participante['status_inscricao'] ?? 'aprovada';
        $status_class = $status === 'aprovada' ? 'status-aprovada' : 'status-pendente';
        $status_text = $status === 'aprovada' ? '✅ Inscrito' : '⏳ Pendente';
        ?>
        <div class="status-badge <?= $status_class ?>"><?= $status_text ?></div>
        
        <!-- QR Code -->
        <div class="qr-code">
            <div id="qrcode"></div>
        </div>
        
        <div class="qr-instructions">
            📱 <strong>Apresente este QR Code na entrada do evento</strong><br>
            Mantenha esta tela aberta ou baixe a imagem
        </div>
        
        <!-- Ações -->
        <div class="actions">
            <a href="?<?= http_build_query(array_merge($_GET, ['img' => '1', 'download' => '1'])) ?>" 
               class="btn btn-primary">
                📥 Baixar PNG
            </a>
            
            <a href="<?= $whatsapp_url ?>" 
               target="_blank" 
               class="btn btn-success">
                📱 Compartilhar
            </a>
            
            <button onclick="window.print()" class="btn btn-outline">
                🖨️ Imprimir
            </button>
        </div>
        
        <!-- Informações do rodapé -->
        <div class="footer-info">
            🔒 Link seguro e personalizado<br>
            💾 Salve este link nos favoritos para acesso rápido
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="<?= SITE_URL ?>/assets/js/qrcode.min.js"></script>
    <script>
        // Gerar QR Code
        const qrData = <?= json_encode($qr_data) ?>;
        
        // Limpar container
        document.getElementById('qrcode').innerHTML = '';
        
        // Gerar QR usando biblioteca
        const qr = new QRCode(document.getElementById('qrcode'), {
            text: qrData,
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
        
        // Função para baixar QR como PNG (fallback)
        function baixarQRLocal() {
            const canvas = document.querySelector('#qrcode canvas');
            if (canvas) {
                const link = document.createElement('a');
                link.download = 'qr-code-<?= preg_replace('/[^a-zA-Z0-9]/', '-', $participante['nome']) ?>.png';
                link.href = canvas.toDataURL();
                link.click();
            }
        }
    </script>
</body>
</html>