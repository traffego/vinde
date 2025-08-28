<?php
require_once '../../includes/init.php';

// Verificar login
requer_login();

// Definir header JSON
header('Content-Type: application/json');

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Receber dados
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }

    $participante_id = (int)($input['participante_id'] ?? 0);
    $novo_status = $input['status'] ?? '';
    $observacoes = $input['observacoes'] ?? '';

    // Log de debug para entrada
    error_log("API Pagamento - Dados recebidos: participante_id={$participante_id}, status={$novo_status}");
    
    // Validar dados
    if (!$participante_id) {
        error_log("API Pagamento - Erro: ID do participante é obrigatório");
        throw new Exception('ID do participante é obrigatório');
    }

    if (!in_array($novo_status, ['pendente', 'pago', 'cancelado', 'estornado'])) {
        error_log("API Pagamento - Erro: Status de pagamento inválido: {$novo_status}");
        throw new Exception('Status de pagamento inválido');
    }

    // Buscar dados do participante e pagamento
    $dados = buscar_um("
        SELECT 
            p.id as participante_id, p.nome, p.status as participante_status,
            i.id as inscricao_id, i.status as inscricao_status,
            pg.id as pagamento_id, pg.status as pagamento_status, pg.valor,
            e.nome as evento_nome
        FROM participantes p
        LEFT JOIN inscricoes i ON i.participante_id = p.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        LEFT JOIN eventos e ON i.evento_id = e.id
        WHERE p.id = ?
        ORDER BY i.id DESC
        LIMIT 1
    ", [$participante_id]);

    if (!$dados) {
        throw new Exception('Participante não encontrado');
    }

    // Se não há pagamento, criar um
    if (!$dados['pagamento_id'] && $dados['inscricao_id']) {
        $txid = 'ADMIN' . date('YmdHis') . str_pad($dados['inscricao_id'], 6, '0', STR_PAD_LEFT);
        $pagamento_id = inserir_registro('pagamentos', [
            'participante_id' => $participante_id,
            'inscricao_id' => $dados['inscricao_id'],
            'valor' => 0, // Será atualizado se necessário
            'status' => $novo_status,
            'metodo' => 'manual',
            'pix_txid' => $txid,
            'observacoes' => 'Criado via admin: ' . $observacoes
        ]);
        
        if (!$pagamento_id) {
            throw new Exception('Erro ao criar registro de pagamento');
        }
        
        $dados['pagamento_id'] = $pagamento_id;
    }

    // Atualizar status do pagamento
    if ($dados['pagamento_id']) {
        $dados_update = [
            'status' => $novo_status
        ];
        
        // Se está marcando como pago, definir data de pagamento
        if ($novo_status === 'pago' && $dados['pagamento_status'] !== 'pago') {
            error_log("API Pagamento - Definindo data de pagamento para status 'pago'");
            $dados_update['pago_em'] = date('Y-m-d H:i:s');
            error_log("API Pagamento - Data de pagamento definida: " . $dados_update['pago_em']);
        }
        
        // Adicionar observações se fornecidas
        if ($observacoes) {
            $obs_atual = buscar_um("SELECT observacoes FROM pagamentos WHERE id = ?", [$dados['pagamento_id']])['observacoes'] ?? '';
            $nova_obs = $obs_atual ? $obs_atual . "\n" . date('Y-m-d H:i:s') . " - " . $observacoes : date('Y-m-d H:i:s') . " - " . $observacoes;
            $dados_update['observacoes'] = $nova_obs;
        }
        
        error_log("API Pagamento - Atualizando pagamento ID: {$dados['pagamento_id']} com dados: " . json_encode($dados_update));
        $sucesso_pagamento = atualizar_registro('pagamentos', $dados_update, ['id' => $dados['pagamento_id']]);
        
        // Verificar se realmente houve erro ou se apenas não houve mudanças
        if (!$sucesso_pagamento) {
            // Verificar se o registro ainda existe e se os dados são os mesmos
            $pagamento_atual = buscar_um("SELECT status FROM pagamentos WHERE id = ?", [$dados['pagamento_id']]);
            if (!$pagamento_atual) {
                error_log("API Pagamento - Pagamento ID: {$dados['pagamento_id']} não encontrado");
                throw new Exception('Registro de pagamento não encontrado');
            }
            // Se o status já é o mesmo, não é erro
            if ($pagamento_atual['status'] !== $novo_status) {
                error_log("API Pagamento - Erro real ao atualizar pagamento ID: {$dados['pagamento_id']}");
                throw new Exception('Erro ao atualizar status do pagamento');
            }
            error_log("API Pagamento - Status do pagamento já era '{$novo_status}', nenhuma alteração necessária");
        }
        error_log("API Pagamento - Pagamento atualizado com sucesso");
    }

    // Atualizar status da inscrição baseado no pagamento
    if ($dados['inscricao_id']) {
        $novo_status_inscricao = $dados['inscricao_status'];
        
        switch ($novo_status) {
            case 'pago':
                $novo_status_inscricao = 'aprovada';
                break;
            case 'cancelado':
            case 'estornado':
                $novo_status_inscricao = 'cancelada';
                break;
            case 'pendente':
                $novo_status_inscricao = 'pendente';
                break;
        }
        
        if ($novo_status_inscricao !== $dados['inscricao_status']) {
            error_log("API Pagamento - Atualizando inscrição ID: {$dados['inscricao_id']} para status: {$novo_status_inscricao}");
            $dados_inscricao = [
                'status' => $novo_status_inscricao
            ];
            
            if ($novo_status === 'pago') {
                $dados_inscricao['data_pagamento'] = date('Y-m-d H:i:s');
            }
            
            $sucesso_inscricao = atualizar_registro('inscricoes', $dados_inscricao, ['id' => $dados['inscricao_id']]);
            
            // Verificar se realmente houve erro ou se apenas não houve mudanças
            if (!$sucesso_inscricao) {
                // Verificar se o registro ainda existe e se os dados são os mesmos
                $inscricao_atual = buscar_um("SELECT status FROM inscricoes WHERE id = ?", [$dados['inscricao_id']]);
                if (!$inscricao_atual) {
                    error_log("API Pagamento - Inscrição ID: {$dados['inscricao_id']} não encontrada");
                    throw new Exception('Registro de inscrição não encontrado');
                }
                // Se o status já é o mesmo, não é erro
                if ($inscricao_atual['status'] !== $novo_status_inscricao) {
                    error_log("API Pagamento - Erro real ao atualizar inscrição ID: {$dados['inscricao_id']}");
                    throw new Exception('Erro ao atualizar status da inscrição');
                }
                error_log("API Pagamento - Status da inscrição já era '{$novo_status_inscricao}', nenhuma alteração necessária");
            }
            error_log("API Pagamento - Inscrição atualizada com sucesso");
        }
    }

    // Atualizar status do participante
    $novo_status_participante = $dados['participante_status'];
    
    switch ($novo_status) {
        case 'pago':
            $novo_status_participante = 'pago';
            break;
        case 'cancelado':
        case 'estornado':
            $novo_status_participante = 'cancelado';
            break;
        case 'pendente':
            $novo_status_participante = 'inscrito';
            break;
    }
    
    if ($novo_status_participante !== $dados['participante_status']) {
        error_log("API Pagamento - Atualizando participante ID: {$participante_id} para status: {$novo_status_participante}");
        $sucesso_participante = atualizar_registro('participantes', [
            'status' => $novo_status_participante
        ], ['id' => $participante_id]);
        
        // Verificar se realmente houve erro ou se apenas não houve mudanças
        if (!$sucesso_participante) {
            // Verificar se o registro ainda existe e se os dados são os mesmos
            $participante_atual = buscar_um("SELECT status FROM participantes WHERE id = ?", [$participante_id]);
            if (!$participante_atual) {
                error_log("API Pagamento - Participante ID: {$participante_id} não encontrado");
                throw new Exception('Participante não encontrado');
            }
            // Se o status já é o mesmo, não é erro
            if ($participante_atual['status'] !== $novo_status_participante) {
                error_log("API Pagamento - Erro real ao atualizar participante ID: {$participante_id}");
                throw new Exception('Erro ao atualizar status do participante');
            }
            error_log("API Pagamento - Status do participante já era '{$novo_status_participante}', nenhuma alteração necessária");
        }
        error_log("API Pagamento - Participante atualizado com sucesso");
    }

    // Log de sucesso antes do registro final
    error_log("API Pagamento - Operação concluída com sucesso. Registrando log final.");
    
    // Registrar log da ação (com tratamento de erro)
    try {
        registrar_log('pagamento_atualizado_admin', 
            "Status de pagamento atualizado para '{$novo_status}' - Participante: {$dados['nome']} | Evento: {$dados['evento_nome']}", 
            $participante_id
        );
        error_log("API Pagamento - Log registrado com sucesso");
    } catch (Exception $log_error) {
        error_log("Erro ao registrar log de pagamento: " . $log_error->getMessage());
        // Não falhar a operação por causa do log
    }

    // Forçar flush do buffer de saída
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Status de pagamento atualizado com sucesso!',
        'novo_status' => $novo_status,
        'participante' => $dados['nome']
    ]);
    
    // Garantir que a resposta seja enviada
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

} catch (Exception $e) {
    error_log("Erro na API de atualização de pagamento: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>