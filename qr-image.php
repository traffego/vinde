<?php
require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

// Verificar se participante está logado
if (!participante_esta_logado()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Não autorizado');
}

// Obter parâmetros
$participante_id = $_GET['p'] ?? '';
$evento_id = $_GET['e'] ?? '';
$formato = $_GET['f'] ?? 'png';

if (!$participante_id || !$evento_id) {
    header('HTTP/1.1 400 Bad Request');
    exit('Parâmetros inválidos');
}

// Verificar se o participante logado pode acessar este QR
$participante_logado = obter_participante_logado();
if ($participante_logado['id'] != $participante_id) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso negado');
}

try {
    servir_qr_imagem($participante_id, $evento_id, $formato);
} catch (Exception $e) {
    error_log("Erro ao servir QR image: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Erro interno');
}
?>
