<?php
/**
 * Debug específico para payload PIX copia e cola
 * Verifica se o payload está válido para pagamento
 */

require_once '../includes/init.php';
requer_login('admin');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Debug Payload PIX</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; border: 1px solid #dee2e6; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .step h3 { margin-top: 0; color: #007bff; }
        .payload-box { background: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin: 10px 0; font-family: monospace; word-break: break-all; }
        .comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .comparison > div { padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Payload PIX Copia e Cola</h1>
        
        <?php
        // Criar cobrança de teste
        $dados_teste = [
            'valor' => 0.01,
            'descricao' => 'Teste payload PIX',
            'participante_id' => 999999,
            'evento_nome' => 'Debug Test',
            'nome_pagador' => 'Debug Test',
            'cpf_pagador' => '',
            'expiracao' => 300
        ];
        
        echo "<div class='step'>";
        echo "<h3>Passo 1: Criando Cobrança PIX via EFI Bank</h3>";
        
        $resultado_efi = efi_criar_pix_completo($dados_teste);
        
        if ($resultado_efi && isset($resultado_efi['sucesso'])) {
            echo "<div class='success'>✅ Cobrança criada com sucesso!</div>";
            
            echo "<h4>Dados retornados pela EFI Bank:</h4>";
            echo "<pre>" . json_encode($resultado_efi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            
            // Verificar se o QR Code data é um payload PIX válido
            $qr_data = $resultado_efi['pix_qrcode_data'] ?? '';
            
            echo "<h4>Análise do QR Code Data:</h4>";
            echo "<div class='info'>";
            echo "<strong>Tamanho:</strong> " . strlen($qr_data) . " caracteres<br>";
            echo "<strong>Começa com '00020126':</strong> " . (strpos($qr_data, '00020126') === 0 ? 'Sim ✅' : 'Não ❌') . "<br>";
            echo "<strong>Contém 'BR.GOV.BCB.PIX':</strong> " . (strpos($qr_data, 'BR.GOV.BCB.PIX') !== false ? 'Sim ✅' : 'Não ❌') . "<br>";
            echo "<strong>Termina com CRC (4 dígitos):</strong> " . (preg_match('/[0-9A-F]{4}$/', $qr_data) ? 'Sim ✅' : 'Não ❌') . "<br>";
            echo "</div>";
            
            echo "<h4>Payload PIX (para cópia):</h4>";
            echo "<div class='payload-box'>" . htmlspecialchars($qr_data) . "</div>";
            
            // Verificar se a URL do QR Code está funcionando
            echo "<h4>Verificando URL do QR Code:</h4>";
            $qr_url = $resultado_efi['pix_qrcode_url'] ?? '';
            if ($qr_url) {
                echo "<div class='info'>URL: <a href='{$qr_url}' target='_blank'>{$qr_url}</a></div>";
                
                // Verificar se a URL responde
                $headers = @get_headers($qr_url);
                if ($headers && strpos($headers[0], '200') !== false) {
                    echo "<div class='success'>✅ URL do QR Code está acessível</div>";
                } else {
                    echo "<div class='error'>❌ URL do QR Code não está acessível</div>";
                }
            } else {
                echo "<div class='error'>❌ URL do QR Code não foi gerada</div>";
            }
            
        } else {
            echo "<div class='error'>❌ Falha ao criar cobrança</div>";
            if (isset($resultado_efi['erro'])) {
                echo "<div class='error'>Erro: " . $resultado_efi['erro'] . "</div>";
            }
        }
        
        echo "</div>";
        
        // Comparar com PIX simples (se disponível)
        echo "<div class='step'>";
        echo "<h3>Passo 2: Comparação com PIX Simples</h3>";
        
        // Obter configurações PIX simples
        $config_pix = obter_configuracoes_pix();
        
        if (!empty($config_pix['pix_chave']) && !empty($config_pix['pix_nome'])) {
            echo "<div class='info'>Gerando payload PIX simples para comparação...</div>";
            
            // Incluir funções PIX simples se existirem
            if (function_exists('gerar_payload_pix')) {
                $payload_simples = gerar_payload_pix(
                    $config_pix['pix_chave'],
                    $dados_teste['valor'],
                    $config_pix['pix_nome'] ?? 'VINDE',
                    $config_pix['pix_cidade'] ?? 'SAO PAULO',
                    $dados_teste['descricao'],
                    'TESTE' . time()
                );
                
                echo "<div class='comparison'>";
                echo "<div>";
                echo "<h5>Payload EFI Bank:</h5>";
                echo "<div class='payload-box'>" . htmlspecialchars($qr_data) . "</div>";
                echo "<p><strong>Tamanho:</strong> " . strlen($qr_data) . " chars</p>";
                echo "</div>";
                
                echo "<div>";
                echo "<h5>Payload PIX Simples:</h5>";
                echo "<div class='payload-box'>" . htmlspecialchars($payload_simples) . "</div>";
                echo "<p><strong>Tamanho:</strong> " . strlen($payload_simples) . " chars</p>";
                echo "</div>";
                echo "</div>";
                
                // Testar qual funciona melhor
                echo "<h4>Validação dos Payloads:</h4>";
                echo "<div class='info'>";
                echo "<strong>EFI Bank válido:</strong> " . (strlen($qr_data) > 50 && strpos($qr_data, '00020126') === 0 ? 'Provavelmente ✅' : 'Duvidoso ❌') . "<br>";
                echo "<strong>PIX Simples válido:</strong> " . (strlen($payload_simples) > 50 && strpos($payload_simples, '00020126') === 0 ? 'Provavelmente ✅' : 'Duvidoso ❌') . "<br>";
                echo "</div>";
                
            } else {
                echo "<div class='warning'>Função gerar_payload_pix não encontrada</div>";
            }
        } else {
            echo "<div class='warning'>Configurações PIX simples não encontradas</div>";
        }
        
        echo "</div>";
        
        // Testar um pagamento real recente
        echo "<div class='step'>";
        echo "<h3>Passo 3: Verificar Pagamento Recente Real</h3>";
        
        try {
            $pagamento_recente = buscar_um("
                SELECT p.*, pag.pix_qrcode_data, pag.pix_qrcode_url, pag.pix_txid 
                FROM participantes p 
                JOIN pagamentos pag ON p.id = pag.participante_id 
                WHERE pag.pix_qrcode_data IS NOT NULL 
                ORDER BY pag.criado_em DESC 
                LIMIT 1
            ");
            
            if ($pagamento_recente) {
                echo "<div class='info'>Encontrado pagamento recente:</div>";
                echo "<p><strong>Participante:</strong> " . htmlspecialchars($pagamento_recente['nome']) . "</p>";
                echo "<p><strong>TXID:</strong> " . htmlspecialchars($pagamento_recente['pix_txid']) . "</p>";
                
                echo "<h4>Payload do pagamento real:</h4>";
                echo "<div class='payload-box'>" . htmlspecialchars($pagamento_recente['pix_qrcode_data']) . "</div>";
                
                // Análise do payload real
                $qr_real = $pagamento_recente['pix_qrcode_data'];
                echo "<div class='info'>";
                echo "<strong>Tamanho:</strong> " . strlen($qr_real) . " caracteres<br>";
                echo "<strong>Válido PIX:</strong> " . (strlen($qr_real) > 50 && strpos($qr_real, '00020126') === 0 ? 'Provavelmente ✅' : 'Duvidoso ❌') . "<br>";
                echo "</div>";
                
            } else {
                echo "<div class='warning'>Nenhum pagamento recente encontrado</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>Erro ao buscar pagamento: " . $e->getMessage() . "</div>";
        }
        
        echo "</div>";
        ?>
        
        <div class="step">
            <h3>Conclusões e Recomendações</h3>
            <p><strong>Se o payload EFI Bank não estiver funcionando:</strong></p>
            <ul>
                <li>Verificar se a chave PIX está corretamente cadastrada na EFI Bank</li>
                <li>Confirmar se o ambiente (sandbox/produção) está correto</li>
                <li>Testar o payload em um app bancário real</li>
                <li>Considerar usar PIX simples como fallback</li>
            </ul>
            
            <p><strong>Teste manual:</strong></p>
            <ol>
                <li>Copie o payload PIX gerado acima</li>
                <li>Abra o app do seu banco</li>
                <li>Vá em "PIX" > "Pagar" > "Pix Copia e Cola"</li>
                <li>Cole o código e verifique se aparece os dados corretos</li>
            </ol>
        </div>
    </div>
</body>
</html> 