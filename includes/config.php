<?php
// Configurações do Sistema de Inscrições Católicas
// Arquivo: includes/config.php

// Prevenir acesso direto
if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'platafo5_vinde');
define('DB_USER', 'platafo5_vinde');
define('DB_PASS', 'Traffego444#');
define('DB_CHARSET', 'utf8mb4');

// Configurações do Site
define('SITE_URL', 'https://vinde.traffego.agency');
define('SITE_NOME', 'Vinde - Eventos Católicos');
define('SITE_EMAIL', 'contato@vinde.com.br');
define('ADMIN_EMAIL', 'admin@vinde.com.br');

// Configurações de Segurança
define('SALT_KEY', 'vinde_salt_2024_catolico_eventos');
define('SESSION_NAME', 'vinde_session');
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 1800); // 30 minutos

// Configurações de Upload
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Configurações PIX - EFI Bank
define('PIX_CHAVE', '10ba6099-17e3-4b0a-a53a-363e17bfe295'); // Sua chave PIX cadastrada na EFI
define('PIX_NOME', 'SAOFRANCISCODEASSIS');
define('PIX_CIDADE', 'QUEIMADOS');

// Credenciais EFI Bank
define('EFI_CLIENT_ID_PROD', 'Client_Id_69b6f548a62cf4ac775464356f7404594c475ed6'); // Client ID Produção
define('EFI_CLIENT_SECRET_PROD', 'Client_Secret_895e44b677cf998ff1bdf0296b4ae21094969ad6'); // Client Secret Produção
define('EFI_CLIENT_ID_HOM', 'Client_Id_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); // Client ID Homologação
define('EFI_CLIENT_SECRET_HOM', 'Client_Secret_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); // Client Secret Homologação
define('EFI_CERTIFICADO_PROD', __DIR__ . '/../certificados/certificado_prod.p12'); // Certificado Produção
define('EFI_CERTIFICADO_HOM', __DIR__ . '/../certificados/certificado_hom.p12'); // Certificado Homologação
define('EFI_SENHA_CERTIFICADO', ''); // Senha do certificado (se houver)

// URLs da API EFI
define('EFI_API_URL_PROD', 'https://pix.api.efipay.com.br');
define('EFI_API_URL_HOM', 'https://pix-h.api.efipay.com.br');

// Ambiente EFI (desenvolvimento | producao)
define('EFI_AMBIENTE', 'desenvolvimento');

// Configurações WhatsApp
define('WHATSAPP_CONTATO', '5511999999999');
define('WHATSAPP_API_TOKEN', ''); // Para integração futura

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Constantes de Status
define('STATUS_ATIVO', 'ativo');
define('STATUS_INATIVO', 'inativo');
define('STATUS_FINALIZADO', 'finalizado');
define('STATUS_ESGOTADO', 'esgotado');

define('PARTICIPANTE_INSCRITO', 'inscrito');
define('PARTICIPANTE_PAGO', 'pago');
define('PARTICIPANTE_PRESENTE', 'presente');
define('PARTICIPANTE_CANCELADO', 'cancelado');

define('PAGAMENTO_PENDENTE', 'pendente');
define('PAGAMENTO_PAGO', 'pago');
define('PAGAMENTO_CANCELADO', 'cancelado');
define('PAGAMENTO_ESTORNADO', 'estornado');

// Ambiente
define('AMBIENTE', 'desenvolvimento');

// Configurações de erro
if (defined('AMBIENTE') && AMBIENTE === 'desenvolvimento') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configurações de cache
define('CACHE_TIME', 3600); // 1 hora
define('ENABLE_CACHE', true);

// Versão do sistema
define('SISTEMA_VERSAO', '1.0.0');
define('SISTEMA_DATA', '2024-01-01');

?> 