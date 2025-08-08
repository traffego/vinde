<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Configurar resposta JSON
header('Content-Type: application/json');

// Verificar se participante está logado
if (!participante_esta_logado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Obter dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['inscricao_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }
    
    $inscricao_id = (int)$input['inscricao_id'];
    $pagamento_id = isset($input['pagamento_id']) ? (int)$input['pagamento_id'] : null;
    
    $participante_logado = obter_participante_logado();
    
    // Verificar se a inscrição pertence ao participante logado
    $inscricao = buscar_um("
        SELECT i.*, e.nome as evento_nome, e.valor as evento_valor
        FROM inscricoes i
        JOIN eventos e ON i.evento_id = e.id
        WHERE i.id = ? AND i.participante_id = ?
    ", [$inscricao_id, $participante_logado['id']]);
    
    if (!$inscricao) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Inscrição não encontrada']);
        exit;
    }
    
    // Se evento é gratuito, marcar como aprovado
    if ($inscricao['evento_valor'] <= 0) {
        if ($inscricao['status'] !== 'aprovada') {
            atualizar_registro('inscricoes', ['status' => 'aprovada'], ['id' => $inscricao_id]);
        }
        
        echo json_encode([
            'success' => true,
            'pago' => true,
            'message' => 'Inscrição confirmada (evento gratuito)'
        ]);
        exit;
    }
    
    // Verificar status do pagamento
    $pagamento = buscar_um("
        SELECT * FROM pagamentos 
        WHERE inscricao_id = ? AND status = 'pago'
    ", [$inscricao_id]);
    
    if ($pagamento) {
        // Pagamento confirmado - atualizar status da inscrição
        if ($inscricao['status'] !== 'aprovada') {
            atualizar_registro('inscricoes', [
                'status' => 'aprovada',
                'data_pagamento' => date('Y-m-d H:i:s')
            ], ['id' => $inscricao_id]);
            
            // Log da aprovação
            registrar_log('inscricao_aprovada_pagamento', "Inscrição ID: {$inscricao_id} aprovada via pagamento");
        }
        
        echo json_encode([
            'success' => true,
            'pago' => true,
            'message' => 'Pagamento confirmado!'
        ]);
    } else {
        // Verificar se há algum pagamento pendente
        $pagamento_pendente = buscar_um("
            SELECT * FROM pagamentos 
            WHERE inscricao_id = ? AND status = 'pendente'
        ", [$inscricao_id]);
        
        if ($pagamento_pendente) {
            // Se existe TXID, tentar verificar com EFI Bank
            if (!empty($pagamento_pendente['pix_txid'])) {
                $status_efi = verificar_pagamento_efi($pagamento_pendente['pix_txid']);
                
                if ($status_efi && $status_efi['status'] === 'CONCLUIDA') {
                    // Pagamento confirmado na EFI
                    atualizar_registro('pagamentos', [
                        'status' => 'pago',
                        'pago_em' => date('Y-m-d H:i:s')
                    ], ['id' => $pagamento_pendente['id']]);
                    
                    // Atualizar inscrição
                    atualizar_registro('inscricoes', [
                        'status' => 'aprovada',
                        'data_pagamento' => date('Y-m-d H:i:s')
                    ], ['id' => $inscricao_id]);
                    
                    registrar_log('pagamento_confirmado_efi', "Pagamento ID: {$pagamento_pendente['id']} confirmado via EFI Bank");
                    
                    echo json_encode([
                        'success' => true,
                        'pago' => true,
                        'message' => 'Pagamento confirmado!'
                    ]);
                    exit;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'pago' => false,
            'message' => 'Pagamento ainda não foi identificado'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro na API de verificação de pagamento: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

/**
 * Verificar pagamento na EFI Bank
 */
function verificar_pagamento_efi($txid) {
    try {
        // Verificar se EFI Bank está ativo
        $efi_ativo = obter_configuracao('efi_ativo', '0') === '1';
        if (!$efi_ativo) {
            return null;
        }
        
        // Chamar função da EFI Bank se existir
        if (function_exists('efi_consultar_cobranca')) {
            return efi_consultar_cobranca($txid);
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar pagamento EFI: " . $e->getMessage());
        return null;
    }
}
?> 