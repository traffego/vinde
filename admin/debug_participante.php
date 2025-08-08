<?php
require_once '../includes/init.php';

$participante_id = $_GET['id'] ?? 12;

echo "=== DEBUG PARTICIPANTE {$participante_id} ===\n\n";

// Verificar se participante existe
$participante = buscar_um("SELECT * FROM participantes WHERE id = ?", [$participante_id]);

if (!$participante) {
    echo "❌ Participante {$participante_id} não encontrado\n";
    
    // Listar participantes disponíveis
    $participantes = buscar_todos("SELECT id, nome, email, status FROM participantes ORDER BY id DESC LIMIT 10");
    echo "\n📋 Últimos participantes:\n";
    foreach ($participantes as $p) {
        echo "ID: {$p['id']} | Nome: {$p['nome']} | Status: {$p['status']}\n";
    }
    exit;
}

echo "✅ Participante encontrado:\n";
echo "Nome: {$participante['nome']}\n";
echo "Email: {$participante['email']}\n";
echo "Status: {$participante['status']}\n";
echo "Evento ID: {$participante['evento_id']}\n\n";

// Verificar evento
$evento = buscar_um("SELECT * FROM eventos WHERE id = ?", [$participante['evento_id']]);

if (!$evento) {
    echo "❌ Evento {$participante['evento_id']} não encontrado\n";
    exit;
}

echo "✅ Evento encontrado:\n";
echo "Nome: {$evento['nome']}\n";
echo "Status: {$evento['status']}\n";
echo "Valor: R$ " . number_format($evento['valor'], 2, ',', '.') . "\n\n";

// Verificar pagamento
$pagamento = buscar_um("SELECT * FROM pagamentos WHERE participante_id = ?", [$participante_id]);

if (!$pagamento) {
    echo "❌ Pagamento não encontrado\n";
    echo "💡 Criando pagamento...\n";
    
    // Criar pagamento
    $resultado = executar("INSERT INTO pagamentos (participante_id, valor, status, criado_em) VALUES (?, ?, 'pendente', ?)", 
        [$participante_id, $evento['valor'], date('Y-m-d H:i:s')]);
    
    if ($resultado) {
        echo "✅ Pagamento criado com sucesso!\n";
        $pagamento = buscar_um("SELECT * FROM pagamentos WHERE participante_id = ?", [$participante_id]);
    } else {
        echo "❌ Erro ao criar pagamento\n";
        exit;
    }
}

echo "✅ Pagamento encontrado:\n";
echo "ID: {$pagamento['id']}\n";
echo "Valor: R$ " . number_format($pagamento['valor'], 2, ',', '.') . "\n";
echo "Status: {$pagamento['status']}\n";
echo "PIX TXID: " . ($pagamento['pix_txid'] ?? 'Não gerado') . "\n";
echo "PIX Data: " . (empty($pagamento['pix_qrcode_data']) ? 'Não gerado' : 'Gerado') . "\n\n";

// Testar geração PIX se necessário
if (empty($pagamento['pix_qrcode_data']) && efi_esta_ativo()) {
    echo "🔄 Testando geração PIX via EFI Bank...\n";
    
    $dados_pix = [
        'valor' => $pagamento['valor'],
        'descricao' => "Inscrição: " . $evento['nome'],
        'participante_id' => $participante_id,
        'evento_nome' => $evento['nome'],
        'nome_pagador' => $participante['nome'],
        'cpf_pagador' => $participante['cpf'] ?? '',
        'expiracao' => 3600
    ];
    
    $resultado_efi = efi_criar_pix_completo($dados_pix);
    
    if ($resultado_efi && isset($resultado_efi['sucesso'])) {
        echo "✅ PIX gerado com sucesso!\n";
        echo "TXID: {$resultado_efi['pix_txid']}\n";
        echo "Source: {$resultado_efi['payload_source']}\n";
        
        // Atualizar pagamento
        executar("UPDATE pagamentos SET pix_txid = ?, pix_qrcode_data = ?, pix_qrcode_url = ?, pix_expires_at = ? WHERE id = ?", 
            [$resultado_efi['pix_txid'], $resultado_efi['pix_qrcode_data'], $resultado_efi['pix_qrcode_url'], 
             $resultado_efi['pix_expires_at'], $pagamento['id']]);
        
        echo "✅ Pagamento atualizado no banco!\n";
    } else {
        echo "❌ Erro ao gerar PIX: " . ($resultado_efi['erro'] ?? 'Desconhecido') . "\n";
    }
}

echo "\n🔗 Link da página:\n";
echo "https://vinde.traffego.agency/pagamento.php?participante={$participante_id}\n";
?> 