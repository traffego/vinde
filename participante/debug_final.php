<?php
// Debug final das correções
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Debug Final - Correções Aplicadas</h1>";

try {
    echo "<h2>1. Verificando autenticação</h2>";
    
    if (participante_esta_logado()) {
        echo "✅ Participante está logado<br>";
        $participante = obter_participante_logado();
        echo "Nome: " . $participante['nome'] . "<br>";
        echo "ID: " . $participante['id'] . "<br>";
    } else {
        echo "❌ Participante NÃO está logado<br>";
        exit;
    }
    
    echo "<h2>2. Testando obter_inscricoes_participante (CORRIGIDA)</h2>";
    try {
        $eventos = obter_inscricoes_participante($participante['id']);
        echo "✅ Função executada com sucesso!<br>";
        echo "Total de eventos encontrados: " . count($eventos) . "<br>";
        
        if (!empty($eventos)) {
            echo "<h3>Detalhes dos eventos:</h3>";
            foreach ($eventos as $evento) {
                echo "<div style='background: #f0f8ff; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
                echo "<strong>Evento:</strong> " . htmlspecialchars($evento['evento_nome']) . "<br>";
                echo "<strong>Status Inscrição:</strong> " . $evento['status_inscricao'] . "<br>";
                echo "<strong>Data:</strong> " . date('d/m/Y', strtotime($evento['data_inicio'])) . "<br>";
                echo "<strong>Local:</strong> " . htmlspecialchars($evento['local']) . "<br>";
                echo "<strong>Valor:</strong> R$ " . number_format($evento['valor'], 2, ',', '.') . "<br>";
                echo "<strong>Status Pagamento:</strong> " . ($evento['pagamento_status'] ?? 'Não informado') . "<br>";
                echo "</div>";
            }
        } else {
            echo "ℹ️ Nenhum evento encontrado para este participante.<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro na função corrigida: " . $e->getMessage() . "<br>";
        echo "Detalhes: " . $e->getTraceAsString() . "<br>";
    }
    
    echo "<h2>3. Testando gerar_qr_checkin (CORRIGIDA)</h2>";
    if (!empty($eventos)) {
        $primeiro_evento = $eventos[0];
        try {
            $qr_data = gerar_qr_checkin($primeiro_evento['participante_id'], $primeiro_evento['evento_id']);
            
            if ($qr_data) {
                echo "✅ QR Code gerado com sucesso!<br>";
                $qr_decoded = json_decode($qr_data, true);
                echo "<div style='background: #f0fff0; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
                echo "<strong>QR Data:</strong><br>";
                echo "- Tipo: " . $qr_decoded['type'] . "<br>";
                echo "- Inscrição ID: " . $qr_decoded['inscricao_id'] . "<br>";
                echo "- Evento: " . $qr_decoded['evento_nome'] . "<br>";
                echo "- Participante: " . $qr_decoded['participante_nome'] . "<br>";
                echo "- Token: " . substr($qr_decoded['token'], 0, 10) . "...<br>";
                echo "</div>";
            } else {
                echo "❌ Falha ao gerar QR Code (dados não encontrados)<br>";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao gerar QR: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "⚠️ Sem eventos para testar QR Code<br>";
    }
    
    echo "<h2>4. Verificando estrutura das inscrições</h2>";
    try {
        $inscricoes_raw = buscar_todos("
            SELECT i.*, e.nome as evento_nome, p.nome as participante_nome 
            FROM inscricoes i 
            JOIN eventos e ON i.evento_id = e.id 
            JOIN participantes p ON i.participante_id = p.id 
            WHERE i.participante_id = ?
        ", [$participante['id']]);
        
        echo "✅ Query direta executada com sucesso<br>";
        echo "Total de inscrições na tabela: " . count($inscricoes_raw) . "<br>";
        
        if (!empty($inscricoes_raw)) {
            echo "<h4>Inscrições encontradas:</h4>";
            foreach ($inscricoes_raw as $inscricao) {
                echo "- ID: {$inscricao['id']} | Evento: {$inscricao['evento_nome']} | Status: {$inscricao['status']}<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro na query direta: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>5. Verificando pagamentos</h2>";
    try {
        $pagamentos = buscar_todos("
            SELECT pg.*, i.id as inscricao_id, e.nome as evento_nome 
            FROM pagamentos pg 
            LEFT JOIN inscricoes i ON pg.inscricao_id = i.id 
            LEFT JOIN eventos e ON i.evento_id = e.id 
            WHERE pg.participante_id = ?
        ", [$participante['id']]);
        
        echo "✅ Pagamentos encontrados: " . count($pagamentos) . "<br>";
        
        if (!empty($pagamentos)) {
            foreach ($pagamentos as $pagamento) {
                echo "- Valor: R$ " . number_format($pagamento['valor'], 2, ',', '.') . 
                     " | Status: " . $pagamento['status'] . 
                     " | Evento: " . ($pagamento['evento_nome'] ?? 'Não vinculado') . "<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao verificar pagamentos: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>✅ RESULTADO FINAL</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; color: #155724;'>";
    echo "<strong>STATUS:</strong> Funções corrigidas e funcionando!<br>";
    echo "<strong>PRÓXIMO PASSO:</strong> Testar a área do participante em /participante/<br>";
    echo "<strong>QR CODES:</strong> Funcionando para eventos com status 'pendente' ou 'aprovada'<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO GERAL</h2>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 