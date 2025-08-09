<?php
require_once 'includes/init.php';

// Verificar se sistema foi migrado
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

// Verificar se foi fornecido ID ou slug
if (isset($_GET['id']) && !empty($_GET['id'])) {
    // Buscar por ID (nova forma)
    $evento_id = (int) $_GET['id'];
    
    if ($evento_id <= 0) {
        exibir_erro_404();
    }
    
    if ($tabela_inscricoes_existe) {
        $evento = buscar_um("
            SELECT e.*, 
                   COUNT(i.id) as total_inscritos,
                   (e.limite_participantes - COUNT(i.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente', 'aprovada')
            WHERE e.id = ? AND e.status = 'ativo'
            GROUP BY e.id
        ", [$evento_id]);
    } else {
        $evento = buscar_um("
            SELECT e.*, 
                   COUNT(p.id) as total_inscritos,
                   (e.limite_participantes - COUNT(p.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
            WHERE e.id = ? AND e.status = 'ativo'
            GROUP BY e.id
        ", [$evento_id]);
    }
    
} elseif (isset($_GET['slug']) && !empty($_GET['slug'])) {
    // Buscar por slug (compatibilidade)
    $slug = sanitizar_entrada($_GET['slug']);
    
    if ($tabela_inscricoes_existe) {
        $evento = buscar_um("
            SELECT e.*, 
                   COUNT(i.id) as total_inscritos,
                   (e.limite_participantes - COUNT(i.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente', 'aprovada')
            WHERE e.slug = ? AND e.status = 'ativo'
            GROUP BY e.id
        ", [$slug]);
    } else {
        $evento = buscar_um("
            SELECT e.*, 
                   COUNT(p.id) as total_inscritos,
                   (e.limite_participantes - COUNT(p.id)) as vagas_restantes
            FROM eventos e
            LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
            WHERE e.slug = ? AND e.status = 'ativo'
            GROUP BY e.id
        ", [$slug]);
    }
    
} else {
    // Nem ID nem slug fornecidos
    exibir_erro_404();
}

if (!$evento) {
    exibir_erro_404();
}

// Decodificar JSON fields
$programacao = $evento['programacao'] ? json_decode($evento['programacao'], true) : [];
$inclui = $evento['inclui'] ? json_decode($evento['inclui'], true) : [];

// Verificar se evento já passou
$evento_passou = strtotime($evento['data_inicio']) < strtotime('today');
$evento_esgotado = $evento['vagas_restantes'] <= 0;

obter_cabecalho($evento['nome'] . ' - Vinde', 'evento');
?>

<main class="evento-main">
    <!-- Breadcrumb -->
    <nav class="evento-breadcrumb">
        <div class="container">
            <div class="breadcrumb-content">
                <a href="<?= SITE_URL ?>" class="breadcrumb-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                    </svg>
                    Eventos
                </a>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current"><?= htmlspecialchars($evento['nome']) ?></span>
            </div>
        </div>
    </nav>

    <!-- Hero Elegante do Evento -->
    <div class="sympla-page evento-page">
        <div class="container">
            <!-- Card Principal do Evento -->
            <div class="main-card evento-card">
                <!-- Header do Evento -->
                <div class="event-header">
                    <div class="event-image">
                        <?php if ($evento['imagem']): ?>
                            <img src="<?= SITE_URL ?>/uploads/<?= $evento['imagem'] ?>" alt="<?= htmlspecialchars($evento['nome']) ?>">
                        <?php else: ?>
                            <div class="event-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"/>
                                </svg>
                            </div>
                                    <?php endif; ?>
                            </div>
                    <div class="event-info">
                        <!-- Badges -->
                        <div class="event-badges">
                            <span class="event-badge badge-<?= $evento['tipo'] ?>"><?= ucfirst($evento['tipo']) ?></span>
                            <?php if ($evento_passou): ?>
                                <span class="event-badge badge-finished">✓ Finalizado</span>
                            <?php elseif ($evento_esgotado): ?>
                                <span class="event-badge badge-sold-out">🔥 Esgotado</span>
                            <?php elseif ($evento['vagas_restantes'] <= 5): ?>
                                <span class="event-badge badge-limited">⚡ Últimas vagas</span>
                            <?php endif; ?>
                        </div>
                        
                        <h1 class="event-title"><?= htmlspecialchars($evento['nome']) ?></h1>
                        
                        <?php if ($evento['descricao']): ?>
                            <p class="event-description"><?= htmlspecialchars($evento['descricao']) ?></p>
                        <?php endif; ?>
                        
                        <div class="event-details">
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"/>
                                </svg>
                                <span><?= formatar_data($evento['data_inicio']) ?></span>
                                <?php if ($evento['data_fim'] && $evento['data_fim'] !== $evento['data_inicio']): ?>
                                    <span> - <?= formatar_data($evento['data_fim']) ?></span>
                                <?php endif; ?>
                                <?php if ($evento['horario_inicio']): ?>
                                    <span>às <?= date('H:i', strtotime($evento['horario_inicio'])) ?></span>
                                    <?php if ($evento['horario_fim']): ?>
                                        <span> - <?= date('H:i', strtotime($evento['horario_fim'])) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <span><?= htmlspecialchars($evento['local']) ?> - <?= htmlspecialchars($evento['cidade']) ?>, <?= $evento['estado'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção de Inscrição -->
                <div class="inscription-section">
                    <div class="inscription-info">
                        <div class="price-section">
                            <?php if ($evento['valor'] > 0): ?>
                                <div class="price-main">
                                    <span class="price-currency">R$</span>
                                    <span class="price-value"><?= number_format($evento['valor'], 0, ',', '.') ?></span>
                                    <?php if ($evento['valor'] - floor($evento['valor']) > 0): ?>
                                        <span class="price-decimal">,<?= sprintf('%02d', ($evento['valor'] - floor($evento['valor'])) * 100) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="price-label">por pessoa</span>
                            <?php else: ?>
                                <div class="price-free">
                                    <span class="free-label">Evento Gratuito</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="availability-info">
                            <div class="availability-text">
                                <span class="available-number"><?= $evento['vagas_restantes'] ?></span>
                                <span class="available-label">vagas disponíveis</span>
                            </div>
                            <div class="total-spots"><?= $evento['total_inscritos'] ?>/<?= $evento['limite_participantes'] ?></div>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= min(100, ($evento['total_inscritos'] / $evento['limite_participantes']) * 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="inscription-action">
                        <?php if ($evento_passou): ?>
                            <button class="btn-inscription disabled" disabled>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                Evento Finalizado
                            </button>
                        <?php elseif ($evento_esgotado): ?>
                            <button class="btn-inscription disabled" disabled>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L16.31 7.1C17.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>
                                </svg>
                                Esgotado
                            </button>
                        <?php else: ?>
                            <a href="<?= SITE_URL ?>/inscricao.php?evento_id=<?= $evento['id'] ?>" class="btn-inscription primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                                </svg>
                                <?= $evento['valor'] > 0 ? 'Garantir Minha Vaga' : 'Inscrever-se Gratuitamente' ?>
                            </a>
                        <?php endif; ?>
                        
                        <div class="security-info">
                            <div class="security-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                                </svg>
                                <span>Confirmação imediata</span>
                            </div>
                            <div class="security-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/>
                                </svg>
                                <span>Dados protegidos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <section class="evento-conteudo">
        <div class="container">
            <div class="evento-layout-compacto">
                <!-- Coluna Principal -->
                    <!-- Descrição Completa -->
                    <?php if ($evento['descricao_completa']): ?>
                    <div class="content-card">
                        <h2>Sobre o Evento</h2>
                        <div class="text-content">
                            <?= nl2br(htmlspecialchars($evento['descricao_completa'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Programação -->
                    <?php if (!empty($programacao)): ?>
                    <div class="content-card">
                        <div class="section-header">
                        <h2>Programação</h2>
                            <button class="toggle-btn" onclick="toggleProgram()">
                                <span>Ver programação completa</span>
                                <svg class="toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7 10l5 5 5-5z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="program-content" id="program-content">
                            <div class="program-timeline">
                                <?php foreach ($programacao as $index => $item): ?>
                                    <div class="program-item">
                                    <?php if (isset($item['horario'])): ?>
                                            <div class="program-time">
                                                <span class="time-badge"><?= htmlspecialchars($item['horario']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="program-content-item">
                                            <div class="program-info">
                                                <h3 class="program-title"><?= htmlspecialchars($item['titulo'] ?? '') ?></h3>
                                                <?php if (isset($item['descricao'])): ?>
                                                    <p class="program-description"><?= htmlspecialchars($item['descricao']) ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($item['palestrante'])): ?>
                                                    <div class="program-speaker">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                        </svg>
                                                        <span><?= htmlspecialchars($item['palestrante']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($index < count($programacao) - 1): ?>
                                            <div class="program-connector"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- O que está incluído -->
                    <?php if (!empty($inclui)): ?>
                    <div class="content-card">
                        <h2>O que está incluído</h2>
                        <ul class="included-list">
                            <?php foreach ($inclui as $item): ?>
                                <li class="included-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="check-icon">
                                        <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                                    </svg>
                                    <span><?= htmlspecialchars($item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Endereço -->
                    <?php if ($evento['endereco']): ?>
                    <div class="content-card">
                        <h2>Como chegar</h2>
                        <div class="location-info">
                            <div class="location-details">
                                <h3 class="location-name"><?= htmlspecialchars($evento['local']) ?></h3>
                                <p class="location-address"><?= htmlspecialchars($evento['endereco']) ?></p>
                                <p class="location-city"><?= htmlspecialchars($evento['cidade']) ?>, <?= $evento['estado'] ?></p>
                            </div>
                            
                            <a href="https://maps.google.com/?q=<?= urlencode($evento['endereco'] . ', ' . $evento['cidade'] . ', ' . $evento['estado']) ?>" 
                               target="_blank" 
                               class="btn-map">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                Ver no Google Maps
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Eventos Relacionados -->
    <section class="eventos-relacionados">
        <div class="container">
            <h2>Outros eventos que podem interessar</h2>
            
            <?php
            $eventos_relacionados = buscar_todos("
                SELECT e.*, 
                       COUNT(i.id) as total_inscritos,
                       (e.limite_participantes - COUNT(i.id)) as vagas_restantes
                FROM eventos e
                LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente','aprovada')
                WHERE e.status = 'ativo' 
                AND e.data_inicio >= CURDATE()
                AND e.id != ?
                AND (e.cidade = ? OR e.tipo = ?)
                GROUP BY e.id
                ORDER BY e.data_inicio ASC
                LIMIT 3
            ", [$evento['id'], $evento['cidade'], $evento['tipo']]);
            ?>
            
            <?php if (!empty($eventos_relacionados)): ?>
                <div class="eventos-grid-mini">
                    <?php foreach ($eventos_relacionados as $evento_rel): ?>
                        <article class="evento-card-mini">
                            <div class="card-imagem-mini">
                                <?php if ($evento_rel['imagem']): ?>
                                    <img src="<?= SITE_URL ?>/uploads/<?= $evento_rel['imagem'] ?>" 
                                         alt="<?= htmlspecialchars($evento_rel['nome']) ?>">
                                <?php else: ?>
                                    <div class="imagem-placeholder-mini">
                                        <i class="icon-calendar"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-content-mini">
                                <h3><a href="<?= SITE_URL ?>/evento/<?= $evento_rel['id'] ?>"><?= htmlspecialchars($evento_rel['nome']) ?></a></h3>
                                <p class="data"><?= formatar_data($evento_rel['data_inicio']) ?></p>
                                <p class="local"><?= htmlspecialchars($evento_rel['cidade']) ?></p>
                                <p class="preco"><?= $evento_rel['valor'] > 0 ? formatar_dinheiro($evento_rel['valor']) : 'Gratuito' ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center">Nenhum evento relacionado encontrado no momento.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
function copiarLink() {
    const url = window.location.href;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('Link copiado com sucesso!');
        });
    } else {
        // Fallback para navegadores mais antigos
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Link copiado com sucesso!');
    }
}

// Toggle da programação
function toggleProgram() {
    const content = document.getElementById('program-content');
    const icon = document.querySelector('.toggle-icon');
    const btn = document.querySelector('.toggle-btn span');
    
    if (content.style.display === 'none' || !content.style.display) {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
        btn.textContent = 'Ocultar programação';
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        btn.textContent = 'Ver programação completa';
    }
}

// Scroll suave para seções
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Inicializar programação como oculta
    const programContent = document.getElementById('program-content');
    if (programContent) {
        programContent.style.display = 'none';
    }
});
</script>

<?php
obter_rodape();
?> 