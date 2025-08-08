<?php
// Debug detalhado da lógica da página de pagamento
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Detalhado - Lógica da Página de Pagamento</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .erro{color:red;} .aviso{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

try {
    require_once 'includes/init.php';
    require_once 'includes/auth_participante.php';

    $inscricao_id = 21;
    $debug_mode = true;
    
    echo "<h2>1. ✅ Simulando Início da Página</h2>";
    echo "<p>Inscrição ID: {$inscricao_id}</p>";
    
    // Verificar se usuário está logado
    if (!participante_esta_logado()) {
        echo "<p><span class='erro'>❌ Usuário não logado - deveria redirecionar</span></p>";
        exit;
    }
    
    $participante_logado = obter_participante_logado();
    echo "<p>Participante logado: {$participante_logado['id']}</p>";
    
    echo "<h2>2. ✅ Buscando Dados</h2>";
    
    // Buscar dados exatamente como na página original
    $dados = buscar_um("
        SELECT i.*, 
               p.nome as participante_nome, p.cpf as participante_cpf, 
               p.email as participante_email, p.whatsapp as participante_whatsapp,
               e.nome as evento_nome, e.data_inicio, e.horario_inicio, 
               e.local, e.cidade as evento_cidade, e.estado as evento_estado,
               e.valor as evento_valor,
               pag.id as pagamento_id, pag.valor as pagamento_valor, 
               pag.status as pagamento_status, pag.pix_txid, pag.pix_loc_id, 
               pag.pix_qrcode_data, pag.pix_qrcode_url, pag.pix_expires_at, 
               pag.criado_em as pagamento_criado
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pag ON i.id = pag.inscricao_id
        WHERE i.id = ? AND i.participante_id = ?
    ", [$inscricao_id, $participante_logado['id']]);

    if (!$dados) {
        echo "<p><span class='erro'>❌ Dados não encontrados</span></p>";
        exit;
    }
    
    echo "<p>✅ Dados encontrados</p>";
    
    // Separar dados em arrays organizados
    $inscricao = [
        'id' => $dados['id'],
        'status' => $dados['status'],
        'valor_pago' => $dados['valor_pago'],
        'data_inscricao' => $dados['data_inscricao']
    ];

    $participante = [
        'nome' => $dados['participante_nome'],
        'cpf' => $dados['participante_cpf'],
        'email' => $dados['participante_email'],
        'whatsapp' => $dados['participante_whatsapp']
    ];

    $evento = [
        'nome' => $dados['evento_nome'],
        'data_inicio' => $dados['data_inicio'],
        'horario_inicio' => $dados['horario_inicio'],
        'local' => $dados['local'],
        'cidade' => $dados['evento_cidade'],
        'estado' => $dados['evento_estado'],
        'valor' => $dados['evento_valor']
    ];

    $pagamento = [
        'id' => $dados['pagamento_id'],
        'valor' => $dados['pagamento_valor'],
        'status' => $dados['pagamento_status'],
        'pix_txid' => $dados['pix_txid'],
        'pix_loc_id' => $dados['pix_loc_id'],
        'pix_qrcode_data' => $dados['pix_qrcode_data'],
        'pix_qrcode_url' => $dados['pix_qrcode_url'],
        'pix_expires_at' => $dados['pix_expires_at'],
        'criado_em' => $dados['pagamento_criado']
    ];
    
    echo "<p><strong>Status Inscrição:</strong> {$inscricao['status']}</p>";
    echo "<p><strong>Status Pagamento:</strong> {$pagamento['status']}</p>";
    echo "<p><strong>Valor Evento:</strong> R$ " . number_format($evento['valor'], 2, ',', '.') . "</p>";
    
    echo "<h2>3. ✅ Verificando se Pagamento já foi Processado</h2>";
    
    if ($pagamento['status'] === 'pago') {
        echo "<p><span class='aviso'>⚠️ Pagamento já processado - deveria redirecionar para confirmação</span></p>";
        exit;
    }
    
    echo "<h2>4. ✅ Verificando se Evento é Gratuito</h2>";
    
    if ($evento['valor'] <= 0) {
        echo "<p><span class='aviso'>⚠️ Evento gratuito - deveria atualizar status e redirecionar</span></p>";
        exit;
    }
    
    echo "<p>Evento pago: R$ " . number_format($evento['valor'], 2, ',', '.') . "</p>";
    
    echo "<h2>5. ✅ Garantindo Registro de Pagamento</h2>";
    
    if (empty($pagamento['id'])) {
        echo "<p>Criando registro de pagamento...</p>";
        $txid = 'VINDE' . date('YmdHis') . str_pad($inscricao_id, 6, '0', STR_PAD_LEFT);
        $pagamento_id = inserir_registro('pagamentos', [
            'participante_id' => $participante_logado['id'],
            'inscricao_id' => $inscricao_id,
            'valor' => $evento['valor'],
            'status' => 'pendente',
            'metodo' => 'pix',
            'pix_txid' => $txid
        ]);
        $pagamento['id'] = $pagamento_id;
        $pagamento['status'] = 'pendente';
        $pagamento['valor'] = $evento['valor'];
        $pagamento['pix_txid'] = $txid;
        echo "<p>✅ Registro de pagamento criado: ID {$pagamento_id}</p>";
    } else {
        echo "<p>✅ Registro de pagamento já existe: ID {$pagamento['id']}</p>";
    }
    
    echo "<h2>6. 🔄 Testando Lógica de Geração de PIX</h2>";
    
    // Esta é a parte crítica que pode estar causando o erro
    $deve_gerar_pix = ($pagamento['status'] !== 'pago') && (
        empty($pagamento['pix_qrcode_data']) || 
        ($pagamento['pix_expires_at'] && strtotime($pagamento['pix_expires_at']) < time()) ||
        true // Sempre gerar novo PIX quando não está pago
    );

    echo "<p><strong>Deve gerar PIX:</strong> " . ($deve_gerar_pix ? '<span class="ok">✅ SIM</span>' : '<span class="aviso">⚠️ NÃO</span>') . "</p>";
    
    if ($deve_gerar_pix) {
        echo "<h3>6.1 Gerando TXID</h3>";
        
        $timestamp = date('YmdHis');
        $inscricao_padded = str_pad($inscricao_id, 4, '0', STR_PAD_LEFT);
        $random_suffix = strtoupper(substr(md5(uniqid()), 0, 3));
        $txid = 'VINDE' . $timestamp . $inscricao_padded . $random_suffix;
        $valor = $evento['valor'];
        
        echo "<p><strong>TXID gerado:</strong> {$txid}</p>";
        echo "<p><strong>Tamanho TXID:</strong> " . strlen($txid) . " caracteres</p>";
        
        echo "<h3>6.2 Verificando Configurações EFI</h3>";
        
        $efi_ativo = obter_configuracao('efi_ativo', '0') === '1';
        $config_efi = obter_configuracoes_efi();
        $certificado_existe = !empty($config_efi['efi_certificado_path']) && file_exists($config_efi['efi_certificado_path']);
        
        echo "<p><strong>EFI Ativo:</strong> " . ($efi_ativo ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO</span>') . "</p>";
        echo "<p><strong>Certificado:</strong> " . ($certificado_existe ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não encontrado</span>') . "</p>";
        echo "<p><strong>Caminho:</strong> " . htmlspecialchars($config_efi['efi_certificado_path'] ?? 'N/A') . "</p>";
        
        if ($efi_ativo && $certificado_existe) {
            echo "<h3>6.3 🚨 TESTANDO CRIAÇÃO PIX EFI (PONTO CRÍTICO)</h3>";
            
            try {
                $resultado_pix = efi_criar_pix_completo([
                    'valor' => $valor,
                    'descricao' => sprintf('Inscricao %s - %s', $evento['nome'], $participante['nome']),
                    'participante_id' => $participante_logado['id'],
                    'evento_nome' => $evento['nome'],
                    'nome_pagador' => $participante['nome'],
                    'cpf_pagador' => limpar_cpf($participante['cpf']),
                    'expiracao' => 3600,
                    'debug' => $debug_mode,
                    'txid_customizado' => $txid
                ]);
                
                echo "<p><strong>Resultado PIX:</strong></p>";
                echo "<pre>" . htmlspecialchars(print_r($resultado_pix, true)) . "</pre>";
                
                if (!empty($resultado_pix['sucesso'])) {
                    echo "<p><span class='ok'>✅ PIX criado com sucesso!</span></p>";
                } else {
                    echo "<p><span class='erro'>❌ Falha ao criar PIX</span></p>";
                    if (isset($resultado_pix['erro'])) {
                        echo "<p><strong>Erro:</strong> " . htmlspecialchars($resultado_pix['erro']) . "</p>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<p><span class='erro'>❌ ERRO CAPTURADO NA CRIAÇÃO DO PIX:</span></p>";
                echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
                echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            }
            
        } else {
            echo "<p><span class='erro'>❌ EFI não configurado corretamente</span></p>";
        }
    }
    
    echo "<h2>✅ Teste Concluído</h2>";
    echo "<p>Se chegou até aqui, o problema foi identificado acima.</p>";
    
} catch (Exception $e) {
    echo "<h2><span class='erro'>❌ ERRO CAPTURADO!</span></h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    
} catch (Error $e) {
    echo "<h2><span class='erro'>❌ ERRO FATAL CAPTURADO!</span></h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><small>Debug detalhado gerado em: " . date('d/m/Y H:i:s') . "</small></p>";
?>
