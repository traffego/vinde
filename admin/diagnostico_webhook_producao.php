<?php
/**
 * Diagnóstico específico para webhook em produção
 * Verifica conectividade, SSL, DNS e configurações
 */

require_once '../includes/init.php';
requer_login('admin');

$titulo_pagina = 'Diagnóstico Webhook Produção';

function testar_conectividade_webhook($url) {
    $resultados = [];
    
    // Teste 1: Ping básico ao domínio
    $domain = parse_url($url, PHP_URL_HOST);
    $resultados['domain'] = $domain;
    
    // Teste 2: Resolução DNS
    $ip = gethostbyname($domain);
    $resultados['dns_resolve'] = ($ip !== $domain);
    $resultados['ip_address'] = $ip;
    
    // Teste 3: Conectividade básica
    $curl_test = curl_init();
    curl_setopt_array($curl_test, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true, // HEAD request apenas
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Diagnostico-Webhook/1.0'
    ]);
    
    $response = curl_exec($curl_test);
    $http_code = curl_getinfo($curl_test, CURLINFO_HTTP_CODE);
    $ssl_verify_result = curl_getinfo($curl_test, CURLINFO_SSL_VERIFYRESULT);
    $connect_time = curl_getinfo($curl_test, CURLINFO_CONNECT_TIME);
    $total_time = curl_getinfo($curl_test, CURLINFO_TOTAL_TIME);
    $error = curl_error($curl_test);
    $info = curl_getinfo($curl_test);
    curl_close($curl_test);
    
    $resultados['conectividade'] = [
        'sucesso' => empty($error) && $http_code > 0,
        'http_code' => $http_code,
        'ssl_verify_result' => $ssl_verify_result,
        'connect_time' => round($connect_time * 1000, 2) . 'ms',
        'total_time' => round($total_time * 1000, 2) . 'ms',
        'error' => $error,
        'url_final' => $info['url'] ?? $url
    ];
    
    // Teste 4: Teste POST real
    $payload_teste = [
        'pix' => [
            [
                'endToEndId' => 'E' . str_pad(time(), 32, '0', STR_PAD_LEFT),
                'txid' => 'DIAG_' . time(),
                'valor' => '0.01',
                'horario' => date('c'),
                'infoPagador' => 'Teste diagnóstico produção'
            ]
        ]
    ];
    
    $curl_post = curl_init();
    curl_setopt_array($curl_post, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload_teste),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: Diagnostico-Webhook-POST/1.0',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_VERBOSE => false
    ]);
    
    $post_response = curl_exec($curl_post);
    $post_http_code = curl_getinfo($curl_post, CURLINFO_HTTP_CODE);
    $post_error = curl_error($curl_post);
    $post_info = curl_getinfo($curl_post);
    curl_close($curl_post);
    
    $resultados['teste_post'] = [
        'payload_enviado' => $payload_teste,
        'sucesso' => empty($post_error) && $post_http_code > 0,
        'http_code' => $post_http_code,
        'response' => $post_response,
        'error' => $post_error,
        'content_type' => $post_info['content_type'] ?? 'N/A',
        'total_time' => round($post_info['total_time'] * 1000, 2) . 'ms'
    ];
    
    return $resultados;
}

$resultado_diagnostico = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'diagnosticar') {
    $webhook_url = SITE_URL . '/webhook_efi.php';
    $resultado_diagnostico = testar_conectividade_webhook($webhook_url);
}

obter_cabecalho_admin($titulo_pagina, 'configuracoes');
?>

<div class="admin-content">
    <div class="admin-header">
        <h1><?= $titulo_pagina ?></h1>
        <p>Diagnóstico completo da conectividade do webhook em produção</p>
    </div>

    <div class="info-section">
        <h3>🌐 Informações do Ambiente Atual</h3>
        <ul>
            <li><strong>Domínio:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'N/A' ?></li>
            <li><strong>URL Configurada:</strong> <code><?= SITE_URL ?></code></li>
            <li><strong>Webhook URL:</strong> <code><?= SITE_URL ?>/webhook_efi.php</code></li>
            <li><strong>Protocolo:</strong> <?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'HTTPS ✅' : 'HTTP ⚠️' ?></li>
            <li><strong>IP do Servidor:</strong> <?= $_SERVER['SERVER_ADDR'] ?? 'N/A' ?></li>
            <li><strong>Ambiente Local Detectado:</strong> <?= (function_exists('is_ambiente_local') && is_ambiente_local()) ? 'Sim ⚠️' : 'Não ✅' ?></li>
        </ul>
    </div>

    <form method="POST" style="text-align: center; margin: 30px 0;">
        <input type="hidden" name="acao" value="diagnosticar">
        <button type="submit" class="btn btn-primary btn-large">🔍 Executar Diagnóstico Completo</button>
    </form>

    <?php if ($resultado_diagnostico): ?>
    <div class="diagnostico-resultado">
        <h2>📊 Resultado do Diagnóstico</h2>
        
        <!-- DNS e Conectividade Básica -->
        <div class="diagnostico-secao">
            <h3>🌐 DNS e Conectividade</h3>
            <div class="status-grid">
                <div class="status-item <?= $resultado_diagnostico['dns_resolve'] ? 'success' : 'error' ?>">
                    <strong>Resolução DNS</strong><br>
                    <?= $resultado_diagnostico['dns_resolve'] ? '✅ OK' : '❌ Falha' ?><br>
                    <small>IP: <?= $resultado_diagnostico['ip_address'] ?></small>
                </div>
                
                <div class="status-item <?= $resultado_diagnostico['conectividade']['sucesso'] ? 'success' : 'error' ?>">
                    <strong>Conectividade</strong><br>
                    <?= $resultado_diagnostico['conectividade']['sucesso'] ? '✅ OK' : '❌ Falha' ?><br>
                    <small>Tempo: <?= $resultado_diagnostico['conectividade']['connect_time'] ?></small>
                </div>
                
                <div class="status-item <?= ($resultado_diagnostico['conectividade']['http_code'] >= 200 && $resultado_diagnostico['conectividade']['http_code'] < 400) ? 'success' : 'warning' ?>">
                    <strong>HTTP Status</strong><br>
                    <?= $resultado_diagnostico['conectividade']['http_code'] ?: 'N/A' ?><br>
                    <small><?= $resultado_diagnostico['conectividade']['error'] ?: 'OK' ?></small>
                </div>
                
                <div class="status-item <?= ($resultado_diagnostico['conectividade']['ssl_verify_result'] === 0) ? 'success' : 'warning' ?>">
                    <strong>SSL/TLS</strong><br>
                    <?= ($resultado_diagnostico['conectividade']['ssl_verify_result'] === 0) ? '✅ Válido' : '⚠️ Problema' ?><br>
                    <small>Código: <?= $resultado_diagnostico['conectividade']['ssl_verify_result'] ?></small>
                </div>
            </div>
        </div>

        <!-- Teste POST -->
        <div class="diagnostico-secao">
            <h3>📤 Teste de Requisição POST</h3>
            <div class="post-resultado <?= $resultado_diagnostico['teste_post']['sucesso'] ? 'success' : 'error' ?>">
                <h4>Status: <?= $resultado_diagnostico['teste_post']['sucesso'] ? '✅ Sucesso' : '❌ Falha' ?></h4>
                <p><strong>HTTP Code:</strong> <?= $resultado_diagnostico['teste_post']['http_code'] ?></p>
                <p><strong>Tempo de Resposta:</strong> <?= $resultado_diagnostico['teste_post']['total_time'] ?></p>
                <p><strong>Content-Type:</strong> <?= $resultado_diagnostico['teste_post']['content_type'] ?></p>
                
                <?php if ($resultado_diagnostico['teste_post']['error']): ?>
                <div class="error-details">
                    <strong>Erro cURL:</strong> <?= htmlspecialchars($resultado_diagnostico['teste_post']['error']) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($resultado_diagnostico['teste_post']['response']): ?>
                <div class="response-details">
                    <h5>Resposta do Webhook:</h5>
                    <pre><?= htmlspecialchars($resultado_diagnostico['teste_post']['response']) ?></pre>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payload Enviado -->
        <div class="diagnostico-secao">
            <h3>📋 Payload de Teste Enviado</h3>
            <pre class="payload-display"><?= json_encode($resultado_diagnostico['teste_post']['payload_enviado'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
        </div>

        <!-- Recomendações -->
        <div class="diagnostico-secao">
            <h3>💡 Recomendações</h3>
            <div class="recomendacoes">
                <?php if (!$resultado_diagnostico['dns_resolve']): ?>
                <div class="recomendacao error">❌ <strong>DNS não resolve:</strong> Verifique se o domínio está configurado corretamente.</div>
                <?php endif; ?>
                
                <?php if (!$resultado_diagnostico['conectividade']['sucesso']): ?>
                <div class="recomendacao error">❌ <strong>Falha de conectividade:</strong> Servidor pode estar offline ou bloqueado.</div>
                <?php endif; ?>
                
                <?php if ($resultado_diagnostico['conectividade']['ssl_verify_result'] !== 0): ?>
                <div class="recomendacao warning">⚠️ <strong>Problema SSL:</strong> Certificado pode estar expirado ou inválido.</div>
                <?php endif; ?>
                
                <?php if ($resultado_diagnostico['teste_post']['http_code'] >= 400): ?>
                <div class="recomendacao error">❌ <strong>Erro HTTP:</strong> Webhook retornou erro <?= $resultado_diagnostico['teste_post']['http_code'] ?>.</div>
                <?php endif; ?>
                
                <?php if ($resultado_diagnostico['conectividade']['sucesso'] && $resultado_diagnostico['teste_post']['sucesso']): ?>
                <div class="recomendacao success">✅ <strong>Webhook funcionando:</strong> Conectividade OK! Pode configurar na EFI.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="info-adicional">
        <h3>📚 Informações Adicionais</h3>
        <div class="info-grid">
            <div class="info-card">
                <h4>🔧 Se há problemas de conectividade:</h4>
                <ul>
                    <li>Verificar se o domínio está ativo</li>
                    <li>Verificar certificado SSL</li>
                    <li>Verificar firewall do servidor</li>
                    <li>Verificar se o Apache/Nginx está rodando</li>
                </ul>
            </div>
            
            <div class="info-card">
                <h4>🔒 Se há problemas de SSL:</h4>
                <ul>
                    <li>Renovar certificado SSL</li>
                    <li>Verificar configuração HTTPS</li>
                    <li>Verificar cadeia de certificados</li>
                    <li>Testar com SSLLabs.com</li>
                </ul>
            </div>
            
            <div class="info-card">
                <h4>📝 Se webhook retorna erro:</h4>
                <ul>
                    <li>Verificar logs do servidor</li>
                    <li>Verificar configurações EFI</li>
                    <li>Verificar banco de dados</li>
                    <li>Ativar debug no webhook</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.diagnostico-resultado {
    margin: 30px 0;
}

.diagnostico-secao {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.status-item {
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
}

.status-item.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-item.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-item.warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.post-resultado {
    padding: 20px;
    border-radius: 8px;
    margin-top: 15px;
}

.post-resultado.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.post-resultado.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.payload-display {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 15px;
    overflow-x: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.response-details {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.recomendacoes {
    margin-top: 15px;
}

.recomendacao {
    padding: 10px 15px;
    margin: 10px 0;
    border-radius: 5px;
    border-left: 4px solid;
}

.recomendacao.success {
    background: #d4edda;
    border-left-color: #28a745;
    color: #155724;
}

.recomendacao.error {
    background: #f8d7da;
    border-left-color: #dc3545;
    color: #721c24;
}

.recomendacao.warning {
    background: #fff3cd;
    border-left-color: #ffc107;
    color: #856404;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.info-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}

.info-card h4 {
    color: #495057;
    margin-bottom: 15px;
}

.info-card ul {
    margin: 0;
    padding-left: 20px;
}

.info-card li {
    margin-bottom: 8px;
    color: #6c757d;
}

@media (max-width: 768px) {
    .status-grid, .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php obter_rodape_admin(); ?>
