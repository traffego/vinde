<?php
require_once '../../includes/init.php';

// Verificar login
requer_login();

// Definir header JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido']);
        exit;
    }

    // Verificar CSRF token
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Token de segurança inválido']);
        exit;
    }

    $acao = $_POST['acao'] ?? null;
    $ids = $_POST['ids'] ?? [];

    if (!$acao) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ação não especificada']);
        exit;
    }

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum participante selecionado']);
        exit;
    }

    // Validar IDs (devem ser números)
    $ids = array_filter($ids, 'is_numeric');
    if (empty($ids)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'IDs inválidos']);
        exit;
    }

    $sucesso = 0;
    $erros = 0;
    $mensagens = [];

    foreach ($ids as $participante_id) {
        try {
            switch ($acao) {
                case 'aprovar':
                    $resultado = executar_consulta(
                        "UPDATE inscricoes SET status = 'aprovada' WHERE participante_id = ? AND status = 'pendente'",
                        [$participante_id]
                    );
                    if ($resultado->rowCount() > 0) {
                        $sucesso++;
                        registrar_log('inscricao_aprovada_massa', "Participante ID: {$participante_id}");
                    }
                    break;

                case 'rejeitar':
                    $resultado = executar_consulta(
                        "UPDATE inscricoes SET status = 'rejeitada' WHERE participante_id = ? AND status = 'pendente'",
                        [$participante_id]
                    );
                    if ($resultado->rowCount() > 0) {
                        $sucesso++;
                        registrar_log('inscricao_rejeitada_massa', "Participante ID: {$participante_id}");
                    }
                    break;

                case 'cancelar':
                    $resultado = executar_consulta(
                        "UPDATE inscricoes SET status = 'cancelada' WHERE participante_id = ?",
                        [$participante_id]
                    );
                    if ($resultado->rowCount() > 0) {
                        $sucesso++;
                        registrar_log('inscricao_cancelada_massa', "Participante ID: {$participante_id}");
                    }
                    break;

                case 'excluir':
                    // Verificar se pode excluir (mesmas validações da exclusão individual)
                    $participante_data = buscar_um("SELECT nome, status FROM participantes WHERE id = ?", [$participante_id]);
                    
                    if ($participante_data) {
                        // Verificar pagamentos confirmados
                        $pagamentos_pagos = buscar_um("SELECT COUNT(*) as total FROM pagamentos WHERE participante_id = ? AND status = 'pago'", [$participante_id]);
                        $total_pagamentos_pagos = $pagamentos_pagos ? $pagamentos_pagos['total'] : 0;
                        
                        if ($total_pagamentos_pagos > 0) {
                            $mensagens[] = "Participante {$participante_data['nome']}: não pode ser excluído (tem pagamentos confirmados)";
                            $erros++;
                            continue;
                        }
                        
                        // Verificar check-in
                        $inscricoes_aprovadas = buscar_um("SELECT COUNT(*) as total FROM inscricoes WHERE participante_id = ? AND status = 'aprovada'", [$participante_id]);
                        $total_inscricoes_aprovadas = $inscricoes_aprovadas ? $inscricoes_aprovadas['total'] : 0;
                        
                        if ($total_inscricoes_aprovadas > 0 && $participante_data['status'] === 'presente') {
                            $mensagens[] = "Participante {$participante_data['nome']}: não pode ser excluído (já fez check-in)";
                            $erros++;
                            continue;
                        }
                        
                        // Excluir
                        $resultado = remover_registro('participantes', ['id' => $participante_id]);
                        if ($resultado) {
                            $sucesso++;
                            registrar_log('participante_excluido_massa', "Participante: {$participante_data['nome']} (ID: {$participante_id})");
                        } else {
                            $erros++;
                        }
                    } else {
                        $erros++;
                    }
                    break;

                default:
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Ação não reconhecida']);
                    exit;
            }
        } catch (Exception $e) {
            $erros++;
            error_log("Erro na ação em massa ({$acao}) para participante {$participante_id}: " . $e->getMessage());
        }
    }

    // Preparar resposta
    $total = count($ids);
    $mensagem = '';
    
    if ($sucesso > 0) {
        $mensagem .= "{$sucesso} de {$total} participante(s) processado(s) com sucesso. ";
    }
    
    if ($erros > 0) {
        $mensagem .= "{$erros} erro(s) encontrado(s). ";
    }
    
    if (!empty($mensagens)) {
        $mensagem .= "Detalhes: " . implode('; ', $mensagens);
    }

    echo json_encode([
        'sucesso' => $sucesso > 0,
        'mensagem' => trim($mensagem),
        'processados' => $sucesso,
        'erros' => $erros,
        'total' => $total
    ]);

} catch (Exception $e) {
    error_log("Erro na API de ação em massa: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro interno do servidor',
        'debug' => [
            'erro' => $e->getMessage(),
            'arquivo' => basename($e->getFile()),
            'linha' => $e->getLine()
        ]
    ]);
}
?>
