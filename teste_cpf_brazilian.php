<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste - Validação CPF com Brazilian Values</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .test-input {
            width: 200px;
            padding: 8px;
            margin: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .test-button {
            padding: 8px 16px;
            background: #007cba;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .valid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .invalid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste de Validação CPF - Brazilian Values</h1>
        <p>Este teste verifica se a biblioteca <strong>brazilian-values</strong> está funcionando corretamente para validação de CPF.</p>
        
        <!-- Teste Backend PHP -->
        <div class="test-section">
            <h3>🔧 Teste Backend (PHP)</h3>
            <?php
            require_once 'includes/functions.php';
            
            $cpfs_teste = [
                '366.418.768-70', // CPF válido
                '36641876870',    // CPF válido sem formatação
                '111.111.111-11', // CPF inválido (todos iguais)
                '123.456.789-00', // CPF inválido
                '000.000.000-00', // CPF inválido
                '12345678901',    // CPF inválido
            ];
            
            echo '<div class="info"><strong>Testando função validar_cpf() do PHP:</strong></div>';
            
            foreach ($cpfs_teste as $cpf) {
                $resultado = validar_cpf($cpf);
                $classe = $resultado ? 'valid' : 'invalid';
                $status = $resultado ? '✅ VÁLIDO' : '❌ INVÁLIDO';
                echo "<div class='result $classe'>CPF: <strong>$cpf</strong> → $status</div>";
            }
            ?>
        </div>
        
        <!-- Teste Frontend JavaScript -->
        <div class="test-section">
            <h3>🌐 Teste Frontend (JavaScript)</h3>
            <div class="info"><strong>Digite um CPF para testar a validação em tempo real:</strong></div>
            
            <input type="text" id="cpfTeste" class="test-input" placeholder="Digite um CPF" maxlength="14">
            <button onclick="testarCPF()" class="test-button">Validar CPF</button>
            
            <div id="resultadoJS"></div>
            
            <div class="info" style="margin-top: 20px;">
                <strong>CPFs para teste:</strong><br>
                • 366.418.768-70 (válido)<br>
                • 111.111.111-11 (inválido)<br>
                • 123.456.789-00 (inválido)
            </div>
        </div>
        
        <!-- Status da Biblioteca -->
        <div class="test-section">
            <h3>📚 Status da Biblioteca</h3>
            <div id="statusBiblioteca"></div>
        </div>
    </div>
    
    <!-- Biblioteca Brazilian Values -->
    <script src="https://unpkg.com/brazilian-values@0.13.1/dist/brazilian-values.umd.min.js"></script>
    
    <script>
        // Função de validação de CPF usando brazilian-values
        function validarCPF(cpf) {
            if (!cpf) return false;
            
            // Remove formatação
            const cpfLimpo = cpf.replace(/[^\d]/g, '');
            
            // Verifica se tem 11 dígitos
            if (cpfLimpo.length !== 11) return false;
            
            // Usa a biblioteca brazilian-values
            if (typeof BrazilianValues !== 'undefined' && BrazilianValues.isCPF) {
                return BrazilianValues.isCPF(cpf);
            }
            
            // Fallback manual se a biblioteca não carregar
            if (/^(\d)\1{10}$/.test(cpfLimpo)) return false;
            
            let soma = 0;
            for (let i = 0; i < 9; i++) {
                soma += parseInt(cpfLimpo.charAt(i)) * (10 - i);
            }
            let resto = 11 - (soma % 11);
            let digito1 = resto < 2 ? 0 : resto;
            
            if (parseInt(cpfLimpo.charAt(9)) !== digito1) return false;
            
            soma = 0;
            for (let i = 0; i < 10; i++) {
                soma += parseInt(cpfLimpo.charAt(i)) * (11 - i);
            }
            resto = 11 - (soma % 11);
            let digito2 = resto < 2 ? 0 : resto;
            
            return parseInt(cpfLimpo.charAt(10)) === digito2;
        }
        
        // Função para testar CPF
        function testarCPF() {
            const cpf = document.getElementById('cpfTeste').value;
            const resultado = validarCPF(cpf);
            const resultadoDiv = document.getElementById('resultadoJS');
            
            if (!cpf) {
                resultadoDiv.innerHTML = '<div class="result info">Digite um CPF para testar</div>';
                return;
            }
            
            const classe = resultado ? 'valid' : 'invalid';
            const status = resultado ? '✅ VÁLIDO' : '❌ INVÁLIDO';
            const metodo = typeof BrazilianValues !== 'undefined' ? 'Brazilian Values' : 'Fallback Manual';
            
            resultadoDiv.innerHTML = `
                <div class="result ${classe}">
                    <strong>CPF:</strong> ${cpf}<br>
                    <strong>Status:</strong> ${status}<br>
                    <strong>Método:</strong> ${metodo}
                </div>
            `;
        }
        
        // Máscara para CPF
        document.getElementById('cpfTeste').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
        
        // Verificar status da biblioteca
        document.addEventListener('DOMContentLoaded', function() {
            const statusDiv = document.getElementById('statusBiblioteca');
            
            if (typeof BrazilianValues !== 'undefined') {
                statusDiv.innerHTML = `
                    <div class="result valid">
                        ✅ <strong>Biblioteca Brazilian Values carregada com sucesso!</strong><br>
                        Versão detectada: ${BrazilianValues.version || 'Não informada'}<br>
                        Função isCPF disponível: ${typeof BrazilianValues.isCPF === 'function' ? 'Sim' : 'Não'}
                    </div>
                `;
            } else {
                statusDiv.innerHTML = `
                    <div class="result invalid">
                        ❌ <strong>Biblioteca Brazilian Values não foi carregada!</strong><br>
                        Usando validação fallback manual.
                    </div>
                `;
            }
        });
    </script>
</body>
</html>