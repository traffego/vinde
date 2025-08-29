<?php
require_once '../includes/init.php';

// Verificar login
requer_login();

// Ações do CRUD
$acao = $_GET['acao'] ?? 'listar';
$participante_id = $_GET['id'] ?? null;
$evento_id = $_GET['evento'] ?? null;
$erro = '';
$sucesso = '';



// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        switch ($acao) {
            case 'criar':
            case 'editar':
                $resultado = salvar_participante($_POST, $participante_id);
                if ($resultado['sucesso']) {
                    $sucesso = $resultado['mensagem'];
                    if ($acao === 'criar') {
                        redirecionar(SITE_URL . '/admin/participantes.php?acao=editar&id=' . $resultado['id']);
                    }
                } else {
                    $erro = $resultado['mensagem'];
                }
                break;
                
            case 'alterar_status':
                if ($participante_id && isset($_POST['novo_status'])) {
                    $novo_status = $_POST['novo_status'];
                    if (atualizar_registro('participantes', ['status' => $novo_status], ['id' => $participante_id])) {
                        registrar_log('status_participante_alterado', "Participante ID: {$participante_id} - Novo status: {$novo_status}");
                        $sucesso = 'Status alterado com sucesso!';
                    } else {
                        $erro = 'Erro ao alterar status.';
                    }
                }
                break;
        }
    }
}

// Buscar dados para edição
$participante = [];
if (($acao === 'editar' || $acao === 'visualizar') && $participante_id) {
    // Buscar participante e a última inscrição (se existir)
    $participante = buscar_um("
        SELECT 
            p.*,
            li.id as inscricao_id_display,
            e.nome as evento_nome, 
            e.slug as evento_slug, 
            e.data_inicio,
            pag.status as pagamento_status, 
            pag.valor, 
            pag.pago_em
        FROM participantes p
        LEFT JOIN (
            SELECT i.* 
            FROM inscricoes i 
            WHERE i.participante_id = ? 
            ORDER BY i.data_inscricao DESC 
            LIMIT 1
        ) li ON li.participante_id = p.id
        LEFT JOIN eventos e ON e.id = li.evento_id
        LEFT JOIN pagamentos pag ON pag.inscricao_id = li.id
        WHERE p.id = ?
    ", [$participante_id, $participante_id]);
    
    if (!$participante) {
        exibir_erro_404();
    }
}



// Eventos para filtro
$eventos = buscar_todos("SELECT id, nome FROM eventos ORDER BY data_inicio DESC");

// Verificar se sistema foi migrado
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

// Estatísticas - usando estrutura nova com tabela inscricoes e pagamentos
if ($tabela_inscricoes_existe) {
    // Sistema novo - considerar pagamentos na tabela separada
    $stats = buscar_um("
        SELECT 
            COUNT(DISTINCT i.id) as total,
            SUM(CASE WHEN pag.status IS NULL OR pag.status = 'pendente' THEN 1 ELSE 0 END) as inscritos,
            SUM(CASE WHEN pag.status = 'pago' THEN 1 ELSE 0 END) as pagos,
            SUM(CASE WHEN p.status = 'presente' THEN 1 ELSE 0 END) as presentes
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        LEFT JOIN pagamentos pag ON pag.inscricao_id = i.id
        WHERE i.status != 'cancelada'
    ");
} else {
    // Sistema antigo - usando apenas tabela participantes
    $stats = buscar_um("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'inscrito' THEN 1 ELSE 0 END) as inscritos,
            SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pagos,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes
        FROM participantes
        WHERE status != 'cancelado'
    ");
}

/**
 * Salvar participante (criar ou editar)
 */
function salvar_participante($dados, $participante_id = null) {
    try {
        // Validações
        $erros = [];
        
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        
        // Validar CPF conforme configuração
        if (empty($dados['cpf'])) {
            $erros[] = 'CPF é obrigatório';
        } elseif (cpf_obrigatorio() && !validar_cpf($dados['cpf'])) {
            // Só valida formato se a verificação estiver ativada
            $erros[] = 'CPF inválido';
        }
        if (empty($dados['whatsapp']) || !validar_telefone($dados['whatsapp'])) $erros[] = 'WhatsApp inválido';
        if (empty($dados['email']) || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Email inválido';
        if (empty($dados['idade']) || !is_numeric($dados['idade']) || $dados['idade'] < 1 || $dados['idade'] > 120) {
            $erros[] = 'Idade deve ser um número válido';
        }
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatória';
        if (empty($dados['evento_id']) && !$participante_id) $erros[] = 'Evento é obrigatório';
        
        // Verificar se CPF já existe em outro participante
        if ($participante_id) {
            $cpf_existente = buscar_um("
                SELECT id FROM participantes 
                WHERE cpf = ? AND id != ? AND status != 'cancelado'
            ", [limpar_cpf($dados['cpf']), $participante_id]);
        } else {
            $cpf_existente = buscar_um("
                SELECT id FROM participantes 
                WHERE cpf = ? AND status != 'cancelado'
            ", [limpar_cpf($dados['cpf'])]);
        }
        
        if ($cpf_existente) {
            $erros[] = 'Já existe outro participante com este CPF';
        }
        
        if (!empty($erros)) {
            return ['sucesso' => false, 'mensagem' => implode(', ', $erros)];
        }
        
        // Preparar dados para salvamento
        $participante_dados = [
            'nome' => sanitizar_entrada($dados['nome']),
            'cpf' => limpar_cpf($dados['cpf']),
            'whatsapp' => limpar_telefone($dados['whatsapp']),
            'instagram' => sanitizar_entrada($dados['instagram'] ?? ''),
            'email' => sanitizar_entrada($dados['email']),
            'idade' => intval($dados['idade']),
            'cidade' => sanitizar_entrada($dados['cidade']),
            'estado' => $dados['estado'] ?? 'SP',
            'status' => $dados['status'] ?? 'inscrito'
        ];
        
        // Adicionar evento_id apenas na criação
        if (!$participante_id && !empty($dados['evento_id'])) {
            $participante_dados['evento_id'] = intval($dados['evento_id']);
            $participante_dados['qr_token'] = gerar_string_aleatoria(32);
        }
        
        if ($participante_id) {
            // Editar
            $sucesso = atualizar_registro('participantes', $participante_dados, ['id' => $participante_id]);
            $acao_log = 'participante_editado';
            $id_resultado = $participante_id;
        } else {
            // Criar
            $id_resultado = inserir_registro('participantes', $participante_dados);
            $sucesso = $id_resultado > 0;
            $acao_log = 'participante_criado';
        }
        
        if ($sucesso) {
            registrar_log($acao_log, "Participante: {$dados['nome']} (ID: {$id_resultado})");
            return [
                'sucesso' => true, 
                'mensagem' => $participante_id ? 'Participante atualizado com sucesso!' : 'Participante criado com sucesso!',
                'id' => $id_resultado
            ];
        } else {
            return ['sucesso' => false, 'mensagem' => 'Erro ao salvar participante'];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao salvar participante: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno ao salvar participante'];
    }
}

// Definir título da página
$titulos = [
    'listar' => 'Participantes',
    'criar' => 'Novo Participante',
    'editar' => 'Editar Participante',
    'visualizar' => 'Visualizar Participante'
];

$titulo_pagina = $titulos[$acao] ?? 'Participantes';

obter_cabecalho_admin($titulo_pagina, 'participantes');
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/participantes-cards.css">

<?php if ($acao === 'listar'): ?>
    
    <!-- Estatísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $stats['total'] ?></h3>
            <p>Total de Participantes</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['inscritos'] ?></h3>
            <p>Aguardando Pagamento</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['pagos'] ?></h3>
            <p>Pagos</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['presentes'] ?></h3>
            <p>Presentes</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-section">
        <div class="filtros-grid">
            <div class="filtro-group">
                <label class="filtro-label">Buscar</label>
                <input type="text" id="filtro-busca" class="filtro-input" placeholder="Nome, CPF ou email...">
            </div>
            
            <div class="filtro-group">
                <label class="filtro-label">Evento</label>
                <select id="filtro-evento" class="filtro-input">
                    <option value="">Todos os eventos</option>
                    <?php foreach ($eventos as $ev): ?>
                        <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filtro-group">
                <label class="filtro-label">Status de Inscrição</label>
                <select id="filtro-status" class="filtro-input">
                    <option value="">Todos os status</option>
                    <option value="pendente">Pendente</option>
                    <option value="aprovada">Aprovada</option>
                    <option value="rejeitada">Rejeitada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            
            <div class="filtro-group">
                <label class="filtro-label">Status Pagamento</label>
                <select id="filtro-pagamento" class="filtro-input">
                    <option value="">Todos os pagamentos</option>
                    <option value="pendente">Pendente</option>
                    <option value="pago">Pago</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="estornado">Estornado</option>
                </select>
            </div>
            
            <div class="filtro-group">
                <label class="filtro-label">Cidade</label>
                <input type="text" id="filtro-cidade" class="filtro-input" placeholder="Cidade...">
            </div>
            
            <div class="filtro-group">
                <label class="filtro-label">Ações</label>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="limparFiltros()" class="btn btn-outline">Limpar</button>
            <a href="<?= SITE_URL ?>/admin/participantes.php?acao=criar" class="btn btn-primary">Novo Participante</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações em Massa -->
    <div class="acoes-massa-section" style="display: none;">
        <div class="acoes-massa-content">
            <div class="acoes-massa-info">
                <input type="checkbox" id="select-all-cards" onchange="toggleSelectAll()">
                <span id="selecionados-count">0 selecionados</span>
            </div>
            <div class="acoes-massa-buttons">
                <select id="acao-massa" class="filtro-input" style="width: auto;">
                    <option value="">Escolha uma ação</option>
                    <option value="aprovar">Aprovar Inscrições</option>
                    <option value="rejeitar">Rejeitar Inscrições</option>
                <option value="cancelar">Cancelar Inscrições</option>
                    <option value="excluir">Excluir Selecionados</option>
            </select>
                <button type="button" onclick="executarAcaoMassa()" class="btn btn-primary">Executar</button>
                <button type="button" onclick="cancelarSelecao()" class="btn btn-outline">Cancelar</button>
    </div>
                                </div>
                                </div>
    <!-- Grid de Participantes -->
    <div id="participantes-container">
        <div class="loading">
            <div class="loading-spinner"></div>
            Carregando participantes...
                                    </div>
                                </div>
                                
    <!-- Modal de Detalhes -->
    <div id="modal-participante" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Detalhes do Participante</h2>
                <button class="close" onclick="fecharModal()">&times;</button>
            </div>
            <div class="modal-body" id="modal-body-content">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="fecharModal()">Fechar</button>
                <button type="button" class="btn btn-primary" id="btn-editar-modal" onclick="editarParticipante()">Editar</button>
                <button type="button" class="btn btn-danger" id="btn-excluir-modal" onclick="confirmarExclusaoModal()">Excluir</button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="modal-exclusao" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Confirmar Exclusão</h2>
                <button class="close" onclick="fecharModalExclusao()">&times;</button>
        </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir este participante?</p>
                <p><strong id="nome-participante-exclusao"></strong></p>
                <p style="font-size: 0.875rem; color: #6b7280;">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="fecharModalExclusao()">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao">Excluir</button>
            </div>
        </div>
    </div>

<?php else: ?>
    
    <!-- Formulário de Criação/Edição -->
    <div class="admin-form">
        
        <?php if ($erro): ?>
            <div class="admin-mensagem mensagem-error">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="admin-mensagem mensagem-success">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($acao === 'editar' && !empty($participante)): ?>
            <!-- Informações do Evento -->
            <div class="info-card">
                <h3>📅 Evento: <?= htmlspecialchars($participante['evento_nome'] ?? 'N/A') ?></h3>
                <p><strong>Data:</strong> <?= formatar_data($participante['data_inicio'] ?? '') ?></p>
                <p><strong>Status Pagamento:</strong> 
                    <span class="status-badge-admin status-<?= $participante['pagamento_status'] ?? 'pendente' ?>">
                        <?= ucfirst($participante['pagamento_status'] ?? 'pendente') ?>
                    </span>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
            
            <?php if ($acao === 'criar'): ?>
                <!-- Seleção de Evento (apenas na criação) -->
                <h3>Evento</h3>
                <div class="form-row">
                    <div class="form-group-admin">
                        <label class="form-label-admin">Evento *</label>
                        <select name="evento_id" class="form-select-admin required" required>
                            <option value="">Selecione um evento</option>
                            <?php foreach ($eventos as $ev): ?>
                                <option value="<?= $ev['id'] ?>" <?= ($_POST['evento_id'] ?? '') == $ev['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ev['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Dados Pessoais -->
            <h3>Dados Pessoais</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Nome Completo *</label>
                    <input type="text" name="nome" class="form-input-admin required" 
                           value="<?= htmlspecialchars($participante['nome'] ?? '') ?>" required>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Idade *</label>
                    <input type="number" name="idade" class="form-input-admin required" 
                           min="1" max="120" value="<?= $participante['idade'] ?? '' ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">CPF <?= cpf_obrigatorio() ? '*' : '(opcional)' ?></label>
                    <input type="text" name="cpf" class="form-input-admin <?= cpf_obrigatorio() ? 'required' : '' ?>" 
                           value="<?= formatarCpf($participante['cpf'] ?? '') ?>" 
                           <?= cpf_obrigatorio() ? 'required' : '' ?> data-mask="cpf">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Status</label>
                    <select name="status" class="form-select-admin">
                        <option value="inscrito" <?= ($participante['status'] ?? '') === 'inscrito' ? 'selected' : '' ?>>Inscrito</option>
                        <option value="pago" <?= ($participante['status'] ?? '') === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="presente" <?= ($participante['status'] ?? '') === 'presente' ? 'selected' : '' ?>>Presente</option>
                        <option value="cancelado" <?= ($participante['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
            </div>
            
            <!-- Contato -->
            <h3>Contato</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">WhatsApp *</label>
                    <input type="tel" name="whatsapp" class="form-input-admin required" 
                           value="<?= formatarTelefone($participante['whatsapp'] ?? '') ?>" required data-mask="telefone">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Email *</label>
                    <input type="email" name="email" class="form-input-admin required" 
                           value="<?= htmlspecialchars($participante['email'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Instagram</label>
                    <input type="text" name="instagram" class="form-input-admin" 
                           value="<?= htmlspecialchars($participante['instagram'] ?? '') ?>" placeholder="@usuario">
                </div>
            </div>
            
            <!-- Localização -->
            <h3>Localização</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Cidade *</label>
                    <input type="text" name="cidade" class="form-input-admin required" 
                           value="<?= htmlspecialchars($participante['cidade'] ?? '') ?>" required>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Estado</label>
                    <select name="estado" class="form-select-admin">
                        <option value="SP" <?= ($participante['estado'] ?? '') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                        <option value="RJ" <?= ($participante['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                        <option value="MG" <?= ($participante['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                        <!-- Adicionar outros estados conforme necessário -->
                    </select>
                </div>
            </div>
            
            <!-- Ações -->
            <div class="form-actions">
                <a href="<?= SITE_URL ?>/admin/participantes.php" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <?= $acao === 'criar' ? 'Criar Participante' : 'Salvar Alterações' ?>
                </button>
                
                <?php if ($acao === 'editar' && !empty($participante['id'])): ?>
                    <!-- Botões especiais -->
                    <a href="<?= SITE_URL ?>/confirmacao.php?participante=<?= $participante['id'] ?>" 
                       target="_blank" class="btn btn-success">Ver QR Code</a>
                       
                    <?php if ($participante['whatsapp'] ?? ''): ?>
                        <a href="https://wa.me/<?= limpar_telefone($participante['whatsapp']) ?>" 
                           target="_blank" class="btn btn-outline">📱 WhatsApp</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </form>
    </div>

<?php endif; ?>

<!-- Biblioteca Brazilian Values para validação de CPF -->
<script src="https://unpkg.com/brazilian-values@0.13.1/dist/brazilian-values.umd.min.js"></script>

<script>
// Configuração global para validação de CPF
window.cpfObrigatorio = <?= cpf_obrigatorio() ? 'true' : 'false' ?>;

// Função de validação de CPF usando brazilian-values
function validarCPF(cpf) {
    if (!cpf) return !window.cpfObrigatorio;
    
    // Remove formatação
    const cpfLimpo = cpf.replace(/[^\d]/g, '');
    
    // Verifica se tem 11 dígitos
    if (cpfLimpo.length !== 11) return false;
    
    // Usa a biblioteca brazilian-values
    if (typeof BrazilianValues !== 'undefined' && BrazilianValues.isCPF) {
        return BrazilianValues.isCPF(cpf);
    }
    
    // Fallback manual se a biblioteca não carregar
    if (/^(\d)\1{10}$/.test(cpfLimpo)) return false;
    
    let soma = 0;
    for (let i = 0; i < 9; i++) {
        soma += parseInt(cpfLimpo.charAt(i)) * (10 - i);
    }
    let resto = 11 - (soma % 11);
    let digito1 = resto < 2 ? 0 : resto;
    
    if (parseInt(cpfLimpo.charAt(9)) !== digito1) return false;
    
    soma = 0;
    for (let i = 0; i < 10; i++) {
        soma += parseInt(cpfLimpo.charAt(i)) * (11 - i);
    }
    resto = 11 - (soma % 11);
    let digito2 = resto < 2 ? 0 : resto;
    
    return parseInt(cpfLimpo.charAt(10)) === digito2;
}

// Variáveis globais
let participantesData = [];
let filtrosAtivos = {};
let participanteAtual = null;
let offsetAtual = 0;
let carregandoMais = false;
let temMaisParticipantes = true;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($acao === 'listar'): ?>
        carregarParticipantes(true); // Sempre resetar quando filtrar
        inicializarFiltros();
        inicializarScrollInfinito();
    <?php endif; ?>
    
    // Máscaras de input
    inicializarMascaras();
});

// Carregar participantes via AJAX
function carregarParticipantes(resetar = true) {
    if (carregandoMais) return;
    
    carregandoMais = true;
    const container = document.getElementById('participantes-container');
    
    if (resetar) {
        offsetAtual = 0;
        temMaisParticipantes = true;
        participantesData = [];
        container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Carregando participantes...</p></div>';
    }
    
    // Construir query string com filtros
    const params = new URLSearchParams(filtrosAtivos);
    params.append('offset', offsetAtual);
    
    fetch(`<?= SITE_URL ?>/admin/api/participantes.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                const novosParticipantes = data.participantes || [];
                
                if (resetar) {
                    participantesData = novosParticipantes;
                } else {
                    participantesData = [...participantesData, ...novosParticipantes];
                }
                
                temMaisParticipantes = data.tem_mais || false;
                offsetAtual = data.proximo_offset || offsetAtual;
                
                // Debug temporário
                console.log('Debug Scroll:', {
                    total: data.total,
                    offset_atual: data.offset,
                    por_pagina: data.por_pagina,
                    tem_mais: data.tem_mais,
                    proximo_offset: data.proximo_offset,
                    participantes_carregados: novosParticipantes.length,
                    total_na_memoria: participantesData.length
                });
                
                renderizarParticipantes(resetar ? participantesData : novosParticipantes, resetar);
            } else {
                throw new Error(data.erro || 'Erro desconhecido');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar participantes:', error);
            if (resetar) {
                container.innerHTML = `
                    <div class="no-results">
                        <h3>Erro ao carregar participantes</h3>
                        <p>Tente recarregar a página</p>
                    </div>
                `;
            }
        })
        .finally(() => {
            carregandoMais = false;
            removerLoadingIndicator();
        });
}

// Renderizar grid de participantes
function renderizarParticipantes(participantes, resetar = true) {
    const container = document.getElementById('participantes-container');
    
    if (participantes.length === 0 && resetar) {
        container.innerHTML = `
            <div class="no-results">
                <h3>Nenhum participante encontrado</h3>
                <p>Tente ajustar os filtros ou adicionar um novo participante</p>
            </div>
        `;
        return;
    }
    
    let grid = container.querySelector('.participantes-grid');
    
    if (resetar || !grid) {
        grid = document.createElement('div');
        grid.className = 'participantes-grid';
        container.innerHTML = '';
        container.appendChild(grid);
    }
    
    // Se for reset, adicionar todos. Se não, adicionar apenas os novos participantes
    participantes.forEach(p => {
        const card = criarCardParticipante(p);
        grid.appendChild(card);
    });
    
    console.log('Renderizados:', participantes.length, 'participantes. Reset:', resetar);
    
    // Adicionar loading indicator se há mais para carregar
    if (temMaisParticipantes && !carregandoMais) {
        adicionarLoadingIndicator();
    }
}

// Criar card individual do participante
function criarCardParticipante(p) {
    const card = document.createElement('div');
    card.className = 'participante-card';
    card.onclick = () => abrirModal(p.id);
    
    // Status do participante (inscrito, pago, presente, cancelado)
    const statusParticipante = p.status || 'inscrito';
    
    // Status do pagamento (pendente, pago, cancelado, estornado)
    const statusPagamento = p.pagamento_status || 'pendente';
    
    card.innerHTML = `
        <div class="participante-header">
            <div class="participante-checkbox">
                <input type="checkbox" name="participantes[]" value="${p.id}" class="card-checkbox" onclick="event.stopPropagation()">
            </div>
            <div class="participante-info-principal">
                <h3 class="participante-nome">${escapeHtml(p.nome)}</h3>
                <p class="participante-evento">${escapeHtml(p.evento_nome || 'Sem evento')}</p>
                <p class="participante-cpf">${formatarCpf(p.cpf)}</p>
            </div>
            <div class="participante-actions">
                <button class="btn-delete-card" onclick="event.stopPropagation(); confirmarExclusao(${p.id}, '${escapeHtml(p.nome)}')" title="Excluir">
                    🗑️
                </button>
            </div>
        </div>
        
        <div class="participante-badges">
            <span class="status-badge status-${statusParticipante}">
                ${ucfirst(statusParticipante)}
            </span>
            <span class="status-badge status-pagamento-${statusPagamento}">
                ${p.valor > 0 ? `R$ ${formatarMoeda(p.valor)} - ${ucfirst(statusPagamento)}` : 'Gratuito'}
            </span>
        </div>
    `;
    
    return card;
}

// Inicializar filtros
function inicializarFiltros() {
    const filtros = ['busca', 'evento', 'status', 'pagamento', 'cidade'];
    
    filtros.forEach(filtro => {
        const elemento = document.getElementById(`filtro-${filtro}`);
        if (elemento) {
            elemento.addEventListener('input', debounce(() => {
                filtrosAtivos[filtro] = elemento.value;
                carregarParticipantes(true); // Sempre resetar quando filtrar
            }, 500));
        }
    });
    
    // Adicionar listener para checkboxes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('card-checkbox')) {
            atualizarContadorSelecionados();
        }
    });
}

// Limpar filtros
function limparFiltros() {
    const filtros = ['busca', 'evento', 'status', 'pagamento', 'cidade'];
    
    filtros.forEach(filtro => {
        const elemento = document.getElementById(`filtro-${filtro}`);
        if (elemento) {
            elemento.value = '';
        }
    });
    
    filtrosAtivos = {};
    carregarParticipantes();
}

// Abrir modal com detalhes
function abrirModal(participanteId) {
    const participante = participantesData.find(p => p.id == participanteId);
    if (!participante) return;
    
    participanteAtual = participante;
    
    // Carregar dados completos via AJAX
    fetch(`<?= SITE_URL ?>/admin/api/participante.php?id=${participanteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                renderizarModalConteudo(data.participante);
                document.getElementById('modal-participante').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados do participante:', error);
        });
}

// Renderizar conteúdo do modal
function renderizarModalConteudo(p) {
    const content = document.getElementById('modal-body-content');
    
    content.innerHTML = `
        <div class="modal-grid">
            <div class="modal-section">
                <h4>Dados Pessoais</h4>
                <p><strong>Nome:</strong> ${escapeHtml(p.nome)}</p>
                <p><strong>CPF:</strong> ${formatarCpf(p.cpf)}</p>
                <p><strong>Idade:</strong> ${p.idade} anos</p>
                <p><strong>Status:</strong> 
                    <span class="status-badge status-${p.status}">${ucfirst(p.status)}</span>
                </p>
            </div>
            
            <div class="modal-section">
                <h4>Contato</h4>
                <p><strong>WhatsApp:</strong> ${formatarTelefone(p.whatsapp)}</p>
                <p><strong>Email:</strong> ${escapeHtml(p.email)}</p>
                ${p.instagram ? `<p><strong>Instagram:</strong> @${escapeHtml(p.instagram)}</p>` : ''}
            </div>
            
            <div class="modal-section">
                <h4>Localização</h4>
                <p><strong>Cidade:</strong> ${escapeHtml(p.cidade)}</p>
                <p><strong>Estado:</strong> ${p.estado}</p>
            </div>
            
            <div class="modal-section">
                <h4>Evento</h4>
                <p><strong>Nome:</strong> ${escapeHtml(p.evento_nome || 'N/A')}</p>
                ${p.data_inicio ? `<p><strong>Data:</strong> ${formatarData(p.data_inicio)}</p>` : ''}
                ${p.pagamento_status ? `
                    <p><strong>Pagamento:</strong> 
                        <span class="status-badge status-${p.pagamento_status}">${ucfirst(p.pagamento_status)}</span>
                    </p>
                    ${p.valor ? `<p><strong>Valor:</strong> R$ ${formatarMoeda(p.valor)}</p>` : ''}
                ` : ''}
            </div>
        </div>
        
        ${p.criado_em ? `
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                <p><strong>Criado em:</strong> ${formatarDataHora(p.criado_em)}</p>
                ${p.checkin_timestamp ? `<p><strong>Check-in:</strong> ${formatarDataHora(p.checkin_timestamp)}</p>` : ''}
            </div>
        ` : ''}
    `;
    
    // Atualizar botões de ação
    document.getElementById('btn-editar-modal').onclick = () => editarParticipante(p.id);
    
    // Salvar dados do participante atual para as ações
    participanteAtual = p;
}

// Fechar modal
function fecharModal() {
    document.getElementById('modal-participante').style.display = 'none';
    participanteAtual = null;
}

// Editar participante
function editarParticipante(id = null) {
    const participanteId = id || (participanteAtual ? participanteAtual.id : null);
    if (participanteId) {
        window.location.href = `<?= SITE_URL ?>/admin/participantes.php?acao=editar&id=${participanteId}`;
    }
}

// Confirmar exclusão via modal de detalhes
function confirmarExclusaoModal() {
    if (!participanteAtual) return;
    
    document.getElementById('nome-participante-exclusao').textContent = participanteAtual.nome;
    document.getElementById('modal-exclusao').style.display = 'block';
    
    document.getElementById('btn-confirmar-exclusao').onclick = () => excluirParticipante(participanteAtual.id);
}

// Confirmar exclusão (função mantida para compatibilidade)
function confirmarExclusao(id, nome) {
    participanteAtual = { id, nome };
    document.getElementById('nome-participante-exclusao').textContent = nome;
    document.getElementById('modal-exclusao').style.display = 'block';
    
    document.getElementById('btn-confirmar-exclusao').onclick = () => excluirParticipante(id);
}

// Fechar modal de exclusão
function fecharModalExclusao() {
    document.getElementById('modal-exclusao').style.display = 'none';
    participanteAtual = null;
}

// Excluir participante
function excluirParticipante(id) {
    const btn = document.getElementById('btn-confirmar-exclusao');
    btn.innerHTML = '<div class="loading-spinner"></div> Excluindo...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= gerar_csrf_token() ?>');
    formData.append('id', id);
    
    fetch('<?= SITE_URL ?>/admin/api/excluir_participante.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Verificar se a resposta é JSON válido
        const contentType = response.headers.get('content-type');
        
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Erro: Resposta não é JSON:', text.substring(0, 200));
                throw new Error('Erro do servidor. Tente novamente.');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.sucesso) {
            fecharModalExclusao();
            carregarParticipantes(true); // Sempre resetar quando filtrar
            mostrarToast('Participante excluído com sucesso!', 'success');
        } else {
            mostrarToast(data.mensagem || 'Erro ao excluir participante', 'error');
        }
    })
    .catch(error => {
        console.error('Erro ao excluir participante:', error);
        mostrarToast('Erro de comunicação com o servidor: ' + error.message, 'error');
    })
    .finally(() => {
        btn.innerHTML = 'Excluir';
        btn.disabled = false;
    });
}

// Fechar modais ao clicar fora
window.onclick = function(event) {
    const modalParticipante = document.getElementById('modal-participante');
    const modalExclusao = document.getElementById('modal-exclusao');
    
    if (event.target === modalParticipante) {
        fecharModal();
    }
    if (event.target === modalExclusao) {
        fecharModalExclusao();
    }
}

// Funções utilitárias
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatarCpf(cpf) {
    if (!cpf) return '';
    const limpo = cpf.replace(/\D/g, '');
    return limpo.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function formatarTelefone(tel) {
    if (!tel) return '';
    const cleaned = tel.replace(/\D/g, '');
    if (cleaned.length === 11) {
        return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }
    return tel;
}

function formatarMoeda(valor) {
    return parseFloat(valor || 0).toFixed(2).replace('.', ',');
}

function formatarData(data) {
    if (!data) return '';
    return new Date(data).toLocaleDateString('pt-BR');
}

function formatarDataHora(data) {
    if (!data) return '';
    return new Date(data).toLocaleString('pt-BR');
}

function mostrarToast(mensagem, tipo = 'info') {
    // Remover toasts existentes
    const existentes = document.querySelectorAll('.toast');
    existentes.forEach(t => t.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    
    const cores = {
        success: { bg: '#dcfce7', border: '#10b981', color: '#065f46' },
        error: { bg: '#fef2f2', border: '#ef4444', color: '#991b1b' },
        warning: { bg: '#fef3c7', border: '#f59e0b', color: '#92400e' },
        info: { bg: '#dbeafe', border: '#3b82f6', color: '#1e40af' }
    };
    
    const cor = cores[tipo] || cores.info;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 2000;
        max-width: 400px;
        background: ${cor.bg};
        border: 1px solid ${cor.border};
        color: ${cor.color};
        animation: slideInRight 0.3s ease;
    `;
    
    toast.textContent = mensagem;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Ações em massa
function atualizarContadorSelecionados() {
    const checkboxes = document.querySelectorAll('.card-checkbox:checked');
    const count = checkboxes.length;
    const countElement = document.getElementById('selecionados-count');
    const acoesMassaSection = document.querySelector('.acoes-massa-section');
    
    if (countElement) {
        countElement.textContent = `${count} selecionado${count !== 1 ? 's' : ''}`;
    }
    
    if (acoesMassaSection) {
        acoesMassaSection.style.display = count > 0 ? 'block' : 'none';
    }
    
    // Atualizar checkbox "selecionar todos"
    const selectAllCheckbox = document.getElementById('select-all-cards');
    const allCheckboxes = document.querySelectorAll('.card-checkbox');
    
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        selectAllCheckbox.checked = count === allCheckboxes.length;
        selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
    }
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all-cards');
    const checkboxes = document.querySelectorAll('.card-checkbox');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAllCheckbox.checked;
    });
    
    atualizarContadorSelecionados();
}

function cancelarSelecao() {
    const checkboxes = document.querySelectorAll('.card-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    
    const selectAllCheckbox = document.getElementById('select-all-cards');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }
    
    atualizarContadorSelecionados();
}

function executarAcaoMassa() {
    const acao = document.getElementById('acao-massa').value;
    const checkboxes = document.querySelectorAll('.card-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (!acao) {
        mostrarToast('Selecione uma ação', 'warning');
        return;
    }
    
    if (ids.length === 0) {
        mostrarToast('Selecione pelo menos um participante', 'warning');
        return;
    }
    
    const acoes = {
        'aprovar': 'aprovar as inscrições',
        'rejeitar': 'rejeitar as inscrições',
        'cancelar': 'cancelar as inscrições',
        'excluir': 'excluir os participantes'
    };
    
    if (!confirm(`Tem certeza que deseja ${acoes[acao]} de ${ids.length} participante(s)?`)) {
        return;
    }
    
    // Mostrar loading
    const btnExecutar = document.querySelector('.acoes-massa-buttons .btn-primary');
    const textoOriginal = btnExecutar.innerHTML;
    btnExecutar.innerHTML = '<div class="loading-spinner"></div> Processando...';
    btnExecutar.disabled = true;
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= gerar_csrf_token() ?>');
    formData.append('acao', acao);
    ids.forEach(id => formData.append('ids[]', id));
    
    fetch('<?= SITE_URL ?>/admin/api/acao_massa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Erro: Resposta não é JSON:', text.substring(0, 200));
                throw new Error('Erro do servidor. Tente novamente.');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.sucesso || data.processados > 0) {
            mostrarToast(data.mensagem, data.erros > 0 ? 'warning' : 'success');
            carregarParticipantes(true); // Sempre resetar quando filtrar // Recarregar lista
            cancelarSelecao(); // Limpar seleção
        } else {
            mostrarToast(data.mensagem || 'Erro ao processar ação em massa', 'error');
        }
    })
    .catch(error => {
        console.error('Erro na ação em massa:', error);
        mostrarToast('Erro ao processar ação. Tente novamente.', 'error');
    })
    .finally(() => {
        // Restaurar botão
        btnExecutar.innerHTML = textoOriginal;
        btnExecutar.disabled = false;
    });
}

// Scroll infinito
function inicializarScrollInfinito() {
    let throttleTimer = null;
    
    window.addEventListener('scroll', function() {
        if (throttleTimer) return;
        
        throttleTimer = setTimeout(() => {
            verificarScrollCarregar();
            throttleTimer = null;
        }, 200);
    });
}

function verificarScrollCarregar() {
    if (carregandoMais || !temMaisParticipantes) {
        console.log('Scroll ignorado:', { carregandoMais, temMaisParticipantes });
        return;
    }
    
    const scrollTop = window.pageYOffset;
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const distanciaDoFinal = documentHeight - (scrollTop + windowHeight);
    
    console.log('Verificando scroll:', {
        scrollTop,
        windowHeight,
        documentHeight,
        distanciaDoFinal,
        trigger: distanciaDoFinal <= 300
    });
    
    // Carregar mais quando estiver a 300px do final
    if (distanciaDoFinal <= 300) {
        console.log('Trigger ativado! Carregando mais...');
        carregarParticipantes(false);
    }
}

function adicionarLoadingIndicator() {
    removerLoadingIndicator(); // Remove qualquer indicador existente
    
    const container = document.getElementById('participantes-container');
    const indicator = document.createElement('div');
    indicator.className = 'scroll-loading-indicator';
    indicator.id = 'scroll-loading';
    indicator.innerHTML = `
        <div class="loading-spinner"></div>
        <p>Carregando mais participantes...</p>
    `;
    
    container.appendChild(indicator);
}

function removerLoadingIndicator() {
    const indicator = document.getElementById('scroll-loading');
    if (indicator) {
        indicator.remove();
    }
}

// Máscaras de input
function inicializarMascaras() {
    // Máscara e validação CPF
    const cpfInputs = document.querySelectorAll('[data-mask="cpf"]');
    cpfInputs.forEach(input => {
        input.addEventListener('input', function() {
            let valor = this.value.replace(/\D/g, '');
            valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            this.value = valor;
        });
        
        // Validação em tempo real do CPF
        input.addEventListener('blur', function() {
            const cpf = this.value;
            if (window.cpfObrigatorio && cpf && !validarCPF(cpf)) {
                this.setCustomValidity('CPF inválido');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Máscara Telefone
    const telInputs = document.querySelectorAll('[data-mask="telefone"]');
    telInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            }
            this.value = value;
        });
    });
}
</script>

<?php
obter_rodape_admin();
?>