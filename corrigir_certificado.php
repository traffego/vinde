<?php
// Script para corrigir caminho do certificado EFI
require_once 'includes/init.php';

echo "<h1>🔧 Corrigir Caminho do Certificado EFI</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .erro{color:red;}</style>";

// Verificar certificado atual
$caminho_atual = obter_configuracao('efi_certificado_path', '');
$caminho_correto = __DIR__ . '/certificados/certificado_prod.p12';
$caminho_relativo = './certificados/certificado_prod.p12';

echo "<h2>📋 Diagnóstico</h2>";
echo "<p><strong>Caminho atual:</strong> " . htmlspecialchars($caminho_atual) . "</p>";
echo "<p><strong>Certificado existe no atual:</strong> " . (file_exists($caminho_atual) ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO</span>') . "</p>";
echo "<p><strong>Caminho correto:</strong> " . htmlspecialchars($caminho_correto) . "</p>";
echo "<p><strong>Certificado existe no correto:</strong> " . (file_exists($caminho_correto) ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO</span>') . "</p>";

// Corrigir se solicitado
if (isset($_POST['corrigir'])) {
    echo "<h2>🔄 Aplicando Correção</h2>";
    
    // Atualizar configuração
    $sucesso = salvar_configuracao('efi_certificado_path', $caminho_correto, 'Caminho do certificado EFI Bank (corrigido automaticamente)');
    
    if ($sucesso) {
        echo "<p><span class='ok'>✅ Caminho do certificado atualizado com sucesso!</span></p>";
        echo "<p><strong>Novo caminho:</strong> " . htmlspecialchars($caminho_correto) . "</p>";
        
        // Testar se agora funciona
        echo "<h3>🧪 Testando Token EFI</h3>";
        try {
            $token = efi_obter_token();
            if ($token) {
                echo "<p><span class='ok'>✅ Token obtido com sucesso!</span> (Tamanho: " . strlen($token) . " caracteres)</p>";
                echo "<p><strong>🎉 PROBLEMA RESOLVIDO!</strong> A EFI Bank agora deve funcionar corretamente.</p>";
            } else {
                echo "<p><span class='erro'>❌ Ainda não conseguiu obter token</span></p>";
            }
        } catch (Exception $e) {
            echo "<p><span class='erro'>❌ Erro ao testar: " . htmlspecialchars($e->getMessage()) . "</span></p>";
        }
        
    } else {
        echo "<p><span class='erro'>❌ Erro ao atualizar configuração</span></p>";
    }
    
    echo "<hr>";
    echo "<p><a href='debug_pix_efi.php?debug=1' style='background:#007bff;color:white;padding:10px;text-decoration:none;border-radius:5px;'>🔍 Testar Debug EFI</a></p>";
    echo "<p><a href='pagamento.php?inscricao=18&debug=1' style='background:#28a745;color:white;padding:10px;text-decoration:none;border-radius:5px;'>💰 Testar Página de Pagamento</a></p>";
    
} else {
    echo "<h2>🛠️ Correção Necessária</h2>";
    echo "<p>O certificado existe mas está configurado com o caminho errado.</p>";
    echo "<p><strong>Caminho configurado:</strong> <code>" . htmlspecialchars($caminho_atual) . "</code></p>";
    echo "<p><strong>Caminho real:</strong> <code>" . htmlspecialchars($caminho_correto) . "</code></p>";
    
    echo "<form method='POST'>";
    echo "<button type='submit' name='corrigir' style='background:#28a745;color:white;padding:15px 30px;border:none;border-radius:5px;font-size:16px;cursor:pointer;'>🔧 Corrigir Caminho do Certificado</button>";
    echo "</form>";
}

echo "<hr>";
echo "<p><small>Script gerado em: " . date('d/m/Y H:i:s') . "</small></p>";
?>
