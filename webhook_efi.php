<?php
/**
 * Webhook EFI Bank - Baixa Automática de Pagamentos PIX
 * Arquivo: webhook_efi.php
 * 
 * Este arquivo recebe notificações da EFI Bank quando um PIX é recebido
 * e processa a baixa automática do pagamento
 */

require_once 'includes/init.php';

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Função para log específico do webhook
function log_webhook($tipo, $dados, $mensagem = '') {
    $log_data = [
        'tipo' => $tipo,
        'request_data' => json_encode($dados),
        'mensagem' => $mensagem,
        'criado_em' => date('Y-m-d H:i:s')
    ];
    
    try {
        inserir_registro('efi_logs', $log_data);
    } catch (Exception $e) {
        error_log("Erro ao salvar log webhook EFI: " . $e->getMessage());
    }
}

// Função para resposta JSON
function resposta_json($sucesso, $mensagem, $dados = []) {
    $resposta = [
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'timestamp' => date('c'),
        'dados' => $dados
    ];
    
    echo json_encode($resposta);
    exit;
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    resposta_json(false, 'Método não permitido');
}

// Verificar se EFI está ativo
$efi_ativo = obter_configuracao('efi_ativo', '0') === '1';
if (!$efi_ativo) {
    http_response_code(503);
    resposta_json(false, 'EFI Bank não está ativo');
}

// Ler dados do webhook
$input = file_get_contents('php://input');
$dados_webhook = json_decode($input, true);

// Log da requisição recebida
log_webhook('webhook', $dados_webhook, 'Webhook recebido');

// Verificar se dados são válidos
if (!$dados_webhook) {
    http_response_code(400);
    resposta_json(false, 'Dados inválidos');
}

// Verificar se é notificação de PIX
if (!isset($dados_webhook['pix'])) {
    resposta_json(true, 'Notificação ignorada - não é PIX');
}

try {
    // Processar cada PIX recebido
    $processados = 0;
    $erros = [];
    
    foreach ($dados_webhook['pix'] as $pix_info) {
        if (!isset($pix_info['endToEndId'])) {
            $erros[] = "PIX sem endToEndId";
            continue;
        }
        
        $endToEndId = $pix_info['endToEndId'];
        
        // Consultar dados completos do PIX na EFI
        $pix_completo = efi_consultar_pix($endToEndId);
        
        if (!$pix_completo) {
            $erros[] = "Não foi possível consultar PIX: {$endToEndId}";
            continue;
        }
        
        // Log do PIX consultado
        log_webhook('consulta', $pix_completo, "PIX consultado: {$endToEndId}");
        
        // Extrair TXID da cobrança
        $txid = null;
        if (isset($pix_completo['txid'])) {
            $txid = $pix_completo['txid'];
        } elseif (isset($pix_completo['infoPagador'])) {
            // Tentar extrair TXID das informações do pagador
            $info_pagador = $pix_completo['infoPagador'];
            if (preg_match('/VINDE\d{20}/', $info_pagador, $matches)) {
                $txid = $matches[0];
            }
        }
        
        if (!$txid) {
            $erros[] = "TXID não encontrado para PIX: {$endToEndId}";
            continue;
        }
        
        // Buscar pagamento no banco
        $pagamento = buscar_um("
            SELECT p.*, pa.id as participante_id, pa.nome, pa.whatsapp, e.nome as evento_nome
            FROM pagamentos p
            JOIN participantes pa ON p.participante_id = pa.id
            JOIN eventos e ON pa.evento_id = e.id
            WHERE p.pix_txid = ? AND p.status = ?
        ", [$txid, PAGAMENTO_PENDENTE]);
        
        if (!$pagamento) {
            log_webhook('erro', $pix_completo, "Pagamento não encontrado para TXID: {$txid}");
            continue;
        }
        
        // Verificar valor
        $valor_pix = floatval($pix_completo['valor']);
        $valor_pagamento = floatval($pagamento['valor']);
        
        if (abs($valor_pix - $valor_pagamento) > 0.01) {
            $erros[] = "Valor divergente - PIX: R$ {$valor_pix} | Esperado: R$ {$valor_pagamento}";
            continue;
        }
        
        // Processar baixa automática
        $sucesso_baixa = efi_processar_baixa_automatica($txid, $pagamento['participante_id']);
        
        if ($sucesso_baixa) {
            $processados++;
            
            // Log específico da baixa
            log_webhook('baixa', [
                'txid' => $txid,
                'participante_id' => $pagamento['participante_id'],
                'valor' => $valor_pix,
                'endToEndId' => $endToEndId
            ], "Baixa processada com sucesso");
            
            // Enviar confirmação por WhatsApp
            $mensagem_whatsapp = "🎉 *Pagamento Confirmado!*\n\n";
            $mensagem_whatsapp .= "Olá {$pagamento['nome']},\n\n";
            $mensagem_whatsapp .= "Seu pagamento PIX foi confirmado!\n\n";
            $mensagem_whatsapp .= "📅 *Evento:* {$pagamento['evento_nome']}\n";
            $mensagem_whatsapp .= "💰 *Valor:* R$ " . number_format($valor_pix, 2, ',', '.') . "\n";
            $mensagem_whatsapp .= "🆔 *Código:* {$txid}\n\n";
            $mensagem_whatsapp .= "✅ Sua inscrição está confirmada!\n";
            $mensagem_whatsapp .= "Em breve você receberá o QR Code de acesso.\n\n";
            $mensagem_whatsapp .= "Paz e Bem! 🙏";
            
            enviar_whatsapp($pagamento['whatsapp'], $mensagem_whatsapp);
            
        } else {
            $erros[] = "Erro ao processar baixa para TXID: {$txid}";
        }
    }
    
    // Resposta final
    if ($processados > 0) {
        $mensagem = "Processados {$processados} pagamento(s)";
        if (!empty($erros)) {
            $mensagem .= " com " . count($erros) . " erro(s)";
        }
        
        registrar_log('webhook_efi_sucesso', $mensagem);
        resposta_json(true, $mensagem, [
            'processados' => $processados,
            'erros' => $erros
        ]);
    } else {
        $mensagem = "Nenhum pagamento processado";
        if (!empty($erros)) {
            $mensagem .= " - Erros: " . implode(', ', $erros);
        }
        
        log_webhook('erro', $dados_webhook, $mensagem);
        resposta_json(false, $mensagem, ['erros' => $erros]);
    }
    
} catch (Exception $e) {
    $erro_msg = "Erro no webhook EFI: " . $e->getMessage();
    error_log($erro_msg);
    
    log_webhook('erro', $dados_webhook, $erro_msg);
    
    http_response_code(500);
    resposta_json(false, 'Erro interno do servidor');
}
?> 