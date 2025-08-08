<?php
// Limpeza profunda de cache da aplicação
// Uso: acessar este arquivo autenticado como admin

require_once __DIR__ . '/includes/init.php';

// Exigir login de admin para executar
requer_login('admin');

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

set_time_limit(120);

$resultados = [];
$inicio = microtime(true);

// Função utilitária: remover diretório recursivamente (conteúdo)
function limpar_diretorio($dir) {
    if (!is_dir($dir)) {
        return [false, 'Diretório inexistente'];
    }
    $ok = true; $msg = 'OK';
    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($ri as $file) {
        try {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        } catch (Throwable $e) {
            $ok = false; $msg = 'Falhas ao remover alguns arquivos: ' . $e->getMessage();
        }
    }
    return [$ok, $msg];
}

// 1) OPcache
try {
    $ok = false; $msg = 'OPcache indisponível';
    if (function_exists('opcache_reset')) {
        $ok = @opcache_reset();
        $msg = $ok ? 'OPcache reset efetuado' : 'Falhou ao resetar OPcache (verifique permissões/opcache.restrict_api)';
    }
    $resultados[] = ['etapa' => 'opcache_reset', 'ok' => $ok, 'mensagem' => $msg];
} catch (Throwable $e) {
    $resultados[] = ['etapa' => 'opcache_reset', 'ok' => false, 'mensagem' => $e->getMessage()];
}

// 2) APCu/APC
try {
    $ok = false; $msg = 'APCu/APC indisponível';
    if (function_exists('apcu_clear_cache')) {
        @apcu_clear_cache();
        $ok = true; $msg = 'APCu limpo';
    }
    if (function_exists('apc_clear_cache')) {
        @apc_clear_cache('opcode');
        @apc_clear_cache('user');
        $ok = true; $msg = trim(($msg !== 'APCu limpo' ? '' : $msg . '; ') . 'APC limpo');
    }
    $resultados[] = ['etapa' => 'apcu_apc_clear', 'ok' => $ok, 'mensagem' => $msg];
} catch (Throwable $e) {
    $resultados[] = ['etapa' => 'apcu_apc_clear', 'ok' => false, 'mensagem' => $e->getMessage()];
}

// 3) Realpath/stat cache
try {
    clearstatcache(true);
    $resultados[] = ['etapa' => 'clearstatcache', 'ok' => true, 'mensagem' => 'Cache de filesystem limpo'];
} catch (Throwable $e) {
    $resultados[] = ['etapa' => 'clearstatcache', 'ok' => false, 'mensagem' => $e->getMessage()];
}

// 4) Limpeza de diretórios de cache comuns (se existirem)
$dirs_cache = [
    __DIR__ . '/cache',
    __DIR__ . '/tmp/cache',
    __DIR__ . '/var/cache',
    __DIR__ . '/storage/cache',
];
foreach ($dirs_cache as $dir) {
    if (is_dir($dir)) {
        [$ok, $msg] = limpar_diretorio($dir);
        $resultados[] = ['etapa' => 'limpar_diretorio', 'alvo' => $dir, 'ok' => $ok, 'mensagem' => $msg];
    }
}

// 5) Limpar tokens/flags de cache em sessão (sem deslogar admin)
try {
    if (isset($_SESSION['efi_token'])) unset($_SESSION['efi_token']);
    if (isset($_SESSION['efi_token_expires'])) unset($_SESSION['efi_token_expires']);
    // Mensagens flash de UI (se houver)
    if (isset($_SESSION['mensagem'])) unset($_SESSION['mensagem']);
    if (isset($_SESSION['mensagem_tipo'])) unset($_SESSION['mensagem_tipo']);
    $resultados[] = ['etapa' => 'cleanup_sessao', 'ok' => true, 'mensagem' => 'Sessão limpa (tokens e mensagens)'];
} catch (Throwable $e) {
    $resultados[] = ['etapa' => 'cleanup_sessao', 'ok' => false, 'mensagem' => $e->getMessage()];
}

$duracao = round((microtime(true) - $inicio) * 1000);

echo "LIMPEZA DE CACHE - VINDE\n";
echo "Ambiente: " . (defined('AMBIENTE') ? AMBIENTE : 'desconhecido') . "\n";
echo "Hora: " . date('Y-m-d H:i:s') . "\n";
echo "Duração: {$duracao} ms\n";
echo str_repeat('-', 50) . "\n";
foreach ($resultados as $r) {
    $etapa = $r['etapa'];
    $ok = $r['ok'] ? 'OK' : 'FALHA';
    $alvo = isset($r['alvo']) ? " | Alvo: {$r['alvo']}" : '';
    $msg = isset($r['mensagem']) ? $r['mensagem'] : '';
    echo "{$etapa}{$alvo}: {$ok}" . ($msg ? " - {$msg}" : '') . "\n";
}
echo str_repeat('-', 50) . "\nConcluído.\n";

?>


