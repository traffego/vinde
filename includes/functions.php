<?php
// Funções Auxiliares do Sistema
// Arquivo: includes/functions.php

if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * FUNÇÕES DE VALIDAÇÃO E LIMPEZA
 */

/**
 * Remove formatação do CPF
 * @param string $cpf CPF formatado
 * @return string
 */
function limpar_cpf($cpf) {
    return preg_replace('/[^0-9]/', '', $cpf);
}

/**
 * Remove formatação do telefone
 * @param string $telefone Telefone formatado
 * @return string
 */
function limpar_telefone($telefone) {
    return preg_replace('/[^0-9]/', '', $telefone);
}

/**
 * Valida CPF brasileiro
 * @param string $cpf CPF para validar
 * @return bool
 */
function validar_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

/**
 * Valida email
 * @param string $email Email para validar
 * @return bool
 */
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida telefone brasileiro
 * @param string $telefone Telefone para validar
 * @return bool
 */
function validar_telefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    return preg_match('/^(\d{2})9?\d{8}$/', $telefone);
}

/**
 * Valida idade mínima
 * @param int $idade Idade para validar
 * @param int $minima Idade mínima permitida
 * @return bool
 */
function validar_idade($idade, $minima = 12) {
    return $idade >= $minima && $idade <= 120;
}

/**
 * FUNÇÕES DE FORMATAÇÃO
 */

/**
 * Formata CPF
 * @param string $cpf CPF para formatar
 * @return string
 */
function formatar_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

/**
 * Alias para formatar_cpf
 */
function formatarCpf($cpf) {
    return formatar_cpf($cpf);
}

/**
 * Formata telefone
 * @param string $telefone Telefone para formatar
 * @return string
 */
function formatar_telefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
    } elseif (strlen($telefone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    }
    return $telefone;
}

/**
 * Alias para formatar_telefone
 */
function formatarTelefone($telefone) {
    return formatar_telefone($telefone);
}

/**
 * Formata valor monetário
 * @param float $valor Valor para formatar
 * @return string
 */
function formatar_dinheiro($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Alias para formatar_dinheiro
 * @param float $valor Valor para formatar
 * @return string
 */
function formatar_moeda($valor) {
    return number_format($valor, 2, ',', '.');
}

/**
 * Formata data brasileira
 * @param string $data Data para formatar
 * @return string
 */
function formatar_data($data) {
    if (empty($data)) return '';
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

/**
 * Formata data e hora brasileira
 * @param string $datetime Data/hora para formatar
 * @return string
 */
function formatar_data_hora($datetime) {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * FUNÇÕES DE SEGURANÇA
 */

/**
 * Sanitiza entrada de dados
 * @param string $input Dados para sanitizar
 * @return string
 */
function sanitizar_entrada($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Gera hash seguro de senha
 * @param string $senha Senha para gerar hash
 * @return string
 */
function gerar_hash_senha($senha) {
    return password_hash($senha . SALT_KEY, PASSWORD_DEFAULT);
}

/**
 * Verifica senha contra hash
 * @param string $senha Senha para verificar
 * @param string $hash Hash armazenado
 * @return bool
 */
function verificar_senha($senha, $hash) {
    return password_verify($senha . SALT_KEY, $hash);
}

/**
 * Gera token CSRF
 * @return string
 */
function gerar_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica token CSRF
 * @param string $token Token para verificar
 * @return bool
 */
function verificar_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Gera string aleatória
 * @param int $tamanho Tamanho da string
 * @return string
 */
function gerar_string_aleatoria($tamanho = 32) {
    return bin2hex(random_bytes($tamanho / 2));
}

/**
 * FUNÇÕES DE ARQUIVOS
 */

/**
 * Upload de imagem
 * @param array $arquivo Arquivo $_FILES
 * @param string $destino Diretório de destino
 * @return string|false Nome do arquivo ou false
 */
function fazer_upload_imagem($arquivo, $destino = 'eventos') {
    if (!isset($arquivo['tmp_name']) || empty($arquivo['tmp_name'])) {
        return false;
    }
    
    // Verificar tipo
    if (!in_array($arquivo['type'], ALLOWED_IMAGE_TYPES)) {
        return false;
    }
    
    // Verificar tamanho
    if ($arquivo['size'] > UPLOAD_MAX_SIZE) {
        return false;
    }
    
    // Gerar nome único
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nome_arquivo = uniqid() . '_' . time() . '.' . $extensao;
    
    // Criar diretório se necessário
    $caminho_destino = UPLOAD_PATH . $destino . '/';
    if (!is_dir($caminho_destino)) {
        mkdir($caminho_destino, 0755, true);
    }
    
    // Mover arquivo
    $caminho_completo = $caminho_destino . $nome_arquivo;
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        return $destino . '/' . $nome_arquivo;
    }
    
    return false;
}

/**
 * FUNÇÕES DE LOGS
 */

/**
 * Registra atividade no sistema
 * @param string $acao Ação realizada
 * @param string $detalhes Detalhes da ação
 * @param string $usuario Usuário que realizou
 */
function registrar_log($acao, $detalhes = '', $usuario = null) {
    try {
        $dados = [
            'usuario' => $usuario ?: ($_SESSION['admin_user'] ?? 'sistema'),
            'acao' => $acao,
            'detalhes' => $detalhes,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        inserir_registro('logs_atividades', $dados);
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}



/**
 * FUNÇÕES DE UTILIDADE
 */

/**
 * Gera slug amigável para URL
 * @param string $texto Texto para converter
 * @return string
 */
function gerar_slug($texto) {
    $texto = strtolower($texto);
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $texto = preg_replace('/[^a-z0-9\-]/', '-', $texto);
    $texto = preg_replace('/-+/', '-', $texto);
    return trim($texto, '-');
}

/**
 * Calcula idade pela data de nascimento
 * @param string $data_nascimento Data de nascimento
 * @return int
 */
function calcular_idade($data_nascimento) {
    $hoje = new DateTime();
    $nascimento = new DateTime($data_nascimento);
    return $hoje->diff($nascimento)->y;
}

/**
 * Verifica se evento está esgotado
 * @param int $evento_id ID do evento
 * @return bool
 */
function verificar_evento_esgotado($evento_id) {
    $evento = buscar_um("SELECT limite_participantes FROM eventos WHERE id = ?", [$evento_id]);
    $total_inscritos = contar_registros('participantes', [
        'evento_id' => $evento_id, 
        'status' => PARTICIPANTE_INSCRITO
    ]);
    
    return $total_inscritos >= $evento['limite_participantes'];
}

/**
 * Envia notificação WhatsApp (simulada)
 * @param string $telefone Telefone de destino
 * @param string $mensagem Mensagem para enviar
 * @return bool
 */
function enviar_whatsapp($telefone, $mensagem) {
    // Simular envio - integração futura com API
    registrar_log('whatsapp_enviado', "Para: {$telefone} | Mensagem: " . substr($mensagem, 0, 100));
    return true;
}

/**
 * Simula envio de WhatsApp (alias)
 */
function simular_whatsapp($telefone, $mensagem) {
    return enviar_whatsapp($telefone, $mensagem);
}

/**
 * Obtém configuração do sistema
 * @param string $chave Chave da configuração
 * @param mixed $padrao Valor padrão se não encontrar
 * @return mixed
 */
function obter_configuracao($chave, $padrao = null) {
    static $cache = [];
    
    if (!isset($cache[$chave])) {
        $config = buscar_um("SELECT valor FROM configuracoes WHERE chave = ?", [$chave]);
        $cache[$chave] = $config ? $config['valor'] : $padrao;
    }
    
    return $cache[$chave];
}

/**
 * Define configuração do sistema
 * @param string $chave Chave da configuração
 * @param mixed $valor Valor da configuração
 * @return bool
 */
function definir_configuracao($chave, $valor) {
    $existe = buscar_um("SELECT id FROM configuracoes WHERE chave = ?", [$chave]);
    
    if ($existe) {
        return atualizar_registro('configuracoes', ['valor' => $valor], ['chave' => $chave]);
    } else {
        $id = inserir_registro('configuracoes', ['chave' => $chave, 'valor' => $valor]);
        return $id > 0;
    }
}

/**
 * Redireciona para uma URL
 * @param string $url URL de destino
 */
function redirecionar($url) {
    header("Location: {$url}");
    exit;
}

/**
 * Exibe mensagem de sucesso ou erro
 * @param string $mensagem Mensagem para exibir
 * @param string $tipo Tipo da mensagem (success, error, warning, info)
 */
function exibir_mensagem($mensagem, $tipo = 'info') {
    $_SESSION['mensagem'] = $mensagem;
    $_SESSION['mensagem_tipo'] = $tipo;
}

/**
 * Obtém e limpa mensagem da sessão
 * @return array|null Array com mensagem e tipo ou null
 */
function obter_mensagem() {
    if (isset($_SESSION['mensagem'])) {
        $mensagem = [
            'texto' => $_SESSION['mensagem'],
            'tipo' => $_SESSION['mensagem_tipo'] ?? 'info'
        ];
        unset($_SESSION['mensagem'], $_SESSION['mensagem_tipo']);
        return $mensagem;
    }
    return null;
}



?> 