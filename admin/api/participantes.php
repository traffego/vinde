<?php
require_once '../../includes/init.php';

// Verificar login
requer_login();

// Definir header JSON
header('Content-Type: application/json');

try {
    // Receber filtros
    $filtros = [
        'busca' => $_GET['busca'] ?? '',
        'evento' => $_GET['evento'] ?? '',
        'status' => $_GET['status'] ?? '',
        'cidade' => $_GET['cidade'] ?? '',
        'data_inicio' => $_GET['data_inicio'] ?? '',
        'data_fim' => $_GET['data_fim'] ?? ''
    ];

    $where_conditions = ['1=1'];
    $params = [];

    if (!empty($filtros['busca'])) {
        $where_conditions[] = '(p.nome LIKE ? OR p.cpf LIKE ? OR p.email LIKE ?)';
        $busca = '%' . $filtros['busca'] . '%';
        $params[] = $busca;
        $params[] = $busca;
        $params[] = $busca;
    }

    if (!empty($filtros['evento'])) {
        $where_conditions[] = 'i.evento_id = ?';
        $params[] = $filtros['evento'];
    }

    if (!empty($filtros['status'])) {
        $where_conditions[] = 'i.status = ?';
        $params[] = $filtros['status'];
    }

    if (!empty($filtros['cidade'])) {
        $where_conditions[] = 'p.cidade LIKE ?';
        $params[] = '%' . $filtros['cidade'] . '%';
    }

    if (!empty($filtros['data_inicio'])) {
        $where_conditions[] = 'i.data_inscricao >= ?';
        $params[] = $filtros['data_inicio'] . ' 00:00:00';
    }

    if (!empty($filtros['data_fim'])) {
        $where_conditions[] = 'i.data_inscricao <= ?';
        $params[] = $filtros['data_fim'] . ' 23:59:59';
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Paginação
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $por_pagina = 20;
    $offset = ($pagina - 1) * $por_pagina;

    $total_participantes = buscar_um("
        SELECT COUNT(*) as total 
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        WHERE {$where_clause}
    ", $params)['total'];

    $total_paginas = ceil($total_participantes / $por_pagina);

    $participantes = buscar_todos("
        SELECT 
            i.id AS inscricao_id,
            p.id,
            p.nome, p.cpf, p.whatsapp, p.email, p.instagram, p.idade, p.cidade, p.estado,
            e.id AS evento_id, e.nome AS evento_nome, e.slug AS evento_slug, e.data_inicio,
            i.status AS status_inscricao,
            pg.status AS pagamento_status, pg.valor, pg.pago_em,
            i.data_inscricao AS criado_em,
            p.checkin_timestamp,
            p.status
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        WHERE {$where_clause}
        ORDER BY i.data_inscricao DESC
        LIMIT {$por_pagina} OFFSET {$offset}
    ", $params);

    echo json_encode([
        'sucesso' => true,
        'participantes' => $participantes,
        'total' => $total_participantes,
        'pagina' => $pagina,
        'total_paginas' => $total_paginas,
        'por_pagina' => $por_pagina
    ]);

} catch (Exception $e) {
    error_log("Erro na API de participantes: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno do servidor'
    ]);
}
?>
