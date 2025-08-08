<?php
// Arquivo temporário para debug da página de inscrição
// DELETE ESTE ARQUIVO APÓS RESOLVER O PROBLEMA

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug - Inscrição</h1>";

try {
    echo "<h2>1. Testando includes básicos</h2>";
    require_once 'includes/init.php';
    echo "✅ includes/init.php carregado<br>";
    
    require_once 'includes/auth_participante.php';
    echo "✅ includes/auth_participante.php carregado<br>";
    
    echo "<h2>2. Testando parâmetros</h2>";
    $evento_id = $_GET['evento_id'] ?? 0;
    echo "evento_id: " . $evento_id . "<br>";
    
    if (!$evento_id) {
        echo "❌ evento_id não fornecido<br>";
        exit;
    }
    
    echo "<h2>3. Testando conexão com banco</h2>";
    $pdo = conectar_banco();
    echo "✅ Conexão com banco estabelecida<br>";
    
    echo "<h2>4. Testando se tabela eventos existe</h2>";
    $tabela_eventos = buscar_um("SHOW TABLES LIKE 'eventos'");
    if ($tabela_eventos) {
        echo "✅ Tabela eventos existe<br>";
    } else {
        echo "❌ Tabela eventos não existe<br>";
    }
    
    echo "<h2>5. Testando se tabela inscricoes existe</h2>";
    $tabela_inscricoes = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    if ($tabela_inscricoes) {
        echo "✅ Tabela inscricoes existe<br>";
    } else {
        echo "❌ Tabela inscricoes NÃO existe - EXECUTAR MIGRAÇÃO!<br>";
    }
    
    echo "<h2>6. Testando busca do evento (query original)</h2>";
    $evento_original = buscar_um("
        SELECT *, 
               (limite_participantes - (SELECT COUNT(*) FROM participantes WHERE evento_id = eventos.id AND status != 'cancelado')) as vagas_restantes
        FROM eventos 
        WHERE id = ? AND status = 'ativo'
    ", [$evento_id]);
    
    if ($evento_original) {
        echo "✅ Query original funciona<br>";
        echo "Evento: " . $evento_original['nome'] . "<br>";
    } else {
        echo "❌ Evento não encontrado com query original<br>";
    }
    
    echo "<h2>7. Testando busca do evento (query nova)</h2>";
    try {
        $evento_novo = buscar_um("
            SELECT *, 
                   (limite_participantes - (
                       SELECT COUNT(*) 
                       FROM inscricoes 
                       WHERE evento_id = eventos.id AND status IN ('pendente', 'aprovada')
                   )) as vagas_restantes
            FROM eventos 
            WHERE id = ? AND status = 'ativo'
        ", [$evento_id]);
        
        if ($evento_novo) {
            echo "✅ Query nova funciona<br>";
            echo "Evento: " . $evento_novo['nome'] . "<br>";
        } else {
            echo "❌ Evento não encontrado com query nova<br>";
        }
    } catch (Exception $e) {
        echo "❌ ERRO na query nova: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>8. Testando funções de autenticação</h2>";
    
    if (function_exists('participante_esta_logado')) {
        echo "✅ Função participante_esta_logado existe<br>";
        $logado = participante_esta_logado();
        echo "Usuário logado: " . ($logado ? 'SIM' : 'NÃO') . "<br>";
    } else {
        echo "❌ Função participante_esta_logado NÃO existe<br>";
    }
    
    if (function_exists('participante_ja_inscrito')) {
        echo "✅ Função participante_ja_inscrito existe<br>";
    } else {
        echo "❌ Função participante_ja_inscrito NÃO existe<br>";
    }
    
    if (function_exists('criar_inscricao_participante')) {
        echo "✅ Função criar_inscricao_participante existe<br>";
    } else {
        echo "❌ Função criar_inscricao_participante NÃO existe<br>";
    }
    
    echo "<h2>9. Estrutura da tabela participantes</h2>";
    try {
        $estrutura_participantes = buscar_todos("DESCRIBE participantes");
        echo "Colunas na tabela participantes:<br>";
        foreach ($estrutura_participantes as $coluna) {
            echo "- " . $coluna['Field'] . " (" . $coluna['Type'] . ")<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao descrever tabela participantes: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>10. Executar migração se necessário</h2>";
    if (!$tabela_inscricoes) {
        echo "⚠️  AÇÃO NECESSÁRIA: Executar o script database_migration_new_flow.sql<br>";
        echo "Este script criará a tabela inscricoes e fará as alterações necessárias.<br>";
    }
    
    echo "<h2>Resultado</h2>";
    echo "Se chegou até aqui sem erros fatais, o problema está na lógica específica da página inscricao.php<br>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO CAPTURADO</h2>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?> 