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

// Exigir mTLS em produção (a validação real é feita pelo servidor web)
if (defined('AMBIENTE') && AMBIENTE === 'producao') {
    $mtlsOk = isset($_SERVER['SSL_CLIENT_VERIFY']) && $_SERVER['SSL_CLIENT_VERIFY'] === 'SUCCESS';
    if (!$mtlsOk) {
        http_response_code(400);
        registrar_log('webhook_erro', 'mTLS ausente ou inválido ao acessar webhook', $_SERVER['SSL_CLIENT_VERIFY'] ?? 'NA');
        resposta_json(false, 'mTLS obrigatório: certificado de cliente não verificado');
    }
}

// Verificar se EFI está ativo e configurado
if (!efi_esta_ativo()) {
    http_response_code(503);
    resposta_json(false, 'EFI Bank não está ativo ou configurado');
}

// Obter configurações EFI
$config_efi = obter_configuracoes_efi();

// Ler dados do webhook
$input = file_get_contents('php://input');
$dados_webhook = json_decode($input, true);

// Log da requisição recebida
registrar_log('webhook_recebido', 'Webhook EFI Bank recebido', null);

// Verificar se dados são válidos
if (!$dados_webhook) {
    http_response_code(400);
    registrar_log('webhook_erro', 'Dados JSON inválidos no webhook');
    resposta_json(false, 'Dados inválidos');
}

// Log detalhado se debug está ativo
if ($config_efi['efi_debug'] === '1') {
    error_log("EFI Webhook Debug: " . json_encode($dados_webhook));
}

// Validar webhook secret se configurado
if (!empty($config_efi['efi_webhook_secret'])) {
    $webhook_signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
    $expected_signature = 'sha1=' . hash_hmac('sha1', $input, $config_efi['efi_webhook_secret']);
    
    if (!hash_equals($expected_signature, $webhook_signature)) {
        http_response_code(401);
        registrar_log('webhook_erro', 'Assinatura do webhook inválida');
        resposta_json(false, 'Assinatura inválida');
    }
}

// Verificar se é notificação de PIX
if (!isset($dados_webhook['pix'])) {
    registrar_log('webhook_info', 'Notificação recebida mas não é PIX');
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
            registrar_log('webhook_erro', "Falha ao consultar PIX via API", $endToEndId);
            continue;
        }
        
        // Log do PIX consultado
        registrar_log('webhook_pix_consultado', "PIX consultado com sucesso", $endToEndId);
        
        // Verificar se é um PIX de entrada (recebido)
        if ($pix_completo['devolucao'] ?? false) {
            // É uma devolução, ignorar
            continue;
        }
        
        // Extrair dados do PIX
        $valor_pix = floatval($pix_completo['valor'] ?? 0);
        $txid_relacionado = $pix_completo['txid'] ?? null;
        $data_pagamento = $pix_completo['horario'] ?? date('Y-m-d H:i:s');
        $info_pagador = $pix_completo['infoPagador'] ?? '';
        
        if ($config_efi['efi_debug'] === '1') {
            error_log("EFI Webhook Debug PIX: TXID={$txid_relacionado}, Valor={$valor_pix}, EndToEndId={$endToEndId}");
        }
        
        // Buscar pagamento no banco pelo TXID
        $pagamento = null;
        
        if ($txid_relacionado) {
            $pagamento = buscar_um("
                SELECT 
                    p.*, 
                    i.id AS inscricao_id,
                    pa.id AS participante_id, 
                    pa.nome AS participante_nome, 
                    pa.email, 
                    pa.whatsapp, 
                    e.id AS evento_id,
                    e.nome AS evento_nome
                FROM pagamentos p
                JOIN inscricoes i   ON p.inscricao_id = i.id
                JOIN participantes pa ON p.participante_id = pa.id
                JOIN eventos e       ON i.evento_id = e.id
                WHERE p.pix_txid = ? AND p.status != 'pago'
            ", [$txid_relacionado]);
        }
        
        if (!$pagamento) {
            // Tentar buscar por valor e status pendente (fallback)
            $pagamento = buscar_um("
                SELECT 
                    p.*, 
                    i.id AS inscricao_id,
                    pa.id AS participante_id, 
                    pa.nome AS participante_nome, 
                    pa.email, 
                    pa.whatsapp, 
                    e.id AS evento_id,
                    e.nome AS evento_nome
                FROM pagamentos p
                JOIN inscricoes i   ON p.inscricao_id = i.id
                JOIN participantes pa ON p.participante_id = pa.id
                JOIN eventos e       ON i.evento_id = e.id
                WHERE p.valor = ? AND p.status = 'pendente'
                ORDER BY p.criado_em DESC
                LIMIT 1
            ", [$valor_pix]);
        }
        
        if (!$pagamento) {
            $erros[] = "Pagamento não encontrado para PIX: {$endToEndId} (TXID: {$txid_relacionado}, Valor: R$ {$valor_pix})";
            registrar_log('webhook_erro', "Pagamento não encontrado", $endToEndId);
            continue;
        }
        
        // Verificar se o valor confere
        if (abs($valor_pix - floatval($pagamento['valor'])) > 0.01) {
            $erros[] = "Valor do PIX (R$ {$valor_pix}) não confere com pagamento (R$ {$pagamento['valor']})";
            registrar_log('webhook_erro', "Valor divergente: PIX R$ {$valor_pix} vs Pagamento R$ {$pagamento['valor']}", $endToEndId);
            continue;
        }
        
        // Confirmar pagamento no banco
        $dados_update = [
            'status' => 'pago',
            'pago_em' => date('Y-m-d H:i:s', strtotime($data_pagamento)),
            'pix_end_to_end_id' => $endToEndId,
            'pix_info_pagador' => $info_pagador
        ];
        
        $pagamento_atualizado = executar("
            UPDATE pagamentos 
            SET status = ?, pago_em = ?, pix_end_to_end_id = ?, pix_info_pagador = ? 
            WHERE id = ?
        ", [
            $dados_update['status'],
            $dados_update['pago_em'],
            $dados_update['pix_end_to_end_id'],
            $dados_update['pix_info_pagador'],
            $pagamento['id']
        ]);
        
        if ($pagamento_atualizado) {
            // Atualizar status da inscrição e participante
            if (!empty($pagamento['inscricao_id'])) {
                executar(
                    "UPDATE inscricoes 
                     SET status = 'aprovada', valor_pago = ?, data_pagamento = NOW(), atualizado_em = NOW() 
                     WHERE id = ?",
                    [$valor_pix, $pagamento['inscricao_id']]
                );
            }
            executar("UPDATE participantes SET status = 'pago' WHERE id = ?", [$pagamento['participante_id']]);
            
            // Log de sucesso
            registrar_log('webhook_baixa_automatica', 
                "Pagamento confirmado automaticamente - Participante: {$pagamento['participante_nome']} | Valor: R$ {$valor_pix} | Evento: {$pagamento['evento_nome']}", 
                $txid_relacionado
            );
            
            // Enviar notificação por WhatsApp
            if (!empty($pagamento['whatsapp'])) {
                $mensagem_confirmacao = "🎉 Pagamento confirmado automaticamente!

Olá {$pagamento['participante_nome']},

Seu PIX foi processado com sucesso!

📅 Evento: {$pagamento['evento_nome']}
💰 Valor: R$ " . number_format($valor_pix, 2, ',', '.') . "
📅 Data do Pagamento: " . date('d/m/Y H:i', strtotime($data_pagamento)) . "

            🎫 Acesse sua confirmação:
            " . SITE_URL . "/confirmacao.php?inscricao={$pagamento['inscricao_id']}

Nos vemos lá! 🙏";

                simular_whatsapp($pagamento['whatsapp'], $mensagem_confirmacao);
            }
            
            $processados++;
        } else {
            $erros[] = "Erro ao atualizar pagamento no banco de dados";
            registrar_log('webhook_erro', "Falha ao atualizar pagamento no banco", $endToEndId);
        }
    }
    
    // Resposta final
    $resposta_dados = [
        'processados' => $processados,
        'erros' => $erros,
        'total_pix' => count($dados_webhook['pix'])
    ];
    
    if ($processados > 0) {
        registrar_log('webhook_sucesso', "Webhook processado com sucesso: {$processados} pagamentos confirmados");
        resposta_json(true, "Processados {$processados} pagamentos com sucesso", $resposta_dados);
    } else {
        registrar_log('webhook_info', "Webhook processado mas nenhum pagamento foi confirmado");
        resposta_json(true, "Nenhum pagamento foi processado", $resposta_dados);
    }
    
} catch (Exception $e) {
    error_log("Erro no webhook EFI: " . $e->getMessage());
    registrar_log('webhook_erro', "Exceção no webhook: " . $e->getMessage());
    
    http_response_code(500);
    resposta_json(false, 'Erro interno do servidor: ' . $e->getMessage());
}

?> 