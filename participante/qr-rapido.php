<?php
/**
 * Acesso super rápido ao QR Code
 * Redireciona automaticamente para o QR do próximo evento do participante
 */

require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Verificar login
if (!participante_esta_logado()) {
    // Redirecionar para login com retorno
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$participante = obter_participante_logado();

// Buscar próximo evento do participante
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

$proximo_evento = null;

if ($tabela_inscricoes_existe && function_exists('obter_inscricoes_participante')) {
    // Sistema novo - buscar próximo evento aprovado
    $eventos = buscar_todos("
        SELECT 
            i.*,
            e.nome,
            e.data_inicio,
            e.horario_inicio,
            e.local,
            i.evento_id,
            i.status as status_inscricao
        FROM inscricoes i
        JOIN eventos e ON i.evento_id = e.id
        WHERE i.participante_id = ? AND i.status = 'aprovada'
        AND e.data_inicio >= CURDATE()
        ORDER BY e.data_inicio ASC, e.horario_inicio ASC
        LIMIT 1
    ", [$participante['id']]);
    
    $proximo_evento = !empty($eventos) ? $eventos[0] : null;
} else {
    // Sistema antigo
    $eventos = buscar_todos("
        SELECT 
            p.*,
            e.nome,
            e.data_inicio,
            e.horario_inicio,
            e.local,
            p.evento_id,
            p.status
        FROM participantes p 
        INNER JOIN eventos e ON p.evento_id = e.id 
        WHERE p.cpf = ? AND p.status != 'cancelado'
        AND e.data_inicio >= CURDATE()
        ORDER BY e.data_inicio ASC, e.horario_inicio ASC
        LIMIT 1
    ", [$participante['cpf']]);
    
    $proximo_evento = !empty($eventos) ? $eventos[0] : null;
}

// Se encontrou próximo evento, redirecionar
if ($proximo_evento) {
    header('Location: meu-qr.php?evento=' . $proximo_evento['evento_id']);
    exit;
}

// Se não encontrou próximo evento, buscar o mais recente
if ($tabela_inscricoes_existe && function_exists('obter_inscricoes_participante')) {
    $eventos_recentes = buscar_todos("
        SELECT 
            i.*,
            e.nome,
            e.data_inicio,
            e.horario_inicio,
            e.local,
            i.evento_id,
            i.status as status_inscricao
        FROM inscricoes i
        JOIN eventos e ON i.evento_id = e.id
        WHERE i.participante_id = ? AND i.status = 'aprovada'
        ORDER BY e.data_inicio DESC, e.horario_inicio DESC
        LIMIT 1
    ", [$participante['id']]);
    
    $evento_recente = !empty($eventos_recentes) ? $eventos_recentes[0] : null;
} else {
    $eventos_recentes = buscar_todos("
        SELECT 
            p.*,
            e.nome,
            e.data_inicio,
            e.horario_inicio,
            e.local,
            p.evento_id,
            p.status
        FROM participantes p 
        INNER JOIN eventos e ON p.evento_id = e.id 
        WHERE p.cpf = ? AND p.status != 'cancelado'
        ORDER BY e.data_inicio DESC, e.horario_inicio DESC
        LIMIT 1
    ", [$participante['cpf']]);
    
    $evento_recente = !empty($eventos_recentes) ? $eventos_recentes[0] : null;
}

// Se encontrou evento recente, redirecionar
if ($evento_recente) {
    header('Location: meu-qr.php?evento=' . $evento_recente['evento_id']);
    exit;
}

// Se não encontrou nenhum evento, redirecionar para página principal
header('Location: meu-qr.php');
exit;
?>