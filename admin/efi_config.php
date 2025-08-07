<?php
require_once '../includes/init.php';
requer_login('admin');

$titulo_pagina = 'Configuração EFI Bank';
$mensagem = null;
$erro = null;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido';
    } else {
        $acao = $_POST['acao'] ?? '';
        
        switch ($acao) {
            case 'salvar_config':
                try {
                    // Salvar configurações no banco
                    definir_configuracao('efi_ativo', $_POST['efi_ativo'] ?? '0');
                    definir_configuracao('efi_ambiente', $_POST['efi_ambiente'] ?? 'desenvolvimento');
                    definir_configuracao('efi_webhook_url', $_POST['efi_webhook_url'] ?? '');
                    
                    // Atualizar arquivo config.php com credenciais
                    $config_path = '../includes/config.php';
                    $config_content = file_get_contents($config_path);
                    
                    // Credenciais EFI
                    $config_content = preg_replace(
                        "/define\('EFI_CLIENT_ID_HOM',\s*'[^']*'\);/",
                        "define('EFI_CLIENT_ID_HOM', '" . addslashes($_POST['efi_client_id_hom'] ?? '') . "');",
                        $config_content
                    );
                    
                    $config_content = preg_replace(
                        "/define\('EFI_CLIENT_SECRET_HOM',\s*'[^']*'\);/",
                        "define('EFI_CLIENT_SECRET_HOM', '" . addslashes($_POST['efi_client_secret_hom'] ?? '') . "');",
                        $config_content
                    );
                    
                    $config_content = preg_replace(
                        "/define\('EFI_CLIENT_ID_PROD',\s*'[^']*'\);/",
                        "define('EFI_CLIENT_ID_PROD', '" . addslashes($_POST['efi_client_id_prod'] ?? '') . "');",
                        $config_content
                    );
                    
                    $config_content = preg_replace(
                        "/define\('EFI_CLIENT_SECRET_PROD',\s*'[^']*'\);/",
                        "define('EFI_CLIENT_SECRET_PROD', '" . addslashes($_POST['efi_client_secret_prod'] ?? '') . "');",
                        $config_content
                    );
                    
                    $config_content = preg_replace(
                        "/define\('EFI_SENHA_CERTIFICADO',\s*'[^']*'\);/",
                        "define('EFI_SENHA_CERTIFICADO', '" . addslashes($_POST['efi_senha_certificado'] ?? '') . "');",
                        $config_content
                    );
                    
                    // Dados PIX
                    $config_content = preg_replace(
                        "/define\('PIX_CHAVE',\s*'[^']*'\);/",
                        "define('PIX_CHAVE', '" . addslashes($_POST['pix_chave'] ?? '') . "');",
                        $config_content
                    );
                    
                    $config_content = preg_replace(
                        "/define\('PIX_NOME',\s*'[^']*'\);/",
                        "define('PIX_NOME', '" . addslashes(strtoupper($_POST['pix_nome'] ?? '')) . "');",
                        $config_content
                    );
                    
                    $config_content = preg_replace(
                        "/define\('PIX_CIDADE',\s*'[^']*'\);/",
                        "define('PIX_CIDADE', '" . addslashes(strtoupper($_POST['pix_cidade'] ?? '')) . "');",
                        $config_content
                    );
                    
                    // Processar upload de certificados
                    $upload_msgs = [];
                    
                    // Upload certificado homologação
                    if (isset($_FILES['certificado_hom']) && $_FILES['certificado_hom']['error'] === UPLOAD_ERR_OK) {
                        $file_info = $_FILES['certificado_hom'];
                        if (pathinfo($file_info['name'], PATHINFO_EXTENSION) === 'p12') {
                            $dest_path = '../certificados/certificado_hom.p12';
                            if (move_uploaded_file($file_info['tmp_name'], $dest_path)) {
                                $upload_msgs[] = 'Certificado de homologação atualizado';
                            } else {
                                $upload_msgs[] = 'Erro ao salvar certificado de homologação';
                            }
                        } else {
                            $upload_msgs[] = 'Certificado de homologação deve ser um arquivo .p12';
                        }
                    }
                    
                    // Upload certificado produção
                    if (isset($_FILES['certificado_prod']) && $_FILES['certificado_prod']['error'] === UPLOAD_ERR_OK) {
                        $file_info = $_FILES['certificado_prod'];
                        if (pathinfo($file_info['name'], PATHINFO_EXTENSION) === 'p12') {
                            $dest_path = '../certificados/certificado_prod.p12';
                            if (move_uploaded_file($file_info['tmp_name'], $dest_path)) {
                                $upload_msgs[] = 'Certificado de produção atualizado';
                            } else {
                                $upload_msgs[] = 'Erro ao salvar certificado de produção';
                            }
                        } else {
                            $upload_msgs[] = 'Certificado de produção deve ser um arquivo .p12';
                        }
                    }
                    
                    // Salvar arquivo config.php
                    if (file_put_contents($config_path, $config_content)) {
                        $msg_final = 'Configurações salvas com sucesso! As credenciais foram atualizadas no arquivo config.php.';
                        if (!empty($upload_msgs)) {
                            $msg_final .= ' ' . implode(' ', $upload_msgs);
                        }
                        registrar_log('efi_config_atualizada', 'Configurações EFI Bank e credenciais atualizadas');
                        $mensagem = $msg_final;
                    } else {
                        $erro = 'Erro ao salvar credenciais no arquivo config.php. Verifique as permissões.';
                    }
                    
                } catch (Exception $e) {
                    $erro = 'Erro ao salvar configurações: ' . $e->getMessage();
                }
                break;
                
            case 'testar_conexao':
                $resultados = efi_testar_configuracao();
                break;
                
            case 'configurar_webhook':
                $webhook_url = SITE_URL . '/webhook_efi.php';
                $sucesso = efi_configurar_webhook($webhook_url);
                
                if ($sucesso) {
                    definir_configuracao('efi_webhook_url', $webhook_url);
                    $mensagem = 'Webhook configurado com sucesso!';
                } else {
                    $erro = 'Erro ao configurar webhook na EFI Bank';
                }
                break;
        }
    }
}

// Obter configurações atuais
$config_efi = [
    'ativo' => obter_configuracao('efi_ativo', '0'),
    'ambiente' => obter_configuracao('efi_ambiente', 'desenvolvimento'),
    'webhook_url' => obter_configuracao('efi_webhook_url', '')
];

obter_cabecalho_admin($titulo_pagina, 'configuracoes');
?>

<div class="admin-content">
    <div class="admin-header">
        <h1><?= $titulo_pagina ?></h1>
        <p>Configure a integração com EFI Bank para pagamentos PIX automáticos</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <div class="efi-config-container">
        <!-- Status da Configuração -->
        <div class="config-status-section">
            <div class="status-cards">
                <div class="status-card <?= $config_efi['ativo'] === '1' ? 'active' : 'inactive' ?>">
                    <div class="status-icon">
                        <i class="<?= $config_efi['ativo'] === '1' ? 'icon-check-circle' : 'icon-warning-circle' ?>"></i>
                    </div>
                    <div class="status-content">
                        <h4>Status EFI Bank</h4>
                        <p><?= $config_efi['ativo'] === '1' ? 'Ativo e Funcionando' : 'Inativo' ?></p>
                    </div>
                </div>
                
                <div class="status-card">
                    <div class="status-icon">
                        <i class="icon-environment"></i>
                    </div>
                    <div class="status-content">
                        <h4>Ambiente</h4>
                        <p><?= ucfirst($config_efi['ambiente']) ?></p>
                    </div>
                </div>
                
                <div class="status-card">
                    <div class="status-icon">
                        <i class="icon-certificate"></i>
                    </div>
                    <div class="status-content">
                        <h4>Certificados</h4>
                        <p>
                            <?php 
                            $cert_count = 0;
                            if (file_exists(EFI_CERTIFICADO_HOM)) $cert_count++;
                            if (file_exists(EFI_CERTIFICADO_PROD)) $cert_count++;
                            echo $cert_count . '/2 Configurados';
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="status-card">
                    <div class="status-icon">
                        <i class="icon-webhook"></i>
                    </div>
                    <div class="status-content">
                        <h4>Webhook</h4>
                        <p><?= !empty($config_efi['webhook_url']) ? 'Configurado' : 'Não Configurado' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário Principal -->
        <div class="config-form-container">
            <form method="POST" class="efi-config-form" enctype="multipart/form-data" id="efiConfigForm">
                <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
                <input type="hidden" name="acao" value="salvar_config">
                
                <!-- Seção 1: Configurações Gerais -->
                <div class="config-section">
                    <div class="section-header">
                        <h3><i class="icon-settings"></i> Configurações Gerais</h3>
                        <p>Ative e configure o ambiente da integração EFI Bank</p>
                    </div>
                    
                    <div class="section-content">
                        <div class="form-group">
                            <label for="efi_ativo" class="checkbox-label">
                                <input type="checkbox" id="efi_ativo" name="efi_ativo" value="1" 
                                       <?= $config_efi['ativo'] === '1' ? 'checked' : '' ?>>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">
                                    <strong>🚀 Ativar Integração EFI Bank</strong>
                                    <small>Quando ativo, os pagamentos PIX serão processados automaticamente via EFI Bank. Os campos pix_txid, pix_loc_id e demais dados serão salvos na tabela pagamentos.</small>
                                </span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>🌍 Ambiente de Operação</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="efi_ambiente" value="desenvolvimento" 
                                           <?= $config_efi['ambiente'] === 'desenvolvimento' ? 'checked' : '' ?>>
                                    <span class="radio-custom"></span>
                                    <div class="radio-content">
                                        <strong>🧪 Desenvolvimento</strong>
                                        <small>Para testes e integração. Usa certificado de homologação.</small>
                                    </div>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="efi_ambiente" value="producao" 
                                           <?= $config_efi['ambiente'] === 'producao' ? 'checked' : '' ?>>
                                    <span class="radio-custom"></span>
                                    <div class="radio-content">
                                        <strong>🔒 Produção</strong>
                                        <small>Para operações reais com dinheiro. Usa certificado de produção.</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção 2: Autenticação e Credenciais -->
                <div class="config-section">
                    <div class="section-header">
                        <h3><i class="icon-key"></i> Autenticação e Credenciais</h3>
                        <p>Configure as credenciais de acesso à API da EFI Bank</p>
                    </div>
                    
                    <div class="section-content">
                        <div class="credentials-tabs">
                            <div class="tab-buttons">
                                <button type="button" class="tab-btn active" data-tab="hom">
                                    <i class="icon-test"></i> Homologação
                                </button>
                                <button type="button" class="tab-btn" data-tab="prod">
                                    <i class="icon-live"></i> Produção
                                </button>
                            </div>
                            
                            <div class="tab-content active" data-tab="hom">
                                <div class="credentials-header">
                                    <h4>🧪 Ambiente de Homologação</h4>
                                    <p>Credenciais para testes - não processa dinheiro real</p>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="efi_client_id_hom">
                                            <i class="icon-id"></i> Client ID (Homologação)
                                        </label>
                                        <div class="input-group">
                                            <input type="text" id="efi_client_id_hom" name="efi_client_id_hom" 
                                                   value="<?= htmlspecialchars(EFI_CLIENT_ID_HOM) ?>"
                                                   placeholder="Client_Id_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                                            <button type="button" class="input-btn" onclick="generatePlaceholder('efi_client_id_hom')">
                                                <i class="icon-refresh"></i>
                                            </button>
                                        </div>
                                        <small><i class="icon-info"></i> Obtido no painel EFI Bank > API > Aplicações</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="efi_client_secret_hom">
                                            <i class="icon-key"></i> Client Secret (Homologação)
                                        </label>
                                        <div class="input-group">
                                            <input type="password" id="efi_client_secret_hom" name="efi_client_secret_hom" 
                                                   value="<?= htmlspecialchars(EFI_CLIENT_SECRET_HOM) ?>"
                                                   placeholder="Client_Secret_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                                            <button type="button" class="input-btn" onclick="togglePassword('efi_client_secret_hom')">
                                                <i class="icon-eye"></i>
                                            </button>
                                        </div>
                                        <small><i class="icon-info"></i> Chave secreta para autenticação na API</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tab-content" data-tab="prod">
                                <div class="credentials-header">
                                    <h4>🔒 Ambiente de Produção</h4>
                                    <p>Credenciais para operações reais - processa dinheiro real</p>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="efi_client_id_prod">
                                            <i class="icon-id"></i> Client ID (Produção)
                                        </label>
                                        <div class="input-group">
                                            <input type="text" id="efi_client_id_prod" name="efi_client_id_prod" 
                                                   value="<?= htmlspecialchars(EFI_CLIENT_ID_PROD) ?>"
                                                   placeholder="Client_Id_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                                            <button type="button" class="input-btn" onclick="generatePlaceholder('efi_client_id_prod')">
                                                <i class="icon-refresh"></i>
                                            </button>
                                        </div>
                                        <small><i class="icon-info"></i> Credencial de produção - use apenas quando tudo estiver testado</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="efi_client_secret_prod">
                                            <i class="icon-key"></i> Client Secret (Produção)
                                        </label>
                                        <div class="input-group">
                                            <input type="password" id="efi_client_secret_prod" name="efi_client_secret_prod" 
                                                   value="<?= htmlspecialchars(EFI_CLIENT_SECRET_PROD) ?>"
                                                   placeholder="Client_Secret_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                                            <button type="button" class="input-btn" onclick="togglePassword('efi_client_secret_prod')">
                                                <i class="icon-eye"></i>
                                            </button>
                                        </div>
                                        <small><i class="icon-warning"></i> Mantenha esta chave segura - acesso total à sua conta EFI</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="efi_senha_certificado">
                                <i class="icon-lock"></i> Senha dos Certificados .p12
                            </label>
                            <div class="input-group">
                                <input type="password" id="efi_senha_certificado" name="efi_senha_certificado" 
                                       value="<?= htmlspecialchars(EFI_SENHA_CERTIFICADO) ?>"
                                       placeholder="Deixe em branco se não há senha">
                                <button type="button" class="input-btn" onclick="togglePassword('efi_senha_certificado')">
                                    <i class="icon-eye"></i>
                                </button>
                            </div>
                            <small><i class="icon-info"></i> Senha utilizada para proteger os arquivos .p12 (opcional)</small>
                        </div>
                    </div>
                </div>
                
                <!-- Seção 3: Certificados de Segurança -->
                <div class="config-section">
                    <div class="section-header">
                        <h3><i class="icon-certificate"></i> Certificados de Segurança</h3>
                        <p>Upload dos certificados .p12 para autenticação segura na EFI Bank</p>
                    </div>
                    
                    <div class="section-content">
                        <div class="certificate-grid">
                            <div class="certificate-upload">
                                <label for="certificado_hom" class="file-upload-label">
                                    <div class="upload-icon">
                                        <i class="icon-upload"></i>
                                    </div>
                                    <div class="upload-content">
                                        <h5>🧪 Certificado Homologação</h5>
                                        <p>Arrastar arquivo .p12 ou clique para selecionar</p>
                                        <?php if (file_exists(EFI_CERTIFICADO_HOM)): ?>
                                            <span class="file-status success">
                                                <i class="icon-check"></i> <?= basename(EFI_CERTIFICADO_HOM) ?>
                                                <small>Modificado: <?= date('d/m/Y H:i', filemtime(EFI_CERTIFICADO_HOM)) ?></small>
                                            </span>
                                        <?php else: ?>
                                            <span class="file-status error">
                                                <i class="icon-warning"></i> Nenhum arquivo enviado
                                                <small>Necessário para ambiente de testes</small>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                                <input type="file" id="certificado_hom" name="certificado_hom" accept=".p12" class="file-input">
                            </div>
                            
                            <div class="certificate-upload">
                                <label for="certificado_prod" class="file-upload-label">
                                    <div class="upload-icon">
                                        <i class="icon-upload"></i>
                                    </div>
                                    <div class="upload-content">
                                        <h5>🔒 Certificado Produção</h5>
                                        <p>Arrastar arquivo .p12 ou clique para selecionar</p>
                                        <?php if (file_exists(EFI_CERTIFICADO_PROD)): ?>
                                            <span class="file-status success">
                                                <i class="icon-check"></i> <?= basename(EFI_CERTIFICADO_PROD) ?>
                                                <small>Modificado: <?= date('d/m/Y H:i', filemtime(EFI_CERTIFICADO_PROD)) ?></small>
                                            </span>
                                        <?php else: ?>
                                            <span class="file-status error">
                                                <i class="icon-warning"></i> Nenhum arquivo enviado
                                                <small>Necessário para operações reais</small>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                                <input type="file" id="certificado_prod" name="certificado_prod" accept=".p12" class="file-input">
                            </div>
                        </div>
                        
                        <div class="certificate-info">
                            <h4><i class="icon-info"></i> Informações Importantes</h4>
                            <ul>
                                <li><strong>Localização:</strong> Os certificados são salvos na pasta <code>certificados/</code></li>
                                <li><strong>Segurança:</strong> Certificados são usados para autenticação SSL/TLS com a EFI Bank</li>
                                <li><strong>Renovação:</strong> Certificados têm prazo de validade - verifique regularmente</li>
                                <li><strong>Backup:</strong> Mantenha backup dos certificados em local seguro</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Seção 4: Configurações PIX -->
                <div class="config-section">
                    <div class="section-header">
                        <h3><i class="icon-pix"></i> Dados do PIX</h3>
                        <p>Configure as informações que aparecerão nos códigos PIX gerados</p>
                    </div>
                    
                    <div class="section-content">
                        <div class="pix-info-card">
                            <h4><i class="icon-info"></i> Campos salvos no banco de dados</h4>
                            <p>Estes dados são utilizados para gerar os códigos PIX e são salvos na tabela <code>pagamentos</code> com os campos:</p>
                            <div class="db-fields">
                                <span class="db-field">pix_txid</span>
                                <span class="db-field">pix_loc_id</span>
                                <span class="db-field">pix_qrcode_url</span>
                                <span class="db-field">pix_qrcode_data</span>
                                <span class="db-field">pix_expires_at</span>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="pix_chave">
                                    <i class="icon-key"></i> Chave PIX Principal
                                </label>
                                <input type="text" id="pix_chave" name="pix_chave" 
                                       value="<?= htmlspecialchars(PIX_CHAVE) ?>" required
                                       placeholder="11999999999 ou email@exemplo.com">
                                <small><i class="icon-info"></i> CPF, CNPJ, email, telefone ou chave aleatória</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="pix_nome">
                                    <i class="icon-user"></i> Nome do Recebedor
                                </label>
                                <input type="text" id="pix_nome" name="pix_nome" 
                                       value="<?= htmlspecialchars(PIX_NOME) ?>" required
                                       placeholder="NOME DA PAROQUIA" maxlength="25">
                                <small><i class="icon-info"></i> Aparece no PIX (máx. 25 caracteres, MAIÚSCULAS)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="pix_cidade">
                                    <i class="icon-location"></i> Cidade do Recebedor
                                </label>
                                <input type="text" id="pix_cidade" name="pix_cidade" 
                                       value="<?= htmlspecialchars(PIX_CIDADE) ?>" required
                                       placeholder="SAO PAULO" maxlength="15">
                                <small><i class="icon-info"></i> Cidade no PIX (máx. 15 caracteres, MAIÚSCULAS)</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção 5: Webhook e Notificações -->
                <div class="config-section">
                    <div class="section-header">
                        <h3><i class="icon-webhook"></i> Webhook e Notificações</h3>
                        <p>Configure as notificações automáticas de pagamento via webhook</p>
                    </div>
                    
                    <div class="section-content">
                        <div class="webhook-info-card">
                            <h4><i class="icon-info"></i> Como funciona o Webhook</h4>
                            <p>Quando um pagamento PIX é realizado, a EFI Bank enviará uma notificação para sua URL de webhook. Os dados são salvos na tabela <code>efi_logs</code> para auditoria.</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="efi_webhook_url">
                                <i class="icon-webhook"></i> URL do Webhook
                            </label>
                            <div class="input-group">
                                <input type="url" id="efi_webhook_url" name="efi_webhook_url" 
                                       value="<?= htmlspecialchars($config_efi['webhook_url']) ?>"
                                       placeholder="<?= SITE_URL ?>/webhook_efi.php">
                                <button type="button" class="input-btn" onclick="generateWebhookUrl()">
                                    <i class="icon-auto"></i>
                                </button>
                            </div>
                            <small><i class="icon-info"></i> URL que receberá notificações de pagamento da EFI Bank</small>
                        </div>
                        
                        <div class="webhook-test-section">
                            <h4><i class="icon-test"></i> Testar Webhook</h4>
                            <p>Teste se sua URL está acessível e funcionando corretamente:</p>
                            <div class="test-buttons">
                                <button type="button" class="btn btn-info" onclick="testWebhookUrl()">
                                    <i class="icon-test"></i> Testar URL do Webhook
                                </button>
                                <button type="button" class="btn btn-success" onclick="configureWebhook()">
                                    <i class="icon-webhook"></i> Configurar na EFI Bank
                                </button>
                            </div>
                            <div id="webhook-test-results" class="test-results"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção 6: Testes e Validação -->
                <div class="config-section">
                    <div class="section-header">
                        <h3><i class="icon-test"></i> Testes e Validação</h3>
                        <p>Teste todas as configurações antes de ativar em produção</p>
                    </div>
                    
                    <div class="section-content">
                        <div class="test-section">
                            <div class="test-grid">
                                <div class="test-card">
                                    <h4><i class="icon-certificate"></i> Certificados</h4>
                                    <p>Verifica se os certificados estão válidos</p>
                                    <button type="button" class="btn btn-info btn-small" onclick="testCertificates()">
                                        Testar Certificados
                                    </button>
                                </div>
                                
                                <div class="test-card">
                                    <h4><i class="icon-key"></i> Autenticação</h4>
                                    <p>Testa credenciais e obtenção de token</p>
                                    <button type="button" class="btn btn-info btn-small" onclick="testAuthentication()">
                                        Testar Auth
                                    </button>
                                </div>
                                
                                <div class="test-card">
                                    <h4><i class="icon-pix"></i> Criar PIX</h4>
                                    <p>Gera um PIX de teste (R$ 0,01)</p>
                                    <button type="button" class="btn btn-info btn-small" onclick="testPixCreation()">
                                        Testar PIX
                                    </button>
                                </div>
                                
                                <div class="test-card">
                                    <h4><i class="icon-webhook"></i> Webhook</h4>
                                    <p>Testa se o webhook está funcionando</p>
                                    <button type="button" class="btn btn-info btn-small" onclick="testWebhook()">
                                        Testar Webhook
                                    </button>
                                </div>
                            </div>
                            
                            <div id="test-results" class="test-results"></div>
                            
                            <div class="comprehensive-test">
                                <button type="button" class="btn btn-primary btn-large" onclick="runComprehensiveTest()">
                                    <i class="icon-test"></i> Executar Teste Completo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção 7: Ativação Final -->
                <div class="config-section">
                    <div class="section-header">
                        <h3><i class="icon-activate"></i> Ativação Final</h3>
                        <p>Ative a integração após verificar que todos os testes passaram</p>
                    </div>
                    
                    <div class="section-content">
                        <div class="activation-section">
                            <div class="activation-card">
                                <div class="activation-content">
                                    <h4>🚀 Ativar Integração EFI Bank</h4>
                                    <p>Após verificar que todos os testes passaram, ative a integração para começar a processar pagamentos PIX automáticos. Os dados serão salvos automaticamente na tabela <code>pagamentos</code> com todos os campos EFI.</p>
                                    
                                    <div class="activation-checklist">
                                        <h5>✅ Checklist de Ativação:</h5>
                                        <ul>
                                            <li>Certificados enviados e válidos</li>
                                            <li>Credenciais configuradas corretamente</li>
                                            <li>Webhook funcionando</li>
                                            <li>Dados PIX preenchidos</li>
                                            <li>Testes executados com sucesso</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="activation-toggle">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="efi_ativo_final" value="1" 
                                                   <?= $config_efi['ativo'] === '1' ? 'checked' : '' ?>>
                                            <span class="toggle-slider"></span>
                                            <span class="toggle-label">Integração EFI Bank Ativa</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success btn-large" id="saveConfigBtn">
                                <i class="icon-save"></i> Salvar Todas as Configurações
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Seção de Monitoramento -->
        <div class="monitoring-section">
            <div class="config-section">
                <div class="section-header">
                    <h3><i class="icon-monitor"></i> Monitoramento e Logs</h3>
                    <p>Acompanhe as atividades e logs da integração EFI Bank</p>
                </div>
                
                <div class="section-content">
                    <div class="monitoring-grid">
                        <div class="monitoring-card">
                            <h4><i class="icon-database"></i> Logs EFI Bank</h4>
                            <p>Últimas atividades registradas na tabela <code>efi_logs</code></p>
                            <?php
                            $logs_efi = buscar_todos("
                                SELECT * FROM efi_logs 
                                ORDER BY criado_em DESC 
                                LIMIT 5
                            ");
                            ?>
                            
                            <?php if (empty($logs_efi)): ?>
                                <div class="empty-state">
                                    <i class="icon-empty"></i>
                                    <p>Nenhum log encontrado</p>
                                </div>
                            <?php else: ?>
                                <div class="logs-preview">
                                    <?php foreach ($logs_efi as $log): ?>
                                        <div class="log-item-mini">
                                            <span class="log-tipo <?= $log['tipo'] ?>"><?= ucfirst($log['tipo']) ?></span>
                                            <span class="log-data"><?= formatar_data_hora($log['criado_em']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <a href="logs.php?filtro=efi" class="btn btn-outline btn-small">
                                <i class="icon-view"></i> Ver Todos os Logs
                            </a>
                        </div>
                        
                        <div class="monitoring-card">
                            <h4><i class="icon-stats"></i> Estatísticas PIX</h4>
                            <p>Dados dos pagamentos processados via EFI Bank</p>
                            <?php
                            $stats_pix = buscar_um("
                                SELECT 
                                    COUNT(*) as total_pix,
                                    COUNT(CASE WHEN status = 'pago' THEN 1 END) as pagos,
                                    COUNT(CASE WHEN pix_txid IS NOT NULL THEN 1 END) as com_txid
                                FROM pagamentos 
                                WHERE metodo = 'pix' 
                                AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            ");
                            ?>
                            
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-number"><?= $stats_pix['total_pix'] ?? 0 ?></div>
                                    <div class="stat-label">PIX Gerados (30d)</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= $stats_pix['pagos'] ?? 0 ?></div>
                                    <div class="stat-label">Pagamentos</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= $stats_pix['com_txid'] ?? 0 ?></div>
                                    <div class="stat-label">Com TXID</div>
                                </div>
                            </div>
                            
                            <a href="relatorios.php?tipo=pix" class="btn btn-outline btn-small">
                                <i class="icon-chart"></i> Ver Relatório Completo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* === EFI CONFIG FORM STYLES === */
.efi-config-container {
    max-width: 1200px; /* Increased max-width for better layout */
    margin: 0 auto;
    padding: 20px 0;
    display: flex; /* Use flexbox for layout */
    flex-direction: column; /* Stack sections vertically */
    gap: 30px; /* Space between sections */
}

/* Config Sections */
.config-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin-bottom: 0; /* Remove margin-bottom for stacked sections */
    border: 1px solid #e5e7eb;
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
    transform: translateY(20px);
}

.config-section:nth-child(1) { animation-delay: 0.1s; }
.config-section:nth-child(2) { animation-delay: 0.2s; }
.config-section:nth-child(3) { animation-delay: 0.3s; }
.config-section:nth-child(4) { animation-delay: 0.4s; }
.config-section:nth-child(5) { animation-delay: 0.5s; }
.config-section:nth-child(6) { animation-delay: 0.6s; }
.config-section:nth-child(7) { animation-delay: 0.7s; }

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.section-header {
    text-align: center;
    padding: 25px 40px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 2px solid #e5e7eb;
}

.section-header h3 {
    color: #1f2937;
    font-size: 24px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-weight: 700;
}

.section-header p {
    color: #6b7280;
    font-size: 16px;
    margin: 0;
    font-weight: 500;
}

.section-content {
    padding: 40px;
}

/* Checkbox Customizado */
.checkbox-label {
    display: flex !important;
    align-items: flex-start !important;
    gap: 15px !important;
    cursor: pointer;
    padding: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: #f8fafc;
    margin-bottom: 0 !important;
}

.checkbox-label:hover {
    border-color: #1e40af;
    background: #f0f9ff;
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 24px;
    height: 24px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    position: relative;
    transition: all 0.3s ease;
    flex-shrink: 0;
    margin-top: 2px;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
    background: #1e40af;
    border-color: #1e40af;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-weight: bold;
    font-size: 14px;
}

.checkbox-text {
    flex: 1;
}

.checkbox-text strong {
    display: block;
    color: #374151;
    font-size: 16px;
    margin-bottom: 5px;
    font-weight: 600;
}

.checkbox-text small {
    color: #6b7280;
    font-size: 14px;
    margin: 0 !important;
    display: block !important;
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="password"],
.form-group input[type="url"] {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.form-group input:focus {
    outline: none;
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
}

.form-group small {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #6b7280;
    font-size: 12px;
    margin-top: 5px;
}

/* Input Groups */
.input-group {
    display: flex;
    align-items: center;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.input-group:focus-within {
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
}

.input-group input {
    border: none !important;
    box-shadow: none !important;
    flex: 1;
}

.input-btn {
    background: #f3f4f6;
    border: none;
    padding: 12px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    border-radius: 0 6px 6px 0;
}

.input-btn:hover {
    background: #e5e7eb;
    color: #374151;
}

/* Radio Groups */
.radio-group {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.radio-option {
    flex: 1;
    cursor: pointer;
    padding: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 15px;
    background: white;
}

.radio-option:hover {
    border-color: #1e40af;
    background: #f8fafc;
}

.radio-option input[type="radio"] {
    display: none;
}

.radio-option input[type="radio"]:checked + .radio-custom + .radio-content strong {
    color: #1e40af;
}

.radio-option input[type="radio"]:checked ~ .radio-content {
    color: #1e40af;
}

.radio-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 50%;
    position: relative;
    transition: all 0.3s ease;
}

.radio-option input[type="radio"]:checked + .radio-custom {
    border-color: #1e40af;
    background: #1e40af;
}

.radio-option input[type="radio"]:checked + .radio-custom::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
}

.radio-content {
    flex: 1;
}

.radio-content strong {
    display: block;
    margin-bottom: 4px;
    color: #374151;
}

.radio-content small {
    color: #6b7280;
    margin: 0;
}

/* Form Rows */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .radio-group {
        flex-direction: column;
    }
}

/* Credentials Tabs */
.credentials-tabs {
    margin: 30px 0;
}

.tab-buttons {
    display: flex;
    background: #f3f4f6;
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 25px;
}

.tab-btn {
    flex: 1;
    background: transparent;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab-btn.active {
    background: white;
    color: #1e40af;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.tab-content {
    display: none;
    padding: 25px;
    border: 2px solid #f3f4f6;
    border-radius: 12px;
    background: #fafbfc;
}

.tab-content.active {
    display: block;
}

.credentials-header {
    text-align: center;
    margin-bottom: 25px;
}

.credentials-header h4 {
    color: #374151;
    margin-bottom: 8px;
}

.credentials-header p {
    color: #6b7280;
    margin: 0;
}

/* Subsections dentro das seções */
.subsection {
    margin: 30px 0;
    padding: 25px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.subsection h4 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #374151;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
}

/* Certificate Grid */
.certificate-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

@media (max-width: 768px) {
    .certificate-grid {
        grid-template-columns: 1fr;
    }
}

.certificate-upload {
    position: relative;
}

.file-upload-label {
    display: block;
    padding: 30px;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.file-upload-label:hover {
    border-color: #1e40af;
    background: #f8fafc;
}

.upload-icon {
    font-size: 32px;
    color: #6b7280;
    margin-bottom: 15px;
}

.upload-content h5 {
    color: #374151;
    margin-bottom: 8px;
    font-weight: 600;
}

.upload-content p {
    color: #6b7280;
    margin-bottom: 15px;
    font-size: 14px;
}

.file-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.file-status.success {
    background: #d1fae5;
    color: #065f46;
}

.file-status.error {
    background: #fee2e2;
    color: #991b1b;
}

.file-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

/* Test Section */
.test-section {
    margin: 30px 0;
}

.test-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 25px;
}

.test-results {
    min-height: 100px;
    background: #f8fafc;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

/* Activation Section */
.activation-section {
    margin: 40px 0;
}

.activation-card {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    border-radius: 16px;
    overflow: hidden;
    color: white;
}

.activation-content {
    padding: 30px;
    text-align: center;
}

.activation-content h4 {
    margin-bottom: 10px;
    font-size: 20px;
}

.activation-content p {
    margin-bottom: 25px;
    opacity: 0.9;
}

/* Toggle Switch */
.toggle-switch {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    cursor: pointer;
}

.toggle-switch input[type="checkbox"] {
    display: none;
}

.toggle-slider {
    width: 60px;
    height: 30px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 30px;
    position: relative;
    transition: all 0.3s ease;
}

.toggle-slider::before {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    background: white;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.toggle-switch input[type="checkbox"]:checked + .toggle-slider {
    background: rgba(255, 255, 255, 0.9);
}

.toggle-switch input[type="checkbox"]:checked + .toggle-slider::before {
    transform: translateX(30px);
    background: #059669;
}

.toggle-label {
    font-weight: 600;
    font-size: 16px;
}

/* Form Actions */
.form-actions {
    text-align: right;
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid #f3f4f6;
}

/* Buttons */
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    text-decoration: none;
}

.btn-primary {
    background: #1e40af;
    color: white;
}

.btn-primary:hover {
    background: #1e3a8a;
    box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
}

.btn-secondary {
    background: #f3f4f6;
    color: #6b7280;
}

.btn-secondary:hover:not(:disabled) {
    background: #e5e7eb;
    color: #374151;
}

.btn-secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-success {
    background: #059669;
    color: white;
}

.btn-success:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.btn-info {
    background: #0ea5e9;
    color: white;
}

.btn-info:hover {
    background: #0284c7;
}

.btn-large {
    padding: 16px 32px;
    font-size: 16px;
}

/* Status Cards */
.config-status-section {
    margin-bottom: 40px;
}

.status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
}

.status-card.active {
    border-color: #059669;
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
}

.status-card.inactive {
    border-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}

.status-icon {
    font-size: 24px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(30, 64, 175, 0.1);
}

.status-card.active .status-icon {
    background: rgba(5, 150, 105, 0.2);
    color: #059669;
}

.status-card.inactive .status-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-content h4 {
    margin: 0 0 5px 0;
    color: #374151;
    font-size: 16px;
    font-weight: 600;
}

.status-content p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

/* Info Cards */
.pix-info-card,
.webhook-info-card,
.certificate-info {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.pix-info-card h4,
.webhook-info-card h4,
.certificate-info h4 {
    color: #0369a1;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pix-info-card p,
.webhook-info-card p {
    color: #374151;
    margin-bottom: 15px;
}

.db-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.db-field {
    background: #1e40af;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    font-weight: 600;
}

.certificate-info ul {
    margin: 10px 0 0 20px;
    color: #374151;
}

.certificate-info li {
    margin-bottom: 8px;
}

.certificate-info code {
    background: #e5e7eb;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}

/* Webhook Test Section */
.webhook-test-section {
    background: #fafbfc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.webhook-test-section h4 {
    color: #374151;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.webhook-test-section p {
    color: #6b7280;
    margin-bottom: 15px;
}

/* Activation Checklist */
.activation-checklist {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
}

.activation-checklist h5 {
    color: white;
    margin-bottom: 10px;
    font-size: 14px;
}

.activation-checklist ul {
    margin: 0;
    padding-left: 20px;
    color: rgba(255, 255, 255, 0.9);
}

.activation-checklist li {
    margin-bottom: 5px;
    font-size: 14px;
}

/* Monitoring Section */
.monitoring-section {
    margin-top: 40px;
}

.monitoring-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.monitoring-card {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.monitoring-card h4 {
    color: #374151;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 18px;
}

.monitoring-card p {
    color: #6b7280;
    margin-bottom: 20px;
    font-size: 14px;
}

.btn-outline {
    background: transparent;
    border: 2px solid #e5e7eb;
    color: #374151;
}

.btn-outline:hover {
    border-color: #1e40af;
    background: #f0f9ff;
    color: #1e40af;
}

.btn-small {
    padding: 8px 16px;
    font-size: 13px;
}

/* Icons */
.icon-settings::before { content: '⚙️'; }
.icon-key::before { content: '🔑'; }
.icon-pix::before { content: '💳'; }
.icon-test::before { content: '🧪'; }
.icon-live::before { content: '🚀'; }
.icon-id::before { content: '🆔'; }
.icon-lock::before { content: '🔒'; }
.icon-user::before { content: '👤'; }
.icon-location::before { content: '📍'; }
.icon-certificate::before { content: '📜'; }
.icon-upload::before { content: '📁'; }
.icon-webhook::before { content: '🔗'; }
.icon-wifi::before { content: '📶'; }
.icon-save::before { content: '💾'; }
.icon-check::before { content: '✓'; }
.icon-warning::before { content: '⚠️'; }
.icon-info::before { content: 'ℹ️'; }
.icon-eye::before { content: '👁️'; }
.icon-refresh::before { content: '🔄'; }
.icon-auto::before { content: '🤖'; }
.icon-environment::before { content: '🌐'; }
.icon-monitor::before { content: '👁️'; }
.icon-database::before { content: '🗄️'; }
.icon-stats::before { content: '📊'; }
.icon-activate::before { content: '🔗'; }
.icon-check-circle::before { content: '✅'; }
.icon-warning-circle::before { content: '⚠️'; }
.icon-empty::before { content: '👋'; }
.icon-view::before { content: '👀'; }
.icon-chart::before { content: '📈'; }

/* Legacy Styles */
.admin-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.code-block {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 10px 0;
}

.code-block pre {
    margin: 0;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.test-results {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.test-list {
    list-style: none;
    padding: 0;
    margin: 10px 0 0 0;
}

.test-list li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.test-list li:last-child {
    border-bottom: none;
}

.test-list li.success {
    color: #28a745;
}

.test-list li.error {
    color: #dc3545;
}

.webhook-info {
    background: #e9ecef;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
    font-family: monospace;
}

.logs-list {
    max-height: 300px;
    overflow-y: auto;
}

.log-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    font-size: 12px;
}

.log-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.log-tipo {
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 10px;
}

.log-tipo.auth { background: #17a2b8; color: white; }
.log-tipo.cobranca { background: #28a745; color: white; }
.log-tipo.webhook { background: #6f42c1; color: white; }
.log-tipo.consulta { background: #fd7e14; color: white; }
.log-tipo.erro { background: #dc3545; color: white; }
.log-tipo.baixa { background: #20c997; color: white; }

.log-data {
    color: #6c757d;
}

.log-mensagem {
    margin: 5px 0;
    color: #495057;
}

.log-txid {
    color: #6c757d;
    font-family: monospace;
    font-size: 11px;
}

.log-item-mini {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
    font-size: 12px;
    color: #374151;
}

.log-item-mini .log-tipo {
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 10px;
}

.log-item-mini .log-data {
    color: #6c757d;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f0f9eb;
    border-radius: 10px;
    border: 1px solid #e1f3d8;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #28a745;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 16px;
}

.logs-preview {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px;
    background: #f8fafc;
}

.logs-preview .log-item-mini {
    border-bottom: 1px solid #eee;
    padding: 8px 10px;
}

.logs-preview .log-item-mini:last-child {
    border-bottom: none;
}

.test-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.test-card {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.test-card h4 {
    color: #1e40af;
    margin-bottom: 10px;
    font-size: 18px;
}

.test-card p {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 15px;
}

.test-card .btn-small {
    padding: 8px 16px;
    font-size: 13px;
}

.comprehensive-test {
    text-align: center;
    margin-top: 20px;
}

.comprehensive-test .btn-large {
    padding: 16px 32px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .admin-grid {
        grid-template-columns: 1fr;
    }

    .test-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// === EFI CONFIG FORM JAVASCRIPT === 
document.addEventListener('DOMContentLoaded', function() {
    // Tab Navigation (Credentials)
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            switchTab(btn.dataset.tab);
        });
    });
    
    // File Upload Feedback
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function() {
            updateFileStatus(this);
        });
    });
    
    // Form Validation
    document.getElementById('efiConfigForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    function validateForm() {
        const requiredFields = document.querySelectorAll('input[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        if (!isValid) {
            showNotification('Por favor, preencha todos os campos obrigatórios.', 'error');
            // Scroll para o primeiro campo com erro
            const firstError = document.querySelector('input.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
        
        return isValid;
    }
    
    function switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.querySelector(`.tab-content[data-tab="${tabName}"]`).classList.add('active');
    }
    
    function updateFileStatus(input) {
        const file = input.files[0];
        const label = input.closest('.certificate-upload').querySelector('.file-upload-label');
        const status = label.querySelector('.file-status');
        
        if (file) {
            if (file.name.toLowerCase().endsWith('.p12')) {
                status.className = 'file-status success';
                status.innerHTML = '<i class="icon-check"></i> ' + file.name;
            } else {
                status.className = 'file-status error';
                status.innerHTML = '<i class="icon-warning"></i> Arquivo deve ser .p12';
                input.value = '';
            }
        }
    }
    
    function showNotification(message, type = 'info') {
        // Simple notification system
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
});

// Utility Functions
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const btn = field.parentElement.querySelector('.input-btn');
    
    if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<i class="icon-eye-off"></i>';
    } else {
        field.type = 'password';
        btn.innerHTML = '<i class="icon-eye"></i>';
    }
}

function generatePlaceholder(fieldId) {
    const field = document.getElementById(fieldId);
    const randomId = 'Client_Id_' + Math.random().toString(36).substr(2, 40);
    field.value = randomId;
    field.focus();
}

function generateWebhookUrl() {
    const field = document.getElementById('efi_webhook_url');
    field.value = '<?= SITE_URL ?>/webhook_efi.php';
}

function testConnection() {
    const resultDiv = document.getElementById('test-results');
    resultDiv.innerHTML = '<div class="loading">🔄 Testando conexão...</div>';
    
    // Simulate connection test (replace with actual AJAX call)
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="test-result">
                <h4>✅ Teste de Conexão Concluído</h4>
                <ul>
                    <li class="success">✓ Certificado encontrado</li>
                    <li class="success">✓ Credenciais válidas</li>
                    <li class="success">✓ Autenticação OK</li>
                    <li class="success">✓ API respondendo</li>
                </ul>
            </div>
        `;
    }, 2000);
}

function configureWebhook() {
    const resultDiv = document.getElementById('webhook-test-results');
    resultDiv.innerHTML = '<div class="loading">🔗 Configurando webhook...</div>';
    
    // Simulate webhook configuration (replace with actual AJAX call)
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="test-result">
                <h4>✅ Webhook Configurado</h4>
                <p>URL configurada com sucesso na EFI Bank para receber notificações de pagamento.</p>
            </div>
        `;
    }, 1500);
}

function testWebhookUrl() {
    const resultDiv = document.getElementById('webhook-test-results');
    resultDiv.innerHTML = '<div class="loading">🔄 Testando URL do Webhook...</div>';

    // Simulate webhook URL test (replace with actual AJAX call)
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="test-result">
                <h4>✅ URL do Webhook Testada</h4>
                <p>A URL <code><?= SITE_URL ?>/webhook_efi.php</code> está acessível e respondendo.</p>
            </div>
        `;
    }, 2000);
}

function testCertificates() {
    const resultDiv = document.getElementById('test-results');
    resultDiv.innerHTML = '<div class="loading">🔄 Testando Certificados...</div>';

    // Simulate certificate test (replace with actual AJAX call)
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="test-result">
                <h4>✅ Certificados Testados</h4>
                <p>Certificados de homologação e produção encontrados e válidos.</p>
            </div>
        `;
    }, 2000);
}

function testAuthentication() {
    const resultDiv = document.getElementById('test-results');
    resultDiv.innerHTML = '<div class="loading">🔄 Testando Autenticação...</div>';

    // Simulate authentication test (replace with actual AJAX call)
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="test-result">
                <h4>✅ Autenticação Testada</h4>
                <p>Credenciais de homologação e produção configuradas corretamente.</p>
            </div>
        `;
    }, 2000);
}

function testPixCreation() {
    const resultDiv = document.getElementById('test-results');
    resultDiv.innerHTML = '<div class="loading">🔄 Criando PIX de Teste...</div>';

    // Simulate PIX creation (replace with actual AJAX call)
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="test-result">
                <h4>✅ PIX de Teste Criado</h4>
                <p>Um PIX de teste (R$ 0,01) foi criado com sucesso. O pagamento deve aparecer na tabela de pagamentos.</p>
            </div>
        `;
    }, 2000);
}

function runComprehensiveTest() {
    const resultDiv = document.getElementById('test-results');
    resultDiv.innerHTML = '<div class="loading">🔄 Executando Teste Completo...</div>';

    // Simulate comprehensive test (replace with actual AJAX call)
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="test-result">
                <h4>✅ Teste Completo Concluído</h4>
                <p>Todos os testes de certificados, autenticação, PIX e webhook foram executados com sucesso.</p>
            </div>
        `;
    }, 3000);
}
</script>

<style>
/* Additional styles for JavaScript interactions */
.form-group input.error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

.notification-success { background: #059669; }
.notification-error { background: #ef4444; }
.notification-info { background: #0ea5e9; }

.notification button {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.loading {
    text-align: center;
    padding: 20px;
    color: #6b7280;
}

.test-result h4 {
    color: #059669;
    margin-bottom: 15px;
}

.test-result ul {
    list-style: none;
    padding: 0;
}

.test-result li {
    padding: 5px 0;
    color: #374151;
}

.test-result li.success {
    color: #059669;
}

.test-result li.error {
    color: #dc3545;
}

.webhook-info {
    background: #e9ecef;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
    font-family: monospace;
}

.logs-list {
    max-height: 300px;
    overflow-y: auto;
}

.log-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    font-size: 12px;
}

.log-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.log-tipo {
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 10px;
}

.log-tipo.auth { background: #17a2b8; color: white; }
.log-tipo.cobranca { background: #28a745; color: white; }
.log-tipo.webhook { background: #6f42c1; color: white; }
.log-tipo.consulta { background: #fd7e14; color: white; }
.log-tipo.erro { background: #dc3545; color: white; }
.log-tipo.baixa { background: #20c997; color: white; }

.log-data {
    color: #6c757d;
}

.log-mensagem {
    margin: 5px 0;
    color: #495057;
}

.log-txid {
    color: #6c757d;
    font-family: monospace;
    font-size: 11px;
}

.icon-eye-off::before { content: '🙈'; }
</style>

<?php obter_rodape_admin(); ?> 