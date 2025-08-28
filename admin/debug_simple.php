<?php
require_once '../includes/init.php';
requer_login();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Simples - Participantes</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/participantes-cards.css">
    <style>
        .debug-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .debug-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .console-log {
            background: #000;
            color: #0f0;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .info { color: #0ff; }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>🔍 Debug Simples - Participantes</h1>
        
        <div class="debug-section">
            <h3>📊 Console Logs</h3>
            <div id="console-output" class="console-log">Aguardando logs...
</div>
            <button onclick="clearConsole()" class="btn btn-outline">Limpar Console</button>
        </div>
        
        <div class="debug-section">
            <h3>🎯 Teste Direto da API</h3>
            <button onclick="testarAPI()" class="btn btn-primary">Testar API</button>
            <div id="api-result" style="margin-top: 10px;"></div>
        </div>
        
        <div class="debug-section">
            <h3>📋 Container dos Participantes</h3>
            <div id="participantes-container">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    Carregando participantes...
                </div>
            </div>
        </div>
        
        <div class="debug-section">
            <h3>🔧 Controles</h3>
            <button onclick="carregarParticipantes(true)" class="btn btn-success">Carregar Participantes</button>
            <button onclick="verificarVariaveis()" class="btn btn-info">Verificar Variáveis</button>
            <button onclick="verificarDOM()" class="btn btn-warning">Verificar DOM</button>
        </div>
    </div>

    <script>
        // Interceptar console.log
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;
        
        function addToConsole(message, type = 'info') {
            const output = document.getElementById('console-output');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `[${timestamp}] ${type.toUpperCase()}: ${message}\n`;
            output.textContent += logEntry;
            output.scrollTop = output.scrollHeight;
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToConsole(args.join(' '), 'info');
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addToConsole(args.join(' '), 'error');
        };
        
        console.warn = function(...args) {
            originalWarn.apply(console, args);
            addToConsole(args.join(' '), 'warning');
        };
        
        function clearConsole() {
            document.getElementById('console-output').textContent = 'Console limpo...\n';
        }
        
        function verificarVariaveis() {
            console.log('=== VERIFICAÇÃO DE VARIÁVEIS ===');
            console.log('participantesData:', typeof participantesData, participantesData);
            console.log('filtrosAtivos:', typeof filtrosAtivos, filtrosAtivos);
            console.log('offsetAtual:', typeof offsetAtual, offsetAtual);
            console.log('carregandoMais:', typeof carregandoMais, carregandoMais);
            console.log('temMaisParticipantes:', typeof temMaisParticipantes, temMaisParticipantes);
        }
        
        function verificarDOM() {
            console.log('=== VERIFICAÇÃO DO DOM ===');
            const container = document.getElementById('participantes-container');
            console.log('Container encontrado:', !!container);
            if (container) {
                console.log('Container innerHTML length:', container.innerHTML.length);
                console.log('Container children count:', container.children.length);
                console.log('Container classes:', container.className);
            }
            
            const grid = container ? container.querySelector('.participantes-grid') : null;
            console.log('Grid encontrada:', !!grid);
            if (grid) {
                console.log('Grid children count:', grid.children.length);
            }
        }
        
        function testarAPI() {
            console.log('🔄 Testando API diretamente...');
            
            fetch('<?= SITE_URL ?>/admin/api/participantes.php')
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response ok:', response.ok);
                    return response.text();
                })
                .then(text => {
                    console.log('Response length:', text.length);
                    console.log('Response preview:', text.substring(0, 200));
                    
                    try {
                        const data = JSON.parse(text);
                        console.log('JSON parsed successfully');
                        console.log('Data keys:', Object.keys(data));
                        console.log('Sucesso:', data.sucesso);
                        console.log('Participantes count:', data.participantes ? data.participantes.length : 0);
                        console.log('Total:', data.total);
                        
                        document.getElementById('api-result').innerHTML = `
                            <div style="background: #d1fae5; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                <strong>✅ API OK:</strong> ${data.participantes ? data.participantes.length : 0} participantes retornados
                            </div>
                        `;
                    } catch (e) {
                        console.error('Erro no parse JSON:', e.message);
                        document.getElementById('api-result').innerHTML = `
                            <div style="background: #fee2e2; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                <strong>❌ Erro JSON:</strong> ${e.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    document.getElementById('api-result').innerHTML = `
                        <div style="background: #fee2e2; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <strong>❌ Erro:</strong> ${error.message}
                        </div>
                    `;
                });
        }
        
        // Interceptar erros não tratados
        window.addEventListener('error', function(e) {
            console.error('Erro não tratado:', e.error ? e.error.message : e.message);
            console.error('Arquivo:', e.filename);
            console.error('Linha:', e.lineno);
        });
        
        // Interceptar promises rejeitadas
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Promise rejeitada:', e.reason);
        });
        
        console.log('🚀 Debug carregado, console interceptado');
    </script>
    
    <!-- Incluir o JavaScript original do participantes.php -->
    <script>
        // Variáveis globais (copiadas do participantes.php)
        let participantesData = [];
        let filtrosAtivos = {};
        let participanteAtual = null;
        let offsetAtual = 0;
        let carregandoMais = false;
        let temMaisParticipantes = true;
        
        console.log('✅ Variáveis globais inicializadas');
        
        // Carregar participantes via AJAX (função original)
        function carregarParticipantes(resetar = true) {
            console.log('🚀 carregarParticipantes chamada, resetar:', resetar);
            
            if (carregandoMais) {
                console.log('⏸️ Já carregando, retornando');
                return;
            }
            
            carregandoMais = true;
            console.log('🔄 carregandoMais = true');
            
            const container = document.getElementById('participantes-container');
            console.log('📦 Container encontrado:', !!container);
            
            if (resetar) {
                console.log('🔄 Resetando dados...');
                offsetAtual = 0;
                temMaisParticipantes = true;
                participantesData = [];
                container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Carregando participantes...</p></div>';
            }
            
            // Construir query string com filtros
            const params = new URLSearchParams(filtrosAtivos);
            params.append('offset', offsetAtual);
            
            const url = `<?= SITE_URL ?>/admin/api/participantes.php?${params}`;
            console.log('🌐 URL da requisição:', url);
            
            fetch(url)
                .then(response => {
                    console.log('📡 Response recebido, status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('📊 Data recebido:', data);
                    
                    if (data.sucesso) {
                        const novosParticipantes = data.participantes || [];
                        console.log('👥 Novos participantes:', novosParticipantes.length);
                        
                        if (resetar) {
                            participantesData = novosParticipantes;
                        } else {
                            participantesData = [...participantesData, ...novosParticipantes];
                        }
                        
                        temMaisParticipantes = data.tem_mais || false;
                        offsetAtual = data.proximo_offset || offsetAtual;
                        
                        console.log('🔄 Dados atualizados:', {
                            total: data.total,
                            offset_atual: data.offset,
                            por_pagina: data.por_pagina,
                            tem_mais: data.tem_mais,
                            proximo_offset: data.proximo_offset,
                            participantes_carregados: novosParticipantes.length,
                            total_na_memoria: participantesData.length
                        });
                        
                        console.log('🎨 Chamando renderizarParticipantes...');
                        renderizarParticipantes(resetar ? participantesData : novosParticipantes, resetar);
                    } else {
                        console.error('❌ API retornou erro:', data.erro);
                        throw new Error(data.erro || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar participantes:', error);
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
                    console.log('✅ carregandoMais = false');
                    removerLoadingIndicator();
                });
        }
        
        // Renderizar grid de participantes (função original)
        function renderizarParticipantes(participantes, resetar = true) {
            console.log('🎨 renderizarParticipantes chamada:', participantes.length, 'participantes, resetar:', resetar);
            
            const container = document.getElementById('participantes-container');
            
            if (participantes.length === 0 && resetar) {
                console.log('📭 Nenhum participante para renderizar');
                container.innerHTML = `
                    <div class="no-results">
                        <h3>Nenhum participante encontrado</h3>
                        <p>Tente ajustar os filtros ou adicionar um novo participante</p>
                    </div>
                `;
                return;
            }
            
            let grid = container.querySelector('.participantes-grid');
            console.log('🏗️ Grid existente encontrada:', !!grid);
            
            if (resetar || !grid) {
                console.log('🏗️ Criando nova grid');
                grid = document.createElement('div');
                grid.className = 'participantes-grid';
                container.innerHTML = '';
                container.appendChild(grid);
            }
            
            // Se for reset, adicionar todos. Se não, adicionar apenas os novos participantes
            participantes.forEach((p, index) => {
                console.log(`👤 Renderizando participante ${index + 1}/${participantes.length}:`, p.nome);
                try {
                    const card = criarCardParticipante(p);
                    grid.appendChild(card);
                    console.log(`✅ Card criado para: ${p.nome}`);
                } catch (error) {
                    console.error(`❌ Erro ao criar card para ${p.nome}:`, error);
                }
            });
            
            console.log('✅ Renderização concluída:', participantes.length, 'participantes. Reset:', resetar);
            
            // Adicionar loading indicator se há mais para carregar
            if (temMaisParticipantes && !carregandoMais) {
                adicionarLoadingIndicator();
            }
        }
        
        // Criar card individual do participante (função original simplificada)
        function criarCardParticipante(p) {
            console.log('🏗️ Criando card para:', p.nome);
            
            const card = document.createElement('div');
            card.className = 'participante-card';
            
            // Status do participante (inscrito, pago, presente, cancelado)
            const statusParticipante = p.status || 'inscrito';
            
            // Status do pagamento (pendente, pago, cancelado, estornado)
            const statusPagamento = p.pagamento_status || 'pendente';
            
            // Formatar data de criação
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
            
            console.log('✅ Card HTML criado para:', p.nome);
            return card;
        }
        
        // Funções utilitárias
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
        
        function formatarMoeda(valor) {
            return parseFloat(valor || 0).toFixed(2).replace('.', ',');
        }
        
        // Funções de loading (simplificadas)
        function adicionarLoadingIndicator() {
            console.log('➕ Adicionando loading indicator');
            const container = document.getElementById('participantes-container');
            let indicator = container.querySelector('.scroll-loading-indicator');
            
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'scroll-loading-indicator';
                indicator.innerHTML = `
                    <div class="loading-spinner"></div>
                    <p>Carregando mais participantes...</p>
                `;
                container.appendChild(indicator);
            }
        }
        
        function removerLoadingIndicator() {
            console.log('➖ Removendo loading indicator');
            const container = document.getElementById('participantes-container');
            const indicator = container.querySelector('.scroll-loading-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
    </script>
</body>
</html>