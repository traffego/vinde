<?php
// Debug específico do erro SQL
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug SQL - Erro de Inserção</h1>";

try {
    echo "<h2>1. Verificando constraints e índices da tabela participantes</h2>";
    
    $constraints = buscar_todos("
        SELECT 
            CONSTRAINT_NAME,
            CONSTRAINT_TYPE,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'participantes'
    ");
    
    echo "📋 Constraints encontradas:<br>";
    foreach ($constraints as $constraint) {
        echo "- " . $constraint['CONSTRAINT_NAME'] . " (" . $constraint['CONSTRAINT_TYPE'] . ") em " . $constraint['COLUMN_NAME'] . "<br>";
    }
    
    echo "<h2>2. Verificando se existe foreign key problemática</h2>";
    
    $foreign_keys = buscar_todos("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'participantes'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if (!empty($foreign_keys)) {
        echo "❌ Foreign keys encontradas (podem estar causando problema):<br>";
        foreach ($foreign_keys as $fk) {
            echo "- " . $fk['CONSTRAINT_NAME'] . ": " . $fk['COLUMN_NAME'] . " → " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "<br>";
        }
    } else {
        echo "✅ Nenhuma foreign key encontrada<br>";
    }
    
    echo "<h2>3. Testando inserção SQL direta com erro detalhado</h2>";
    
    $dados_teste = [
        'nome' => 'Teste SQL Debug',
        'cpf' => '99999999998',
        'whatsapp' => '21999999998',
        'email' => 'teste.sql@exemplo.com',
        'idade' => 25,
        'cidade' => 'Cidade SQL',
        'estado' => 'RJ',
        'senha' => password_hash('senha123', PASSWORD_DEFAULT)
    ];
    
    echo "<h3>Dados para inserção:</h3>";
    echo "<pre>";
    print_r($dados_teste);
    echo "</pre>";
    
    // Primeiro, verificar se CPF já existe
    $cpf_existe = buscar_um("SELECT id FROM participantes WHERE cpf = ?", [$dados_teste['cpf']]);
    if ($cpf_existe) {
        echo "⚠️ CPF de teste já existe, removendo...<br>";
        executar("DELETE FROM participantes WHERE cpf = ?", [$dados_teste['cpf']]);
    }
    
    echo "<h3>Tentativa 1: INSERT manual</h3>";
    try {
        $sql = "INSERT INTO participantes (nome, cpf, whatsapp, email, idade, cidade, estado, senha) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $dados_teste['nome'],
            $dados_teste['cpf'],
            $dados_teste['whatsapp'],
            $dados_teste['email'],
            $dados_teste['idade'],
            $dados_teste['cidade'],
            $dados_teste['estado'],
            $dados_teste['senha']
        ];
        
        echo "SQL: " . $sql . "<br>";
        echo "Parâmetros: ";
        $params_debug = $params;
        $params_debug[7] = '*** (senha hash)';
        print_r($params_debug);
        echo "<br>";
        
        $stmt = executar($sql, $params);
        $id_inserido = $GLOBALS['pdo']->lastInsertId();
        
        echo "✅ INSERT manual funcionou! ID: " . $id_inserido . "<br>";
        
        // Remover teste
        executar("DELETE FROM participantes WHERE id = ?", [$id_inserido]);
        echo "🧹 Registro removido<br>";
        
    } catch (Exception $e) {
        echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828;'>";
        echo "<strong>❌ ERRO no INSERT manual:</strong><br>";
        echo "Mensagem: " . $e->getMessage() . "<br>";
        echo "Código: " . $e->getCode() . "<br>";
        
        if (method_exists($e, 'errorInfo')) {
            echo "Error Info: ";
            print_r($e->errorInfo);
        }
        echo "</div>";
    }
    
    echo "<h3>Tentativa 2: Usando inserir_registro()</h3>";
    try {
        $dados_teste['cpf'] = '99999999997'; // CPF diferente
        
        echo "Dados para inserir_registro():<br>";
        $dados_debug = $dados_teste;
        $dados_debug['senha'] = '*** (hash)';
        echo "<pre>";
        print_r($dados_debug);
        echo "</pre>";
        
        $id_inserido = inserir_registro('participantes', $dados_teste);
        
        if ($id_inserido) {
            echo "✅ inserir_registro() funcionou! ID: " . $id_inserido . "<br>";
            // Remover teste
            executar("DELETE FROM participantes WHERE id = ?", [$id_inserido]);
            echo "🧹 Registro removido<br>";
        } else {
            echo "❌ inserir_registro() retornou false<br>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828;'>";
        echo "<strong>❌ ERRO no inserir_registro():</strong><br>";
        echo "Mensagem: " . $e->getMessage() . "<br>";
        echo "Código: " . $e->getCode() . "<br>";
        echo "</div>";
    }
    
    echo "<h2>4. Verificando função inserir_registro</h2>";
    
    $reflection = new ReflectionFunction('inserir_registro');
    echo "Arquivo: " . $reflection->getFileName() . "<br>";
    echo "Linha: " . $reflection->getStartLine() . "<br>";
    
    echo "<h2>5. Verificando todos os participantes existentes</h2>";
    
    $participantes = buscar_todos("SELECT id, nome, cpf FROM participantes ORDER BY id DESC LIMIT 5");
    echo "Últimos 5 participantes:<br>";
    foreach ($participantes as $p) {
        echo "- ID: " . $p['id'] . " | Nome: " . $p['nome'] . " | CPF: " . $p['cpf'] . "<br>";
    }
    
    echo "<h2>6. Verificando estrutura pós-migração</h2>";
    
    // Verificar se ainda existe evento_id
    $tem_evento_id = buscar_um("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'participantes' 
        AND COLUMN_NAME = 'evento_id'
    ");
    
    if ($tem_evento_id['count'] > 0) {
        echo "❌ PROBLEMA: Campo evento_id ainda existe na tabela participantes!<br>";
        echo "A migração não foi completada corretamente.<br>";
    } else {
        echo "✅ Campo evento_id foi removido corretamente<br>";
    }
    
    echo "<h2>✅ DIAGNÓSTICO COMPLETO</h2>";
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; color: #1976d2;'>";
    echo "<strong>Este debug identifica:</strong><br>";
    echo "1. Se há foreign keys problemáticas<br>";
    echo "2. O erro SQL exato da inserção<br>";
    echo "3. Se o problema é na função inserir_registro<br>";
    echo "4. Se a migração foi completa<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO GERAL NO DEBUG SQL</h2>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 