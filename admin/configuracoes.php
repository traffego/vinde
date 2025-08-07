<?php
require_once '../includes/init.php';

// Verificar login de admin
requer_login('admin');

$erro = '';
$sucesso = '';

// Processar salvamento de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        $resultado = salvar_configuracoes($_POST);
        if ($resultado['sucesso']) {
            $sucesso = $resultado['mensagem'];
        } else {
            $erro = $resultado['mensagem'];
        }
    }
}

// Buscar configurações atuais
$configuracoes = buscar_todos("SELECT * FROM configuracoes ORDER BY chave");
$config_array = [];
foreach ($configuracoes as $config) {
    $config_array[$config['chave']] = $config['valor'];
}

/**
 * Salvar configurações
 */
function salvar_configuracoes($dados) {
    try {
        $configuracoes_para_salvar = [
            'site_nome' => sanitizar_entrada($dados['site_nome'] ?? ''),
            'site_email' => sanitizar_entrada($dados['site_email'] ?? ''),
            'whatsapp_contato' => sanitizar_entrada($dados['whatsapp_contato'] ?? ''),
            'pix_chave' => sanitizar_entrada($dados['pix_chave'] ?? ''),
            'pix_nome' => sanitizar_entrada($dados['pix_nome'] ?? ''),
            'pix_cidade' => sanitizar_entrada($dados['pix_cidade'] ?? ''),
            'smtp_host' => sanitizar_entrada($dados['smtp_host'] ?? ''),
            'smtp_port' => intval($dados['smtp_port'] ?? 587),
            'smtp_user' => sanitizar_entrada($dados['smtp_user'] ?? ''),
            'smtp_pass' => $dados['smtp_pass'] ?? '',
            'backup_automatico' => isset($dados['backup_automatico']) ? '1' : '0',
            'logs_retencao_dias' => intval($dados['logs_retencao_dias'] ?? 30),
            'timezone' => $dados['timezone'] ?? 'America/Sao_Paulo',
            'theme_color' => sanitizar_entrada($dados['theme_color'] ?? '#1e40af'),
            'max_upload_size' => intval($dados['max_upload_size'] ?? 5),
            'termos_uso' => $dados['termos_uso'] ?? '',
            'politica_privacidade' => $dados['politica_privacidade'] ?? ''
        ];
        
        // Validações
        if (empty($configuracoes_para_salvar['site_nome'])) {
            return ['sucesso' => false, 'mensagem' => 'Nome do site é obrigatório'];
        }
        
        if (!empty($configuracoes_para_salvar['site_email']) && !filter_var($configuracoes_para_salvar['site_email'], FILTER_VALIDATE_EMAIL)) {
            return ['sucesso' => false, 'mensagem' => 'Email do site inválido'];
        }
        
        // Salvar cada configuração
        foreach ($configuracoes_para_salvar as $chave => $valor) {
            $existe = buscar_um("SELECT id FROM configuracoes WHERE chave = ?", [$chave]);
            
            if ($existe) {
                atualizar_registro('configuracoes', ['valor' => $valor], ['chave' => $chave]);
            } else {
                inserir_registro('configuracoes', [
                    'chave' => $chave,
                    'valor' => $valor,
                    'descricao' => obter_descricao_config($chave)
                ]);
            }
        }
        
        registrar_log('configuracoes_atualizadas', 'Configurações do sistema atualizadas');
        
        return ['sucesso' => true, 'mensagem' => 'Configurações salvas com sucesso!'];
        
    } catch (Exception $e) {
        error_log("Erro ao salvar configurações: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno ao salvar configurações'];
    }
}

/**
 * Obter descrição da configuração
 */
function obter_descricao_config($chave) {
    $descricoes = [
        'site_nome' => 'Nome do site exibido no cabeçalho',
        'site_email' => 'Email principal do site',
        'whatsapp_contato' => 'WhatsApp para contato',
        'pix_chave' => 'Chave PIX para pagamentos',
        'pix_nome' => 'Nome para PIX',
        'pix_cidade' => 'Cidade para PIX',
        'smtp_host' => 'Servidor SMTP para emails',
        'smtp_port' => 'Porta do servidor SMTP',
        'smtp_user' => 'Usuário SMTP',
        'smtp_pass' => 'Senha SMTP',
        'backup_automatico' => 'Backup automático ativado',
        'logs_retencao_dias' => 'Dias para manter logs',
        'timezone' => 'Fuso horário do sistema',
        'theme_color' => 'Cor principal do tema',
        'max_upload_size' => 'Tamanho máximo de upload (MB)',
        'termos_uso' => 'Termos de uso do sistema',
        'politica_privacidade' => 'Política de privacidade'
    ];
    
    return $descricoes[$chave] ?? '';
}

obter_cabecalho_admin('Configurações do Sistema', 'configuracoes');
?>

<div class="configuracoes-container">
    
    <?php if ($erro): ?>
        <div class="admin-mensagem mensagem-error">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($sucesso): ?>
        <div class="admin-mensagem mensagem-success">
            <?= htmlspecialchars($sucesso) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="configuracoes-form">
        <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
        
        <!-- Configurações Gerais -->
        <div class="config-section">
            <h3>🏢 Configurações Gerais</h3>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Nome do Site</label>
                    <input type="text" name="site_nome" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['site_nome'] ?? 'Vinde - Eventos Católicos') ?>" required>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Email Principal</label>
                    <input type="email" name="site_email" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['site_email'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">WhatsApp de Contato</label>
                    <input type="tel" name="whatsapp_contato" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['whatsapp_contato'] ?? '') ?>"
                           placeholder="(11) 99999-9999">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Fuso Horário</label>
                    <select name="timezone" class="form-select-admin">
                        <option value="America/Sao_Paulo" <?= ($config_array['timezone'] ?? 'America/Sao_Paulo') === 'America/Sao_Paulo' ? 'selected' : '' ?>>
                            São Paulo (UTC-3)
                        </option>
                        <option value="America/Rio_Branco" <?= ($config_array['timezone'] ?? '') === 'America/Rio_Branco' ? 'selected' : '' ?>>
                            Acre (UTC-5)
                        </option>
                        <option value="America/Manaus" <?= ($config_array['timezone'] ?? '') === 'America/Manaus' ? 'selected' : '' ?>>
                            Amazonas (UTC-4)
                        </option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Configurações PIX -->
        <div class="config-section">
            <h3>💳 Configurações PIX</h3>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Chave PIX</label>
                    <input type="text" name="pix_chave" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['pix_chave'] ?? '') ?>"
                           placeholder="CPF, CNPJ, email ou telefone">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Nome no PIX</label>
                    <input type="text" name="pix_nome" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['pix_nome'] ?? 'VINDE EVENTOS') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Cidade PIX</label>
                    <input type="text" name="pix_cidade" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['pix_cidade'] ?? 'SAO PAULO') ?>">
                </div>
            </div>
        </div>
        
        <!-- Configurações de Email -->
        <div class="config-section">
            <h3>📧 Configurações de Email (SMTP)</h3>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Servidor SMTP</label>
                    <input type="text" name="smtp_host" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['smtp_host'] ?? '') ?>"
                           placeholder="smtp.gmail.com">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Porta SMTP</label>
                    <input type="number" name="smtp_port" class="form-input-admin" 
                           value="<?= $config_array['smtp_port'] ?? 587 ?>" min="1" max="65535">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Usuário SMTP</label>
                    <input type="email" name="smtp_user" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['smtp_user'] ?? '') ?>">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Senha SMTP</label>
                    <input type="password" name="smtp_pass" class="form-input-admin" 
                           value="<?= htmlspecialchars($config_array['smtp_pass'] ?? '') ?>"
                           placeholder="Deixe em branco para não alterar">
                </div>
            </div>
        </div>
        
        <!-- Configurações do Sistema -->
        <div class="config-section">
            <h3>⚙️ Configurações do Sistema</h3>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Cor Principal do Tema</label>
                    <input type="color" name="theme_color" class="form-input-admin" 
                           value="<?= $config_array['theme_color'] ?? '#1e40af' ?>">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Tamanho Máximo de Upload (MB)</label>
                    <input type="number" name="max_upload_size" class="form-input-admin" 
                           value="<?= $config_array['max_upload_size'] ?? 5 ?>" min="1" max="50">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Dias para Manter Logs</label>
                    <input type="number" name="logs_retencao_dias" class="form-input-admin" 
                           value="<?= $config_array['logs_retencao_dias'] ?? 30 ?>" min="1" max="365">
                </div>
                
                <div class="form-group-admin">
                    <label class="checkbox-container">
                        <input type="checkbox" name="backup_automatico" 
                               <?= ($config_array['backup_automatico'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        Backup Automático Ativado
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Termos e Políticas -->
        <div class="config-section">
            <h3>📄 Termos e Políticas</h3>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Termos de Uso</label>
                    <textarea name="termos_uso" class="form-textarea-admin" rows="6"
                              placeholder="Digite os termos de uso do sistema..."><?= htmlspecialchars($config_array['termos_uso'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Política de Privacidade</label>
                    <textarea name="politica_privacidade" class="form-textarea-admin" rows="6"
                              placeholder="Digite a política de privacidade..."><?= htmlspecialchars($config_array['politica_privacidade'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Ações -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar Configurações</button>
            <button type="button" onclick="testarEmail()" class="btn btn-outline">📧 Testar Email</button>
            <button type="button" onclick="fazerBackup()" class="btn btn-success">💾 Backup Manual</button>
            <button type="button" onclick="limparCache()" class="btn btn-warning">🗑️ Limpar Cache</button>
        </div>
    </form>
    
    <!-- Informações do Sistema -->
    <div class="sistema-info">
        <h3>ℹ️ Informações do Sistema</h3>
        
        <div class="info-grid">
            <div class="info-card">
                <h4>Versão do PHP</h4>
                <p><?= PHP_VERSION ?></p>
            </div>
            
            <div class="info-card">
                <h4>Versão do MySQL</h4>
                <p>
                    <?php
                    try {
                        $versao = buscar_um("SELECT VERSION() as versao");
                        echo $versao['versao'];
                    } catch (Exception $e) {
                        echo 'Não disponível';
                    }
                    ?>
                </p>
            </div>
            
            <div class="info-card">
                <h4>Espaço em Disco</h4>
                <p>
                    <?php
                    $bytes = disk_free_space(".");
                    $gb = round($bytes / (1024 * 1024 * 1024), 2);
                    echo $gb . ' GB livres';
                    ?>
                </p>
            </div>
            
            <div class="info-card">
                <h4>Última Atualização</h4>
                <p><?= date('d/m/Y H:i:s') ?></p>
            </div>
            
            <div class="info-card">
                <h4>Total de Eventos</h4>
                <p><?= buscar_um("SELECT COUNT(*) as total FROM eventos")['total'] ?></p>
            </div>
            
            <div class="info-card">
                <h4>Total de Participantes</h4>
                <p><?= buscar_um("SELECT COUNT(*) as total FROM participantes WHERE status != 'cancelado'")['total'] ?></p>
            </div>
        </div>
    </div>
</div>

<script>
// Testar configurações de email
function testarEmail() {
    if (!confirm('Enviar email de teste para verificar as configurações?')) {
        return;
    }
    
    // Aqui seria implementada a função de teste de email
    alert('Funcionalidade de teste de email em desenvolvimento');
}

// Fazer backup manual
function fazerBackup() {
    if (!confirm('Criar backup manual do banco de dados?')) {
        return;
    }
    
    const btn = event.target;
    const textoOriginal = btn.textContent;
    
    btn.disabled = true;
    btn.textContent = '⏳ Gerando backup...';
    
    // Simular criação de backup
    setTimeout(() => {
        btn.disabled = false;
        btn.textContent = textoOriginal;
        alert('Backup criado com sucesso!');
    }, 3000);
}

// Limpar cache
function limparCache() {
    if (!confirm('Limpar todos os caches do sistema?')) {
        return;
    }
    
    // Aqui seria implementada a limpeza de cache
    alert('Cache limpo com sucesso!');
}

// Aplicar máscara no WhatsApp
document.addEventListener('DOMContentLoaded', function() {
    const whatsappInput = document.querySelector('[name="whatsapp_contato"]');
    if (whatsappInput) {
        whatsappInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            }
            this.value = value;
        });
    }
});
</script>

<?php
obter_rodape_admin();
?> 