<?php
// Conexão com Banco de Dados
// Arquivo: includes/database.php

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

// Variável global para conexão
$pdo = null;

/**
 * Conecta ao banco de dados MySQL usando PDO
 * @return PDO Instância da conexão
 */
function conectar_banco() {
    global $pdo;
    
    // Reutilizar conexão existente
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        return $pdo;
        
    } catch(PDOException $e) {
        error_log("Erro de conexão com banco: " . $e->getMessage());
        
        if (defined('AMBIENTE') && AMBIENTE === 'desenvolvimento') {
            die("Erro de conexão: " . $e->getMessage());
        } else {
            die("Erro interno do servidor. Tente novamente mais tarde.");
        }
    }
}

/**
 * Executa uma consulta SQL preparada
 * @param string $sql Consulta SQL
 * @param array $params Parâmetros da consulta
 * @return PDOStatement
 */
function executar_consulta($sql, $params = []) {
    try {
        $pdo = conectar_banco();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Erro na consulta SQL: " . $e->getMessage() . " | SQL: " . $sql);
        throw new Exception("Erro na operação do banco de dados");
    }
}

/**
 * Busca um único registro
 * @param string $sql Consulta SQL
 * @param array $params Parâmetros
 * @return array|false
 */
function buscar_um($sql, $params = []) {
    $stmt = executar_consulta($sql, $params);
    return $stmt->fetch();
}

/**
 * Busca múltiplos registros
 * @param string $sql Consulta SQL
 * @param array $params Parâmetros
 * @return array
 */
function buscar_todos($sql, $params = []) {
    $stmt = executar_consulta($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insere um registro e retorna o ID
 * @param string $tabela Nome da tabela
 * @param array $dados Array associativo com os dados
 * @return int ID do registro inserido
 */
function inserir_registro($tabela, $dados) {
    $campos = array_keys($dados);
    $placeholders = ':' . implode(', :', $campos);
    $sql = "INSERT INTO {$tabela} (" . implode(', ', $campos) . ") VALUES ({$placeholders})";
    
    executar_consulta($sql, $dados);
    
    $pdo = conectar_banco();
    return $pdo->lastInsertId();
}

/**
 * Atualiza um registro
 * @param string $tabela Nome da tabela
 * @param array $dados Dados para atualizar
 * @param array $condicoes Condições WHERE
 * @return bool
 */
function atualizar_registro($tabela, $dados, $condicoes) {
    $set_campos = [];
    foreach (array_keys($dados) as $campo) {
        $set_campos[] = "{$campo} = :{$campo}";
    }
    
    $where_campos = [];
    $where_params = [];
    foreach ($condicoes as $campo => $valor) {
        $where_campos[] = "{$campo} = :where_{$campo}";
        $where_params["where_{$campo}"] = $valor;
    }
    
    $sql = "UPDATE {$tabela} SET " . implode(', ', $set_campos) . 
           " WHERE " . implode(' AND ', $where_campos);
    
    $params = array_merge($dados, $where_params);
    $stmt = executar_consulta($sql, $params);
    
    return $stmt->rowCount() > 0;
}

/**
 * Remove um registro
 * @param string $tabela Nome da tabela
 * @param array $condicoes Condições WHERE
 * @return bool
 */
function remover_registro($tabela, $condicoes) {
    $where_campos = [];
    foreach (array_keys($condicoes) as $campo) {
        $where_campos[] = "{$campo} = :{$campo}";
    }
    
    $sql = "DELETE FROM {$tabela} WHERE " . implode(' AND ', $where_campos);
    $stmt = executar_consulta($sql, $condicoes);
    
    return $stmt->rowCount() > 0;
}

/**
 * Conta registros de uma tabela
 * @param string $tabela Nome da tabela
 * @param array $condicoes Condições WHERE opcionais
 * @return int
 */
function contar_registros($tabela, $condicoes = []) {
    $sql = "SELECT COUNT(*) FROM {$tabela}";
    
    if (!empty($condicoes)) {
        $where_campos = [];
        foreach (array_keys($condicoes) as $campo) {
            $where_campos[] = "{$campo} = :{$campo}";
        }
        $sql .= " WHERE " . implode(' AND ', $where_campos);
    }
    
    $stmt = executar_consulta($sql, $condicoes);
    return (int) $stmt->fetchColumn();
}

/**
 * Inicia uma transação
 */
function iniciar_transacao() {
    $pdo = conectar_banco();
    return $pdo->beginTransaction();
}

/**
 * Confirma uma transação
 */
function confirmar_transacao() {
    $pdo = conectar_banco();
    return $pdo->commit();
}

/**
 * Desfaz uma transação
 */
function desfazer_transacao() {
    $pdo = conectar_banco();
    return $pdo->rollBack();
}

/**
 * Verifica se uma transação está ativa
 */
function transacao_ativa() {
    $pdo = conectar_banco();
    return $pdo->inTransaction();
}

/**
 * Executa uma consulta SQL e retorna se foi bem-sucedida (wrapper para compatibilidade)
 * @param string $sql Consulta SQL
 * @param array $params Parâmetros da consulta
 * @return bool True se executou com sucesso, false se houve erro
 */
function executar($sql, $params = []) {
    try {
        $stmt = executar_consulta($sql, $params);
        return $stmt->rowCount() >= 0; // Retorna true se executou, mesmo que não afetou linhas
    } catch (Exception $e) {
        error_log("Erro na função executar(): " . $e->getMessage() . " | SQL: " . $sql);
        return false;
    }
}

/**
 * Fecha a conexão com o banco
 */
function fechar_conexao() {
    global $pdo;
    $pdo = null;
}

?> 