<?php
/**
 * Versão de teste do index.php para identificar problemas
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "Carregando sistema...<br>";
    require_once 'includes/init.php';
    echo "✅ Sistema carregado!<br>";
    
    echo "Testando banco de dados...<br>";
    $teste_query = buscar_um("SELECT 1 as teste");
    echo "✅ Banco funcionando!<br>";
    
    echo "Verificando tabelas...<br>";
    
    // Verificar se sistema foi migrado
    $tabela_inscricoes_existe = false;
    try {
        $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
        $tabela_inscricoes_existe = $teste_tabela !== false;
        echo "✅ Tabela inscricoes: " . ($tabela_inscricoes_existe ? "existe" : "não existe") . "<br>";
    } catch (Exception $e) {
        echo "⚠️ Erro ao verificar tabela inscricoes: " . $e->getMessage() . "<br>";
        $tabela_inscricoes_existe = false;
    }
    
    echo "Buscando eventos...<br>";
    
    if ($tabela_inscricoes_existe) {
        // Sistema novo - usar tabela inscricoes
        echo "Usando sistema novo (tabela inscricoes)<br>";
        $eventos = buscar_todos("
            SELECT e.*, 
                   COUNT(i.id) as total_inscritos,
                   (e.limite_participantes - COUNT(i.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente', 'aprovada')
            WHERE e.status = 'ativo' 
            AND e.data_inicio >= CURDATE()
            GROUP BY e.id
            ORDER BY e.data_inicio ASC
            LIMIT 5
        ");
    } else {
        // Sistema antigo - usar tabela participantes
        echo "Usando sistema antigo (tabela participantes)<br>";
        $eventos = buscar_todos("
            SELECT e.*, 
                   COUNT(p.id) as total_inscritos,
                   (e.limite_participantes - COUNT(p.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN participantes p ON e.id = p.evento_id AND p.status IN ('inscrito', 'pago')
            WHERE e.status = 'ativo' 
            AND e.data_inicio >= CURDATE()
            GROUP BY e.id
            ORDER BY e.data_inicio ASC
            LIMIT 5
        ");
    }
    
    echo "✅ Eventos encontrados: " . count($eventos) . "<br>";
    
    if (count($eventos) > 0) {
        echo "<h3>Eventos:</h3>";
        foreach ($eventos as $evento) {
            echo "- " . htmlspecialchars($evento['nome']) . " (" . $evento['total_inscritos'] . " inscritos)<br>";
        }
    }
    
    echo "<h2>🎉 Index.php funcionando corretamente!</h2>";
    echo "<p>O sistema está operacional. Se ainda há erro 500, pode ser algo específico no HTML ou CSS.</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Erro no index.php:</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre style='background:#f0f0f0;padding:10px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
