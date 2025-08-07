<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Verificar login
if (!participante_esta_logado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Configurar resposta JSON
header('Content-Type: application/json');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Obter dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['participante_id']) || !isset($input['evento_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }
    
    $participante_id = (int)$input['participante_id'];
    $evento_id = (int)$input['evento_id'];
    
    // Verificar se o participante logado pode acessar este QR
    $participante_logado = obter_participante_logado();
    if ($participante_logado['id'] != $participante_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }
    
    // Gerar QR Code
    $qr_data = gerar_qr_checkin($participante_id, $evento_id);
    
    if (!$qr_data) {
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar QR Code']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'qr_data' => $qr_data,
        'message' => 'QR Code gerado com sucesso'
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API QR participante: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?> 