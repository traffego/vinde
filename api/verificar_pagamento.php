<?php
// API para verificar status de pagamento PIX
// Arquivo: api/verificar_pagamento.php

require_once '../includes/init.php';

// Definir cabeçalhos JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['txid']) || !isset($input['participante_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$txid = $input['txid'];
$participante_id = (int) $input['participante_id'];

try {
    // Verificar status usando PIX simples
    $status_pagamento = verificar_pagamento_pix_simples($txid);
    
    if (!$status_pagamento) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao verificar pagamento'
        ]);
        exit;
    }
    
    // Retornar status
    echo json_encode($status_pagamento);
    
} catch (Exception $e) {
    error_log("Erro ao verificar pagamento: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor'
    ]);
}
?> 