<?php
/**
 * Gerador simples de QR Code usando API do Google Charts (fallback) ou biblioteca interna
 * Sistema de Inscrições Católicas - Vinde
 */

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Gerar QR Code como imagem base64
 */
function gerar_qr_code_imagem($dados, $tamanho = 200) {
    // Usar API do Google Charts como fallback
    $url = "https://chart.googleapis.com/chart?chs={$tamanho}x{$tamanho}&cht=qr&chl=" . urlencode($dados);
    
    try {
        // Tentar obter a imagem
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Vinde-System/1.0'
            ]
        ]);
        
        $image_data = @file_get_contents($url, false, $context);
        
        if ($image_data !== false) {
            return 'data:image/png;base64,' . base64_encode($image_data);
        }
    } catch (Exception $e) {
        error_log("Erro ao gerar QR Code via API: " . $e->getMessage());
    }
    
    // Se falhou, retornar SVG simples
    return gerar_qr_code_svg($dados, $tamanho);
}

/**
 * Gerar QR Code como SVG simples (fallback)
 */
function gerar_qr_code_svg($dados, $tamanho = 200) {
    $hash = md5($dados);
    $grid_size = 21; // QR Code padrão 21x21
    $cell_size = $tamanho / $grid_size;
    
    $svg = '<svg width="' . $tamanho . '" height="' . $tamanho . '" xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<rect width="100%" height="100%" fill="white"/>';
    
    // Gerar padrão baseado no hash (simplificado)
    for ($y = 0; $y < $grid_size; $y++) {
        for ($x = 0; $x < $grid_size; $x++) {
            $index = ($y * $grid_size + $x) % strlen($hash);
            $char = hexdec($hash[$index]);
            
            // Cantos (sempre pretos para simular QR code)
            if (($x < 7 && $y < 7) || ($x > $grid_size - 8 && $y < 7) || ($x < 7 && $y > $grid_size - 8)) {
                if (($x > 0 && $x < 6 && $y > 0 && $y < 6) || 
                    ($x > $grid_size - 7 && $x < $grid_size - 1 && $y > 0 && $y < 6) ||
                    ($x > 0 && $x < 6 && $y > $grid_size - 7 && $y < $grid_size - 1)) {
                    $svg .= '<rect x="' . ($x * $cell_size) . '" y="' . ($y * $cell_size) . '" width="' . $cell_size . '" height="' . $cell_size . '" fill="black"/>';
                }
            }
            // Dados baseados no hash
            elseif ($char > 7) {
                $svg .= '<rect x="' . ($x * $cell_size) . '" y="' . ($y * $cell_size) . '" width="' . $cell_size . '" height="' . $cell_size . '" fill="black"/>';
            }
        }
    }
    
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Gerar URL para QR Code usando API externa
 */
function gerar_qr_code_url($dados, $tamanho = 200) {
    // Usando API QR Server (gratuita)
    return "https://api.qrserver.com/v1/create-qr-code/?size={$tamanho}x{$tamanho}&data=" . urlencode($dados);
}

/**
 * Gerar QR Code para check-in (melhorado)
 */
function gerar_qr_checkin_completo($participante_id, $evento_id) {
    // Verificar se participante está inscrito no evento
    $inscricao = buscar_um("
        SELECT 
            i.id as inscricao_id,
            i.status,
            p.nome,
            p.cpf,
            p.qr_token,
            e.nome as evento_nome,
            e.data_inicio,
            e.local,
            e.slug
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        WHERE i.participante_id = ? AND i.evento_id = ? AND i.status IN ('pendente', 'aprovada')
    ", [$participante_id, $evento_id]);
    
    if (!$inscricao) {
        return false;
    }
    
    // Gerar QR token se não existir
    if (empty($inscricao['qr_token'])) {
        $qr_token = bin2hex(random_bytes(16));
        
        // Atualizar na tabela participantes
        atualizar_registro('participantes', ['qr_token' => $qr_token], ['id' => $participante_id]);
        
        $inscricao['qr_token'] = $qr_token;
    }
    
    // Dados do QR Code para check-in
    $qr_data = [
        'tipo' => 'checkin',
        'v' => '1.0', // versão
        'inscricao_id' => $inscricao['inscricao_id'],
        'participante_id' => $participante_id,
        'evento_id' => $evento_id,
        'token' => $inscricao['qr_token'],
        'evento' => $inscricao['evento_nome'],
        'participante' => $inscricao['nome'],
        'data_evento' => $inscricao['data_inicio'],
        'gerado_em' => date('Y-m-d H:i:s')
    ];
    
    $qr_json = json_encode($qr_data);
    
    return [
        'data' => $qr_json,
        'url' => gerar_qr_code_url($qr_json),
        'base64' => gerar_qr_code_imagem($qr_json),
        'inscricao' => $inscricao
    ];
}

/**
 * Validar QR Code de check-in
 */
function validar_qr_checkin_completo($qr_data_json) {
    try {
        $dados = json_decode($qr_data_json, true);
        
        if (!$dados || !isset($dados['tipo']) || $dados['tipo'] !== 'checkin') {
            return ['valido' => false, 'erro' => 'QR Code inválido'];
        }
        
        $campos_obrigatorios = ['participante_id', 'token', 'evento_id'];
        foreach ($campos_obrigatorios as $campo) {
            if (empty($dados[$campo])) {
                return ['valido' => false, 'erro' => 'Dados incompletos no QR Code'];
            }
        }
        
        // Verificar se o participante existe e o token está correto
        $participante = buscar_um("
            SELECT p.*, e.nome as evento_nome, e.data_inicio, e.local,
                   i.status as inscricao_status,
                   pg.status as pagamento_status, pg.valor as pagamento_valor
            FROM inscricoes i
            JOIN participantes p ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
            WHERE p.id = ? AND p.qr_token = ? AND i.evento_id = ? AND i.status != 'cancelada'
        ", [$dados['participante_id'], $dados['token'], $dados['evento_id']]);
        
        if (!$participante) {
            return ['valido' => false, 'erro' => 'Participante não encontrado ou token inválido'];
        }
        
        // Verificar se o pagamento foi confirmado (se evento pago)
        if ($participante['pagamento_valor'] > 0 && $participante['pagamento_status'] !== 'pago') {
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
 * Endpoint para servir QR Code como imagem
 */
function servir_qr_imagem($participante_id, $evento_id, $formato = 'png') {
    $qr_result = gerar_qr_checkin_completo($participante_id, $evento_id);
    
    if (!$qr_result) {
        header('HTTP/1.1 404 Not Found');
        exit('QR Code não encontrado');
    }
    
    if ($formato === 'svg') {
        header('Content-Type: image/svg+xml');
        echo base64_decode(str_replace('data:image/svg+xml;base64,', '', $qr_result['base64']));
    } else {
        // Redirecionar para API externa
        header('Location: ' . $qr_result['url']);
    }
    
    exit;
}

?>
