<?php
/**
 * Teste rápido e simples para identificar erro 500
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Teste Rápido</title></head><body>";
echo "<h1>🧪 Teste Rápido do Sistema</h1>";

try {
    echo "<p>1. ✅ PHP funcionando (versão: " . PHP_VERSION . ")</p>";
    
    // Definir constante obrigatória
    define('SISTEMA_INSCRICOES', true);
    echo "<p>2. ✅ Constante SISTEMA_INSCRICOES definida</p>";
    
    // Testar config
    require_once __DIR__ . '/includes/config.php';
    echo "<p>3. ✅ Configurações carregadas</p>";
    
    // Testar banco
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    echo "<p>4. ✅ Banco conectado (" . DB_NAME . ")</p>";
    
    // Testar query
    $stmt = $pdo->query("SELECT 1");
    echo "<p>5. ✅ Query funcionando</p>";
    
    // Testar demais includes
    require_once __DIR__ . '/includes/functions.php';
    echo "<p>6. ✅ Funções carregadas</p>";
    
    require_once __DIR__ . '/includes/database.php';
    echo "<p>7. ✅ Database.php carregado</p>";
    
    // Testar init completo
    require_once __DIR__ . '/includes/init.php';
    echo "<p>8. ✅ Sistema completo carregado</p>";
    
    echo "<h2>🎉 Sistema OK! Pode acessar a página inicial.</h2>";
    echo "<p><a href='https://vinde.traffego.agency/'>🏠 Ir para página inicial</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Erro encontrado:</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<h2 style='color:red'>❌ Erro fatal:</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>
