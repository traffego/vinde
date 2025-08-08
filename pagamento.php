<?php
require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

// Debug mode para desenvolvimento
$debug_mode = is_debug_enabled() || isset($_GET['debug']);

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
$deve_gerar_pix = ($pagamento['status'] !== 'pago') && (
    empty($pagamento['pix_qrcode_data']) || 
    ($pagamento['pix_expires_at'] && strtotime($pagamento['pix_expires_at']) < time()) ||
    true // Sempre gerar novo PIX quando não está pago (conforme solicitado)
);

if ($deve_gerar_pix) {
    
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
    }
}

// Calcular tempo restante para expiração
$tempo_expiracao = null;
if ($pagamento['pix_expires_at']) {
    $expira_timestamp = strtotime($pagamento['pix_expires_at']);
    $agora = time();
    $tempo_expiracao = max(0, $expira_timestamp - $agora);
}

// Variável já inicializada no início do arquivo

// Debug: verificar se chegou até aqui
if ($debug_mode) {
    error_log("PAGAMENTO DEBUG: Chegou até obter_cabecalho - Evento: " . $evento['nome']);
}

obter_cabecalho('Pagamento - ' . $evento['nome']);
?>

<style>
/* Estilos específicos da página de pagamento */
.pagamento-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.pagamento-header {
    text-align: center;
    margin-bottom: 40px;
}

.pagamento-header h1 {
    color: var(--cor-primaria);
    margin-bottom: 10px;
}

.pagamento-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 40px;
    align-items: start;
}

@media (max-width: 768px) {
    .pagamento-layout {
        grid-template-columns: 1fr;
        gap: 30px;
    }
}

.pagamento-main {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.pagamento-sidebar {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    height: fit-content;
}

.pix-section {
    text-align: center;
}

.qr-code-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    margin: 20px 0;
    display: inline-block;
}

.qr-code-container img {
    display: block;
    margin: 0 auto;
    max-width: 250px;
    width: 100%;
}

.pix-code {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    word-break: break-all;
    word-wrap: break-word;
    white-space: pre-wrap;
    line-height: 1.4;
    margin: 15px 0;
    max-height: 120px;
    overflow-y: auto;
    text-align: left;
    user-select: all;
    cursor: text;
}

.btn-copiar {
    background: var(--cor-primaria);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    margin: 10px 5px;
    transition: all 0.3s ease;
}

.btn-copiar:hover {
    background: var(--cor-primaria-dark);
}

.btn-copiar.copiado {
    background: #28a745;
}

.pix-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin: 15px 0;
}

.pix-code-section {
    margin: 20px 0;
}

.pix-code-section p {
    margin-bottom: 10px;
    color: #495057;
    font-weight: 500;
}

.pix-code:hover {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .pix-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .pix-actions button {
        width: 100%;
        max-width: 250px;
        margin: 5px 0 !important;
    }
    
    .pix-code {
        font-size: 10px;
        padding: 12px;
    }
}

.timer-expiracao {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
    text-align: center;
}

.timer-expiracao.expirado {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.resumo-pagamento h3 {
    color: var(--cor-primaria);
    margin-bottom: 20px;
    font-size: 18px;
}

.resumo-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.resumo-item:last-child {
    border-bottom: none;
    font-weight: 600;
    font-size: 18px;
    color: var(--cor-primaria);
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pendente {
    background: #fff3cd;
    color: #856404;
}

.status-aprovada {
    background: #d4edda;
    color: #155724;
}

.instrucoes-pix {
    background: #e7f3ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.instrucoes-pix h4 {
    color: #004085;
    margin-bottom: 15px;
}

.instrucoes-pix ol {
    margin: 0;
    padding-left: 20px;
}

.instrucoes-pix li {
    margin-bottom: 8px;
    color: #004085;
}

.verificacao-status {
    text-align: center;
    margin: 30px 0;
}

.btn-verificar {
    background: #17a2b8;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-verificar:hover {
    background: #138496;
}

.loading-spinner {
    display: none;
    margin: 20px auto;
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--cor-primaria);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<main class="pagamento-container">
    <div class="pagamento-header">
        <h1>Finalizar Pagamento</h1>
        <p>Complete seu pagamento para confirmar a inscrição</p>
        </div>

    <?php if ($erro): ?>
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

                <?php if (!empty($pagamento['pix_qrcode_url'])): ?>
                    <div class="qr-code-container">
                        <img src="<?= htmlspecialchars($pagamento['pix_qrcode_url']) ?>" 
                             alt="QR Code PIX" 
                             id="qr-code-img"
                             onerror="document.getElementById('qr-code-img').style.display='none';document.getElementById('qr-canvas-pix').style.display='block';gerarQrPixCanvas();">
                    </div>
                <?php endif; ?>

                <?php if (!empty($pagamento['pix_qrcode_data'])): ?>
                    <div class="qr-code-container" style="display: <?= empty($pagamento['pix_qrcode_url']) ? 'inline-block' : 'none' ?>;" id="qr-canvas-wrapper">
                        <canvas id="qr-canvas-pix"></canvas>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pagamento['pix_qrcode_data'])): ?>
                    <div class="pix-code-section">
                        <p><strong>Ou copie o código PIX:</strong></p>
                        <div class="pix-code" id="pix-code" title="Clique para selecionar todo o código">
                            <?= htmlspecialchars(trim($pagamento['pix_qrcode_data'])) ?>
                        </div>
                        
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
                            <button type="button" class="btn-copiar" onclick="copiarPix(this)" id="btn-copiar-pix">
                                📋 Copiar Código PIX
                            </button>
                            <button type="button" class="btn-copiar" onclick="validarCodigoPix()" style="background: #17a2b8; margin-left: 10px;">
                                ✅ Validar Código
                            </button>
                        </div>
                        <div id="pix-validation-result" style="margin-top: 10px; font-size: 12px;"></div>
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

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
// Timer de expiração
<?php if ($tempo_expiracao !== null): ?>
let tempoRestante = <?= $tempo_expiracao ?>;

function atualizarTimer() {
    if (tempoRestante <= 0) {
        document.getElementById('timer-container').className = 'timer-expiracao expirado';
        document.getElementById('timer-text').innerHTML = '⚠️ Código PIX expirado - Recarregue a página para gerar um novo';
        return;
    }
    
    const minutos = Math.floor(tempoRestante / 60);
    const segundos = tempoRestante % 60;
    document.getElementById('countdown').textContent = 
        String(minutos).padStart(2, '0') + ':' + String(segundos).padStart(2, '0');
    
    tempoRestante--;
}

// Atualizar timer a cada segundo
setInterval(atualizarTimer, 1000);
<?php endif; ?>

// Função para copiar código PIX (melhorada)
function copiarPix(btn) {
    const pixEl = document.getElementById('pix-code');
    if (!pixEl) {
        alert('Código PIX não encontrado');
        return;
    }
    
    let pixCode = (pixEl.textContent || pixEl.innerText || '').trim();
    
    // Remover quebras de linha e espaços extras
    pixCode = pixCode.replace(/\s+/g, '').replace(/\r?\n|\r/g, '');
    
    if (!pixCode || pixCode.length < 50) {
        alert('Código PIX inválido ou muito curto');
        return;
    }
    
    // Validação básica do formato PIX
    if (!pixCode.startsWith('00020101') && !pixCode.startsWith('00020126')) {
        alert('Código PIX com formato inválido');
        return;
    }
    
    // Tentar copiar usando API moderna
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pixCode)
            .then(() => {
                feedbackCopiado(btn);
                console.log('PIX copiado:', pixCode.substring(0, 50) + '...');
            })
            .catch(err => {
                console.error('Erro ao copiar:', err);
                copiarPixFallback(pixCode, btn);
            });
    } else {
        copiarPixFallback(pixCode, btn);
    }
}

// Fallback para navegadores antigos
function copiarPixFallback(pixCode, btn) {
    const area = document.createElement('textarea');
    area.value = pixCode;
    area.setAttribute('readonly', '');
    area.style.position = 'absolute';
    area.style.left = '-9999px';
    area.style.opacity = '0';
    document.body.appendChild(area);
    
    try {
        area.select();
        area.setSelectionRange(0, 99999); // Para mobile
        const success = document.execCommand('copy');
        if (success) {
            feedbackCopiado(btn);
        } else {
            alert('Não foi possível copiar automaticamente. Por favor, selecione e copie manualmente.');
        }
    } catch (err) {
        console.error('Erro no fallback:', err);
        alert('Erro ao copiar. Tente selecionar o código manualmente.');
    } finally {
        document.body.removeChild(area);
    }
}

function feedbackCopiado(btn) {
    if (!btn) return;
    const textoOriginal = btn.textContent;
    btn.textContent = '✅ Copiado!';
    btn.classList.add('copiado');
    setTimeout(function() {
        btn.textContent = textoOriginal;
        btn.classList.remove('copiado');
    }, 2000);
}

// Função para validar código PIX
function validarCodigoPix() {
    const pixEl = document.getElementById('pix-code');
    const resultEl = document.getElementById('pix-validation-result');
    
    if (!pixEl || !resultEl) return;
    
    let pixCode = (pixEl.textContent || pixEl.innerText || '').trim();
    pixCode = pixCode.replace(/\s+/g, '').replace(/\r?\n|\r/g, '');
    
    // Limpar resultado anterior
    resultEl.innerHTML = '';
    
    if (!pixCode) {
        resultEl.innerHTML = '<span style="color: #dc3545;">❌ Código PIX não encontrado</span>';
        return;
    }
    
    // Validações básicas
    const validacoes = [];
    
    // 1. Tamanho mínimo
    if (pixCode.length < 50) {
        validacoes.push('❌ Código muito curto (mínimo 50 caracteres)');
    } else {
        validacoes.push('✅ Tamanho adequado (' + pixCode.length + ' caracteres)');
    }
    
    // 2. Formato inicial
    if (pixCode.startsWith('00020101') || pixCode.startsWith('00020126')) {
        validacoes.push('✅ Formato inicial correto');
    } else {
        validacoes.push('❌ Formato inicial inválido');
    }
    
    // 3. Verificar se contém identificador PIX
    if (pixCode.includes('BR.GOV.BCB.PIX')) {
        validacoes.push('✅ Identificador PIX encontrado');
    } else {
        validacoes.push('❌ Identificador PIX não encontrado');
    }
    
    // 4. Verificar país (BR)
    if (pixCode.includes('5802BR')) {
        validacoes.push('✅ Código de país correto (BR)');
    } else {
        validacoes.push('❌ Código de país não encontrado');
    }
    
    // 5. CRC (últimos 4 caracteres devem ser hexadecimais)
    const crc = pixCode.slice(-4);
    if (/^[0-9A-F]{4}$/i.test(crc)) {
        validacoes.push('✅ CRC com formato correto (' + crc + ')');
    } else {
        validacoes.push('❌ CRC com formato inválido');
    }
    
    // Mostrar resultados
    const temErros = validacoes.some(v => v.includes('❌'));
    const corGeral = temErros ? '#dc3545' : '#28a745';
    const statusGeral = temErros ? '⚠️ Código com problemas' : '✅ Código PIX válido';
    
    resultEl.innerHTML = `
        <div style="color: ${corGeral}; font-weight: bold; margin-bottom: 5px;">
            ${statusGeral}
        </div>
        <div style="font-size: 11px; line-height: 1.3;">
            ${validacoes.join('<br>')}
        </div>
    `;
}

// Função para verificar status do pagamento
function verificarPagamento() {
    const spinner = document.getElementById('loading-spinner');
    const btn = event.target;
    
    spinner.style.display = 'block';
    btn.disabled = true;
    
    fetch('<?= SITE_URL ?>/api/verificar_pagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            inscricao_id: <?= $inscricao_id ?>,
            pagamento_id: <?= $pagamento['id'] ?? 'null' ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        spinner.style.display = 'none';
        btn.disabled = false;
        
        if (data.success && data.pago) {
            // Pagamento confirmado - redirecionar
            window.location.href = '<?= SITE_URL ?>/confirmacao.php?inscricao=<?= $inscricao_id ?>';
        } else {
            // Mostrar resultado
            alert(data.message || 'Pagamento ainda não foi identificado. Tente novamente em alguns instantes.');
        }
    })
    .catch(error => {
        spinner.style.display = 'none';
        btn.disabled = false;
        alert('Erro ao verificar pagamento. Tente novamente.');
    });
}

// Verificação automática a cada 30 segundos
setInterval(function() {
    verificarPagamento();
}, 30000);

// Geração local do QR Code PIX a partir do payload
function gerarQrPixCanvas() {
    const canvas = document.getElementById('qr-canvas-pix');
    if (!canvas) return;
    const payload = `<?= isset($pagamento['pix_qrcode_data']) ? addslashes($pagamento['pix_qrcode_data']) : '' ?>`;
    if (!payload) return;
    QRCode.toCanvas(canvas, payload, {
        width: 250,
        margin: 2,
        color: { dark: '#000000', light: '#FFFFFF' }
    }, function(err){ /* noop */ });
}

// Se não houver imagem de QR ou estiver oculta, desenhar o canvas
document.addEventListener('DOMContentLoaded', function() {
    const img = document.getElementById('qr-code-img');
    const shouldDraw = !img || img.style.display === 'none';
    if (shouldDraw) {
        const wrapper = document.getElementById('qr-canvas-wrapper');
        if (wrapper) wrapper.style.display = 'inline-block';
        gerarQrPixCanvas();
    }
    
    // Adicionar funcionalidade de clique no código PIX para seleção
    const pixCodeEl = document.getElementById('pix-code');
    if (pixCodeEl) {
        pixCodeEl.addEventListener('click', function() {
            // Selecionar todo o texto do código PIX
            if (window.getSelection && document.createRange) {
                const range = document.createRange();
                range.selectNodeContents(pixCodeEl);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            } else if (document.body.createTextRange) {
                // Fallback para IE
                const range = document.body.createTextRange();
                range.moveToElementText(pixCodeEl);
                range.select();
            }
            
            // Feedback visual
            pixCodeEl.style.backgroundColor = '#e3f2fd';
            setTimeout(() => {
                pixCodeEl.style.backgroundColor = '#f8f9fa';
            }, 1000);
        });
        
        // Validar código automaticamente ao carregar
        setTimeout(validarCodigoPix, 500);
    }
});
</script>

<?php obter_rodape(); ?> 