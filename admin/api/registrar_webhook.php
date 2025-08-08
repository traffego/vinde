<?php
require_once '../../includes/init.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    // Apenas admins
    requer_login('admin');

    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido']);
        exit;
    }

    // CSRF opcional (se vier no header ou body)
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrfBody = $input['csrf_token'] ?? '';
    $csrf = $csrfHeader ?: $csrfBody;
    if ($csrf && !verificar_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido']);
        exit;
    }

    // Registrar webhook via API EFI
    if (!function_exists('efi_registrar_webhook_configurado')) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Função de registro de webhook indisponível']);
        exit;
    }

    $resultado = efi_registrar_webhook_configurado();

    if (!empty($resultado['sucesso'])) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Webhook registrado com sucesso na Efí']);
    } else {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => $resultado['mensagem'] ?? 'Falha ao registrar webhook']);
    }
} catch (Exception $e) {
    error_log('Erro ao registrar webhook EFI: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno']);
}


