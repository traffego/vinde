<?php
require_once '../../includes/init.php';

// Verificar login
requer_login();

// Definir header JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido']);
        exit;
    }

    // Verificar CSRF token
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Token de segurança inválido']);
        exit;
    }

    $participante_id = $_POST['id'] ?? null;

    if (!$participante_id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do participante não fornecido']);
        exit;
    }

    // Buscar dados do participante para o log
    $participante_data = buscar_um("SELECT nome, status FROM participantes WHERE id = ?", [$participante_id]);
    
    if (!$participante_data) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Participante não encontrado']);
        exit;
    }
    
    // Verificar se há pagamentos confirmados (status = 'pago') associados
    $pagamentos_pagos = buscar_um("SELECT COUNT(*) as total FROM pagamentos WHERE participante_id = ? AND status = 'pago'", [$participante_id]);
    $total_pagamentos_pagos = $pagamentos_pagos ? $pagamentos_pagos['total'] : 0;
    
    if ($total_pagamentos_pagos > 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Não é possível excluir este participante. Há pagamentos confirmados associados']);
        exit;
    }
    
    // Verificar se há inscrições aprovadas
    $inscricoes_aprovadas = buscar_um("SELECT COUNT(*) as total FROM inscricoes WHERE participante_id = ? AND status = 'aprovada'", [$participante_id]);
    $total_inscricoes_aprovadas = $inscricoes_aprovadas ? $inscricoes_aprovadas['total'] : 0;
    
    if ($total_inscricoes_aprovadas > 0 && $participante_data['status'] === 'presente') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Não é possível excluir este participante. Ele já fez check-in no evento']);
        exit;
    }
    
    // Excluir participante (o CASCADE vai remover inscrições e pagamentos relacionados)
    $resultado = remover_registro('participantes', ['id' => $participante_id]);
    
    if ($resultado) {
        registrar_log('participante_excluido', "Participante: {$participante_data['nome']} (ID: {$participante_id})");
        echo json_encode(['sucesso' => true, 'mensagem' => 'Participante excluído com sucesso!']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir participante no banco de dados']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao excluir participante via API: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro interno do servidor',
        'debug' => [
            'erro' => $e->getMessage(),
            'arquivo' => basename($e->getFile()),
            'linha' => $e->getLine()
        ]
    ]);
}
?>
