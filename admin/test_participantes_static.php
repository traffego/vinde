<?php
require_once '../includes/init.php';
requer_login();

$acao = 'listar';
$stats = [
    'total' => 5,
    'inscritos' => 2,
    'pagos' => 2,
    'presentes' => 1
];
$eventos = [
    ['id' => 1, 'nome' => 'Evento Teste 1'],
    ['id' => 2, 'nome' => 'Evento Teste 2']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Participantes - Dados Estáticos</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/participantes-cards.css?v=<?= time() ?>">
</head>
<body>
    <div class="admin-container">
        <h1>🧪 Teste Participantes - Dados Estáticos</h1>
        
        <div class="debug-info">
            <p><strong>Ação:</strong> <?= $acao ?></p>
            <p><strong>Condição JavaScript:</strong> <?= $acao === 'listar' ? '✅ VERDADEIRA' : '❌ FALSA' ?></p>
        </div>

        <!-- Estatísticas -->
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

        <!-- Grid de Participantes -->
        <div id="participantes-container" class="participantes-container" style="min-height: 200px;">
            <div class="loading-container" id="loading-inicial">
                <div class="loading-spinner"></div>
                <p>Carregando participantes...</p>
            </div>
            <div class="participantes-grid" id="participantes-grid" style="display: none;"></div>
        </div>
    </div>

    <script>
        console.log('🚀 Iniciando teste com dados estáticos');
        
        // Dados estáticos para teste
        const participantesEstaticos = [
            {
                id: 1,
                nome: 'João Silva',
                cpf: '12345678901',
                email: 'joao@teste.com',
                whatsapp: '11999999999',
                evento_nome: 'Evento Teste 1',
                status: 'inscrito',
                pagamento_status: 'pendente',
                criado_em: '2024-01-15 10:30:00'
            },
            {
                id: 2,
                nome: 'Maria Santos',
                cpf: '98765432109',
                email: 'maria@teste.com',
                whatsapp: '11888888888',
                evento_nome: 'Evento Teste 2',
                status: 'pago',
                pagamento_status: 'pago',
                criado_em: '2024-01-16 14:20:00'
            },
            {
                id: 3,
                nome: 'Pedro Costa',
                cpf: '11122233344',
                email: 'pedro@teste.com',
                whatsapp: '11777777777',
                evento_nome: 'Evento Teste 1',
                status: 'presente',
                pagamento_status: 'pago',
                criado_em: '2024-01-17 09:15:00'
            }
        ];

        // Funções utilitárias (copiadas do original)
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
        }

        function formatarCpf(cpf) {
            if (!cpf) return '';
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }

        function criarCardParticipante(p) {
            const card = document.createElement('div');
            card.className = 'participante-card';
            
            const statusParticipante = p.status || 'inscrito';
            const statusPagamento = p.pagamento_status || 'pendente';
            
            let dataCriacao = '';
            if (p.criado_em) {
                const data = new Date(p.criado_em);
                dataCriacao = data.toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            card.innerHTML = `
                <div class="participante-header">
                    <div class="participante-info-principal">
                        <h3 class="participante-nome">${escapeHtml(p.nome)}</h3>
                        <div class="participante-evento-info">
                            <p class="participante-evento">${escapeHtml(p.evento_nome || 'Sem evento')}</p>
                            ${dataCriacao ? `<p class="participante-data-criacao">Criado: ${dataCriacao}</p>` : ''}
                        </div>
                        <p class="participante-cpf">${formatarCpf(p.cpf)}</p>
                        <p class="participante-email">${escapeHtml(p.email)}</p>
                        <p class="participante-whatsapp">${p.whatsapp}</p>
                    </div>
                    <div class="participante-badges">
                        <span class="badge badge-${statusParticipante}">${statusParticipante}</span>
                        <span class="badge badge-pagamento-${statusPagamento}">${statusPagamento}</span>
                    </div>
                </div>
            `;
            
            return card;
        }

        function renderizarParticipantesEstaticos() {
            console.log('📋 Renderizando participantes estáticos:', participantesEstaticos.length);
            
            const container = document.getElementById('participantes-container');
            const loadingInicial = document.getElementById('loading-inicial');
            const grid = document.getElementById('participantes-grid');
            
            // Esconder loading inicial
            if (loadingInicial) {
                loadingInicial.style.display = 'none';
                console.log('✅ Loading inicial escondido');
            }
            
            // Mostrar grid
            if (grid) {
                grid.style.display = 'grid';
                console.log('✅ Grid mostrado');
            }
            
            // Limpar grid
            if (grid) {
                grid.innerHTML = '';
            }
            
            // Adicionar cards
            if (grid && participantesEstaticos.length > 0) {
                participantesEstaticos.forEach((participante, index) => {
                    console.log(`📝 Criando card ${index + 1}:`, participante.nome);
                    const card = criarCardParticipante(participante);
                    grid.appendChild(card);
                });
                
                console.log('✅ Total de cards criados:', grid.children.length);
            } else {
                console.log('❌ Nenhum participante para renderizar');
            }
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎯 DOM carregado, iniciando teste');
            console.log('🔍 Condição listar: <?= $acao === 'listar' ? 'true' : 'false' ?>');
            
            <?php if ($acao === 'listar'): ?>
                console.log('✅ Condição PHP atendida, renderizando participantes estáticos');
                setTimeout(() => {
                    renderizarParticipantesEstaticos();
                }, 1000); // Simular delay de carregamento
            <?php else: ?>
                console.log('❌ Condição PHP não atendida');
            <?php endif; ?>
        });
    </script>
</body>
</html>