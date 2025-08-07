<?php
// Funções de Autenticação e Autorização
// Arquivo: includes/auth.php

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Inicia sessão segura
 */
function iniciar_sessao() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
        
        // Regenerar ID da sessão periodicamente
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Verifica se usuário está logado
 * @return bool
 */
function esta_logado() {
    iniciar_sessao();
    
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_user'])) {
        return false;
    }
    
    // Verificar timeout da sessão
    if (isset($_SESSION['ultimo_acesso'])) {
        if (time() - $_SESSION['ultimo_acesso'] > LOGIN_TIMEOUT) {
            fazer_logout();
            return false;
        }
    }
    
    // Atualizar último acesso
    $_SESSION['ultimo_acesso'] = time();
    
    return true;
}

/**
 * Verifica permissões do usuário
 * @param string $nivel_requerido Nível mínimo necessário
 * @return bool
 */
function verificar_permissao($nivel_requerido = 'operador') {
    if (!esta_logado()) {
        return false;
    }
    
    $nivel_usuario = $_SESSION['admin_nivel'] ?? 'operador';
    
    // Admin tem acesso total
    if ($nivel_usuario === 'admin') {
        return true;
    }
    
    // Operador só tem acesso a funções básicas
    if ($nivel_requerido === 'operador' && $nivel_usuario === 'operador') {
        return true;
    }
    
    return false;
}

/**
 * Força login (redireciona se não logado)
 * @param string $nivel_requerido Nível mínimo necessário
 */
function requer_login($nivel_requerido = 'operador') {
    if (!esta_logado()) {
        redirecionar(SITE_URL . '/admin/login.php');
    }
    
    if (!verificar_permissao($nivel_requerido)) {
        exibir_mensagem('Acesso negado. Permissões insuficientes.', 'error');
        redirecionar(SITE_URL . '/admin/');
    }
}

/**
 * Realiza login do usuário
 * @param string $username Nome de usuário
 * @param string $password Senha
 * @return bool|string True se sucesso, string com erro se falha
 */
function fazer_login($username, $password) {
    iniciar_sessao();
    
    // Sanitizar entrada
    $username = sanitizar_entrada($username);
    
    // Buscar usuário
    $usuario = buscar_um(
        "SELECT id, username, password, nome, email, nivel, ativo FROM usuarios WHERE username = ? AND ativo = 1",
        [$username]
    );
    
    if (!$usuario) {
        registrar_tentativa_login($username, false);
        return 'Usuário ou senha inválidos.';
    }
    
    // Verificar senha
    if (!verificar_senha($password, $usuario['password'])) {
        registrar_tentativa_login($username, false);
        return 'Usuário ou senha inválidos.';
    }
    
    // Login bem-sucedido
    $_SESSION['admin_id'] = $usuario['id'];
    $_SESSION['admin_user'] = $usuario['username'];
    $_SESSION['admin_nome'] = $usuario['nome'];
    $_SESSION['admin_email'] = $usuario['email'];
    $_SESSION['admin_nivel'] = $usuario['nivel'];
    $_SESSION['ultimo_acesso'] = time();
    
    // Registrar login
    registrar_log('login_realizado', "Usuário: {$username}");
    
    return true;
}

/**
 * Realiza logout do usuário
 */
function fazer_logout() {
    iniciar_sessao();
    
    if (isset($_SESSION['admin_user'])) {
        registrar_log('logout_realizado', "Usuário: " . $_SESSION['admin_user']);
    }
    
    // Destruir todas as variáveis da sessão
    $_SESSION = array();
    
    // Destruir cookie da sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir sessão
    session_destroy();
}

/**
 * Verifica se IP está bloqueado por tentativas excessivas
 * @param string $username Nome de usuário
 * @return bool
 */
function verificar_bloqueio_login($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agora = time();
    $limite_tempo = $agora - 900; // 15 minutos
    
    // Contar tentativas recentes
    $tentativas = contar_registros('logs_atividades', [
        'acao' => 'tentativa_login_falhou',
        'ip' => $ip
    ]);
    
    // Verificar se excedeu o limite
    return $tentativas >= LOGIN_MAX_ATTEMPTS;
}

/**
 * Registra tentativa de login
 * @param string $username Nome de usuário
 * @param bool $sucesso Se foi bem-sucedida
 */
function registrar_tentativa_login($username, $sucesso) {
    $acao = $sucesso ? 'tentativa_login_sucesso' : 'tentativa_login_falhou';
    registrar_log($acao, "Username: {$username}");
}

/**
 * Limpa tentativas de login do usuário
 * @param string $username Nome de usuário
 */
function limpar_tentativas_login($username) {
    // Implementar limpeza se necessário
    registrar_log('tentativas_login_limpas', "Username: {$username}");
}

/**
 * Cria novo usuário administrador
 * @param array $dados Dados do usuário
 * @return int|false ID do usuário criado ou false
 */
function criar_usuario($dados) {
    // Validar dados obrigatórios
    $campos_obrigatorios = ['username', 'password', 'nome', 'email', 'nivel'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($dados[$campo])) {
            return false;
        }
    }
    
    // Verificar se username já existe
    $existe = buscar_um("SELECT id FROM usuarios WHERE username = ?", [$dados['username']]);
    if ($existe) {
        return false;
    }
    
    // Preparar dados para inserção
    $usuario_dados = [
        'username' => sanitizar_entrada($dados['username']),
        'password' => gerar_hash_senha($dados['password']),
        'nome' => sanitizar_entrada($dados['nome']),
        'email' => sanitizar_entrada($dados['email']),
        'nivel' => $dados['nivel'],
        'ativo' => $dados['ativo'] ?? true
    ];
    
    try {
        $id = inserir_registro('usuarios', $usuario_dados);
        registrar_log('usuario_criado', "Username: {$dados['username']} | Nome: {$dados['nome']}");
        return $id;
    } catch (Exception $e) {
        error_log("Erro ao criar usuário: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza dados do usuário
 * @param int $id ID do usuário
 * @param array $dados Novos dados
 * @return bool
 */
function atualizar_usuario($id, $dados) {
    $dados_atualizacao = [];
    
    // Campos que podem ser atualizados
    $campos_permitidos = ['nome', 'email', 'nivel', 'ativo'];
    foreach ($campos_permitidos as $campo) {
        if (isset($dados[$campo])) {
            $dados_atualizacao[$campo] = sanitizar_entrada($dados[$campo]);
        }
    }
    
    // Atualizar senha se fornecida
    if (!empty($dados['password'])) {
        $dados_atualizacao['password'] = gerar_hash_senha($dados['password']);
    }
    
    if (empty($dados_atualizacao)) {
        return false;
    }
    
    try {
        $sucesso = atualizar_registro('usuarios', $dados_atualizacao, ['id' => $id]);
        if ($sucesso) {
            registrar_log('usuario_atualizado', "ID: {$id}");
        }
        return $sucesso;
    } catch (Exception $e) {
        error_log("Erro ao atualizar usuário: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove usuário (desativa)
 * @param int $id ID do usuário
 * @return bool
 */
function remover_usuario($id) {
    // Não permitir remoção do próprio usuário
    if ($id == $_SESSION['admin_id']) {
        return false;
    }
    
    try {
        $sucesso = atualizar_registro('usuarios', ['ativo' => false], ['id' => $id]);
        if ($sucesso) {
            registrar_log('usuario_removido', "ID: {$id}");
        }
        return $sucesso;
    } catch (Exception $e) {
        error_log("Erro ao remover usuário: " . $e->getMessage());
        return false;
    }
}

/**
 * Lista usuários ativos
 * @return array
 */
function listar_usuarios() {
    return buscar_todos(
        "SELECT id, username, nome, email, nivel, criado_em 
         FROM usuarios 
         WHERE ativo = 1 
         ORDER BY nome"
    );
}

/**
 * Obtém dados do usuário atual
 * @return array|false
 */
function obter_usuario_atual() {
    if (!esta_logado()) {
        return false;
    }
    
    return buscar_um(
        "SELECT id, username, nome, email, nivel FROM usuarios WHERE id = ?",
        [$_SESSION['admin_id']]
    );
}

/**
 * Atualiza último acesso do usuário
 */
function atualizar_ultimo_acesso() {
    if (esta_logado()) {
        $_SESSION['ultimo_acesso'] = time();
    }
}

/**
 * Verifica se precisa trocar senha
 * @return bool
 */
function precisa_trocar_senha() {
    // Implementar lógica de expiração de senha se necessário
    return false;
}

/**
 * Gera token de recuperação de senha
 * @param string $email Email do usuário
 * @return string|false Token gerado ou false
 */
function gerar_token_recuperacao($email) {
    $usuario = buscar_um("SELECT id FROM usuarios WHERE email = ? AND ativo = 1", [$email]);
    
    if (!$usuario) {
        return false;
    }
    
    $token = gerar_string_aleatoria(64);
    $expiracao = date('Y-m-d H:i:s', time() + 3600); // 1 hora
    
    // Salvar token (implementar tabela de tokens se necessário)
    registrar_log('token_recuperacao_gerado', "Email: {$email}");
    
    return $token;
}

?> 