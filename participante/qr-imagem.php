<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Verificar login
if (!participante_esta_logado()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Não autorizado');
}

$participante = obter_participante_logado();

// Verificar se foi especificado um evento
if (!isset($_GET['evento'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Evento não especificado');
}

$evento_id = (int)$_GET['evento'];

// Verificar se o participante tem acesso a este evento
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

$tem_acesso = false;
if ($tabela_inscricoes_existe) {
    // Sistema novo
    $inscricao = buscar_um("
        SELECT i.*, e.nome as evento_nome
        FROM inscricoes i
        JOIN eventos e ON i.evento_id = e.id
        WHERE i.participante_id = ? AND i.evento_id = ? AND i.status = 'aprovada'
    ", [$participante['id'], $evento_id]);
    $tem_acesso = $inscricao !== false;
} else {
    // Sistema antigo
    $evento_participante = buscar_um("
        SELECT p.*, e.nome as evento_nome
        FROM participantes p
        JOIN eventos e ON p.evento_id = e.id
        WHERE p.cpf = ? AND p.evento_id = ? AND p.status != 'cancelado'
    ", [$participante['cpf'], $evento_id]);
    $tem_acesso = $evento_participante !== false;
}

if (!$tem_acesso) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso negado a este evento');
}

// Gerar QR Code
try {
    $qr_data = gerar_qr_checkin($participante['id'], $evento_id);
    
    if (!$qr_data) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Erro ao gerar QR Code');
    }
    
    // Definir tamanho (padrão 300px, máximo 500px)
    $tamanho = isset($_GET['size']) ? min(500, max(100, (int)$_GET['size'])) : 300;
    
    // Gerar URL da imagem usando API externa
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size={$tamanho}x{$tamanho}&data=" . urlencode($qr_data);
    
    // Se for solicitado download direto
    if (isset($_GET['download'])) {
        $evento_nome = $inscricao['evento_nome'] ?? $evento_participante['evento_nome'] ?? 'evento';
        $filename = 'qr-checkin-' . preg_replace('/[^a-zA-Z0-9]/', '-', $evento_nome) . '.png';
        
        // Buscar a imagem e servir para download
        $image_data = file_get_contents($qr_url);
        if ($image_data) {
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($image_data));
            echo $image_data;
            exit;
        }
    }
    
    // Redirecionar para a imagem ou exibir inline
    if (isset($_GET['inline'])) {
        // Buscar e exibir a imagem diretamente
        $image_data = file_get_contents($qr_url);
        if ($image_data) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600'); // Cache por 1 hora
            echo $image_data;
            exit;
        }
    } else {
        // Redirecionar para a API externa
        header('Location: ' . $qr_url);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Erro ao gerar QR imagem: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Erro interno do servidor');
}

// Se chegou até aqui, algo deu errado
header('HTTP/1.1 500 Internal Server Error');
exit('Erro ao processar solicitação');
?>