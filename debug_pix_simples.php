<?php
// Debug PIX Simples - Sempre acessível
require_once 'includes/init.php';

// Forçar debug mode
$debug_mode = true;
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug PIX EFI - Diagnóstico Rápido</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .erro{color:red;} .aviso{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

echo "<h2>1. Verificações Básicas</h2>";

// 1. Verificar se funções existem
echo "<p><strong>Função efi_esta_ativo():</strong> " . (function_exists('efi_esta_ativo') ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não existe</span>') . "</p>";
echo "<p><strong>Função obter_configuracoes_efi():</strong> " . (function_exists('obter_configuracoes_efi') ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não existe</span>') . "</p>";
echo "<p><strong>Função efi_criar_pix_completo():</strong> " . (function_exists('efi_criar_pix_completo') ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não existe</span>') . "</p>";

echo "<h2>2. Configurações EFI</h2>";

// 2. Configurações
$efi_ativo = obter_configuracao('efi_ativo', '0');
echo "<p><strong>EFI Ativo:</strong> " . ($efi_ativo === '1' ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO (' . $efi_ativo . ')</span>') . "</p>";

$client_id = obter_configuracao('efi_client_id', '');
echo "<p><strong>Client ID:</strong> " . (!empty($client_id) ? '<span class="ok">✅ Configurado</span>' : '<span class="erro">❌ Vazio</span>') . "</p>";

$client_secret = obter_configuracao('efi_client_secret', '');
echo "<p><strong>Client Secret:</strong> " . (!empty($client_secret) ? '<span class="ok">✅ Configurado</span>' : '<span class="erro">❌ Vazio</span>') . "</p>";

$pix_key = obter_configuracao('efi_pix_key', '');
echo "<p><strong>Chave PIX:</strong> " . (!empty($pix_key) ? '<span class="ok">✅ ' . htmlspecialchars($pix_key) . '</span>' : '<span class="erro">❌ Vazia</span>') . "</p>";

$cert_path = obter_configuracao('efi_certificado_path', '');
echo "<p><strong>Caminho Certificado:</strong> " . (!empty($cert_path) ? htmlspecialchars($cert_path) : '<span class="erro">❌ Não configurado</span>') . "</p>";

if (!empty($cert_path)) {
    echo "<p><strong>Certificado Existe:</strong> " . (file_exists($cert_path) ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO</span>') . "</p>";
}

echo "<h2>3. Teste da Função efi_esta_ativo()</h2>";

if (function_exists('efi_esta_ativo')) {
    $efi_result = efi_esta_ativo();
    echo "<p><strong>Resultado:</strong> " . ($efi_result ? '<span class="ok">✅ TRUE (EFI ativo)</span>' : '<span class="erro">❌ FALSE (EFI inativo)</span>') . "</p>";
} else {
    echo "<p><span class='erro'>❌ Função não existe</span></p>";
}

echo "<h2>4. Configurações Completas EFI</h2>";

if (function_exists('obter_configuracoes_efi')) {
    $config_efi = obter_configuracoes_efi();
    echo "<pre>";
    foreach ($config_efi as $chave => $valor) {
        if (in_array($chave, ['efi_client_secret', 'efi_certificate_password'])) {
            $valor = !empty($valor) ? '***OCULTO***' : 'VAZIO';
        }
        echo htmlspecialchars($chave) . " = " . htmlspecialchars($valor) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p><span class='erro'>❌ Função obter_configuracoes_efi não existe</span></p>";
}

echo "<h2>5. Teste Rápido de Token EFI</h2>";

if (isset($_GET['test_token'])) {
    echo "<p>🔄 Testando obtenção de token...</p>";
    
    if (function_exists('efi_obter_token')) {
        try {
            $token = efi_obter_token();
            if ($token) {
                echo "<p><span class='ok'>✅ Token obtido com sucesso!</span> (Tamanho: " . strlen($token) . " caracteres)</p>";
                echo "<p><strong>Token:</strong> " . htmlspecialchars(substr($token, 0, 50)) . "...</p>";
            } else {
                echo "<p><span class='erro'>❌ Falha ao obter token</span></p>";
            }
        } catch (Exception $e) {
            echo "<p><span class='erro'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span></p>";
        }
    } else {
        echo "<p><span class='erro'>❌ Função efi_obter_token não existe</span></p>";
    }
} else {
    echo "<p><a href='?test_token=1' style='background:#007bff;color:white;padding:10px;text-decoration:none;border-radius:5px;'>🧪 Testar Token EFI</a></p>";
}

echo "<h2>6. Simulação da Página de Pagamento</h2>";

// Simular o que acontece na página de pagamento
$inscricao_id = 18; // ID de teste
$valor = 50.00; // Valor de teste

echo "<p><strong>Simulando geração de PIX para:</strong></p>";
echo "<ul>";
echo "<li>Inscrição ID: {$inscricao_id}</li>";
echo "<li>Valor: R$ " . number_format($valor, 2, ',', '.') . "</li>";
echo "</ul>";

// Simular verificação EFI
$efi_ativo = obter_configuracao('efi_ativo', '0') === '1';
$config_efi = function_exists('obter_configuracoes_efi') ? obter_configuracoes_efi() : [];
$certificado_existe = !empty($config_efi['efi_certificado_path']) && file_exists($config_efi['efi_certificado_path']);

echo "<p><strong>Verificação EFI:</strong></p>";
echo "<ul>";
echo "<li>EFI Ativo: " . ($efi_ativo ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO</span>') . "</li>";
echo "<li>Certificado Existe: " . ($certificado_existe ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO</span>') . "</li>";
echo "<li>Condição para usar EFI: " . (($efi_ativo && $certificado_existe) ? '<span class="ok">✅ VERDADEIRA</span>' : '<span class="erro">❌ FALSA</span>') . "</li>";
echo "</ul>";

if (!($efi_ativo && $certificado_existe)) {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;'>";
    echo "<strong>⚠️ PROBLEMA IDENTIFICADO:</strong><br>";
    echo "A EFI Bank não está sendo usada porque as condições não são atendidas.<br>";
    echo "Por isso o sistema está gerando PIX estático local (formato incorreto).<br><br>";
    echo "<strong>SOLUÇÕES:</strong><br>";
    if (!$efi_ativo) echo "• Ativar EFI Bank nas configurações<br>";
    if (!$certificado_existe) echo "• Configurar o certificado EFI corretamente<br>";
    echo "</div>";
}

echo "<h2>7. Links Úteis</h2>";
echo "<p>";
echo "<a href='admin/efi_config.php' style='background:#28a745;color:white;padding:8px 12px;text-decoration:none;border-radius:4px;margin:5px;'>⚙️ Configurar EFI</a> ";
echo "<a href='pagamento.php?inscricao=18&debug=1' style='background:#17a2b8;color:white;padding:8px 12px;text-decoration:none;border-radius:4px;margin:5px;'>🔍 Testar Pagamento</a> ";
echo "<a href='admin/testar_efi.php' style='background:#ffc107;color:black;padding:8px 12px;text-decoration:none;border-radius:4px;margin:5px;'>🧪 Testar EFI Admin</a>";
echo "</p>";

echo "<hr><p><small>Debug gerado em: " . date('d/m/Y H:i:s') . "</small></p>";
?>
