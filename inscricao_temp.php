<?php
// Versão temporária da inscrição que funciona sem migração
require_once 'includes/init.php';
require_once 'includes/auth_participante.php';

// Verificar se evento existe
$evento_id = $_GET['evento_id'] ?? 0;
if (!$evento_id) {
    redirecionar(SITE_URL);
}

// Verificar se tabela inscricoes existe
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

// Se a tabela inscricoes não existe, usar o sistema antigo
if (!$tabela_inscricoes_existe) {
    // Query compatível com sistema antigo
    $evento = buscar_um("
        SELECT *, 
               (limite_participantes - (SELECT COUNT(*) FROM participantes WHERE evento_id = eventos.id AND status != 'cancelado')) as vagas_restantes
        FROM eventos 
        WHERE id = ? AND status = 'ativo'
    ", [$evento_id]);
} else {
    // Query nova com tabela inscricoes
    $evento = buscar_um("
        SELECT *, 
               (limite_participantes - (
                   SELECT COUNT(*) 
                   FROM inscricoes 
                   WHERE evento_id = eventos.id AND status IN ('pendente', 'aprovada')
               )) as vagas_restantes
        FROM eventos 
        WHERE id = ? AND status = 'ativo'
    ", [$evento_id]);
}

if (!$evento) {
    exibir_mensagem('Evento não encontrado ou inativo.', 'error');
    redirecionar(SITE_URL);
}

// Verificar vagas
if ($evento['vagas_restantes'] <= 0) {
    exibir_mensagem('Evento esgotado.', 'error');
    redirecionar(SITE_URL . '/evento/' . $evento['id']);
}

$erro = '';

// Verificar se o usuário está logado
$usuario_logado = participante_esta_logado();
$participante_logado = null;

if ($usuario_logado) {
    $participante_logado = obter_participante_logado();
    
    // Verificar se já está inscrito e obter detalhes (versão compatível)
    $inscricao_existente = null;
    if ($tabela_inscricoes_existe && function_exists('participante_ja_inscrito')) {
        if (participante_ja_inscrito($participante_logado['id'], $evento_id)) {
            $inscricao_existente = obter_inscricao_participante($participante_logado['id'], $evento_id);
        }
    } else {
        // Verificação antiga
        $inscricao_existente = buscar_um("
            SELECT *, e.nome as evento_nome, e.valor as evento_valor
            FROM participantes p
            JOIN eventos e ON p.evento_id = e.id
            WHERE p.cpf = ? AND p.evento_id = ? AND p.status != 'cancelado'
        ", [$participante_logado['cpf'], $evento_id]);
    }
}

// Processar confirmação da inscrição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        if (!$usuario_logado) {
            $erro = 'Você precisa estar logado para se inscrever.';
        } else {
            // Usar sistema novo se disponível, senão usar antigo
            if ($tabela_inscricoes_existe && function_exists('criar_inscricao_participante')) {
                $resultado = criar_inscricao_participante($participante_logado['id'], $evento_id);
                
                if ($resultado['sucesso']) {
                    if (isset($resultado['redirect_to'])) {
                        redirecionar($resultado['redirect_to']);
                    } else {
                        redirecionar(SITE_URL . '/participante/');
                    }
                } else {
                    $erro = $resultado['mensagem'];
                }
            } else {
                // Sistema antigo - criar participante diretamente
                try {
                    // Gerar token único para QR Code
                    $qr_token = gerar_string_aleatoria(32);
                    
                    // Inserir participante (sistema antigo)
                    $participante_dados = [
                        'evento_id' => $evento_id,
                        'nome' => $participante_logado['nome'],
                        'cpf' => $participante_logado['cpf'],
                        'whatsapp' => $participante_logado['whatsapp'],
                        'email' => $participante_logado['email'],
                        'idade' => 25, // Padrão temporário
                        'cidade' => 'Não informado',
                        'estado' => 'SP',
                        'tipo' => 'normal',
                        'status' => 'inscrito',
                        'qr_token' => $qr_token
                    ];
                    
                    $participante_id = inserir_registro('participantes', $participante_dados);
                    
                    if ($participante_id) {
                        // Criar pagamento se necessário
                        if ($evento['valor'] > 0) {
                            $txid = 'VINDE' . date('YmdHis') . str_pad($participante_id, 6, '0', STR_PAD_LEFT);
                            
                            $pagamento_dados = [
                                'participante_id' => $participante_id,
                                'valor' => $evento['valor'],
                                'status' => 'pendente',
                                'metodo' => 'pix',
                                'pix_txid' => $txid
                            ];
                            
                            inserir_registro('pagamentos', $pagamento_dados);
                            redirecionar(SITE_URL . '/pagamento.php?participante=' . $participante_id);
                        } else {
                            // Evento gratuito
                            redirecionar(SITE_URL . '/confirmacao.php?participante=' . $participante_id);
                        }
                    } else {
                        $erro = 'Erro ao processar inscrição. Tente novamente.';
                    }
                } catch (Exception $e) {
                    $erro = 'Erro interno. Tente novamente mais tarde.';
                    error_log("Erro na inscrição: " . $e->getMessage());
                }
            }
        }
    }
}

// Se não está logado, redirecionar para login
if (!$usuario_logado) {
    $redirect_url = SITE_URL . '/participante/login.php?evento_id=' . urlencode($evento_id) . '&redirect_to=' . urlencode($_SERVER['REQUEST_URI']);
    redirecionar($redirect_url);
}

obter_cabecalho('Confirmar Inscrição - ' . $evento['nome'], 'inscricao');
echo '<link rel="stylesheet" href="' . SITE_URL . '/assets/css/checkout.css">';
?>

<main class="inscricao-main">
    <!-- Breadcrumb -->
    <nav class="inscricao-breadcrumb">
        <div class="container">
            <div class="breadcrumb-content">
                <a href="<?= SITE_URL ?>" class="breadcrumb-link">Eventos</a>
                <span class="breadcrumb-separator">›</span>
                <a href="<?= SITE_URL ?>/evento/<?= $evento['id'] ?>" class="breadcrumb-link"><?= htmlspecialchars($evento['nome']) ?></a>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current">Confirmar Inscrição</span>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Aviso sobre migração -->
        <?php if (!$tabela_inscricoes_existe): ?>
            <div class="alert alert-warning" style="margin-bottom: 30px;">
                <strong>⚠️ Sistema em Transição:</strong> O novo sistema de inscrições ainda não foi migrado. 
                Funcionando no modo de compatibilidade.
            </div>
        <?php endif; ?>

        <!-- Layout Checkout -->
        <div class="checkout-layout">
            <div class="checkout-main">
                <?php if ($erro): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <!-- Dados do Participante -->
                <div class="checkout-step">
                    <div class="step-header">
                        <div class="step-number">✓</div>
                        <div class="step-title">Participante Logado</div>
                    </div>
                    
                    <div class="step-content">
                        <div class="participant-info">
                            <h3><?= htmlspecialchars($participante_logado['nome']) ?></h3>
                            <p><strong>CPF:</strong> <?= formatarCpf($participante_logado['cpf']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($participante_logado['email']) ?></p>
                            <p><strong>WhatsApp:</strong> <?= formatarTelefone($participante_logado['whatsapp']) ?></p>
                        </div>
                        
                        <div class="participant-actions">
                            <a href="<?= SITE_URL ?>/participante/logout.php" class="btn-link">
                                Não é você? Fazer login com outra conta
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Confirmação da Inscrição -->
                <div class="checkout-step">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <div class="step-title">Confirmar Inscrição</div>
                    </div>
                    
                    <div class="step-content">
                        <div class="inscricao-resumo">
                            <p>Você está prestes a se inscrever no evento:</p>
                            <h3><?= htmlspecialchars($evento['nome']) ?></h3>
                            
                            <?php if ($evento['valor'] > 0): ?>
                                <div class="valor-evento">
                                    <strong>Valor: R$ <?= number_format($evento['valor'], 2, ',', '.') ?></strong>
                                </div>
                                <p class="info-pagamento">
                                    Após confirmar, você será direcionado para o pagamento via PIX.
                                </p>
                            <?php else: ?>
                                <div class="evento-gratuito">
                                    <strong>Evento Gratuito</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" class="form-confirmacao" id="form-confirmacao">
                            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
                            
                            <button type="submit" class="btn-confirmar" id="btn-confirmar">
                                <?= $evento['valor'] > 0 ? 'Confirmar e Pagar' : 'Confirmar Inscrição Gratuita' ?>
                            </button>
                        </form>
                        
                        <!-- Overlay de confirmação -->
                        <div class="confirmation-overlay" id="confirmation-overlay">
                            <div class="overlay-content">
                                <div class="overlay-spinner"></div>
                                <h3 class="overlay-title">Processando sua inscrição...</h3>
                                <p class="overlay-message" id="overlay-message">
                                    <?= $evento['valor'] > 0 ? 'Estamos criando seu pagamento PIX.' : 'Confirmando sua inscrição gratuita.' ?>
                                </p>
                                <p class="overlay-submessage">Por favor, aguarde...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar com dados do evento -->
            <div class="checkout-sidebar">
                <div class="evento-card">
                    <?php if ($evento['imagem']): ?>
                        <div class="evento-imagem">
                            <img src="<?= SITE_URL ?>/uploads/<?= htmlspecialchars($evento['imagem']) ?>" 
                                 alt="<?= htmlspecialchars($evento['nome']) ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="evento-info">
                        <h3><?= htmlspecialchars($evento['nome']) ?></h3>
                        
                        <div class="evento-detalhes">
                            <div class="detalhe-item">
                                <span class="detalhe-label">📅 Data:</span>
                                <span class="detalhe-valor">
                                    <?= date('d/m/Y', strtotime($evento['data_inicio'])) ?>
                                    <?php if ($evento['data_fim'] && $evento['data_fim'] !== $evento['data_inicio']): ?>
                                        a <?= date('d/m/Y', strtotime($evento['data_fim'])) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if ($evento['horario_inicio']): ?>
                                <div class="detalhe-item">
                                    <span class="detalhe-label">🕐 Horário:</span>
                                    <span class="detalhe-valor">
                                        <?= date('H:i', strtotime($evento['horario_inicio'])) ?>
                                        <?php if ($evento['horario_fim']): ?>
                                            às <?= date('H:i', strtotime($evento['horario_fim'])) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detalhe-item">
                                <span class="detalhe-label">📍 Local:</span>
                                <span class="detalhe-valor"><?= htmlspecialchars($evento['local']) ?></span>
                            </div>
                            
                            <div class="detalhe-item">
                                <span class="detalhe-label">🎫 Vagas:</span>
                                <span class="detalhe-valor"><?= $evento['vagas_restantes'] ?> restantes</span>
                            </div>
                            
                            <?php if ($evento['valor'] > 0): ?>
                                <div class="detalhe-item valor-destaque">
                                    <span class="detalhe-label">💰 Valor:</span>
                                    <span class="detalhe-valor">R$ <?= number_format($evento['valor'], 2, ',', '.') ?></span>
                                </div>
                            <?php else: ?>
                                <div class="detalhe-item gratuito-destaque">
                                    <span class="detalhe-label">🎁 Valor:</span>
                                    <span class="detalhe-valor">Gratuito</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.participant-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 15px;
}

.participant-info h3 {
    margin: 0 0 10px 0;
    color: var(--cor-primaria);
}

.participant-info p {
    margin: 5px 0;
    color: var(--cor-texto-secundario);
}

.participant-actions {
    text-align: center;
}

.btn-link {
    color: var(--cor-primaria);
    text-decoration: none;
    font-size: 14px;
}

.btn-link:hover {
    text-decoration: underline;
}

.inscricao-resumo {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
}

.valor-evento {
    font-size: 24px;
    color: var(--cor-primaria);
    margin: 15px 0;
}

.evento-gratuito {
    font-size: 24px;
    color: #28a745;
    margin: 15px 0;
}

.info-pagamento {
    font-size: 14px;
    color: var(--cor-texto-secundario);
    margin-top: 10px;
}

.form-confirmacao {
    text-align: center;
}

.btn-confirmar {
    background: var(--cor-primaria);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 250px;
}

.btn-confirmar:hover {
    background: var(--cor-primaria-dark);
    transform: translateY(-2px);
}

.evento-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.evento-imagem img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.evento-info {
    padding: 20px;
}

.evento-info h3 {
    margin: 0 0 15px 0;
    color: var(--cor-primaria);
    font-size: 18px;
}

.evento-detalhes {
    space-y: 10px;
}

.detalhe-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    font-size: 14px;
}

.detalhe-label {
    font-weight: 500;
    color: var(--cor-texto-secundario);
    min-width: 80px;
}

.detalhe-valor {
    text-align: right;
    flex: 1;
    margin-left: 10px;
}

.valor-destaque .detalhe-valor {
    font-weight: 700;
    font-size: 16px;
    color: var(--cor-primaria);
}

.gratuito-destaque .detalhe-valor {
    font-weight: 700;
    font-size: 16px;
    color: #28a745;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-confirmacao');
    const btn = document.getElementById('btn-confirmar');
    const overlay = document.getElementById('confirmation-overlay');
    const overlayMessage = document.getElementById('overlay-message');
    
    if (form && btn && overlay) {
        form.addEventListener('submit', function(e) {
            // Mostrar overlay e desabilitar botão
            btn.disabled = true;
            overlay.classList.add('active');
            
            // Adicionar pequeno delay para dar feedback visual instantâneo
            setTimeout(function() {
                // O formulário será submetido normalmente após o feedback visual
            }, 100);
            
            // Timeout de segurança para esconder overlay caso algo dê errado
            setTimeout(function() {
                if (overlay.classList.contains('active')) {
                    overlay.classList.remove('active');
                    btn.disabled = false;
                    
                    // Mostrar mensagem de erro
                    if (window.VindeUtils && window.VindeUtils.showMessage) {
                        window.VindeUtils.showMessage('Tempo limite excedido. Tente novamente.', 'error');
                    } else {
                        alert('Tempo limite excedido. Tente novamente.');
                    }
                }
            }, 30000); // 30 segundos
        });
        
        // Função para esconder overlay (pode ser usada em caso de erro)
        window.hideConfirmationOverlay = function() {
            overlay.classList.remove('active');
            btn.disabled = false;
        };
        
        // Atualizar mensagem do overlay baseado no tipo de evento
        const isEventoPago = <?= $evento['valor'] > 0 ? 'true' : 'false' ?>;
        if (isEventoPago) {
            overlayMessage.textContent = 'Estamos criando seu pagamento PIX.';
        } else {
            overlayMessage.textContent = 'Confirmando sua inscrição gratuita.';
        }
    }
});
</script>

<?php obter_rodape(); ?> 