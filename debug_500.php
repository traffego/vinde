<?php
// Debug simples para erro 500
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG ERRO 500 ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    echo "1. Testando includes/init.php... ";
    require_once 'includes/init.php';
    echo "OK\n";
    
    echo "2. Testando includes/auth_participante.php... ";
    require_once 'includes/auth_participante.php';
    echo "OK\n";
    
    echo "3. Verificando login... ";
    if (!participante_esta_logado()) {
        echo "ERRO - Não logado\n";
        echo "\nRedirecionando para login...\n";
        echo "URL: " . SITE_URL . "/participante/login.php\n";
        exit;
    }
    echo "OK\n";
    
    $participante_logado = obter_participante_logado();
    echo "   Participante ID: {$participante_logado['id']}\n";
    
    $inscricao_id = $_GET['inscricao'] ?? '21';
    echo "4. Inscrição ID: {$inscricao_id}\n";
    
    echo "5. Testando query principal... ";
    $dados = buscar_um("
        SELECT i.*, 
               p.nome as participante_nome, p.cpf as participante_cpf, 
               e.nome as evento_nome, e.valor as evento_valor,
               pag.status as pagamento_status, pag.pix_qrcode_data
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pag ON i.id = pag.inscricao_id
        WHERE i.id = ? AND i.participante_id = ?
    ", [$inscricao_id, $participante_logado['id']]);
    
    if (!$dados) {
        echo "ERRO - Dados não encontrados\n";
        exit;
    }
    echo "OK\n";
    echo "   Evento: {$dados['evento_nome']}\n";
    echo "   Status: {$dados['pagamento_status']}\n";
    echo "   Valor: R$ " . number_format($dados['evento_valor'], 2, ',', '.') . "\n";
    
    echo "6. Testando função obter_cabecalho()... ";
    ob_start();
    obter_cabecalho('Teste');
    $output = ob_get_clean();
    echo "OK (tamanho: " . strlen($output) . " bytes)\n";
    
    echo "\n=== TODOS OS TESTES PASSARAM ===\n";
    echo "A lógica básica está funcionando.\n";
    echo "O erro 500 pode estar em:\n";
    echo "- Tamanho do CSS inline\n";
    echo "- JavaScript com erro\n";
    echo "- Limite de memória PHP\n";
    echo "- Output buffering\n";
    
} catch (Exception $e) {
    echo "\nERRO CAPTURADO:\n";
    echo "Tipo: Exception\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    
} catch (Error $e) {
    echo "\nERRO FATAL CAPTURADO:\n";
    echo "Tipo: Error\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}

echo "\n=== FIM DO DEBUG ===\n";
?>
