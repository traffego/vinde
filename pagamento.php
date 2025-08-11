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

// OTIMIZAÇÃO: Pré-carregar token EFI para acelerar geração de PIX
if (function_exists('efi_precarregar_token')) {
    efi_precarregar_token();
}
    
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
$pagamento_criado_agora = false;
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
        // Garantir que campos PIX estejam inicializados
        $pagamento['pix_qrcode_data'] = null;
        $pagamento['pix_qrcode_url'] = null;
        $pagamento['pix_expires_at'] = null;
        $pagamento['pix_loc_id'] = null;
        $pagamento_criado_agora = true;
        
        if ($debug_mode) {
            error_log("PAGAMENTO DEBUG: Novo registro de pagamento criado - ID: {$pagamento_id}");
        }
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
// CORREÇÃO: Garantir que PIX seja SEMPRE gerado quando necessário
$pix_inexistente = empty($pagamento['pix_qrcode_data']);
$pix_expirado = $pagamento['pix_expires_at'] && strtotime($pagamento['pix_expires_at']) <= time();
$pix_sem_expiracao = empty($pagamento['pix_expires_at']);

$deve_gerar_pix = empty($erro) && ($pagamento['status'] !== 'pago') && (
    $pix_inexistente || 
    $pix_expirado || 
    $pix_sem_expiracao ||
    $pagamento_criado_agora
);

// Debug detalhado das condições
if ($debug_mode) {
    error_log("PAGAMENTO DEBUG: Verificando condições para gerar PIX:");
    error_log("PAGAMENTO DEBUG: - erro vazio: " . (empty($erro) ? 'SIM' : 'NÃO'));
    error_log("PAGAMENTO DEBUG: - status não é 'pago': " . ($pagamento['status'] !== 'pago' ? 'SIM' : 'NÃO') . " (status atual: {$pagamento['status']})");
    error_log("PAGAMENTO DEBUG: - pix_inexistente: " . ($pix_inexistente ? 'SIM' : 'NÃO'));
    error_log("PAGAMENTO DEBUG: - pix_expirado: " . ($pix_expirado ? 'SIM' : 'NÃO'));
    error_log("PAGAMENTO DEBUG: - pix_sem_expiracao: " . ($pix_sem_expiracao ? 'SIM' : 'NÃO'));
    error_log("PAGAMENTO DEBUG: - pagamento_criado_agora: " . ($pagamento_criado_agora ? 'SIM' : 'NÃO'));
    error_log("PAGAMENTO DEBUG: - expires_at: " . ($pagamento['pix_expires_at'] ?? 'NULL'));
    error_log("PAGAMENTO DEBUG: - qrcode_data presente: " . (!empty($pagamento['pix_qrcode_data']) ? 'SIM' : 'NÃO'));
    if ($pagamento['pix_expires_at']) {
        $exp_time = strtotime($pagamento['pix_expires_at']);
        $now_time = time();
        error_log("PAGAMENTO DEBUG: - expires_at timestamp: {$exp_time}, now: {$now_time}");
    }
    error_log("PAGAMENTO DEBUG: - RESULTADO deve_gerar_pix: " . ($deve_gerar_pix ? 'SIM' : 'NÃO'));
}

if ($deve_gerar_pix) {
    // SISTEMA DE GERAÇÃO PIX OTIMIZADO (SEM DELAYS BLOQUEANTES)
    $pix_gerado_com_sucesso = false;
    $erro_geracao = '';
    
    try {
        if ($debug_mode) {
            error_log("PAGAMENTO DEBUG: Iniciando geração de PIX otimizada");
        }
        
        // Gerar novo PIX sempre com TXID único (máximo 35 caracteres para EFI)
        $timestamp = date('YmdHis'); // 14 caracteres
        $inscricao_padded = str_pad($inscricao_id, 4, '0', STR_PAD_LEFT); // 4 caracteres
        $random_suffix = strtoupper(substr(md5(uniqid() . microtime()), 0, 3)); // 3 caracteres
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
            // Pré-carregar token EFI para acelerar a chamada (sem bloquear)
            if (!isset($_SESSION['efi_token']) || !isset($_SESSION['efi_token_expires']) || 
                time() >= $_SESSION['efi_token_expires'] - 300) {
                if ($debug_mode) {
                    error_log("PAGAMENTO DEBUG: Token EFI não existe ou expirado, obtendo novo...");
                }
            }
            
            // Usar EFI Bank com função de alto nível (tentativa única, sem retry com sleep)
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
                
                // PIX gerado com sucesso!
                $pix_gerado_com_sucesso = true;
                $novo_pix_gerado = true;
                
                if ($debug_mode) {
                    error_log("PAGAMENTO DEBUG: PIX gerado com sucesso - Payload: " . substr($resultado_pix['pix_qrcode_data'] ?? '', 0, 50) . "...");
                }
            } else {
                $erro_geracao = $resultado_pix['erro'] ?? 'Erro desconhecido na API EFI';
                
                if ($debug_mode) {
                    error_log("PAGAMENTO DEBUG: Falha na geração PIX - resultado_pix: " . print_r($resultado_pix, true));
                }
            }
        } else {
            // Configuração inválida - log detalhado
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
        $erro_geracao = "Exceção - " . $e->getMessage();
        error_log("PAGAMENTO ERRO: Exceção na geração PIX: " . $e->getMessage());
    }
    
    // Se não conseguiu gerar PIX mas não há erro de configuração
    if (!$pix_gerado_com_sucesso && empty($erro) && !empty($erro_geracao)) {
        error_log("ERRO: EFI Bank falhou ao gerar PIX - Erro: {$erro_geracao}");
        error_log("ERRO: Inscricao ID: {$inscricao_id} | Participante ID: {$participante_logado['id']} | Valor: {$evento['valor']}");
        error_log("ERRO: TXID: {$txid} | Tamanho TXID: " . strlen($txid));
        
        // NÃO definir $erro aqui para permitir que o usuário tente novamente com F5
        // $erro = "Erro temporário ao gerar PIX. Recarregue a página para tentar novamente.";
        
        if ($debug_mode) {
            error_log("PAGAMENTO DEBUG: Erro na geração PIX: {$erro_geracao}");
            error_log("PAGAMENTO DEBUG: Tentando gerar PIX novamente em próximas visitas...");
        }
    }
}

// Calcular tempo restante para expiração
$tempo_expiracao = null;
if ($pagamento['pix_expires_at']) {
    $expira_timestamp = strtotime($pagamento['pix_expires_at']);
    $agora = time();
    $tempo_expiracao = max(0, $expira_timestamp - $agora);
    
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Calculando tempo expiração:");
        error_log("PAGAMENTO DEBUG: - pix_expires_at: {$pagamento['pix_expires_at']}");
        error_log("PAGAMENTO DEBUG: - timestamp expira: {$expira_timestamp}");
        error_log("PAGAMENTO DEBUG: - timestamp agora: {$agora}");
        error_log("PAGAMENTO DEBUG: - tempo_expiracao segundos: {$tempo_expiracao}");
        error_log("PAGAMENTO DEBUG: - pix_qrcode_data presente: " . (!empty($pagamento['pix_qrcode_data']) ? 'SIM' : 'NÃO'));
    }
} else {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: pix_expires_at está vazio - PIX pode não ter sido gerado");
    }
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
                                <canvas id="qr-canvas-pix"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Loading do PIX em geração -->
                    <div class="qr-code-loading" id="qr-loading">
                        <div class="loading-spinner-pix"></div>
                        <p><strong>🔄 Gerando código PIX...</strong></p>
                        <p><small>Processando sua solicitação de pagamento. Este processo foi otimizado e deve levar apenas alguns segundos.</small></p>
                        
                        <div id="loading-progress" style="margin: 15px 0;">
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">
                                <span id="loading-step">Conectando com servidor de pagamentos...</span>
                            </div>
                            <div style="width: 100%; background-color: #e9ecef; border-radius: 10px; height: 4px;">
                                <div id="progress-bar" style="height: 100%; background-color: #007bff; border-radius: 10px; width: 20%; transition: width 0.5s;"></div>
                            </div>
                        </div>
                        
                        <button type="button" onclick="window.location.reload()" class="btn-reload" style="margin-top: 10px; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; display: none;" id="btn-manual-reload">
                            🔄 Atualizar Página
                        </button>
                        
                        <div style="font-size: 11px; color: #6c757d; margin-top: 10px;">
                            💡 Se o QR Code não aparecer automaticamente, use o botão acima
                        </div>
                    </div>
                    
                    <script>
                        // Simular progresso de carregamento
                        setTimeout(function() {
                            const step = document.getElementById('loading-step');
                            const bar = document.getElementById('progress-bar');
                            if (step && bar) {
                                step.textContent = 'Gerando código PIX seguro...';
                                bar.style.width = '60%';
                            }
                        }, 2000);
                        
                        setTimeout(function() {
                            const step = document.getElementById('loading-step');
                            const bar = document.getElementById('progress-bar');
                            if (step && bar) {
                                step.textContent = 'Finalizando processo...';
                                bar.style.width = '90%';
                            }
                        }, 4000);
                        
                        // Mostrar botão de reload manual após 8 segundos
                        setTimeout(function() {
                            const btnReload = document.getElementById('btn-manual-reload');
                            if (btnReload) {
                                btnReload.style.display = 'inline-block';
                            }
                        }, 8000);
                    </script>
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
                        <small>O código PIX não foi gerado corretamente. <?= $debug_mode ? 'Verifique as configurações EFI Bank no painel administrativo.' : 'A página será recarregada automaticamente em alguns segundos...' ?></small>
                        
                        <div style="margin-top: 15px;">
                            <button type="button" onclick="window.location.reload()" class="btn-reload" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                🔄 Tentar Novamente
                            </button>
                        </div>
                    </div>
                    
                    <!-- Script para verificar se PIX foi gerado e recarregar se necessário -->
                    <script>
                        let tentativas = 0;
                        const maxTentativas = 3;
                        
                        function verificarPixGerado() {
                            tentativas++;
                            
                            // Fazer requisição AJAX para verificar se PIX foi gerado
                            fetch(window.SITE_URL + '/api/verificar_pagamento.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    inscricao_id: window.INSCRICAO_ID,
                                    pagamento_id: window.PAGAMENTO_ID,
                                    verificar_pix_apenas: true
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                console.log('Verificação PIX:', data);
                                
                                // Se PIX foi gerado ou pagamento foi processado, recarregar
                                if (data.pix_gerado || data.pago) {
                                    console.log('PIX foi gerado, recarregando página...');
                                    window.location.reload();
                                } else if (tentativas < maxTentativas) {
                                    // Tentar novamente após 2 segundos
                                    setTimeout(verificarPixGerado, 2000);
                                } else {
                                    // Após 3 tentativas, recarregar a página completa
                                    console.log('Após', maxTentativas, 'tentativas, recarregando página...');
                                    window.location.reload();
                                }
                            })
                            .catch(error => {
                                console.error('Erro na verificação PIX:', error);
                                // Em caso de erro, recarregar após 3 segundos
                                if (tentativas <= 1) {
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 3000);
                                }
                            });
                        }
                        
                        // Iniciar verificação após 2 segundos
                        setTimeout(verificarPixGerado, 2000);
                    </script>
                <?php endif; ?>

                <div class="instrucoes-pix" style="margin-bottom: 8px;">
                    <h4>Como pagar:</h4>
        <ol>
            <li>Abra o app do seu banco</li>
            <li>Escolha a opção PIX</li>
                        <li>Escaneie o QR Code ou cole o código copiado</li>
            <li>Confirme o pagamento</li>
                        <li>Aguarde a confirmação automática</li>
        </ol>
    </div>

                <div class="verificacao-status" style="margin: 8px 0 0 0;">
                    <button type="button" class="btn-verificar" onclick="verificarPagamento(this, true)">
                        🔄 Verificar Pagamento
                    </button>
                    <div class="loading-spinner" id="loading-spinner"></div>
                    <div id="status-verificacao" style="text-align: center; margin-top: 10px; min-height: 20px;"></div>
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

<script src="<?= SITE_URL ?>/assets/js/qr-simple.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
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
