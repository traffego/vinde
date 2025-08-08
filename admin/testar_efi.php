<?php
/**
 * Script de teste da integração EFI Bank
 * Execute este arquivo para verificar se a EFI Bank está funcionando
 * Acesse: https://vinde.traffego.agency/admin/testar_efi.php
 */

require_once '../includes/init.php';

// Verificar se é admin (opcional - remova se quiser executar sem login)
requer_login('admin');

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Teste EFI Bank</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .test-item { margin: 20px 0; padding: 15px; border-left: 4px solid #ccc; }
        .test-success { border-left-color: #28a745; }
        .test-error { border-left-color: #dc3545; }
        .test-warning { border-left-color: #ffc107; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Teste da Integração EFI Bank</h1>";

$testes_realizados = 0;
$testes_passou = 0;
$testes_falhou = 0;

// Teste 1: Verificar se EFI está ativo
echo "<div class='test-item'>";
echo "<h3>📋 Teste 1: Verificação de Status</h3>";

$testes_realizados++;
if (efi_esta_ativo()) {
    echo "<div class='success'>✅ EFI Bank está ATIVO no sistema</div>";
    $testes_passou++;
} else {
    echo "<div class='error'>❌ EFI Bank está INATIVO - Configure em Admin > EFI Bank</div>";
    $testes_falhou++;
}
echo "</div>";

// Teste 2: Verificar configurações
echo "<div class='test-item'>";
echo "<h3>⚙️ Teste 2: Configurações</h3>";

$config_efi = obter_configuracoes_efi();
$configs_ok = true;

echo "<h4>Configurações encontradas:</h4><pre>";
foreach ($config_efi as $chave => $valor) {
    if (in_array($chave, ['efi_client_secret', 'efi_certificate_password'])) {
        echo "$chave: " . (empty($valor) ? 'NÃO CONFIGURADO' : str_repeat('*', 20)) . "\n";
    } else {
        echo "$chave: " . ($valor ?: 'NÃO CONFIGURADO') . "\n";
    }
}
echo "</pre>";

$testes_realizados++;
$campos_obrigatorios = ['efi_client_id', 'efi_client_secret', 'efi_certificado_path', 'efi_pix_key'];
foreach ($campos_obrigatorios as $campo) {
    if (empty($config_efi[$campo])) {
        echo "<div class='error'>❌ Campo obrigatório não configurado: $campo</div>";
        $configs_ok = false;
    }
}

if ($configs_ok) {
    echo "<div class='success'>✅ Todas as configurações obrigatórias estão preenchidas</div>";
    $testes_passou++;
} else {
    echo "<div class='error'>❌ Configurações incompletas</div>";
    $testes_falhou++;
}
echo "</div>";

// Teste 3: Verificar certificado
echo "<div class='test-item'>";
echo "<h3>🔐 Teste 3: Certificado P12</h3>";

$testes_realizados++;
$cert_path = $config_efi['efi_certificado_path'] ?? '';

if (empty($cert_path)) {
    echo "<div class='error'>❌ Caminho do certificado não configurado</div>";
    $testes_falhou++;
} elseif (!file_exists($cert_path)) {
    echo "<div class='error'>❌ Arquivo de certificado não encontrado: $cert_path</div>";
    $testes_falhou++;
} else {
    $file_size = filesize($cert_path);
    echo "<div class='success'>✅ Certificado encontrado: $cert_path (tamanho: " . number_format($file_size) . " bytes)</div>";
    $testes_passou++;
}
echo "</div>";

// Teste 4: Teste de autenticação
echo "<div class='test-item'>";
echo "<h3>🔑 Teste 4: Autenticação EFI</h3>";

$testes_realizados++;
if ($configs_ok && !empty($cert_path) && file_exists($cert_path)) {
    echo "<p>Testando autenticação na API EFI Bank...</p>";
    
    $token = efi_obter_token();
    
    if ($token) {
        echo "<div class='success'>✅ Autenticação realizada com sucesso!</div>";
        echo "<div class='info'>Token obtido: " . substr($token, 0, 20) . "... (truncado por segurança)</div>";
        $testes_passou++;
    } else {
        echo "<div class='error'>❌ Falha na autenticação - Verifique:</div>";
        echo "<ul>";
        echo "<li>Client ID e Client Secret estão corretos?</li>";
        echo "<li>Certificado é válido e a senha está correta?</li>";
        echo "<li>Ambiente (sandbox/produção) está correto?</li>";
        echo "<li>Verifique os logs do sistema para mais detalhes</li>";
        echo "</ul>";
        $testes_falhou++;
    }
} else {
    echo "<div class='warning'>⚠️ Não foi possível testar autenticação - Configure primeiro os itens anteriores</div>";
    $testes_falhou++;
}
echo "</div>";

// Teste 5: Teste de criação de cobrança (opcional)
if ($token ?? false) {
    echo "<div class='test-item'>";
    echo "<h3>💰 Teste 5: Criação de Cobrança PIX</h3>";
    
    $testes_realizados++;
    echo "<p>Testando criação de cobrança PIX de teste (R$ 0,01)...</p>";
    
    $dados_teste = [
        'valor' => 0.01,
        'descricao' => 'Teste automatico do sistema',
        'participante_id' => 999999,
        'evento_nome' => 'Teste EFI Bank',
        'nome_pagador' => 'Teste Sistema',
        'cpf_pagador' => '',
        'expiracao' => 300 // 5 minutos
    ];
    
    $resultado_pix = efi_criar_pix_completo($dados_teste);
    
    if ($resultado_pix && isset($resultado_pix['sucesso'])) {
        echo "<div class='success'>✅ Cobrança PIX criada com sucesso!</div>";
        echo "<div class='info'>";
        echo "<strong>TXID:</strong> " . ($resultado_pix['pix_txid'] ?? 'N/A') . "<br>";
        echo "<strong>Status:</strong> " . ($resultado_pix['status'] ?? 'N/A') . "<br>";
        echo "<strong>QR Code gerado:</strong> " . (empty($resultado_pix['pix_qrcode_data']) ? 'Não' : 'Sim') . "<br>";
        echo "</div>";
        $testes_passou++;
    } else {
        $erro_msg = $resultado_pix['erro'] ?? 'Erro desconhecido';
        echo "<div class='error'>❌ Falha ao criar cobrança PIX: $erro_msg</div>";
        $testes_falhou++;
    }
    echo "</div>";
}

// Resumo final
echo "<div class='info'>";
echo "<h3>📊 Resumo dos Testes</h3>";
echo "<p><strong>Total de testes:</strong> $testes_realizados</p>";
echo "<p><strong>✅ Passou:</strong> $testes_passou</p>";
echo "<p><strong>❌ Falhou:</strong> $testes_falhou</p>";

if ($testes_falhou == 0) {
    echo "<div class='success'>";
    echo "<h4>🎉 Parabéns! Todos os testes passaram!</h4>";
    echo "<p>A integração EFI Bank está funcionando corretamente.</p>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h4>⚠️ Alguns testes falharam</h4>";
    echo "<p>Corrija os problemas indicados acima antes de usar o sistema em produção.</p>";
    echo "</div>";
}
echo "</div>";

echo "<h3>🔧 Próximos passos:</h3>";
echo "<ol>";
echo "<li>Se todos os testes passaram, teste fazer um pagamento real em: <a href='../pagamento.php?participante=7' target='_blank'>Página de Pagamento</a></li>";
echo "<li>Configure o webhook na EFI Bank se ainda não fez</li>";
echo "<li>Teste o recebimento de notificações de pagamento</li>";
echo "<li>Após confirmar que está funcionando, <strong>delete este arquivo</strong> por segurança</li>";
echo "</ol>";

echo "<h3>🧹 Limpeza:</h3>";
echo "<p><strong>IMPORTANTE:</strong> Após os testes, delete este arquivo por segurança:</p>";
echo "<pre>admin/testar_efi.php</pre>";

echo "    </div>
</body>
</html>";
?> 