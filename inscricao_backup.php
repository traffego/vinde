<?php
require_once 'includes/init.php';

$erro = '';
$sucesso = '';

// Buscar evento por ID ou slug
$evento = [];

if (isset($_GET['evento_id']) && !empty($_GET['evento_id'])) {
    // Buscar por ID (nova forma)
    $evento_id = (int) $_GET['evento_id'];
    
    if ($evento_id > 0) {
        $evento = buscar_um("
            SELECT e.*, 
                   COUNT(p.id) as total_inscritos,
                   (e.limite_participantes - COUNT(p.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
            WHERE e.id = ? AND e.status = 'ativo'
            GROUP BY e.id
        ", [$evento_id]);
    }
} elseif (isset($_GET['evento']) && !empty($_GET['evento'])) {
    // Buscar por slug (compatibilidade)
    $evento_slug = $_GET['evento'];
    
    $evento = buscar_um("
        SELECT e.*, 
               COUNT(p.id) as total_inscritos,
               (e.limite_participantes - COUNT(p.id)) as vagas_restantes
        FROM eventos e
        LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
        WHERE e.slug = ? AND e.status = 'ativo'
        GROUP BY e.id
    ", [$evento_slug]);
}

if (!$evento) {
    obter_cabecalho('Evento não encontrado');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Evento não encontrado</h1>
            <p>O evento solicitado não foi encontrado ou não está mais disponível para inscrições.</p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Verificar se ainda há vagas
if ($evento['vagas_restantes'] <= 0) {
    obter_cabecalho('Evento Esgotado');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Evento Esgotado</h1>
            <p>Infelizmente as vagas para este evento já foram preenchidas.</p>
            <p><strong><?= htmlspecialchars($evento['nome']) ?></strong></p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Ver Outros Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Processar inscrição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $resultado = processar_inscricao($_POST, $evento['id']);
        if ($resultado['sucesso']) {
            redirecionar(SITE_URL . '/pagamento.php?participante=' . $resultado['participante_id']);
        } else {
            $erro = $resultado['mensagem'];
        }
    }
}

/**
 * Processar inscrição do participante
 */
function processar_inscricao($dados, $evento_id) {
    try {
        // Validações
        $erros = [];
        
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        if (empty($dados['cpf']) || !validar_cpf($dados['cpf'])) $erros[] = 'CPF inválido';
        if (empty($dados['whatsapp']) || !validar_telefone($dados['whatsapp'])) $erros[] = 'WhatsApp inválido';
        if (empty($dados['email']) || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Email inválido';
        if (empty($dados['idade']) || !is_numeric($dados['idade']) || $dados['idade'] < 1 || $dados['idade'] > 120) {
            $erros[] = 'Idade deve ser um número válido';
        }
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatória';
        
        // Verificar se já existe inscrição com este CPF para este evento
        $inscricao_existente = buscar_um("
            SELECT id FROM participantes 
            WHERE evento_id = ? AND cpf = ? AND status != 'cancelado'
        ", [$evento_id, limpar_cpf($dados['cpf'])]);
        
        if ($inscricao_existente) {
            $erros[] = 'Já existe uma inscrição com este CPF para este evento';
        }
        
        if (!empty($erros)) {
            return ['sucesso' => false, 'mensagem' => implode(', ', $erros)];
        }
        
        // Verificar vagas novamente
        $evento_atual = buscar_um("
            SELECT e.limite_participantes,
                   COUNT(p.id) as total_inscritos,
                   (e.limite_participantes - COUNT(p.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
            WHERE e.id = ?
            GROUP BY e.id
        ", [$evento_id]);
        
        if ($evento_atual['vagas_restantes'] <= 0) {
            return ['sucesso' => false, 'mensagem' => 'As vagas se esgotaram durante o preenchimento. Tente outro evento.'];
        }
        
        // Gerar token único para QR Code
        $qr_token = gerar_string_aleatoria(32);
        
        // Inserir participante
        $participante_dados = [
            'evento_id' => $evento_id,
            'nome' => sanitizar_entrada($dados['nome']),
            'cpf' => limpar_cpf($dados['cpf']),
            'whatsapp' => limpar_telefone($dados['whatsapp']),
            'instagram' => sanitizar_entrada($dados['instagram'] ?? ''),
            'email' => sanitizar_entrada($dados['email']),
            'idade' => intval($dados['idade']),
            'cidade' => sanitizar_entrada($dados['cidade']),
            'estado' => $dados['estado'] ?? 'SP',
            'tipo' => 'normal',
            'status' => 'inscrito',
            'qr_token' => $qr_token
        ];
        
        $participante_id = inserir_registro('participantes', $participante_dados);
        
        if (!$participante_id) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao processar inscrição. Tente novamente.'];
        }
        
        // Buscar valor do evento para criar pagamento
        $evento_info = buscar_um("SELECT valor FROM eventos WHERE id = ?", [$evento_id]);
        $valor = floatval($evento_info['valor']);
        
        // Criar registro de pagamento
        if ($valor > 0) {
            // Gerar TXID único para EFI Bank
            $txid = 'VINDE' . date('YmdHis') . str_pad($participante_id, 6, '0', STR_PAD_LEFT);
            
            $pagamento_dados = [
                'participante_id' => $participante_id,
                'valor' => $valor,
                'status' => 'pendente',
                'metodo' => 'pix',
                'pix_txid' => $txid
            ];
            
            // Verificar se EFI Bank está ativo e certificado disponível (config do banco)
            $efi_ativo = obter_configuracao('efi_ativo', '0') === '1';
            $config_efi = obter_configuracoes_efi();
            $certificado_existe = !empty($config_efi['efi_certificado_path']) && file_exists($config_efi['efi_certificado_path']);
            
            if ($efi_ativo && $certificado_existe) {
                // Usar EFI Bank
                $descricao = "Inscrição: {$evento['nome']} - {$dados['nome']}";
                $cobranca_efi = efi_criar_cobranca_pix(
                    $txid,
                    $valor,
                    $descricao,
                    $dados['nome'],
                    limpar_cpf($dados['cpf']),
                    3600 // 1 hora de expiração
                );
                
                if ($cobranca_efi) {
                    $pagamento_dados['pix_loc_id'] = $cobranca_efi['loc']['id'] ?? null;
                    $pagamento_dados['pix_expires_at'] = date('Y-m-d H:i:s', time() + 3600);
                    
                    // Gerar QR Code da cobrança
                    if (isset($cobranca_efi['loc']['id'])) {
                        $qrcode_data = efi_gerar_qrcode($cobranca_efi['loc']['id']);
                        if ($qrcode_data) {
                            $pagamento_dados['pix_qrcode_data'] = $qrcode_data['qrcode'];
                            $pagamento_dados['pix_qrcode_url'] = $qrcode_data['imagemQrcode'] ?? null;
                        }
                    }
                } else {
                    // Erro: EFI Bank não configurado ou falhou
                    throw new Exception('Sistema de pagamento indisponível. Tente novamente mais tarde.');
                }
            
            inserir_registro('pagamentos', $pagamento_dados);
        } else {
            // Evento gratuito - marcar como pago
            $pagamento_dados = [
                'participante_id' => $participante_id,
                'valor' => 0,
                'status' => 'pago',
                'metodo' => 'dinheiro',
                'pago_em' => date('Y-m-d H:i:s')
            ];
            
            inserir_registro('pagamentos', $pagamento_dados);
            
            // Atualizar status do participante
            atualizar_registro('participantes', ['status' => 'pago'], ['id' => $participante_id]);
        }
        
        // Log da ação
        registrar_log('inscricao_criada', "Participante: {$dados['nome']} - Evento ID: {$evento_id}");
        
        return ['sucesso' => true, 'participante_id' => $participante_id];
        
    } catch (Exception $e) {
        error_log("Erro ao processar inscrição: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno. Tente novamente mais tarde.'];
    }
}

obter_cabecalho('Inscrição - ' . $evento['nome'], 'inscricao');
echo '<link rel="stylesheet" href="' . SITE_URL . '/assets/css/checkout.css">';
?>

<main class="inscricao-main">
    <!-- Breadcrumb -->
    <nav class="inscricao-breadcrumb">
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
                <span class="breadcrumb-current">Inscrição</span>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Layout Checkout -->
        <div class="checkout-layout">
            <div class="checkout-main">
                <?php if ($erro): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="form-inscricao" class="checkout-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
                    
                    <!-- Seção Dados Pessoais -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                            <div class="section-title">
                                <h3>Dados Pessoais</h3>
                                <p>Informações básicas para sua inscrição</p>
                            </div>
                        </div>
            
                        <div class="form-grid">
                            <div class="form-field-premium full-width">
                                <label for="nome" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                    Nome Completo *
                                </label>
                                <input type="text" id="nome" name="nome" class="field-input" required
                                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                                       placeholder="Digite seu nome completo">
                                <div class="field-error" id="erro-nome"></div>
                            </div>
                        </div>
                        
                        <div class="form-grid two-columns">
                            <div class="form-field-premium">
                                <label for="cpf" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                    </svg>
                                    CPF *
                                </label>
                                <input type="text" id="cpf" name="cpf" class="field-input" required
                                       value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>"
                                       placeholder="000.000.000-00" data-mask="cpf">
                                <div class="field-error" id="erro-cpf"></div>
                            </div>
                            
                            <div class="form-field-premium">
                                <label for="idade" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9 11H7v6h2v-6zm4 0h-2v6h2v-6zm4 0h-2v6h2v-6zm2-7h-3V2h-2v2H8V2H6v2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H3V9h14v11z"/>
                                    </svg>
                                    Idade *
                                </label>
                                <input type="number" id="idade" name="idade" class="field-input" required min="1" max="120"
                                       value="<?= htmlspecialchars($_POST['idade'] ?? '') ?>"
                                       placeholder="Ex: 25">
                                <div class="field-error" id="erro-idade"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Contato -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                            </div>
                            <div class="section-title">
                                <h3>Informações de Contato</h3>
                                <p>Como podemos entrar em contato com você</p>
                            </div>
                        </div>
                        
                        <div class="form-grid two-columns">
                            <div class="form-field-premium">
                                <label for="whatsapp" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893A11.821 11.821 0 0020.89 3.488"/>
                                    </svg>
                                    WhatsApp *
                                </label>
                                <input type="tel" id="whatsapp" name="whatsapp" class="field-input" required
                                       value="<?= htmlspecialchars($_POST['whatsapp'] ?? '') ?>"
                                       placeholder="(11) 99999-9999" data-mask="telefone">
                                <div class="field-help">Utilizaremos para comunicações importantes sobre o evento</div>
                                <div class="field-error" id="erro-whatsapp"></div>
                            </div>
                            
                            <div class="form-field-premium">
                                <label for="email" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                    </svg>
                                    Email *
                                </label>
                                <input type="email" id="email" name="email" class="field-input" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="seu@email.com">
                                <div class="field-error" id="erro-email"></div>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field-premium">
                                <label for="instagram" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M7.8,2H16.2C19.4,2 22,4.6 22,7.8V16.2A5.8,5.8 0 0,1 16.2,22H7.8C4.6,22 2,19.4 2,16.2V7.8A5.8,5.8 0 0,1 7.8,2M7.6,4A3.6,3.6 0 0,0 4,7.6V16.4C4,18.39 5.61,20 7.6,20H16.4A3.6,3.6 0 0,0 20,16.4V7.6C20,5.61 18.39,4 16.4,4H7.6M17.25,5.5A1.25,1.25 0 0,1 18.5,6.75A1.25,1.25 0 0,1 17.25,8A1.25,1.25 0 0,1 16,6.75A1.25,1.25 0 0,1 17.25,5.5M12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9Z"/>
                                    </svg>
                                    Instagram <span class="optional">(opcional)</span>
                                </label>
                                <input type="text" id="instagram" name="instagram" class="field-input"
                                       value="<?= htmlspecialchars($_POST['instagram'] ?? '') ?>"
                                       placeholder="seuusuario">
                                <div class="field-help">Sem o @, apenas o nome do usuário</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Localização -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                            </div>
                            <div class="section-title">
                                <h3>Sua Localização</h3>
                                <p>Para estatísticas e organização do evento</p>
                            </div>
                        </div>
                        
                        <div class="form-grid two-columns">
                            <div class="form-field-premium">
                                <label for="cidade" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M15,11V5L12,2L9,5V7H3V21H21V11H15M7,19H5V17H7V19M7,15H5V13H7V15M7,11H5V9H7V11M13,19H11V17H13V19M13,15H11V13H13V15M13,11H11V9H13V11M13,7H11V5H13V7M19,19H17V17H19V19M19,15H17V13H19V15Z"/>
                                    </svg>
                                    Cidade *
                                </label>
                                <input type="text" id="cidade" name="cidade" class="field-input" required
                                       value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>"
                                       placeholder="Digite sua cidade">
                                <div class="field-error" id="erro-cidade"></div>
                            </div>
                            
                            <div class="form-field-premium">
                                <label for="estado" class="field-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2L13.09,6.26L22,9.27L17,14.14L18.18,21.02L12,17.77L5.82,21.02L7,14.14L2,9.27L10.91,6.26L12,2Z"/>
                                    </svg>
                                    Estado
                                </label>
                                <select id="estado" name="estado" class="field-select">
                                    <option value="SP" <?= ($_POST['estado'] ?? 'SP') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                                    <option value="RJ" <?= ($_POST['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                                    <option value="MG" <?= ($_POST['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                                    <option value="ES" <?= ($_POST['estado'] ?? '') === 'ES' ? 'selected' : '' ?>>Espírito Santo</option>
                                    <option value="PR" <?= ($_POST['estado'] ?? '') === 'PR' ? 'selected' : '' ?>>Paraná</option>
                                    <option value="SC" <?= ($_POST['estado'] ?? '') === 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                                    <option value="RS" <?= ($_POST['estado'] ?? '') === 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Termos -->
                    <div class="form-section">
                        <div class="terms-section">
                            <div class="checkbox-premium">
                                <input type="checkbox" id="aceito-termos" required>
                                <label for="aceito-termos" class="checkbox-label">
                                    <div class="checkbox-custom">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                                        </svg>
                                    </div>
                                    <span>Aceito os <a href="#" onclick="abrirTermos(); return false;" class="terms-link">termos e condições</a> do evento *</span>
                                </label>
                            </div>
                            <div class="field-error" id="erro-termos"></div>
                        </div>
                    </div>
                    
                    <!-- Botão de Submissão Premium -->
                    <div class="form-actions-premium">
                        <div class="actions-content">
                            <a href="<?= SITE_URL ?>/evento/<?= $evento['id'] ?>" class="btn-secondary-premium">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                                </svg>
                                Voltar ao Evento
                            </a>
                            
                            <button type="submit" class="btn-primary-premium" id="btn-inscricao">
                                <div class="btn-content">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <?php if ($evento['valor'] > 0): ?>
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM7.07 18.28c.43-.9 3.05-1.78 4.93-1.78s4.51.88 4.93 1.78C15.57 19.36 13.86 20 12 20s-3.57-.64-4.93-1.72zm11.29-1.45c-1.43-1.74-4.9-2.33-6.36-2.33s-4.93.59-6.36 2.33C4.62 15.49 4 13.82 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8c0 1.82-.62 3.49-1.64 4.83z"/>
                                        <?php else: ?>
                                            <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                                        <?php endif; ?>
                                    </svg>
                                    <span>
                                        <?php if ($evento['valor'] > 0): ?>
                                            Prosseguir para Pagamento
                                        <?php else: ?>
                                            Finalizar Inscrição Gratuita
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="btn-loading" style="display: none;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,4a8,8 0 0,1 7.89,6.7A1.53,1.53 0 0,0 21.38,12h0a1.5,1.5 0 0,0 1.48-1.75,11,11 0 0,0-21.72,0A1.5,1.5 0 0,0 2.62,12h0a1.53,1.53 0 0,0 1.49-1.3A8,8 0 0,1 12,4Z">
                                            <animateTransform attributeName="transform" dur="0.75s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"/>
                                        </path>
                                    </svg>
                                    Processando...
                                </div>
                            </button>
                        </div>
                        
                        <div class="security-note">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                            </svg>
                            <span>Seus dados estão protegidos e seguros</span>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<!-- Modal de Termos -->
<div id="modal-termos" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Termos e Condições</h3>
            <span class="modal-close" onclick="fecharTermos()">&times;</span>
        </div>
        <div class="modal-body">
            <h4>Inscrição no Evento</h4>
            <p>Ao se inscrever neste evento católico, você concorda com os seguintes termos:</p>
            
            <h5>1. Dados Pessoais</h5>
            <p>Seus dados serão utilizados exclusivamente para organização do evento e comunicações relacionadas. Não compartilhamos informações com terceiros.</p>
            
            <h5>2. Pagamento</h5>
            <p>Para eventos pagos, o pagamento deve ser realizado via PIX. A vaga só será confirmada após a compensação do pagamento.</p>
            
            <h5>3. Cancelamento</h5>
            <p>Cancelamentos devem ser solicitados com até 48 horas de antecedência através do WhatsApp de contato.</p>
            
            <h5>4. Comportamento</h5>
            <p>Esperamos que todos os participantes mantenham comportamento respeitoso e adequado ao ambiente religioso.</p>
            
            <h5>5. Imagens</h5>
            <p>O evento pode ser fotografado/filmado para divulgação. Caso não deseje aparecer, comunique a organização.</p>
        </div>
        <div class="modal-footer">
            <button onclick="aceitarTermos()" class="btn btn-primary">Aceitar Termos</button>
            <button onclick="fecharTermos()" class="btn btn-outline">Fechar</button>
        </div>
    </div>
</div>

<script>
// Validação em tempo real
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-inscricao');
    const inputs = form.querySelectorAll('input[required], select[required]');
    
    // Aplicar máscaras
    aplicarMascaras();
    
    // Validação em tempo real
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validarCampo(this);
        });
    });
    
    // Validação no submit
    form.addEventListener('submit', function(e) {
        let formValido = true;
        
        inputs.forEach(input => {
            if (!validarCampo(input)) {
                formValido = false;
            }
        });
        
        // Validar termos
        const termos = document.getElementById('aceito-termos');
        if (!termos.checked) {
            document.getElementById('erro-termos').textContent = 'Você deve aceitar os termos e condições';
            formValido = false;
        } else {
            document.getElementById('erro-termos').textContent = '';
        }
        
        if (!formValido) {
            e.preventDefault();
            // Scroll para primeiro erro
            const primeiroErro = document.querySelector('.field-error:not(:empty)');
            if (primeiroErro) {
                primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            // Desabilitar botão para evitar duplo click
            const btnSubmit = document.getElementById('btn-inscricao');
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Processando...';
        }
    });
});

function validarCampo(campo) {
    const erro = document.getElementById('erro-' + campo.id);
    let mensagem = '';
    
    if (!erro) return true; // Se não encontrar elemento de erro, considerar válido
    
    switch(campo.id) {
        case 'nome':
            if (!campo.value.trim()) {
                mensagem = 'Nome é obrigatório';
            } else if (campo.value.trim().length < 3) {
                mensagem = 'Nome deve ter pelo menos 3 caracteres';
            }
            break;
            
        case 'cpf':
            if (!campo.value.trim()) {
                mensagem = 'CPF é obrigatório';
            } else if (!validarCPF(campo.value)) {
                mensagem = 'CPF inválido';
            }
            break;
            
        case 'idade':
            const idade = parseInt(campo.value);
            if (!campo.value || idade < 1 || idade > 120) {
                mensagem = 'Idade deve ser um número entre 1 e 120';
            }
            break;
            
        case 'whatsapp':
            if (!campo.value.trim()) {
                mensagem = 'WhatsApp é obrigatório';
            } else if (campo.value.replace(/\D/g, '').length < 10) {
                mensagem = 'WhatsApp deve ter pelo menos 10 dígitos';
            }
            break;
            
        case 'email':
            if (!campo.value.trim()) {
                mensagem = 'Email é obrigatório';
            } else if (!validarEmail(campo.value)) {
                mensagem = 'Email inválido';
            }
            break;
            
        case 'cidade':
            if (!campo.value.trim()) {
                mensagem = 'Cidade é obrigatória';
            }
            break;
    }
    
    if (erro) {
        erro.textContent = mensagem;
        campo.classList.toggle('error', !!mensagem);
    }
    
    return !mensagem;
}

function aplicarMascaras() {
    // Máscara CPF
    const cpfInput = document.getElementById('cpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        });
    }
    
    // Máscara Telefone
    const telefoneInput = document.getElementById('whatsapp');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            }
            this.value = value;
        });
    }
    
    // Limpar Instagram
    const instagramInput = document.getElementById('instagram');
    if (instagramInput) {
        instagramInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9._]/g, '').replace(/^@/, '');
        });
    }
}

function validarCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    if (cpf.length !== 11) return false;
    
    // Verificar sequências iguais
    if (/^(\d)\1{10}$/.test(cpf)) return false;
    
    // Validar dígitos verificadores
    let soma = 0;
    for (let i = 0; i < 9; i++) {
        soma += parseInt(cpf.charAt(i)) * (10 - i);
    }
    let resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(9))) return false;
    
    soma = 0;
    for (let i = 0; i < 10; i++) {
        soma += parseInt(cpf.charAt(i)) * (11 - i);
    }
    resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    
    return resto === parseInt(cpf.charAt(10));
}

function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// Funções da modal
function abrirTermos() {
    const modal = document.getElementById('modal-termos');
    modal.classList.add('show');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevenir scroll
}

function fecharTermos() {
    const modal = document.getElementById('modal-termos');
    modal.classList.remove('show');
    
    // Animação de saída
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restaurar scroll
    }, 300);
}

function aceitarTermos() {
    const checkbox = document.getElementById('aceito-termos');
    checkbox.checked = true;
    
    // Limpar erro se existir
    const erro = document.getElementById('erro-termos');
    if (erro) {
        erro.textContent = '';
        erro.style.display = 'none';
    }
    
    // Destacar que foi aceito
    const checkboxContainer = checkbox.closest('.checkbox-premium');
    if (checkboxContainer) {
        checkboxContainer.style.animation = 'pulse 0.5s ease';
    }
    
    fecharTermos();
}

// Fechar modal clicando fora ou com ESC
window.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-termos');
    
    // Clique fora da modal
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            fecharTermos();
        }
    });
    
    // Tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            fecharTermos();
        }
    });
});
</script>

<?php
obter_rodape();
?>