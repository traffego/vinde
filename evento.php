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

    <!-- Hero Premium do Evento -->
    <section class="evento-hero-premium">
        <div class="hero-background">
            <?php if ($evento['imagem']): ?>
                <img src="<?= SITE_URL ?>/uploads/<?= $evento['imagem'] ?>" 
                     alt="<?= htmlspecialchars($evento['nome']) ?>" class="hero-bg-image">
            <?php endif; ?>
            <div class="hero-overlay"></div>
        </div>
        
        <div class="container">
            <div class="hero-content">
                <div class="hero-badges">
                    <span class="hero-badge hero-badge-<?= $evento['tipo'] ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <?= ucfirst($evento['tipo']) ?>
                    </span>
                    
                    <?php if ($evento_passou): ?>
                        <span class="hero-badge hero-badge-finished">✓ Finalizado</span>
                    <?php elseif ($evento_esgotado): ?>
                        <span class="hero-badge hero-badge-sold-out">🔥 Esgotado</span>
                    <?php elseif ($evento['vagas_restantes'] <= 5): ?>
                        <span class="hero-badge hero-badge-limited">⚡ Últimas vagas</span>
                    <?php endif; ?>
                </div>
                
                <h1 class="hero-title"><?= htmlspecialchars($evento['nome']) ?></h1>
                
                <p class="hero-description"><?= htmlspecialchars($evento['descricao']) ?></p>
                
                <div class="hero-quick-info">
                    <div class="quick-info-grid">
                        <div class="quick-info-item">
                            <div class="quick-info-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 11H7v6h2v-6zm4 0h-2v6h2v-6zm4 0h-2v6h2v-6zm2-7h-3V2h-2v2H8V2H6v2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H3V9h14v11z"/>
                                </svg>
                            </div>
                            <div class="quick-info-content">
                                <span class="quick-info-label">Data</span>
                                <span class="quick-info-value">
                                    <?= formatar_data($evento['data_inicio']) ?>
                                    <?php if ($evento['data_fim'] && $evento['data_fim'] !== $evento['data_inicio']): ?>
                                        - <?= formatar_data($evento['data_fim']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($evento['horario_inicio']): ?>
                        <div class="quick-info-item">
                            <div class="quick-info-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                    <path d="m12.5 7-1 0 0 6 4.75 2.85.75-1.23-4-2.37z"/>
                                </svg>
                            </div>
                            <div class="quick-info-content">
                                <span class="quick-info-label">Horário</span>
                                <span class="quick-info-value">
                                    <?= date('H:i', strtotime($evento['horario_inicio'])) ?>
                                    <?php if ($evento['horario_fim']): ?>
                                        - <?= date('H:i', strtotime($evento['horario_fim'])) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="quick-info-item">
                            <div class="quick-info-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                            </div>
                            <div class="quick-info-content">
                                <span class="quick-info-label">Local</span>
                                <span class="quick-info-value"><?= htmlspecialchars($evento['local']) ?></span>
                            </div>
                        </div>
                        
                        <div class="quick-info-item">
                            <div class="quick-info-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                                </svg>
                            </div>
                            <div class="quick-info-content">
                                <span class="quick-info-label">Preço</span>
                                <span class="quick-info-value quick-info-price">
                                    <?= $evento['valor'] > 0 ? formatar_dinheiro($evento['valor']) : 'Gratuito' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Conteúdo Principal -->
    <section class="evento-conteudo">
        <div class="container">
            <div class="evento-layout">
                <!-- Coluna Principal -->
                <div class="evento-principal">
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
                
                <!-- Sidebar Premium -->
                <aside class="evento-sidebar-premium">
                    <!-- Card de Inscrição Premium -->
                    <div class="inscricao-card-premium">
                        <div class="card-header-premium">
                            <div class="price-display">
                                <?php if ($evento['valor'] > 0): ?>
                                    <div class="price-main">
                                        <span class="price-currency">R$</span>
                                        <span class="price-value"><?= number_format($evento['valor'], 0, ',', '.') ?></span>
                                        <span class="price-decimal"><?= $evento['valor'] - floor($evento['valor']) > 0 ? ',' . sprintf('%02d', ($evento['valor'] - floor($evento['valor'])) * 100) : '' ?></span>
                                    </div>
                                    <span class="price-label">por pessoa</span>
                                <?php else: ?>
                                    <div class="price-free">
                                        <span class="free-badge">Gratuito</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body-premium">
                            <!-- Progresso de Vagas -->
                            <div class="availability-section">
                                <div class="availability-header">
                                    <div class="availability-text">
                                        <span class="available-count"><?= $evento['vagas_restantes'] ?></span>
                                        <span class="availability-label">vagas disponíveis</span>
                                    </div>
                                    <div class="total-spots">
                                        <span><?= $evento['total_inscritos'] ?>/<?= $evento['limite_participantes'] ?></span>
                                    </div>
                                </div>
                                <div class="progress-bar-premium">
                                    <div class="progress-fill" 
                                         style="width: <?= min(100, ($evento['total_inscritos'] / $evento['limite_participantes']) * 100) ?>%">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Botão de Inscrição Premium -->
                            <div class="cta-section">
                                <?php if ($evento_passou): ?>
                                    <button class="btn-premium btn-disabled" disabled>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        Evento Finalizado
                                    </button>
                                <?php elseif ($evento_esgotado): ?>
                                    <button class="btn-premium btn-sold-out" disabled>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L16.31 7.1C17.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>
                                        </svg>
                                        Esgotado
                                    </button>
                                <?php else: ?>
                                    <a href="<?= SITE_URL ?>/inscricao.php?evento_id=<?= $evento['id'] ?>" 
                                       class="btn-premium btn-primary-premium">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                                        </svg>
                                        <?= $evento['valor'] > 0 ? 'Garantir Minha Vaga' : 'Inscrever-se Gratuitamente' ?>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="security-badges">
                                    <div class="security-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                                        </svg>
                                        <span>Confirmação imediata</span>
                                    </div>
                                    <div class="security-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/>
                                        </svg>
                                        <span>Dados protegidos</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações Adicionais -->
                    <div class="info-cards-premium">
                        <div class="info-card-premium">
                            <h4>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                Localização
                            </h4>
                            <p><?= htmlspecialchars($evento['local']) ?></p>
                            <p class="text-muted"><?= htmlspecialchars($evento['cidade']) ?>, <?= $evento['estado'] ?></p>
                        </div>
                        
                        <!-- Compartilhar -->
                        <div class="info-card-premium">
                            <h4>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.50-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
                                </svg>
                                Compartilhar
                            </h4>
                            <div class="share-buttons-premium">
                                <a href="https://wa.me/?text=<?= urlencode('🎉 Confira este evento incrível: ' . $evento['nome'] . ' 📅 ' . formatar_data($evento['data_inicio']) . ' 📍 ' . $evento['local'] . ' 🔗 ' . SITE_URL . '/evento/' . $evento['id']) ?>" 
                                   target="_blank" 
                                   class="share-btn whatsapp-btn">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893A11.821 11.821 0 0020.89 3.488"/>
                                    </svg>
                                    WhatsApp
                                </a>
                                <button onclick="copiarLink()" class="share-btn copy-btn">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                                    </svg>
                                    Copiar Link
                                </button>
                            </div>
                        </div>
                        
                        <!-- Suporte -->
                        <div class="info-card-premium support-card">
                            <h4>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                                Precisa de ajuda?
                            </h4>
                            <p class="support-text">Tire suas dúvidas conosco</p>
                            <a href="https://wa.me/<?= WHATSAPP_CONTATO ?>?text=<?= urlencode('Olá! Tenho dúvidas sobre o evento: ' . $evento['nome']) ?>" 
                               target="_blank" 
                               class="support-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893A11.821 11.821 0 0020.89 3.488"/>
                                </svg>
                                Falar Conosco
                            </a>
                        </div>
                    </div>
                </aside>
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