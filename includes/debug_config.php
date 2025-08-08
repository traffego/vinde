<?php
/**
 * Configurações de Debug do Sistema
 * 
 * Este arquivo controla todo o sistema de debug do site.
 * Altere apenas as configurações abaixo quando necessário debugar problemas.
 * 
 * IMPORTANTE: Em produção, mantenha DEBUG_ENABLED = false
 */

// ========================
// CONFIGURAÇÃO PRINCIPAL
// ========================

// Habilitar/Desabilitar modo debug globalmente
define('DEBUG_ENABLED', false);

// ========================
// CONFIGURAÇÕES ESPECÍFICAS
// ========================

// Debug de PIX e pagamentos
define('DEBUG_PIX', false);

// Debug de EFI Bank
define('DEBUG_EFI', false);

// Debug de banco de dados
define('DEBUG_DATABASE', false);

// Debug de autenticação
define('DEBUG_AUTH', false);

// ========================
// CONFIGURAÇÕES AVANÇADAS
// ========================

// Mostrar erros PHP na tela (apenas quando DEBUG_ENABLED = true)
define('SHOW_PHP_ERRORS', false);

// Log detalhado de queries SQL
define('LOG_SQL_QUERIES', false);

// Log de chamadas da API EFI
define('LOG_EFI_CALLS', false);

// ========================
// FUNÇÕES AUXILIARES
// ========================

/**
 * Verifica se debug está habilitado globalmente
 */
function is_debug_enabled() {
    return defined('DEBUG_ENABLED') && DEBUG_ENABLED === true;
}

/**
 * Verifica se debug de PIX está habilitado
 */
function is_pix_debug_enabled() {
    return is_debug_enabled() && defined('DEBUG_PIX') && DEBUG_PIX === true;
}

/**
 * Verifica se debug de EFI está habilitado
 */
function is_efi_debug_enabled() {
    return is_debug_enabled() && defined('DEBUG_EFI') && DEBUG_EFI === true;
}

/**
 * Log de debug condicional
 */
function debug_log($message, $category = 'general') {
    if (!is_debug_enabled()) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$category}] {$message}";
    error_log($log_message);
}

/**
 * Debug dump condicional
 */
function debug_dump($var, $label = '') {
    if (!is_debug_enabled()) {
        return;
    }
    
    echo "<pre style='background:#f8f9fa;border:1px solid #dee2e6;padding:15px;margin:10px 0;border-radius:5px;'>";
    if ($label) {
        echo "<strong>{$label}:</strong>\n";
    }
    var_dump($var);
    echo "</pre>";
}

// Aplicar configurações de debug se habilitado
if (is_debug_enabled() && defined('SHOW_PHP_ERRORS') && SHOW_PHP_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

?> 