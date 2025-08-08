<?php
/**
 * Script para testar o webhook EFI Bank
 * Simula chamadas da EFI para verificar se o webhook está funcionando
 */

require_once '../includes/init.php';
requer_login('admin');

$titulo_pagina = 'Teste Webhook EFI Bank';
$resultado = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'testar_webhook':
            $resultado = testar_webhook_local();
            break;
            
        case 'simular_pix':
            $resultado = simular_notificacao_pix();
            break;
    }
}

function testar_webhook_local() {
    // Payload de teste simulando notificação da EFI
    $payload_teste = [
        'pix' => [
            [
                'endToEndId' => 'E123456789012345678901234567890123456',
                'txid' => 'TESTE' . time(),
                'valor' => '10.00',
                'horario' => date('c'),
                'infoPagador' => 'Teste webhook'
            ]
        ]
    ];
    
    // Detectar URL do webhook automaticamente
    $webhook_url = detectar_url_webhook();
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $webhook_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload_teste),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: EFI-Webhook-Test/1.0',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true, // Manter verificação SSL em produção
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'EFI-Webhook-Test/1.0',
        CURLOPT_VERBOSE => false
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    return [
        'url' => $webhook_url,
        'payload_enviado' => $payload_teste,
        'http_code' => $http_code,
        'resposta' => $response,
        'erro_curl' => $error,
        'sucesso' => ($http_code >= 200 && $http_code < 500) && !$error, // Aceitar códigos de erro do webhook como sucesso de conectividade
        'info_adicional' => [
            'connect_time' => curl_getinfo($curl, CURLINFO_CONNECT_TIME),
            'total_time' => curl_getinfo($curl, CURLINFO_TOTAL_TIME),
            'ssl_verify_result' => curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT)
        ]
    ];
}

function simular_notificacao_pix() {
    // Criar um pagamento de teste no banco
    try {
        $txid_teste = 'TESTE' . time();
        $end_to_end_teste = 'E' . str_pad(rand(1, 999999999999999999), 32, '0', STR_PAD_LEFT);
        
        // Buscar primeiro participante para teste
        $participante = buscar_um("SELECT * FROM participantes LIMIT 1");
        if (!$participante) {
            return ['erro' => 'Nenhum participante encontrado para teste'];
        }
        
        // Criar pagamento de teste
        $pagamento_id = inserir_registro('pagamentos', [
            'participante_id' => $participante['id'],
            'inscricao_id' => 1, // Assumindo que existe
            'valor' => 10.00,
            'status' => 'pendente',
            'pix_txid' => $txid_teste,
            'tipo_pagamento' => 'pix'
        ]);
        
        if (!$pagamento_id) {
            return ['erro' => 'Falha ao criar pagamento de teste'];
        }
        
        // Simular webhook da EFI
        $payload_pix = [
            'pixRecebidos' => [
                [
                    'endToEndId' => $end_to_end_teste,
                    'txid' => $txid_teste,
                    'valor' => '10.00',
                    'horario' => date('c'),
                    'infoPagador' => 'Simulação de teste'
                ]
            ]
        ];
        
        $webhook_url = detectar_url_webhook();
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $webhook_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload_pix),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: EFI-PIX-Webhook/1.0',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        // Verificar se pagamento foi atualizado
        $pagamento_atualizado = buscar_um("SELECT * FROM pagamentos WHERE id = ?", [$pagamento_id]);
        
        return [
            'txid_teste' => $txid_teste,
            'end_to_end_teste' => $end_to_end_teste,
            'pagamento_id' => $pagamento_id,
            'http_code' => $http_code,
            'resposta_webhook' => $response,
            'erro_curl' => $error,
            'status_antes' => 'pendente',
            'status_depois' => $pagamento_atualizado['status'] ?? 'erro',
            'conectividade_ok' => ($http_code >= 200 && $http_code < 500) && !$error,
            'webhook_processou' => $http_code === 200,
            'pagamento_confirmado' => $pagamento_atualizado['status'] === 'pago',
            'sucesso' => ($http_code >= 200 && $http_code < 500) && !$error
        ];
        
    } catch (Exception $e) {
        return ['erro' => 'Exceção: ' . $e->getMessage()];
    }
}

/**
 * Detecta automaticamente a URL do webhook baseada no ambiente atual
 */
function detectar_url_webhook() {
    // Usar função de detecção de ambiente local se disponível
    if (function_exists('obter_url_base')) {
        return obter_url_base() . '/webhook_efi.php';
    }
    
    // Fallback para detecção manual
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_localhost = (strpos($host, 'localhost') !== false || 
                     strpos($host, '127.0.0.1') !== false ||
                     strpos($host, '::1') !== false);
    
    if ($is_localhost) {
        // Construir URL local
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $port = $_SERVER['SERVER_PORT'] ?? '';
        $port_str = ($port && $port != '80' && $port != '443') ? ':' . $port : '';
        
        $base_url = $protocol . '://' . $host . $port_str;
        
        // Detectar diretório do projeto automaticamente
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $project_dir = dirname($script_dir); // Remove /admin
        
        return $base_url . $project_dir . '/webhook_efi.php';
    }
    
    // Em produção, usar SITE_URL configurado
    return (defined('SITE_URL') ? SITE_URL : 'http://localhost') . '/webhook_efi.php';
}

obter_cabecalho_admin($titulo_pagina, 'configuracoes');
?>

<div class="admin-content">
    <div class="admin-header">
        <h1><?= $titulo_pagina ?></h1>
        <p>Teste o webhook EFI Bank para verificar se está funcionando corretamente</p>
    </div>

    <?php if ($resultado): ?>
        <div class="alert <?= isset($resultado['sucesso']) && $resultado['sucesso'] ? 'alert-success' : 'alert-error' ?>">
            <h3>Resultado do Teste</h3>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;">
<?= htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
            </pre>
        </div>
    <?php endif; ?>

    <div class="test-section">
        <div class="test-card">
            <h3>🔗 Teste Básico do Webhook</h3>
            <p>Envia uma requisição de teste para o webhook para verificar se está respondendo.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="testar_webhook">
                <button type="submit" class="btn btn-primary">Testar Webhook</button>
            </form>
        </div>

        <div class="test-card">
            <h3>💳 Simular Notificação PIX</h3>
            <p>Cria um pagamento de teste e simula uma notificação da EFI Bank.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="simular_pix">
                <button type="submit" class="btn btn-success">Simular PIX</button>
            </form>
        </div>
    </div>

    <div class="info-section">
        <h3>ℹ️ Informações do Webhook</h3>
        <?php 
        $webhook_url_detectada = detectar_url_webhook();
        $is_localhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);
        $ambiente_local = function_exists('is_ambiente_local') && is_ambiente_local();
        $url_base_atual = function_exists('obter_url_base') ? obter_url_base() : (defined('SITE_URL') ? SITE_URL : 'N/A');
        ?>
        <ul>
            <li><strong>URL do Webhook (Configurada):</strong> <code><?= SITE_URL ?>/webhook_efi.php</code></li>
            <li><strong>URL do Webhook (Para Teste):</strong> <code><?= $webhook_url_detectada ?></code></li>
            <li><strong>URL Base Detectada:</strong> <code><?= $url_base_atual ?></code></li>
            <li><strong>Ambiente:</strong> 
                <?php if ($ambiente_local): ?>
                    🏠 Local (Auto-detectado)
                <?php elseif ($is_localhost): ?>
                    🏠 Local (XAMPP)
                <?php else: ?>
                    🌐 Produção
                <?php endif; ?>
            </li>
            <li><strong>Host:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'N/A' ?></li>
            <li><strong>Método:</strong> POST</li>
            <li><strong>Content-Type:</strong> application/json</li>
            <li><strong>EFI Ativa:</strong> <?= efi_esta_ativo() ? '✅ Sim' : '❌ Não' ?></li>
        </ul>
        
        <?php if ($is_localhost): ?>
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 15px;">
            <strong>🏠 Ambiente Local Detectado</strong><br>
            O teste usará a URL local automaticamente (<?= $webhook_url_detectada ?>).<br>
            Para testes reais da EFI, configure o domínio em produção e registre o webhook via API.
        </div>
        <?php endif; ?>
        
        <?php if ($webhook_url_detectada !== SITE_URL . '/webhook_efi.php'): ?>
        <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-top: 15px;">
            <strong>ℹ️ URLs Diferentes</strong><br>
            A URL configurada é diferente da URL detectada. Isso é normal em ambiente local.<br>
            Em produção, certifique-se de que a URL configurada seja acessível pela EFI.
        </div>
        <?php endif; ?>
    </div>

    <div class="logs-section">
        <h3>📋 Logs Recentes do Webhook</h3>
        <div id="logs-webhook">
            <?php
            try {
                $logs = buscar_todos("
                    SELECT * FROM logs_atividades 
                    WHERE acao LIKE '%webhook%' 
                    ORDER BY criado_em DESC 
                    LIMIT 10
                ");
                
                if ($logs) {
                    echo "<table class='table'>";
                    echo "<tr><th>Data</th><th>Ação</th><th>Detalhes</th></tr>";
                    foreach ($logs as $log) {
                        echo "<tr>";
                        echo "<td>" . date('d/m/Y H:i:s', strtotime($log['criado_em'])) . "</td>";
                        echo "<td>" . htmlspecialchars($log['acao']) . "</td>";
                        echo "<td>" . htmlspecialchars(substr($log['detalhes'], 0, 100)) . "...</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>Nenhum log de webhook encontrado.</p>";
                }
            } catch (Exception $e) {
                echo "<p>Erro ao buscar logs: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>
    </div>
</div>

<style>
.test-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 30px 0;
}

.test-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.test-card h3 {
    color: #1e40af;
    margin-bottom: 10px;
}

.test-card p {
    color: #666;
    margin-bottom: 20px;
}

.info-section, .logs-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    margin: 20px 0;
}

.info-section ul {
    margin: 0;
    padding-left: 20px;
}

.info-section li {
    margin-bottom: 10px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.table th, .table td {
    border: 1px solid #ddd;
    padding: 8px 12px;
    text-align: left;
}

.table th {
    background: #f5f5f5;
    font-weight: bold;
}

@media (max-width: 768px) {
    .test-section {
        grid-template-columns: 1fr;
    }
}
</style>

<?php obter_rodape_admin(); ?>
