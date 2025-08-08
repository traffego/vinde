<?php
// Debug específico para verificar a inscrição ID 16
require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Específico - Inscrição ID 16</h1>";

try {
    echo "<h2>1. Verificando se a inscrição ID 16 existe</h2>";
    
    $inscricao = buscar_um("SELECT * FROM inscricoes WHERE id = 16");
    
    if ($inscricao) {
        echo "✅ Inscrição ID 16 encontrada!<br>";
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<strong>Detalhes da inscrição:</strong><br>";
        echo "- ID: " . $inscricao['id'] . "<br>";
        echo "- Participante ID: " . $inscricao['participante_id'] . "<br>";
        echo "- Evento ID: " . $inscricao['evento_id'] . "<br>";
        echo "- Status: " . $inscricao['status'] . "<br>";
        echo "- Data inscrição: " . $inscricao['data_inscricao'] . "<br>";
        echo "- Valor pago: R$ " . number_format($inscricao['valor_pago'] ?? 0, 2, ',', '.') . "<br>";
        echo "</div>";
        
        echo "<h3>1.1 Verificando o participante desta inscrição</h3>";
        $participante_inscricao = buscar_um("
            SELECT * FROM participantes WHERE id = ?
        ", [$inscricao['participante_id']]);
        
        if ($participante_inscricao) {
            echo "✅ Participante encontrado:<br>";
            echo "- Nome: " . $participante_inscricao['nome'] . "<br>";
            echo "- CPF: " . $participante_inscricao['cpf'] . "<br>";
            echo "- Email: " . ($participante_inscricao['email'] ?? 'N/A') . "<br>";
        } else {
            echo "❌ Participante não encontrado!<br>";
        }
        
        echo "<h3>1.2 Verificando o evento desta inscrição</h3>";
        $evento_inscricao = buscar_um("
            SELECT * FROM eventos WHERE id = ?
        ", [$inscricao['evento_id']]);
        
        if ($evento_inscricao) {
            echo "✅ Evento encontrado:<br>";
            echo "- Nome: " . $evento_inscricao['nome'] . "<br>";
            echo "- Data: " . date('d/m/Y', strtotime($evento_inscricao['data_inicio'])) . "<br>";
            echo "- Status: " . $evento_inscricao['status'] . "<br>";
        } else {
            echo "❌ Evento não encontrado!<br>";
        }
        
    } else {
        echo "❌ Inscrição ID 16 NÃO existe na tabela inscricoes<br>";
        
        echo "<h3>1.1 Verificando se existe no sistema antigo (participantes)</h3>";
        $participante_16 = buscar_um("SELECT * FROM participantes WHERE id = 16");
        
        if ($participante_16) {
            echo "✅ Encontrado participante ID 16 no sistema antigo:<br>";
            echo "- Nome: " . $participante_16['nome'] . "<br>";
            echo "- CPF: " . $participante_16['cpf'] . "<br>";
            echo "- Evento ID: " . ($participante_16['evento_id'] ?? 'N/A') . "<br>";
            echo "- Status: " . ($participante_16['status'] ?? 'N/A') . "<br>";
        } else {
            echo "❌ Também não existe participante ID 16<br>";
        }
    }
    
    echo "<h2>2. Verificando participante logado</h2>";
    
    if (participante_esta_logado()) {
        echo "✅ Participante está logado<br>";
        echo "- ID: " . $_SESSION['participante_id'] . "<br>";
        echo "- Nome: " . ($_SESSION['participante_nome'] ?? 'N/A') . "<br>";
        echo "- CPF: " . $_SESSION['participante_cpf'] . "<br>";
        
        echo "<h3>2.1 Inscrições do participante logado</h3>";
        $inscricoes_participante = buscar_todos("
            SELECT * FROM inscricoes WHERE participante_id = ?
        ", [$_SESSION['participante_id']]);
        
        if (!empty($inscricoes_participante)) {
            echo "✅ Inscrições encontradas (" . count($inscricoes_participante) . "):<br>";
            foreach ($inscricoes_participante as $insc) {
                echo "<div style='background: #f0f8ff; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
                echo "- Inscrição ID: <strong>" . $insc['id'] . "</strong><br>";
                echo "- Evento ID: " . $insc['evento_id'] . "<br>";
                echo "- Status: " . $insc['status'] . "<br>";
                echo "- Data: " . $insc['data_inscricao'] . "<br>";
                echo "</div>";
            }
        } else {
            echo "❌ Nenhuma inscrição encontrada para este participante<br>";
        }
        
    } else {
        echo "❌ Participante NÃO está logado<br>";
    }
    
    echo "<h2>3. Verificando todas as inscrições existentes</h2>";
    $todas_inscricoes = buscar_todos("SELECT id, participante_id, evento_id, status FROM inscricoes ORDER BY id DESC LIMIT 10");
    
    if (!empty($todas_inscricoes)) {
        echo "✅ Últimas 10 inscrições no sistema:<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Participante ID</th><th>Evento ID</th><th>Status</th></tr>";
        foreach ($todas_inscricoes as $insc) {
            $destaque = ($insc['id'] == 16) ? "style='background: #ffeb3b;'" : "";
            echo "<tr {$destaque}>";
            echo "<td>" . $insc['id'] . "</td>";
            echo "<td>" . $insc['participante_id'] . "</td>";
            echo "<td>" . $insc['evento_id'] . "</td>";
            echo "<td>" . $insc['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Nenhuma inscrição encontrada no sistema<br>";
    }
    
    echo "<h2>4. Recomendações</h2>";
    echo "<div style='background: #fff3e0; padding: 15px; border-radius: 8px; color: #f57c00;'>";
    echo "<strong>🔧 Possíveis soluções:</strong><br><br>";
    echo "1. <strong>Se a inscrição não existe:</strong> O link pode estar incorreto ou a inscrição foi deletada<br>";
    echo "2. <strong>Se a inscrição existe mas pertence a outro participante:</strong> Verificar se o participante está logado com a conta correta<br>";
    echo "3. <strong>Se houver inconsistência nos dados:</strong> Verificar integridade dos dados nas tabelas<br>";
    echo "4. <strong>Link correto seria:</strong> confirmacao.php?inscricao=[ID_DA_INSCRICAO_CORRETA]<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 8px; color: #c62828; border: 3px solid #f44336;'>";
    echo "<h2>🚨 ERRO NO DEBUG</h2>";
    echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Arquivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Linha:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}
?>
