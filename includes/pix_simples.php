<?php
// Sistema PIX Simples - Sem necessidade de certificado EFI Bank
// Arquivo: includes/pix_simples.php

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Gera payload PIX para QR Code estático
 * @param string $chave_pix Chave PIX (CPF, CNPJ, email, telefone ou chave aleatória)
 * @param float $valor Valor da transação
 * @param string $nome_recebedor Nome do recebedor
 * @param string $cidade Cidade do recebedor
 * @param string $descricao Descrição da transação
 * @param string $txid ID da transação (opcional)
 * @return string Payload PIX
 */
function gerar_payload_pix($chave_pix, $valor, $nome_recebedor, $cidade, $descricao = '', $txid = '') {
    // Função para calcular CRC16 CCITT
    function crc16($data) {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(dechex($crc));
    }
    
    // Função para formatar campo PIX
    function formatarCampo($id, $valor) {
        $tamanho = str_pad(strlen($valor), 2, '0', STR_PAD_LEFT);
        return $id . $tamanho . $valor;
    }
    
    // Construir payload
    $payload = '';
    
    // Payload Format Indicator
    $payload .= formatarCampo('00', '01');
    
    // Point of Initiation Method (12 = QR reutilizável, 11 = QR único)
    $payload .= formatarCampo('01', '12');
    
    // Merchant Account Information
    $conta_info = '';
    $conta_info .= formatarCampo('00', 'BR.GOV.BCB.PIX'); // GUI
    $conta_info .= formatarCampo('01', $chave_pix); // Chave PIX
    if (!empty($descricao)) {
        $conta_info .= formatarCampo('02', $descricao); // Descrição
    }
    $payload .= formatarCampo('26', $conta_info);
    
    // Merchant Category Code
    $payload .= formatarCampo('52', '0000');
    
    // Transaction Currency (986 = BRL)
    $payload .= formatarCampo('53', '986');
    
    // Transaction Amount
    if ($valor > 0) {
        $payload .= formatarCampo('54', number_format($valor, 2, '.', ''));
    }
    
    // Country Code
    $payload .= formatarCampo('58', 'BR');
    
    // Merchant Name
    $payload .= formatarCampo('59', substr($nome_recebedor, 0, 25));
    
    // Merchant City
    $payload .= formatarCampo('60', substr($cidade, 0, 15));
    
    // Additional Data Field Template
    if (!empty($txid)) {
        $adicional = formatarCampo('05', substr($txid, 0, 25)); // Reference Label
        $payload .= formatarCampo('62', $adicional);
    }
    
    // CRC16
    $payload .= '6304';
    $crc = crc16($payload);
    $payload .= $crc;
    
    return $payload;
}

/**
 * Gera QR Code PIX usando API externa gratuita
 * @param string $payload Payload PIX
 * @return string|false URL da imagem do QR Code ou false em caso de erro
 */
function gerar_qrcode_pix($payload) {
    // Usar API gratuita do QR Server
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($payload);
    
    // Verificar se a URL é válida
    $headers = @get_headers($qr_url);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $qr_url;
    }
    
    return false;
}

/**
 * Cria cobrança PIX simples (sem API bancária)
 * @param int $participante_id ID do participante
 * @param float $valor Valor da cobrança
 * @param string $descricao Descrição da cobrança
 * @return array|false Dados da cobrança ou false em caso de erro
 */
function criar_cobranca_pix_simples($participante_id, $valor, $descricao) {
    try {
        // Gerar TXID único
        $txid = 'VINDE' . date('YmdHis') . str_pad($participante_id, 6, '0', STR_PAD_LEFT);
        
        // Obter configurações PIX
        $chave_pix = obter_configuracao('pix_chave', PIX_CHAVE);
        $nome_recebedor = obter_configuracao('pix_nome', PIX_NOME);
        $cidade = obter_configuracao('pix_cidade', PIX_CIDADE);
        
        // Gerar payload PIX
        $payload = gerar_payload_pix(
            $chave_pix,
            $valor,
            $nome_recebedor,
            $cidade,
            $descricao,
            $txid
        );
        
        // Gerar QR Code
        $qrcode_url = gerar_qrcode_pix($payload);
        
        if (!$qrcode_url) {
            return false;
        }
        
        // Retornar dados da cobrança
        return [
            'txid' => $txid,
            'valor' => $valor,
            'payload' => $payload,
            'qrcode_url' => $qrcode_url,
            'chave_pix' => $chave_pix,
            'nome_recebedor' => $nome_recebedor,
            'cidade' => $cidade,
            'descricao' => $descricao,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hora
            'created_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao criar cobrança PIX simples: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica status de pagamento PIX (simulação para desenvolvimento)
 * @param string $txid ID da transação
 * @return array Status do pagamento
 */
function verificar_pagamento_pix_simples($txid) {
    // Em um sistema real, aqui você consultaria a API do banco
    // Por enquanto, vamos simular baseado no tempo
    
    $pagamento = buscar_um("
        SELECT p.*, pa.nome as participante_nome 
        FROM pagamentos p 
        JOIN participantes pa ON p.participante_id = pa.id 
        WHERE p.pix_txid = ?
    ", [$txid]);
    
    if (!$pagamento) {
        return ['status' => 'not_found', 'message' => 'Pagamento não encontrado'];
    }
    
    // Se já está pago, retornar status
    if ($pagamento['status'] === 'pago') {
        return [
            'status' => 'paid',
            'paid_at' => $pagamento['pago_em'],
            'message' => 'Pagamento confirmado'
        ];
    }
    
    // Verificar se expirou
    $expires_at = strtotime($pagamento['pix_expires_at']);
    if (time() > $expires_at) {
        return ['status' => 'expired', 'message' => 'Pagamento expirado'];
    }
    
    // Para desenvolvimento: simular pagamento após 2 minutos (para testes)
    $created_at = strtotime($pagamento['criado_em']);
    if (time() > ($created_at + 120)) { // 2 minutos para teste
        // Marcar como pago automaticamente (apenas para desenvolvimento)
        if (AMBIENTE === 'desenvolvimento') {
            processar_pagamento_confirmado($pagamento['id']);
            return [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'message' => 'Pagamento confirmado (simulação)'
            ];
        }
    }
    
    return ['status' => 'pending', 'message' => 'Aguardando pagamento'];
}

/**
 * Processa confirmação de pagamento
 * @param int $pagamento_id ID do pagamento
 * @return bool
 */
function processar_pagamento_confirmado($pagamento_id) {
    try {
        iniciar_transacao();
        
        // Atualizar status do pagamento
        atualizar_registro('pagamentos', [
            'status' => 'pago',
            'pago_em' => date('Y-m-d H:i:s')
        ], ['id' => $pagamento_id]);
        
        // Buscar dados do participante
        $pagamento = buscar_um("
            SELECT p.participante_id, pa.nome, pa.email, e.nome as evento_nome
            FROM pagamentos p
            JOIN participantes pa ON p.participante_id = pa.id
            JOIN eventos e ON pa.evento_id = e.id
            WHERE p.id = ?
        ", [$pagamento_id]);
        
        // Atualizar status do participante
        atualizar_registro('participantes', [
            'status' => 'pago'
        ], ['id' => $pagamento['participante_id']]);
        
        confirmar_transacao();
        
        // Aqui você pode adicionar envio de email de confirmação
        // enviar_email_confirmacao($pagamento);
        
        return true;
        
    } catch (Exception $e) {
        desfazer_transacao();
        error_log("Erro ao processar pagamento confirmado: " . $e->getMessage());
        return false;
    }
}
?> 