<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste QR Code</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .qr-container { border: 1px solid #ccc; padding: 20px; margin: 20px 0; text-align: center; }
        .teste { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Teste do Sistema QR Code</h1>
    
    <div class="teste">
        <h3>Teste 1: QR Code Direto via API</h3>
        <div class="qr-container">
            <img id="qr-direto" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=teste" alt="QR Test">
        </div>
    </div>
    
    <div class="teste">
        <h3>Teste 2: QR Code via Nossa Biblioteca</h3>
        <div class="qr-container">
            <div id="qr-biblioteca"></div>
        </div>
    </div>
    
    <div class="teste">
        <h3>Teste 3: QR Code Dados Reais</h3>
        <div class="qr-container">
            <div id="qr-real"></div>
        </div>
    </div>

    <script src="<?= $_SERVER['REQUEST_SCHEME'] ?>://<?= $_SERVER['HTTP_HOST'] ?>/vinde/assets/js/qr-simple.js"></script>
    <script>
        console.log('Iniciando testes...');
        
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('DOM carregado, iniciando testes...');
            
            // Teste 2: Via nossa biblioteca
            try {
                console.log('Teste 2: Gerando QR via biblioteca...');
                await window.VindeQR.renderTo('qr-biblioteca', 'Teste da biblioteca VindeQR', { size: 200 });
                console.log('Teste 2: Sucesso!');
            } catch (error) {
                console.error('Teste 2: Erro:', error);
                document.getElementById('qr-biblioteca').innerHTML = '<div style="color: red;">Erro: ' + error.message + '</div>';
            }
            
            // Teste 3: Dados reais
            try {
                console.log('Teste 3: Gerando QR com dados reais...');
                const dadosReais = JSON.stringify({
                    tipo: 'checkin',
                    participante_id: 38,
                    evento_id: 1,
                    token: '63d37fb790bcaede578622457b194518',
                    nome: 'JONATHAS QUINTANILHA',
                    evento: 'Retiro Espiritual de Advento'
                });
                
                await window.VindeQR.renderTo('qr-real', dadosReais, { size: 200 });
                console.log('Teste 3: Sucesso!');
            } catch (error) {
                console.error('Teste 3: Erro:', error);
                document.getElementById('qr-real').innerHTML = '<div style="color: red;">Erro: ' + error.message + '</div>';
            }
        });
    </script>
</body>
</html>
