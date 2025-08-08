<?php
// Debug para erro 500 na página de pagamento
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔍 Debug Erro 500 - Página de Pagamento</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .erro{color:red;} .aviso{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

try {
    echo "<h2>1. Testando Includes Básicos</h2>";
    
    // Testar includes um por um
    echo "<p>Testando includes/init.php... ";
    require_once 'includes/init.php';
    echo "<span class='ok'>✅ OK</span></p>";
    
    echo "<p>Testando includes/auth_participante.php... ";
    require_once 'includes/auth_participante.php';
    echo "<span class='ok'>✅ OK</span></p>";
    
    echo "<h2>2. Testando Parâmetros</h2>";
    
    $inscricao_id = $_GET['inscricao'] ?? '21';
    echo "<p><strong>ID da Inscrição:</strong> " . htmlspecialchars($inscricao_id) . "</p>";
    
    // Validar inscricao_id
    if (empty($inscricao_id) || !is_numeric($inscricao_id)) {
        echo "<p><span class='erro'>❌ ID de inscrição inválido</span></p>";
    } else {
        echo "<p><span class='ok'>✅ ID de inscrição válido</span></p>";
    }
    
    echo "<h2>3. Testando Função is_debug_enabled()</h2>";
    
    if (function_exists('is_debug_enabled')) {
        $debug_mode = is_debug_enabled() || isset($_GET['debug']);
        echo "<p><strong>Debug Mode:</strong> " . ($debug_mode ? '<span class="ok">✅ Ativo</span>' : '<span class="aviso">⚠️ Inativo</span>') . "</p>";
    } else {
        echo "<p><span class='erro'>❌ Função is_debug_enabled não existe</span></p>";
    }
    
    echo "<h2>4. Testando Autenticação de Participante</h2>";
    
    if (function_exists('participante_esta_logado')) {
        $logado = participante_esta_logado();
        echo "<p><strong>Participante Logado:</strong> " . ($logado ? '<span class="ok">✅ SIM</span>' : '<span class="erro">❌ NÃO</span>') . "</p>";
        
        if (!$logado) {
            echo "<p><span class='aviso'>⚠️ Este pode ser o problema - usuário não está logado</span></p>";
            echo "<p><strong>Redirecionamento seria para:</strong> " . SITE_URL . '/participante/login.php</p>';
        }
    } else {
        echo "<p><span class='erro'>❌ Função participante_esta_logado não existe</span></p>";
    }
    
    echo "<h2>5. Testando Busca no Banco</h2>";
    
    if ($logado ?? false) {
        $participante_logado = obter_participante_logado();
        echo "<p><strong>Participante ID:</strong> " . ($participante_logado['id'] ?? 'N/A') . "</p>";
        
        // Testar query da página de pagamento
        echo "<p>Testando query principal... ";
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
        
        if ($dados) {
            echo "<span class='ok'>✅ Dados encontrados</span></p>";
            echo "<p><strong>Evento:</strong> " . htmlspecialchars($dados['evento_nome'] ?? 'N/A') . "</p>";
            echo "<p><strong>Status Inscrição:</strong> " . htmlspecialchars($dados['status'] ?? 'N/A') . "</p>";
            echo "<p><strong>Status Pagamento:</strong> " . htmlspecialchars($dados['pagamento_status'] ?? 'N/A') . "</p>";
        } else {
            echo "<span class='erro'>❌ Nenhum dado encontrado</span></p>";
            echo "<p><span class='erro'>❌ Este é provavelmente o problema - inscrição não encontrada ou não pertence ao usuário</span></p>";
        }
        
    } else {
        echo "<p><span class='aviso'>⚠️ Pulando teste de banco - usuário não logado</span></p>";
    }
    
    echo "<h2>6. Testando Funções EFI</h2>";
    
    echo "<p>Função efi_esta_ativo(): " . (function_exists('efi_esta_ativo') ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não existe</span>') . "</p>";
    echo "<p>Função obter_configuracoes_efi(): " . (function_exists('obter_configuracoes_efi') ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não existe</span>') . "</p>";
    echo "<p>Função efi_criar_pix_completo(): " . (function_exists('efi_criar_pix_completo') ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não existe</span>') . "</p>";
    
    echo "<h2>✅ Teste Concluído</h2>";
    echo "<p>Se chegou até aqui sem erro, o problema pode estar na lógica específica da página de pagamento.</p>";
    
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
echo "<h2>🔗 Links de Teste</h2>";
echo "<p>";
echo "<a href='pagamento.php?inscricao=21' style='background:#dc3545;color:white;padding:8px 12px;text-decoration:none;border-radius:4px;margin:5px;'>❌ Página com Erro</a> ";
echo "<a href='pagamento.php?inscricao=21&debug=1' style='background:#ffc107;color:black;padding:8px 12px;text-decoration:none;border-radius:4px;margin:5px;'>🔍 Com Debug</a> ";
echo "<a href='participante/login.php' style='background:#17a2b8;color:white;padding:8px 12px;text-decoration:none;border-radius:4px;margin:5px;'>🔑 Login Participante</a>";
echo "</p>";

echo "<p><small>Debug gerado em: " . date('d/m/Y H:i:s') . "</small></p>";
?>
