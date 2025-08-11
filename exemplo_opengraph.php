<?php
// Exemplo simples seguindo o padrão solicitado

$titulo = "XV Desperta Tu que Dormes";
$descricao = "XV Desperta Tu que Dormes - 31 de Agosto de 2025 - 07:30h com a Santa Missa";
$imagem = "https://vinde.traffego.agency/uploads/eventos/68978a8574c52_1754761861.png";
$url = "https://vinde.traffego.agency/evento.php?id=1";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
    
    <!-- Open Graph Meta Tags - Padrão Simples -->
    <meta property="og:title" content="<?= htmlspecialchars($titulo) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($descricao) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($imagem) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($url) ?>">
    <meta property="og:type" content="website">
    
    <!-- Meta tags adicionais -->
    <meta property="og:site_name" content="Vinde - Eventos Católicos">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($titulo) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($descricao) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($imagem) ?>">
</head>
<body>
    <h1>Exemplo Open Graph - Padrão Simples</h1>
    <p>Este arquivo demonstra o padrão solicitado:</p>
    <ul>
        <li><strong>Título:</strong> <?= htmlspecialchars($titulo) ?></li>
        <li><strong>Descrição:</strong> <?= htmlspecialchars($descricao) ?></li>
        <li><strong>Imagem:</strong> <a href="<?= htmlspecialchars($imagem) ?>" target="_blank">Ver imagem</a></li>
        <li><strong>URL:</strong> <?= htmlspecialchars($url) ?></li>
    </ul>
    
    <h2>✅ Benefícios deste padrão:</h2>
    <ul>
        <li>Variáveis claras e definidas no topo</li>
        <li>Meta tags diretas no HTML</li>
        <li>Fácil de ler e manter</li>
        <li>Sem arrays complexos</li>
        <li>htmlspecialchars() aplicado corretamente</li>
    </ul>
</body>
</html>
