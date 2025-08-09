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

    <!-- Hero Compacto do Evento -->
    <section class="evento-hero-compacto">
        <div class="container">
            <div class="hero-content-compacto">
                <!-- Linha 1: Badges e Título -->
                <div class="hero-header">
                    <div class="hero-badges">
                        <span class="hero-badge hero-badge-<?= $evento['tipo'] ?>"><?= ucfirst($evento['tipo']) ?></span>
                        <?php if ($evento_passou): ?>
                            <span class="hero-badge hero-badge-finished">✓ Finalizado</span>
                        <?php elseif ($evento_esgotado): ?>
                            <span class="hero-badge hero-badge-sold-out">🔥 Esgotado</span>
                        <?php elseif ($evento['vagas_restantes'] <= 5): ?>
                            <span class="hero-badge hero-badge-limited">⚡ Últimas vagas</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="hero-title-compacto"><?= htmlspecialchars($evento['nome']) ?></h1>
                </div>
                
                <!-- Linha 2: Informações Essenciais -->
                <div class="info-essencial">
                    <div class="info-grid-compacto">
                        <div class="info-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 11H7v6h2v-6zm4 0h-2v6h2v-6zm4 0h-2v6h2v-6zm2-7h-3V2h-2v2H8V2H6v2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H3V9h14v11z"/>
                            </svg>
                            <span><?= formatar_data($evento['data_inicio']) ?><?php if ($evento['data_fim'] && $evento['data_fim'] !== $evento['data_inicio']): ?> - <?= formatar_data($evento['data_fim']) ?><?php endif; ?></span>
                        </div>
                        
                        <?php if ($evento['horario_inicio']): ?>
                        <div class="info-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                <path d="m12.5 7-1 0 0 6 4.75 2.85.75-1.23-4-2.37z"/>
                            </svg>
                            <span><?= date('H:i', strtotime($evento['horario_inicio'])) ?><?php if ($evento['horario_fim']): ?> - <?= date('H:i', strtotime($evento['horario_fim'])) ?><?php endif; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <span><?= htmlspecialchars($evento['local']) ?></span>
                        </div>
                        
                        <div class="info-item preco-destaque">
                            <span class="preco-valor"><?= $evento['valor'] > 0 ? formatar_dinheiro($evento['valor']) : 'Gratuito' ?></span>
                        </div>
                        
                        <!-- Botão de Inscrição Inline -->
                        <div class="cta-inline">
                            <?php if ($evento_passou): ?>
                                <button class="btn-compacto btn-disabled" disabled>Evento Finalizado</button>
                            <?php elseif ($evento_esgotado): ?>
                                <button class="btn-compacto btn-esgotado" disabled>Esgotado</button>
                            <?php else: ?>
                                <a href="<?= SITE_URL ?>/inscricao.php?evento_id=<?= $evento['id'] ?>" class="btn-compacto btn-primary">
                                    Garantir Vaga
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Contador de Vagas -->
                    <div class="contador-vagas">
                        <span class="vagas-restantes"><?= $evento['vagas_restantes'] ?></span> vagas disponíveis
                        <div class="progress-mini">
                            <div class="progress-fill-mini" style="width: <?= min(100, ($evento['total_inscritos'] / $evento['limite_participantes']) * 100) ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Linha 3: Descrição (opcional, mais discreta) -->
                <?php if ($evento['descricao']): ?>
                <p class="hero-description-mini"><?= htmlspecialchars($evento['descricao']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Conteúdo Principal -->
    <section class="evento-conteudo">
        <div class="container">
            <div class="evento-layout-compacto">
                <!-- Coluna Principal -->
                    <!-- Descrição Completa -->
                    <?php if ($evento['descricao_completa']): ?>
                    <div class="evento-secao">
                        <h2>Sobre o Evento</h2>
                        <div class="texto-formatado">
                            <?= nl2br(htmlspecialchars($evento['descricao_completa'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Programação -->
                    <?php if (!empty($programacao)): ?>
                    <div class="evento-secao">
                        <h2>Programação</h2>
                        <div class="programacao">
                            <?php foreach ($programacao as $item): ?>
                                <div class="programacao-item">
                                    <?php if (isset($item['horario'])): ?>
                                        <div class="programacao-horario">
                                            <span><?= htmlspecialchars($item['horario']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="programacao-conteudo">
                                        <h3><?= htmlspecialchars($item['titulo'] ?? '') ?></h3>
                                        <?php if (isset($item['descricao'])): ?>
                                            <p><?= htmlspecialchars($item['descricao']) ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($item['palestrante'])): ?>
                                            <small class="palestrante">
                                                <i class="icon-user"></i>
                                                <?= htmlspecialchars($item['palestrante']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- O que está incluído -->
                    <?php if (!empty($inclui)): ?>
                    <div class="evento-secao">
                        <h2>O que está incluído</h2>
                        <ul class="lista-incluso">
                            <?php foreach ($inclui as $item): ?>
                                <li>
                                    <i class="icon-check"></i>
                                    <?= htmlspecialchars($item) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Endereço -->
                    <?php if ($evento['endereco']): ?>
                    <div class="evento-secao">
                        <h2>Como chegar</h2>
                        <div class="endereco">
                            <p><strong><?= htmlspecialchars($evento['local']) ?></strong></p>
                            <p><?= htmlspecialchars($evento['endereco']) ?></p>
                            <p><?= htmlspecialchars($evento['cidade']) ?>, <?= $evento['estado'] ?></p>
                            
                            <a href="https://maps.google.com/?q=<?= urlencode($evento['endereco'] . ', ' . $evento['cidade'] . ', ' . $evento['estado']) ?>" 
                               target="_blank" 
                               class="btn-mapa">
                                <i class="icon-map"></i>
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
});
</script>

<?php
obter_rodape();
?> 