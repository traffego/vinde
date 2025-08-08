<?php
// Debug melhorado que mostra erro específico
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Debug Melhorado - Cadastro Participante</h1>";

/**
 * Versão debug da função participante_criar_conta
 * SEM try/catch para ver o erro específico
 */
function participante_criar_conta_debug($dados) {
    echo "<h3>🔍 INÍCIO DA FUNÇÃO DEBUG</h3>";
    
    // Validações
    $erros = [];
    
    // Campos obrigatórios
    $campos_obrigatorios = ['nome', 'cpf', 'whatsapp', 'email', 'idade', 'cidade', 'senha'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($dados[$campo])) {
            $erros[] = "Campo '{$campo}' é obrigatório.";
        }
    }
    
    if (!empty($erros)) {
        return ['sucesso' => false, 'mensagem' => implode(' ', $erros)];
    }
    
    // Limpar e validar CPF
    $cpf = preg_replace('/[^0-9]/', '', $dados['cpf']);
    if (strlen($cpf) !== 11) {
        return ['sucesso' => false, 'mensagem' => 'CPF deve ter 11 dígitos.'];
    }
    
    echo "✅ CPF validado: " . $cpf . "<br>";
    
    // Verificar se CPF já existe
    if (participante_cpf_existe($cpf)) {
        return ['sucesso' => false, 'mensagem' => 'Este CPF já está cadastrado no sistema.'];
    }
    
    echo "✅ CPF não existe no sistema<br>";
    
    // Validar email
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        return ['sucesso' => false, 'mensagem' => 'Email inválido.'];
    }
    
    echo "✅ Email validado: " . $dados['email'] . "<br>";
    
    // Validar idade
    $idade = intval($dados['idade']);
    if ($idade < 1 || $idade > 120) {
        return ['sucesso' => false, 'mensagem' => 'Idade deve estar entre 1 e 120 anos.'];
    }
    
    echo "✅ Idade validada: " . $idade . "<br>";
    
    // Validar senha
    if (strlen($dados['senha']) < 6) {
        return ['sucesso' => false, 'mensagem' => 'Senha deve ter pelo menos 6 caracteres.'];
    }
    
    echo "✅ Senha validada (comprimento: " . strlen($dados['senha']) . ")<br>";
    
    // Preparar dados para inserção
    $participante_dados = [
        'nome' => sanitizar_entrada($dados['nome']),
        'cpf' => $cpf,
        'whatsapp' => preg_replace('/[^0-9]/', '', $dados['whatsapp']),
        'instagram' => sanitizar_entrada($dados['instagram'] ?? ''),
        'email' => sanitizar_entrada($dados['email']),
        'idade' => $idade,
        'cidade' => sanitizar_entrada($dados['cidade']),
        'estado' => sanitizar_entrada($dados['estado'] ?? 'SP'),
        'senha' => password_hash($dados['senha'], PASSWORD_DEFAULT)
    ];
    
    echo "<h4>📋 Dados preparados para inserção:</h4>";
    $dados_debug = $participante_dados;
    $dados_debug['senha'] = '*** HASH: ' . substr($participante_dados['senha'], 0, 20) . '...';
    echo "<pre>";
    print_r($dados_debug);
    echo "</pre>";
    
    echo "<h4>🗃️ EXECUTANDO INSERÇÃO (SEM TRY/CATCH):</h4>";
    
    // INSERÇÃO SEM TRY/CATCH - DEIXA O ERRO APARECER
    echo "Chamando inserir_registro('participantes', \$dados)...<br>";
    
    $participante_id = inserir_registro('participantes', $participante_dados);
    
    echo "✅ inserir_registro retornou: " . $participante_id . "<br>";
    
    if (!$participante_id) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao criar conta. Tente novamente.'];
    }
    
    // Log da criação
    registrar_log('participante_cadastrado', "Participante: {$dados['nome']} (CPF: {$cpf})");
    
    echo "✅ SUCESSO! Participante criado com ID: " . $participante_id . "<br>";
    
    return [
        'sucesso' => true, 
        'mensagem' => 'Conta criada com sucesso!',
        'participante_id' => $participante_id
    ];
}

try {
    echo "<h2>1. Dados de teste para debug</h2>";
    
    $dados_teste = [
        'nome' => 'Usuario Debug Melhorado',
        'cpf' => '11111111111',
        'whatsapp' => '21911111111',
        'email' => 'debug.melhorado@teste.com',
        'idade' => 28,
        'cidade' => 'Rio de Janeiro',
        'estado' => 'RJ',
        'senha' => 'senhateste123'
    ];
    
    echo "<pre>";
    print_r($dados_teste);
    echo "</pre>";
    
    echo "<h2>2. Verificando se CPF de teste já existe</h2>";
    $cpf_existe = participante_cpf_existe($dados_teste['cpf']);
    if ($cpf_existe) {
        echo "⚠️ CPF de teste existe, removendo primeiro...<br>";
        executar("DELETE FROM participantes WHERE cpf = ?", [$dados_teste['cpf']]);
        echo "🧹 CPF removido<br>";
    } else {
        echo "✅ CPF de teste não existe<br>";
    }
    
    echo "<h2>3. EXECUTANDO FUNÇÃO DEBUG (sem try/catch)</h2>";
    echo "<div style='background: #fffacd; padding: 15px; border: 2px solid #ddd; border-radius: 8px;'>";
    
    $resultado = participante_criar_conta_debug($dados_teste);
    
    echo "</div>";
    
    echo "<h2>4. Resultado da função debug</h2>";
    echo "<pre>";
    print_r($resultado);
    echo "</pre>";
    
    // Se criou com sucesso, remover para não deixar lixo
    if ($resultado['sucesso'] && isset($resultado['participante_id'])) {
        executar("DELETE FROM participantes WHERE id = ?", [$resultado['participante_id']]);
        echo "🧹 Registro de teste removido<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 8px; color: #c62828; border: 3px solid #f44336;'>";
    echo "<h2>🚨 ERRO ESPECÍFICO CAPTURADO!</h2>";
    echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Código:</strong> " . $e->getCode() . "<br>";
    echo "<strong>Arquivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Linha:</strong> " . $e->getLine() . "<br>";
    
    if ($e instanceof PDOException) {
        echo "<strong>Informações PDO:</strong><br>";
        echo "SQL State: " . $e->errorInfo[0] . "<br>";
        echo "Driver Code: " . $e->errorInfo[1] . "<br>";
        echo "Driver Message: " . $e->errorInfo[2] . "<br>";
    }
    
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
    
    echo "<h3>🔧 ESTE É O ERRO REAL QUE ESTAVA SENDO OCULTADO!</h3>";
}
?> 