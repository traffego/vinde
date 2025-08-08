<?php
// Versão minimalista da página de pagamento para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'includes/init.php';
    require_once 'includes/auth_participante.php';

    $inscricao_id = $_GET['inscricao'] ?? '21';
    $debug_mode = true;
    $erro = '';
    $novo_pix_gerado = false;

    // Verificações básicas
    if (empty($inscricao_id) || !is_numeric($inscricao_id)) {
        die("ID inválido");
    }

    if (!participante_esta_logado()) {
        die("Não logado");
    }

    $participante_logado = obter_participante_logado();
    
    // Buscar dados
    $dados = buscar_um("
        SELECT i.*, 
               p.nome as participante_nome, p.cpf as participante_cpf, 
               e.nome as evento_nome, e.valor as evento_valor,
               pag.id as pagamento_id, pag.status as pagamento_status, 
               pag.pix_qrcode_data, pag.pix_expires_at
        FROM inscricoes i
        JOIN participantes p ON i.participante_id = p.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN pagamentos pag ON i.id = pag.inscricao_id
        WHERE i.id = ? AND i.participante_id = ?
    ", [$inscricao_id, $participante_logado['id']]);

    if (!$dados) {
        die("Dados não encontrados");
    }

    $evento = ['nome' => $dados['evento_nome'], 'valor' => $dados['evento_valor']];
    $pagamento = ['status' => $dados['pagamento_status'], 'pix_qrcode_data' => $dados['pix_qrcode_data']];

    // Simular geração de PIX se necessário
    if (empty($pagamento['pix_qrcode_data'])) {
        $pagamento['pix_qrcode_data'] = '00020101021226830014BR.GOV.BCB.PIX2561qrcodespix.sejaefi.com.br/v2/teste123';
        $novo_pix_gerado = true;
    }

    // Cabeçalho HTML simples
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pagamento - <?= htmlspecialchars($evento['nome']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .container { max-width: 800px; margin: 0 auto; }
            .pix-code { background: #f5f5f5; padding: 15px; border-radius: 5px; word-break: break-all; }
            .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Pagamento - <?= htmlspecialchars($evento['nome']) ?></h1>
            
            <?php if ($novo_pix_gerado): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    ✅ Novo PIX gerado com sucesso!
                </div>
            <?php endif; ?>
            
            <h2>Código PIX</h2>
            <div class="pix-code" id="pix-code">
                <?= htmlspecialchars($pagamento['pix_qrcode_data']) ?>
            </div>
            
            <button class="btn" onclick="copiarPix()">Copiar Código PIX</button>
            
            <script>
                function copiarPix() {
                    const code = document.getElementById('pix-code').textContent;
                    navigator.clipboard.writeText(code.trim()).then(() => {
                        alert('Código PIX copiado!');
                    });
                }
            </script>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    echo "<h1>Erro Capturado</h1>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
