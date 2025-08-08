<?php
// Debug específico para confirmacao.php
require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Confirmação - Erro 500</h1>";

try {
    echo "<h2>1. Verificando parâmetros</h2>";
    $inscricao_id = $_GET['inscricao'] ?? '';
    echo "inscricao_id recebido: " . $inscricao_id . "<br>";
    
    echo "<h2>2. Verificando autenticação</h2>";
    if (!participante_esta_logado()) {
        echo "❌ Participante NÃO está logado<br>";
        echo "Sessão atual: ";
        print_r($_SESSION);
    } else {
        echo "✅ Participante está logado<br>";
        echo "ID do participante: " . participante_obter_id() . "<br>";
        echo "Nome: " . participante_obter_nome() . "<br>";
    }
    
    echo "<h2>3. Verificando se tabela inscricoes existe</h2>";
    
    $tabela_inscricoes_existe = buscar_um("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'inscricoes'
    ");
    
    echo "Tabela inscricoes existe: " . ($tabela_inscricoes_existe['count'] > 0 ? 'SIM' : 'NÃO') . "<br>";
    
    if ($inscricao_id) {
        echo "<h2>4. Testando consulta de dados</h2>";
        
        if ($tabela_inscricoes_existe['count'] > 0) {
            echo "<h3>4.1 Sistema novo - testando query com inscricoes</h3>";
            
            try {
                $dados = buscar_um("
                    SELECT 
                        i.*,
                        p.nome as participante_nome,
                        p.cpf as participante_cpf,
                        p.email as participante_email,
                        p.whatsapp as participante_whatsapp,
                        p.qr_token,
                        e.nome as evento_nome,
                        e.descricao as evento_descricao,
                        e.data_inicio,
                        e.data_fim,
                        e.horario_inicio,
                        e.horario_fim,
                        e.local,
                        e.endereco,
                        e.cidade,
                        e.estado,
                        e.valor,
                        e.imagem,
                        e.slug,
                        e.programacao,
                        e.max_participantes,
                        pag.status as pagamento_status,
                        pag.valor as pagamento_valor,
                        pag.pago_em
                    FROM inscricoes i
                    JOIN participantes p ON i.participante_id = p.id
                    JOIN eventos e ON i.evento_id = e.id
                    LEFT JOIN pagamentos pag ON pag.inscricao_id = i.id
                    WHERE i.id = ? AND i.participante_id = ?
                ", [$inscricao_id, participante_obter_id()]);
                
                if ($dados) {
                    echo "✅ Dados encontrados no sistema novo<br>";
                    echo "Participante: " . $dados['participante_nome'] . "<br>";
                    echo "Evento: " . $dados['evento_nome'] . "<br>";
                    echo "Status inscrição: " . ($dados['status'] ?? 'N/A') . "<br>";
                } else {
                    echo "❌ Nenhum dado encontrado no sistema novo<br>";
                    
                    // Verificar se a inscrição existe mas não pertence ao usuário
                    $inscricao_existe = buscar_um("SELECT * FROM inscricoes WHERE id = ?", [$inscricao_id]);
                    if ($inscricao_existe) {
                        echo "⚠️ Inscrição existe mas não pertence ao participante logado<br>";
                        echo "ID da inscrição: " . $inscricao_existe['id'] . "<br>";
                        echo "Participante da inscrição: " . $inscricao_existe['participante_id'] . "<br>";
                        echo "Participante logado: " . participante_obter_id() . "<br>";
                    } else {
                        echo "❌ Inscrição não existe no sistema<br>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828;'>";
                echo "<strong>❌ ERRO na query do sistema novo:</strong><br>";
                echo "Mensagem: " . $e->getMessage() . "<br>";
                echo "Linha: " . $e->getLine() . "<br>";
                echo "</div>";
            }
            
        } else {
            echo "<h3>4.2 Sistema antigo - testando query com participantes</h3>";
            
            try {
                $dados = buscar_um("
                    SELECT 
                        p.id as inscricao_id,
                        p.id as participante_id,
                        p.evento_id,
                        p.status,
                        'aprovada' as status_inscricao,
                        p.nome as participante_nome,
                        p.cpf as participante_cpf,
                        p.email as participante_email,
                        p.whatsapp as participante_whatsapp,
                        p.qr_token,
                        e.nome as evento_nome,
                        e.descricao as evento_descricao,
                        e.data_inicio,
                        e.data_fim,
                        e.horario_inicio,
                        e.horario_fim,
                        e.local,
                        e.endereco,
                        e.cidade,
                        e.estado,
                        e.valor,
                        e.imagem,
                        e.slug,
                        e.programacao,
                        e.max_participantes,
                        pag.status as pagamento_status,
                        pag.valor as pagamento_valor,
                        pag.pago_em
                    FROM participantes p
                    JOIN eventos e ON p.evento_id = e.id
                    LEFT JOIN pagamentos pag ON p.id = pag.participante_id
                    WHERE p.id = ?
                ", [$inscricao_id]);
                
                if ($dados) {
                    echo "✅ Dados encontrados no sistema antigo<br>";
                    echo "Participante: " . $dados['participante_nome'] . "<br>";
                    echo "Evento: " . $dados['evento_nome'] . "<br>";
                } else {
                    echo "❌ Nenhum dado encontrado no sistema antigo<br>";
                }
                
            } catch (Exception $e) {
                echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828;'>";
                echo "<strong>❌ ERRO na query do sistema antigo:</strong><br>";
                echo "Mensagem: " . $e->getMessage() . "<br>";
                echo "Linha: " . $e->getLine() . "<br>";
                echo "</div>";
            }
        }
        
        echo "<h2>5. Verificando estrutura das tabelas</h2>";
        
        // Verificar estrutura da tabela participantes
        echo "<h3>5.1 Estrutura da tabela participantes</h3>";
        $estrutura_participantes = buscar_todos("DESCRIBE participantes");
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>";
        foreach ($estrutura_participantes as $coluna) {
            echo "<tr>";
            echo "<td>" . $coluna['Field'] . "</td>";
            echo "<td>" . $coluna['Type'] . "</td>";
            echo "<td>" . $coluna['Null'] . "</td>";
            echo "<td>" . $coluna['Key'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($tabela_inscricoes_existe['count'] > 0) {
            echo "<h3>5.2 Estrutura da tabela inscricoes</h3>";
            $estrutura_inscricoes = buscar_todos("DESCRIBE inscricoes");
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>";
            foreach ($estrutura_inscricoes as $coluna) {
                echo "<tr>";
                echo "<td>" . $coluna['Field'] . "</td>";
                echo "<td>" . $coluna['Type'] . "</td>";
                echo "<td>" . $coluna['Null'] . "</td>";
                echo "<td>" . $coluna['Key'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<h3>5.3 Dados na tabela inscricoes</h3>";
            $inscricoes = buscar_todos("SELECT * FROM inscricoes LIMIT 5");
            if (!empty($inscricoes)) {
                echo "<table border='1' style='border-collapse: collapse;'>";
                $first = true;
                foreach ($inscricoes as $inscricao) {
                    if ($first) {
                        echo "<tr>";
                        foreach (array_keys($inscricao) as $header) {
                            echo "<th>" . $header . "</th>";
                        }
                        echo "</tr>";
                        $first = false;
                    }
                    echo "<tr>";
                    foreach ($inscricao as $valor) {
                        echo "<td>" . htmlspecialchars($valor) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "❌ Tabela inscricoes está vazia<br>";
            }
        }
        
        echo "<h2>6. Testando funções específicas</h2>";
        
        echo "<h3>6.1 Função participante_esta_logado()</h3>";
        try {
            $logado = participante_esta_logado();
            echo "Resultado: " . ($logado ? 'true' : 'false') . "<br>";
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "<br>";
        }
        
        echo "<h3>6.2 Função participante_obter_id()</h3>";
        try {
            $id = participante_obter_id();
            echo "ID: " . $id . "<br>";
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "<h2>4. Nenhum inscricao_id fornecido</h2>";
        echo "❌ Parâmetro 'inscricao' não foi enviado na URL<br>";
    }
    
    echo "<h2>✅ DEBUG COMPLETO</h2>";
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; color: #1976d2;'>";
    echo "<p>Execute este debug e analise os resultados para identificar onde está o erro 500.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 8px; color: #c62828; border: 3px solid #f44336;'>";
    echo "<h2>🚨 ERRO GERAL NO DEBUG</h2>";
    echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Código:</strong> " . $e->getCode() . "<br>";
    echo "<strong>Arquivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Linha:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?> 