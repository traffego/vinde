<?php
/**
 * Script simples para verificar se o webhook está funcionando localmente
 * Execute via: http://localhost/vinde/verificar_webhook_local.php
 */

// Configuração básica para funcionar standalone
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificação Webhook EFI - Local</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #dee2e6; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔍 Verificação do Webhook EFI - Ambiente Local</h1>";

// Detectar URLs automaticamente
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$port = $_SERVER['SERVER_PORT'] ?? '';
$port_str = ($port && $port != '80' && $port != '443') ? ':' . $port : '';

// Construir URL base
$base_url = $protocol . '://' . $host . $port_str;

// Detectar diretório do projeto
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$webhook_url = $base_url . $script_dir . '/webhook_efi.php';

echo "<div class='info'>
<strong>🌐 Informações do Ambiente:</strong><br>
Host: {$host}<br>
Protocolo: {$protocol}<br>
URL Base: {$base_url}<br>
Diretório: {$script_dir}<br>
<strong>URL do Webhook:</strong> <code>{$webhook_url}</code>
</div>";

// Verificar se arquivo webhook existe
$webhook_file = __DIR__ . '/webhook_efi.php';
if (!file_exists($webhook_file)) {
    echo "<div class='error'>❌ Arquivo webhook_efi.php não encontrado em: {$webhook_file}</div>";
} else {
    echo "<div class='success'>✅ Arquivo webhook_efi.php encontrado</div>";
}

// Teste de conectividade básica
echo "<h2>🔧 Teste de Conectividade</h2>";

if (isset($_POST['testar'])) {
    $payload_teste = [
        'pix' => [
            [
                'endToEndId' => 'E' . str_pad(time(), 32, '0', STR_PAD_LEFT),
                'txid' => 'TESTE_LOCAL_' . time(),
                'valor' => '1.00',
                'horario' => date('c'),
                'infoPagador' => 'Teste de conectividade local'
            ]
        ]
    ];
    
    echo "<div class='info'>📤 Enviando payload de teste...</div>";
    echo "<pre>" . json_encode($payload_teste, JSON_PRETTY_PRINT) . "</pre>";
    
    // Fazer requisição local
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $webhook_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload_teste),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: Teste-Local-Webhook/1.0'
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    
    echo "<h3>📥 Resultado do Teste:</h3>";
    
    if ($error) {
        echo "<div class='error'>❌ Erro cURL: {$error}</div>";
    }
    
    if ($http_code === 0) {
        echo "<div class='error'>❌ Não foi possível conectar ao webhook</div>";
        echo "<div class='info'>💡 Possíveis causas:<br>
        - Servidor web não está rodando<br>
        - URL incorreta<br>
        - Firewall bloqueando<br>
        - Arquivo webhook_efi.php com erro de sintaxe</div>";
    } else {
        $status_class = ($http_code >= 200 && $http_code < 300) ? 'success' : 'error';
        $status_icon = ($http_code >= 200 && $http_code < 300) ? '✅' : '❌';
        
        echo "<div class='{$status_class}'>{$status_icon} HTTP Status: {$http_code}</div>";
        
        if ($response) {
            echo "<h4>📄 Resposta do Webhook:</h4>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
            
            // Tentar decodificar JSON da resposta
            $response_data = json_decode($response, true);
            if ($response_data) {
                echo "<h4>🔍 Resposta Decodificada:</h4>";
                echo "<pre>" . print_r($response_data, true) . "</pre>";
            }
        } else {
            echo "<div class='info'>📭 Resposta vazia</div>";
        }
    }
    
    echo "<h4>📊 Informações da Requisição:</h4>";
    echo "<pre>";
    echo "URL: " . $info['url'] . "\n";
    echo "HTTP Code: " . $info['http_code'] . "\n";
    echo "Total Time: " . round($info['total_time'], 3) . "s\n";
    echo "Connect Time: " . round($info['connect_time'], 3) . "s\n";
    echo "</pre>";
}

echo "<form method='POST'>
<button type='submit' name='testar' class='btn'>🧪 Testar Webhook</button>
</form>";

// Verificações adicionais
echo "<h2>🔍 Verificações Adicionais</h2>";

// Verificar se as funções necessárias existem
$funcoes_necessarias = ['curl_init', 'json_encode', 'json_decode', 'file_get_contents'];
echo "<h3>📚 Funções PHP:</h3>";
foreach ($funcoes_necessarias as $funcao) {
    $existe = function_exists($funcao);
    $icon = $existe ? '✅' : '❌';
    $class = $existe ? 'success' : 'error';
    echo "<div class='{$class}'>{$icon} {$funcao}()</div>";
}

// Verificar permissões do diretório
echo "<h3>📁 Permissões:</h3>";
$webhook_dir = dirname($webhook_file);
$readable = is_readable($webhook_dir);
$writable = is_writable($webhook_dir);

echo "<div class='" . ($readable ? 'success' : 'error') . "'>" . 
     ($readable ? '✅' : '❌') . " Diretório legível: {$webhook_dir}</div>";
echo "<div class='" . ($writable ? 'success' : 'error') . "'>" . 
     ($writable ? '✅' : '❌') . " Diretório gravável (para logs)</div>";

// Verificar includes necessários
echo "<h3>📦 Arquivos de Include:</h3>";
$includes_necessarios = [
    'includes/init.php',
    'includes/config.php', 
    'includes/database.php',
    'includes/functions.php',
    'includes/efi_bank.php'
];

foreach ($includes_necessarios as $include) {
    $arquivo = __DIR__ . '/' . $include;
    $existe = file_exists($arquivo);
    $icon = $existe ? '✅' : '❌';
    $class = $existe ? 'success' : 'error';
    echo "<div class='{$class}'>{$icon} {$include}</div>";
}

echo "<h2>📋 Próximos Passos</h2>";
echo "<div class='info'>
<strong>Se o teste passou:</strong><br>
1. ✅ Webhook está funcionando localmente<br>
2. Configure as credenciais EFI no admin<br>
3. Registre o webhook na EFI quando em produção<br><br>

<strong>Se o teste falhou:</strong><br>
1. Verifique se o Apache/Nginx está rodando<br>
2. Verifique se há erros no arquivo webhook_efi.php<br>
3. Verifique os logs do servidor web<br>
4. Teste acessar {$webhook_url} diretamente no browser
</div>";

echo "</div></body></html>";
?>
