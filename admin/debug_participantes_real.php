<?php
require_once '../includes/init.php';
requer_login();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Participantes - Função Real</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/participantes-cards.css">
    <style>
        .debug-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .debug-log {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.5rem;
            margin: 0.5rem 0;
            font-family: monospace;
            font-size: 0.875rem;
            max-height: 200px;
            overflow-y: auto;
        }
        .error { color: #dc3545; }
        .success { color: #28a745; }
        .info { color: #007bff; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>🔍 Debug Participantes - Função Real</h1>
        
        <div class="debug-section">
            <h3>📊 Status da API</h3>
            <div id="api-status" class="debug-log">Testando API...</div>
        </div>
        
        <div class="debug-section">
            <h3>🔄 Logs da Função carregarParticipantes()</h3>
            <div id="function-logs" class="debug-log">Aguardando execução...</div>
        </div>
        
        <div class="debug-section">
            <h3>🎨 Logs da Renderização</h3>
            <div id="render-logs" class="debug-log">Aguardando renderização...</div>
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
            <h3>🔧 Controles de Debug</h3>
            <button onclick="testarAPI()" class="btn btn-primary">Testar API Diretamente</button>
            <button onclick="testarCarregarParticipantes()" class="btn btn-success">Testar carregarParticipantes()</button>
            <button onclick="limparLogs()" class="btn btn-outline">Limpar Logs</button>
        </div>
    </div>

    <script>
        // Variáveis globais (copiadas do arquivo original)
        let participantesData = [];
        let filtrosAtivos = {};
        let participanteAtual = null;
        let offsetAtual = 0;
        let carregandoMais = false;
        let temMaisParticipantes = true;
        
        // Função para adicionar logs
        function addLog(containerId, message, type = 'info') {
            const container = document.getElementById(containerId);
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = type;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            container.appendChild(logEntry);
            container.scrollTop = container.scrollHeight;
        }
        
        function limparLogs() {
            ['api-status', 'function-logs', 'render-logs'].forEach(id => {
                document.getElementById(id).innerHTML = '';
            });
        }
        
        // Testar API diretamente
        function testarAPI() {
            addLog('api-status', '🔄 Iniciando teste da API...', 'info');
            
            fetch('api/participantes.php')
                .then(response => {
                    addLog('api-status', `📡 Status HTTP: ${response.status}`, response.ok ? 'success' : 'error');
                    return response.text();
                })
                .then(text => {
                    addLog('api-status', `📏 Tamanho da resposta: ${text.length} caracteres`, 'info');
                    
                    try {
                        const data = JSON.parse(text);
                        addLog('api-status', '✅ JSON válido parseado', 'success');
                        addLog('api-status', `📊 Participantes retornados: ${data.participantes ? data.participantes.length : 0}`, 'info');
                        addLog('api-status', `📈 Total no banco: ${data.total || 0}`, 'info');
                        addLog('api-status', `🔄 Tem mais: ${data.tem_mais ? 'Sim' : 'Não'}`, 'info');
                        
                        if (data.participantes && data.participantes.length > 0) {
                            addLog('api-status', `👤 Primeiro participante: ${data.participantes[0].nome}`, 'success');
                        }
                    } catch (e) {
                        addLog('api-status', `❌ Erro no parse JSON: ${e.message}`, 'error');
                        addLog('api-status', `📄 Primeiros 500 chars: ${text.substring(0, 500)}`, 'warning');
                    }
                })
                .catch(error => {
                    addLog('api-status', `❌ Erro na requisição: ${error.message}`, 'error');
                });
        }
        
        // Testar função carregarParticipantes (copiada e modificada)
        function testarCarregarParticipantes() {
            addLog('function-logs', '🚀 Iniciando carregarParticipantes(true)', 'info');
            
            if (carregandoMais) {
                addLog('function-logs', '⏸️ Já está carregando, abortando', 'warning');
                return;
            }
            
            carregandoMais = true;
            const container = document.getElementById('participantes-container');
            
            // Reset
            offsetAtual = 0;
            temMaisParticipantes = true;
            participantesData = [];
            container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Carregando participantes...</p></div>';
            
            addLog('function-logs', '🔄 Estado resetado, fazendo requisição...', 'info');
            
            // Construir query string com filtros
            const params = new URLSearchParams(filtrosAtivos);
            params.append('offset', offsetAtual);
            
            const url = `<?= SITE_URL ?>/admin/api/participantes.php?${params}`;
            addLog('function-logs', `🌐 URL: ${url}`, 'info');
            
            fetch(url)
                .then(response => {
                    addLog('function-logs', `📡 Response status: ${response.status}`, response.ok ? 'success' : 'error');
                    return response.json();
                })
                .then(data => {
                    addLog('function-logs', '✅ JSON recebido com sucesso', 'success');
                    addLog('function-logs', `📊 Sucesso: ${data.sucesso}`, data.sucesso ? 'success' : 'error');
                    
                    if (data.sucesso) {
                        const novosParticipantes = data.participantes || [];
                        addLog('function-logs', `👥 Novos participantes: ${novosParticipantes.length}`, 'info');
                        
                        participantesData = novosParticipantes;
                        temMaisParticipantes = data.tem_mais || false;
                        offsetAtual = data.proximo_offset || offsetAtual;
                        
                        addLog('function-logs', `🔄 temMaisParticipantes: ${temMaisParticipantes}`, 'info');
                        addLog('function-logs', `📍 offsetAtual: ${offsetAtual}`, 'info');
                        
                        // Chamar renderização
                        addLog('function-logs', '🎨 Chamando renderizarParticipantes...', 'info');
                        renderizarParticipantes(participantesData, true);
                    } else {
                        addLog('function-logs', `❌ Erro da API: ${data.erro}`, 'error');
                        throw new Error(data.erro || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    addLog('function-logs', `❌ Erro: ${error.message}`, 'error');
                    container.innerHTML = `
                        <div class="no-results">
                            <h3>Erro ao carregar participantes</h3>
                            <p>${error.message}</p>
                        </div>
                    `;
                })
                .finally(() => {
                    carregandoMais = false;
                    addLog('function-logs', '✅ carregandoMais = false', 'info');
                });
        }
        
        // Função renderizarParticipantes (copiada e modificada)
        function renderizarParticipantes(participantes, resetar = true) {
            addLog('render-logs', `🎨 renderizarParticipantes chamada: ${participantes.length} participantes, resetar: ${resetar}`, 'info');
            
            const container = document.getElementById('participantes-container');
            
            if (participantes.length === 0 && resetar) {
                addLog('render-logs', '📭 Nenhum participante para renderizar', 'warning');
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
                addLog('render-logs', '🏗️ Criando nova grid', 'info');
                grid = document.createElement('div');
                grid.className = 'participantes-grid';
                container.innerHTML = '';
                container.appendChild(grid);
            }
            
            // Renderizar cada participante
            participantes.forEach((p, index) => {
                addLog('render-logs', `👤 Renderizando ${index + 1}/${participantes.length}: ${p.nome}`, 'info');
                
                try {
                    const card = criarCardParticipante(p);
                    grid.appendChild(card);
                    addLog('render-logs', `✅ Card criado para: ${p.nome}`, 'success');
                } catch (error) {
                    addLog('render-logs', `❌ Erro ao criar card para ${p.nome}: ${error.message}`, 'error');
                }
            });
            
            addLog('render-logs', `✅ Renderização concluída: ${participantes.length} cards adicionados`, 'success');
        }
        
        // Função criarCardParticipante (simplificada)
        function criarCardParticipante(p) {
            const card = document.createElement('div');
            card.className = 'participante-card';
            
            const statusParticipante = p.status || 'inscrito';
            const statusPagamento = p.pagamento_status || 'pendente';
            
            card.innerHTML = `
                <div class="participante-header">
                    <div class="participante-info-principal">
                        <h3 class="participante-nome">${escapeHtml(p.nome)}</h3>
                        <p class="participante-evento">${escapeHtml(p.evento_nome || 'Sem evento')}</p>
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
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            addLog('api-status', '🚀 Debug carregado, pronto para testes', 'success');
        });
    </script>
</body>
</html>