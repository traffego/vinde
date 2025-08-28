<?php
require_once '../../includes/init.php';

// Verificar login
requer_login();

// Definir header JSON
header('Content-Type: application/json');

try {
    // Verificar se sistema foi migrado
    $tabela_inscricoes_existe = false;
    try {
        $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
        $tabela_inscricoes_existe = $teste_tabela !== false;
    } catch (Exception $e) {
        $tabela_inscricoes_existe = false;
    }

    // Receber filtros
    $filtros = [
        'busca' => $_GET['busca'] ?? '',
        'evento' => $_GET['evento'] ?? '',
        'status' => $_GET['status'] ?? '',
        'pagamento' => $_GET['pagamento'] ?? '',
        'cidade' => $_GET['cidade'] ?? '',
        'data_inicio' => $_GET['data_inicio'] ?? '',
        'data_fim' => $_GET['data_fim'] ?? ''
    ];

    $where_conditions = ['1=1'];
    $params = [];

    if ($tabela_inscricoes_existe) {
        // Sistema novo - usar tabela inscricoes
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
            $where_conditions[] = 'p.status = ?';
            $params[] = $filtros['status'];
        }
        
        if (!empty($filtros['pagamento'])) {
            $where_conditions[] = 'pg.status = ?';
            $params[] = $filtros['pagamento'];
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

        // Paginação/Scroll infinito
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $por_pagina = 20;

        $total_participantes = buscar_um("
            SELECT COUNT(*) as total 
            FROM inscricoes i
            JOIN participantes p ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
            WHERE {$where_clause}
        ", $params)['total'];

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
        
    } else {
        // Sistema antigo - usar apenas tabela participantes
        if (!empty($filtros['busca'])) {
            $where_conditions[] = '(p.nome LIKE ? OR p.cpf LIKE ? OR p.email LIKE ?)';
            $busca = '%' . $filtros['busca'] . '%';
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
        }

        if (!empty($filtros['evento'])) {
            $where_conditions[] = 'p.evento_id = ?';
            $params[] = $filtros['evento'];
        }

        if (!empty($filtros['status'])) {
            $where_conditions[] = 'p.status = ?';
            $params[] = $filtros['status'];
        }
        
        if (!empty($filtros['pagamento'])) {
            // No sistema antigo, o status de pagamento está no campo status do participante
            if ($filtros['pagamento'] === 'pago') {
                $where_conditions[] = 'p.status = "pago"';
            } elseif ($filtros['pagamento'] === 'pendente') {
                $where_conditions[] = 'p.status = "inscrito"';
            }
        }

        if (!empty($filtros['cidade'])) {
            $where_conditions[] = 'p.cidade LIKE ?';
            $params[] = '%' . $filtros['cidade'] . '%';
        }

        if (!empty($filtros['data_inicio'])) {
            $where_conditions[] = 'p.criado_em >= ?';
            $params[] = $filtros['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filtros['data_fim'])) {
            $where_conditions[] = 'p.criado_em <= ?';
            $params[] = $filtros['data_fim'] . ' 23:59:59';
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Paginação/Scroll infinito
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $por_pagina = 20;

        $total_participantes = buscar_um("
            SELECT COUNT(*) as total 
            FROM participantes p
            LEFT JOIN eventos e ON p.evento_id = e.id
            WHERE {$where_clause} AND p.status != 'cancelado'
        ", $params)['total'];

        $participantes = buscar_todos("
            SELECT 
                NULL AS inscricao_id,
                p.id,
                p.nome, p.cpf, p.whatsapp, p.email, p.instagram, p.idade, p.cidade, p.estado,
                e.id AS evento_id, e.nome AS evento_nome, e.slug AS evento_slug, e.data_inicio,
                p.status AS status_inscricao,
                CASE 
                    WHEN p.status = 'pago' THEN 'pago'
                    WHEN p.status = 'inscrito' THEN 'pendente'
                    ELSE 'pendente'
                END AS pagamento_status, 
                0 AS valor, 
                NULL AS pago_em,
                p.criado_em,
                p.checkin_timestamp,
                p.status
            FROM participantes p
            LEFT JOIN eventos e ON p.evento_id = e.id
            WHERE {$where_clause} AND p.status != 'cancelado'
            ORDER BY p.criado_em DESC
            LIMIT {$por_pagina} OFFSET {$offset}
        ", $params);
    }

    echo json_encode([
        'sucesso' => true,
        'participantes' => $participantes,
        'total' => $total_participantes,
        'offset' => $offset,
        'por_pagina' => $por_pagina,
        'tem_mais' => ($offset + $por_pagina) < $total_participantes,
        'proximo_offset' => ($offset + $por_pagina) < $total_participantes ? $offset + $por_pagina : null
    ]);

} catch (Exception $e) {
    error_log("Erro na API de participantes: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno do servidor'
    ]);
}
?>
