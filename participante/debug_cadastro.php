<?php
// Debug do cadastro de participante
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug - Cadastro de Participante</h1>";

try {
    echo "<h2>1. Testando estrutura da tabela participantes</h2>";
    
    $estrutura = buscar_todos("DESCRIBE participantes");
    echo "✅ Estrutura da tabela participantes:<br>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    foreach ($estrutura as $campo) {
        echo "<tr>";
        echo "<td>" . $campo['Field'] . "</td>";
        echo "<td>" . $campo['Type'] . "</td>";
        echo "<td>" . $campo['Null'] . "</td>";
        echo "<td>" . $campo['Key'] . "</td>";
        echo "<td>" . ($campo['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>2. Verificando se campos obrigatórios existem</h2>";
    
    $campos_necessarios = ['nome', 'cpf', 'whatsapp', 'email', 'idade', 'cidade', 'senha'];
    $campos_existentes = array_column($estrutura, 'Field');
    
    foreach ($campos_necessarios as $campo) {
        if (in_array($campo, $campos_existentes)) {
            echo "✅ Campo '{$campo}' existe<br>";
        } else {
            echo "❌ Campo '{$campo}' NÃO existe<br>";
        }
    }
    
    echo "<h2>3. Testando inserção de dados simulada</h2>";
    
    $dados_teste = [
        'nome' => 'Teste Usuario Debug',
        'cpf' => '99999999999', // CPF fictício para teste
        'whatsapp' => '21999999999',
        'email' => 'teste.debug@exemplo.com',
        'idade' => 30,
        'cidade' => 'Cidade Teste',
        'estado' => 'RJ',
        'senha' => 'senha123'
    ];
    
    echo "<h3>Dados de teste:</h3>";
    echo "<pre>";
    print_r($dados_teste);
    echo "</pre>";
    
    echo "<h3>Verificando se CPF de teste já existe:</h3>";
    $cpf_existe = participante_cpf_existe($dados_teste['cpf']);
    echo "CPF 99999999999 existe: " . ($cpf_existe ? 'SIM' : 'NÃO') . "<br>";
    
    echo "<h3>Testando validações da função:</h3>";
    
    // Teste 1: Campos obrigatórios
    echo "<strong>Teste 1 - Campos obrigatórios:</strong><br>";
    $campos_obrigatorios = ['nome', 'cpf', 'whatsapp', 'email', 'idade', 'cidade', 'senha'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($dados_teste[$campo])) {
            echo "❌ Campo '{$campo}' vazio<br>";
        } else {
            echo "✅ Campo '{$campo}' preenchido<br>";
        }
    }
    
    // Teste 2: Validação CPF
    echo "<strong>Teste 2 - Validação CPF:</strong><br>";
    $cpf_limpo = preg_replace('/[^0-9]/', '', $dados_teste['cpf']);
    if (strlen($cpf_limpo) === 11) {
        echo "✅ CPF tem 11 dígitos<br>";
    } else {
        echo "❌ CPF não tem 11 dígitos: " . strlen($cpf_limpo) . "<br>";
    }
    
    // Teste 3: Validação email
    echo "<strong>Teste 3 - Validação email:</strong><br>";
    if (filter_var($dados_teste['email'], FILTER_VALIDATE_EMAIL)) {
        echo "✅ Email válido<br>";
    } else {
        echo "❌ Email inválido<br>";
    }
    
    // Teste 4: Validação idade
    echo "<strong>Teste 4 - Validação idade:</strong><br>";
    $idade = intval($dados_teste['idade']);
    if ($idade >= 1 && $idade <= 120) {
        echo "✅ Idade válida: " . $idade . "<br>";
    } else {
        echo "❌ Idade inválida: " . $idade . "<br>";
    }
    
    // Teste 5: Validação senha
    echo "<strong>Teste 5 - Validação senha:</strong><br>";
    if (strlen($dados_teste['senha']) >= 6) {
        echo "✅ Senha tem pelo menos 6 caracteres<br>";
    } else {
        echo "❌ Senha muito curta: " . strlen($dados_teste['senha']) . " caracteres<br>";
    }
    
    echo "<h2>4. Tentando executar participante_criar_conta() com dados de teste</h2>";
    
    if (!$cpf_existe) {
        echo "<strong>Executando função participante_criar_conta()...</strong><br>";
        
        try {
            $resultado = participante_criar_conta($dados_teste);
            
            echo "<div style='background: #f0fff0; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<strong>Resultado da função:</strong><br>";
            echo "Sucesso: " . ($resultado['sucesso'] ? 'SIM' : 'NÃO') . "<br>";
            echo "Mensagem: " . $resultado['mensagem'] . "<br>";
            if (isset($resultado['participante_id'])) {
                echo "ID criado: " . $resultado['participante_id'] . "<br>";
            }
            echo "</div>";
            
            // Se deu certo, remover o registro de teste
            if ($resultado['sucesso'] && isset($resultado['participante_id'])) {
                executar("DELETE FROM participantes WHERE id = ?", [$resultado['participante_id']]);
                echo "🧹 Registro de teste removido<br>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; margin: 10px 0; color: #c62828;'>";
            echo "<strong>❌ ERRO CAPTURADO:</strong><br>";
            echo "Mensagem: " . $e->getMessage() . "<br>";
            echo "Arquivo: " . $e->getFile() . "<br>";
            echo "Linha: " . $e->getLine() . "<br>";
            echo "<strong>Stack Trace:</strong><br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
            echo "</div>";
        }
    } else {
        echo "⚠️ CPF de teste já existe, não executando inserção<br>";
    }
    
    echo "<h2>5. Verificando função inserir_registro</h2>";
    
    if (function_exists('inserir_registro')) {
        echo "✅ Função inserir_registro existe<br>";
        
        // Testar inserção direta simples
        echo "<strong>Testando inserção direta:</strong><br>";
        try {
            $dados_simples = [
                'nome' => 'Teste Direto',
                'cpf' => '88888888888',
                'whatsapp' => '21888888888',
                'email' => 'teste.direto@exemplo.com',
                'idade' => 25,
                'cidade' => 'Cidade Teste',
                'estado' => 'RJ',
                'senha' => password_hash('senha123', PASSWORD_DEFAULT)
            ];
            
            $id_teste = inserir_registro('participantes', $dados_simples);
            
            if ($id_teste) {
                echo "✅ Inserção direta funcionou! ID: " . $id_teste . "<br>";
                // Remover teste
                executar("DELETE FROM participantes WHERE id = ?", [$id_teste]);
                echo "🧹 Registro de teste direto removido<br>";
            } else {
                echo "❌ Inserção direta falhou<br>";
            }
            
        } catch (Exception $e) {
            echo "❌ Erro na inserção direta: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ Função inserir_registro NÃO existe<br>";
    }
    
    echo "<h2>✅ RESUMO DO DEBUG</h2>";
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; color: #1976d2;'>";
    echo "<strong>Este debug mostra:</strong><br>";
    echo "1. Se a tabela participantes tem todos os campos necessários<br>";
    echo "2. Se as validações estão funcionando<br>";
    echo "3. Onde exatamente está o erro na função de cadastro<br>";
    echo "4. Se o problema é na validação ou na inserção no banco<br>";
    echo "<br><strong>Analise os resultados acima para identificar o problema específico.</strong>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO GERAL NO DEBUG</h2>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 