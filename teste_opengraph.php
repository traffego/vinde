<?php
/**
 * Teste para verificar Open Graph - DELETE APÓS TESTE
 */

echo "<h1>Teste Open Graph - Meta Tags</h1>";

// Simular dados de um evento
$evento_teste = [
    'id' => 1,
    'nome' => 'Retiro Espiritual de Quaresma',
    'descricao' => 'Um momento especial de oração e reflexão para nos prepararmos para a Páscoa do Senhor.',
    'imagem' => 'exemplo.jpg', // Coloque aqui o nome de uma imagem real que existe
    'data_inicio' => '2024-02-15',
    'horario_inicio' => '09:00:00',
    'local' => 'Casa de Retiros São José',
    'cidade' => 'São Paulo',
    'estado' => 'SP'
];

// Testar a lógica do Open Graph
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$dominio = $_SERVER['HTTP_HOST'];
$evento_url = $protocolo . '://' . $dominio . '/evento.php?id=' . $evento_teste['id'];

echo "<h2>URL do evento:</h2>";
echo "<p>{$evento_url}</p>";

// Verificar imagem
$evento_imagem_path = '';
if ($evento_teste['imagem']) {
    $caminho_imagem = __DIR__ . '/uploads/' . $evento_teste['imagem'];
    echo "<h2>Verificação de imagem:</h2>";
    echo "<p>Caminho: {$caminho_imagem}</p>";
    echo "<p>Existe: " . (file_exists($caminho_imagem) ? "SIM" : "NÃO") . "</p>";
    
    if (file_exists($caminho_imagem)) {
        $evento_imagem_path = $protocolo . '://' . $dominio . '/uploads/' . $evento_teste['imagem'];
    }
}

// Fallback para logo
if (empty($evento_imagem_path)) {
    $logo_path = __DIR__ . '/assets/images/logo.png';
    echo "<h2>Fallback para logo:</h2>";
    echo "<p>Caminho logo: {$logo_path}</p>";
    echo "<p>Logo existe: " . (file_exists($logo_path) ? "SIM" : "NÃO") . "</p>";
    
    if (file_exists($logo_path)) {
        $evento_imagem_path = $protocolo . '://' . $dominio . '/assets/images/logo.png';
    } else {
        $evento_imagem_path = $protocolo . '://' . $dominio . '/assets/img/default-event.jpg';
    }
}

echo "<h2>URL final da imagem:</h2>";
echo "<p>{$evento_imagem_path}</p>";

// Testar descrição
$evento_descricao = $evento_teste['descricao'] ? strip_tags($evento_teste['descricao']) : $evento_teste['nome'];
$evento_descricao = substr($evento_descricao, 0, 160);
if (strlen(strip_tags($evento_teste['descricao'])) > 160) {
    $evento_descricao .= '...';
}

echo "<h2>Descrição processada:</h2>";
echo "<p>{$evento_descricao}</p>";

// Meta tags que seriam geradas
$meta_tags = [
    'og:title' => htmlspecialchars($evento_teste['nome']),
    'og:description' => htmlspecialchars($evento_descricao),
    'og:image' => $evento_imagem_path,
    'og:image:width' => '1200',
    'og:image:height' => '630',
    'og:image:alt' => htmlspecialchars($evento_teste['nome']),
    'og:url' => $evento_url,
    'og:type' => 'event',
    'og:site_name' => 'Vinde - Eventos Católicos',
    'og:locale' => 'pt_BR'
];

echo "<h2>Meta tags que seriam geradas:</h2>";
echo "<pre>";
foreach ($meta_tags as $property => $content) {
    if (strpos($property, 'twitter:') === 0) {
        echo "<meta name='{$property}' content='{$content}'>\n";
    } else {
        echo "<meta property='{$property}' content='{$content}'>\n";
    }
}
echo "</pre>";

echo "<h2>Verificar pastas de upload:</h2>";
$upload_dirs = [
    __DIR__ . '/uploads/',
    __DIR__ . '/assets/images/',
    __DIR__ . '/assets/img/'
];

foreach ($upload_dirs as $dir) {
    echo "<p>{$dir} - " . (is_dir($dir) ? "EXISTE" : "NÃO EXISTE") . "</p>";
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        echo "<ul>";
        foreach (array_slice($files, 0, 5) as $file) {
            echo "<li>" . basename($file) . "</li>";
        }
        if (count($files) > 5) {
            echo "<li>... e mais " . (count($files) - 5) . " arquivos</li>";
        }
        echo "</ul>";
    }
}
?>
