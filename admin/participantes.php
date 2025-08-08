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

// Processar exclusão via GET (com CSRF token)
if ($acao === 'excluir' && $participante_id && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!verificar_csrf_token($_GET['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        try {
            // Buscar dados do participante para o log
            $participante_data = buscar_um("SELECT nome, status FROM participantes WHERE id = ?", [$participante_id]);
            
            if (!$participante_data) {
                $erro = 'Participante não encontrado.';
            } else {
                // Verificar se há pagamentos associados
                $pagamentos = contar_registros('pagamentos', ['participante_id' => $participante_id]);
                
                if ($pagamentos > 0 && $participante_data['status'] === 'pago') {
                    $erro = 'Não é possível excluir este participante. Há pagamentos confirmados associados. Altere o status para cancelado primeiro.';
                } else {
                    if (remover_registro('participantes', ['id' => $participante_id])) {
                        registrar_log('participante_excluido', "Participante: {$participante_data['nome']} (ID: {$participante_id})");
                        exibir_mensagem('Participante excluído com sucesso!', 'success');
                        redirecionar(SITE_URL . '/admin/participantes.php');
                    } else {
                        $erro = 'Erro ao excluir participante.';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao excluir participante: " . $e->getMessage());
            $erro = 'Erro interno ao excluir participante.';
        }
    }
}

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
    $participante = buscar_um("
        SELECT p.*, e.nome as evento_nome, e.slug as evento_slug, e.data_inicio,
               pag.status as pagamento_status, pag.valor, pag.pago_em
        FROM participantes p
        JOIN eventos e ON p.evento_id = e.id
        LEFT JOIN pagamentos pag ON p.id = pag.participante_id
        WHERE p.id = ?
    ", [$participante_id]);
    
    if (!$participante) {
        exibir_erro_404();
    }
}

// Buscar participantes para listagem
$filtros = [
    'busca' => $_GET['busca'] ?? '',
    'evento' => $_GET['evento'] ?? '',
    'status' => $_GET['status'] ?? '',
    'cidade' => $_GET['cidade'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? ''
];

$where_conditions = ['1=1'];
$params = [];

if (!empty($filtros['busca'])) {
    $where_conditions[] = '(p.nome LIKE ? OR p.cpf LIKE ? OR p.email LIKE ?)';
    $busca = '%' . $filtros['busca'] . '%';
    $params[] = $busca;
    $params[] = $busca;
    $params[] = $busca;
}

if (!empty($filtros['evento'])) {
    $where_conditions[] = 'p.evento_id = ?';
    $params[] = $filtros['evento'];
}

if (!empty($filtros['status'])) {
    $where_conditions[] = 'p.status = ?';
    $params[] = $filtros['status'];
}

if (!empty($filtros['cidade'])) {
    $where_conditions[] = 'p.cidade LIKE ?';
    $params[] = '%' . $filtros['cidade'] . '%';
}

if (!empty($filtros['data_inicio'])) {
    $where_conditions[] = 'p.criado_em >= ?';
    $params[] = $filtros['data_inicio'] . ' 00:00:00';
}

if (!empty($filtros['data_fim'])) {
    $where_conditions[] = 'p.criado_em <= ?';
    $params[] = $filtros['data_fim'] . ' 23:59:59';
}

$where_clause = implode(' AND ', $where_conditions);

// Paginação
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$total_participantes = buscar_um("
    SELECT COUNT(*) as total 
    FROM participantes p
    JOIN eventos e ON p.evento_id = e.id
    WHERE {$where_clause}
", $params)['total'];

$total_paginas = ceil($total_participantes / $por_pagina);

$participantes = buscar_todos("
    SELECT p.*, e.nome as evento_nome, e.slug as evento_slug, e.data_inicio,
           pag.status as pagamento_status, pag.valor, pag.pago_em
    FROM participantes p
    JOIN eventos e ON p.evento_id = e.id
    LEFT JOIN pagamentos pag ON p.id = pag.participante_id
    WHERE {$where_clause}
    ORDER BY p.criado_em DESC
    LIMIT {$por_pagina} OFFSET {$offset}
", $params);

// Eventos para filtro
$eventos = buscar_todos("SELECT id, nome FROM eventos ORDER BY data_inicio DESC");

// Cidades para filtro
$cidades = buscar_todos("SELECT DISTINCT cidade FROM participantes WHERE cidade IS NOT NULL ORDER BY cidade");

/**
 * Salvar participante (criar ou editar)
 */
function salvar_participante($dados, $participante_id = null) {
    try {
        // Validações
        $erros = [];
        
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        
        // Validar CPF apenas se a verificação estiver ativada
        if (cpf_obrigatorio()) {
            if (empty($dados['cpf']) || !validar_cpf($dados['cpf'])) $erros[] = 'CPF inválido';
        } elseif (!empty($dados['cpf']) && !validar_cpf($dados['cpf'])) {
            // Se o CPF foi preenchido mas está inválido, mesmo que não seja obrigatório
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

<?php if ($acao === 'listar'): ?>
    
    <!-- Estatísticas Rápidas -->
    <div class="stats-grid">
        <?php
        $stats = buscar_um("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'inscrito' THEN 1 ELSE 0 END) as inscritos,
                SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pagos,
                SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes
            FROM participantes
            WHERE status != 'cancelado'
        ");
        ?>
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

    <!-- Filtros Avançados -->
    <div class="admin-filters">
        <form method="GET" class="filters-row">
            <div class="form-group-admin">
                <input type="text" name="busca" placeholder="Buscar por nome, CPF ou email..." 
                       value="<?= htmlspecialchars($filtros['busca']) ?>" class="form-input-admin">
            </div>
            
            <div class="form-group-admin">
                <select name="evento" class="form-select-admin">
                    <option value="">Todos os eventos</option>
                    <?php foreach ($eventos as $ev): ?>
                        <option value="<?= $ev['id'] ?>" <?= $filtros['evento'] == $ev['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ev['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group-admin">
                <select name="status" class="form-select-admin">
                    <option value="">Todos os status</option>
                    <option value="inscrito" <?= $filtros['status'] === 'inscrito' ? 'selected' : '' ?>>Inscrito</option>
                    <option value="pago" <?= $filtros['status'] === 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="presente" <?= $filtros['status'] === 'presente' ? 'selected' : '' ?>>Presente</option>
                    <option value="cancelado" <?= $filtros['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            
            <div class="form-group-admin">
                <input type="text" name="cidade" placeholder="Cidade..." 
                       value="<?= htmlspecialchars($filtros['cidade']) ?>" class="form-input-admin">
            </div>
            
            <div class="form-group-admin">
                <input type="date" name="data_inicio" placeholder="Data início..." 
                       value="<?= $filtros['data_inicio'] ?>" class="form-input-admin">
            </div>
            
            <div class="form-group-admin">
                <input type="date" name="data_fim" placeholder="Data fim..." 
                       value="<?= $filtros['data_fim'] ?>" class="form-input-admin">
            </div>
            
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="<?= SITE_URL ?>/admin/participantes.php" class="btn btn-outline">Limpar</a>
            <a href="<?= SITE_URL ?>/admin/participantes.php?acao=criar" class="btn btn-primary">Novo Participante</a>
        </form>
    </div>

    <!-- Ações em Lote -->
    <div class="bulk-actions">
        <form method="POST" id="form-lote">
            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
            <select name="acao_lote" class="form-select-admin">
                <option value="">Ações em lote...</option>
                <option value="marcar_pago">Marcar como Pago</option>
                <option value="marcar_presente">Marcar como Presente</option>
                <option value="cancelar">Cancelar Inscrições</option>
                <option value="exportar">Exportar Selecionados</option>
            </select>
            <button type="button" onclick="executarAcaoLote()" class="btn btn-outline">Executar</button>
        </form>
    </div>

    <!-- Tabela de Participantes -->
    <div class="admin-table">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Participante</th>
                    <th>Evento</th>
                    <th>Contato</th>
                    <th>Status</th>
                    <th>Pagamento</th>
                    <th>Data Inscrição</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($participantes)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                            Nenhum participante encontrado
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($participantes as $p): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="participantes[]" value="<?= $p['id'] ?>" class="select-item">
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                    <br>
                                    <small style="color: #6b7280;">
                                        CPF: <?= formatarCpf($p['cpf']) ?><br>
                                        <?= $p['idade'] ?> anos - <?= htmlspecialchars($p['cidade']) ?>, <?= $p['estado'] ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <a href="<?= SITE_URL ?>/evento/<?= $p['evento_id'] ?>" target="_blank">
                                    <?= htmlspecialchars($p['evento_nome']) ?>
                                </a>
                                <br>
                                <small style="color: #6b7280;">
                                    <?= formatar_data($p['data_inicio']) ?>
                                </small>
                            </td>
                            <td>
                                <div>
                                    📱 <?= formatarTelefone($p['whatsapp']) ?><br>
                                    📧 <?= htmlspecialchars($p['email']) ?><br>
                                    <?php if ($p['instagram']): ?>
                                        📷 @<?= htmlspecialchars($p['instagram']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge-admin status-<?= $p['status'] ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                                <?php if ($p['status'] === 'presente' && $p['checkin_timestamp']): ?>
                                    <br><small>Check-in: <?= formatar_data_hora($p['checkin_timestamp']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['valor'] > 0): ?>
                                    <span class="status-badge-admin status-<?= $p['pagamento_status'] ?>">
                                        <?= ucfirst($p['pagamento_status']) ?>
                                    </span>
                                    <br>R$ <?= formatar_moeda($p['valor']) ?>
                                    <?php if ($p['pago_em']): ?>
                                        <br><small><?= formatar_data($p['pago_em']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge-admin status-gratuito">Gratuito</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= formatar_data_hora($p['criado_em']) ?>
                            </td>
                            <td class="actions">
                                <a href="?acao=editar&id=<?= $p['id'] ?>" class="btn-table edit">Editar</a>
                                
                                <!-- Dropdown de status -->
                                <div class="dropdown">
                                    <button class="btn-table status" onclick="toggleDropdown(<?= $p['id'] ?>)">Status ▼</button>
                                    <div class="dropdown-content" id="dropdown-<?= $p['id'] ?>">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
                                            <input type="hidden" name="acao" value="alterar_status">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="novo_status" value="inscrito">Inscrito</button>
                                            <button type="submit" name="novo_status" value="pago">Pago</button>
                                            <button type="submit" name="novo_status" value="presente">Presente</button>
                                            <button type="submit" name="novo_status" value="cancelado" 
                                                    onclick="return confirm('Confirma cancelamento?')">Cancelado</button>
                                        </form>
                                    </div>
                                </div>
                                
                                <a href="?acao=excluir&id=<?= $p['id'] ?>&csrf_token=<?= gerar_csrf_token() ?>" 
                                   class="btn-table delete" 
                                   onclick="return confirmarExclusaoParticipante(this, '<?= htmlspecialchars($p['nome']) ?>')">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1 ?>&<?= http_build_query($filtros) ?>">« Anterior</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?pagina=<?= $i ?>&<?= http_build_query($filtros) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?>&<?= http_build_query($filtros) ?>">Próxima »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

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

<script>
// Seleção em lote
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.select-item');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Dropdown de status
function toggleDropdown(id) {
    const dropdown = document.getElementById('dropdown-' + id);
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

// Fechar dropdowns ao clicar fora
document.addEventListener('click', function(e) {
    if (!e.target.matches('.btn-table.status')) {
        const dropdowns = document.querySelectorAll('.dropdown-content');
        dropdowns.forEach(d => d.style.display = 'none');
    }
});

// Ações em lote
function executarAcaoLote() {
    const form = document.getElementById('form-lote');
    const acao = form.querySelector('[name="acao_lote"]').value;
    const selecionados = document.querySelectorAll('.select-item:checked');
    
    if (!acao) {
        alert('Selecione uma ação');
        return;
    }
    
    if (selecionados.length === 0) {
        alert('Selecione pelo menos um participante');
        return;
    }
    
    if (confirm(`Confirma a ação "${acao}" para ${selecionados.length} participante(s)?`)) {
        // Aqui você implementaria a lógica para ações em lote
        alert('Funcionalidade em desenvolvimento');
    }
}

// Máscaras de input
document.addEventListener('DOMContentLoaded', function() {
    // Máscara CPF
    const cpfInputs = document.querySelectorAll('[data-mask="cpf"]');
    cpfInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
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
});

// Confirmação de exclusão para participantes
function confirmarExclusaoParticipante(elemento, nome) {
    const confirmacao = confirm(
        `Tem certeza que deseja excluir o participante "${nome}"?\n\n` +
        `Esta ação não pode ser desfeita e irá remover:\n` +
        `• Todos os dados do participante\n` +
        `• Histórico de pagamentos\n` +
        `• QR codes associados\n\n` +
        `Digite "EXCLUIR" para confirmar:`
    );
    
    if (confirmacao) {
        const confirmacaoTexto = prompt('Digite "EXCLUIR" para confirmar a exclusão:');
        if (confirmacaoTexto === 'EXCLUIR') {
            // Adicionar loading
            elemento.innerHTML = '⏳ Excluindo...';
            elemento.style.pointerEvents = 'none';
            return true;
        } else {
            alert('Exclusão cancelada. O texto de confirmação não confere.');
            return false;
        }
    }
    return false;
}

// Melhorar UX das tabelas
document.addEventListener('DOMContentLoaded', function() {
    // Highlight da linha ao passar o mouse
    const linhas = document.querySelectorAll('.admin-table tbody tr');
    linhas.forEach(linha => {
        linha.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8fafc';
        });
        linha.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Auto-submit dos filtros após 500ms de inatividade
    const filtros = document.querySelectorAll('.admin-filters input, .admin-filters select');
    let timeoutId;
    
    filtros.forEach(filtro => {
        filtro.addEventListener('input', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    });
});
</script>

<?php
obter_rodape_admin();
?> 