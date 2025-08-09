<?php
/**
 * Teste específico das queries do index.php
 */
require_once 'includes/init.php';

echo "Content-Type: text/plain\n\n";
echo "=== TESTE DAS QUERIES DO INDEX ===\n\n";

try {
    echo "1. Verificando tabelas...\n";
    
    // Verificar todas as tabelas
    $tabelas = buscar_todos("SHOW TABLES");
    echo "Tabelas no banco:\n";
    foreach ($tabelas as $tabela) {
        $nome_tabela = array_values($tabela)[0];
        echo "- $nome_tabela\n";
    }
    echo "\n";
    
    echo "2. Verificando tabela 'inscricoes'...\n";
    $teste_inscricoes = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_inscricoes !== false;
    echo "Tabela inscricoes existe: " . ($tabela_inscricoes_existe ? "SIM" : "NÃO") . "\n\n";
    
    echo "3. Verificando tabela 'eventos'...\n";
    $total_eventos = buscar_um("SELECT COUNT(*) as total FROM eventos");
    echo "Total de eventos: " . $total_eventos['total'] . "\n";
    
    $eventos_ativos = buscar_um("SELECT COUNT(*) as total FROM eventos WHERE status = 'ativo'");
    echo "Eventos ativos: " . $eventos_ativos['total'] . "\n\n";
    
    echo "4. Testando query do index (sistema novo)...\n";
    if ($tabela_inscricoes_existe) {
        try {
            $query_nova = "
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
            $eventos_nova = buscar_todos($query_nova);
            echo "✅ Query nova executada com sucesso. Eventos encontrados: " . count($eventos_nova) . "\n";
        } catch (Exception $e) {
            echo "❌ Erro na query nova: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n5. Testando query do index (sistema antigo)...\n";
    try {
        $query_antiga = "
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
        $eventos_antiga = buscar_todos($query_antiga);
        echo "✅ Query antiga executada com sucesso. Eventos encontrados: " . count($eventos_antiga) . "\n";
    } catch (Exception $e) {
        echo "❌ Erro na query antiga: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== TESTE COMPLETO ===\n";
    echo "Se não houve erros acima, o problema NÃO está nas queries.\n";
    echo "O problema deve estar no obter_cabecalho() ou no HTML.\n";
    
} catch (Exception $e) {
    echo "❌ ERRO FATAL:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}
?>
