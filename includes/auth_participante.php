<?php
// Funções de Autenticação para Participantes
// Arquivo: includes/auth_participante.php

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Verifica se participante está logado
 */
function participante_esta_logado() {
    iniciar_sessao();
    
    if (!isset($_SESSION['participante_id']) || !isset($_SESSION['participante_cpf'])) {
        return false;
    }
    
    // Verificar timeout da sessão
    if (isset($_SESSION['participante_ultimo_acesso'])) {
        if (time() - $_SESSION['participante_ultimo_acesso'] > LOGIN_TIMEOUT) {
            participante_fazer_logout();
            return false;
        }
    }
    
    // Atualizar último acesso
    $_SESSION['participante_ultimo_acesso'] = time();
    
    return true;
}

/**
 * Fazer login do participante com CPF e WhatsApp
 */
function participante_fazer_login($cpf, $whatsapp) {
    iniciar_sessao();
    
    // Sanitizar entrada
    $cpf = sanitizar_entrada($cpf);
    $whatsapp = sanitizar_entrada($whatsapp);
    
    // Remover formatação
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
    
    // Buscar participante
    $participante = buscar_um("
        SELECT p.*, e.nome as evento_nome, e.data_inicio, e.local, e.imagem as evento_imagem
        FROM participantes p
        JOIN eventos e ON p.evento_id = e.id
        WHERE p.cpf = ? AND p.whatsapp = ? AND p.status != 'cancelado'
        ORDER BY p.criado_em DESC
        LIMIT 1
    ", [$cpf, $whatsapp]);
    
    if (!$participante) {
        return ['sucesso' => false, 'mensagem' => 'CPF ou WhatsApp não encontrados nas inscrições.'];
    }
    
    // Login bem-sucedido
    $_SESSION['participante_id'] = $participante['id'];
    $_SESSION['participante_cpf'] = $participante['cpf'];
    $_SESSION['participante_nome'] = $participante['nome'];
    $_SESSION['participante_email'] = $participante['email'];
    $_SESSION['participante_whatsapp'] = $participante['whatsapp'];
    $_SESSION['participante_ultimo_acesso'] = time();
    
    return ['sucesso' => true, 'mensagem' => 'Login realizado com sucesso!'];
}

/**
 * Fazer logout do participante
 */
function participante_fazer_logout() {
    iniciar_sessao();
    
    // Limpar sessão do participante
    unset($_SESSION['participante_id']);
    unset($_SESSION['participante_cpf']);
    unset($_SESSION['participante_nome']);
    unset($_SESSION['participante_email']);
    unset($_SESSION['participante_whatsapp']);
    unset($_SESSION['participante_ultimo_acesso']);
}

/**
 * Obter dados do participante logado
 */
function obter_participante_logado() {
    if (!participante_esta_logado()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['participante_id'],
        'cpf' => $_SESSION['participante_cpf'],
        'nome' => $_SESSION['participante_nome'],
        'email' => $_SESSION['participante_email'],
        'whatsapp' => $_SESSION['participante_whatsapp']
    ];
}

/**
 * Buscar eventos do participante logado
 */
function obter_eventos_participante($participante_id) {
    return buscar_varios("
        SELECT 
            p.id as participante_id,
            p.status,
            p.tipo,
            p.qr_token,
            p.checkin_timestamp,
            p.criado_em as inscricao_em,
            e.id as evento_id,
            e.nome,
            e.slug,
            e.descricao,
            e.data_inicio,
            e.data_fim,
            e.horario_inicio,
            e.horario_fim,
            e.local,
            e.endereco,
            e.cidade,
            e.estado,
            e.valor,
            e.imagem,
            e.status as evento_status,
            pag.status as pagamento_status,
            pag.valor as pagamento_valor,
            pag.metodo as pagamento_metodo,
            pag.pago_em
        FROM participantes p
        JOIN eventos e ON p.evento_id = e.id
        LEFT JOIN pagamentos pag ON p.id = pag.participante_id
        WHERE p.id = ? AND p.status != 'cancelado'
        ORDER BY e.data_inicio DESC
    ", [$participante_id]);
}

/**
 * Gerar QR Code para check-in do participante
 */
function gerar_qr_checkin($participante_id, $evento_id) {
    // Buscar dados completos
    $dados = buscar_um("
        SELECT 
            p.id,
            p.nome,
            p.cpf,
            p.whatsapp,
            p.email,
            p.qr_token,
            p.status,
            e.id as evento_id,
            e.nome as evento_nome,
            e.data_inicio,
            e.horario_inicio,
            e.local
        FROM participantes p
        JOIN eventos e ON p.evento_id = e.id
        WHERE p.id = ? AND e.id = ?
    ", [$participante_id, $evento_id]);
    
    if (!$dados) {
        return null;
    }
    
    // Dados do QR Code para check-in
    $qr_data = [
        'type' => 'checkin',
        'participante_id' => $dados['id'],
        'token' => $dados['qr_token'],
        'evento_id' => $dados['evento_id'],
        'evento_nome' => $dados['evento_nome'],
        'participante_nome' => $dados['nome'],
        'data_evento' => $dados['data_inicio'],
        'timestamp' => time()
    ];
    
    return json_encode($qr_data);
}

/**
 * Requer login do participante
 */
function requer_login_participante() {
    if (!participante_esta_logado()) {
        redirecionar(SITE_URL . '/participante/login.php');
    }
}
?> 