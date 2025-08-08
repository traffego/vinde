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
    
    // Verificar se certificado existe
    if (empty($config_efi['efi_certificado_path']) || !file_exists($config_efi['efi_certificado_path'])) {
        error_log("EFI: Certificado não encontrado ou não configurado");
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
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json'
        ],
        // Certificado P12 conforme documentação
        CURLOPT_SSLCERT => $config['certificado'],
        CURLOPT_SSLCERTPASSWD => $config['certificate_password'],
        CURLOPT_SSLCERTTYPE => 'P12',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0
    ]);
    
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
        CURLOPT_SSLCERT => $config['certificado'],
        CURLOPT_SSLCERTPASSWD => $config['certificate_password'],
        CURLOPT_SSLCERTTYPE => 'P12',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0
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
        error_log("EFI cURL Error: " . $error);
        registrar_log_efi('efi_curl_error', "Erro cURL: " . $error . " | Endpoint: " . $endpoint);
        return false;
    }
    
    $data_response = json_decode($response, true);
    
    if ($config['debug']) {
        error_log("EFI Debug: Resposta HTTP {$httpCode}: " . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
    }
    
    if ($httpCode >= 400) {
        error_log("EFI API Error HTTP {$httpCode}: " . $response);
        registrar_log_efi('efi_api_error', "HTTP {$httpCode} em {$endpoint}: " . $response);
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
    $dados = [
        'webhookUrl' => $webhook_url
    ];
    
    $endpoint = '/v2/webhook/pix';
    $resposta = efi_fazer_requisicao($endpoint, 'PUT', $dados);
    
    if ($resposta) {
        registrar_log_efi('efi_webhook_configurado', "URL: {$webhook_url}");
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

/**
 * Função principal para criar PIX completo via EFI Bank
 * Esta função integra criação de cobrança + geração de QR Code
 * @param array $dados_pagamento Array com dados do pagamento
 * @return array|false Resultado completo ou false em erro
 */
function efi_criar_pix_completo($dados_pagamento) {
    // Verificar se EFI está configurado e ativo
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
        // Gerar TXID único baseado no participante e timestamp
        $txid = 'VINDE' . str_pad($dados_pagamento['participante_id'], 6, '0', STR_PAD_LEFT) . substr(time(), -6);
        
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
        
        // Calcular data de expiração
        $expiracao_segundos = $dados_pagamento['expiracao'] ?? 3600;
        $expires_at = date('Y-m-d H:i:s', time() + $expiracao_segundos);
        
        // Retornar dados completos para salvar no banco
        $resultado = [
            'sucesso' => true,
            'pix_txid' => $txid,
            'pix_loc_id' => $loc_id,
            'pix_qrcode_data' => $qrcode['qrcode'] ?? '',
            'pix_qrcode_url' => $qrcode['linkVisualizacao'] ?? '',
            'pix_expires_at' => $expires_at,
            'efi_response' => $cobranca,
            'qr_response' => $qrcode,
            'status' => $cobranca['status'] ?? 'ATIVA'
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

?> 