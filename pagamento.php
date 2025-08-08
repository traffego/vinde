<?php
    require_once 'includes/init.php';
    require_once 'includes/auth_participante.php';

// Debug mode para desenvolvimento
$debug_mode = is_debug_enabled() || isset($_GET['debug']);

// Evitar cache para página de pagamento (gera dados dinâmicos)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

    $inscricao_id = $_GET['inscricao'] ?? '';
$erro = '';
$sucesso = '';
$novo_pix_gerado = false;

// Debug inicial
if ($debug_mode) {
    error_log("PAGAMENTO DEBUG: Inscrição ID = {$inscricao_id}");
}

// Validar inscricao_id
    if (empty($inscricao_id) || !is_numeric($inscricao_id)) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: ID de inscrição inválido");
    }
    obter_cabecalho('Pagamento - ID Inválido');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>ID de Inscrição Inválido</h1>
            <p>O ID da inscrição não foi fornecido ou é inválido.</p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
        exit;
    }

// Verificar se usuário está logado
if (!participante_esta_logado()) {
    redirecionar(SITE_URL . '/participante/login.php');
}

    $participante_logado = obter_participante_logado();
    
// Buscar dados da inscrição, participante, evento e pagamento
$inscricao = [];
$participante = [];
$evento = [];
$pagamento = [];

try {
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
        throw new Exception("Inscrição não encontrada ou não pertence ao usuário logado");
    }

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
        
        if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Dados encontrados - Inscrição: {$inscricao['id']}, Status: {$inscricao['status']}, Pagamento: {$pagamento['status']}");
    }

} catch (Exception $e) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Erro ao buscar dados - " . $e->getMessage());
    }
    
    obter_cabecalho('Erro - Pagamento');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Inscrição Não Encontrada</h1>
            <p>A inscrição solicitada não foi encontrada ou não pertence a você.</p>
            <a href="<?= SITE_URL ?>/participante/" class="btn btn-primary">Ir para Área do Participante</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Garantir que exista um registro de pagamento associado a esta inscrição
if (empty($pagamento['id'])) {
    try {
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
    } catch (Exception $e) {
        error_log('PAGAMENTO ERRO: Falha ao criar registro de pagamento: ' . $e->getMessage());
        $erro = 'Erro ao criar registro de pagamento. Tente novamente em instantes.';
    }
}

// Verificar se pagamento já foi processado
if ($pagamento['status'] === 'pago') {
    redirecionar(SITE_URL . '/confirmacao.php?inscricao=' . $inscricao_id);
}

// Verificar se o evento é gratuito
if ($evento['valor'] <= 0) {
    // Evento gratuito - atualizar status e redirecionar
    atualizar_registro('inscricoes', ['status' => 'aprovada'], ['id' => $inscricao_id]);
    redirecionar(SITE_URL . '/confirmacao.php?inscricao=' . $inscricao_id);
}

// Processar geração/renovação de PIX sempre que status não for pago
// Isso garante que sempre há um PIX válido e atualizado disponível
$deve_gerar_pix = empty($erro) && ($pagamento['status'] !== 'pago') && (
    empty($pagamento['pix_qrcode_data']) || 
    ($pagamento['pix_expires_at'] && strtotime($pagamento['pix_expires_at']) < time()) ||
    true // Sempre gerar novo PIX quando não está pago
);

if ($deve_gerar_pix) {
    try {
    
    // Gerar novo PIX sempre com TXID único (máximo 35 caracteres para EFI)
    $timestamp = date('YmdHis'); // 14 caracteres
    $inscricao_padded = str_pad($inscricao_id, 4, '0', STR_PAD_LEFT); // 4 caracteres
    $random_suffix = strtoupper(substr(md5(uniqid()), 0, 3)); // 3 caracteres
    $txid = 'VINDE' . $timestamp . $inscricao_padded . $random_suffix; // 5+14+4+3 = 26 caracteres
    $valor = $evento['valor'];
    
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Gerando novo PIX - TXID: {$txid} | Valor: R$ {$valor}");
    }
    
    // Verificar se EFI Bank está ativo (configurações vindas do banco)
    $efi_ativo = obter_configuracao('efi_ativo', '0') === '1';
    $config_efi = obter_configuracoes_efi();
    $certificado_existe = !empty($config_efi['efi_certificado_path']) && file_exists($config_efi['efi_certificado_path']);
    
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: EFI Status - Ativo: " . ($efi_ativo ? 'SIM' : 'NÃO'));
        error_log("PAGAMENTO DEBUG: EFI Client ID: " . (!empty($config_efi['efi_client_id']) ? 'Configurado' : 'Vazio'));
        error_log("PAGAMENTO DEBUG: EFI Client Secret: " . (!empty($config_efi['efi_client_secret']) ? 'Configurado' : 'Vazio'));
        error_log("PAGAMENTO DEBUG: EFI Certificado: " . ($certificado_existe ? 'Existe' : 'Não encontrado'));
        error_log("PAGAMENTO DEBUG: EFI Chave PIX: " . (!empty($config_efi['efi_pix_key']) ? $config_efi['efi_pix_key'] : 'Vazio'));
    }
    
    if ($efi_ativo && $certificado_existe) {
        // Usar EFI Bank com função de alto nível
        $resultado_pix = efi_criar_pix_completo([
            'valor' => $valor,
            'descricao' => sprintf('Inscricao %s - %s', $evento['nome'], $participante['nome']),
            'participante_id' => $participante_logado['id'],
            'evento_nome' => $evento['nome'],
            'nome_pagador' => $participante['nome'],
            'cpf_pagador' => limpar_cpf($participante['cpf']),
            'expiracao' => 3600,
            'debug' => $debug_mode,
            'txid_customizado' => $txid // Usar nosso TXID gerado
        ]);

        if (!empty($resultado_pix['sucesso'])) {
            $dados_pagamento = [
                'pix_txid' => $resultado_pix['pix_txid'],
                'pix_loc_id' => $resultado_pix['pix_loc_id'] ?? null,
                'pix_qrcode_data' => $resultado_pix['pix_qrcode_data'] ?? null,
                'pix_qrcode_url' => $resultado_pix['pix_qrcode_url'] ?? null,
                'pix_expires_at' => $resultado_pix['pix_expires_at'] ?? date('Y-m-d H:i:s', time() + 3600),
                'status' => 'pendente', // Garantir que status seja pendente para novo PIX
                'atualizado_em' => date('Y-m-d H:i:s')
            ];

            // Atualizar tabela de pagamentos
            $sucesso_pagamento = atualizar_registro('pagamentos', $dados_pagamento, ['id' => $pagamento['id']]);
            
            // Atualizar tabela de inscrições conforme solicitado
            if ($sucesso_pagamento) {
                $dados_inscricao = [
                    'status' => 'pendente', // Status pendente até confirmação do pagamento
                    'atualizado_em' => date('Y-m-d H:i:s')
                ];
                
                atualizar_registro('inscricoes', $dados_inscricao, ['id' => $inscricao_id]);
                
                if ($debug_mode) {
                    error_log("PAGAMENTO DEBUG: Dados atualizados - Pagamento ID: {$pagamento['id']}, Inscrição ID: {$inscricao_id}");
                }
            }
            
            $pagamento = array_merge($pagamento, $dados_pagamento);
            
            // Definir variável para mostrar mensagem de novo PIX gerado
            $novo_pix_gerado = true;
            
            if ($debug_mode) {
                error_log("PAGAMENTO DEBUG: PIX gerado com sucesso - Payload: " . substr($resultado_pix['pix_qrcode_data'] ?? '', 0, 50) . "...");
            }
        } else {
            if ($debug_mode) {
                error_log("PAGAMENTO DEBUG: Falha ao gerar PIX via EFI Bank - resultado_pix: " . print_r($resultado_pix, true));
            }
            
            // Log de erro crítico - não deve usar fallback em produção
            error_log("ERRO CRÍTICO: EFI Bank falhou ao gerar PIX - TXID: {$txid} | Valor: R$ {$valor}");
            $erro = "Erro ao gerar PIX. Tente novamente ou entre em contato com o suporte.";
        }
            } else {
        // Log detalhado do motivo de não usar EFI
        if ($debug_mode) {
            if (!$efi_ativo) {
                error_log("PAGAMENTO DEBUG: EFI Bank não está ativo nas configurações");
            }
            if (!$certificado_existe) {
                error_log("PAGAMENTO DEBUG: Certificado EFI não encontrado: " . ($config_efi['efi_certificado_path'] ?? 'caminho não configurado'));
            }
        }
        
        error_log("ERRO: EFI Bank não configurado corretamente - Ativo: " . ($efi_ativo ? 'SIM' : 'NÃO') . " | Certificado: " . ($certificado_existe ? 'SIM' : 'NÃO'));
        $erro = "Sistema de pagamento não configurado. Entre em contato com o suporte.";
    }
    } catch (Exception $e) {
        error_log('PAGAMENTO ERRO: Exceção ao gerar PIX: ' . $e->getMessage());
        $erro = 'Erro interno ao gerar o PIX. Tente novamente.';
    }
}

// Calcular tempo restante para expiração
$tempo_expiracao = null;
if ($pagamento['pix_expires_at']) {
    $expira_timestamp = strtotime($pagamento['pix_expires_at']);
    $agora = time();
    $tempo_expiracao = max(0, $expira_timestamp - $agora);
}

// Debug: verificar se chegou até aqui
if ($debug_mode) {
    error_log("PAGAMENTO DEBUG: Chegou até obter_cabecalho - Evento: " . $evento['nome']);
}

obter_cabecalho('Pagamento - ' . $evento['nome']);
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/pagamento.css?v=<?= urlencode(SISTEMA_VERSAO) ?>">

<main class="pagamento-container">
    <div class="pagamento-header">
        <h1>Finalizar Pagamento</h1>
        <p>Complete seu pagamento para confirmar a inscrição</p>
        </div>

    <?php if ($erro && $debug_mode): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($novo_pix_gerado) && $novo_pix_gerado): ?>
        <div class="alert alert-success" style="margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; color: #155724;">
            <strong>✅ Novo PIX Gerado!</strong><br>
            <small>Um novo código PIX foi criado especialmente para esta sessão. Utilize o QR Code ou código abaixo para realizar o pagamento.</small>
        </div>
    <?php endif; ?>

    <div class="pagamento-layout">
        <div class="pagamento-main">
            <div class="pix-section">
                <h2>Pagamento via PIX</h2>
                
                <?php if ($tempo_expiracao !== null): ?>
                    <div class="timer-expiracao" id="timer-container">
                        <div id="timer-text">
                            ⏰ Código expira em: <span id="countdown"><?= gmdate('i:s', $tempo_expiracao) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pagamento['pix_qrcode_url']) || !empty($pagamento['pix_qrcode_data'])): ?>
                    <div class="qr-code-container">
                        <?php if (!empty($pagamento['pix_qrcode_url'])): ?>
                            <img 
                                src="<?= strpos($pagamento['pix_qrcode_url'], 'data:image') === 0 ? $pagamento['pix_qrcode_url'] : 'data:image/png;base64,' . preg_replace('/\s+/', '', $pagamento['pix_qrcode_url']) ?>"
                                alt="QR Code PIX" 
                                id="qr-code-img"
                                width="260" height="260"
                                decoding="async" loading="eager"
                                onerror="this.style.display='none';var w=document.getElementById('qr-canvas-wrapper');if(w){w.style.display='inline-block';}gerarQrPixCanvas();"
                            >
                        <?php endif; ?>
                        <?php if (!empty($pagamento['pix_qrcode_data'])): ?>
                            <div id="qr-canvas-wrapper" style="display: <?= empty($pagamento['pix_qrcode_url']) ? 'inline-block' : 'none' ?>;">
                                <!-- <canvas id="qr-canvas-pix"></canvas> -->
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pagamento['pix_qrcode_data'])): ?>
                    <div class="pix-code-section">
                        <p><strong>Ou copie o código PIX:</strong></p>
                        <div class="pix-code" id="pix-code" title="Clique para selecionar todo o código"><?php echo htmlspecialchars(trim($pagamento['pix_qrcode_data'])); ?></div>
                        
                        <?php if ($debug_mode): ?>
                        <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; font-size: 11px;">
                            <strong>Debug Info:</strong><br>
                            <?php if (isset($novo_pix_gerado) && $novo_pix_gerado): ?>
                                <span style="color: #28a745; font-weight: bold;">🔄 NOVO PIX GERADO NESTA SESSÃO</span><br>
                            <?php endif; ?>
                            Tamanho: <?= strlen(trim($pagamento['pix_qrcode_data'])) ?> caracteres<br>
                            Início: <?= htmlspecialchars(substr(trim($pagamento['pix_qrcode_data']), 0, 20)) ?>...<br>
                            Final: ...<?= htmlspecialchars(substr(trim($pagamento['pix_qrcode_data']), -20)) ?><br>
                            TXID: <?= htmlspecialchars($pagamento['pix_txid'] ?? 'N/A') ?><br>
                            Expira em: <?= $pagamento['pix_expires_at'] ? date('d/m/Y H:i:s', strtotime($pagamento['pix_expires_at'])) : 'N/A' ?><br>
                            Status Pagamento: <?= htmlspecialchars($pagamento['status'] ?? 'N/A') ?><br>
                            Status Inscrição: <?= htmlspecialchars($inscricao['status'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="pix-actions">
                            <button type="button" class="btn-copiar" onclick="copiarPix(this)" id="btn-copiar-pix">📋 Copiar Código PIX</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
                        <strong>⚠️ Código PIX não disponível</strong><br>
                        <small>O código PIX não foi gerado corretamente. <?= $debug_mode ? 'Verifique as configurações EFI Bank no painel administrativo.' : 'Entre em contato com o suporte.' ?></small>
                    </div>
                <?php endif; ?>

                <div class="instrucoes-pix">
                    <h4>Como pagar:</h4>
                    <ol>
                        <li>Abra o app do seu banco</li>
                        <li>Escolha a opção PIX</li>
                        <li>Escaneie o QR Code ou cole o código copiado</li>
                        <li>Confirme o pagamento</li>
                        <li>Aguarde a confirmação automática</li>
                    </ol>
                        </div>
                        
                <div class="verificacao-status">
                    <button type="button" class="btn-verificar" onclick="verificarPagamento()">
                        🔄 Verificar Pagamento
                    </button>
                    <div class="loading-spinner" id="loading-spinner"></div>
                            </div>
                        </div>
                    </div>
                    
        <div class="pagamento-sidebar">
            <div class="resumo-pagamento">
                <h3>Resumo da Inscrição</h3>
                
                <div class="resumo-item">
                    <span>Evento:</span>
                    <span><?= htmlspecialchars($evento['nome']) ?></span>
                </div>
                
                <div class="resumo-item">
                    <span>Data:</span>
                    <span><?= date('d/m/Y', strtotime($evento['data_inicio'])) ?></span>
                        </div>
                        
                <?php if ($evento['horario_inicio']): ?>
                    <div class="resumo-item">
                        <span>Horário:</span>
                        <span><?= date('H:i', strtotime($evento['horario_inicio'])) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="resumo-item">
                    <span>Local:</span>
                    <span><?= htmlspecialchars($evento['local']) ?></span>
                    </div>
                    
                <div class="resumo-item">
                    <span>Participante:</span>
                    <span><?= htmlspecialchars($participante['nome']) ?></span>
                    </div>
                    
                <div class="resumo-item">
                    <span>Status:</span>
                    <span class="status-badge status-<?= $inscricao['status'] ?>">
                        <?= ucfirst($inscricao['status']) ?>
                    </span>
                    </div>
                
                <div class="resumo-item">
                    <span>Valor Total:</span>
                    <span>R$ <?= number_format($evento['valor'], 2, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js?v=1.5.3"></script>
<script>
// Configurações globais para o JavaScript
window.SITE_URL = '<?= SITE_URL ?>';
window.INSCRICAO_ID = <?= $inscricao_id ?>;
window.PAGAMENTO_ID = <?= $pagamento['id'] ?? 'null' ?>;
window.PIX_PAYLOAD = '<?= isset($pagamento['pix_qrcode_data']) ? addslashes($pagamento['pix_qrcode_data']) : '' ?>';
<?php if ($tempo_expiracao !== null): ?>
window.TEMPO_EXPIRACAO = <?= $tempo_expiracao ?>;
<?php endif; ?>
    </script>
<script src="<?= SITE_URL ?>/assets/js/pagamento.js?v=<?= urlencode(SISTEMA_VERSAO) ?>"></script>

<?php obter_rodape(); ?>
