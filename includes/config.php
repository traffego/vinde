<?php
// Configurações do Sistema de Inscrições Católicas
// Arquivo: includes/config.php

// Prevenir acesso direto
if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

// Configurações do Banco de Dados
define('DB_HOST', '187.33.241.40');
define('DB_NAME', 'platafo5_vinde2');
define('DB_USER', 'platafo5_vinde2');
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
// As chaves abaixo NÃO devem conter credenciais reais. Use as configurações salvas no banco (efi_pix_key, pix_chave) via painel.
define('PIX_CHAVE', '');
define('PIX_NOME', '');
define('PIX_CIDADE', '');

// As credenciais e parâmetros da EFI agora vêm da tabela `configuracoes` via funções utilitárias.
// Não definir constantes de credenciais diretamente aqui para evitar divergência com o banco.

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

// Ambiente - CONTROLE CENTRAL DO SISTEMA
define('AMBIENTE', 'producao');

// Configurações de erro (debug é controlado pelo debug_config.php)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

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