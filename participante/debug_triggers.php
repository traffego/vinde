<?php
// Debug específico para identificar triggers problemáticos
require_once '../includes/init.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Debug Triggers - Evento_id</h1>";

/**
 * Função que NÃO mascara o erro SQL
 */
function executar_sql_debug($sql, $params = []) {
    try {
        $pdo = conectar_banco();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828;'>";
        echo "<strong>❌ ERRO SQL:</strong> " . $e->getMessage() . "<br>";
        echo "</div>";
        throw $e;
    }
}

try {
    echo "<h2>1. Verificando triggers existentes na tabela participantes</h2>";
    
    $triggers = executar_sql_debug("
        SELECT 
            TRIGGER_NAME,
            EVENT_MANIPULATION,
            EVENT_OBJECT_TABLE,
            ACTION_TIMING,
            ACTION_STATEMENT
        FROM INFORMATION_SCHEMA.TRIGGERS 
        WHERE EVENT_OBJECT_SCHEMA = DATABASE() 
        AND EVENT_OBJECT_TABLE = 'participantes'
    ");
    
    $trigger_list = $triggers->fetchAll();
    
    if (empty($trigger_list)) {
        echo "✅ Nenhum trigger encontrado na tabela participantes<br>";
    } else {
        echo "📋 Triggers encontrados:<br>";
        foreach ($trigger_list as $trigger) {
            echo "<h3>🔍 Trigger: " . $trigger['TRIGGER_NAME'] . "</h3>";
            echo "- Evento: " . $trigger['EVENT_MANIPULATION'] . "<br>";
            echo "- Timing: " . $trigger['ACTION_TIMING'] . "<br>";
            echo "- Código:<br>";
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px;'>";
            echo htmlspecialchars($trigger['ACTION_STATEMENT']);
            echo "</pre>";
            
            // Verificar se o trigger contém evento_id
            if (strpos(strtolower($trigger['ACTION_STATEMENT']), 'evento_id') !== false) {
                echo "<div style='background: #ffebee; padding: 10px; border-radius: 4px; color: #c62828;'>";
                echo "❌ <strong>PROBLEMA ENCONTRADO!</strong> Este trigger contém referências a 'evento_id'<br>";
                echo "</div>";
                
                echo "<h4>🔧 Comando para remover este trigger:</h4>";
                echo "<code style='background: #fff3e0; padding: 5px; border-radius: 4px;'>";
                echo "DROP TRIGGER IF EXISTS " . $trigger['TRIGGER_NAME'] . ";";
                echo "</code><br>";
            } else {
                echo "✅ Este trigger parece estar OK (não contém evento_id)<br>";
            }
            echo "<hr>";
        }
    }
    
    echo "<h2>2. Verificando triggers em outras tabelas que possam afetar participantes</h2>";
    
    $all_triggers = executar_sql_debug("
        SELECT 
            TRIGGER_NAME,
            EVENT_MANIPULATION,
            EVENT_OBJECT_TABLE,
            ACTION_TIMING,
            ACTION_STATEMENT
        FROM INFORMATION_SCHEMA.TRIGGERS 
        WHERE EVENT_OBJECT_SCHEMA = DATABASE()
    ");
    
    $all_trigger_list = $all_triggers->fetchAll();
    
    $problematic_triggers = [];
    
    foreach ($all_trigger_list as $trigger) {
        // Verificar se o trigger faz referência a participantes.evento_id
        if (strpos(strtolower($trigger['ACTION_STATEMENT']), 'evento_id') !== false) {
            $problematic_triggers[] = $trigger;
        }
    }
    
    if (empty($problematic_triggers)) {
        echo "✅ Nenhum trigger problemático encontrado em outras tabelas<br>";
    } else {
        echo "⚠️ Triggers problemáticos encontrados:<br>";
        foreach ($problematic_triggers as $trigger) {
            echo "<h3>🔍 Trigger: " . $trigger['TRIGGER_NAME'] . " (tabela: " . $trigger['EVENT_OBJECT_TABLE'] . ")</h3>";
            echo "- Evento: " . $trigger['EVENT_MANIPULATION'] . "<br>";
            echo "- Timing: " . $trigger['ACTION_TIMING'] . "<br>";
            echo "- Código:<br>";
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px;'>";
            echo htmlspecialchars($trigger['ACTION_STATEMENT']);
            echo "</pre>";
            
            echo "<h4>🔧 Comando para remover este trigger:</h4>";
            echo "<code style='background: #fff3e0; padding: 5px; border-radius: 4px;'>";
            echo "DROP TRIGGER IF EXISTS " . $trigger['TRIGGER_NAME'] . ";";
            echo "</code><br>";
            echo "<hr>";
        }
    }
    
    echo "<h2>3. Script SQL para corrigir todos os triggers problemáticos</h2>";
    
    $sql_fix = "-- SQL para corrigir triggers com evento_id\n\n";
    
    // Coletar todos os triggers problemáticos
    $triggers_to_drop = [];
    foreach ($all_trigger_list as $trigger) {
        if (strpos(strtolower($trigger['ACTION_STATEMENT']), 'evento_id') !== false) {
            $triggers_to_drop[] = $trigger['TRIGGER_NAME'];
            $sql_fix .= "DROP TRIGGER IF EXISTS " . $trigger['TRIGGER_NAME'] . ";\n";
        }
    }
    
    if (!empty($triggers_to_drop)) {
        echo "<div style='background: #fff3e0; padding: 15px; border-radius: 8px;'>";
        echo "<h3>📝 Script SQL de correção:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px;'>";
        echo $sql_fix;
        echo "</pre>";
        
        echo "<h3>🚀 Executar correção automaticamente?</h3>";
        echo "<p>Os seguintes triggers serão removidos:</p>";
        echo "<ul>";
        foreach ($triggers_to_drop as $trigger_name) {
            echo "<li>" . $trigger_name . "</li>";
        }
        echo "</ul>";
        echo "</div>";
        
        // Executar a correção automaticamente
        echo "<h3>🔧 Executando correção...</h3>";
        foreach ($triggers_to_drop as $trigger_name) {
            try {
                echo "Removendo trigger: " . $trigger_name . "... ";
                executar_sql_debug("DROP TRIGGER IF EXISTS " . $trigger_name);
                echo "✅ Removido<br>";
            } catch (Exception $e) {
                echo "❌ Erro: " . $e->getMessage() . "<br>";
            }
        }
        
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; color: #2e7d32;'>";
        echo "<h3>✅ CORREÇÃO APLICADA!</h3>";
        echo "<p>Triggers problemáticos foram removidos. Agora tente cadastrar um participante novamente.</p>";
        echo "</div>";
        
    } else {
        echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; color: #1976d2;'>";
        echo "<h3>✅ Nenhum trigger problemático encontrado</h3>";
        echo "<p>Pode haver outro problema. Vamos investigar mais...</p>";
        echo "</div>";
    }
    
    echo "<h2>4. Testando inserção após correção</h2>";
    
    $dados_teste = [
        'nome' => 'Teste Após Correção',
        'cpf' => '00000000003',
        'whatsapp' => '11999999997',
        'email' => 'teste.correcao@teste.com',
        'idade' => 25,
        'cidade' => 'Teste',
        'senha' => password_hash('teste123', PASSWORD_DEFAULT)
    ];
    
    echo "Testando inserção com dados básicos...<br>";
    
    try {
        // Verificar se CPF existe e remover
        $existe = executar_sql_debug("SELECT id FROM participantes WHERE cpf = ?", [$dados_teste['cpf']]);
        if ($existe->fetch()) {
            echo "⚠️ CPF existe, removendo...<br>";
            executar_sql_debug("DELETE FROM participantes WHERE cpf = ?", [$dados_teste['cpf']]);
        }
        
        // Tentar inserir
        $campos = array_keys($dados_teste);
        $placeholders = ':' . implode(', :', $campos);
        $sql = "INSERT INTO participantes (" . implode(', ', $campos) . ") VALUES (" . $placeholders . ")";
        
        echo "SQL: " . $sql . "<br>";
        $stmt = executar_sql_debug($sql, $dados_teste);
        
        $pdo = conectar_banco();
        $id = $pdo->lastInsertId();
        
        echo "✅ <strong>SUCESSO!</strong> Participante inserido com ID: " . $id . "<br>";
        
        // Limpar
        executar_sql_debug("DELETE FROM participantes WHERE id = ?", [$id]);
        echo "🧹 Registro removido<br>";
        
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; color: #2e7d32;'>";
        echo "<h3>🎉 PROBLEMA RESOLVIDO!</h3>";
        echo "<p>A inserção de participantes está funcionando normalmente após a remoção dos triggers problemáticos.</p>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828;'>";
        echo "<h3>❌ Ainda há problema:</h3>";
        echo "Erro: " . $e->getMessage() . "<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 8px; color: #c62828;'>";
    echo "<h2>❌ ERRO GERAL NO DEBUG</h2>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "</div>";
}
?> 