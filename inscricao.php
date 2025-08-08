<?php
require_once 'includes/init.php';

// Verificar se evento existe
$evento_id = $_GET['evento_id'] ?? 0;
if (!$evento_id) {
    redirecionar(SITE_URL);
}

$evento = buscar_um("
    SELECT *, 
           (limite_participantes - (SELECT COUNT(*) FROM participantes WHERE evento_id = eventos.id AND status != 'cancelado')) as vagas_restantes
    FROM eventos 
    WHERE id = ? AND status = 'ativo'
", [$evento_id]);

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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        $dados = [
            'nome' => sanitizar_entrada($_POST['nome'] ?? ''),
            'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? ''),
            'idade' => (int)($_POST['idade'] ?? 0),
            'whatsapp' => preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? ''),
            'email' => sanitizar_entrada($_POST['email'] ?? ''),
            'instagram' => sanitizar_entrada($_POST['instagram'] ?? ''),
            'cidade' => sanitizar_entrada($_POST['cidade'] ?? ''),
            'estado' => sanitizar_entrada($_POST['estado'] ?? 'SP')
        ];

        // Validações básicas
        if (empty($dados['nome']) || empty($dados['cpf']) || empty($dados['whatsapp']) || empty($dados['email']) || empty($dados['cidade'])) {
            $erro = 'Por favor, preencha todos os campos obrigatórios.';
        } elseif ($dados['idade'] < 1 || $dados['idade'] > 120) {
            $erro = 'Idade deve estar entre 1 e 120 anos.';
        } elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erro = 'Email inválido.';
        } else {
            // Processar inscrição
            $resultado = processar_inscricao($evento_id, $dados);
            if ($resultado['sucesso']) {
                redirecionar(SITE_URL . '/pagamento.php?participante=' . $resultado['participante_id']);
            } else {
                $erro = $resultado['mensagem'];
            }
        }
    }
}

obter_cabecalho('Inscrição - ' . $evento['nome'], 'inscricao');
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
                <span class="breadcrumb-current">Inscrição</span>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Layout Checkout -->
        <div class="checkout-layout">
            <div class="checkout-main">
                <?php if ($erro): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="form-inscricao" class="checkout-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
                    
                    <!-- Etapa 1: Dados Pessoais -->
                    <div class="checkout-step">
                        <div class="step-header">
                            <div class="step-number">1</div>
                            <div class="step-title">Dados Pessoais</div>
                        </div>
                        
                        <div class="step-content">
                            <div class="form-row">
                                <label for="nome">Nome completo *</label>
                                <input type="text" id="nome" name="nome" required
                                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                                       placeholder="Nome Sobrenome">
                                <div class="field-error" id="erro-nome"></div>
                            </div>
                            
                            <div class="form-row-group">
                                <div class="form-row">
                                    <label for="cpf">CPF *</label>
                                    <input type="text" id="cpf" name="cpf" required
                                           value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>"
                                           placeholder="000.000.000-00" data-mask="cpf">
                                    <div class="field-error" id="erro-cpf"></div>
                                </div>
                                
                                <div class="form-row">
                                    <label for="idade">Idade *</label>
                                    <input type="number" id="idade" name="idade" required min="1" max="120"
                                           value="<?= htmlspecialchars($_POST['idade'] ?? '') ?>"
                                           placeholder="25">
                                    <div class="field-error" id="erro-idade"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Etapa 2: Contato -->
                    <div class="checkout-step">
                        <div class="step-header">
                            <div class="step-number">2</div>
                            <div class="step-title">Informações de Contato</div>
                        </div>
                        
                        <div class="step-content">
                            <div class="form-row-group">
                                <div class="form-row">
                                    <label for="whatsapp">WhatsApp *</label>
                                    <input type="tel" id="whatsapp" name="whatsapp" required
                                           value="<?= htmlspecialchars($_POST['whatsapp'] ?? '') ?>"
                                           placeholder="(11) 99999-9999" data-mask="telefone">
                                    <div class="field-error" id="erro-whatsapp"></div>
                                </div>
                                
                                <div class="form-row">
                                    <label for="email">E-mail *</label>
                                    <input type="email" id="email" name="email" required
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                           placeholder="exemplo@email.com.br">
                                    <div class="field-error" id="erro-email"></div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <label for="instagram">Instagram (opcional)</label>
                                <input type="text" id="instagram" name="instagram"
                                       value="<?= htmlspecialchars($_POST['instagram'] ?? '') ?>"
                                       placeholder="seuusuario">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Etapa 3: Localização -->
                    <div class="checkout-step">
                        <div class="step-header">
                            <div class="step-number">3</div>
                            <div class="step-title">Sua Localização</div>
                        </div>
                        
                        <div class="step-content">
                            <div class="form-row-group">
                                <div class="form-row">
                                    <label for="cidade">Cidade *</label>
                                    <input type="text" id="cidade" name="cidade" required
                                           value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>"
                                           placeholder="Digite sua cidade">
                                    <div class="field-error" id="erro-cidade"></div>
                                </div>
                                
                                <div class="form-row">
                                    <label for="estado">Estado</label>
                                    <select id="estado" name="estado">
                                        <option value="SP" <?= ($_POST['estado'] ?? 'SP') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                                        <option value="RJ" <?= ($_POST['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                                        <option value="MG" <?= ($_POST['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                                        <option value="ES" <?= ($_POST['estado'] ?? '') === 'ES' ? 'selected' : '' ?>>Espírito Santo</option>
                                        <option value="PR" <?= ($_POST['estado'] ?? '') === 'PR' ? 'selected' : '' ?>>Paraná</option>
                                        <option value="SC" <?= ($_POST['estado'] ?? '') === 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                                        <option value="RS" <?= ($_POST['estado'] ?? '') === 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Etapa 4: Termos -->
                    <div class="checkout-step">
                        <div class="step-header">
                            <div class="step-number">4</div>
                            <div class="step-title">Termos e Condições</div>
                        </div>
                        
                        <div class="step-content">
                            <div class="terms-checkbox">
                                <input type="checkbox" id="aceito-termos" required>
                                <label for="aceito-termos">
                                    Aceito os <a href="#" onclick="abrirTermos(); return false;">termos e condições</a> do evento *
                                </label>
                                <div class="field-error" id="erro-termos"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botão -->
                    <button type="submit" class="checkout-btn" id="btn-submeter">
                        <span class="btn-text">Próximo</span>
                        <span class="btn-loading">Processando...</span>
                    </button>
                </form>
            </div>
            
            <!-- Sidebar Resumo -->
            <div class="checkout-sidebar">
                <div class="resumo-pedido">
                    <h3>Resumo do Pedido</h3>
                    
                    <div class="evento-resumo">
                        <div class="evento-data">
                            <?= formatar_data($evento['data_inicio']) ?>
                            <?php if ($evento['horario_inicio']): ?>
                                • <?= date('H:i', strtotime($evento['horario_inicio'])) ?>
                            <?php endif; ?>
                        </div>
                        <div class="evento-nome"><?= htmlspecialchars($evento['nome']) ?></div>
                        <div class="evento-local">
                            <?= htmlspecialchars($evento['local']) ?>
                            <?php if ($evento['cidade']): ?>
                                <br><?= htmlspecialchars($evento['cidade']) ?>, <?= $evento['estado'] ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="valor-linha">
                        <span>1 Inscrição</span>
                        <span><?= $evento['valor'] > 0 ? 'R$ ' . number_format($evento['valor'], 2, ',', '.') : 'Gratuito' ?></span>
                    </div>
                    
                    <div class="total-linha">
                        <span>Total</span>
                        <span><?= $evento['valor'] > 0 ? 'R$ ' . number_format($evento['valor'], 2, ',', '.') : 'R$ 0,00' ?></span>
                    </div>
                    
                    <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
                        <strong><?= $evento['vagas_restantes'] ?></strong> vagas restantes de <?= $evento['limite_participantes'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal de Termos (mantém o existente) -->
<div id="modal-termos" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Termos e Condições</h3>
            <button type="button" onclick="fecharTermos()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <h4>Inscrição no Evento</h4>
            <p>Ao se inscrever neste evento católico, você concorda com os seguintes termos:</p>
            
            <h5>1. Dados Pessoais</h5>
            <p>Seus dados serão utilizados exclusivamente para organização do evento e comunicações relacionadas. Não compartilhamos informações com terceiros.</p>
            
            <h5>2. Pagamento</h5>
            <p>Para eventos pagos, o pagamento deve ser realizado via PIX. A vaga só será confirmada após a compensação do pagamento.</p>
            
            <h5>3. Cancelamento</h5>
            <p>Cancelamentos devem ser solicitados com até 48 horas de antecedência através do WhatsApp de contato.</p>
            
            <h5>4. Comportamento</h5>
            <p>Esperamos que todos os participantes mantenham comportamento respeitoso e adequado ao ambiente religioso.</p>
            
            <h5>5. Imagens</h5>
            <p>O evento pode ser fotografado/filmado para divulgação. Caso não deseje aparecer, comunique a organização.</p>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="aceitarTermos()" class="btn-aceitar">Aceitar Termos</button>
            <button type="button" onclick="fecharTermos()" class="btn-fechar">Fechar</button>
        </div>
    </div>
</div>

<script>
// Máscaras de input (mantém as existentes)
document.addEventListener('DOMContentLoaded', function() {
    // Máscara CPF
    const cpfInput = document.getElementById('cpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }

    // Máscara WhatsApp
    const whatsappInput = document.getElementById('whatsapp');
    if (whatsappInput) {
        whatsappInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = value;
        });
    }

    // Submissão do formulário
    const form = document.getElementById('form-inscricao');
    const btnSubmeter = document.getElementById('btn-submeter');
    
    if (form && btnSubmeter) {
        form.addEventListener('submit', function() {
            btnSubmeter.classList.add('loading');
            btnSubmeter.disabled = true;
        });
    }
});

// Funções dos termos
function abrirTermos() {
    document.getElementById('modal-termos').style.display = 'flex';
}

function fecharTermos() {
    document.getElementById('modal-termos').style.display = 'none';
}

function aceitarTermos() {
    document.getElementById('aceito-termos').checked = true;
    fecharTermos();
}
</script>

<?php obter_rodape(); ?> 