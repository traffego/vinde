<?php
// Debug da área do participante
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug - Área do Participante</h1>";

try {
    echo "<h2>1. Testando autenticação</h2>";
    
    if (participante_esta_logado()) {
        echo "✅ Participante está logado<br>";
        
        $participante = obter_participante_logado();
        echo "Nome: " . $participante['nome'] . "<br>";
        echo "CPF: " . $participante['cpf'] . "<br>";
        echo "ID: " . $participante['id'] . "<br>";
        
    } else {
        echo "❌ Participante NÃO está logado<br>";
        exit;
    }
    
    echo "<h2>2. Verificando tabela inscricoes</h2>";
    $tabela_inscricoes_existe = false;
    try {
        $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
        $tabela_inscricoes_existe = $teste_tabela !== false;
        
        if ($tabela_inscricoes_existe) {
            echo "✅ Tabela inscricoes existe<br>";
        } else {
            echo "❌ Tabela inscricoes NÃO existe<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao verificar tabela: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>3. Verificando função obter_inscricoes_participante</h2>";
    if (function_exists('obter_inscricoes_participante')) {
        echo "✅ Função obter_inscricoes_participante existe<br>";
    } else {
        echo "❌ Função obter_inscricoes_participante NÃO existe<br>";
    }
    
    echo "<h2>4. Buscando eventos (sistema antigo)</h2>";
    try {
        $eventos_antigo = buscar_todos("
            SELECT 
                p.*,
                e.nome as evento_nome,
                e.slug,
                e.data_inicio,
                e.data_fim,
                e.horario_inicio,
                e.horario_fim,
                e.local,
                e.cidade,
                e.valor,
                e.imagem,
                pg.status as pagamento_status,
                p.id as participante_id,
                p.evento_id,
                p.status,
                p.checkin_timestamp,
                e.nome as nome,
                e.data_inicio,
                e.local
            FROM participantes p 
            INNER JOIN eventos e ON p.evento_id = e.id 
            LEFT JOIN pagamentos pg ON p.id = pg.participante_id 
            WHERE p.cpf = ? 
            ORDER BY e.data_inicio DESC
        ", [$participante['cpf']]);
        
        echo "✅ Query sistema antigo executada com sucesso<br>";
        echo "Total de eventos encontrados: " . count($eventos_antigo) . "<br>";
        
        if (!empty($eventos_antigo)) {
            echo "<h3>Eventos encontrados:</h3>";
            foreach ($eventos_antigo as $evento) {
                echo "- " . $evento['evento_nome'] . " (Status: " . $evento['status'] . ")<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro na query sistema antigo: " . $e->getMessage() . "<br>";
    }
    
    if ($tabela_inscricoes_existe && function_exists('obter_inscricoes_participante')) {
        echo "<h2>5. Buscando eventos (sistema novo)</h2>";
        try {
            $eventos_novo = obter_inscricoes_participante($participante['id']);
            echo "✅ Função sistema novo executada com sucesso<br>";
            echo "Total de eventos encontrados: " . count($eventos_novo) . "<br>";
            
            if (!empty($eventos_novo)) {
                echo "<h3>Eventos encontrados (sistema novo):</h3>";
                foreach ($eventos_novo as $evento) {
                    echo "- " . $evento['evento_nome'] . " (Status: " . $evento['status_inscricao'] . ")<br>";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Erro na função sistema novo: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>6. Testando função gerar_qr_checkin</h2>";
    if (function_exists('gerar_qr_checkin')) {
        echo "✅ Função gerar_qr_checkin existe<br>";
        
        // Testar com dados do primeiro evento
        if (!empty($eventos_antigo)) {
            $primeiro_evento = $eventos_antigo[0];
            try {
                $qr_data = gerar_qr_checkin($primeiro_evento['participante_id'], $primeiro_evento['evento_id']);
                if ($qr_data) {
                    echo "✅ QR Code gerado com sucesso<br>";
                    echo "QR Data: " . substr($qr_data, 0, 50) . "...<br>";
                } else {
                    echo "❌ Falha ao gerar QR Code<br>";
                }
            } catch (Exception $e) {
                echo "❌ Erro ao gerar QR: " . $e->getMessage() . "<br>";
            }
        }
    } else {
        echo "❌ Função gerar_qr_checkin NÃO existe<br>";
    }
    
    echo "<h2>7. Estrutura do participante logado</h2>";
    echo "<pre>";
    print_r($participante);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO CAPTURADO</h2>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?> 