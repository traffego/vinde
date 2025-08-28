<?php
require_once '../includes/init.php';
requer_login();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug API Participantes</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Debug API Participantes</h1>
    
    <div class="debug-section">
        <h2>Teste 1: Chamada Direta da API</h2>
        <button onclick="testarAPIDireta()">Testar API</button>
        <div id="resultado-api"></div>
    </div>
    
    <div class="debug-section">
        <h2>Teste 2: Verificar Console do Navegador</h2>
        <p>Abra o console do navegador (F12) para ver logs detalhados</p>
    </div>
    
    <script>
    function testarAPIDireta() {
        const resultadoDiv = document.getElementById('resultado-api');
        resultadoDiv.innerHTML = '<p>Carregando...</p>';
        
        console.log('Iniciando teste da API...');
        
        fetch('<?= SITE_URL ?>/admin/api/participantes.php')
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return response.text(); // Primeiro como texto para ver se há problemas
            })
            .then(text => {
                console.log('Response text:', text);
                
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                    
                    resultadoDiv.innerHTML = `
                        <div class="success">
                            <h3>✅ API funcionando!</h3>
                            <p><strong>Sucesso:</strong> ${data.sucesso}</p>
                            <p><strong>Total de participantes:</strong> ${data.total || 0}</p>
                            <p><strong>Participantes carregados:</strong> ${data.participantes ? data.participantes.length : 0}</p>
                            <p><strong>Tem mais:</strong> ${data.tem_mais}</p>
                            <p><strong>Próximo offset:</strong> ${data.proximo_offset}</p>
                            <h4>Dados completos:</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } catch (jsonError) {
                    console.error('Erro ao fazer parse do JSON:', jsonError);
                    resultadoDiv.innerHTML = `
                        <div class="error">
                            <h3>❌ Erro de JSON</h3>
                            <p><strong>Erro:</strong> ${jsonError.message}</p>
                            <h4>Resposta recebida:</h4>
                            <pre>${text}</pre>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                resultadoDiv.innerHTML = `
                    <div class="error">
                        <h3>❌ Erro na requisição</h3>
                        <p><strong>Erro:</strong> ${error.message}</p>
                    </div>
                `;
            });
    }
    
    // Testar automaticamente ao carregar
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Página carregada, testando API automaticamente...');
        testarAPIDireta();
    });
    </script>
</body>
</html>