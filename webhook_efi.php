<?php
/**
 * Webhook EFI Bank - Baixa Automática de Pagamentos PIX (Versão Melhorada)
 * Arquivo: webhook_efi.php
 * 
 * Este arquivo recebe notificações da EFI Bank quando um PIX é recebido
 * e processa a baixa automática do pagamento
 * 
 * Melhorias implementadas:
 * - Idempotência para evitar processamento duplicado
 * - Melhor debugging para erro 400
 * - Validação mais robusta da estrutura do webhook
 * - Remoção do fallback perigoso de busca por valor
 * - Headers corretos da EFI Bank
 */

require_once 'includes/init.php';

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Gerencianet-Signature');

// Função para resposta JSON
function resposta_json($sucesso, $mensagem, $dados = [], $codigo_http = null) {
    if ($codigo_http) {
        http_response_code($codigo_http);
    }
    
    $resposta = [
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'timestamp' => date('c'),
        'dados' => $dados
    ];
    
    echo json_encode($resposta);
    exit;
}

// Função para log detalhado de debug
function debug_webhook($titulo, $dados = null, $forcar_log = false) {
    global $config_efi;
    
    if ($config_efi['efi_debug'] === '1' || $forcar_log) {
        $log_entry = "EFI Webhook Debug - {$titulo}";
        if ($dados !== null) {
            $log_entry .= ": " . (is_array($dados) || is_object($dados) ? json_encode($dados) : $dados);
        }
        error_log($log_entry);
        registrar_log('webhook_debug', $titulo, $dados);
    }
}

// DEBUG: Log de todos os headers recebidos para identificar problema do 400
debug_webhook("Headers recebidos", getallheaders(), true);
debug_webhook("Método HTTP", $_SERVER['REQUEST_METHOD'], true);
debug_webhook("Content-Type recebido", $_SERVER['CONTENT_TYPE'] ?? 'N/A', true);
debug_webhook("IP do cliente", $_SERVER['REMOTE_ADDR'] ?? 'N/A', true);

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_webhook("Erro: Método não permitido", $_SERVER['REQUEST_METHOD'], true);
    resposta_json(false, 'Método não permitido', [], 405);
}

// Verificar se EFI está ativo e configurado
if (!efi_esta_ativo()) {
    debug_webhook("Erro: EFI Bank não está ativo", null, true);
    resposta_json(false, 'EFI Bank não está ativo ou configurado', [], 503);
}

// Obter configurações EFI
$config_efi = obter_configuracoes_efi();

// DEBUG: Log das configurações (sem dados sensíveis)
debug_webhook("Configurações EFI", [
    'efi_debug' => $config_efi['efi_debug'],
    'webhook_secret_configurado' => !empty($config_efi['efi_webhook_secret']),
    'ambiente' => defined('AMBIENTE') ? AMBIENTE : 'nao_definido'
], true);

// Exigir mTLS em produção (a validação real é feita pelo servidor web)
if (defined('AMBIENTE') && AMBIENTE === 'producao') {
    $ssl_client_verify = $_SERVER['SSL_CLIENT_VERIFY'] ?? 'N/A';
    $ssl_client_cert = $_SERVER['SSL_CLIENT_CERT'] ?? 'N/A';
    
    debug_webhook("mTLS Debug", [
        'SSL_CLIENT_VERIFY' => $ssl_client_verify,
        'SSL_CLIENT_CERT_presente' => $ssl_client_cert !== 'N/A' ? 'sim' : 'nao'
    ], true);
    
    $mtlsOk = $ssl_client_verify === 'SUCCESS';
    if (!$mtlsOk) {
        registrar_log('webhook_erro', 'mTLS ausente ou inválido ao acessar webhook', $ssl_client_verify);
        resposta_json(false, 'mTLS obrigatório: certificado de cliente não verificado', [
            'ssl_client_verify' => $ssl_client_verify
        ], 400);
    }
}

// Ler dados do webhook
$input = file_get_contents('php://input');
debug_webhook("Payload bruto recebido", strlen($input) . " bytes", true);
debug_webhook("Payload conteúdo", $input, true);

// Verificar se há dados
if (empty($input)) {
    debug_webhook("Erro: Payload vazio", null, true);
    resposta_json(false, 'Payload vazio', [], 400);
}

// Decodificar JSON
$dados_webhook = json_decode($input, true);
$json_error = json_last_error();

debug_webhook("JSON decode result", [
    'json_error' => $json_error,
    'json_error_msg' => json_last_error_msg(),
    'dados_validos' => $dados_webhook !== null
], true);

// Verificar se dados são válidos
if ($dados_webhook === null || $json_error !== JSON_ERROR_NONE) {
    registrar_log('webhook_erro', 'Dados JSON inválidos no webhook: ' . json_last_error_msg());
    resposta_json(false, 'Dados JSON inválidos: ' . json_last_error_msg(), [
        'json_error_code' => $json_error
    ], 400);
}

// Log da requisição recebida
registrar_log('webhook_recebido', 'Webhook EFI Bank recebido com sucesso', null);

// Log detalhado da estrutura recebida
debug_webhook("Estrutura do webhook", $dados_webhook);

// Validar webhook secret se configurado
if (!empty($config_efi['efi_webhook_secret'])) {
    // EFI Bank pode usar diferentes headers para assinatura
    $possible_signature_headers = [
        'HTTP_X_GERENCIANET_SIGNATURE',
        'HTTP_X_HUB_SIGNATURE',
        'HTTP_X_SIGNATURE'
    ];
    
    $webhook_signature = '';
    $header_usado = '';
    
    foreach ($possible_signature_headers as $header) {
        if (isset($_SERVER[$header])) {
            $webhook_signature = $_SERVER[$header];
            $header_usado = $header;
            break;
        }
    }
    
    debug_webhook("Validação de assinatura", [
        'header_usado' => $header_usado,
        'signature_presente' => !empty($webhook_signature),
        'signature_valor' => $webhook_signature
    ]);
    
    if (!empty($webhook_signature)) {
        // Tentar diferentes formatos de assinatura
        $expected_signatures = [
            'sha1=' . hash_hmac('sha1', $input, $config_efi['efi_webhook_secret']),
            hash_hmac('sha1', $input, $config_efi['efi_webhook_secret']),
            'sha256=' . hash_hmac('sha256', $input, $config_efi['efi_webhook_secret']),
            hash_hmac('sha256', $input, $config_efi['efi_webhook_secret'])
        ];
        
        $assinatura_valida = false;
        foreach ($expected_signatures as $expected) {
            if (hash_equals($expected, $webhook_signature)) {
                $assinatura_valida = true;
                break;
            }
        }
        
        if (!$assinatura_valida) {
            debug_webhook("Erro: Assinatura inválida", [
                'recebida' => $webhook_signature,
                'esperadas' => $expected_signatures
            ], true);
            registrar_log('webhook_erro', 'Assinatura do webhook inválida');
            resposta_json(false, 'Assinatura inválida', [], 401);
        }
    } else {
        debug_webhook("Aviso: Webhook secret configurado mas nenhum header de assinatura encontrado", null, true);
    }
}

// Verificar estruturas possíveis do webhook EFI Bank
$pix_data = null;
$estrutura_detectada = '';

if (isset($dados_webhook['pix']) && is_array($dados_webhook['pix'])) {
    $pix_data = $dados_webhook['pix'];
    $estrutura_detectada = 'pix';
} elseif (isset($dados_webhook['pixRecebidos']) && is_array($dados_webhook['pixRecebidos'])) {
    $pix_data = $dados_webhook['pixRecebidos'];
    $estrutura_detectada = 'pixRecebidos';
} elseif (isset($dados_webhook['endToEndId'])) {
    // Webhook com PIX único
    $pix_data = [$dados_webhook];
    $estrutura_detectada = 'pix_unico';
}

debug_webhook("Estrutura detectada", [
    'tipo' => $estrutura_detectada,
    'quantidade_pix' => $pix_data ? count($pix_data) : 0
]);

// Verificar se é notificação de PIX
if (!$pix_data) {
    debug_webhook("Info: Notificação recebida mas estrutura PIX não reconhecida", array_keys($dados_webhook));
    registrar_log('webhook_info', 'Notificação recebida mas estrutura PIX não reconhecida');
    resposta_json(true, 'Notificação ignorada - estrutura PIX não reconhecida', [
        'estrutura_recebida' => array_keys($dados_webhook)
    ]);
}

try {
    // Processar cada PIX recebido
    $processados = 0;
    $erros = [];
    $ja_processados = 0;
    
    foreach ($pix_data as $index => $pix_info) {
        debug_webhook("Processando PIX {$index}", $pix_info);
        
        if (!isset($pix_info['endToEndId'])) {
            $erro = "PIX {$index} sem endToEndId";
            $erros[] = $erro;
            debug_webhook("Erro", $erro);
            continue;
        }
        
        $endToEndId = $pix_info['endToEndId'];
        
        // MELHORIA 1: Verificar idempotência - se já foi processado
        $ja_processado = buscar_um("
            SELECT id, status FROM pagamentos 
            WHERE pix_end_to_end_id = ?
        ", [$endToEndId]);
        
        if ($ja_processado) {
            if ($ja_processado['status'] === 'pago') {
                debug_webhook("PIX já processado", $endToEndId);
                $ja_processados++;
                continue;
            }
        }
        
        // Consultar dados completos do PIX na EFI
        debug_webhook("Consultando PIX na API EFI", $endToEndId);
        $pix_completo = efi_consultar_pix($endToEndId);
        
        if (!$pix_completo) {
            $erro = "Não foi possível consultar PIX: {$endToEndId}";
            $erros[] = $erro;
            debug_webhook("Erro API EFI", $erro);
            registrar_log('webhook_erro', "Falha ao consultar PIX via API", $endToEndId);
            continue;
        }
        
        // Log do PIX consultado
        debug_webhook("PIX consultado com sucesso", $pix_completo);
        registrar_log('webhook_pix_consultado', "PIX consultado com sucesso", $endToEndId);
        
        // Verificar se é um PIX de entrada (recebido) e não uma devolução
        if (isset($pix_completo['devolucao']) && $pix_completo['devolucao']) {
            debug_webhook("PIX ignorado - é devolução", $endToEndId);
            continue;
        }
        
        // Extrair dados do PIX
        $valor_pix = floatval($pix_completo['valor'] ?? 0);
        $txid_relacionado = $pix_completo['txid'] ?? null;
        $data_pagamento = $pix_completo['horario'] ?? date('Y-m-d H:i:s');
        $info_pagador = $pix_completo['infoPagador'] ?? '';
        
        debug_webhook("Dados extraídos do PIX", [
            'valor' => $valor_pix,
            'txid' => $txid_relacionado,
            'data' => $data_pagamento,
            'endToEndId' => $endToEndId
        ]);
        
        // MELHORIA 2: Buscar pagamento APENAS por TXID (mais seguro)
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
            $erro = "Pagamento não encontrado para PIX: {$endToEndId} (TXID: {$txid_relacionado}, Valor: R$ {$valor_pix})";
            $erros[] = $erro;
            debug_webhook("Erro", $erro);
            registrar_log('webhook_erro', "Pagamento não encontrado - TXID obrigatório", [
                'endToEndId' => $endToEndId,
                'txid' => $txid_relacionado,
                'valor' => $valor_pix
            ]);
            continue;
        }
        
        debug_webhook("Pagamento encontrado", [
            'pagamento_id' => $pagamento['id'],
            'participante' => $pagamento['participante_nome'],
            'evento' => $pagamento['evento_nome'],
            'valor_esperado' => $pagamento['valor']
        ]);
        
        // Verificar se o valor confere (tolerância de R$ 0,01)
        $diferenca_valor = abs($valor_pix - floatval($pagamento['valor']));
        if ($diferenca_valor > 0.01) {
            $erro = "Valor do PIX (R$ {$valor_pix}) não confere com pagamento (R$ {$pagamento['valor']}) - Diferença: R$ {$diferenca_valor}";
            $erros[] = $erro;
            debug_webhook("Erro valor", $erro);
            registrar_log('webhook_erro', "Valor divergente", [
                'endToEndId' => $endToEndId,
                'valor_pix' => $valor_pix,
                'valor_pagamento' => $pagamento['valor'],
                'diferenca' => $diferenca_valor
            ]);
            continue;
        }
        
        // Confirmar pagamento no banco
        $dados_update = [
            'status' => 'pago',
            'pago_em' => date('Y-m-d H:i:s', strtotime($data_pagamento)),
            'pix_end_to_end_id' => $endToEndId,
            'pix_info_pagador' => $info_pagador
        ];
        
        debug_webhook("Atualizando pagamento", $dados_update);
        
        $pagamento_atualizado = executar("
            UPDATE pagamentos 
            SET status = ?, pago_em = ?, pix_end_to_end_id = ?, pix_info_pagador = ?, atualizado_em = NOW() 
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
                
                debug_webhook("Inscrição atualizada", $pagamento['inscricao_id']);
            }
            
            executar("UPDATE participantes SET status = 'pago', atualizado_em = NOW() WHERE id = ?", [$pagamento['participante_id']]);
            
            // Log de sucesso
            registrar_log('webhook_baixa_automatica', 
                "Pagamento confirmado automaticamente - Participante: {$pagamento['participante_nome']} | Valor: R$ {$valor_pix} | Evento: {$pagamento['evento_nome']}", 
                $txid_relacionado
            );
            
            debug_webhook("Pagamento confirmado com sucesso", [
                'pagamento_id' => $pagamento['id'],
                'participante' => $pagamento['participante_nome'],
                'valor' => $valor_pix
            ]);
            
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
                debug_webhook("WhatsApp enviado", $pagamento['whatsapp']);
            }
            
            $processados++;
        } else {
            $erro = "Erro ao atualizar pagamento no banco de dados";
            $erros[] = $erro;
            debug_webhook("Erro BD", $erro);
            registrar_log('webhook_erro', "Falha ao atualizar pagamento no banco", $endToEndId);
        }
    }
    
    // Resposta final
    $resposta_dados = [
        'processados' => $processados,
        'ja_processados' => $ja_processados,
        'erros' => $erros,
        'total_pix' => count($pix_data),
        'estrutura_detectada' => $estrutura_detectada
    ];
    
    debug_webhook("Resultado final", $resposta_dados);
    
    if ($processados > 0) {
        registrar_log('webhook_sucesso', "Webhook processado com sucesso: {$processados} pagamentos confirmados");
        resposta_json(true, "Processados {$processados} pagamentos com sucesso", $resposta_dados);
    } elseif ($ja_processados > 0) {
        registrar_log('webhook_info', "Webhook processado: {$ja_processados} pagamentos já haviam sido processados");
        resposta_json(true, "{$ja_processados} pagamentos já haviam sido processados anteriormente", $resposta_dados);
    } else {
        registrar_log('webhook_info', "Webhook processado mas nenhum pagamento foi confirmado");
        resposta_json(true, "Nenhum pagamento foi processado", $resposta_dados);
    }
    
} catch (Exception $e) {
    $erro_msg = "Exceção no webhook: " . $e->getMessage();
    debug_webhook("EXCEÇÃO", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], true);
    
    error_log("Erro no webhook EFI: " . $e->getMessage());
    registrar_log('webhook_erro', $erro_msg);
    
    resposta_json(false, 'Erro interno do servidor: ' . $e->getMessage(), [], 500);
}

?>