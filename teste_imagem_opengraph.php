<?php
/**
 * Teste específico para validar imagens Open Graph
 * DELETE APÓS RESOLVER O PROBLEMA
 */

echo "<h1>🖼️ Teste de Imagens Open Graph</h1>";

// Função para verificar se imagem é acessível
function verificar_imagem_acessivel($url) {
    echo "<p>🔍 Testando: <strong>{$url}</strong></p>";
    
    $headers = @get_headers($url);
    if ($headers) {
        echo "<ul>";
        foreach (array_slice($headers, 0, 3) as $header) {
            echo "<li>{$header}</li>";
        }
        echo "</ul>";
        
        $sucesso = strpos($headers[0], '200') !== false;
        echo "<p>" . ($sucesso ? "✅ <strong>ACESSÍVEL</strong>" : "❌ <strong>NÃO ACESSÍVEL</strong>") . "</p>";
        return $sucesso;
    } else {
        echo "<p>❌ <strong>ERRO ao obter headers</strong></p>";
        return false;
    }
}

// Detectar protocolo e domínio
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$dominio = $_SERVER['HTTP_HOST'];

echo "<h2>📋 Informações do Servidor</h2>";
echo "<ul>";
echo "<li><strong>Protocolo:</strong> {$protocolo}</li>";
echo "<li><strong>Domínio:</strong> {$dominio}</li>";
echo "<li><strong>URL Base:</strong> {$protocolo}://{$dominio}</li>";
echo "</ul>";

echo "<h2>📁 Verificando Estrutura de Pastas</h2>";
$pastas = [
    'uploads' => __DIR__ . '/uploads/',
    'assets/images' => __DIR__ . '/assets/images/',
    'assets/img' => __DIR__ . '/assets/img/'
];

foreach ($pastas as $nome => $caminho) {
    echo "<h3>📂 {$nome}</h3>";
    echo "<p><strong>Caminho:</strong> {$caminho}</p>";
    
    if (is_dir($caminho)) {
        echo "<p>✅ <strong>Pasta existe</strong></p>";
        
        $arquivos = glob($caminho . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
        if ($arquivos) {
            echo "<p>🖼️ <strong>Imagens encontradas:</strong></p>";
            echo "<ul>";
            foreach (array_slice($arquivos, 0, 5) as $arquivo) {
                $nome_arquivo = basename($arquivo);
                $url_imagem = $protocolo . '://' . $dominio . '/' . $nome . '/' . $nome_arquivo;
                echo "<li>";
                echo "{$nome_arquivo} ";
                echo "<a href='{$url_imagem}' target='_blank'>[ver]</a> ";
                echo "<button onclick='testarImagem(\"{$url_imagem}\")'>[testar]</button>";
                echo "</li>";
            }
            echo "</ul>";
            
            if (count($arquivos) > 5) {
                echo "<p>... e mais " . (count($arquivos) - 5) . " arquivos</p>";
            }
        } else {
            echo "<p>⚠️ <strong>Nenhuma imagem encontrada</strong></p>";
        }
    } else {
        echo "<p>❌ <strong>Pasta não existe</strong></p>";
    }
}

echo "<h2>🧪 Teste de URLs de Imagem</h2>";

// URLs para testar
$urls_teste = [
    $protocolo . '://' . $dominio . '/uploads/exemplo.jpg',
    $protocolo . '://' . $dominio . '/assets/images/logo.png',
    $protocolo . '://' . $dominio . '/assets/img/logo.png',
    'https://via.placeholder.com/1200x630/1e40af/ffffff?text=Teste'
];

foreach ($urls_teste as $url) {
    echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0;'>";
    verificar_imagem_acessivel($url);
    echo "</div>";
}

echo "<h2>🔧 Teste de Função de Verificação</h2>";
echo "<div id='resultado-teste'></div>";

?>

<script>
function testarImagem(url) {
    const resultado = document.getElementById('resultado-teste');
    resultado.innerHTML = '<p>🔄 Testando ' + url + '...</p>';
    
    // Criar elemento img para testar carregamento
    const img = new Image();
    
    img.onload = function() {
        resultado.innerHTML = '<p>✅ <strong>Imagem carregou com sucesso!</strong><br>' +
                             'URL: ' + url + '<br>' +
                             'Dimensões: ' + this.naturalWidth + 'x' + this.naturalHeight + '</p>' +
                             '<img src="' + url + '" style="max-width: 300px; border: 1px solid #ccc;">';
    };
    
    img.onerror = function() {
        resultado.innerHTML = '<p>❌ <strong>Erro ao carregar imagem</strong><br>URL: ' + url + '</p>';
    };
    
    img.src = url;
}

// Teste automático do placeholder
setTimeout(() => {
    testarImagem('https://via.placeholder.com/1200x630/1e40af/ffffff?text=EventoTeste');
}, 1000);
</script>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}
h1 { color: #1e40af; }
h2 { color: #3b82f6; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
h3 { color: #6b7280; }
button {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 2px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
button:hover { background: #1d4ed8; }
</style>

<?php
echo "<h2>📋 Informações Adicionais</h2>";
echo "<ul>";
echo "<li><strong>SITE_URL definido:</strong> " . (defined('SITE_URL') ? SITE_URL : 'NÃO DEFINIDO') . "</li>";
echo "<li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</li>";
echo "<li><strong>HTTP Host:</strong> " . $_SERVER['HTTP_HOST'] . "</li>";
echo "<li><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</li>";
echo "</ul>";
?>
