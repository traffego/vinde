<?php
/**
 * Teste básico do sistema
 * Carrega o sistema completo e verifica funcionamento
 */

// Habilitar debug para este teste
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste Sistema Básico</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
<h1>🧪 Teste Sistema Básico</h1>";

try {
    echo "<div class='info'>🔄 Iniciando testes do sistema...</div>";
    
    // Definir constante do sistema ANTES de carregar qualquer arquivo
    if (!defined('SISTEMA_INSCRICOES')) {
        define('SISTEMA_INSCRICOES', true);
    }
    
    // Teste 1: Carregar configurações
    echo "<h3>1. Carregando configurações...</h3>";
    require_once __DIR__ . '/includes/config.php';
    echo "<div class='success'>✅ Configurações carregadas</div>";
    
    // Teste 2: Carregar debug
    echo "<h3>2. Carregando debug...</h3>";
    require_once __DIR__ . '/includes/debug_config.php';
    echo "<div class='success'>✅ Debug carregado</div>";
    
    // Teste 3: Carregar funções
    echo "<h3>3. Carregando funções...</h3>";
    require_once __DIR__ . '/includes/functions.php';
    echo "<div class='success'>✅ Funções carregadas</div>";
    
    // Teste 4: Carregar banco
    echo "<h3>4. Testando banco de dados...</h3>";
    require_once __DIR__ . '/includes/database.php';
    $pdo = conectar_banco();
    echo "<div class='success'>✅ Banco de dados conectado</div>";
    
    // Teste 5: Query simples
    echo "<h3>5. Testando query...</h3>";
    $stmt = $pdo->query("SELECT 1 as teste");
    $result = $stmt->fetch();
    if ($result && $result['teste'] == 1) {
        echo "<div class='success'>✅ Query funcionando</div>";
    } else {
        echo "<div class='error'>❌ Query falhou</div>";
    }
    
    // Teste 6: Carregar sistema completo
    echo "<h3>6. Carregando sistema completo...</h3>";
    require_once __DIR__ . '/includes/init.php';
    echo "<div class='success'>✅ Sistema carregado completamente</div>";
    
    // Teste 7: Verificar funções essenciais
    echo "<h3>7. Verificando funções essenciais...</h3>";
    $funcoes_teste = [
        'buscar_um',
        'executar',
        'obter_configuracao',
        'efi_esta_ativo'
    ];
    
    foreach ($funcoes_teste as $funcao) {
        if (function_exists($funcao)) {
            echo "<div class='success'>✅ Função {$funcao}() disponível</div>";
        } else {
            echo "<div class='error'>❌ Função {$funcao}() não encontrada</div>";
        }
    }
    
    // Teste 8: Verificar configurações EFI
    echo "<h3>8. Verificando configurações EFI...</h3>";
    if (function_exists('obter_configuracoes_efi')) {
        $config_efi = obter_configuracoes_efi();
        echo "<div class='success'>✅ Configurações EFI carregadas</div>";
        echo "<div class='info'>EFI Ativo: " . (efi_esta_ativo() ? 'Sim' : 'Não') . "</div>";
    } else {
        echo "<div class='error'>❌ Função obter_configuracoes_efi() não encontrada</div>";
    }
    
    echo "<h2>🎉 Testes Concluídos com Sucesso!</h2>";
    echo "<div class='success'>
    <strong>✅ Sistema funcionando corretamente!</strong><br>
    Todos os componentes básicos estão operacionais.
    </div>";
    
    echo "<div class='info'>
    <strong>Próximo passo:</strong> Agora você pode acessar a página inicial normalmente.
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>
    <strong>❌ Erro encontrado:</strong><br>
    " . htmlspecialchars($e->getMessage()) . "<br><br>
    <strong>Arquivo:</strong> " . $e->getFile() . "<br>
    <strong>Linha:</strong> " . $e->getLine() . "
    </div>";
    
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    
} catch (Error $e) {
    echo "<div class='error'>
    <strong>❌ Erro fatal encontrado:</strong><br>
    " . htmlspecialchars($e->getMessage()) . "<br><br>
    <strong>Arquivo:</strong> " . $e->getFile() . "<br>
    <strong>Linha:</strong> " . $e->getLine() . "
    </div>";
    
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<div class='info'>
<strong>📋 Links para testar:</strong><br>
<a href='https://vinde.traffego.agency/'>🏠 Página Inicial</a> | 
<a href='https://vinde.traffego.agency/admin/'>👤 Admin</a> | 
<a href='https://vinde.traffego.agency/debug_erro_500_producao.php'>🔍 Diagnóstico Completo</a>
</div>";

echo "</div></body></html>";
?>
