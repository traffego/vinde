<?php
require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

// Debug mode para desenvolvimento
$debug_mode = is_debug_enabled() || isset($_GET['debug']);

$inscricao_id = $_GET['inscricao'] ?? '';
$erro = '';
$sucesso = '';

// Debug inicial
if ($debug_mode) {
    error_log("PAGAMENTO DEBUG: Inscrição ID = {$inscricao_id}");
}

// Validar inscricao_id
if (empty($inscricao_id) || !is_numeric($inscricao_id)) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: ID de inscrição inválido");
    }
    obter_cabecalho('Pagamento - ID Inválido');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>ID de Inscrição Inválido</h1>
            <p>O ID da inscrição não foi fornecido ou é inválido.</p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary">Voltar aos Eventos</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Verificar se usuário está logado
if (!participante_esta_logado()) {
    redirecionar(SITE_URL . '/participante/login.php');
}

$participante_logado = obter_participante_logado();

// Buscar dados da inscrição, participante, evento e pagamento
$inscricao = [];
$participante = [];
$evento = [];
$pagamento = [];

try {
    $dados = buscar_um("
        SELECT i.*, 
               p.nome as participante_nome, p.cpf as participante_cpf, 
               p.email as participante_email, p.whatsapp as participante_whatsapp,
               e.nome as evento_nome, e.data_inicio, e.horario_inicio, 
               e.local, e.cidade as evento_cidade, e.estado as evento_estado,
               e.valor as evento_valor,
               pag.id as pagamento_id, pag.valor as pagamento_valor, 
               pag.status as pagamento_status, pag.pix_txid, pag.pix_loc_id, 
               pag.pix_qrcode_data, pag.pix_qrcode_url, pag.pix_expires_at, 
               pag.criado_em as pagamento_criado
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pag ON i.id = pag.inscricao_id
        WHERE i.id = ? AND i.participante_id = ?
    ", [$inscricao_id, $participante_logado['id']]);

    if (!$dados) {
        throw new Exception("Inscrição não encontrada ou não pertence ao usuário logado");
    }

    // Separar dados em arrays organizados
    $inscricao = [
        'id' => $dados['id'],
        'status' => $dados['status'],
        'valor_pago' => $dados['valor_pago'],
        'data_inscricao' => $dados['data_inscricao']
    ];

    $participante = [
        'nome' => $dados['participante_nome'],
        'cpf' => $dados['participante_cpf'],
        'email' => $dados['participante_email'],
        'whatsapp' => $dados['participante_whatsapp']
    ];

    $evento = [
        'nome' => $dados['evento_nome'],
        'data_inicio' => $dados['data_inicio'],
        'horario_inicio' => $dados['horario_inicio'],
        'local' => $dados['local'],
        'cidade' => $dados['evento_cidade'],
        'estado' => $dados['evento_estado'],
        'valor' => $dados['evento_valor']
    ];

    $pagamento = [
        'id' => $dados['pagamento_id'],
        'valor' => $dados['pagamento_valor'],
        'status' => $dados['pagamento_status'],
        'pix_txid' => $dados['pix_txid'],
        'pix_loc_id' => $dados['pix_loc_id'],
        'pix_qrcode_data' => $dados['pix_qrcode_data'],
        'pix_qrcode_url' => $dados['pix_qrcode_url'],
        'pix_expires_at' => $dados['pix_expires_at'],
        'criado_em' => $dados['pagamento_criado']
    ];

    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Dados encontrados - Inscrição: {$inscricao['id']}, Status: {$inscricao['status']}, Pagamento: {$pagamento['status']}");
    }

} catch (Exception $e) {
    if ($debug_mode) {
        error_log("PAGAMENTO DEBUG: Erro ao buscar dados - " . $e->getMessage());
    }
    
    obter_cabecalho('Erro - Pagamento');
    ?>
    <div class="container">
        <div class="error-page">
            <h1>Inscrição Não Encontrada</h1>
            <p>A inscrição solicitada não foi encontrada ou não pertence a você.</p>
            <a href="<?= SITE_URL ?>/participante/" class="btn btn-primary">Ir para Área do Participante</a>
        </div>
    </div>
    <?php
    obter_rodape();
    exit;
}

// Verificar se pagamento já foi processado
if ($pagamento['status'] === 'pago') {
    redirecionar(SITE_URL . '/confirmacao.php?inscricao=' . $inscricao_id);
}

// Verificar se o evento é gratuito
if ($evento['valor'] <= 0) {
    // Evento gratuito - atualizar status e redirecionar
    atualizar_registro('inscricoes', ['status' => 'aprovada'], ['id' => $inscricao_id]);
    redirecionar(SITE_URL . '/confirmacao.php?inscricao=' . $inscricao_id);
}

// Processar geração/renovação de PIX se necessário
if (empty($pagamento['pix_qrcode_data']) || 
    ($pagamento['pix_expires_at'] && strtotime($pagamento['pix_expires_at']) < time())) {
    
    // Gerar novo PIX
    $txid = $pagamento['pix_txid'] ?: 'VINDE' . date('YmdHis') . str_pad($inscricao_id, 6, '0', STR_PAD_LEFT);
    $valor = $evento['valor'];
    
    // Verificar se EFI Bank está ativo
    $efi_ativo = obter_configuracao('efi_ativo', '0') === '1';
    $certificado_existe = file_exists(EFI_CERTIFICADO_PROD);
    
    if ($efi_ativo && $certificado_existe) {
        // Usar EFI Bank
        $descricao = "Inscrição: {$evento['nome']} - {$participante['nome']}";
        $cobranca_efi = efi_criar_cobranca_pix(
            $txid,
            $valor,
            $descricao,
            $participante['nome'],
            limpar_cpf($participante['cpf']),
            3600 // 1 hora de expiração
        );
        
        if ($cobranca_efi) {
            $dados_pagamento = [
                'pix_txid' => $txid,
                'pix_loc_id' => $cobranca_efi['loc']['id'] ?? null,
                'pix_expires_at' => date('Y-m-d H:i:s', time() + 3600)
            ];
            
            // Gerar QR Code da cobrança
            if (isset($cobranca_efi['loc']['id'])) {
                $qrcode_data = efi_gerar_qrcode($cobranca_efi['loc']['id']);
                if ($qrcode_data) {
                    $dados_pagamento['pix_qrcode_data'] = $qrcode_data['qrcode'];
                    $dados_pagamento['pix_qrcode_url'] = $qrcode_data['imagemQrcode'] ?? null;
                }
            }
            
            atualizar_registro('pagamentos', $dados_pagamento, ['id' => $pagamento['id']]);
            $pagamento = array_merge($pagamento, $dados_pagamento);
        }
    }
    
    // Fallback para PIX simples se EFI Bank não funcionou
    if (empty($pagamento['pix_qrcode_data'])) {
        $descricao = "Inscricao: " . substr($evento['nome'], 0, 20) . " - " . substr($participante['nome'], 0, 15);
        $cobranca_simples = criar_cobranca_pix_simples($inscricao_id, $valor, $descricao);
        
        if ($cobranca_simples) {
            $dados_pagamento = [
                'pix_txid' => $txid,
                'pix_qrcode_data' => $cobranca_simples['payload'],
                'pix_qrcode_url' => $cobranca_simples['qrcode_url'],
                'pix_expires_at' => $cobranca_simples['expires_at']
            ];
            
            atualizar_registro('pagamentos', $dados_pagamento, ['id' => $pagamento['id']]);
            $pagamento = array_merge($pagamento, $dados_pagamento);
            
            if ($debug_mode) {
                error_log("PAGAMENTO DEBUG: PIX simples gerado - TXID: {$txid}");
            }
        }
    }
}

// Calcular tempo restante para expiração
$tempo_expiracao = null;
if ($pagamento['pix_expires_at']) {
    $expira_timestamp = strtotime($pagamento['pix_expires_at']);
    $agora = time();
    $tempo_expiracao = max(0, $expira_timestamp - $agora);
}

obter_cabecalho('Pagamento - ' . $evento['nome']);
?>

<style>
/* Estilos específicos da página de pagamento */
.pagamento-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.pagamento-header {
    text-align: center;
    margin-bottom: 40px;
}

.pagamento-header h1 {
    color: var(--cor-primaria);
    margin-bottom: 10px;
}

.pagamento-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 40px;
    align-items: start;
}

@media (max-width: 768px) {
    .pagamento-layout {
        grid-template-columns: 1fr;
        gap: 30px;
    }
}

.pagamento-main {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.pagamento-sidebar {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    height: fit-content;
}

.pix-section {
    text-align: center;
}

.qr-code-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    margin: 20px 0;
    display: inline-block;
}

.qr-code-container img {
    display: block;
    margin: 0 auto;
    max-width: 250px;
    width: 100%;
}

.pix-code {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    font-family: monospace;
    font-size: 12px;
    word-break: break-all;
    margin: 15px 0;
}

.btn-copiar {
    background: var(--cor-primaria);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    margin: 10px 5px;
    transition: all 0.3s ease;
}

.btn-copiar:hover {
    background: var(--cor-primaria-dark);
}

.btn-copiar.copiado {
    background: #28a745;
}

.timer-expiracao {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
    text-align: center;
}

.timer-expiracao.expirado {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.resumo-pagamento h3 {
    color: var(--cor-primaria);
    margin-bottom: 20px;
    font-size: 18px;
}

.resumo-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.resumo-item:last-child {
    border-bottom: none;
    font-weight: 600;
    font-size: 18px;
    color: var(--cor-primaria);
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pendente {
    background: #fff3cd;
    color: #856404;
}

.status-aprovada {
    background: #d4edda;
    color: #155724;
}

.instrucoes-pix {
    background: #e7f3ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.instrucoes-pix h4 {
    color: #004085;
    margin-bottom: 15px;
}

.instrucoes-pix ol {
    margin: 0;
    padding-left: 20px;
}

.instrucoes-pix li {
    margin-bottom: 8px;
    color: #004085;
}

.verificacao-status {
    text-align: center;
    margin: 30px 0;
}

.btn-verificar {
    background: #17a2b8;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-verificar:hover {
    background: #138496;
}

.loading-spinner {
    display: none;
    margin: 20px auto;
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--cor-primaria);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<main class="pagamento-container">
    <div class="pagamento-header">
        <h1>Finalizar Pagamento</h1>
        <p>Complete seu pagamento para confirmar a inscrição</p>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <div class="pagamento-layout">
        <div class="pagamento-main">
            <div class="pix-section">
                <h2>Pagamento via PIX</h2>
                
                <?php if ($tempo_expiracao !== null): ?>
                    <div class="timer-expiracao" id="timer-container">
                        <div id="timer-text">
                            ⏰ Código expira em: <span id="countdown"><?= gmdate('i:s', $tempo_expiracao) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pagamento['pix_qrcode_url'])): ?>
                    <div class="qr-code-container">
                        <img src="<?= htmlspecialchars($pagamento['pix_qrcode_url']) ?>" 
                             alt="QR Code PIX" 
                             id="qr-code-img">
                    </div>
                <?php endif; ?>

                <?php if (!empty($pagamento['pix_qrcode_data'])): ?>
                    <div class="pix-code-section">
                        <p><strong>Ou copie o código PIX:</strong></p>
                        <div class="pix-code" id="pix-code">
                            <?= htmlspecialchars($pagamento['pix_qrcode_data']) ?>
                        </div>
                        <button type="button" class="btn-copiar" onclick="copiarPix()">
                            📋 Copiar Código PIX
                        </button>
                    </div>
                <?php endif; ?>

                <div class="instrucoes-pix">
                    <h4>Como pagar:</h4>
                    <ol>
                        <li>Abra o app do seu banco</li>
                        <li>Escolha a opção PIX</li>
                        <li>Escaneie o QR Code ou cole o código copiado</li>
                        <li>Confirme o pagamento</li>
                        <li>Aguarde a confirmação automática</li>
                    </ol>
                </div>

                <div class="verificacao-status">
                    <button type="button" class="btn-verificar" onclick="verificarPagamento()">
                        🔄 Verificar Pagamento
                    </button>
                    <div class="loading-spinner" id="loading-spinner"></div>
                </div>
            </div>
        </div>

        <div class="pagamento-sidebar">
            <div class="resumo-pagamento">
                <h3>Resumo da Inscrição</h3>
                
                <div class="resumo-item">
                    <span>Evento:</span>
                    <span><?= htmlspecialchars($evento['nome']) ?></span>
                </div>
                
                <div class="resumo-item">
                    <span>Data:</span>
                    <span><?= date('d/m/Y', strtotime($evento['data_inicio'])) ?></span>
                </div>
                
                <?php if ($evento['horario_inicio']): ?>
                    <div class="resumo-item">
                        <span>Horário:</span>
                        <span><?= date('H:i', strtotime($evento['horario_inicio'])) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="resumo-item">
                    <span>Local:</span>
                    <span><?= htmlspecialchars($evento['local']) ?></span>
                </div>
                
                <div class="resumo-item">
                    <span>Participante:</span>
                    <span><?= htmlspecialchars($participante['nome']) ?></span>
                </div>
                
                <div class="resumo-item">
                    <span>Status:</span>
                    <span class="status-badge status-<?= $inscricao['status'] ?>">
                        <?= ucfirst($inscricao['status']) ?>
                    </span>
                </div>
                
                <div class="resumo-item">
                    <span>Valor Total:</span>
                    <span>R$ <?= number_format($evento['valor'], 2, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Timer de expiração
<?php if ($tempo_expiracao !== null): ?>
let tempoRestante = <?= $tempo_expiracao ?>;

function atualizarTimer() {
    if (tempoRestante <= 0) {
        document.getElementById('timer-container').className = 'timer-expiracao expirado';
        document.getElementById('timer-text').innerHTML = '⚠️ Código PIX expirado - Recarregue a página para gerar um novo';
        return;
    }
    
    const minutos = Math.floor(tempoRestante / 60);
    const segundos = tempoRestante % 60;
    document.getElementById('countdown').textContent = 
        String(minutos).padStart(2, '0') + ':' + String(segundos).padStart(2, '0');
    
    tempoRestante--;
}

// Atualizar timer a cada segundo
setInterval(atualizarTimer, 1000);
<?php endif; ?>

// Função para copiar código PIX
function copiarPix() {
    const pixCode = document.getElementById('pix-code').textContent;
    navigator.clipboard.writeText(pixCode).then(function() {
        const btn = event.target;
        const textoOriginal = btn.textContent;
        btn.textContent = '✅ Copiado!';
        btn.classList.add('copiado');
        
        setTimeout(function() {
            btn.textContent = textoOriginal;
            btn.classList.remove('copiado');
        }, 2000);
    });
}

// Função para verificar status do pagamento
function verificarPagamento() {
    const spinner = document.getElementById('loading-spinner');
    const btn = event.target;
    
    spinner.style.display = 'block';
    btn.disabled = true;
    
    fetch('<?= SITE_URL ?>/api/verificar_pagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            inscricao_id: <?= $inscricao_id ?>,
            pagamento_id: <?= $pagamento['id'] ?? 'null' ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        spinner.style.display = 'none';
        btn.disabled = false;
        
        if (data.success && data.pago) {
            // Pagamento confirmado - redirecionar
            window.location.href = '<?= SITE_URL ?>/confirmacao.php?inscricao=<?= $inscricao_id ?>';
        } else {
            // Mostrar resultado
            alert(data.message || 'Pagamento ainda não foi identificado. Tente novamente em alguns instantes.');
        }
    })
    .catch(error => {
        spinner.style.display = 'none';
        btn.disabled = false;
        alert('Erro ao verificar pagamento. Tente novamente.');
    });
}

// Verificação automática a cada 30 segundos
setInterval(function() {
    verificarPagamento();
}, 30000);
</script>

<?php obter_rodape(); ?> 