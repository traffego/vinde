<?php
/**
 * Debug específico para encontrar problema no index.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG INDEX.PHP ===\n";

try {
    echo "1. Carregando init.php...\n";
    require_once 'includes/init.php';
    echo "✅ Init carregado\n";
    
    echo "2. Verificando SITE_URL: " . SITE_URL . "\n";
    
    echo "3. Testando busca de eventos...\n";
    
    // Copiar exata lógica do index.php
    $tabela_inscricoes_existe = false;
    try {
        $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
        $tabela_inscricoes_existe = $teste_tabela !== false;
        echo "✅ Verificação tabela inscricoes: " . ($tabela_inscricoes_existe ? "existe" : "não existe") . "\n";
    } catch (Exception $e) {
        echo "⚠️ Erro ao verificar tabela: " . $e->getMessage() . "\n";
        $tabela_inscricoes_existe = false;
    }
    
    echo "4. Executando query de eventos...\n";
    
    if ($tabela_inscricoes_existe) {
        $query = "
            SELECT e.*, 
                   COUNT(i.id) as total_inscritos,
                   (e.limite_participantes - COUNT(i.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente', 'aprovada')
            WHERE e.status = 'ativo' 
            AND e.data_inicio >= CURDATE()
            GROUP BY e.id
            ORDER BY e.data_inicio ASC
        ";
    } else {
        $query = "
            SELECT e.*, 
                   COUNT(p.id) as total_inscritos,
                   (e.limite_participantes - COUNT(p.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
            WHERE e.status = 'ativo' 
            AND e.data_inicio >= CURDATE()
            GROUP BY e.id
            ORDER BY e.data_inicio ASC
        ";
    }
    
    echo "Query a executar:\n" . $query . "\n";
    
    $eventos = buscar_todos($query);
    echo "✅ Eventos encontrados: " . count($eventos) . "\n";
    
    echo "5. Testando obter_cabecalho()...\n";
    
    // Test obter_cabecalho with output buffering
    ob_start();
    try {
        obter_cabecalho('Teste Debug', 'debug');
        $header_html = ob_get_contents();
        ob_end_clean();
        echo "✅ obter_cabecalho() executou sem erro. Tamanho HTML: " . strlen($header_html) . "\n";
        
        // Verificar se há algum problema no HTML gerado
        if (strpos($header_html, 'Fatal error') !== false || strpos($header_html, 'Parse error') !== false) {
            echo "❌ Erro encontrado no HTML gerado!\n";
            echo "Primeiros 500 chars do HTML:\n";
            echo substr($header_html, 0, 500) . "\n";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ Erro em obter_cabecalho(): " . $e->getMessage() . "\n";
        echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "\n";
    }
    
    echo "\n=== TESTE COMPLETO ===\n";
    echo "Se chegou até aqui, o problema pode estar:\n";
    echo "1. Na saída do obter_cabecalho() (HTML mal formado)\n";
    echo "2. Em algum include de CSS/JS que não existe\n";
    echo "3. Em alguma configuração de headers\n";
    
} catch (Exception $e) {
    echo "❌ ERRO FATAL:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ ERRO PHP:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
