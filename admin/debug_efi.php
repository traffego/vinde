<?php
/**
 * Script de debug detalhado da integração EFI Bank
 * Execute este arquivo para diagnosticar problemas específicos
 * Acesse: https://vinde.traffego.agency/admin/debug_efi.php
 */

require_once '../includes/init.php';

// Verificar se é admin
requer_login('admin');

header('Content-Type: application/json; charset=utf-8');

try {
    $debug_info = [
        'timestamp' => date('Y-m-d H:i:s'),
        'servidor' => [
            'php_version' => PHP_VERSION,
            'curl_version' => curl_version(),
            'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'N/A',
            'ambiente' => AMBIENTE ?? 'N/A'
        ],
        'configuracoes' => [
            'efi_esta_ativo' => efi_esta_ativo(),
            'config_efi' => obter_configuracoes_efi()
        ],
        'certificado' => [],
        'teste_conexao' => [],
        'logs_recentes' => []
    ];
    
    // Verificar certificado
    $config_efi = $debug_info['configuracoes']['config_efi'];
    $cert_path = $config_efi['efi_certificado_path'] ?? '';
    
    if (!empty($cert_path)) {
        $debug_info['certificado']['path'] = $cert_path;
        $debug_info['certificado']['existe'] = file_exists($cert_path);
        
        if (file_exists($cert_path)) {
            $debug_info['certificado']['tamanho'] = filesize($cert_path);
            $debug_info['certificado']['modificado'] = date('Y-m-d H:i:s', filemtime($cert_path));
            $debug_info['certificado']['permissoes'] = substr(sprintf('%o', fileperms($cert_path)), -4);
            
            // Verificar se é P12 válido
            if (function_exists('openssl_pkcs12_read')) {
                $cert_data = file_get_contents($cert_path);
                $certs = [];
                $senha = $config_efi['efi_certificate_password'] ?? '';
                
                if (openssl_pkcs12_read($cert_data, $certs, $senha)) {
                    $debug_info['certificado']['formato'] = 'P12 válido';
                    $debug_info['certificado']['tem_chave_privada'] = isset($certs['pkey']);
                    $debug_info['certificado']['tem_certificado'] = isset($certs['cert']);
                } else {
                    $debug_info['certificado']['formato'] = 'P12 inválido ou senha incorreta';
                    $debug_info['certificado']['openssl_error'] = openssl_error_string();
                }
            } else {
                $debug_info['certificado']['formato'] = 'Função openssl_pkcs12_read não disponível';
            }
        }
    } else {
        $debug_info['certificado']['erro'] = 'Caminho do certificado não configurado';
    }
    
    // Teste de conexão básica
    if (efi_esta_ativo()) {
        $debug_info['teste_conexao']['status'] = 'Tentando obter token...';
        
        $token = efi_obter_token();
        if ($token) {
            $debug_info['teste_conexao']['token_obtido'] = true;
            $debug_info['teste_conexao']['token_preview'] = substr($token, 0, 20) . '...';
            
            // Teste de requisição simples
            $teste_api = efi_fazer_requisicao('/v2/cob', 'GET');
            $debug_info['teste_conexao']['api_respondeu'] = $teste_api !== false;
            
            if ($teste_api === false) {
                $debug_info['teste_conexao']['erro_api'] = 'API não respondeu ou retornou erro';
            }
        } else {
            $debug_info['teste_conexao']['token_obtido'] = false;
            $debug_info['teste_conexao']['erro'] = 'Falha ao obter token de autenticação';
        }
    } else {
        $debug_info['teste_conexao']['erro'] = 'EFI Bank não está ativo';
    }
    
    // Buscar logs recentes (se a tabela existir)
    try {
        $logs = buscar_todos("
            SELECT tipo, mensagem, txid, criado_em 
            FROM efi_logs 
            ORDER BY criado_em DESC 
            LIMIT 10
        ");
        $debug_info['logs_recentes'] = $logs;
    } catch (Exception $e) {
        $debug_info['logs_recentes'] = ['erro' => 'Tabela de logs não existe ou erro ao consultar'];
    }
    
    // Verificar URLs configuradas
    $debug_info['urls'] = [
        'ambiente_sandbox' => $config_efi['efi_sandbox'] === '1',
        'url_api' => $config_efi['efi_sandbox'] === '1' 
            ? 'https://pix-h.api.efipay.com.br' 
            : 'https://pix.api.efipay.com.br'
    ];
    
    // Mascarar dados sensíveis na resposta
    if (isset($debug_info['configuracoes']['config_efi']['efi_client_secret'])) {
        $debug_info['configuracoes']['config_efi']['efi_client_secret'] = 
            substr($debug_info['configuracoes']['config_efi']['efi_client_secret'], 0, 10) . '...';
    }
    if (isset($debug_info['configuracoes']['config_efi']['efi_certificate_password'])) {
        $debug_info['configuracoes']['config_efi']['efi_certificate_password'] = '***';
    }
    
    echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'erro' => 'Erro durante debug',
        'mensagem' => $e->getMessage(),
        'arquivo' => $e->getFile(),
        'linha' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?> 