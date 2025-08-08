<?php
// Debug específico para PIX EFI
require_once 'includes/init.php';

// Verificar se debug está habilitado ou se é admin
$debug_habilitado = is_debug_enabled() || isset($_GET['debug']) || isset($_SESSION['admin_logado']);

if (!$debug_habilitado) {
    header('Location: index.php');
    exit;
}

$debug_results = [];

// 1. Verificar configurações EFI
$debug_results['config'] = [
    'efi_ativo' => obter_configuracao('efi_ativo', '0'),
    'efi_client_id' => !empty(obter_configuracao('efi_client_id', '')) ? 'Configurado' : 'Vazio',
    'efi_client_secret' => !empty(obter_configuracao('efi_client_secret', '')) ? 'Configurado' : 'Vazio',
    'config_efi' => obter_configuracoes_efi()
];

// 2. Verificar função efi_esta_ativo
$debug_results['efi_ativo_function'] = efi_esta_ativo();

// 3. Testar obtenção de token
$debug_results['token_test'] = null;
try {
    $token = efi_obter_token();
    $debug_results['token_test'] = $token ? 'Token obtido com sucesso' : 'Falha ao obter token';
} catch (Exception $e) {
    $debug_results['token_test'] = 'Erro: ' . $e->getMessage();
}

// 4. Testar criação de PIX completo
$debug_results['pix_test'] = null;
if (isset($_POST['test_pix'])) {
    try {
        $resultado_pix = efi_criar_pix_completo([
            'valor' => 10.50,
            'descricao' => 'Teste PIX Debug',
            'participante_id' => 1,
            'evento_nome' => 'Evento Teste',
            'nome_pagador' => 'Teste Debug',
            'cpf_pagador' => '12345678901',
            'expiracao' => 3600,
            'debug' => true,
            'txid_customizado' => 'TESTE' . date('YmdHis') . '001'
        ]);
        
        $debug_results['pix_test'] = $resultado_pix;
    } catch (Exception $e) {
        $debug_results['pix_test'] = ['erro' => $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug PIX EFI - Vinde</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .debug-section h3 { margin-top: 0; color: #333; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug PIX EFI Bank</h1>
        <p>Esta página ajuda a identificar problemas na integração com a EFI Bank.</p>
        
        <!-- Configurações -->
        <div class="debug-section <?= $debug_results['config']['efi_ativo'] === '1' ? 'success' : 'error' ?>">
            <h3>📋 Configurações EFI</h3>
            <p><strong>EFI Ativo:</strong> <?= $debug_results['config']['efi_ativo'] === '1' ? 'SIM' : 'NÃO' ?></p>
            <p><strong>Client ID:</strong> <?= $debug_results['config']['efi_client_id'] ?></p>
            <p><strong>Client Secret:</strong> <?= $debug_results['config']['efi_client_secret'] ?></p>
            <p><strong>Chave PIX:</strong> <?= !empty($debug_results['config']['config_efi']['efi_pix_key']) ? $debug_results['config']['config_efi']['efi_pix_key'] : 'Não configurada' ?></p>
            <p><strong>Certificado:</strong> <?= !empty($debug_results['config']['config_efi']['efi_certificado_path']) ? $debug_results['config']['config_efi']['efi_certificado_path'] : 'Não configurado' ?></p>
            <?php if (!empty($debug_results['config']['config_efi']['efi_certificado_path'])): ?>
                <p><strong>Certificado Existe:</strong> <?= file_exists($debug_results['config']['config_efi']['efi_certificado_path']) ? 'SIM' : 'NÃO' ?></p>
            <?php endif; ?>
            <p><strong>Sandbox:</strong> <?= $debug_results['config']['config_efi']['efi_sandbox'] === '1' ? 'SIM' : 'NÃO' ?></p>
        </div>
        
        <!-- Função efi_esta_ativo -->
        <div class="debug-section <?= $debug_results['efi_ativo_function'] ? 'success' : 'error' ?>">
            <h3>⚙️ Função efi_esta_ativo()</h3>
            <p><strong>Resultado:</strong> <?= $debug_results['efi_ativo_function'] ? 'TRUE (EFI ativo)' : 'FALSE (EFI inativo)' ?></p>
        </div>
        
        <!-- Teste de Token -->
        <div class="debug-section <?= $debug_results['token_test'] && strpos($debug_results['token_test'], 'sucesso') !== false ? 'success' : 'error' ?>">
            <h3>🔑 Teste de Token EFI</h3>
            <p><strong>Resultado:</strong> <?= htmlspecialchars($debug_results['token_test'] ?? 'Não testado') ?></p>
        </div>
        
        <!-- Teste PIX Completo -->
        <div class="debug-section">
            <h3>💰 Teste PIX Completo</h3>
            <form method="POST">
                <button type="submit" name="test_pix" class="btn">🧪 Testar Criação de PIX</button>
            </form>
            
            <?php if ($debug_results['pix_test']): ?>
                <div style="margin-top: 15px;">
                    <h4>Resultado do Teste:</h4>
                    <pre><?= htmlspecialchars(print_r($debug_results['pix_test'], true)) ?></pre>
                    
                    <?php if (isset($debug_results['pix_test']['sucesso']) && $debug_results['pix_test']['sucesso']): ?>
                        <div class="success" style="margin-top: 10px; padding: 10px;">
                            <strong>✅ PIX criado com sucesso!</strong><br>
                            <strong>TXID:</strong> <?= htmlspecialchars($debug_results['pix_test']['pix_txid'] ?? 'N/A') ?><br>
                            <strong>Payload:</strong> <?= !empty($debug_results['pix_test']['pix_qrcode_data']) ? 'Gerado (' . strlen($debug_results['pix_test']['pix_qrcode_data']) . ' caracteres)' : 'Não gerado' ?><br>
                            <?php if (!empty($debug_results['pix_test']['pix_qrcode_data'])): ?>
                                <strong>Formato:</strong> <?= substr($debug_results['pix_test']['pix_qrcode_data'], 0, 50) ?>...
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="error" style="margin-top: 10px; padding: 10px;">
                            <strong>❌ Falha ao criar PIX</strong><br>
                            <?php if (isset($debug_results['pix_test']['erro'])): ?>
                                <strong>Erro:</strong> <?= htmlspecialchars($debug_results['pix_test']['erro']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Logs Recentes -->
        <div class="debug-section info">
            <h3>📝 Logs Recentes EFI</h3>
            <?php
            try {
                $logs = buscar_todos("SELECT * FROM efi_logs ORDER BY criado_em DESC LIMIT 10");
                if ($logs) {
                    echo "<table style='width: 100%; border-collapse: collapse;'>";
                    echo "<tr style='background: #f8f9fa;'><th style='padding: 8px; border: 1px solid #ddd;'>Data</th><th style='padding: 8px; border: 1px solid #ddd;'>Tipo</th><th style='padding: 8px; border: 1px solid #ddd;'>Mensagem</th></tr>";
                    foreach ($logs as $log) {
                        echo "<tr>";
                        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . date('d/m/Y H:i:s', strtotime($log['criado_em'])) . "</td>";
                        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($log['tipo']) . "</td>";
                        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars(substr($log['mensagem'], 0, 100)) . "...</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>Nenhum log encontrado.</p>";
                }
            } catch (Exception $e) {
                echo "<p>Erro ao buscar logs: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="admin/" class="btn">← Voltar ao Admin</a>
            <a href="pagamento.php?inscricao=18&debug=1" class="btn" style="background: #28a745;">🔍 Testar Página de Pagamento</a>
        </div>
    </div>
</body>
</html>
