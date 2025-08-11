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
 * Obtém configurações do ambiente EFI a partir do banco de dados
 * @return array|false Configurações do ambiente ou false se não configurado
 */
function obter_config_efi() {
    // Verificar se EFI está ativo
    if (!efi_esta_ativo()) {
        return false;
    }
    
    // Obter configurações do banco
    $config_efi = obter_configuracoes_efi();
    
    // Verificar se as configurações básicas estão definidas
    if (empty($config_efi['efi_client_id']) || empty($config_efi['efi_client_secret'])) {
        error_log("EFI: Credenciais não configuradas");
        return false;
    }
    
    // Normalizar caminho do certificado e verificar existência
    if (!empty($config_efi['efi_certificado_path'])) {
        $certPath = $config_efi['efi_certificado_path'];
        // Se caminho relativo, tentar a partir da pasta includes/..
        if (!file_exists($certPath)) {
            $candidate = realpath(__DIR__ . '/../' . ltrim($certPath, './'));
            if ($candidate && file_exists($candidate)) {
                $certPath = $candidate;
            }
        }
        // Segunda tentativa: a partir da raiz do projeto (dirname(__DIR__))
        if (!file_exists($certPath)) {
            $candidate2 = realpath(dirname(__DIR__) . '/' . ltrim($certPath, './'));
            if ($candidate2 && file_exists($candidate2)) {
                $certPath = $candidate2;
            }
        }
        // Preferir PEM se existir um arquivo com mesmo basename e extensão .pem
        $basenameNoExt = preg_replace('/\.(p12|pfx)$/i', '', $certPath);
        if ($basenameNoExt && file_exists($basenameNoExt . '.pem')) {
            $certPath = $basenameNoExt . '.pem';
        }
        $config_efi['efi_certificado_path'] = $certPath;
    }
    
    if (empty($config_efi['efi_certificado_path']) || !file_exists($config_efi['efi_certificado_path'])) {
        error_log("EFI: Certificado não encontrado ou não configurado em: " . ($config_efi['efi_certificado_path'] ?? '(vazio)'));
        return false;
    }
    
    // Determinar URLs da API baseado no ambiente
    $is_sandbox = $config_efi['efi_sandbox'] === '1';
    $api_url = $is_sandbox 
        ? 'https://pix-h.api.efipay.com.br' 
        : 'https://pix.api.efipay.com.br';
    
    return [
        'client_id' => $config_efi['efi_client_id'],
        'client_secret' => $config_efi['efi_client_secret'],
        'certificado' => $config_efi['efi_certificado_path'],
        'certificate_password' => $config_efi['efi_certificate_password'] ?? '',
        'api_url' => $api_url,
        'pix_key' => $config_efi['efi_pix_key'] ?? '',
        'webhook_url' => $config_efi['efi_webhook_url'] ?? '',
        'webhook_secret' => $config_efi['efi_webhook_secret'] ?? '',
        'debug' => $config_efi['efi_debug'] === '1',
        'sandbox' => $is_sandbox
    ];
}

/**
 * Autentica na API EFI e obtém token de acesso
 * Seguindo documentação oficial: https://dev.efipay.com.br/docs/api-pix/credenciais
 * @return string|false Token de acesso ou false em caso de erro
 */
function efi_obter_token() {
    $config = obter_config_efi();
    
    // Verificar se a configuração foi obtida com sucesso
    if (!$config) {
        error_log("EFI: Configuração não disponível ou EFI inativo");
        return false;
    }
    
    // Verificar se certificado existe
    if (!file_exists($config['certificado'])) {
        error_log("EFI: Certificado não encontrado: " . $config['certificado']);
        return false;
    }
    
    // Basic Auth conforme documentação oficial
    $auth = base64_encode($config['client_id'] . ':' . $config['client_secret']);
    $url = $config['api_url'] . '/oauth/token';
    
    // Payload conforme documentação
    $postData = json_encode(['grant_type' => 'client_credentials']);
    
    // Log de debug se ativo
    if ($config['debug']) {
        error_log("EFI Debug: Obtendo token - URL: $url");
        error_log("EFI Debug: Ambiente: " . ($config['sandbox'] ? 'Sandbox' : 'Produção'));
    }
    
    // Inicializar cURL conforme exemplo oficial
    $curl = curl_init();
    
    $curlOptionsAuth = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0
    ];

    // Suporte a certificado em P12 ou PEM
    $ext = strtolower(pathinfo($config['certificado'], PATHINFO_EXTENSION));
    if ($ext === 'pem') {
        $curlOptionsAuth[CURLOPT_SSLCERT] = $config['certificado'];
        $curlOptionsAuth[CURLOPT_SSLCERTTYPE] = 'PEM';
        if (!empty($config['certificate_password'])) {
            $curlOptionsAuth[CURLOPT_SSLCERTPASSWD] = $config['certificate_password'];
            $curlOptionsAuth[CURLOPT_SSLKEYPASSWD] = $config['certificate_password'];
        }
        // Opcional: se existir arquivo de chave com mesmo prefixo e extensão .key, usar
        $possibleKey = preg_replace('/\.pem$/i', '.key', $config['certificado']);
        if ($possibleKey && file_exists($possibleKey)) {
            $curlOptionsAuth[CURLOPT_SSLKEY] = $possibleKey;
        }
    } else {
        // Padrão anterior: P12
        $curlOptionsAuth[CURLOPT_SSLCERT] = $config['certificado'];
        $curlOptionsAuth[CURLOPT_SSLCERTPASSWD] = $config['certificate_password'];
        $curlOptionsAuth[CURLOPT_SSLCERTTYPE] = 'P12';
    }

    curl_setopt_array($curl, $curlOptionsAuth);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        error_log("EFI cURL Error: " . $error);
        registrar_log_efi('efi_auth_error', "cURL Error: " . $error);
        return false;
    }
    
    if ($config['debug']) {
        error_log("EFI Debug: Resposta HTTP {$httpCode}: " . substr($response, 0, 200) . (strlen($response) > 200 ? '...' : ''));
    }
    
    if ($httpCode !== 200) {
        error_log("EFI Auth Error HTTP {$httpCode}: " . $response);
        registrar_log_efi('efi_auth_error', "HTTP {$httpCode}: " . $response);
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['access_token'])) {
        error_log("EFI: Token não encontrado na resposta");
        registrar_log_efi('efi_auth_error', "Token não encontrado na resposta: " . $response);
        return false;
    }
    
    if ($config['debug']) {
        error_log("EFI Debug: Token obtido com sucesso");
    }
    
    registrar_log_efi('efi_auth_success', "Token obtido com sucesso - Ambiente: " . ($config['sandbox'] ? 'Sandbox' : 'Produção'));
    
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
    $token = efi_obter_token();
    
    if ($token) {
        // Cache do token (válido por 1 hora conforme documentação)
        $_SESSION['efi_token'] = $token;
        $_SESSION['efi_token_expires'] = time() + 3600; // 1 hora
    }
    
    return $token;
}

/**
 * Faz requisição para API EFI
 * @param string $endpoint Endpoint da API
 * @param string $method Método HTTP (GET, POST, PUT, DELETE)
 * @param array $data Dados para enviar
 * @return array|false Resposta da API ou false em caso de erro
 */
function efi_fazer_requisicao($endpoint, $method = 'GET', $data = null) {
    // Expor informações da última chamada para diagnóstico
    global $EFI_LAST_HTTP_CODE, $EFI_LAST_ERROR_MESSAGE;
    $EFI_LAST_HTTP_CODE = null;
    $EFI_LAST_ERROR_MESSAGE = null;
    $token = efi_obter_token_valido();
    
    if (!$token) {
        return false;
    }
    
    $config = obter_config_efi();
    
    if (!$config) {
        return false;
    }
    
    $url = $config['api_url'] . $endpoint;
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    
    if ($config['debug']) {
        error_log("EFI Debug: Fazendo requisição {$method} para: {$url}");
        if ($data) {
            error_log("EFI Debug: Dados: " . json_encode($data));
        }
    }
    
    $curl = curl_init();
    
    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0
    ];

    // Suporte a certificado em P12 ou PEM nas chamadas gerais
    $extReq = strtolower(pathinfo($config['certificado'], PATHINFO_EXTENSION));
    if ($extReq === 'pem') {
        $curlOptions[CURLOPT_SSLCERT] = $config['certificado'];
        $curlOptions[CURLOPT_SSLCERTTYPE] = 'PEM';
        if (!empty($config['certificate_password'])) {
            $curlOptions[CURLOPT_SSLCERTPASSWD] = $config['certificate_password'];
            $curlOptions[CURLOPT_SSLKEYPASSWD] = $config['certificate_password'];
        }
        $possibleKey2 = preg_replace('/\.pem$/i', '.key', $config['certificado']);
        if ($possibleKey2 && file_exists($possibleKey2)) {
            $curlOptions[CURLOPT_SSLKEY] = $possibleKey2;
        }
    } else {
        $curlOptions[CURLOPT_SSLCERT] = $config['certificado'];
        $curlOptions[CURLOPT_SSLCERTPASSWD] = $config['certificate_password'];
        $curlOptions[CURLOPT_SSLCERTTYPE] = 'P12';
    }
    
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
    $EFI_LAST_HTTP_CODE = $httpCode;
    
    if ($error) {
        error_log("EFI cURL Error: " . $error);
        registrar_log_efi('efi_curl_error', "Erro cURL: " . $error . " | Endpoint: " . $endpoint);
        $EFI_LAST_ERROR_MESSAGE = $error;
        return false;
    }
    
    $data_response = json_decode($response, true);
    
    if ($config['debug']) {
        error_log("EFI Debug: Resposta HTTP {$httpCode}: " . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
    }
    
    if ($httpCode >= 400) {
        error_log("EFI API Error HTTP {$httpCode}: " . $response);
        registrar_log_efi('efi_api_error', "HTTP {$httpCode} em {$endpoint}: " . $response);
        $EFI_LAST_ERROR_MESSAGE = $response;
        return false;
    }
    
    // Verificar se a resposta é JSON válida
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("EFI: Resposta não é JSON válida: " . json_last_error_msg());
        registrar_log_efi('efi_json_error', "JSON inválido em {$endpoint}: " . json_last_error_msg());
        return false;
    }
    
    return $data_response;
}

/**
 * Cria cobrança PIX via EFI Bank
 * Seguindo documentação oficial: https://dev.efipay.com.br/docs/api-pix/cob
 * @param string $txid ID único da transação (max 35 chars)
 * @param float $valor Valor da cobrança
 * @param string $descricao Descrição da cobrança (max 140 chars)
 * @param string $nome_pagador Nome do pagador
 * @param string $cpf_pagador CPF do pagador
 * @param int $expiracao Tempo de expiração em segundos (padrão: 3600)
 * @return array|false Dados da cobrança ou false em caso de erro
 */
function efi_criar_cobranca_pix($txid, $valor, $descricao, $nome_pagador, $cpf_pagador, $expiracao = 3600) {
    // Obter configurações EFI
    $config = obter_config_efi();
    
    if (!$config) {
        error_log("EFI: Configuração não disponível para criar cobrança");
        registrar_log_efi('efi_cobranca_erro', "Configuração não disponível");
        return false;
    }
    
    // Verificar se a chave PIX está configurada
    if (empty($config['pix_key'])) {
        error_log("EFI: Chave PIX não configurada");
        registrar_log_efi('efi_cobranca_erro', "Chave PIX não configurada");
        return false;
    }
    
    // Validações conforme documentação EFI
    if (strlen($txid) > 35) {
        error_log("EFI: TXID muito longo (max 35 chars): " . $txid);
        registrar_log_efi('efi_cobranca_erro', "TXID muito longo: " . $txid);
        return false;
    }
    
    if (strlen($descricao) > 140) {
        $descricao = substr($descricao, 0, 140);
    }
    
    // Estrutura conforme documentação oficial EFI
    $dados = [
        'calendario' => [
            'expiracao' => $expiracao
        ],
        'valor' => [
            'original' => number_format($valor, 2, '.', '')
        ],
        'chave' => $config['pix_key'],
        'solicitacaoPagador' => $descricao
    ];
    
    // Adicionar info do pagador apenas se fornecido
    if (!empty($nome_pagador) || !empty($cpf_pagador)) {
        $dados['infoAdicionais'] = [];
        
        if (!empty($nome_pagador)) {
            $dados['infoAdicionais'][] = [
                'nome' => 'Pagador',
                'valor' => substr($nome_pagador, 0, 200) // Max 200 chars
            ];
        }
        
        if (!empty($cpf_pagador)) {
            $dados['infoAdicionais'][] = [
                'nome' => 'CPF',
                'valor' => preg_replace('/[^0-9]/', '', $cpf_pagador)
            ];
        }
    }
    
    if ($config['debug']) {
        error_log("EFI Debug: Criando cobrança PIX - TXID: {$txid} | Valor: R$ {$valor}");
        error_log("EFI Debug: Dados da cobrança: " . json_encode($dados));
    }
    
    $endpoint = "/v2/cob/{$txid}";
    registrar_log_efi('efi_cobranca_tentativa', "TXID: {$txid} | Valor: R$ {$valor} | Endpoint: {$endpoint}");
    
    $resposta = efi_fazer_requisicao($endpoint, 'PUT', $dados);
    
    if ($resposta) {
        registrar_log_efi('efi_cobranca_criada', 
            "TXID: {$txid} | Valor: R$ {$valor} | Status: " . ($resposta['status'] ?? 'N/A') . 
            " | Loc ID: " . ($resposta['loc']['id'] ?? 'N/A') .
            " | Ambiente: " . ($config['sandbox'] ? 'Sandbox' : 'Produção')
        );
    } else {
        registrar_log_efi('efi_cobranca_erro', "Falha ao criar cobrança - TXID: {$txid} | Valor: R$ {$valor}");
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
            registrar_log_efi('efi_erro_consulta', "TXID: {$txid} não encontrado");
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
            registrar_log_efi('efi_erro_pagamento', "Pagamento não encontrado para TXID: {$txid}");
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
            registrar_log_efi('efi_baixa_automatica', 
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
        registrar_log_efi('efi_erro_baixa', "Erro: " . $e->getMessage() . " | TXID: {$txid}");
        return false;
    }
}

/**
 * Configura webhook para recebimento de notificações
 * @param string $webhook_url URL do webhook
 * @return bool Sucesso da operação
 */
function efi_configurar_webhook($webhook_url) {
    // Obter configuração para acessar a chave PIX correta
    $cfg = obter_config_efi();
    if (!$cfg) {
        return false;
    }

    $chavePix = trim($cfg['pix_key'] ?? '');
    if ($chavePix === '') {
        registrar_log_efi('efi_webhook_erro', 'Chave PIX não configurada');
        return false;
    }

    $dados = [
        'webhookUrl' => $webhook_url
    ];

    // Endpoint correto para configuração de webhook na Efí é por chave PIX
    $endpoint = '/v2/webhook/' . rawurlencode($chavePix);
    $resposta = efi_fazer_requisicao($endpoint, 'PUT', $dados);

    if ($resposta) {
        registrar_log_efi('efi_webhook_configurado', "Chave: {$chavePix} | URL: {$webhook_url}");
        return true;
    }

    return false;
}

/**
 * Registra o webhook usando a URL salva em configuracoes (efi_webhook_url)
 */
function efi_registrar_webhook_configurado() {
    $cfg = obter_config_efi();
    if (!$cfg) {
        return ['sucesso' => false, 'mensagem' => 'EFI inativo ou credenciais/certificado ausentes'];
    }
    $url = $cfg['webhook_url'] ?? '';
    if (empty($url) || stripos($url, 'http') !== 0) {
        return ['sucesso' => false, 'mensagem' => 'URL do webhook inválida. Configure a chave efi_webhook_url com a URL completa (https://...)'];
    }
    $chavePix = trim($cfg['pix_key'] ?? '');
    if ($chavePix === '') {
        return ['sucesso' => false, 'mensagem' => 'Chave PIX não configurada. Defina `efi_pix_key` nas configurações.'];
    }
    $ok = efi_configurar_webhook($url);
    if ($ok) {
        return ['sucesso' => true, 'mensagem' => 'Webhook registrado com sucesso'];
    }
    global $EFI_LAST_HTTP_CODE, $EFI_LAST_ERROR_MESSAGE;
    return [
        'sucesso' => false,
        'mensagem' => 'Falha ao registrar webhook. Verifique logs EFI e credenciais.',
        'http_code' => $EFI_LAST_HTTP_CODE,
        'erro' => $EFI_LAST_ERROR_MESSAGE
    ];
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

/**
 * Função principal para criar PIX completo via EFI Bank
 * Esta função integra criação de cobrança + geração de QR Code
 * @param array $dados_pagamento Array com dados do pagamento
 * @return array|false Resultado completo ou false em erro
 */
function efi_criar_pix_completo($dados_pagamento) {
    // Verificar se EFI está configurado e ativo (obrigatório em produção)
    if (!efi_esta_ativo()) {
        return ['erro' => 'EFI Bank não está ativo ou configurado'];
    }
    
    // Validar dados obrigatórios
    $campos_obrigatorios = ['valor', 'descricao', 'participante_id', 'evento_nome'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($dados_pagamento[$campo])) {
            return ['erro' => "Campo obrigatório não informado: {$campo}"];
        }
    }
    
    try {
        // Usar TXID customizado se fornecido, senão gerar novo
        if (!empty($dados_pagamento['txid_customizado'])) {
            $txid = $dados_pagamento['txid_customizado'];
            if ($dados_pagamento['debug'] ?? false) {
                error_log("EFI Debug: Usando TXID customizado: {$txid} (tamanho: " . strlen($txid) . ")");
            }
        } else {
            // Gerar TXID único conforme padrão EFI Bank
            $txid = efi_gerar_txid_valido('VINDE', $dados_pagamento['participante_id']);
            if ($dados_pagamento['debug'] ?? false) {
                error_log("EFI Debug: TXID gerado automaticamente: {$txid} (tamanho: " . strlen($txid) . ")");
            }
        }
        
        // Criar cobrança PIX na EFI Bank
        $cobranca = efi_criar_cobranca_pix(
            $txid,
            $dados_pagamento['valor'],
            $dados_pagamento['descricao'],
            $dados_pagamento['nome_pagador'] ?? '',
            $dados_pagamento['cpf_pagador'] ?? '',
            $dados_pagamento['expiracao'] ?? 3600
        );
        
        if (!$cobranca) {
            return ['erro' => 'Falha ao criar cobrança PIX na EFI Bank'];
        }
        
        // Verificar se a cobrança foi criada com sucesso
        if (!isset($cobranca['loc']['id'])) {
            return ['erro' => 'Cobrança criada mas Location ID não encontrado'];
        }
        
        $loc_id = $cobranca['loc']['id'];
        
        // Gerar QR Code
        $qrcode = efi_gerar_qrcode($loc_id);
        
        if (!$qrcode) {
            return ['erro' => 'Falha ao gerar QR Code PIX'];
        }
        
        // Verificar se o payload PIX é válido e preparar a imagem do QR (sempre base64 da EFI)
        $payload_pix = $qrcode['qrcode'] ?? '';
        $imagem_qrcode = $qrcode['imagemQrcode'] ?? '';
        $imagem_qrcode = is_string($imagem_qrcode) ? preg_replace('/\s+/', '', trim($imagem_qrcode)) : '';
        if (empty($imagem_qrcode)) {
            return ['erro' => 'Imagem do QR Code (base64) não retornada pela EFI'];
        }
        // Evitar prefixo duplicado (algumas respostas já vêm com data:image/png;base64,)
        if (strpos($imagem_qrcode, 'data:image') === 0) {
            $qr_url = $imagem_qrcode;
        } else {
            $qr_url = 'data:image/png;base64,' . $imagem_qrcode;
        }
        
        // Validar se o payload PIX está correto
        $payload_valido = !empty($payload_pix) && 
                         strlen($payload_pix) > 50 && 
                         (strpos($payload_pix, '00020101') === 0 || strpos($payload_pix, '00020126') === 0) &&
                         strpos($payload_pix, 'BR.GOV.BCB.PIX') !== false;
        
        // Sem fallback local: em produção exigimos payload vindo da EFI
        if (!$payload_valido) {
            return ['erro' => 'Payload do QR Code não retornado pela EFI'];
        }
        
        // Calcular data de expiração
        $expiracao_segundos = $dados_pagamento['expiracao'] ?? 3600;
        $expires_at = date('Y-m-d H:i:s', time() + $expiracao_segundos);
        
        // Retornar dados completos para salvar no banco
        $resultado = [
            'sucesso' => true,
            'pix_txid' => $txid,
            'pix_loc_id' => $loc_id,
            'pix_qrcode_data' => $payload_pix,
            'pix_qrcode_url' => $qr_url,
            'pix_expires_at' => $expires_at,
            'efi_response' => $cobranca,
            'qr_response' => $qrcode,
            'status' => $cobranca['status'] ?? 'ATIVA',
            'payload_source' => $payload_valido ? 'efi_bank' : 'pix_simples'
        ];
        
        // Log de sucesso
        registrar_log_efi('efi_pix_completo', 
            "PIX criado com sucesso - TXID: {$txid} | Participante: {$dados_pagamento['participante_id']} | Valor: R$ {$dados_pagamento['valor']}"
        );
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("EFI: Erro ao criar PIX completo: " . $e->getMessage());
        registrar_log_efi('efi_pix_erro', "Erro ao criar PIX: " . $e->getMessage());
        return ['erro' => 'Erro interno ao processar PIX: ' . $e->getMessage()];
    }
}

/**
 * Função para consultar status de pagamento PIX
 * @param string $txid ID da transação
 * @return array|false Status do pagamento ou false em erro
 */
function efi_verificar_pagamento_pix($txid) {
    if (!efi_esta_ativo()) {
        return false;
    }
    
    try {
        $cobranca = efi_consultar_cobranca($txid);
        
        if (!$cobranca) {
            return false;
        }
        
        $status_pago = isset($cobranca['pix']) && !empty($cobranca['pix']);
        
        $resultado = [
            'txid' => $txid,
            'status' => $cobranca['status'] ?? 'N/A',
            'pago' => $status_pago,
            'valor_original' => $cobranca['valor']['original'] ?? 0,
            'data_criacao' => $cobranca['calendario']['criacao'] ?? null,
            'data_expiracao' => null
        ];
        
        // Calcular data de expiração se disponível
        if (isset($cobranca['calendario']['criacao']) && isset($cobranca['calendario']['expiracao'])) {
            $criacao = strtotime($cobranca['calendario']['criacao']);
            $expiracao_segundos = $cobranca['calendario']['expiracao'];
            $resultado['data_expiracao'] = date('Y-m-d H:i:s', $criacao + $expiracao_segundos);
        }
        
        // Dados do pagamento se foi realizado
        if ($status_pago && isset($cobranca['pix'][0])) {
            $pix_info = $cobranca['pix'][0];
            $resultado['pix_info'] = [
                'end_to_end_id' => $pix_info['endToEndId'] ?? '',
                'valor_pago' => $pix_info['valor'] ?? 0,
                'data_pagamento' => $pix_info['horario'] ?? null,
                'info_pagador' => $pix_info['infoPagador'] ?? ''
            ];
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("EFI: Erro ao verificar pagamento PIX {$txid}: " . $e->getMessage());
        return false;
    }
}

/**
 * Função utilitária para registrar logs do sistema EFI
 * @param string $tipo Tipo do log
 * @param string $mensagem Mensagem do log
 * @param string $txid TXID relacionado (opcional)
 */
function registrar_log_efi($tipo, $mensagem, $txid = null) {
    try {
        // Log básico sempre no error_log do PHP
        error_log("EFI Log [{$tipo}]: {$mensagem}" . ($txid ? " | TXID: {$txid}" : ""));
        
        // Tentar inserir no banco de dados
        try {
            // Verificar se a tabela existe
            $table_exists = buscar_um("SHOW TABLES LIKE 'efi_logs'");
            
            if (!$table_exists) {
                // Criar tabela se não existir
                executar("
                    CREATE TABLE IF NOT EXISTS efi_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tipo VARCHAR(50) NOT NULL,
                        mensagem TEXT NOT NULL,
                        txid VARCHAR(35) NULL,
                        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_tipo (tipo),
                        INDEX idx_txid (txid),
                        INDEX idx_criado_em (criado_em)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }
            
            // Inserir log
            executar("
                INSERT INTO efi_logs (tipo, mensagem, txid) 
                VALUES (?, ?, ?)
            ", [$tipo, $mensagem, $txid]);
            
        } catch (Exception $db_error) {
            // Se falhar no banco, pelo menos registrar no error_log
            error_log("Falha ao inserir log EFI no banco: " . $db_error->getMessage());
        }
        
    } catch (Exception $e) {
        // Se tudo falhar, registrar erro no error_log
        error_log("Erro crítico no sistema de logs EFI: " . $e->getMessage() . " | Log original: [{$tipo}] {$mensagem}");
    }
}

/**
 * Verificar se as configurações mínimas estão presentes
 * @return array Resultado da verificação com detalhes
 */
function efi_verificar_configuracoes() {
    $config_efi = obter_configuracoes_efi();
    $problemas = [];
    
    // Verificar campos obrigatórios
    $campos_obrigatorios = [
        'efi_client_id' => 'Client ID',
        'efi_client_secret' => 'Client Secret', 
        'efi_certificado_path' => 'Caminho do certificado',
        'efi_pix_key' => 'Chave PIX'
    ];
    
    foreach ($campos_obrigatorios as $campo => $nome) {
        if (empty($config_efi[$campo])) {
            $problemas[] = "$nome não configurado";
        }
    }
    
    // Verificar se certificado existe
    if (!empty($config_efi['efi_certificado_path'])) {
        if (!file_exists($config_efi['efi_certificado_path'])) {
            $problemas[] = "Arquivo de certificado não encontrado: " . $config_efi['efi_certificado_path'];
        }
    }
    
    // Verificar se EFI está ativo
    if ($config_efi['efi_ativo'] !== '1') {
        $problemas[] = "EFI Bank não está ativo";
    }
    
    return [
        'configurado' => empty($problemas),
        'problemas' => $problemas,
        'ambiente' => $config_efi['efi_sandbox'] === '1' ? 'sandbox' : 'producao'
    ];
}

/**
 * Gera TXID válido conforme padrão EFI Bank
 * Padrão: ^[a-zA-Z0-9]{26,35}$ (26-35 caracteres alfanuméricos)
 * @param string $prefixo Prefixo para o TXID (ex: 'VINDE')
 * @param int $participante_id ID do participante (opcional)
 * @return string TXID válido
 */
function efi_gerar_txid_valido($prefixo = 'VINDE', $participante_id = null) {
    $timestamp = time();
    $random_str = strtoupper(bin2hex(random_bytes(12))); // 24 caracteres hex
    
    if ($participante_id) {
        $participante_str = str_pad($participante_id, 6, '0', STR_PAD_LEFT);
        // Formato: PREFIX + 6 dígitos participante + 6 dígitos timestamp + resto random
        $txid = $prefixo . $participante_str . substr($timestamp, -6) . substr($random_str, 0, 35 - strlen($prefixo) - 6 - 6);
    } else {
        // Formato: PREFIX + 6 dígitos timestamp + resto random
        $txid = $prefixo . substr($timestamp, -6) . substr($random_str, 0, 35 - strlen($prefixo) - 6);
    }
    
    // Garantir que tem entre 26-35 caracteres e só alfanuméricos
    $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 35);
    
    // Se ficou menor que 26, preencher com random
    while (strlen($txid) < 26) {
        $txid .= strtoupper(dechex(mt_rand(0, 15)));
    }
    
    return substr($txid, 0, 35); // Máximo 35 caracteres
}

/**
 * Configura automaticamente configurações PIX básicas usando dados da EFI Bank
 * @return bool Sucesso da operação
 */
function efi_configurar_pix_basico() {
    try {
        $config_efi = obter_configuracoes_efi();
        
        if (empty($config_efi['efi_pix_key'])) {
            return false;
        }
        
        // Configurar chave PIX para o sistema de PIX simples também
        salvar_configuracao('pix_chave', $config_efi['efi_pix_key'], 'Chave PIX configurada via EFI Bank');
        salvar_configuracao('pix_nome', 'SAOFRANCISCODEASSIS', 'Nome para PIX (máximo 25 caracteres)');
        salvar_configuracao('pix_cidade', 'QUEIMADOS', 'Cidade para PIX (máximo 15 caracteres)');
        salvar_configuracao('pix_ativo', '1', 'PIX ativo no sistema');
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao configurar PIX básico: " . $e->getMessage());
        return false;
    }
}

?> 