<?php
/**
 * Funções para geração e validação de QR Codes
 * Sistema de Inscrições Católicas - Vinde
 */

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Gerar dados para QR Code
 */
function gerar_qr_code_data($conteudo) {
    // Em um sistema real, isso seria convertido para imagem
    // Por ora, retornamos os dados como string para usar com bibliotecas JS
    return base64_encode($conteudo);
}

/**
 * Calcular CRC16 para PIX
 */
function calcular_crc16($payload) {
    $crc = 0xFFFF;
    $polynomial = 0x1021;
    
    for ($i = 0; $i < strlen($payload); $i++) {
        $crc ^= (ord($payload[$i]) << 8);
        
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    
    return str_pad(strtoupper(dechex($crc)), 4, '0', STR_PAD_LEFT);
}

/**
 * Gerar código PIX no formato EMV
 */
function gerar_codigo_pix($valor, $descricao, $nome_participante) {
    // Formato EMV do PIX
    $payload = '';
    
    // Payload Format Indicator
    $payload .= '00' . '02' . '01';
    
    // Point of Initiation Method
    $payload .= '01' . '02' . '12';
    
    // Merchant Account Information
    $chave_pix = PIX_CHAVE;
    $gui = 'br.gov.bcb.pix';
    
    $merchant_info = '00' . str_pad(strlen($gui), 2, '0', STR_PAD_LEFT) . $gui;
    $merchant_info .= '01' . str_pad(strlen($chave_pix), 2, '0', STR_PAD_LEFT) . $chave_pix;
    
    if (!empty($descricao)) {
        $descricao_clean = substr(sanitizar_entrada($descricao), 0, 25);
        $merchant_info .= '02' . str_pad(strlen($descricao_clean), 2, '0', STR_PAD_LEFT) . $descricao_clean;
    }
    
    $payload .= '26' . str_pad(strlen($merchant_info), 2, '0', STR_PAD_LEFT) . $merchant_info;
    
    // Merchant Category Code
    $payload .= '52' . '04' . '0000';
    
    // Transaction Currency
    $payload .= '53' . '03' . '986'; // BRL
    
    // Transaction Amount
    if ($valor > 0) {
        $valor_str = number_format($valor, 2, '.', '');
        $payload .= '54' . str_pad(strlen($valor_str), 2, '0', STR_PAD_LEFT) . $valor_str;
    }
    
    // Country Code
    $payload .= '58' . '02' . 'BR';
    
    // Merchant Name
    $nome_merchant = PIX_NOME ?? 'VINDE EVENTOS';
    $payload .= '59' . str_pad(strlen($nome_merchant), 2, '0', STR_PAD_LEFT) . $nome_merchant;
    
    // Merchant City
    $cidade_merchant = PIX_CIDADE ?? 'SAO PAULO';
    $payload .= '60' . str_pad(strlen($cidade_merchant), 2, '0', STR_PAD_LEFT) . $cidade_merchant;
    
    // Additional Data Field Template
    if (!empty($nome_participante)) {
        $txid = 'VINDE' . date('YmdHis') . substr(md5($nome_participante), 0, 10);
        $additional_data = '05' . str_pad(strlen($txid), 2, '0', STR_PAD_LEFT) . $txid;
        $payload .= '62' . str_pad(strlen($additional_data), 2, '0', STR_PAD_LEFT) . $additional_data;
    }
    
    // CRC16
    $payload .= '6304';
    $crc = calcular_crc16($payload);
    $payload .= $crc;
    
    return $payload;
}

/**
 * Validar QR Code de check-in
 */
function validar_qr_checkin($qr_data) {
    try {
        $dados = json_decode($qr_data, true);
        
        if (!$dados || !isset($dados['tipo']) || $dados['tipo'] !== 'checkin') {
            return ['valido' => false, 'erro' => 'QR Code inválido'];
        }
        
        $campos_obrigatorios = ['participante_id', 'token', 'evento_id', 'nome'];
        foreach ($campos_obrigatorios as $campo) {
            if (empty($dados[$campo])) {
                return ['valido' => false, 'erro' => 'Dados incompletos no QR Code'];
            }
        }
        
        // Verificar se o participante existe e o token está correto
        $participante = buscar_um("
            SELECT p.*, e.nome as evento_nome, e.data_inicio, e.local
            FROM inscricoes i
            JOIN participantes p ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            WHERE p.id = ? AND p.qr_token = ? AND i.evento_id = ? AND i.status != 'cancelada'
        ", [$dados['participante_id'], $dados['token'], $dados['evento_id']]);
        
        if (!$participante) {
            return ['valido' => false, 'erro' => 'Participante não encontrado ou token inválido'];
        }
        
        // Verificar se o pagamento foi confirmado
        $pagamento = buscar_um("
            SELECT pg.status, pg.valor 
            FROM pagamentos pg 
            JOIN inscricoes i ON pg.inscricao_id = i.id 
            WHERE i.participante_id = ? AND i.evento_id = ?
        ", [$dados['participante_id'], $dados['evento_id']]);
        
        if ($pagamento && $pagamento['valor'] > 0 && $pagamento['status'] !== 'pago') {
            return ['valido' => false, 'erro' => 'Pagamento não confirmado'];
        }
        
        // Verificar se já fez check-in
        if ($participante['status'] === 'presente') {
            return [
                'valido' => true,
                'ja_presente' => true,
                'participante' => $participante,
                'checkin_anterior' => $participante['checkin_timestamp']
            ];
        }
        
        return [
            'valido' => true,
            'participante' => $participante,
            'ja_presente' => false
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao validar QR Code: " . $e->getMessage());
        return ['valido' => false, 'erro' => 'Erro interno ao validar QR Code'];
    }
}

/**
 * Processar check-in do participante
 */
function processar_checkin($participante_id, $operador_nome = null) {
    try {
        // Verificar se o participante existe e pode fazer check-in
        $participante = buscar_um("
            SELECT p.*, e.nome as evento_nome, e.data_inicio, e.local
            FROM inscricoes i
            JOIN participantes p ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            WHERE p.id = ? AND i.status != 'cancelada'
            ORDER BY i.data_inscricao DESC
            LIMIT 1
        ", [$participante_id]);
        
        if (!$participante) {
            return ['sucesso' => false, 'mensagem' => 'Participante não encontrado'];
        }
        
        if ($participante['status'] === 'presente') {
            return [
                'sucesso' => false, 
                'mensagem' => 'Check-in já realizado',
                'ja_presente' => true,
                'checkin_anterior' => $participante['checkin_timestamp']
            ];
        }
        
        // Atualizar status do participante
        $dados_atualizacao = [
            'status' => 'presente',
            'checkin_timestamp' => date('Y-m-d H:i:s'),
            'checkin_operador' => $operador_nome ?: 'sistema'
        ];
        
        $sucesso = atualizar_registro('participantes', $dados_atualizacao, ['id' => $participante_id]);
        
        if ($sucesso) {
            // Registrar log
            registrar_log('checkin_realizado', 
                "Participante: {$participante['nome']} - Evento: {$participante['evento_nome']} - Operador: " . ($operador_nome ?: 'sistema'),
                $operador_nome
            );
            
            return [
                'sucesso' => true,
                'mensagem' => 'Check-in realizado com sucesso!',
                'participante' => $participante,
                'timestamp' => $dados_atualizacao['checkin_timestamp']
            ];
        } else {
            return ['sucesso' => false, 'mensagem' => 'Erro ao processar check-in'];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar check-in: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno ao processar check-in'];
    }
}

/**
 * Gerar QR Code para check-in do participante
 */
function gerar_qr_checkin_basico($participante_id) {
    $participante = buscar_um("
        SELECT p.*, e.nome as evento_nome 
        FROM participantes p 
        JOIN inscricoes i ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id 
        WHERE p.id = ?
    ", [$participante_id]);
    
    if (!$participante) {
        return false;
    }
    
    $dados_qr = [
        'tipo' => 'checkin',
        'participante_id' => $participante_id,
        'token' => $participante['qr_token'],
        'evento_id' => $participante['evento_id'],
        'nome' => $participante['nome'],
        'evento' => $participante['evento_nome'],
        'gerado_em' => date('Y-m-d H:i:s')
    ];
    
    return json_encode($dados_qr);
}

/**
 * Verificar se QR Code está expirado
 */
function qr_code_expirado($qr_data, $minutos_validade = 30) {
    try {
        $dados = json_decode($qr_data, true);
        
        if (!$dados || !isset($dados['gerado_em'])) {
            return true; // Se não tem timestamp, considera expirado
        }
        
        $gerado_em = strtotime($dados['gerado_em']);
        $agora = time();
        $diferenca_minutos = ($agora - $gerado_em) / 60;
        
        return $diferenca_minutos > $minutos_validade;
        
    } catch (Exception $e) {
        return true; // Em caso de erro, considera expirado
    }
}

/**
 * Estatísticas de check-in por evento
 */
function obter_estatisticas_checkin($evento_id) {
    // total_inscritos: inscrições não canceladas
    // total_pagos: pagamentos com status 'pago'
    // total_presentes: participantes com status 'presente' e com inscrição no evento
    $stats = buscar_um("
        SELECT 
            (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = ? AND i.status != 'cancelada') AS total_inscritos,
            (SELECT COUNT(*) FROM pagamentos pg JOIN inscricoes i2 ON pg.inscricao_id = i2.id WHERE i2.evento_id = ? AND pg.status = 'pago') AS total_pagos,
            (SELECT COUNT(*) FROM inscricoes i3 JOIN participantes p3 ON i3.participante_id = p3.id WHERE i3.evento_id = ? AND i3.status != 'cancelada' AND p3.status = 'presente') AS total_presentes
    ", [$evento_id, $evento_id, $evento_id]);
    
    if ($stats) {
        $stats['percentual_presenca'] = $stats['total_inscritos'] > 0 
            ? round(($stats['total_presentes'] / $stats['total_inscritos']) * 100, 1)
            : 0;
    }
    
    return $stats;
}

/**
 * Obter histórico de check-ins por data
 */
function obter_historico_checkins($evento_id, $data = null) {
    $where = "i.evento_id = ? AND p.status = 'presente'";
    $params = [$evento_id];
    
    if ($data) {
        $where .= " AND DATE(p.checkin_timestamp) = ?";
        $params[] = $data;
    }
    
    return buscar_todos("
        SELECT p.nome, p.checkin_timestamp, p.checkin_operador, e.nome as evento_nome
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        WHERE {$where}
        ORDER BY p.checkin_timestamp DESC
    ", $params);
}

/**
 * Reverter check-in (para casos especiais)
 */
function reverter_checkin($participante_id, $motivo = '', $operador_nome = null) {
    try {
        $participante = buscar_um("
            SELECT nome FROM participantes WHERE id = ? AND status = 'presente'
        ", [$participante_id]);
        
        if (!$participante) {
            return ['sucesso' => false, 'mensagem' => 'Participante não encontrado ou não fez check-in'];
        }
        
        $sucesso = atualizar_registro('participantes', [
            'status' => 'pago',
            'checkin_timestamp' => null,
            'checkin_operador' => null
        ], ['id' => $participante_id]);
        
        if ($sucesso) {
            registrar_log('checkin_revertido', 
                "Participante: {$participante['nome']} - Motivo: {$motivo} - Operador: " . ($operador_nome ?: 'sistema'),
                $operador_nome
            );
            
            return ['sucesso' => true, 'mensagem' => 'Check-in revertido com sucesso'];
        } else {
            return ['sucesso' => false, 'mensagem' => 'Erro ao reverter check-in'];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao reverter check-in: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno ao reverter check-in'];
    }
}

/**
 * Gerar relatório de presença em formato array
 */
function gerar_relatorio_presenca($evento_id) {
    $evento = buscar_um("SELECT nome, data_inicio, local FROM eventos WHERE id = ?", [$evento_id]);
    
    if (!$evento) {
        return false;
    }
    
    $participantes = buscar_todos("
        SELECT 
            nome, 
            cpf, 
            whatsapp, 
            email, 
            cidade, 
            status,
            checkin_timestamp,
            checkin_operador
        FROM participantes 
        WHERE evento_id = ? AND status != 'cancelado'
        ORDER BY nome
    ", [$evento_id]);
    
    $stats = obter_estatisticas_checkin($evento_id);
    
    return [
        'evento' => $evento,
        'participantes' => $participantes,
        'estatisticas' => $stats,
        'gerado_em' => date('Y-m-d H:i:s')
    ];
}

?> 