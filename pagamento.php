<?php
require_once 'includes/init.php';

// Debug mode para desenvolvimento
$debug_mode = is_debug_enabled() || isset($_GET['debug']);

$participante_id = $_GET['participante'] ?? '';
$erro = '';
$sucesso = '';

// Debug inicial
if ($debug_mode) {
    error_log("PAGAMENTO DEBUG: Participante ID = {$participante_id}");
}

// Validar participante_id
if (empty($participante_id) || !is_numeric($participante_id)) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: ID inválido");
    }
    obter_cabecalho('Pagamento - ID Inválido');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>ID de Participante Inválido</h1>
            <p>O ID do participante não foi fornecido ou é inválido.</p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Buscar dados do participante e evento
$participante = [];
$evento = [];
$pagamento = [];

try {
    $dados = buscar_um("
        SELECT p.*, e.*, 
               pag.id as pagamento_id, pag.valor, pag.status as pagamento_status,
               pag.pix_txid, pag.pix_loc_id, pag.pix_qrcode_data, pag.pix_qrcode_url, 
               pag.pix_expires_at, pag.criado_em as pagamento_criado
        FROM participantes p
        JOIN eventos e ON p.evento_id = e.id
        LEFT JOIN pagamentos pag ON p.id = pag.participante_id
        WHERE p.id = ?
    ", [$participante_id]);
    
    if ($dados) {
        $participante = $dados;
        $evento = $dados;
        $pagamento = $dados;
        
        if ($debug_mode) {
            error_log("PAGAMENTO DEBUG: Dados encontrados - Nome: {$participante['nome']}, Evento: {$evento['nome']}");
        }
    } else {
        if ($debug_mode) {
            error_log("PAGAMENTO DEBUG: Nenhum dado encontrado para participante {$participante_id}");
        }
    }
    
} catch (Exception $e) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Erro ao buscar dados - " . $e->getMessage());
    }
    $erro = 'Erro interno ao buscar dados do participante.';
}

// Verificar se participante foi encontrado
if (!$participante || empty($participante['nome'])) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Participante não encontrado");
    }
    obter_cabecalho('Participante não encontrado');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Participante não encontrado</h1>
            <p>Os dados do participante ID <?= htmlspecialchars($participante_id) ?> não foram encontrados.</p>
            <?php if ($debug_mode): ?>
                <div class="debug-info" style="background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px;">
                    <h4>Debug Info:</h4>
                    <p><strong>Participante ID:</strong> <?= htmlspecialchars($participante_id) ?></p>
                    <p><strong>Verificar:</strong> <a href="admin/debug_participante.php?id=<?= $participante_id ?>" target="_blank">Debug Participante</a></p>
                </div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Verificar se evento foi encontrado
if (!$evento || empty($evento['nome'])) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Evento não encontrado para participante {$participante_id}");
    }
    obter_cabecalho('Evento não encontrado');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Evento não encontrado</h1>
            <p>O evento associado ao participante não foi encontrado.</p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Verificar/criar pagamento se necessário
if (empty($pagamento['pagamento_id'])) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Pagamento não encontrado, criando...");
    }
    
    try {
        // Criar pagamento
        $resultado = executar("INSERT INTO pagamentos (participante_id, valor, status, criado_em) VALUES (?, ?, 'pendente', ?)", 
            [$participante_id, $evento['valor'], date('Y-m-d H:i:s')]);
        
        if ($resultado) {
            // Buscar pagamento criado
            $pagamento_novo = buscar_um("SELECT * FROM pagamentos WHERE participante_id = ? ORDER BY id DESC LIMIT 1", [$participante_id]);
            if ($pagamento_novo) {
                $pagamento = array_merge($pagamento, $pagamento_novo);
                $pagamento['pagamento_id'] = $pagamento_novo['id'];
                $pagamento['pagamento_status'] = $pagamento_novo['status'];
                $sucesso = 'Pagamento criado com sucesso!';
                
                if ($debug_mode) {
                    error_log("PAGAMENTO DEBUG: Pagamento criado com ID: {$pagamento['pagamento_id']}");
                }
            }
        }
    } catch (Exception $e) {
        if ($debug_mode) {
            error_log("PAGAMENTO DEBUG: Erro ao criar pagamento - " . $e->getMessage());
        }
        $erro = 'Erro ao criar registro de pagamento.';
    }
}

// Verificar se ainda não tem pagamento
if (empty($pagamento['pagamento_id'])) {
    obter_cabecalho('Erro no Pagamento');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Erro no Sistema de Pagamento</h1>
            <p>Não foi possível inicializar o pagamento. Entre em contato com o suporte.</p>
            <?php if ($debug_mode): ?>
                <div class="debug-info" style="background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px;">
                    <h4>Debug Info:</h4>
                    <p><strong>Erro:</strong> <?= htmlspecialchars($erro) ?></p>
                    <p><strong>Participante:</strong> <?= htmlspecialchars($participante['nome']) ?></p>
                    <p><strong>Evento:</strong> <?= htmlspecialchars($evento['nome']) ?></p>
                </div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Se já foi pago, redirecionar para confirmação
if ($pagamento['pagamento_status'] === 'pago') {
    redirecionar(SITE_URL . '/confirmacao.php?participante=' . $participante_id);
}

// Se é evento gratuito, redirecionar para confirmação
if ($pagamento['valor'] <= 0) {
    redirecionar(SITE_URL . '/confirmacao.php?participante=' . $participante_id);
}

// Verificar se precisa gerar novo PIX via EFI Bank
$precisa_gerar_pix = false;

if (efi_esta_ativo()) {
    // Se EFI Bank está ativo, verificar se já tem PIX válido
    if (empty($pagamento['pix_txid']) || empty($pagamento['pix_qrcode_data'])) {
        $precisa_gerar_pix = true;
    } else {
        // Verificar se não expirou
        if (!empty($pagamento['pix_expires_at'])) {
            $expires_timestamp = strtotime($pagamento['pix_expires_at']);
            if (time() > $expires_timestamp) {
                $precisa_gerar_pix = true;
            }
        }
    }
    
    // Gerar novo PIX via EFI Bank se necessário
    if ($precisa_gerar_pix) {
        $dados_pix = [
            'valor' => $pagamento['valor'],
            'descricao' => "Inscrição: " . $evento['nome'],
            'participante_id' => $participante_id,
            'evento_nome' => $evento['nome'],
            'nome_pagador' => $participante['nome'],
            'cpf_pagador' => $participante['cpf'] ?? '',
            'expiracao' => 3600 // 1 hora
        ];
        
        $resultado_efi = efi_criar_pix_completo($dados_pix);
        
        if ($resultado_efi && isset($resultado_efi['sucesso'])) {
            // Atualizar dados no banco
            $dados_update = [
                'pix_txid' => $resultado_efi['pix_txid'],
                'pix_loc_id' => $resultado_efi['pix_loc_id'],
                'pix_qrcode_data' => $resultado_efi['pix_qrcode_data'],
                'pix_qrcode_url' => $resultado_efi['pix_qrcode_url'],
                'pix_expires_at' => $resultado_efi['pix_expires_at']
            ];
            
            if (executar("UPDATE pagamentos SET pix_txid = ?, pix_loc_id = ?, pix_qrcode_data = ?, pix_qrcode_url = ?, pix_expires_at = ? WHERE id = ?", 
                [$dados_update['pix_txid'], $dados_update['pix_loc_id'], $dados_update['pix_qrcode_data'], 
                 $dados_update['pix_qrcode_url'], $dados_update['pix_expires_at'], $pagamento['pagamento_id']])) {
                
                // Atualizar dados na memória
                $pagamento = array_merge($pagamento, $dados_update);
                $sucesso = 'PIX gerado com sucesso via EFI Bank!';
            } else {
                $erro = 'PIX gerado mas houve erro ao salvar no banco de dados.';
            }
        } else {
            $erro_msg = $resultado_efi['erro'] ?? 'Erro desconhecido ao gerar PIX via EFI Bank';
            $erro = "Erro EFI Bank: " . $erro_msg . " - Entre em contato com o suporte.";
        }
    }
} else {
    // EFI Bank não está ativo - verificar configurações
    $erro = 'Sistema de pagamento PIX não configurado. ';
    
    // Verificar configurações específicas para orientar o administrador
    $config_efi = obter_configuracoes_efi();
    if (empty($config_efi['efi_client_id']) || empty($config_efi['efi_client_secret'])) {
        $erro .= 'Credenciais EFI Bank não configuradas. ';
    }
    if (empty($config_efi['efi_certificado_path']) || !file_exists($config_efi['efi_certificado_path'])) {
        $erro .= 'Certificado EFI Bank não encontrado. ';
    }
    if (empty($config_efi['efi_pix_key'])) {
        $erro .= 'Chave PIX não configurada. ';
    }
    if ($config_efi['efi_ativo'] !== '1') {
        $erro .= 'EFI Bank não está ativo. ';
    }
    
    $erro .= 'Entre em contato com o suporte técnico.';
    
    error_log("EFI Bank não está ativo - Detalhes: " . json_encode($config_efi));
}

// Processar confirmação manual de pagamento (para teste)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pagamento'])) {
    if (verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        // Em produção, isso seria feito via webhook do banco
        $sucesso_update = executar("UPDATE pagamentos SET status = 'pago', pago_em = ? WHERE id = ?", 
            [date('Y-m-d H:i:s'), $pagamento['pagamento_id']]);
        
        if ($sucesso_update) {
            executar("UPDATE participantes SET status = 'pago' WHERE id = ?", [$participante_id]);
            
            registrar_log('pagamento_confirmado', "Participante: {$participante['nome']} - Valor: R$ {$pagamento['valor']} - TXID: " . ($pagamento['pix_txid'] ?? 'N/A'));
            
            // Simular envio de WhatsApp
            $mensagem_whatsapp = "🎉 Pagamento confirmado!

Olá {$participante['nome']},

Seu pagamento foi confirmado com sucesso!

📅 Evento: {$evento['nome']}
💰 Valor: R$ " . number_format($pagamento['valor'], 2, ',', '.') . "
📍 Local: {$evento['local']}
📅 Data: " . formatar_data($evento['data_inicio']) . "

🎫 Acesse o link para ver sua confirmação:
" . SITE_URL . "/confirmacao.php?participante={$participante_id}

Nos vemos lá! 🙏";

            simular_whatsapp($participante['whatsapp'], $mensagem_whatsapp);
            
            // Redirecionar para página de confirmação
            redirecionar(SITE_URL . '/confirmacao.php?participante=' . $participante_id);
        } else {
            $erro = 'Erro ao confirmar pagamento. Tente novamente.';
        }
    } else {
        $erro = 'Token de segurança inválido.';
    }
}

obter_cabecalho('Pagamento PIX - ' . $evento['nome'], 'pagamento');

// Verificar se o pagamento expirou
$expirou = false;
$tempo_restante = 0;
if ($pagamento['pix_expires_at']) {
    $expira_em = strtotime($pagamento['pix_expires_at']);
    $expirou = $expira_em < time();
    $tempo_restante = max(0, $expira_em - time());
}
?>

<main class="pagamento-main">
    <!-- Breadcrumb -->
    <nav class="pagamento-breadcrumb">
        <div class="container">
            <div class="breadcrumb-content">
                <a href="<?= SITE_URL ?>" class="breadcrumb-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                    </svg>
                    Eventos
                </a>
                <span class="breadcrumb-separator">›</span>
                <a href="<?= SITE_URL ?>/evento/<?= $evento['id'] ?>" class="breadcrumb-link"><?= htmlspecialchars($evento['nome']) ?></a>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current">Pagamento</span>
            </div>
        </div>
    </nav>
    
    <!-- Alertas de Erro e Sucesso -->
    <?php if (!empty($erro) || !empty($sucesso)): ?>
    <div class="container">
        <div class="alert-section">
            <?php if (!empty($erro)): ?>
                <div class="alert alert-error">
                    <div class="alert-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div class="alert-content">
                        <h4>Erro no Sistema de Pagamento</h4>
                        <p><?= htmlspecialchars($erro) ?></p>
                        <?php if ($debug_mode): ?>
                            <div class="debug-details">
                                <h5>Debug Info:</h5>
                                <p><strong>EFI Ativo:</strong> <?= efi_esta_ativo() ? 'Sim' : 'Não' ?></p>
                                <p><strong>Participante ID:</strong> <?= $participante_id ?></p>
                                <p><strong>Pagamento ID:</strong> <?= $pagamento['pagamento_id'] ?? 'N/A' ?></p>
                                <p><strong>Debug Links:</strong> 
                                    <a href="admin/debug_participante.php?id=<?= $participante_id ?>" target="_blank">Participante</a> | 
                                    <a href="admin/debug_efi.php" target="_blank">EFI Bank</a> | 
                                    <a href="admin/debug_qr_payload.php" target="_blank">QR Payload</a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($sucesso)): ?>
                <div class="alert alert-success">
                    <div class="alert-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                        </svg>
                    </div>
                    <div class="alert-content">
                        <h4>Sucesso!</h4>
                        <p><?= htmlspecialchars($sucesso) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Header Premium do Pagamento -->
        <section class="pagamento-header-premium">
            <div class="pagamento-header-content">
                <div class="status-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <span>Aguardando Pagamento</span>
                </div>
                
                <h1>Finalize sua Inscrição</h1>
                
                <div class="evento-info-pagamento">
                    <div class="evento-details">
                        <h2><?= htmlspecialchars($evento['nome']) ?></h2>
                        <div class="details-grid">
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                <span><?= htmlspecialchars($participante['nome']) ?></span>
                            </div>
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 11H7v6h2v-6zm4 0h-2v6h2v-6zm4 0h-2v6h2v-6zm2-7h-3V2h-2v2H8V2H6v2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H3V9h14v11z"/>
                                </svg>
                                <span><?= formatar_data($evento['data_inicio']) ?></span>
                            </div>
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <span><?= htmlspecialchars($evento['local']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="valor-total-premium">
                        <span class="valor-label">Total a Pagar</span>
                        <span class="valor-amount">R$ <?= number_format($pagamento['valor'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo Principal -->
        <section class="pagamento-content-premium">
            <div class="pagamento-grid-premium">
                <!-- QR Code PIX Premium -->
                <div class="pix-card-premium">
                    <div class="pix-header-premium">
                        <div class="pix-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM7.07 18.28c.43-.9 3.05-1.78 4.93-1.78s4.51.88 4.93 1.78C15.57 19.36 13.86 20 12 20s-3.57-.64-4.93-1.72zm11.29-1.45c-1.43-1.74-4.9-2.33-6.36-2.33s-4.93.59-6.36 2.33C4.62 15.49 4 13.82 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8c0 1.82-.62 3.49-1.64 4.83z"/>
                            </svg>
                        </div>
                        <div class="pix-title">
                            <h3>Pagamento via PIX</h3>
                            <p>Aprovação instantânea e segura</p>
                        </div>
                    </div>
                    
                    <?php if (!$expirou && !empty($pagamento['pix_qrcode_url']) && !empty($pagamento['pix_qrcode_data'])): ?>
                    <div class="qr-display">
                        <div class="qr-container">
                            <img src="<?= $pagamento['pix_qrcode_url'] ?>" alt="QR Code PIX" class="qr-image">
                        </div>
                        
                        <div class="pix-steps">
                            <h4>Como pagar com PIX:</h4>
                            <div class="steps-list">
                                <div class="step">
                                    <span class="step-number">1</span>
                                    <span>Abra o app do seu banco</span>
                                </div>
                                <div class="step">
                                    <span class="step-number">2</span>
                                    <span>Toque em "Pagar com PIX"</span>
                                </div>
                                <div class="step">
                                    <span class="step-number">3</span>
                                    <span>Escaneie o QR Code</span>
                                </div>
                                <div class="step">
                                    <span class="step-number">4</span>
                                    <span>Confirme o pagamento</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Código PIX Copia e Cola -->
                    <div class="pix-copy-section">
                        <h4>Ou pague com Pix Copia e Cola:</h4>
                        <div class="copy-container">
                            <input type="text" id="pix-code" value="<?= htmlspecialchars($pagamento['pix_qrcode_data']) ?>" readonly>
                            <button onclick="copiarPixCode()" class="copy-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                                </svg>
                                Copiar
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="qr-expired">
                        <div class="expired-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L16.31 7.1C17.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>
                            </svg>
                        </div>
                        <h4>QR Code Expirado</h4>
                        <p>Este código PIX expirou. Clique no botão abaixo para gerar um novo.</p>
                        <button onclick="gerarNovoQR()" class="btn-premium btn-primary-premium">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 6v3l4-4-4-4v3c-4.42 0-8 3.58-8 8 0 1.57.46 3.03 1.24 4.26L6.7 14.8c-.45-.83-.7-1.79-.7-2.8 0-3.31 2.69-6 6-6zm6.76 1.74L17.3 9.2c.44.84.7 1.79.7 2.8 0 3.31-2.69 6-6 6v-3l-4 4 4 4v-3c4.42 0 8-3.58 8-8 0-1.57-.46-3.03-1.24-4.26z"/>
                            </svg>
                            Gerar Novo QR Code
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Informações e Status -->
                <div class="info-sidebar-premium">
                    <!-- Status do Pagamento -->
                    <div class="status-card-premium" id="status-card">
                        <div class="status-header">
                            <div class="status-icon" id="status-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                            </div>
                            <div class="status-text">
                                <h4 id="status-title">Aguardando Pagamento</h4>
                                <p id="status-message">Escaneie o QR Code para pagar</p>
                            </div>
                        </div>
                        
                        <?php if (!$expirou && $tempo_restante > 0): ?>
                        <div class="countdown-premium">
                            <div class="countdown-label">Tempo restante:</div>
                            <div class="countdown-timer" id="countdown-timer"><?= gmdate("i:s", $tempo_restante) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informações de Segurança -->
                    <div class="security-info-premium">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                            </svg>
                            Pagamento Seguro
                        </h4>
                        <ul>
                            <li>✅ Transação protegida pelo Banco Central</li>
                            <li>✅ Confirmação automática em até 2 minutos</li>
                            <li>✅ Dados criptografados e seguros</li>
                        </ul>
                    </div>
                    
                    <!-- Próximos Passos -->
                    <div class="next-steps-premium">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Após o Pagamento
                        </h4>
                        <ul>
                            <li>📧 Confirmação por email</li>
                            <li>📱 QR Code de acesso via WhatsApp</li>
                            <li>🎫 Ingresso digital disponível</li>
                        </ul>
                    </div>
                    
                    <!-- Suporte -->
                    <div class="support-card-premium">
                        <h4>Precisa de Ajuda?</h4>
                        <p>Entre em contato conosco</p>
                        <a href="https://wa.me/<?= WHATSAPP_CONTATO ?>?text=<?= urlencode('Olá! Preciso de ajuda com o pagamento do evento: ' . $evento['nome']) ?>" 
                           target="_blank" 
                           class="support-btn-premium">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893A11.821 11.821 0 0020.89 3.488"/>
                            </svg>
                            Falar Conosco
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<!-- JavaScript para funcionalidades interativas -->
<script>
// Dados do pagamento
const pagamentoData = {
    participanteId: <?= $participante_id ?>,
    txid: '<?= $pagamento['pix_txid'] ?? '' ?>',
    tempoRestante: <?= $tempo_restante ?>,
    expirou: <?= $expirou ? 'true' : 'false' ?>
};

// Copiar código PIX
function copiarPixCode() {
    const pixCode = document.getElementById('pix-code');
    pixCode.select();
    pixCode.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        mostrarNotificacao('✅ Código PIX copiado!', 'success');
    } catch (err) {
        mostrarNotificacao('❌ Erro ao copiar código', 'error');
    }
}

// Verificar status do pagamento
function verificarStatusPagamento() {
    if (pagamentoData.expirou) return;
    
    fetch('api/verificar_pagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            txid: pagamentoData.txid,
            participante_id: pagamentoData.participanteId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'paid') {
            // Pagamento confirmado
            atualizarStatusPagamento('pago', 'Pagamento Confirmado!', 'Redirecionando...');
            setTimeout(() => {
                window.location.href = 'confirmacao.php?participante=' + pagamentoData.participanteId;
            }, 2000);
        } else if (data.status === 'expired') {
            // Pagamento expirado
            atualizarStatusPagamento('expirado', 'QR Code Expirado', 'Gere um novo código');
            mostrarQRExpirado();
        }
    })
    .catch(error => {
        console.error('Erro ao verificar pagamento:', error);
    });
}

// Atualizar visual do status
function atualizarStatusPagamento(status, titulo, mensagem) {
    const statusIcon = document.getElementById('status-icon');
    const statusTitle = document.getElementById('status-title');
    const statusMessage = document.getElementById('status-message');
    const statusCard = document.getElementById('status-card');
    
    statusTitle.textContent = titulo;
    statusMessage.textContent = mensagem;
    
    if (status === 'pago') {
        statusCard.className = 'status-card-premium status-success';
        statusIcon.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
            </svg>
        `;
    } else if (status === 'expirado') {
        statusCard.className = 'status-card-premium status-expired';
        statusIcon.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L16.31 7.1C17.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>
            </svg>
        `;
    }
}

// Mostrar notificação
function mostrarNotificacao(mensagem, tipo) {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao notificacao-${tipo}`;
    notificacao.textContent = mensagem;
    
    // Adicionar ao DOM
    document.body.appendChild(notificacao);
    
    // Remover após 3 segundos
    setTimeout(() => {
        notificacao.remove();
    }, 3000);
}

// Countdown timer
function iniciarCountdown() {
    if (pagamentoData.tempoRestante <= 0 || pagamentoData.expirou) return;
    
    const countdownElement = document.getElementById('countdown-timer');
    let tempoRestante = pagamentoData.tempoRestante;
    
    const timer = setInterval(() => {
        tempoRestante--;
        
        if (tempoRestante <= 0) {
            clearInterval(timer);
            atualizarStatusPagamento('expirado', 'QR Code Expirado', 'Gere um novo código');
            mostrarQRExpirado();
            return;
        }
        
        const minutos = Math.floor(tempoRestante / 60);
        const segundos = tempoRestante % 60;
        countdownElement.textContent = `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
    }, 1000);
}

// Gerar novo QR Code
function gerarNovoQR() {
    window.location.reload();
}

// Mostrar QR expirado
function mostrarQRExpirado() {
    // Implementar lógica para mostrar interface de QR expirado
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    // Iniciar countdown se não expirou
    if (!pagamentoData.expirou) {
        iniciarCountdown();
        
        // Verificar status a cada 15 segundos
        setInterval(verificarStatusPagamento, 15000);
    }
});
</script>

<!-- Estilos CSS específicos da página -->
<style>
/* Alertas */
.alert-section {
    margin: 20px 0;
}

.alert {
    display: flex;
    align-items: flex-start;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    border: 1px solid;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.alert-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-color: #f87171;
    color: #7f1d1d;
}

.alert-success {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    border-color: #34d399;
    color: #14532d;
}

.alert-icon {
    margin-right: 12px;
    flex-shrink: 0;
    width: 24px;
    height: 24px;
}

.alert-content {
    flex: 1;
}

.alert-content h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
}

.alert-content p {
    margin: 0 0 8px 0;
    font-size: 14px;
    line-height: 1.5;
}

.debug-details {
    margin-top: 12px;
    padding: 12px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 8px;
    font-size: 12px;
}

.debug-details h5 {
    margin: 0 0 8px 0;
    font-size: 13px;
    font-weight: 600;
}

.debug-details p {
    margin: 4px 0;
    font-family: monospace;
}

.debug-details a {
    color: inherit;
    text-decoration: underline;
}

.debug-details a:hover {
    text-decoration: none;
}

/* Estilos da página de pagamento serão adicionados ao CSS principal */
</style>
                    <h4>📊 Status do Pagamento</h4>
                    <div class="status-indicator">
                        <span class="status-badge status-pendente">Aguardando Pagamento</span>
                    </div>
                    <button type="button" onclick="verificarPagamento()" class="btn btn-outline" id="btn-verificar">
                        🔄 Verificar Pagamento
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botões de Ação -->
    <div class="pagamento-actions">
        <a href="<?= SITE_URL ?>/inscricao.php?evento=<?= $evento['slug'] ?>" class="btn btn-outline">
            ← Voltar para Inscrição
        </a>
        
        <!-- Botão para demonstração - remover em produção -->
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
            <button type="submit" name="confirmar_pagamento" class="btn btn-success" 
                    onclick="return confirm('ATENÇÃO: Este botão é apenas para demonstração. Em produção, o pagamento é confirmado automaticamente pelo banco. Confirmar pagamento?')">
                ✅ Simular Pagamento (DEMO)
            </button>
        </form>
        
        <div class="help-text">
            <p>Precisa de ajuda? Entre em contato pelo WhatsApp: 
                <a href="https://wa.me/<?= limpar_telefone(WHATSAPP_CONTATO) ?>" target="_blank">
                    <?= WHATSAPP_CONTATO ?>
                </a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
let tempoRestante = 30 * 60; // 30 minutos em segundos
let intervaloCronometro;
let intervaloVerificacao;

document.addEventListener('DOMContentLoaded', function() {
    // Iniciar cronômetro
    iniciarCronometro();
    
    // Verificar pagamento periodicamente
    iniciarVerificacaoPagamento();
});

// QR Code é fornecido pela EFI Bank - não precisa gerar aqui

function iniciarCronometro() {
    intervaloCronometro = setInterval(function() {
        const minutos = Math.floor(tempoRestante / 60);
        const segundos = tempoRestante % 60;
        
        document.getElementById('tempo-restante').textContent = 
            `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
        
        if (tempoRestante <= 0) {
            clearInterval(intervaloCronometro);
            expirarPagamento();
        }
        
        tempoRestante--;
    }, 1000);
}

function iniciarVerificacaoPagamento() {
    // Verificar a cada 10 segundos
    intervaloVerificacao = setInterval(verificarPagamento, 10000);
}

function verificarPagamento() {
    const btn = document.getElementById('btn-verificar');
    btn.disabled = true;
    btn.textContent = 'Verificando...';
    
    fetch('<?= SITE_URL ?>/api/verificar_pagamento.php?participante=<?= $participante_id ?>')
        .then(response => response.json())
        .then(data => {
            if (data.pago) {
                pagamentoConfirmado();
            } else {
                btn.disabled = false;
                btn.textContent = '🔄 Verificar Pagamento';
            }
        })
        .catch(error => {
            console.error('Erro ao verificar pagamento:', error);
            btn.disabled = false;
            btn.textContent = '🔄 Verificar Pagamento';
        });
}

function pagamentoConfirmado() {
    clearInterval(intervaloCronometro);
    clearInterval(intervaloVerificacao);
    
    document.getElementById('status-pagamento').innerHTML = `
        <h4>✅ Pagamento Confirmado!</h4>
        <div class="status-indicator">
            <span class="status-badge status-pago">Pagamento Aprovado</span>
        </div>
        <p>Redirecionando para confirmação...</p>
    `;
    
    setTimeout(() => {
        window.location.href = '<?= SITE_URL ?>/confirmacao.php?participante=<?= $participante_id ?>';
    }, 3000);
}

function expirarPagamento() {
    document.getElementById('status-pagamento').innerHTML = `
        <h4>⏰ QR Code Expirado</h4>
        <div class="status-indicator">
            <span class="status-badge status-expirado">Expirado</span>
        </div>
        <button type="button" onclick="window.location.reload()" class="btn btn-primary">
            Gerar Novo QR Code
        </button>
    `;
}

function copiarCodigo() {
    const textarea = document.getElementById('codigo-pix');
    textarea.select();
    document.execCommand('copy');
    
    // Feedback visual
    const btn = event.target;
    const textoOriginal = btn.textContent;
    btn.textContent = '✅ Copiado!';
    btn.style.background = '#10b981';
    
    setTimeout(() => {
        btn.textContent = textoOriginal;
        btn.style.background = '';
    }, 2000);
}

// Cleanup ao sair da página
window.addEventListener('beforeunload', function() {
    if (intervaloCronometro) clearInterval(intervaloCronometro);
    if (intervaloVerificacao) clearInterval(intervaloVerificacao);
});
</script>

<?php
obter_rodape();
?> 