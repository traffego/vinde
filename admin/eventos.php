<?php
require_once '../includes/init.php';

// Verificar login
requer_login();

// Ações do CRUD
$acao = $_GET['acao'] ?? 'listar';
$evento_id = $_GET['id'] ?? null;
$erro = '';
$sucesso = '';

// Processar exclusão via GET (com CSRF token)
if ($acao === 'excluir' && $evento_id && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!verificar_csrf_token($_GET['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        try {
            // Verificar se há inscrições e pagamentos vinculados (novo modelo)
            $participantes = buscar_um("SELECT COUNT(*) AS total FROM inscricoes WHERE evento_id = ? AND status != 'cancelada'", [$evento_id])['total'] ?? 0;
            $pagos = buscar_um("SELECT COUNT(*) AS total FROM pagamentos pg JOIN inscricoes i ON pg.inscricao_id = i.id WHERE i.evento_id = ? AND pg.status = 'pago'", [$evento_id])['total'] ?? 0;
            
            if ($participantes > 0 || $pagos > 0) {
                $erro = "Não é possível excluir este evento. Há {$participantes} participantes inscritos e {$pagos} com pagamento confirmado. Cancele as inscrições primeiro.";
            } else {
                // Buscar nome do evento para o log
                $evento_nome = buscar_um("SELECT nome FROM eventos WHERE id = ?", [$evento_id])['nome'] ?? "ID: {$evento_id}";
                
                if (remover_registro('eventos', ['id' => $evento_id])) {
                    registrar_log('evento_excluido', "Evento: {$evento_nome} (ID: {$evento_id})");
                    exibir_mensagem('Evento excluído com sucesso!', 'success');
                    redirecionar(SITE_URL . '/admin/eventos.php');
                } else {
                    $erro = 'Erro ao excluir evento.';
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao excluir evento: " . $e->getMessage());
            $erro = 'Erro interno ao excluir evento.';
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
                $resultado = salvar_evento($_POST, $evento_id);
                if ($resultado['sucesso']) {
                    $sucesso = $resultado['mensagem'];
                    if ($acao === 'criar') {
                        redirecionar(SITE_URL . '/admin/eventos.php?acao=editar&id=' . $resultado['id']);
                    }
                } else {
                    $erro = $resultado['mensagem'];
                }
                break;
        }
    }
}

// Buscar dados para edição
$evento = [];
if (($acao === 'editar' || $acao === 'visualizar') && $evento_id) {
    $evento = buscar_um("SELECT * FROM eventos WHERE id = ?", [$evento_id]);
    if (!$evento) {
        exibir_erro_404();
    }
}

// Buscar eventos para listagem
$filtros = [
    'busca' => $_GET['busca'] ?? '',
    'status' => $_GET['status'] ?? '',
    'cidade' => $_GET['cidade'] ?? ''
];

$where_conditions = ['1=1'];
$params = [];

if (!empty($filtros['busca'])) {
    $where_conditions[] = '(nome LIKE ? OR descricao LIKE ?)';
    $params[] = '%' . $filtros['busca'] . '%';
    $params[] = '%' . $filtros['busca'] . '%';
}

if (!empty($filtros['status'])) {
    $where_conditions[] = 'status = ?';
    $params[] = $filtros['status'];
}

if (!empty($filtros['cidade'])) {
    $where_conditions[] = 'cidade = ?';
    $params[] = $filtros['cidade'];
}

$where_clause = implode(' AND ', $where_conditions);

// Paginação
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$total_eventos = buscar_um("SELECT COUNT(*) as total FROM eventos WHERE {$where_clause}", $params)['total'];
$total_paginas = ceil($total_eventos / $por_pagina);

$eventos = buscar_todos("
    SELECT e.*, 
           COUNT(i.id) AS total_inscritos,
           (e.limite_participantes - COUNT(i.id)) AS vagas_restantes
    FROM eventos e
    LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente','aprovada')
    WHERE {$where_clause}
    GROUP BY e.id
    ORDER BY e.criado_em DESC
    LIMIT {$por_pagina} OFFSET {$offset}
", $params);

// Cidades para filtro
$cidades = buscar_todos("SELECT DISTINCT cidade FROM eventos ORDER BY cidade");

/**
 * Salvar evento (criar ou editar)
 */
function salvar_evento($dados, $evento_id = null) {
    try {
        // Validações
        $erros = [];
        
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        if (empty($dados['data_inicio'])) $erros[] = 'Data de início é obrigatória';
        if (empty($dados['local'])) $erros[] = 'Local é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatória';
        if (!is_numeric($dados['limite_participantes']) || $dados['limite_participantes'] < 1) {
            $erros[] = 'Limite de participantes deve ser um número maior que 0';
        }
        
        if (!empty($erros)) {
            return ['sucesso' => false, 'mensagem' => implode(', ', $erros)];
        }
        
        // Gerar slug
        $slug = gerar_slug($dados['nome']);
        if ($evento_id) {
            $slug_existente = buscar_um("SELECT slug FROM eventos WHERE id = ?", [$evento_id])['slug'];
            if ($slug !== $slug_existente) {
                $contador = 1;
                $slug_original = $slug;
                while (buscar_um("SELECT id FROM eventos WHERE slug = ? AND id != ?", [$slug, $evento_id])) {
                    $slug = $slug_original . '-' . $contador;
                    $contador++;
                }
            }
        } else {
            $contador = 1;
            $slug_original = $slug;
            while (buscar_um("SELECT id FROM eventos WHERE slug = ?", [$slug])) {
                $slug = $slug_original . '-' . $contador;
                $contador++;
            }
        }
        
        // Processar programação (JSON)
        $programacao = [];
        if (!empty($dados['programacao_horario'])) {
            for ($i = 0; $i < count($dados['programacao_horario']); $i++) {
                if (!empty($dados['programacao_titulo'][$i])) {
                    $programacao[] = [
                        'horario' => $dados['programacao_horario'][$i] ?? '',
                        'titulo' => $dados['programacao_titulo'][$i] ?? '',
                        'descricao' => $dados['programacao_descricao'][$i] ?? '',
                        'palestrante' => $dados['programacao_palestrante'][$i] ?? ''
                    ];
                }
            }
        }
        
        // Processar o que está incluído
        $inclui = [];
        if (!empty($dados['inclui'])) {
            $inclui = array_filter(explode("\n", $dados['inclui']), 'trim');
        }
        
        // Upload de imagem
        $imagem = null;
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $imagem = fazer_upload_imagem($_FILES['imagem'], 'eventos');
            if (!$imagem) {
                return ['sucesso' => false, 'mensagem' => 'Erro no upload da imagem'];
            }
        }
        
        // Preparar dados para salvamento
        $evento_dados = [
            'nome' => sanitizar_entrada($dados['nome']),
            'slug' => $slug,
            'descricao' => sanitizar_entrada($dados['descricao'] ?? ''),
            'descricao_completa' => $dados['descricao_completa'] ?? '',
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => $dados['data_fim'] ?: null,
            'horario_inicio' => $dados['horario_inicio'] ?: null,
            'horario_fim' => $dados['horario_fim'] ?: null,
            'local' => sanitizar_entrada($dados['local']),
            'endereco' => $dados['endereco'] ?? '',
            'cidade' => sanitizar_entrada($dados['cidade']),
            'estado' => $dados['estado'] ?? 'SP',
            'valor' => floatval($dados['valor'] ?? 0),
            'limite_participantes' => intval($dados['limite_participantes']),
            'tipo' => $dados['tipo'] ?? 'presencial',
            'status' => $dados['status'] ?? 'ativo',
            'programacao' => !empty($programacao) ? json_encode($programacao) : null,
            'inclui' => !empty($inclui) ? json_encode($inclui) : null
        ];
        
        if ($imagem) {
            $evento_dados['imagem'] = $imagem;
        }
        
        if ($evento_id) {
            // Editar
            $sucesso = atualizar_registro('eventos', $evento_dados, ['id' => $evento_id]);
            $acao_log = 'evento_editado';
            $id_resultado = $evento_id;
        } else {
            // Criar
            $id_resultado = inserir_registro('eventos', $evento_dados);
            $sucesso = $id_resultado > 0;
            $acao_log = 'evento_criado';
        }
        
        if ($sucesso) {
            registrar_log($acao_log, "Evento: {$dados['nome']} (ID: {$id_resultado})");
            return [
                'sucesso' => true, 
                'mensagem' => $evento_id ? 'Evento atualizado com sucesso!' : 'Evento criado com sucesso!',
                'id' => $id_resultado
            ];
        } else {
            return ['sucesso' => false, 'mensagem' => 'Erro ao salvar evento'];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao salvar evento: " . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno ao salvar evento'];
    }
}

// Definir título da página
$titulos = [
    'listar' => 'Eventos',
    'criar' => 'Novo Evento',
    'editar' => 'Editar Evento',
    'visualizar' => 'Visualizar Evento'
];

$titulo_pagina = $titulos[$acao] ?? 'Eventos';

obter_cabecalho_admin($titulo_pagina, 'eventos');
?>

<?php if ($acao === 'listar'): ?>
    
    <!-- Filtros -->
    <div class="admin-filters">
        <form method="GET" class="filters-row">
            <div class="form-group-admin">
                <input type="text" name="busca" placeholder="Buscar eventos..." 
                       value="<?= htmlspecialchars($filtros['busca']) ?>" class="form-input-admin">
            </div>
            
            <div class="form-group-admin">
                <select name="status" class="form-select-admin">
                    <option value="">Todos os status</option>
                    <option value="ativo" <?= $filtros['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= $filtros['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    <option value="finalizado" <?= $filtros['status'] === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                    <option value="esgotado" <?= $filtros['status'] === 'esgotado' ? 'selected' : '' ?>>Esgotado</option>
                </select>
            </div>
            
            <div class="form-group-admin">
                <select name="cidade" class="form-select-admin">
                    <option value="">Todas as cidades</option>
                    <?php foreach ($cidades as $cidade_opt): ?>
                        <option value="<?= htmlspecialchars($cidade_opt['cidade']) ?>" 
                                <?= $filtros['cidade'] === $cidade_opt['cidade'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cidade_opt['cidade']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="<?= SITE_URL ?>/admin/eventos.php" class="btn btn-outline">Limpar</a>
            <a href="<?= SITE_URL ?>/admin/eventos.php?acao=criar" class="btn btn-primary">Novo Evento</a>
        </form>
    </div>

    <!-- Tabela de Eventos -->
    <div class="admin-table">
        <table>
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>Data</th>
                    <th>Local</th>
                    <th>Inscrições</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($eventos)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                            Nenhum evento encontrado
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($eventos as $ev): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($ev['nome']) ?></strong>
                                    <br>
                                    <small style="color: #6b7280;">
                                        <?= substr(htmlspecialchars($ev['descricao']), 0, 80) ?>
                                        <?= strlen($ev['descricao']) > 80 ? '...' : '' ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <?= formatar_data($ev['data_inicio']) ?>
                                <?php if ($ev['data_fim'] && $ev['data_fim'] !== $ev['data_inicio']): ?>
                                    <br><small>a <?= formatar_data($ev['data_fim']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($ev['local']) ?>
                                <br>
                                <small style="color: #6b7280;"><?= htmlspecialchars($ev['cidade']) ?>, <?= $ev['estado'] ?></small>
                            </td>
                            <td>
                                <strong><?= $ev['total_inscritos'] ?>/<?= $ev['limite_participantes'] ?></strong>
                                <br>
                                <small style="color: #6b7280;">
                                    <?= $ev['vagas_restantes'] ?> vagas restantes
                                </small>
                            </td>
                            <td>
                                <span class="status-badge-admin status-<?= $ev['status'] ?>">
                                    <?= ucfirst($ev['status']) ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?= SITE_URL ?>/evento/<?= $ev['id'] ?>" 
                                   target="_blank" class="btn-table view" title="Ver no site">Ver</a>
                                <a href="?acao=editar&id=<?= $ev['id'] ?>" 
                                   class="btn-table edit">Editar</a>
                                <a href="?acao=excluir&id=<?= $ev['id'] ?>&csrf_token=<?= gerar_csrf_token() ?>" 
                                   class="btn-table delete" 
                                   onclick="return confirmarExclusao(this, '<?= htmlspecialchars($ev['nome']) ?>')">Excluir</a>
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
        
        <form method="POST" enctype="multipart/form-data" data-autosave="evento_<?= $evento_id ?? 'novo' ?>">
            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
            
            <!-- Informações Básicas -->
            <h3>Informações Básicas</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Nome do Evento *</label>
                    <input type="text" name="nome" class="form-input-admin required" 
                           value="<?= htmlspecialchars($evento['nome'] ?? '') ?>" required>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Status</label>
                    <select name="status" class="form-select-admin">
                        <option value="ativo" <?= ($evento['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($evento['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="finalizado" <?= ($evento['status'] ?? '') === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Descrição Curta</label>
                    <textarea name="descricao" class="form-textarea-admin" rows="3" maxlength="250"
                              placeholder="Descrição que aparece na listagem de eventos"><?= htmlspecialchars($evento['descricao'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Descrição Completa</label>
                    <textarea name="descricao_completa" class="form-textarea-admin" rows="6"
                              placeholder="Descrição detalhada que aparece na página do evento"><?= htmlspecialchars($evento['descricao_completa'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Data e Horário -->
            <h3>Data e Horário</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Data de Início *</label>
                    <input type="date" name="data_inicio" class="form-input-admin required" 
                           value="<?= $evento['data_inicio'] ?? '' ?>" required>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Data de Fim</label>
                    <input type="date" name="data_fim" class="form-input-admin" 
                           value="<?= $evento['data_fim'] ?? '' ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Horário de Início</label>
                    <input type="time" name="horario_inicio" class="form-input-admin" 
                           value="<?= $evento['horario_inicio'] ?? '' ?>">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Horário de Fim</label>
                    <input type="time" name="horario_fim" class="form-input-admin" 
                           value="<?= $evento['horario_fim'] ?? '' ?>">
                </div>
            </div>
            
            <!-- Local -->
            <h3>Local e Endereço</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Local *</label>
                    <input type="text" name="local" class="form-input-admin required" 
                           value="<?= htmlspecialchars($evento['local'] ?? '') ?>" 
                           placeholder="Ex: Igreja Matriz São José" required>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Tipo</label>
                    <select name="tipo" class="form-select-admin">
                        <option value="presencial" <?= ($evento['tipo'] ?? 'presencial') === 'presencial' ? 'selected' : '' ?>>Presencial</option>
                        <option value="online" <?= ($evento['tipo'] ?? '') === 'online' ? 'selected' : '' ?>>Online</option>
                        <option value="hibrido" <?= ($evento['tipo'] ?? '') === 'hibrido' ? 'selected' : '' ?>>Híbrido</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Endereço Completo</label>
                    <textarea name="endereco" class="form-textarea-admin" rows="2"
                              placeholder="Rua, número, bairro, CEP"><?= htmlspecialchars($evento['endereco'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Cidade *</label>
                    <input type="text" name="cidade" class="form-input-admin required" 
                           value="<?= htmlspecialchars($evento['cidade'] ?? '') ?>" required>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Estado</label>
                    <select name="estado" class="form-select-admin">
                        <option value="SP" <?= ($evento['estado'] ?? 'SP') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                        <option value="RJ" <?= ($evento['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                        <option value="MG" <?= ($evento['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                        <!-- Adicionar outros estados conforme necessário -->
                    </select>
                </div>
            </div>
            
            <!-- Inscrições -->
            <h3>Inscrições e Valor</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Valor (R$)</label>
                    <input type="number" name="valor" class="form-input-admin" step="0.01" min="0" 
                           value="<?= $evento['valor'] ?? '0' ?>" placeholder="0.00">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Limite de Participantes *</label>
                    <input type="number" name="limite_participantes" class="form-input-admin required" 
                           min="1" max="10000" value="<?= $evento['limite_participantes'] ?? '100' ?>" required>
                </div>
            </div>
            
            <!-- Imagem -->
            <h3>Imagem do Evento</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Imagem</label>
                    <input type="file" name="imagem" class="form-input-admin" accept="image/*">
                    <?php if (!empty($evento['imagem'])): ?>
                        <div class="image-preview">
                            <img src="<?= SITE_URL ?>/uploads/<?= $evento['imagem'] ?>" 
                                 alt="Imagem atual" style="max-width: 200px; margin-top: 10px; border-radius: 8px;">
                            <p><small>Imagem atual - faça upload de uma nova para substituir</small></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- O que está incluído -->
            <h3>O que está incluído</h3>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Lista do que está incluído</label>
                    <textarea name="inclui" class="form-textarea-admin" rows="4"
                              placeholder="Digite um item por linha&#10;Ex:&#10;Todas as refeições&#10;Material de apoio&#10;Certificado"><?php
                        if (!empty($evento['inclui'])) {
                            $inclui_array = json_decode($evento['inclui'], true);
                            echo htmlspecialchars(implode("\n", $inclui_array));
                        }
                    ?></textarea>
                </div>
            </div>
            
            <!-- Programação -->
            <h3>Programação</h3>
            <div id="programacao-container">
                <?php
                $programacao = [];
                if (!empty($evento['programacao'])) {
                    $programacao = json_decode($evento['programacao'], true) ?: [];
                }
                
                if (empty($programacao)) {
                    $programacao = [['horario' => '', 'titulo' => '', 'descricao' => '', 'palestrante' => '']];
                }
                
                foreach ($programacao as $index => $item):
                ?>
                <div class="programacao-item" data-index="<?= $index ?>">
                    <h4>Item da Programação <?= $index + 1 ?></h4>
                    <div class="form-row">
                        <div class="form-group-admin">
                            <label class="form-label-admin">Horário</label>
                            <input type="time" name="programacao_horario[]" class="form-input-admin" 
                                   value="<?= htmlspecialchars($item['horario'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group-admin">
                            <label class="form-label-admin">Título</label>
                            <input type="text" name="programacao_titulo[]" class="form-input-admin" 
                                   value="<?= htmlspecialchars($item['titulo'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group-admin">
                            <label class="form-label-admin">Descrição</label>
                            <textarea name="programacao_descricao[]" class="form-textarea-admin" rows="2"><?= htmlspecialchars($item['descricao'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group-admin">
                            <label class="form-label-admin">Palestrante</label>
                            <input type="text" name="programacao_palestrante[]" class="form-input-admin" 
                                   value="<?= htmlspecialchars($item['palestrante'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <?php if ($index > 0): ?>
                        <button type="button" onclick="removerProgramacao(this)" class="btn btn-outline" style="margin-top: 10px;">
                            Remover Item
                        </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" onclick="adicionarProgramacao()" class="btn btn-outline">
                Adicionar Item da Programação
            </button>
            
            <!-- Ações -->
            <div class="form-actions">
                <a href="<?= SITE_URL ?>/admin/eventos.php" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <?= $evento_id ? 'Atualizar Evento' : 'Criar Evento' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
    let programacaoIndex = <?= count($programacao) ?>;
    
    function adicionarProgramacao() {
        const container = document.getElementById('programacao-container');
        const div = document.createElement('div');
        div.className = 'programacao-item';
        div.dataset.index = programacaoIndex;
        
        div.innerHTML = `
            <h4>Item da Programação ${programacaoIndex + 1}</h4>
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Horário</label>
                    <input type="time" name="programacao_horario[]" class="form-input-admin">
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Título</label>
                    <input type="text" name="programacao_titulo[]" class="form-input-admin">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-admin">
                    <label class="form-label-admin">Descrição</label>
                    <textarea name="programacao_descricao[]" class="form-textarea-admin" rows="2"></textarea>
                </div>
                
                <div class="form-group-admin">
                    <label class="form-label-admin">Palestrante</label>
                    <input type="text" name="programacao_palestrante[]" class="form-input-admin">
                </div>
            </div>
            
            <button type="button" onclick="removerProgramacao(this)" class="btn btn-outline" style="margin-top: 10px;">
                Remover Item
            </button>
        `;
        
        container.appendChild(div);
        programacaoIndex++;
    }
    
    function removerProgramacao(button) {
        button.parentElement.remove();
    }
    </script>

<?php endif; ?>

<script>
// Confirmação de exclusão mais elegante
function confirmarExclusao(elemento, nome) {
    const confirmacao = confirm(
        `Tem certeza que deseja excluir o evento "${nome}"?\n\n` +
        `Esta ação não pode ser desfeita e irá remover:\n` +
        `• O evento e todas suas informações\n` +
        `• Todas as inscrições associadas\n` +
        `• Histórico de pagamentos relacionados\n\n` +
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