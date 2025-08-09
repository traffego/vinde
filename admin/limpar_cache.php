<?php
/**
 * Limpar Cache - Sistema de limpeza bruta de cache
 * Para garantir que alterações estéticas sejam vistas imediatamente
 */

require_once '../includes/init.php';
require_once '../includes/auth.php';

// Verificar se usuário está logado como admin
verificar_admin();

$resultado = ['sucesso' => false, 'mensagem' => ''];
$acoes_executadas = [];

// Processar limpeza se solicitado
if ($_POST && isset($_POST['limpar_cache'])) {
    try {
        // 1. Limpar cache do PHP (OPcache)
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $acoes_executadas[] = '✅ OPcache limpo';
        } else {
            $acoes_executadas[] = '⚠️ OPcache não disponível';
        }

        // 2. Limpar arquivos temporários do sistema
        $temp_dirs = [
            '../tmp/',
            '../cache/',
            '../uploads/temp/',
            '../assets/cache/'
        ];

        foreach ($temp_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                $deleted = 0;
                foreach ($files as $file) {
                    if (is_file($file) && !in_array(basename($file), ['.htaccess', 'index.html'])) {
                        unlink($file);
                        $deleted++;
                    }
                }
                if ($deleted > 0) {
                    $acoes_executadas[] = "✅ {$deleted} arquivo(s) temporário(s) removido(s) de " . basename($dir);
                }
            }
        }

        // 3. Forçar reload de CSS/JS alterando timestamp nos arquivos
        $css_files = [
            '../assets/css/style.css',
            '../assets/css/admin.css',
            '../assets/css/checkout.css'
        ];

        $js_files = [
            '../assets/js/main.js',
            '../assets/js/admin.js'
        ];

        $timestamp = '?v=' . time();
        
        // Criar arquivo de versão para forçar reload
        $version_file = '../assets/version.txt';
        file_put_contents($version_file, time());
        $acoes_executadas[] = '✅ Timestamp de versão atualizado';

        // 4. Limpar session cache se existir
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Não destruir sessão, apenas limpar cache específico
            if (isset($_SESSION['cache'])) {
                unset($_SESSION['cache']);
                $acoes_executadas[] = '✅ Cache de sessão limpo';
            }
        }

        // 5. Headers para forçar reload do navegador
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 6. Limpar cache de banco (se aplicável)
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
            $acoes_executadas[] = '✅ APCu cache limpo';
        }

        $resultado['sucesso'] = true;
        $resultado['mensagem'] = 'Cache limpo com sucesso!';
        
    } catch (Exception $e) {
        $resultado['sucesso'] = false;
        $resultado['mensagem'] = 'Erro ao limpar cache: ' . $e->getMessage();
        $acoes_executadas[] = '❌ Erro: ' . $e->getMessage();
    }
}

// Se foi uma requisição AJAX, retornar JSON
if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'sucesso' => $resultado['sucesso'],
        'mensagem' => $resultado['mensagem'],
        'acoes' => $acoes_executadas
    ]);
    exit;
}

obter_cabecalho_admin('Limpar Cache', 'cache');
?>

<div class="admin-content-header">
    <h1>🧹 Limpar Cache</h1>
    <p>Force a atualização imediata de alterações estéticas e funcionais</p>
</div>

<div class="cache-container">
    <?php if ($resultado['mensagem']): ?>
        <div class="alert alert-<?= $resultado['sucesso'] ? 'success' : 'error' ?>">
            <?= htmlspecialchars($resultado['mensagem']) ?>
        </div>
    <?php endif; ?>

    <div class="cache-card">
        <div class="cache-header">
            <h2>🚀 Limpeza Bruta de Cache</h2>
            <p>Esta ação irá:</p>
        </div>

        <div class="cache-actions-list">
            <ul>
                <li>🗂️ Limpar OPcache do PHP</li>
                <li>🗑️ Remover arquivos temporários</li>
                <li>🔄 Forçar reload de CSS e JavaScript</li>
                <li>💾 Limpar cache de sessão</li>
                <li>⚡ Definir headers anti-cache</li>
                <li>🏎️ Limpar APCu (se disponível)</li>
            </ul>
        </div>

        <?php if (!empty($acoes_executadas)): ?>
            <div class="acoes-executadas">
                <h3>Ações executadas:</h3>
                <ul>
                    <?php foreach ($acoes_executadas as $acao): ?>
                        <li><?= htmlspecialchars($acao) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="cache-form" class="cache-form">
            <input type="hidden" name="limpar_cache" value="1">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-danger btn-cache" id="btn-limpar">
                    🧹 Limpar Cache Agora
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                    🔄 Recarregar Página
                </button>
                
                <a href="<?= SITE_URL ?>/admin/" class="btn btn-outline">
                    ← Voltar ao Dashboard
                </a>
            </div>
        </form>

        <div class="cache-info">
            <h3>💡 Dicas importantes:</h3>
            <ul>
                <li><strong>Use após alterações em CSS/JS:</strong> Garante que mudanças visuais sejam vistas imediatamente</li>
                <li><strong>Após atualizações:</strong> Remove cache de código PHP antigo</li>
                <li><strong>Problemas de visualização:</strong> Força o navegador a baixar arquivos atualizados</li>
                <li><strong>Sem risco:</strong> Não afeta dados do banco ou configurações</li>
            </ul>
        </div>
    </div>
</div>

<style>
.cache-container {
    max-width: 800px;
    margin: 0 auto;
    padding: var(--espaco-lg);
}

.cache-card {
    background: var(--cor-branco);
    border-radius: var(--borda-radius-grande);
    padding: var(--espaco-xl);
    box-shadow: var(--sombra-media);
    border: 1px solid #e5e7eb;
}

.cache-header h2 {
    color: var(--cor-primaria);
    margin-bottom: var(--espaco-sm);
    display: flex;
    align-items: center;
    gap: var(--espaco-sm);
}

.cache-actions-list ul {
    background: #f8fafc;
    border-radius: var(--borda-radius);
    padding: var(--espaco-lg);
    margin: var(--espaco-lg) 0;
}

.cache-actions-list li {
    padding: var(--espaco-sm) 0;
    border-bottom: 1px solid #e5e7eb;
}

.cache-actions-list li:last-child {
    border-bottom: none;
}

.acoes-executadas {
    background: #f0f9f4;
    border: 1px solid #86efac;
    border-radius: var(--borda-radius);
    padding: var(--espaco-lg);
    margin: var(--espaco-lg) 0;
}

.acoes-executadas h3 {
    color: #065f46;
    margin-bottom: var(--espaco-md);
}

.acoes-executadas li {
    padding: var(--espaco-xs) 0;
    color: #065f46;
}

.cache-form {
    margin: var(--espaco-xl) 0;
}

.form-actions {
    display: flex;
    gap: var(--espaco-md);
    flex-wrap: wrap;
    align-items: center;
}

.btn-cache {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    border: none;
    padding: var(--espaco-md) var(--espaco-lg);
    border-radius: var(--borda-radius);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1rem;
}

.btn-cache:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}

.btn-cache:active {
    transform: translateY(0);
}

.btn-cache.loading {
    opacity: 0.7;
    cursor: not-allowed;
}

.cache-info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: var(--borda-radius);
    padding: var(--espaco-lg);
    margin-top: var(--espaco-xl);
}

.cache-info h3 {
    color: #1e40af;
    margin-bottom: var(--espaco-md);
}

.cache-info li {
    padding: var(--espaco-xs) 0;
    color: #1e40af;
}

.alert {
    padding: var(--espaco-md);
    border-radius: var(--borda-radius);
    margin-bottom: var(--espaco-lg);
}

.alert-success {
    background: #f0f9f4;
    border: 1px solid #86efac;
    color: #065f46;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #dc2626;
}

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
document.getElementById('cache-form').addEventListener('submit', function(e) {
    const btn = document.getElementById('btn-limpar');
    btn.classList.add('loading');
    btn.textContent = '🔄 Limpando...';
    btn.disabled = true;
});
</script>

<?php
obter_rodape_admin();
?>
