<?php
require_once 'includes/init.php';
require_once 'includes/config_social.php';

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

// URL atual do evento - usar HTTP_HOST para garantir domínio correto
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$dominio = $_SERVER['HTTP_HOST'];
$evento_url = $protocolo . '://' . $dominio . '/evento.php?id=' . $evento['id'];

// Imagem do evento - usar exatamente a mesma lógica do HTML
$evento_imagem_path = '';

if ($evento['imagem']) {
    // Usar a mesma estrutura que está sendo usada no HTML da página
    // SITE_URL . '/uploads/' . $evento['imagem']
    $evento_imagem_path = $protocolo . '://' . $dominio . '/uploads/' . $evento['imagem'];
    
    // Verificar se arquivo existe localmente 
    $caminho_local = __DIR__ . '/uploads/' . $evento['imagem'];
    
    if (!file_exists($caminho_local)) {
        // Se não existir localmente, resetar para buscar fallback
        $evento_imagem_path = '';
    }
}

// Fallbacks se não houver imagem válida
if (empty($evento_imagem_path)) {
    $fallbacks = [
        ['path' => '/assets/images/logo.png', 'local' => __DIR__ . '/assets/images/logo.png'],
        ['path' => '/assets/img/logo.png', 'local' => __DIR__ . '/assets/img/logo.png'],
        ['path' => '/assets/images/default-event.jpg', 'local' => __DIR__ . '/assets/images/default-event.jpg'],
        ['path' => '/assets/img/default-event.jpg', 'local' => __DIR__ . '/assets/img/default-event.jpg']
    ];
    
    foreach ($fallbacks as $fallback) {
        if (file_exists($fallback['local'])) {
            $evento_imagem_path = $protocolo . '://' . $dominio . $fallback['path'];
            break;
        }
    }
}

// Fallback final para placeholder se nada foi encontrado
if (empty($evento_imagem_path)) {
    $evento_imagem_path = 'https://via.placeholder.com/1200x630/1e40af/ffffff?text=' . urlencode($evento['nome']);
}

// Descrição otimizada para Open Graph
$evento_descricao = $evento['descricao'] ? strip_tags($evento['descricao']) : $evento['nome'];
$evento_descricao = substr($evento_descricao, 0, 160);
if (strlen(strip_tags($evento['descricao'])) > 160) {
    $evento_descricao .= '...';
}

// Meta tags específicas para Open Graph - Seguindo regras rigorosas
$meta_tags = [
    // Facebook App ID (obrigatório para algumas funcionalidades)
    'fb:app_id' => FACEBOOK_APP_ID,
    
    // Open Graph básico (obrigatório)
    'og:title' => htmlspecialchars($evento['nome']),
    'og:type' => 'website', // Mudança: 'event' pode não ser reconhecido por todas as plataformas
    'og:image' => $evento_imagem_path,
    'og:url' => $evento_url,
    'og:description' => htmlspecialchars($evento_descricao),
    
    // Meta tags adicionais para melhor compatibilidade
    'og:site_name' => SITE_NAME,
    'og:locale' => SITE_LOCALE,
    
    // Especificações da imagem (IMPORTANTES para funcionamento)
    'og:image:url' => $evento_imagem_path,
    'og:image:secure_url' => str_replace('http://', 'https://', $evento_imagem_path),
    'og:image:type' => 'image/jpeg', // Assumindo JPEG - será corrigido dinamicamente
    'og:image:width' => DEFAULT_OG_IMAGE_WIDTH,
    'og:image:height' => DEFAULT_OG_IMAGE_HEIGHT,
    'og:image:alt' => htmlspecialchars($evento['nome']),
    
    // Meta tags para evento específico
    'event:start_time' => date('c', strtotime($evento['data_inicio'] . ' ' . ($evento['horario_inicio'] ?: '00:00:00'))),
    'event:location' => htmlspecialchars($evento['local'] . ' - ' . $evento['cidade'] . ', ' . $evento['estado']),
    
    // Twitter Cards (essencial para WhatsApp e Twitter)
    'twitter:card' => 'summary_large_image',
    'twitter:site' => TWITTER_HANDLE,
    'twitter:title' => htmlspecialchars($evento['nome']),
    'twitter:description' => htmlspecialchars($evento_descricao),
    'twitter:image' => $evento_imagem_path,
    'twitter:image:alt' => htmlspecialchars($evento['nome']),
    
    // Meta tags extras para melhor SEO
    'description' => htmlspecialchars($evento_descricao),
    'keywords' => 'evento católico, ' . strtolower($evento['cidade']) . ', ' . htmlspecialchars($evento['nome'])
];

// Detectar tipo MIME da imagem para meta tag correta
if ($evento['imagem']) {
    $extensao = strtolower(pathinfo($evento['imagem'], PATHINFO_EXTENSION));
    switch ($extensao) {
        case 'jpg':
        case 'jpeg':
            $meta_tags['og:image:type'] = 'image/jpeg';
            break;
        case 'png':
            $meta_tags['og:image:type'] = 'image/png';
            break;
        case 'webp':
            $meta_tags['og:image:type'] = 'image/webp';
            break;
        default:
            $meta_tags['og:image:type'] = 'image/jpeg';
    }
}

obter_cabecalho($evento['nome'] . ' - Vinde', 'evento', $meta_tags);

// DEBUG: Mostrar meta tags se parâmetro debug=1 estiver presente
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<!-- DEBUG: Meta tags geradas para Open Graph -->";
    echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc; font-family: monospace; font-size: 12px;'>";
    echo "<h3>🔍 Debug Open Graph (vinde.traffego.agency):</h3>";
    
    echo "<h4>📋 Informações do evento:</h4>";
    echo "<ul>";
    echo "<li><strong>Campo imagem DB:</strong> " . htmlspecialchars($evento['imagem'] ?: 'VAZIO') . "</li>";
    echo "<li><strong>Caminho local:</strong> " . htmlspecialchars(__DIR__ . '/uploads/' . $evento['imagem']) . "</li>";
    echo "<li><strong>Arquivo existe:</strong> " . (file_exists(__DIR__ . '/uploads/' . $evento['imagem']) ? 'SIM' : 'NÃO') . "</li>";
    echo "<li><strong>URL gerada:</strong> {$evento_imagem_path}</li>";
    echo "<li><strong>Domínio detectado:</strong> {$dominio}</li>";
    echo "<li><strong>Protocolo:</strong> {$protocolo}</li>";
    echo "</ul>";
    
    echo "<h4>🖼️ Teste da imagem:</h4>";
    echo "<p><a href='{$evento_imagem_path}' target='_blank' style='background: #007cba; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Abrir imagem em nova aba</a></p>";
    echo "<img src='{$evento_imagem_path}' style='max-width: 200px; border: 1px solid #ccc; margin: 10px 0;' onerror='this.style.display=\"none\"; this.nextSibling.style.display=\"block\";'>";
    echo "<p style='display:none; color: red;'>❌ Erro ao carregar imagem</p>";
    
    echo "<h4>📝 Meta tags geradas:</h4>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;'>";
    foreach ($meta_tags as $property => $content) {
        if (strpos($property, 'twitter:') === 0) {
            echo htmlspecialchars("<meta name='{$property}' content='{$content}'>") . "\n";
        } else {
            echo htmlspecialchars("<meta property='{$property}' content='{$content}'>") . "\n";
        }
    }
    echo "</pre>";
    
    echo "<h4>🔗 Ferramentas de validação:</h4>";
    echo "<ul>";
    echo "<li><a href='https://developers.facebook.com/tools/debug/?q=" . urlencode($evento_url) . "' target='_blank'>Facebook Debugger</a></li>";
    echo "<li><a href='https://www.linkedin.com/post-inspector/inspect/" . urlencode($evento_url) . "' target='_blank'>LinkedIn Inspector</a></li>";
    echo "<li><a href='https://cards-dev.twitter.com/validator' target='_blank'>Twitter Card Validator</a></li>";
    echo "</ul>";
    
    echo "<p><small>Para remover este debug, retire o parâmetro ?debug=1 da URL</small></p>";
    echo "</div>";
}
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
                            <img src="<?= SITE_URL ?>/uploads/<?= $evento['imagem'] ?>" alt="<?= htmlspecialchars($evento['nome']) ?>" loading="eager">
                            <div class="image-shine"></div>
                        <?php else: ?>
                            <div class="event-placeholder">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="currentColor">
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
                                <!-- Debug: <?= "Valor do banco: " . var_export($evento['valor'], true) ?> -->
                                <div class="price-main">
                                    <span class="price-currency">R$</span>
                                    <span class="price-value"><?= formatar_moeda($evento['valor']) ?></span>
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
                    
                    <!-- Botões de Compartilhamento -->
                    <div class="share-section">
                        <h3 class="share-title">Compartilhar evento</h3>
                        <div class="share-buttons">
                            <button onclick="shareWhatsApp()" class="btn-share whatsapp">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.893 3.488"/>
                                </svg>
                                WhatsApp
                            </button>
                            
                            <button onclick="copyEventLink()" class="btn-share copy">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                                </svg>
                                Copiar Link
                            </button>
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

// Funções de compartilhamento
function shareWhatsApp() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('Olha que evento incrível! <?= htmlspecialchars($evento['nome']) ?> - <?= formatar_data($evento['data_inicio']) ?>');
    const whatsappUrl = `https://wa.me/?text=${text}%20${url}`;
    window.open(whatsappUrl, '_blank');
}

function copyEventLink() {
    const url = window.location.href;
    
    if (navigator.clipboard && window.isSecureContext) {
        // Usar a API moderna
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link copiado com sucesso! 📋', 'success');
        }).catch(() => {
            fallbackCopyLink(url);
        });
    } else {
        fallbackCopyLink(url);
    }
}

function fallbackCopyLink(url) {
    // Fallback para navegadores mais antigos
    const textArea = document.createElement('textarea');
    textArea.value = url;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast('Link copiado com sucesso! 📋', 'success');
    } catch (err) {
        showToast('Erro ao copiar link. Copie manualmente: ' + url, 'error');
    }
    
    document.body.removeChild(textArea);
}

function showToast(message, type = 'info') {
    // Remover toasts existentes
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    // Criar novo toast
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    // Estilos inline
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d1fae5' : '#fef2f2'};
        color: ${type === 'success' ? '#065f46' : '#dc2626'};
        border: 1px solid ${type === 'success' ? '#86efac' : '#fca5a5'};
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        max-width: 350px;
        font-size: 14px;
    `;
    
    // Adicionar ao DOM
    document.body.appendChild(toast);
    
    // Auto-remover após 3 segundos
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 3000);
}

// Adicionar CSS das animações do toast
const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .toast-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }
    .toast-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        opacity: 0.7;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .toast-close:hover {
        opacity: 1;
    }
`;
document.head.appendChild(toastStyle);
</script>

<?php
obter_rodape();
?> 