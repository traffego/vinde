<?php
require_once 'includes/init.php';

// Buscar eventos ativos
// Verificar se sistema foi migrado
$tabela_inscricoes_existe = false;
try {
    $teste_tabela = buscar_um("SHOW TABLES LIKE 'inscricoes'");
    $tabela_inscricoes_existe = $teste_tabela !== false;
} catch (Exception $e) {
    $tabela_inscricoes_existe = false;
}

if ($tabela_inscricoes_existe) {
    // Sistema novo - usar tabela inscricoes
    $eventos = buscar_todos("
        SELECT e.*, 
               COUNT(i.id) as total_inscritos,
               (e.limite_participantes - COUNT(i.id)) as vagas_restantes
        FROM eventos e
        LEFT JOIN inscricoes i ON e.id = i.evento_id AND i.status IN ('pendente', 'aprovada')
        WHERE e.status = 'ativo' 
        AND e.data_inicio >= CURDATE()
        GROUP BY e.id
        ORDER BY e.data_inicio ASC
    ");
} else {
    // Sistema antigo - usar tabela participantes
    $eventos = buscar_todos("
        SELECT e.*, 
               COUNT(p.id) as total_inscritos,
               (e.limite_participantes - COUNT(p.id)) as vagas_restantes
        FROM eventos e
        LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
        WHERE e.status = 'ativo' 
        AND e.data_inicio >= CURDATE()
        GROUP BY e.id
        ORDER BY e.data_inicio ASC
    ");
}

obter_cabecalho('Vinde - Eventos Católicos', 'home');
?>

<main>
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Vinde e Participai</h1>
                <p>Descubra e participe dos melhores eventos católicos. Unidos pela fé, crescemos em comunidade.</p>
                <div class="hero-stats">
                    <div class="stat">
                        <span class="stat-number"><?= count($eventos) ?></span>
                        <span class="stat-label">Eventos Disponíveis</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number"><?= array_sum(array_column($eventos, 'total_inscritos')) ?></span>
                        <span class="stat-label">Participantes Inscritos</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filtros -->
    <section class="filtros">
        <div class="container">
            <div class="filtros-content">
                <h2>Encontre o evento ideal</h2>
                <div class="filtros-form">
                    <input type="text" id="busca-evento" placeholder="Buscar por nome do evento..." class="input-busca">
                    <select id="filtro-cidade" class="select-filtro">
                        <option value="">Todas as cidades</option>
                        <?php
                        $cidades = buscar_todos("SELECT DISTINCT cidade FROM eventos WHERE status = 'ativo' ORDER BY cidade");
                        foreach ($cidades as $cidade) {
                            echo "<option value='{$cidade['cidade']}'>{$cidade['cidade']}</option>";
                        }
                        ?>
                    </select>
                    <select id="filtro-tipo" class="select-filtro">
                        <option value="">Todos os tipos</option>
                        <option value="presencial">Presencial</option>
                        <option value="online">Online</option>
                        <option value="hibrido">Híbrido</option>
                    </select>
                    <button id="limpar-filtros" class="btn-limpar">Limpar Filtros</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Lista de Eventos -->
    <section class="eventos">
        <div class="container">
            <?php if (empty($eventos)): ?>
                <div class="eventos-vazio">
                    <div class="vazio-icon">📅</div>
                    <h3>Nenhum evento disponível</h3>
                    <p>Não há eventos programados no momento. Volte em breve para conferir nossas novidades!</p>
                </div>
            <?php else: ?>
                <div class="eventos-grid" id="eventos-lista">
                    <?php foreach ($eventos as $evento): ?>
                        <article class="evento-card" 
                                 data-nome="<?= strtolower($evento['nome']) ?>"
                                 data-cidade="<?= $evento['cidade'] ?>"
                                 data-tipo="<?= $evento['tipo'] ?>">
                            <div class="card-imagem">
                                <?php if ($evento['imagem']): ?>
                                    <img src="<?= SITE_URL ?>/uploads/<?= $evento['imagem'] ?>" 
                                         alt="<?= htmlspecialchars($evento['nome']) ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="imagem-placeholder">
                                        <i class="icon-calendar"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-badges">
                                    <span class="badge badge-<?= $evento['tipo'] ?>"><?= ucfirst($evento['tipo']) ?></span>
                                    <?php if ($evento['vagas_restantes'] <= 5 && $evento['vagas_restantes'] > 0): ?>
                                        <span class="badge badge-warning">Últimas vagas</span>
                                    <?php elseif ($evento['vagas_restantes'] <= 0): ?>
                                        <span class="badge badge-danger">Esgotado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-content">
                                <header class="card-header">
                                    <h3 class="evento-titulo">
                                        <a href="<?= SITE_URL ?>/evento/<?= $evento['id'] ?>">
                                            <?= htmlspecialchars($evento['nome']) ?>
                                        </a>
                                    </h3>
                                    <div class="evento-data">
                                        <i class="icon-calendar"></i>
                                        <?= formatar_data($evento['data_inicio']) ?>
                                        <?php if ($evento['data_fim'] && $evento['data_fim'] !== $evento['data_inicio']): ?>
                                            a <?= formatar_data($evento['data_fim']) ?>
                                        <?php endif; ?>
                                    </div>
                                </header>
                                
                                <div class="card-body">
                                    <p class="evento-descricao">
                                        <?= substr(htmlspecialchars($evento['descricao']), 0, 120) ?>
                                        <?= strlen($evento['descricao']) > 120 ? '...' : '' ?>
                                    </p>
                                    
                                    <div class="evento-info">
                                        <div class="info-item">
                                            <i class="icon-location"></i>
                                            <span><?= htmlspecialchars($evento['local']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="icon-map"></i>
                                            <span><?= htmlspecialchars($evento['cidade']) ?>, <?= $evento['estado'] ?></span>
                                        </div>
                                        <?php if ($evento['horario_inicio']): ?>
                                            <div class="info-item">
                                                <i class="icon-clock"></i>
                                                <span><?= date('H:i', strtotime($evento['horario_inicio'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <footer class="card-footer">
                                    <div class="evento-preco">
                                        <?php if ($evento['valor'] > 0): ?>
                                            <span class="preco"><?= formatar_dinheiro($evento['valor']) ?></span>
                                        <?php else: ?>
                                            <span class="preco-gratuito">Gratuito</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="evento-vagas">
                                        <small><?= $evento['total_inscritos'] ?>/<?= $evento['limite_participantes'] ?> inscritos</small>
                                        <div class="progresso-vagas">
                                            <div class="progresso-barra" 
                                                 style="width: <?= min(100, ($evento['total_inscritos'] / $evento['limite_participantes']) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-actions">
                                        <?php if ($evento['vagas_restantes'] > 0): ?>
                                            <a href="<?= SITE_URL ?>/evento/<?= $evento['id'] ?>" 
                                               class="btn btn-primary">
                                                Ver Detalhes
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled>
                                                Esgotado
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </footer>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Seção Sobre -->
    <section class="sobre">
        <div class="container">
            <div class="sobre-content">
                <div class="sobre-texto">
                    <h2>Nossa Missão</h2>
                    <p>O <strong>Vinde</strong> é uma plataforma dedicada a conectar fiéis católicos através de eventos que fortalecem nossa fé e nossa comunidade. Nosso objetivo é facilitar o acesso a retiros, palestras, encontros e celebrações que nutrem o espírito e promovem o crescimento espiritual.</p>
                    
                    <div class="sobre-features">
                        <div class="feature">
                            <i class="icon-heart"></i>
                            <h3>Fé em Comunidade</h3>
                            <p>Eventos que unem pessoas em oração e celebração</p>
                        </div>
                        <div class="feature">
                            <i class="icon-cross"></i>
                            <h3>Crescimento Espiritual</h3>
                            <p>Oportunidades de aprofundar conhecimentos e fé</p>
                        </div>
                        <div class="feature">
                            <i class="icon-users"></i>
                            <h3>Rede Católica</h3>
                            <p>Conectando paróquias e movimentos católicos</p>
                        </div>
                    </div>
                </div>
                
                <div class="sobre-imagem">
                    <img src="<?= SITE_URL ?>/assets/images/sobre-nos.jpg" 
                         alt="Comunidade Católica em Oração" 
                         loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter">
        <div class="container">
            <div class="newsletter-content">
                <h2>Fique por dentro</h2>
                <p>Receba informações sobre novos eventos católicos em sua região</p>
                <form class="newsletter-form" id="form-newsletter">
                    <input type="email" 
                           name="email" 
                           placeholder="Seu melhor email"
                           required
                           class="newsletter-input">
                    <button type="submit" class="btn btn-primary">
                        Cadastrar
                    </button>
                </form>
            </div>
        </div>
    </section>
</main>

<script>
// Filtros de eventos
document.addEventListener('DOMContentLoaded', function() {
    const buscaInput = document.getElementById('busca-evento');
    const filtroCidade = document.getElementById('filtro-cidade');
    const filtroTipo = document.getElementById('filtro-tipo');
    const limparFiltros = document.getElementById('limpar-filtros');
    const eventosLista = document.getElementById('eventos-lista');
    
    function filtrarEventos() {
        const busca = buscaInput.value.toLowerCase();
        const cidade = filtroCidade.value;
        const tipo = filtroTipo.value;
        const eventos = eventosLista.querySelectorAll('.evento-card');
        
        eventos.forEach(evento => {
            const nomeEvento = evento.dataset.nome;
            const cidadeEvento = evento.dataset.cidade;
            const tipoEvento = evento.dataset.tipo;
            
            const matchBusca = !busca || nomeEvento.includes(busca);
            const matchCidade = !cidade || cidadeEvento === cidade;
            const matchTipo = !tipo || tipoEvento === tipo;
            
            if (matchBusca && matchCidade && matchTipo) {
                evento.style.display = 'block';
            } else {
                evento.style.display = 'none';
            }
        });
    }
    
    buscaInput.addEventListener('input', filtrarEventos);
    filtroCidade.addEventListener('change', filtrarEventos);
    filtroTipo.addEventListener('change', filtrarEventos);
    
    limparFiltros.addEventListener('click', function() {
        buscaInput.value = '';
        filtroCidade.value = '';
        filtroTipo.value = '';
        filtrarEventos();
    });
    
    // Newsletter
    document.getElementById('form-newsletter').addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.email.value;
        
        // Simular envio (implementar integração real posteriormente)
        alert('Obrigado! Você receberá nossas novidades em ' + email);
        this.reset();
    });
});
</script>

<?php
obter_rodape();
?> 