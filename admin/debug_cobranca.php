<?php
/**
 * Debug específico para criação de cobrança PIX
 * Testa passo a passo a criação de uma cobrança
 */

require_once '../includes/init.php';
requer_login('admin');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Debug Criação de Cobrança PIX</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; border: 1px solid #dee2e6; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .step h3 { margin-top: 0; color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Criação de Cobrança PIX</h1>
        
        <?php
        echo "<div class='step'>";
        echo "<h3>Passo 1: Verificação de Configurações</h3>";
        
        $verificacao = efi_verificar_configuracoes();
        if ($verificacao['configurado']) {
            echo "<div class='success'>✅ Configurações OK</div>";
            echo "<div class='info'>Ambiente: " . $verificacao['ambiente'] . "</div>";
        } else {
            echo "<div class='error'>❌ Problemas nas configurações:</div>";
            echo "<ul>";
            foreach ($verificacao['problemas'] as $problema) {
                echo "<li>$problema</li>";
            }
            echo "</ul>";
            echo "</div></body></html>";
            exit;
        }
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h3>Passo 2: Teste de Autenticação</h3>";
        
        $token = efi_obter_token();
        if ($token) {
            echo "<div class='success'>✅ Token obtido: " . substr($token, 0, 20) . "...</div>";
        } else {
            echo "<div class='error'>❌ Falha ao obter token</div>";
            echo "</div></body></html>";
            exit;
        }
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h3>Passo 3: Tentativa de Criação de Cobrança</h3>";
        
        // Dados para teste
        // Usar função para gerar TXID válido
        $txid = efi_gerar_txid_valido('TESTE');
        $dados_teste = [
            'valor' => 0.01,
            'descricao' => 'Teste debug cobranca',
            'participante_id' => 999999,
            'evento_nome' => 'Debug Test',
            'nome_pagador' => 'Debug Test',
            'cpf_pagador' => '',
            'expiracao' => 300
        ];
        
        echo "<div class='info'>";
        echo "<strong>Dados do teste:</strong><br>";
        echo "TXID: $txid<br>";
        echo "Valor: R$ " . number_format($dados_teste['valor'], 2, ',', '.') . "<br>";
        echo "Descrição: {$dados_teste['descricao']}<br>";
        echo "</div>";
        
        // Testar criação da cobrança diretamente
        echo "<h4>Chamando efi_criar_cobranca_pix diretamente:</h4>";
        
        $resultado_cobranca = efi_criar_cobranca_pix(
            $txid,
            $dados_teste['valor'],
            $dados_teste['descricao'],
            $dados_teste['nome_pagador'],
            $dados_teste['cpf_pagador'],
            $dados_teste['expiracao']
        );
        
        if ($resultado_cobranca) {
            echo "<div class='success'>✅ Cobrança criada com sucesso!</div>";
            echo "<pre>" . json_encode($resultado_cobranca, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            
            // Tentar gerar QR Code
            if (isset($resultado_cobranca['loc']['id'])) {
                echo "<h4>Tentando gerar QR Code:</h4>";
                $qrcode = efi_gerar_qrcode($resultado_cobranca['loc']['id']);
                
                if ($qrcode) {
                    echo "<div class='success'>✅ QR Code gerado!</div>";
                    echo "<pre>" . json_encode($qrcode, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                } else {
                    echo "<div class='error'>❌ Falha ao gerar QR Code</div>";
                }
            }
            
        } else {
            echo "<div class='error'>❌ Falha ao criar cobrança</div>";
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h3>Passo 4: Teste da Função Completa</h3>";
        
        $resultado_completo = efi_criar_pix_completo($dados_teste);
        
        if ($resultado_completo && isset($resultado_completo['sucesso'])) {
            echo "<div class='success'>✅ PIX completo criado com sucesso!</div>";
            echo "<pre>" . json_encode($resultado_completo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='error'>❌ Falha na função completa</div>";
            if (isset($resultado_completo['erro'])) {
                echo "<div class='error'>Erro: " . $resultado_completo['erro'] . "</div>";
            }
            if ($resultado_completo) {
                echo "<pre>" . json_encode($resultado_completo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            }
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h3>Passo 5: Logs Recentes</h3>";
        
        try {
            $logs = buscar_todos("
                SELECT tipo, mensagem, txid, criado_em 
                FROM efi_logs 
                WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY criado_em DESC 
                LIMIT 20
            ");
            
            if ($logs) {
                echo "<div class='info'>Logs da última hora:</div>";
                echo "<pre>";
                foreach ($logs as $log) {
                    echo "[{$log['criado_em']}] {$log['tipo']}: {$log['mensagem']}\n";
                    if ($log['txid']) echo "  TXID: {$log['txid']}\n";
                }
                echo "</pre>";
            } else {
                echo "<div class='warning'>Nenhum log encontrado na última hora</div>";
            }
        } catch (Exception $e) {
            echo "<div class='warning'>Erro ao buscar logs: " . $e->getMessage() . "</div>";
        }
        
        echo "</div>";
        ?>
        
        <div class="step">
            <h3>Próximos Passos</h3>
            <p>Se ainda houver erro, verifique:</p>
            <ul>
                <li>Logs do servidor PHP (error_log)</li>
                <li>Conectividade com a API EFI</li>
                <li>Validade das credenciais</li>
                <li>Configuração da chave PIX na EFI Bank</li>
            </ul>
        </div>
    </div>
</body>
</html> 