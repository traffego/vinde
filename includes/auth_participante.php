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
 * Fazer login do participante com CPF e senha
 */
function participante_fazer_login($cpf, $senha) {
    iniciar_sessao();
    
    // Sanitizar entrada
    $cpf = sanitizar_entrada($cpf);
    $senha = sanitizar_entrada($senha);
    
    // Remover formatação do CPF
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Validações básicas
    if (empty($cpf) || empty($senha)) {
        return ['sucesso' => false, 'mensagem' => 'CPF e senha são obrigatórios.'];
    }
    
    if (strlen($cpf) !== 11) {
        return ['sucesso' => false, 'mensagem' => 'CPF deve ter 11 dígitos.'];
    }
    
    // Buscar participante pelo CPF
    $participante = buscar_um("
        SELECT id, nome, cpf, email, whatsapp, senha, criado_em
        FROM participantes 
        WHERE cpf = ?
    ", [$cpf]);
    
    if (!$participante) {
        return ['sucesso' => false, 'mensagem' => 'CPF não encontrado. Você precisa criar uma conta primeiro.'];
    }
    
    // Verificar senha
    if (!password_verify($senha, $participante['senha'])) {
        // Log da tentativa de login falhou
        registrar_log('tentativa_login_participante_falhou', "CPF: {$cpf}");
        return ['sucesso' => false, 'mensagem' => 'Senha incorreta.'];
    }
    
    // Login bem-sucedido
    $_SESSION['participante_id'] = $participante['id'];
    $_SESSION['participante_cpf'] = $participante['cpf'];
    $_SESSION['participante_nome'] = $participante['nome'];
    $_SESSION['participante_email'] = $participante['email'];
    $_SESSION['participante_whatsapp'] = $participante['whatsapp'];
    $_SESSION['participante_ultimo_acesso'] = time();
    
    // Log do login bem-sucedido
    registrar_log('login_participante_realizado', "Participante: {$participante['nome']} (CPF: {$cpf})");
    
    return ['sucesso' => true, 'mensagem' => 'Login realizado com sucesso!'];
}

/**
 * Verificar se CPF existe no sistema
 */
function participante_cpf_existe($cpf) {
    // Sanitizar e limpar CPF
    $cpf = preg_replace('/[^0-9]/', '', sanitizar_entrada($cpf));
    
    if (strlen($cpf) !== 11) {
        return false;
    }
    
    $participante = buscar_um("SELECT id FROM participantes WHERE cpf = ?", [$cpf]);
    return $participante !== false;
}

/**
 * Criar novo participante com senha
 */
function participante_criar_conta($dados) {
    try {
        // Validações
        $erros = [];
        
        // Campos obrigatórios
        $campos_obrigatorios = ['nome', 'cpf', 'whatsapp', 'email', 'idade', 'cidade', 'senha'];
        foreach ($campos_obrigatorios as $campo) {
            if (empty($dados[$campo])) {
                $erros[] = "Campo '{$campo}' é obrigatório.";
            }
        }
        
        if (!empty($erros)) {
            return ['sucesso' => false, 'mensagem' => implode(' ', $erros)];
        }
        
        // Limpar e validar CPF
        $cpf = preg_replace('/[^0-9]/', '', $dados['cpf']);
        if (strlen($cpf) !== 11) {
            return ['sucesso' => false, 'mensagem' => 'CPF deve ter 11 dígitos.'];
        }
        
        // Verificar se CPF já existe
        if (participante_cpf_existe($cpf)) {
            return ['sucesso' => false, 'mensagem' => 'Este CPF já está cadastrado no sistema.'];
        }
        
        // Validar email
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            return ['sucesso' => false, 'mensagem' => 'Email inválido.'];
        }
        
        // Validar idade
        $idade = intval($dados['idade']);
        if ($idade < 1 || $idade > 120) {
            return ['sucesso' => false, 'mensagem' => 'Idade deve estar entre 1 e 120 anos.'];
        }
        
        // Validar senha
        if (strlen($dados['senha']) < 6) {
            return ['sucesso' => false, 'mensagem' => 'Senha deve ter pelo menos 6 caracteres.'];
        }
        
        // Preparar dados para inserção
        $participante_dados = [
            'nome' => sanitizar_entrada($dados['nome']),
            'cpf' => $cpf,
            'whatsapp' => preg_replace('/[^0-9]/', '', $dados['whatsapp']),
            'instagram' => sanitizar_entrada($dados['instagram'] ?? ''),
            'email' => sanitizar_entrada($dados['email']),
            'idade' => $idade,
            'cidade' => sanitizar_entrada($dados['cidade']),
            'estado' => sanitizar_entrada($dados['estado'] ?? 'SP'),
            'senha' => password_hash($dados['senha'], PASSWORD_DEFAULT)
        ];
        
        // Inserir participante
        $participante_id = inserir_registro('participantes', $participante_dados);
        
        if (!$participante_id) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar conta. Tente novamente.'];
        }
        
        // Log da criação
        registrar_log('participante_cadastrado', "Participante: {$dados['nome']} (CPF: {$cpf})");
        
        return [
            'sucesso' => true, 
            'mensagem' => 'Conta criada com sucesso!',
            'participante_id' => $participante_id
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao criar conta de participante: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno. Tente novamente mais tarde.'];
    }
}

/**
 * Fazer logout do participante
 */
function participante_fazer_logout() {
    iniciar_sessao();
    
    // Log do logout se o participante estava logado
    if (isset($_SESSION['participante_nome'])) {
        registrar_log('logout_participante_realizado', "Participante: {$_SESSION['participante_nome']}");
    }
    
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
 * Buscar inscrições do participante logado
 */
function obter_inscricoes_participante($participante_id) {
    return buscar_todos("
        SELECT 
            i.id as inscricao_id,
            i.status as status_inscricao,
            i.valor_pago,
            i.metodo_pagamento,
            i.data_inscricao,
            i.data_pagamento,
            e.id as evento_id,
            e.nome as evento_nome,
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
            p.nome as participante_nome,
            p.cpf as participante_cpf,
            p.qr_token,
            -- Campos compatíveis com sistema antigo
            i.status as status,
            e.nome as nome,
            i.participante_id,
            pg.status as pagamento_status,
            NULL as checkin_timestamp
        FROM inscricoes i
        JOIN eventos e ON i.evento_id = e.id
        JOIN participantes p ON i.participante_id = p.id
        LEFT JOIN pagamentos pg ON pg.inscricao_id = i.id
        WHERE i.participante_id = ? AND i.status != 'cancelada'
        ORDER BY e.data_inicio DESC
    ", [$participante_id]);
}

/**
 * Verificar se participante já está inscrito em um evento
 */
function participante_ja_inscrito($participante_id, $evento_id) {
    $inscricao = buscar_um("
        SELECT id FROM inscricoes 
        WHERE participante_id = ? AND evento_id = ? AND status = 'aprovada'
    ", [$participante_id, $evento_id]);
    
    return $inscricao !== false;
}

/**
 * Criar inscrição para participante em evento
 */
function criar_inscricao_participante($participante_id, $evento_id) {
    try {
        // Verificar se já está inscrito
        if (participante_ja_inscrito($participante_id, $evento_id)) {
            return ['sucesso' => false, 'mensagem' => 'Você já está inscrito neste evento.'];
        }
        
        // Verificar se evento existe e está ativo
        $evento = buscar_um("
            SELECT *, 
                   (limite_participantes - (
                       SELECT COUNT(*) 
                       FROM inscricoes 
                       WHERE evento_id = eventos.id AND status = 'aprovada'
                   )) as vagas_restantes
            FROM eventos 
            WHERE id = ? AND status = 'ativo'
        ", [$evento_id]);
        
        if (!$evento) {
            return ['sucesso' => false, 'mensagem' => 'Evento não encontrado ou inativo.'];
        }
        
        // Verificar vagas
        if ($evento['vagas_restantes'] <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Evento esgotado.'];
        }
        
        // Se já existe uma inscrição pendente, reutilizar e redirecionar para pagamento
        $inscricao_pendente = buscar_um("
            SELECT id FROM inscricoes 
            WHERE participante_id = ? AND evento_id = ? AND status = 'pendente'
        ", [$participante_id, $evento_id]);

        if ($inscricao_pendente) {
            $inscricao_id = $inscricao_pendente['id'];
            
            // Verificar se já existe pagamento pendente para esta inscrição
            $pagamento_existente = buscar_um("
                SELECT id FROM pagamentos WHERE inscricao_id = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1
            ", [$inscricao_id]);
            
            if (!$pagamento_existente && $evento['valor'] > 0) {
                // Criar novo pagamento pendente para a inscrição existente
                $txid = 'VINDE' . date('YmdHis') . str_pad($inscricao_id, 6, '0', STR_PAD_LEFT);
                $pagamento_dados = [
                    'participante_id' => $participante_id,
                    'inscricao_id' => $inscricao_id,
                    'valor' => $evento['valor'],
                    'status' => 'pendente',
                    'metodo' => 'pix',
                    'pix_txid' => $txid
                ];
                inserir_registro('pagamentos', $pagamento_dados);
            }
            
            return [
                'sucesso' => true,
                'mensagem' => 'Inscrição pendente encontrada. Redirecionando para pagamento.',
                'inscricao_id' => $inscricao_id,
                'redirect_to' => SITE_URL . '/pagamento.php?inscricao=' . $inscricao_id
            ];
        }

        // Se existe inscrição cancelada para este evento/participante, reabrir ao invés de criar nova
        $inscricao_cancelada = buscar_um("SELECT id FROM inscricoes WHERE participante_id = ? AND evento_id = ? AND status = 'cancelada'", [$participante_id, $evento_id]);
        if ($inscricao_cancelada) {
            $inscricao_id = $inscricao_cancelada['id'];
            executar("UPDATE inscricoes SET status = 'pendente', data_inscricao = NOW(), valor_pago = ? WHERE id = ?", [$evento['valor'], $inscricao_id]);

            $pagamento_existente = buscar_um("SELECT id FROM pagamentos WHERE inscricao_id = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1", [$inscricao_id]);
            if (!$pagamento_existente && $evento['valor'] > 0) {
                $txid = 'VINDE' . date('YmdHis') . str_pad($inscricao_id, 6, '0', STR_PAD_LEFT);
                $pagamento_dados = [
                    'participante_id' => $participante_id,
                    'inscricao_id' => $inscricao_id,
                    'valor' => $evento['valor'],
                    'status' => 'pendente',
                    'metodo' => 'pix',
                    'pix_txid' => $txid
                ];
                inserir_registro('pagamentos', $pagamento_dados);
            }

            return [
                'sucesso' => true,
                'mensagem' => 'Inscrição reaberta. Redirecionando para pagamento.',
                'inscricao_id' => $inscricao_id,
                'redirect_to' => SITE_URL . '/pagamento.php?inscricao=' . $inscricao_id
            ];
        }

        // Criar nova inscrição
        $inscricao_dados = [
            'participante_id' => $participante_id,
            'evento_id' => $evento_id,
            'status' => 'pendente',
            'valor_pago' => $evento['valor']
        ];
        
        $inscricao_id = inserir_registro('inscricoes', $inscricao_dados);
        
        if (!$inscricao_id) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao processar inscrição. Tente novamente.'];
        }
        
        // Determinar próximo passo baseado no valor do evento
        if ($evento['valor'] > 0) {
            // Criar pagamento e redirecionar para pagamento
            $txid = 'VINDE' . date('YmdHis') . str_pad($inscricao_id, 6, '0', STR_PAD_LEFT);
            
            $pagamento_dados = [
                'participante_id' => $participante_id,
                'inscricao_id' => $inscricao_id,
                'valor' => $evento['valor'],
                'status' => 'pendente',
                'metodo' => 'pix',
                'pix_txid' => $txid
            ];
            
            inserir_registro('pagamentos', $pagamento_dados);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Inscrição realizada com sucesso!',
                'inscricao_id' => $inscricao_id,
                'redirect_to' => SITE_URL . '/pagamento.php?inscricao=' . $inscricao_id
            ];
        } else {
            // Evento gratuito - aprovar automaticamente
            executar("UPDATE inscricoes SET status = 'aprovada' WHERE id = ?", [$inscricao_id]);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Inscrição realizada com sucesso!',
                'inscricao_id' => $inscricao_id,
                'redirect_to' => SITE_URL . '/confirmacao.php?inscricao=' . $inscricao_id
            ];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao criar inscrição: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno. Tente novamente mais tarde.'];
    }
}

/**
 * Gerar QR Code para check-in do participante
 */
function gerar_qr_checkin($participante_id, $evento_id) {
    // Verificar se participante está inscrito no evento
    $inscricao = buscar_um("
        SELECT 
            i.id as inscricao_id,
            i.status,
            p.nome,
            p.cpf,
            p.qr_token,
            e.nome as evento_nome,
            e.data_inicio,
            e.local
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        WHERE i.participante_id = ? AND i.evento_id = ? AND i.status IN ('pendente', 'aprovada')
    ", [$participante_id, $evento_id]);
    
    if (!$inscricao) {
        return null;
    }
    
    // Gerar QR token se não existir
    if (empty($inscricao['qr_token'])) {
        $qr_token = gerar_string_aleatoria(32);
        
        // Atualizar na tabela participantes
        executar("UPDATE participantes SET qr_token = ? WHERE id = ?", [$qr_token, $participante_id]);
        
        $inscricao['qr_token'] = $qr_token;
    }
    
    // Dados do QR Code para check-in
    $qr_data = [
        'type' => 'checkin',
        'inscricao_id' => $inscricao['inscricao_id'],
        'participante_id' => $participante_id,
        'evento_id' => $evento_id,
        'token' => $inscricao['qr_token'],
        'evento_nome' => $inscricao['evento_nome'],
        'participante_nome' => $inscricao['nome'],
        'data_evento' => $inscricao['data_inicio'],
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

/**
 * Função para compatibilidade com sistema antigo
 * @deprecated Use participante_fazer_login($cpf, $senha) instead
 */
function participante_fazer_login_antigo($cpf, $whatsapp) {
    return participante_fazer_login($cpf, $whatsapp);
}

?> 