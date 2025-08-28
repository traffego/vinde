<?php
require_once '../includes/init.php';

// Verificar login
requer_login();

echo "<h1>Teste da API de Participantes</h1>";

// Testar se a tabela inscricoes existe
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
    echo "<p><strong>Tabela inscricoes existe:</strong> " . ($tabela_inscricoes_existe ? 'SIM' : 'NÃO') . "</p>";
} catch (Exception $e) {
    echo "<p><strong>Erro ao verificar tabela inscricoes:</strong> " . $e->getMessage() . "</p>";
}

// Testar contagem de participantes
if ($tabela_inscricoes_existe) {
    echo "<h2>Sistema Novo (com tabela inscricoes)</h2>";
    
    try {
        $total_participantes = buscar_um("
            SELECT COUNT(*) as total 
            FROM inscricoes i
            JOIN participantes p ON i.participante_id = p.id
            JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN pagamentos pg ON (pg.inscricao_id = i.id OR pg.participante_id = p.id)
            WHERE 1=1 AND p.status IN ('inscrito', 'pago', 'presente')
        ")['total'];
        
        echo "<p><strong>Total de participantes (sistema novo):</strong> " . $total_participantes . "</p>";
        
        // Buscar alguns participantes
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
            LEFT JOIN pagamentos pg ON (pg.inscricao_id = i.id OR pg.participante_id = p.id)
            WHERE 1=1 AND p.status IN ('inscrito', 'pago', 'presente')
            ORDER BY i.data_inscricao DESC
            LIMIT 5
        ");
        
        echo "<p><strong>Primeiros 5 participantes encontrados:</strong></p>";
        echo "<pre>" . print_r($participantes, true) . "</pre>";
        
    } catch (Exception $e) {
        echo "<p><strong>Erro no sistema novo:</strong> " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<h2>Sistema Antigo (apenas tabela participantes)</h2>";
    
    try {
        $total_participantes = buscar_um("
            SELECT COUNT(*) as total 
            FROM participantes p
            LEFT JOIN eventos e ON p.evento_id = e.id
            WHERE 1=1 AND p.status IN ('inscrito', 'pago', 'presente')
        ")['total'];
        
        echo "<p><strong>Total de participantes (sistema antigo):</strong> " . $total_participantes . "</p>";
        
        // Buscar alguns participantes
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
                    WHEN p.status = 'presente' THEN 'pago'
                    ELSE 'pendente'
                END AS pagamento_status, 
                0 AS valor, 
                NULL AS pago_em,
                p.criado_em,
                p.checkin_timestamp,
                p.status
            FROM participantes p
            LEFT JOIN eventos e ON p.evento_id = e.id
            WHERE 1=1 AND p.status IN ('inscrito', 'pago', 'presente')
            ORDER BY p.criado_em DESC
            LIMIT 5
        ");
        
        echo "<p><strong>Primeiros 5 participantes encontrados:</strong></p>";
        echo "<pre>" . print_r($participantes, true) . "</pre>";
        
    } catch (Exception $e) {
        echo "<p><strong>Erro no sistema antigo:</strong> " . $e->getMessage() . "</p>";
    }
}

// Testar a API diretamente
echo "<h2>Teste da API</h2>";

try {
    // Simular uma requisição GET
    $_GET = [];
    
    // Capturar a saída da API
    ob_start();
    include 'api/participantes.php';
    $api_output = ob_get_clean();
    
    echo "<p><strong>Saída da API:</strong></p>";
    echo "<pre>" . htmlspecialchars($api_output) . "</pre>";
    
    // Tentar decodificar o JSON
    $api_data = json_decode($api_output, true);
    if ($api_data) {
        echo "<p><strong>JSON decodificado com sucesso:</strong></p>";
        echo "<pre>" . print_r($api_data, true) . "</pre>";
    } else {
        echo "<p><strong>Erro ao decodificar JSON:</strong> " . json_last_error_msg() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Erro ao testar API:</strong> " . $e->getMessage() . "</p>";
}

echo "<p><a href='participantes.php'>Voltar para participantes</a></p>";
?>