<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

header('Content-Type: application/json');

if (!participante_esta_logado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $body = json_decode(file_get_contents('php://input'), true);
    $csrf = $body['csrf_token'] ?? '';
    $inscricao_id = (int)($body['inscricao_id'] ?? 0);

    if (!$inscricao_id || !verificar_csrf_token($csrf)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
        exit;
    }

    $participante = obter_participante_logado();

    // Garantir que a inscrição pertence ao participante e está pendente
    $inscricao = buscar_um("SELECT id, participante_id, status FROM inscricoes WHERE id = ? AND participante_id = ?", [$inscricao_id, $participante['id']]);
    if (!$inscricao) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Inscrição não encontrada']);
        exit;
    }

    if ($inscricao['status'] !== 'pendente') {
        echo json_encode(['success' => false, 'message' => 'Somente inscrições pendentes podem ser canceladas']);
        exit;
    }

    // Cancelar inscrição e pagamentos pendentes vinculados
    iniciar_transacao();

    $ok1 = atualizar_registro('inscricoes', ['status' => 'cancelada'], ['id' => $inscricao_id]);
    $ok2 = executar("UPDATE pagamentos SET status = 'cancelado' WHERE inscricao_id = ? AND status = 'pendente'", [$inscricao_id]);

    confirmar_transacao();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    desfazer_transacao();
    error_log('Erro cancelar inscricao participante: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
}
?>


