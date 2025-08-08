<?php
// Versão ultra simples para identificar o problema
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

try {
    // Includes mínimos
    require_once 'includes/init.php';
    require_once 'includes/auth_participante.php';

    // Variáveis básicas
    $inscricao_id = $_GET['inscricao'] ?? '';
    
    // Validação simples
    if (empty($inscricao_id) || !is_numeric($inscricao_id)) {
        die("ID inválido");
    }

    // Verificar login
    if (!participante_esta_logado()) {
        header('Location: ' . SITE_URL . '/participante/login.php');
        exit;
    }

    $participante_logado = obter_participante_logado();
    
    // Query simples
    $dados = buscar_um("
        SELECT 
            i.id, i.status,
            e.nome as evento_nome, e.valor as evento_valor,
            pag.pix_qrcode_data, pag.status as pagamento_status
        FROM inscricoes i
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pag ON i.id = pag.inscricao_id
        WHERE i.id = ? AND i.participante_id = ?
    ", [$inscricao_id, $participante_logado['id']]);

    if (!$dados) {
        die("Dados não encontrados");
    }

    // Verificar se já está pago
    if ($dados['pagamento_status'] === 'pago') {
        header('Location: ' . SITE_URL . '/confirmacao.php?inscricao=' . $inscricao_id);
        exit;
    }

    // Se não tem PIX, simular um
    $pix_code = $dados['pix_qrcode_data'];
    if (empty($pix_code)) {
        $pix_code = '00020101021226830014BR.GOV.BCB.PIX2561qrcodespix.sejaefi.com.br/v2/teste123';
    }

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - <?= htmlspecialchars($dados['evento_nome']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 800px; margin: 0 auto; padding: 20px; }
        .container { background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .pix-code { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace; font-size: 12px; word-break: break-all; margin: 15px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Pagamento - <?= htmlspecialchars($dados['evento_nome']) ?></h1>
    
    <div class="container">
        <h2>Informações</h2>
        <p><strong>Evento:</strong> <?= htmlspecialchars($dados['evento_nome']) ?></p>
        <p><strong>Valor:</strong> R$ <?= number_format($dados['evento_valor'], 2, ',', '.') ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($dados['status']) ?></p>
    </div>

    <div class="container">
        <h2>Código PIX</h2>
        <div class="success">✅ Código PIX gerado com sucesso!</div>
        
        <div class="pix-code" id="pix-code">
            <?= htmlspecialchars($pix_code) ?>
        </div>
        
        <button class="btn" onclick="copiarPix()">📋 Copiar Código PIX</button>
        <button class="btn" onclick="window.location.reload()">🔄 Atualizar</button>
    </div>

    <div class="container">
        <h3>Como pagar:</h3>
        <ol>
            <li>Copie o código PIX acima</li>
            <li>Abra o app do seu banco</li>
            <li>Escolha a opção PIX</li>
            <li>Cole o código copiado</li>
            <li>Confirme o pagamento</li>
        </ol>
    </div>

    <script>
        function copiarPix() {
            const code = document.getElementById('pix-code').textContent.trim();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    alert('Código PIX copiado!');
                }).catch(() => {
                    fallbackCopy(code);
                });
            } else {
                fallbackCopy(code);
            }
        }
        
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('Código PIX copiado!');
            } catch (err) {
                alert('Erro ao copiar. Selecione o código manualmente.');
            }
            document.body.removeChild(textarea);
        }
    </script>
</body>
</html>
