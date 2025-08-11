<?php
require_once '../../includes/init.php';

// Verificar login
requer_login();

// Definir header JSON
header('Content-Type: application/json');

try {
    $participante_id = $_GET['id'] ?? null;
    
    if (!$participante_id) {
        echo json_encode(['sucesso' => false, 'erro' => 'ID do participante não fornecido']);
        exit;
    }

    // Buscar participante com todos os dados
    $participante = buscar_um("
        SELECT 
            p.*,
            li.id as inscricao_id_display,
            e.nome as evento_nome, 
            e.slug as evento_slug, 
            e.data_inicio,
            pag.status as pagamento_status, 
            pag.valor, 
            pag.pago_em
        FROM participantes p
        LEFT JOIN (
            SELECT i.* 
            FROM inscricoes i 
            WHERE i.participante_id = ? 
            ORDER BY i.data_inscricao DESC 
            LIMIT 1
        ) li ON li.participante_id = p.id
        LEFT JOIN eventos e ON e.id = li.evento_id
        LEFT JOIN pagamentos pag ON pag.inscricao_id = li.id
        WHERE p.id = ?
    ", [$participante_id, $participante_id]);

    if (!$participante) {
        echo json_encode(['sucesso' => false, 'erro' => 'Participante não encontrado']);
        exit;
    }

    echo json_encode([
        'sucesso' => true,
        'participante' => $participante
    ]);

} catch (Exception $e) {
    error_log("Erro na API de participante: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno do servidor'
    ]);
}
?>
