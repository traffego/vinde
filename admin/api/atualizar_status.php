<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/funcoes.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

// Verificar autenticação (admin)
if (!isset($_SESSION['admin_logado'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

try {
    // Receber dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }
    
    $participante_id = $input['participante_id'] ?? null;
    $inscricao_id = $input['inscricao_id'] ?? null;
    $status_pagamento = $input['status_pagamento'] ?? null;
    $status_inscricao = $input['status_inscricao'] ?? null;
    $valor_pago = $input['valor_pago'] ?? null;
    $metodo_pagamento = $input['metodo_pagamento'] ?? null;
    
    if (!$participante_id) {
        throw new Exception('ID do participante é obrigatório');
    }
    
    // Verificar se existe tabela inscricoes
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
    
    if ($tabela_inscricoes_existe) {
        // Sistema novo - usar tabelas inscricoes e pagamentos
        
        // Buscar dados da inscrição
        $inscricao = buscar_um("
            SELECT i.*, p.nome as participante_nome
            FROM inscricoes i
            JOIN participantes p ON i.participante_id = p.id
            WHERE i.participante_id = ?
            ORDER BY i.id DESC
            LIMIT 1
        ", [$participante_id]);
        
        if (!$inscricao) {
            throw new Exception('Inscrição não encontrada');
        }
        
        $inscricao_id = $inscricao['id'];
        
        // Atualizar status da inscrição se fornecido
        if ($status_inscricao) {
            $sql_inscricao = "UPDATE inscricoes SET status = ?";
            $params_inscricao = [$status_inscricao];
            
            if ($valor_pago !== null) {
                $sql_inscricao .= ", valor_pago = ?";
                $params_inscricao[] = $valor_pago;
            }
            
            if ($metodo_pagamento) {
                $sql_inscricao .= ", metodo_pagamento = ?";
                $params_inscricao[] = $metodo_pagamento;
            }
            
            if ($status_inscricao === 'aprovada') {
                $sql_inscricao .= ", data_pagamento = NOW()";
            }
            
            $sql_inscricao .= " WHERE id = ?";
            $params_inscricao[] = $inscricao_id;
            
            executar($sql_inscricao, $params_inscricao);
            
            // Log da alteração
            registrar_log('sistema', 'status_inscricao_alterado', 
                "Inscrição ID: {$inscricao_id} | Status: {$status_inscricao} | Participante: {$inscricao['participante_nome']}");
        }
        
        // Atualizar ou criar pagamento se status_pagamento fornecido
        if ($status_pagamento) {
            // Verificar se já existe pagamento
            $pagamento = buscar_um("
                SELECT * FROM pagamentos 
                WHERE inscricao_id = ? OR participante_id = ?
                ORDER BY id DESC LIMIT 1
            ", [$inscricao_id, $participante_id]);
            
            if ($pagamento) {
                // Atualizar pagamento existente
                $sql_pagamento = "UPDATE pagamentos SET status = ?";
                $params_pagamento = [$status_pagamento];
                
                if ($valor_pago !== null) {
                    $sql_pagamento .= ", valor = ?";
                    $params_pagamento[] = $valor_pago;
                }
                
                if ($metodo_pagamento) {
                    $sql_pagamento .= ", metodo = ?";
                    $params_pagamento[] = $metodo_pagamento;
                }
                
                if ($status_pagamento === 'pago') {
                    $sql_pagamento .= ", pago_em = NOW()";
                }
                
                $sql_pagamento .= " WHERE id = ?";
                $params_pagamento[] = $pagamento['id'];
                
                executar($sql_pagamento, $params_pagamento);
                
                // Log da alteração
                registrar_log('sistema', 'status_pagamento_alterado', 
                    "Pagamento ID: {$pagamento['id']} | Status: {$status_pagamento} | Participante: {$inscricao['participante_nome']}");
            } else {
                // Criar novo pagamento
                $valor_pagamento = $valor_pago ?? $inscricao['valor_pago'] ?? 0;
                
                $pagamento_id = executar("
                    INSERT INTO pagamentos (participante_id, inscricao_id, valor, status, metodo, pago_em, criado_em)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ", [
                    $participante_id,
                    $inscricao_id,
                    $valor_pagamento,
                    $status_pagamento,
                    $metodo_pagamento ?? 'pix',
                    $status_pagamento === 'pago' ? date('Y-m-d H:i:s') : null
                ]);
                
                // Log da criação
                registrar_log('sistema', 'pagamento_criado', 
                    "Pagamento ID: {$pagamento_id} | Status: {$status_pagamento} | Participante: {$inscricao['participante_nome']}");
            }
        }
        
        // Atualizar status do participante baseado no pagamento
        if ($status_pagamento === 'pago' || $status_inscricao === 'aprovada') {
            executar("UPDATE participantes SET status = 'pago' WHERE id = ?", [$participante_id]);
        } elseif ($status_pagamento === 'cancelado' || $status_inscricao === 'cancelada') {
            executar("UPDATE participantes SET status = 'cancelado' WHERE id = ?", [$participante_id]);
        }
        
    } else {
        // Sistema antigo - usar apenas tabela participantes
        $sql_participante = "UPDATE participantes SET";
        $params_participante = [];
        $updates = [];
        
        if ($status_pagamento === 'pago') {
            $updates[] = "status = 'pago'";
        } elseif ($status_pagamento === 'cancelado') {
            $updates[] = "status = 'cancelado'";
        }
        
        if (!empty($updates)) {
            $sql_participante .= " " . implode(", ", $updates) . " WHERE id = ?";
            $params_participante[] = $participante_id;
            
            executar($sql_participante, $params_participante);
            
            // Log da alteração
            registrar_log('sistema', 'status_participante_alterado', 
                "Participante ID: {$participante_id} | Status: {$status_pagamento}");
        }
    }
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Status atualizado com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'erro' => $e->getMessage()
    ]);
}
?>