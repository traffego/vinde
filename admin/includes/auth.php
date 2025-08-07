<?php
// Arquivo de autenticação do painel administrativo
// admin/includes/auth.php

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Verificar se o usuário está logado como admin
 */
function verificar_login_admin() {
    // Compatibilidade com sistema existente
    if (!esta_logado()) {
        // Redirecionar para login
        $login_url = SITE_URL . '/admin/login.php';
        if ($_SERVER['REQUEST_URI'] !== parse_url($login_url, PHP_URL_PATH)) {
            header('Location: ' . $login_url);
            exit;
        }
    }
    
    return true;
}

/**
 * Obter dados do usuário admin logado
 */
function obter_usuario_admin() {
    if (esta_logado()) {
        return [
            'id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_user'],
            'nome' => $_SESSION['admin_nome'],
            'email' => $_SESSION['admin_email'],
            'nivel' => $_SESSION['admin_nivel']
        ];
    }
    
    return null;
}
?> 