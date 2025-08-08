<?php
// Debug que mostra o ERRO SQL REAL (sem mascaramento)
require_once '../includes/init.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚨 Debug Erro SQL REAL</h1>";

/**
 * Função que NÃO mascara o erro SQL
 */
function executar_consulta_debug($sql, $params = []) {
    echo "<h3>🔍 EXECUTANDO SQL DEBUG</h3>";
    echo "<strong>SQL:</strong> " . $sql . "<br>";
    echo "<strong>Parâmetros:</strong> ";
    print_r($params);
    echo "<br>";
    
    try {
        $pdo = conectar_banco();
        $stmt = $pdo->prepare($sql);
        
        echo "✅ Prepare executado com sucesso<br>";
        
        $stmt->execute($params);
        
        echo "✅ Execute executado com sucesso<br>";
        
        return $stmt;
        
    } catch(PDOException $e) {
        echo "<div style='background: #ffebee; padding: 20px; border-radius: 8px; color: #c62828; border: 3px solid #f44336;'>";
        echo "<h2>🚨 ERRO SQL REAL CAPTURADO!</h2>";
        echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
        echo "<strong>Código SQL:</strong> " . $e->getCode() . "<br>";
        echo "<strong>SQL State:</strong> " . $e->errorInfo[0] . "<br>";
        echo "<strong>Driver Code:</strong> " . $e->errorInfo[1] . "<br>";
        echo "<strong>Driver Message:</strong> " . $e->errorInfo[2] . "<br>";
        echo "<strong>SQL:</strong> " . $sql . "<br>";
        echo "<strong>Parâmetros:</strong> ";
        print_r($params);
        echo "</div>";
        
        // Re-throw para manter o fluxo, mas agora já mostramos o erro
        throw $e;
    }
}

/**
 * Inserir registro DEBUG sem mascaramento
 */
function inserir_registro_debug($tabela, $dados) {
    $campos = array_keys($dados);
    $placeholders = ':' . implode(', :', $campos);
    $sql = "INSERT INTO {$tabela} (" . implode(', ', $campos) . ") VALUES ({$placeholders})";
    
    echo "<h3>📝 PREPARANDO INSERÇÃO DEBUG</h3>";
    echo "Tabela: " . $tabela . "<br>";
    echo "Campos: " . implode(', ', $campos) . "<br>";
    echo "SQL gerado: " . $sql . "<br>";
    
    $stmt = executar_consulta_debug($sql, $dados);
    
    $pdo = conectar_banco();
    $id = $pdo->lastInsertId();
    
    echo "✅ ID gerado: " . $id . "<br>";
    
    return $id;
}

try {
    echo "<h2>1. Testando estrutura da tabela participantes</h2>";
    
    $descricao = executar_consulta_debug("DESCRIBE participantes", []);
    $colunas = $descricao->fetchAll();
    
    echo "✅ Tabela participantes acessível<br>";
    echo "Colunas: ";
    foreach ($colunas as $coluna) {
        echo $coluna['Field'] . " (" . $coluna['Type'] . "), ";
    }
    echo "<br><br>";
    
    echo "<h2>2. Verificando constraints ativos</h2>";
    
    // SQL mais simples para listar constraints
    $constraints = executar_consulta_debug("
        SELECT 
            COLUMN_NAME,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            COLUMN_KEY
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'participantes'
        ORDER BY ORDINAL_POSITION
    ", []);
    
    $constraint_info = $constraints->fetchAll();
    
    echo "📋 Informações das colunas:<br>";
    foreach ($constraint_info as $info) {
        echo "- " . $info['COLUMN_NAME'] . " | Nulo: " . $info['IS_NULLABLE'] . " | Chave: " . $info['COLUMN_KEY'] . " | Padrão: " . $info['COLUMN_DEFAULT'] . "<br>";
    }
    
    echo "<h2>3. Testando inserção com dados mínimos</h2>";
    
    $dados_minimos = [
        'nome' => 'Teste Minimo',
        'cpf' => '00000000001',
        'whatsapp' => '11999999999',
        'email' => 'teste.minimo@teste.com',
        'idade' => 25,
        'cidade' => 'Teste'
    ];
    
    echo "Dados mínimos (sem senha, sem estado):<br>";
    echo "<pre>";
    print_r($dados_minimos);
    echo "</pre>";
    
    // Verificar se CPF existe e remover
    $existe = executar_consulta_debug("SELECT id FROM participantes WHERE cpf = ?", [$dados_minimos['cpf']]);
    if ($existe->fetch()) {
        echo "⚠️ CPF existe, removendo...<br>";
        executar_consulta_debug("DELETE FROM participantes WHERE cpf = ?", [$dados_minimos['cpf']]);
    }
    
    $id_inserido = inserir_registro_debug('participantes', $dados_minimos);
    
    if ($id_inserido) {
        echo "✅ SUCESSO com dados mínimos! ID: " . $id_inserido . "<br>";
        
        // Limpar
        executar_consulta_debug("DELETE FROM participantes WHERE id = ?", [$id_inserido]);
        echo "🧹 Removido<br>";
    }
    
    echo "<h2>4. Testando inserção com TODOS os campos</h2>";
    
    $dados_completos = [
        'nome' => 'Teste Completo',
        'cpf' => '00000000002',
        'whatsapp' => '11999999998',
        'instagram' => '@teste',
        'email' => 'teste.completo@teste.com',
        'idade' => 30,
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'tipo' => 'normal',
        'status' => 'inscrito',
        'senha' => password_hash('teste123', PASSWORD_DEFAULT)
    ];
    
    echo "Dados completos:<br>";
    $dados_debug = $dados_completos;
    $dados_debug['senha'] = '*** HASH ***';
    echo "<pre>";
    print_r($dados_debug);
    echo "</pre>";
    
    // Verificar se CPF existe e remover
    $existe = executar_consulta_debug("SELECT id FROM participantes WHERE cpf = ?", [$dados_completos['cpf']]);
    if ($existe->fetch()) {
        echo "⚠️ CPF existe, removendo...<br>";
        executar_consulta_debug("DELETE FROM participantes WHERE cpf = ?", [$dados_completos['cpf']]);
    }
    
    $id_inserido = inserir_registro_debug('participantes', $dados_completos);
    
    if ($id_inserido) {
        echo "✅ SUCESSO com dados completos! ID: " . $id_inserido . "<br>";
        
        // Limpar
        executar_consulta_debug("DELETE FROM participantes WHERE id = ?", [$id_inserido]);
        echo "🧹 Removido<br>";
    }
    
    echo "<h2>✅ DIAGNÓSTICO COMPLETO</h2>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; color: #2e7d32;'>";
    echo "<strong>Este debug mostra:</strong><br>";
    echo "1. O erro SQL EXATO (sem mascaramento)<br>";
    echo "2. Qual campo está causando problema<br>";
    echo "3. Se é constraint, tipo de dado ou campo obrigatório<br>";
    echo "4. Diferença entre inserção mínima vs completa<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 8px; color: #c62828; border: 3px solid #f44336;'>";
    echo "<h2>🚨 ESTE É O ERRO REAL!</h2>";
    echo "<strong>Tipo:</strong> " . get_class($e) . "<br>";
    echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Código:</strong> " . $e->getCode() . "<br>";
    echo "<strong>Arquivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Linha:</strong> " . $e->getLine() . "<br>";
    
    if ($e instanceof PDOException) {
        echo "<h3>🔍 DETALHES PDO:</h3>";
        echo "SQL State: " . $e->errorInfo[0] . "<br>";
        echo "Driver Code: " . $e->errorInfo[1] . "<br>";
        echo "Driver Message: " . $e->errorInfo[2] . "<br>";
    }
    
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?> 