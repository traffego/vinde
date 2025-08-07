<?php
// Integração com API PIX EFI Bank
// Arquivo: includes/efi_bank.php

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Classe para integração com EFI Bank PIX API
 * Baseada na documentação: https://dev.efipay.com.br/docs/api-pix/credenciais
 */

/**
 * Obtém configurações do ambiente EFI
 * @return array Configurações do ambiente
 */
function obter_config_efi() {
    $ambiente = EFI_AMBIENTE;
    
    if ($ambiente === 'producao') {
        return [
            'client_id' => EFI_CLIENT_ID_PROD,
            'client_secret' => EFI_CLIENT_SECRET_PROD,
            'certificado' => EFI_CERTIFICADO_PROD,
            'api_url' => EFI_API_URL_PROD
        ];
    } else {
        return [
            'client_id' => EFI_CLIENT_ID_HOM,
            'client_secret' => EFI_CLIENT_SECRET_HOM,
            'certificado' => EFI_CERTIFICADO_HOM,
            'api_url' => EFI_API_URL_HOM
        ];
    }
}

/**
 * Autentica na API EFI e obtém token de acesso
 * @return string|false Token de acesso ou false em caso de erro
 */
function efi_obter_token() {
    $config = obter_config_efi();
    
    // Verificar se certificado existe
    if (!file_exists($config['certificado'])) {
        error_log("EFI: Certificado não encontrado: " . $config['certificado']);
        return false;
    }
    
    $auth = base64_encode($config['client_id'] . ':' . $config['client_secret']);
    $url = $config['api_url'] . '/oauth/token';
    
    $postData = json_encode(['grant_type' => 'client_credentials']);
    
    // Inicializar cURL
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json'
        ],
        CURLOPT_SSLCERT => $config['certificado'],
        CURLOPT_SSLCERTPASSWD => EFI_SENHA_CERTIFICADO,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        error_log("EFI cURL Error: " . $error);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("EFI Auth Error HTTP {$httpCode}: " . $response);
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['access_token'])) {
        error_log("EFI: Token não encontrado na resposta: " . $response);
        return false;
    }
    
    // Cache do token (válido por 1 hora)
    $_SESSION['efi_token'] = $data['access_token'];
    $_SESSION['efi_token_expires'] = time() + ($data['expires_in'] ?? 3600);
    
    registrar_log('efi_auth_success', 'Token obtido com sucesso');
    
    return $data['access_token'];
}

/**
 * Obtém token válido (do cache ou novo)
 * @return string|false Token válido ou false em caso de erro
 */
function efi_obter_token_valido() {
    // Verificar se existe token em cache válido
    if (isset($_SESSION['efi_token']) && isset($_SESSION['efi_token_expires'])) {
        if (time() < $_SESSION['efi_token_expires'] - 300) { // 5 min de margem
            return $_SESSION['efi_token'];
        }
    }
    
    // Obter novo token
    return efi_obter_token();
}

/**
 * Faz requisição para API EFI
 * @param string $endpoint Endpoint da API
 * @param string $method Método HTTP (GET, POST, PUT, DELETE)
 * @param array $data Dados para enviar
 * @return array|false Resposta da API ou false em caso de erro
 */
function efi_fazer_requisicao($endpoint, $method = 'GET', $data = null) {
    $token = efi_obter_token_valido();
    
    if (!$token) {
        return false;
    }
    
    $config = obter_config_efi();
    $url = $config['api_url'] . $endpoint;
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    
    $curl = curl_init();
    
    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSLCERT => $config['certificado'],
        CURLOPT_SSLCERTPASSWD => EFI_SENHA_CERTIFICADO,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30
    ];
    
    switch (strtoupper($method)) {
        case 'POST':
            $curlOptions[CURLOPT_POST] = true;
            if ($data) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            }
            break;
        case 'PUT':
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
            if ($data) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            }
            break;
        case 'DELETE':
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            break;
    }
    
    curl_setopt_array($curl, $curlOptions);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        error_log("EFI API cURL Error: " . $error);
        return false;
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 400) {
        error_log("EFI API Error HTTP {$httpCode}: " . $response);
        return false;
    }
    
    return $responseData;
}

/**
 * Cria cobrança PIX imediata
 * @param string $txid ID da transação (único)
 * @param float $valor Valor da cobrança
 * @param string $descricao Descrição da cobrança
 * @param string $nome_pagador Nome do pagador
 * @param string $cpf_pagador CPF do pagador
 * @param int $expiracao Tempo de expiração em segundos (padrão: 3600)
 * @return array|false Dados da cobrança ou false em caso de erro
 */
function efi_criar_cobranca_pix($txid, $valor, $descricao, $nome_pagador, $cpf_pagador, $expiracao = 3600) {
    $dados = [
        'calendario' => [
            'expiracao' => $expiracao
        ],
        'valor' => [
            'original' => number_format($valor, 2, '.', '')
        ],
        'chave' => PIX_CHAVE,
        'solicitacaoPagador' => $descricao,
        'infoAdicionais' => [
            [
                'nome' => 'Pagador',
                'valor' => $nome_pagador
            ],
            [
                'nome' => 'CPF',
                'valor' => $cpf_pagador
            ]
        ]
    ];
    
    $endpoint = "/v2/cob/{$txid}";
    $resposta = efi_fazer_requisicao($endpoint, 'PUT', $dados);
    
    if ($resposta) {
        registrar_log('efi_cobranca_criada', "TXID: {$txid} | Valor: R$ {$valor}");
    }
    
    return $resposta;
}

/**
 * Gera QR Code para cobrança PIX
 * @param string $locId Location ID da cobrança
 * @return array|false Dados do QR Code ou false em caso de erro
 */
function efi_gerar_qrcode($locId) {
    $endpoint = "/v2/loc/{$locId}/qrcode";
    $resposta = efi_fazer_requisicao($endpoint, 'GET');
    
    return $resposta;
}

/**
 * Consulta cobrança PIX
 * @param string $txid ID da transação
 * @return array|false Dados da cobrança ou false em caso de erro
 */
function efi_consultar_cobranca($txid) {
    $endpoint = "/v2/cob/{$txid}";
    $resposta = efi_fazer_requisicao($endpoint, 'GET');
    
    return $resposta;
}

/**
 * Consulta PIX recebido
 * @param string $endToEndId ID fim a fim do PIX
 * @return array|false Dados do PIX ou false em caso de erro
 */
function efi_consultar_pix($endToEndId) {
    $endpoint = "/v2/pix/{$endToEndId}";
    $resposta = efi_fazer_requisicao($endpoint, 'GET');
    
    return $resposta;
}

/**
 * Lista PIX recebidos por período
 * @param string $inicio Data início (ISO 8601)
 * @param string $fim Data fim (ISO 8601)
 * @return array|false Lista de PIX ou false em caso de erro
 */
function efi_listar_pix_recebidos($inicio, $fim) {
    $endpoint = "/v2/pix";
    $params = [
        'inicio' => $inicio,
        'fim' => $fim
    ];
    
    $url = $endpoint . '?' . http_build_query($params);
    $resposta = efi_fazer_requisicao($url, 'GET');
    
    return $resposta;
}

/**
 * Processa baixa automática do pagamento
 * @param string $txid ID da transação
 * @param int $participante_id ID do participante
 * @return bool Sucesso da operação
 */
function efi_processar_baixa_automatica($txid, $participante_id) {
    try {
        // Consultar cobrança
        $cobranca = efi_consultar_cobranca($txid);
        
        if (!$cobranca) {
            registrar_log('efi_erro_consulta', "TXID: {$txid} não encontrado");
            return false;
        }
        
        // Verificar se foi pago
        if ($cobranca['status'] !== 'CONCLUIDA') {
            return false;
        }
        
        // Buscar dados do pagamento no banco
        $pagamento = buscar_um("
            SELECT p.*, pa.nome, pa.evento_id 
            FROM pagamentos p 
            JOIN participantes pa ON p.participante_id = pa.id 
            WHERE p.pix_txid = ? AND p.status = ?
        ", [$txid, PAGAMENTO_PENDENTE]);
        
        if (!$pagamento) {
            registrar_log('efi_erro_pagamento', "Pagamento não encontrado para TXID: {$txid}");
            return false;
        }
        
        // Atualizar status do pagamento
        $sucesso = atualizar_registro('pagamentos', [
            'status' => PAGAMENTO_PAGO,
            'pago_em' => date('Y-m-d H:i:s'),
            'observacoes' => 'Pago via PIX EFI Bank - TXID: ' . $txid
        ], ['id' => $pagamento['id']]);
        
        if ($sucesso) {
            // Atualizar status do participante
            atualizar_registro('participantes', [
                'status' => PARTICIPANTE_PAGO
            ], ['id' => $participante_id]);
            
            // Registrar log
            registrar_log('efi_baixa_automatica', 
                "Pagamento confirmado | TXID: {$txid} | Participante: {$pagamento['nome']} | Valor: R$ {$pagamento['valor']}"
            );
            
            // Enviar WhatsApp de confirmação
            $telefone = buscar_um("SELECT whatsapp FROM participantes WHERE id = ?", [$participante_id])['whatsapp'];
            $evento = buscar_um("SELECT nome FROM eventos WHERE id = ?", [$pagamento['evento_id']])['nome'];
            
            $mensagem = "🎉 *Pagamento Confirmado!*\n\n";
            $mensagem .= "Evento: {$evento}\n";
            $mensagem .= "Valor: R$ " . number_format($pagamento['valor'], 2, ',', '.') . "\n";
            $mensagem .= "Status: ✅ PAGO\n\n";
            $mensagem .= "Sua inscrição foi confirmada! Em breve você receberá o QR Code de acesso.";
            
            enviar_whatsapp($telefone, $mensagem);
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Erro na baixa automática EFI: " . $e->getMessage());
        registrar_log('efi_erro_baixa', "Erro: " . $e->getMessage() . " | TXID: {$txid}");
        return false;
    }
}

/**
 * Configura webhook para recebimento de notificações
 * @param string $webhook_url URL do webhook
 * @return bool Sucesso da operação
 */
function efi_configurar_webhook($webhook_url) {
    $dados = [
        'webhookUrl' => $webhook_url
    ];
    
    $endpoint = '/v2/webhook/pix';
    $resposta = efi_fazer_requisicao($endpoint, 'PUT', $dados);
    
    if ($resposta) {
        registrar_log('efi_webhook_configurado', "URL: {$webhook_url}");
        return true;
    }
    
    return false;
}

/**
 * Testa configuração da EFI Bank
 * @return array Resultado dos testes
 */
function efi_testar_configuracao() {
    $resultados = [
        'certificado' => false,
        'credenciais' => false,
        'token' => false,
        'api' => false
    ];
    
    $config = obter_config_efi();
    
    // Teste 1: Certificado
    if (file_exists($config['certificado'])) {
        $resultados['certificado'] = true;
    }
    
    // Teste 2: Credenciais
    if (!empty($config['client_id']) && !empty($config['client_secret'])) {
        $resultados['credenciais'] = true;
    }
    
    // Teste 3: Token
    $token = efi_obter_token();
    if ($token) {
        $resultados['token'] = true;
        
        // Teste 4: API (listar cobranças)
        $teste_api = efi_fazer_requisicao('/v2/cob', 'GET');
        if ($teste_api !== false) {
            $resultados['api'] = true;
        }
    }
    
    return $resultados;
}

?> 