<?php
require_once '../../includes/init.php';

// Verificar se é admin
if (!esta_logado()) {
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

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$action = $input['action'];

try {
    switch ($action) {
        case 'checkin_qr':
            $resultado = processarCheckinQR($input);
            break;
            
        case 'checkin_manual':
            $resultado = processarCheckinManual($input);
            break;
            
        case 'undo_checkin':
            $resultado = desfazerCheckin($input);
            break;
            
        case 'search_participant':
            $resultado = buscarParticipante($input);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    error_log("Erro na API de check-in: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}

/**
 * Processar check-in via QR Code
 */
function processarCheckinQR($input) {
    // Verificar se é QR do novo formato (JSON)
    if (isset($input['qr_data'])) {
        $qr_data = json_decode($input['qr_data'], true);
        if (!$qr_data || !isset($qr_data['participante_id']) || !isset($qr_data['token'])) {
            return ['success' => false, 'message' => 'QR Code inválido'];
        }
        $participante_id = (int)$qr_data['participante_id'];
        $token = $qr_data['token'];
    }
    // Formato antigo (compatibilidade)
    else if (isset($input['participante_id']) && isset($input['token'])) {
        $participante_id = (int)$input['participante_id'];
        $token = $input['token'];
    } else {
        return ['success' => false, 'message' => 'Dados incompletos'];
    }
    
    // Buscar participante, inscrição ativa e validar token
    $participante = buscar_um("
        SELECT 
            p.*, 
            e.nome as evento_nome, 
            e.data_inicio, 
            pg.status as pagamento_status, 
            pg.valor
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        WHERE p.id = ? AND p.qr_token = ? AND i.status != 'cancelada'
        ORDER BY i.data_inscricao DESC
        LIMIT 1
    ", [$participante_id, $token]);
    
    if (!$participante) {
        return ['success' => false, 'message' => 'QR Code inválido ou participante não encontrado'];
    }
    
    // Verificar se já fez check-in
    if ($participante['status'] === 'presente') {
        return [
            'success' => false, 
            'message' => 'Check-in já realizado anteriormente',
            'participante_nome' => $participante['nome'],
            'checkin_timestamp' => $participante['checkin_timestamp']
        ];
    }
    
    // Verificar pagamento (se evento pago)
    if ($participante['valor'] > 0 && $participante['pagamento_status'] !== 'pago') {
        return ['success' => false, 'message' => 'Pagamento não confirmado'];
    }
    
    // Realizar check-in
    return realizarCheckin($participante_id, $participante['nome']);
}

/**
 * Processar check-in manual
 */
function processarCheckinManual($input) {
    if (!isset($input['participante_id'])) {
        return ['success' => false, 'message' => 'ID do participante não informado'];
    }
    
    $participante_id = (int)$input['participante_id'];
    
    // Buscar participante com última inscrição ativa
    $participante = buscar_um("
        SELECT 
            p.*, 
            e.nome as evento_nome, 
            pg.status as pagamento_status, 
            pg.valor
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        WHERE p.id = ? AND i.status != 'cancelada'
        ORDER BY i.data_inscricao DESC
        LIMIT 1
    ", [$participante_id]);
    
    if (!$participante) {
        return ['success' => false, 'message' => 'Participante não encontrado'];
    }
    
    // Verificar se já fez check-in
    if ($participante['status'] === 'presente') {
        return [
            'success' => false, 
            'message' => 'Check-in já realizado anteriormente',
            'participante_nome' => $participante['nome']
        ];
    }
    
    // Verificar pagamento (se evento pago)
    if ($participante['valor'] > 0 && $participante['pagamento_status'] !== 'pago') {
        return ['success' => false, 'message' => 'Pagamento não confirmado'];
    }
    
    // Realizar check-in
    return realizarCheckin($participante_id, $participante['nome']);
}

/**
 * Desfazer check-in
 */
function desfazerCheckin($input) {
    if (!isset($input['participante_id'])) {
        return ['success' => false, 'message' => 'ID do participante não informado'];
    }
    
    $participante_id = (int)$input['participante_id'];
    
    // Buscar participante
    $participante = buscar_um("
        SELECT nome, status FROM participantes WHERE id = ?
    ", [$participante_id]);
    
    if (!$participante) {
        return ['success' => false, 'message' => 'Participante não encontrado'];
    }
    
    if ($participante['status'] !== 'presente') {
        return ['success' => false, 'message' => 'Participante não fez check-in'];
    }
    
    // Desfazer check-in
    $sucesso = executar("
        UPDATE participantes 
        SET status = 'pago', 
            checkin_timestamp = NULL, 
            checkin_operador = NULL
        WHERE id = ?
    ", [$participante_id]);
    
    if ($sucesso) {
        // Log da ação
        $operador = obter_usuario_logado()['nome'] ?? 'Admin';
        executar("
            INSERT INTO logs_sistema (tipo, descricao, usuario, data_hora)
            VALUES ('checkin_desfeito', ?, ?, NOW())
        ", [
            "Check-in desfeito para participante: {$participante['nome']} (ID: {$participante_id})",
            $operador
        ]);
        
        return [
            'success' => true,
            'message' => 'Check-in desfeito com sucesso',
            'participante_nome' => $participante['nome']
        ];
    } else {
        return ['success' => false, 'message' => 'Erro ao desfazer check-in'];
    }
}

/**
 * Realizar check-in efetivamente
 */
function realizarCheckin($participante_id, $participante_nome) {
    $operador = obter_usuario_logado()['nome'] ?? 'Admin';
    $timestamp = date('Y-m-d H:i:s');
    
    // Atualizar status do participante
    $sucesso = executar("
        UPDATE participantes 
        SET status = 'presente', 
            checkin_timestamp = ?, 
            checkin_operador = ?
        WHERE id = ?
    ", [$timestamp, $operador, $participante_id]);
    
    if ($sucesso) {
        // Log da ação
        executar("
            INSERT INTO logs_sistema (tipo, descricao, usuario, data_hora)
            VALUES ('checkin_realizado', ?, ?, ?)
        ", [
            "Check-in realizado para participante: {$participante_nome} (ID: {$participante_id})",
            $operador,
            $timestamp
        ]);
        
        return [
            'success' => true,
            'message' => 'Check-in realizado com sucesso',
            'participante_nome' => $participante_nome,
            'checkin_timestamp' => formatar_data_hora($timestamp),
            'operador' => $operador
        ];
    } else {
        return ['success' => false, 'message' => 'Erro ao realizar check-in'];
    }
}

/**
 * Buscar participante por nome, CPF ou email
 */
function buscarParticipante($input) {
    if (!isset($input['query']) || !isset($input['evento_id'])) {
        return ['success' => false, 'message' => 'Parâmetros incompletos'];
    }
    
    $query = trim($input['query']);
    $evento_id = (int)$input['evento_id'];
    
    if (strlen($query) < 3) {
        return ['success' => false, 'message' => 'Digite pelo menos 3 caracteres'];
    }
    
    // Limpar CPF se for o caso
    $cpf_limpo = preg_replace('/[^0-9]/', '', $query);
    
    // Buscar participantes
    $participantes = buscar_todos("
        SELECT 
            p.id,
            p.nome,
            p.email,
            p.cpf,
            p.whatsapp,
            p.status,
            p.checkin_timestamp,
            e.nome as evento_nome
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        WHERE i.evento_id = ? 
        AND i.status != 'cancelada'
        AND (
            LOWER(p.nome) LIKE LOWER(?) 
            OR LOWER(p.email) LIKE LOWER(?)
            OR p.cpf = ?
            OR REPLACE(REPLACE(REPLACE(p.cpf, '.', ''), '-', ''), ' ', '') = ?
        )
        ORDER BY p.nome
        LIMIT 10
    ", [
        $evento_id,
        "%{$query}%",
        "%{$query}%", 
        $cpf_limpo,
        $cpf_limpo
    ]);
    
    if (empty($participantes)) {
        return [
            'success' => true,
            'participants' => [],
            'message' => 'Nenhum participante encontrado'
        ];
    }
    
    return [
        'success' => true,
        'participants' => $participantes,
        'total' => count($participantes)
    ];
}

/**
 * Obter usuário logado
 */
function obter_usuario_logado() {
    if (esta_logado()) {
        return [
            'id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_user'],
            'nome' => $_SESSION['admin_nome'] ?? $_SESSION['admin_user'],
            'email' => $_SESSION['admin_email'] ?? '',
            'nivel' => $_SESSION['admin_nivel'] ?? 'operador'
        ];
    }
    return null;
}
?> 