<?php
// Debug da página principal
require_once 'includes/init.php';

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🏠 Debug - Página Principal</h1>";

try {
    echo "<h2>1. Verificando sistema</h2>";
    
    // Verificar se sistema foi migrado
    $tabela_inscricoes_existe = false;
    try {
        $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
        $tabela_inscricoes_existe = $teste_tabela !== false;
        
        if ($tabela_inscricoes_existe) {
            echo "✅ Sistema MIGRADO - Usando tabela inscricoes<br>";
        } else {
            echo "⚠️ Sistema ANTIGO - Usando tabela participantes<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao verificar sistema: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>2. Testando query de eventos</h2>";
    
    if ($tabela_inscricoes_existe) {
        // Sistema novo
        echo "<h3>Sistema novo (inscricoes):</h3>";
        try {
            $eventos_novo = buscar_todos("
                SELECT e.*, 
                       COUNT(i.id) as total_inscritos,
                       (e.limite_participantes - COUNT(i.id)) as vagas_restantes
                FROM eventos e
                LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente', 'aprovada')
                WHERE e.status = 'ativo' 
                AND e.data_inicio >= CURDATE()
                GROUP BY e.id
                ORDER BY e.data_inicio ASC
            ");
            
            echo "✅ Query novo sistema executada com sucesso<br>";
            echo "Total de eventos encontrados: " . count($eventos_novo) . "<br>";
            
            if (!empty($eventos_novo)) {
                echo "<h4>Eventos encontrados:</h4>";
                foreach ($eventos_novo as $evento) {
                    echo "<div style='background: #f0f8ff; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
                    echo "<strong>Nome:</strong> " . htmlspecialchars($evento['nome']) . "<br>";
                    echo "<strong>Data:</strong> " . date('d/m/Y', strtotime($evento['data_inicio'])) . "<br>";
                    echo "<strong>Local:</strong> " . htmlspecialchars($evento['local']) . "<br>";
                    echo "<strong>Inscritos:</strong> " . $evento['total_inscritos'] . "/" . $evento['limite_participantes'] . "<br>";
                    echo "<strong>Vagas restantes:</strong> " . $evento['vagas_restantes'] . "<br>";
                    echo "</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Erro no sistema novo: " . $e->getMessage() . "<br>";
        }
    } else {
        // Sistema antigo
        echo "<h3>Sistema antigo (participantes):</h3>";
        try {
            $eventos_antigo = buscar_todos("
                SELECT e.*, 
                       COUNT(p.id) as total_inscritos,
                       (e.limite_participantes - COUNT(p.id)) as vagas_restantes
                FROM eventos e
                LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
                WHERE e.status = 'ativo' 
                AND e.data_inicio >= CURDATE()
                GROUP BY e.id
                ORDER BY e.data_inicio ASC
            ");
            
            echo "✅ Query sistema antigo executada com sucesso<br>";
            echo "Total de eventos encontrados: " . count($eventos_antigo) . "<br>";
            
            if (!empty($eventos_antigo)) {
                echo "<h4>Eventos encontrados:</h4>";
                foreach ($eventos_antigo as $evento) {
                    echo "<div style='background: #f0fff0; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
                    echo "<strong>Nome:</strong> " . htmlspecialchars($evento['nome']) . "<br>";
                    echo "<strong>Data:</strong> " . date('d/m/Y', strtotime($evento['data_inicio'])) . "<br>";
                    echo "<strong>Local:</strong> " . htmlspecialchars($evento['local']) . "<br>";
                    echo "<strong>Inscritos:</strong> " . $evento['total_inscritos'] . "/" . $evento['limite_participantes'] . "<br>";
                    echo "<strong>Vagas restantes:</strong> " . $evento['vagas_restantes'] . "<br>";
                    echo "</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Erro no sistema antigo: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>3. Testando funções auxiliares</h2>";
    
    if (function_exists('formatar_data')) {
        echo "✅ Função formatar_data existe<br>";
    } else {
        echo "❌ Função formatar_data NÃO existe<br>";
    }
    
    if (function_exists('formatar_dinheiro')) {
        echo "✅ Função formatar_dinheiro existe<br>";
    } else {
        echo "❌ Função formatar_dinheiro NÃO existe<br>";
    }
    
    if (function_exists('obter_cabecalho')) {
        echo "✅ Função obter_cabecalho existe<br>";
    } else {
        echo "❌ Função obter_cabecalho NÃO existe<br>";
    }
    
    echo "<h2>4. Verificando cidades para filtro</h2>";
    try {
        $cidades = buscar_todos("SELECT DISTINCT cidade FROM eventos WHERE status = 'ativo' ORDER BY cidade");
        echo "✅ Query de cidades executada com sucesso<br>";
        echo "Cidades encontradas: " . count($cidades) . "<br>";
        
        if (!empty($cidades)) {
            echo "Cidades: ";
            foreach ($cidades as $cidade) {
                echo $cidade['cidade'] . " | ";
            }
            echo "<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao buscar cidades: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>✅ RESULTADO</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; color: #155724;'>";
    echo "<strong>STATUS:</strong> Correções aplicadas na página principal<br>";
    echo "<strong>SISTEMA:</strong> " . ($tabela_inscricoes_existe ? "Migrado (novo)" : "Antigo (compatibilidade)") . "<br>";
    echo "<strong>PRÓXIMO PASSO:</strong> Testar https://vinde.traffego.agency/<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO GERAL</h2>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 