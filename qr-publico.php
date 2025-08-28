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

// Verificar se é uma requisição AJAX para status
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $participante['status'] ?? 'inscrito',
        'checkin_timestamp' => $participante['checkin_timestamp'] ?? null,
        'checkin_operador' => $participante['checkin_operador'] ?? null
    ]);
    exit;
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
        
        .status-presente {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .checkin-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        
        .checkin-timestamp {
            font-weight: 600;
            color: #155724;
            margin-bottom: 5px;
        }
        
        .checkin-operador {
             font-size: 0.9em;
             color: #6c757d;
         }
         
         .checkin-success-notification {
             position: fixed;
             top: 20px;
             left: 50%;
             transform: translateX(-50%);
             background: #28a745;
             color: white;
             padding: 15px 25px;
             border-radius: 10px;
             box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
             z-index: 1000;
             animation: slideDown 0.5s ease-out;
             display: none;
         }
         
         @keyframes slideDown {
             from {
                 opacity: 0;
                 transform: translateX(-50%) translateY(-20px);
             }
             to {
                 opacity: 1;
                 transform: translateX(-50%) translateY(0);
             }
         }
         
         .confetti {
             position: fixed;
             width: 10px;
             height: 10px;
             background: #f39c12;
             animation: confetti-fall 3s linear infinite;
             z-index: 999;
         }
         
         @keyframes confetti-fall {
             0% {
                 transform: translateY(-100vh) rotate(0deg);
                 opacity: 1;
             }
             100% {
                 transform: translateY(100vh) rotate(720deg);
                 opacity: 0;
             }
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
        // Verificar status de check-in primeiro
        $participante_status = $participante['status'] ?? 'inscrito';
        if ($participante_status === 'presente') {
            $status_class = 'status-presente';
            $status_text = '🎉 Check-in Realizado!';
            $checkin_timestamp = $participante['checkin_timestamp'] ?? null;
        } else {
            $status = $participante['status_inscricao'] ?? 'aprovada';
            $status_class = $status === 'aprovada' ? 'status-aprovada' : 'status-pendente';
            $status_text = $status === 'aprovada' ? '✅ Inscrito' : '⏳ Pendente';
        }
        ?>
        <div class="status-badge <?= $status_class ?>"><?= $status_text ?></div>
        
        <?php if ($participante_status === 'presente' && $checkin_timestamp): ?>
            <div class="checkin-info">
                <div class="checkin-timestamp">📅 Check-in realizado em: <?= date('d/m/Y H:i', strtotime($checkin_timestamp)) ?></div>
                <?php if (!empty($participante['checkin_operador'])): ?>
                    <div class="checkin-operador">👤 Por: <?= htmlspecialchars($participante['checkin_operador']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- QR Code -->
        <div class="qr-code">
            <div id="qrcode"></div>
        </div>
        
        <div class="qr-instructions">
            <?php if ($participante_status === 'presente'): ?>
                🎉 <strong>Você já fez check-in!</strong><br>
                Seu QR Code foi validado com sucesso
            <?php else: ?>
                📱 <strong>Apresente este QR Code na entrada do evento</strong><br>
                Mantenha esta tela aberta ou baixe a imagem
            <?php endif; ?>
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
        
        // Verificar status de check-in automaticamente
        let checkinVerificado = <?= $participante_status === 'presente' ? 'true' : 'false' ?>;
        
        function verificarStatusCheckin() {
             if (checkinVerificado) return; // Já foi verificado
             
             // Fazer uma requisição para verificar o status atual
             fetch(window.location.href + '&ajax=1')
                 .then(response => response.json())
                 .then(data => {
                     if (data.status === 'presente' && !checkinVerificado) {
                         checkinVerificado = true;
                         
                         // Mostrar notificação de sucesso
                         mostrarNotificacaoCheckin();
                         
                         // Criar efeito de confetes
                         criarConfetes();
                         
                         // Recarregar a página após 2 segundos para mostrar o feedback completo
                         setTimeout(() => {
                             window.location.reload();
                         }, 2000);
                     }
                 })
                 .catch(error => {
                     console.log('Erro ao verificar status:', error);
                 });
         }
         
         function mostrarNotificacaoCheckin() {
             // Criar notificação
             const notification = document.createElement('div');
             notification.className = 'checkin-success-notification';
             notification.innerHTML = '🎉 Check-in realizado com sucesso! 🎉';
             notification.style.display = 'block';
             
             document.body.appendChild(notification);
             
             // Remover após 3 segundos
             setTimeout(() => {
                 notification.remove();
             }, 3000);
         }
         
         function criarConfetes() {
             const cores = ['#f39c12', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f1c40f'];
             
             for (let i = 0; i < 50; i++) {
                 setTimeout(() => {
                     const confete = document.createElement('div');
                     confete.className = 'confetti';
                     confete.style.left = Math.random() * 100 + 'vw';
                     confete.style.backgroundColor = cores[Math.floor(Math.random() * cores.length)];
                     confete.style.animationDelay = Math.random() * 3 + 's';
                     confete.style.animationDuration = (Math.random() * 2 + 2) + 's';
                     
                     document.body.appendChild(confete);
                     
                     // Remover após a animação
                     setTimeout(() => {
                         confete.remove();
                     }, 5000);
                 }, i * 50);
             }
         }
        
        // Verificar a cada 5 segundos se ainda não fez check-in
        if (!checkinVerificado) {
            setInterval(verificarStatusCheckin, 5000);
        }
    </script>
</body>
</html>