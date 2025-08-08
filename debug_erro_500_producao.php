<?php
/**
 * Script de diagnóstico para erro 500 em produção
 * Execute para identificar e corrigir problemas
 */

// Habilitar exibição de erros temporariamente
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico Erro 500 - Vinde</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #ffeaa7; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #bee5eb; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #dee2e6; font-size: 12px; }
        .section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #dee2e6; }
        h1 { color: #dc3545; }
        h2 { color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        h3 { color: #6c757d; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .check-item { margin: 10px 0; padding: 10px; border-left: 4px solid #28a745; background: #f8f9fa; }
        .check-item.error { border-left-color: #dc3545; }
        .check-item.warning { border-left-color: #ffc107; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔍 Diagnóstico Erro 500 - Produção</h1>";

echo "<div class='info'>
<strong>🌐 Informações do Servidor:</strong><br>
Host: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "<br>
IP: " . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "<br>
PHP Version: " . PHP_VERSION . "<br>
Data/Hora: " . date('Y-m-d H:i:s') . "<br>
Ambiente: " . (defined('AMBIENTE') ? AMBIENTE : 'Não definido') . "
</div>";

echo "<h2>📋 Checklist de Diagnóstico</h2>";

$checks = [];

// 1. Verificar se includes básicos existem
$includes_necessarios = [
    'includes/config.php',
    'includes/init.php', 
    'includes/database.php',
    'includes/functions.php',
    'includes/debug_config.php'
];

foreach ($includes_necessarios as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $checks[] = [
        'item' => "Arquivo {$file}",
        'status' => $exists ? 'success' : 'error',
        'message' => $exists ? '✅ Existe' : '❌ Não encontrado'
    ];
}

// 2. Testar carregamento do init.php
echo "<h3>🔧 Teste de Carregamento dos Includes</h3>";
try {
    ob_start();
    
    // Definir constante do sistema ANTES de carregar qualquer arquivo
    if (!defined('SISTEMA_INSCRICOES')) {
        define('SISTEMA_INSCRICOES', true);
    }
    
    include_once __DIR__ . '/includes/config.php';
    $config_ok = true;
    $config_error = '';
} catch (Exception $e) {
    $config_ok = false;
    $config_error = $e->getMessage();
} catch (Error $e) {
    $config_ok = false;
    $config_error = $e->getMessage();
} finally {
    ob_end_clean();
}

$checks[] = [
    'item' => 'Carregamento config.php',
    'status' => $config_ok ? 'success' : 'error',
    'message' => $config_ok ? '✅ OK' : '❌ Erro: ' . $config_error
];

// 3. Testar conexão com banco
if ($config_ok) {
    echo "<h3>🗄️ Teste de Conexão com Banco</h3>";
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        $db_ok = true;
        $db_error = '';
        
        // Testar uma query simples
        $stmt = $pdo->query("SELECT 1");
        $query_ok = $stmt !== false;
        
    } catch (Exception $e) {
        $db_ok = false;
        $db_error = $e->getMessage();
        $query_ok = false;
    }
    
    $checks[] = [
        'item' => 'Conexão com MySQL',
        'status' => $db_ok ? 'success' : 'error',
        'message' => $db_ok ? '✅ Conectado' : '❌ Erro: ' . $db_error
    ];
    
    if ($db_ok) {
        $checks[] = [
            'item' => 'Query de teste',
            'status' => $query_ok ? 'success' : 'error',
            'message' => $query_ok ? '✅ OK' : '❌ Falha na query'
        ];
    }
}

// 4. Verificar permissões de pastas
$folders_check = [
    'logs',
    'uploads', 
    'certificados'
];

foreach ($folders_check as $folder) {
    $folder_path = __DIR__ . '/' . $folder;
    $exists = is_dir($folder_path);
    $writable = $exists ? is_writable($folder_path) : false;
    
    $checks[] = [
        'item' => "Pasta {$folder}",
        'status' => ($exists && $writable) ? 'success' : ($exists ? 'warning' : 'error'),
        'message' => $exists ? 
            ($writable ? '✅ Existe e gravável' : '⚠️ Existe mas não gravável') : 
            '❌ Não existe'
    ];
}

// 5. Verificar index.php
$index_exists = file_exists(__DIR__ . '/index.php');
$checks[] = [
    'item' => 'Arquivo index.php',
    'status' => $index_exists ? 'success' : 'error',
    'message' => $index_exists ? '✅ Existe' : '❌ Não encontrado'
];

// 6. Testar carregamento completo
if ($config_ok) {
    echo "<h3>⚙️ Teste de Carregamento Completo do Sistema</h3>";
    try {
        ob_start();
        
        // Simular carregamento do sistema sem output
        // (SISTEMA_INSCRICOES já foi definido acima)
        
        // Testar includes um por um
        $init_steps = [
            'debug_config.php' => false,
            'database.php' => false,
            'functions.php' => false
        ];
        
        foreach ($init_steps as $file => &$loaded) {
            try {
                include_once __DIR__ . '/includes/' . $file;
                $loaded = true;
            } catch (Exception $e) {
                $init_error = $e->getMessage();
                break;
            } catch (Error $e) {
                $init_error = $e->getMessage();
                break;
            }
        }
        
        $init_ok = !isset($init_error);
        
    } catch (Exception $e) {
        $init_ok = false;
        $init_error = $e->getMessage();
    } catch (Error $e) {
        $init_ok = false;
        $init_error = $e->getMessage();
    } finally {
        ob_end_clean();
    }
    
    $checks[] = [
        'item' => 'Carregamento completo do sistema',
        'status' => $init_ok ? 'success' : 'error',
        'message' => $init_ok ? '✅ OK' : '❌ Erro: ' . ($init_error ?? 'Desconhecido')
    ];
    
    if (!$init_ok && isset($init_steps)) {
        foreach ($init_steps as $file => $loaded) {
            $checks[] = [
                'item' => "Include {$file}",
                'status' => $loaded ? 'success' : 'error',
                'message' => $loaded ? '✅ Carregado' : '❌ Falhou'
            ];
        }
    }
}

// Exibir resultados
foreach ($checks as $check) {
    echo "<div class='check-item {$check['status']}'>";
    echo "<strong>{$check['item']}:</strong> {$check['message']}";
    echo "</div>";
}

// Verificar logs de erro
echo "<h2>📋 Logs de Erro Recentes</h2>";

$log_files = [
    'PHP Error Log' => __DIR__ . '/logs/php_errors.log',
    'Apache Error Log' => '/var/log/apache2/error.log',
    'Nginx Error Log' => '/var/log/nginx/error.log'
];

foreach ($log_files as $name => $path) {
    if (file_exists($path) && is_readable($path)) {
        echo "<h3>{$name}</h3>";
        $lines = file($path);
        if ($lines) {
            $recent_lines = array_slice($lines, -20); // Últimas 20 linhas
            echo "<pre>" . htmlspecialchars(implode('', $recent_lines)) . "</pre>";
        } else {
            echo "<div class='info'>Log vazio</div>";
        }
    } else {
        echo "<h3>{$name}</h3>";
        echo "<div class='warning'>Log não encontrado ou não legível: {$path}</div>";
    }
}

// Testar página inicial
echo "<h2>🏠 Teste da Página Inicial</h2>";

try {
    $home_url = "https://" . $_SERVER['HTTP_HOST'];
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $home_content = @file_get_contents($home_url, false, $context);
    $home_ok = $home_content !== false;
    
    if (!$home_ok && isset($http_response_header)) {
        $status_line = $http_response_header[0] ?? 'N/A';
        echo "<div class='error'>❌ Falha ao acessar página inicial: {$status_line}</div>";
    } elseif ($home_ok) {
        echo "<div class='success'>✅ Página inicial acessível</div>";
    } else {
        echo "<div class='error'>❌ Falha ao acessar página inicial (sem resposta)</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erro ao testar página inicial: " . $e->getMessage() . "</div>";
}

// Ações recomendadas
echo "<h2>🔧 Ações Recomendadas</h2>";

$has_errors = false;
foreach ($checks as $check) {
    if ($check['status'] === 'error') {
        $has_errors = true;
        break;
    }
}

if ($has_errors) {
    echo "<div class='error'>
    <strong>❌ Problemas Encontrados</strong><br>
    Há erros que precisam ser corrigidos antes do site funcionar normalmente.
    </div>";
    
    echo "<div class='section'>
    <h3>Passos para Corrigir:</h3>
    <ol>
    <li>Verificar conexão com banco de dados</li>
    <li>Verificar permissões de pastas</li>
    <li>Verificar se todos os arquivos necessários existem</li>
    <li>Verificar logs de erro para detalhes específicos</li>
    <li>Contatar suporte se necessário</li>
    </ol>
    </div>";
} else {
    echo "<div class='success'>
    <strong>✅ Sistema OK</strong><br>
    Todos os componentes básicos estão funcionando. O erro 500 pode ser específico de uma página.
    </div>";
}

// Habilitar debug temporariamente
echo "<h2>🐛 Debug Temporário</h2>";
echo "<div class='warning'>
<strong>⚠️ Modo Debug</strong><br>
Para identificar erros específicos, você pode habilitar temporariamente o debug:
</div>";

echo "<div class='section'>
<h3>Para habilitar debug:</h3>
<ol>
<li>Edite o arquivo <code>includes/debug_config.php</code></li>
<li>Altere <code>define('DEBUG_ENABLED', false);</code> para <code>define('DEBUG_ENABLED', true);</code></li>
<li>Altere <code>define('SHOW_PHP_ERRORS', false);</code> para <code>define('SHOW_PHP_ERRORS', true);</code></li>
<li>Salve o arquivo e teste novamente</li>
<li><strong>IMPORTANTE:</strong> Desabilitar após identificar o problema!</li>
</ol>
</div>";

echo "<div class='section'>
<h3>Links Úteis:</h3>
<a href='{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/' class='btn'>🏠 Testar Página Inicial</a>
<a href='{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/admin/' class='btn'>👤 Testar Admin</a>
<a href='{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/webhook_efi.php' class='btn'>🔗 Testar Webhook</a>
</div>";

echo "</div></body></html>";
?>
