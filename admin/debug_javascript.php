<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug JavaScript - Participantes</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .log { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .error { background: #ffe6e6; border-left: 4px solid #ff0000; }
        .success { background: #e6ffe6; border-left: 4px solid #00aa00; }
        .info { background: #e6f3ff; border-left: 4px solid #0066cc; }
        #participantes-container { border: 2px solid #ccc; min-height: 200px; padding: 20px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>Debug JavaScript - Sistema de Participantes</h1>
    
    <div class="log info">
        <strong>Teste 1:</strong> Verificando se o container existe
        <div id="teste1"></div>
    </div>
    
    <div class="log info">
        <strong>Teste 2:</strong> Testando a API
        <div id="teste2"></div>
    </div>
    
    <div class="log info">
        <strong>Teste 3:</strong> Simulando a função carregarParticipantes
        <div id="teste3"></div>
    </div>
    
    <div class="log info">
        <strong>Container dos Participantes:</strong>
        <div id="participantes-container">
            <div class="loading">
                <div class="loading-spinner"></div>
                Carregando participantes...
            </div>
        </div>
    </div>
    
    <div class="log info">
        <strong>Logs do Console:</strong>
        <div id="console-logs"></div>
    </div>

    <script>
        // Capturar todos os logs do console
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;
        const logsDiv = document.getElementById('console-logs');
        
        function addLog(type, message) {
            const logElement = document.createElement('div');
            logElement.className = type;
            logElement.innerHTML = `<strong>[${type.toUpperCase()}]</strong> ${message}`;
            logsDiv.appendChild(logElement);
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addLog('info', args.join(' '));
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addLog('error', args.join(' '));
        };
        
        console.warn = function(...args) {
            originalWarn.apply(console, args);
            addLog('error', args.join(' '));
        };
        
        // Capturar erros não tratados
        window.addEventListener('error', function(e) {
            addLog('error', `Erro não tratado: ${e.message} em ${e.filename}:${e.lineno}`);
        });
        
        // Teste 1: Verificar se o container existe
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado, iniciando testes...');
            
            const container = document.getElementById('participantes-container');
            const teste1 = document.getElementById('teste1');
            
            if (container) {
                teste1.innerHTML = '<span class="success">✓ Container encontrado</span>';
                console.log('Container participantes-container encontrado');
            } else {
                teste1.innerHTML = '<span class="error">✗ Container NÃO encontrado</span>';
                console.error('Container participantes-container NÃO encontrado');
            }
            
            // Teste 2: Testar a API
            console.log('Iniciando teste da API...');
            fetch('api/participantes.php')
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Response text length:', text.length);
                    try {
                        const data = JSON.parse(text);
                        console.log('JSON parsed successfully');
                        console.log('Participantes encontrados:', data.participantes ? data.participantes.length : 0);
                        
                        const teste2 = document.getElementById('teste2');
                        teste2.innerHTML = `<span class="success">✓ API funcionando - ${data.participantes ? data.participantes.length : 0} participantes</span>`;
                        
                        // Teste 3: Simular renderização
                        if (data.participantes && data.participantes.length > 0) {
                            console.log('Simulando renderização...');
                            renderizarParticipantesTeste(data.participantes.slice(0, 3)); // Apenas os 3 primeiros
                        }
                        
                    } catch (e) {
                        console.error('Erro ao fazer parse do JSON:', e);
                        console.log('Texto da resposta:', text.substring(0, 500));
                        const teste2 = document.getElementById('teste2');
                        teste2.innerHTML = '<span class="error">✗ Erro no parse do JSON</span>';
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    const teste2 = document.getElementById('teste2');
                    teste2.innerHTML = '<span class="error">✗ Erro na API: ' + error.message + '</span>';
                });
        });
        
        // Função de teste para renderizar participantes
        function renderizarParticipantesTeste(participantes) {
            console.log('Função renderizarParticipantesTeste chamada com', participantes.length, 'participantes');
            
            const container = document.getElementById('participantes-container');
            if (!container) {
                console.error('Container não encontrado na renderização');
                return;
            }
            
            // Limpar o loading
            container.innerHTML = '';
            
            // Criar grid
            const grid = document.createElement('div');
            grid.className = 'participantes-grid';
            grid.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;';
            
            participantes.forEach(participante => {
                console.log('Renderizando participante:', participante.nome);
                
                const card = document.createElement('div');
                card.style.cssText = 'border: 1px solid #ddd; padding: 1rem; border-radius: 8px; background: white;';
                
                card.innerHTML = `
                    <h3 style="margin: 0 0 0.5rem 0; color: #333;">${participante.nome}</h3>
                    <p style="margin: 0.25rem 0; color: #666;"><strong>CPF:</strong> ${participante.cpf || 'N/A'}</p>
                    <p style="margin: 0.25rem 0; color: #666;"><strong>Cidade:</strong> ${participante.cidade || 'N/A'}</p>
                    <p style="margin: 0.25rem 0; color: #666;"><strong>Status:</strong> ${participante.status || 'N/A'}</p>
                    <p style="margin: 0.25rem 0; color: #666;"><strong>Pagamento:</strong> ${participante.pagamento_status || 'N/A'}</p>
                `;
                
                grid.appendChild(card);
            });
            
            container.appendChild(grid);
            
            const teste3 = document.getElementById('teste3');
            teste3.innerHTML = `<span class="success">✓ Renderização teste concluída - ${participantes.length} participantes exibidos</span>`;
            
            console.log('Renderização de teste concluída');
        }
    </script>
</body>
</html>