<?php
/**
 * Versão minimal do index para testar o obter_cabecalho()
 */
require_once 'includes/init.php';

// Sem buscar eventos - só testar o header
echo "<!-- Debug: Antes do obter_cabecalho -->";

obter_cabecalho('Vinde - Teste Minimal', 'home');

echo "<!-- Debug: Depois do obter_cabecalho -->";
?>

<main>
    <div class="container">
        <h1>🧪 Teste Minimal do Index</h1>
        <p>Se você está vendo isso, o obter_cabecalho() funcionou!</p>
        <p>O problema pode estar na query de eventos ou no HTML complexo.</p>
    </div>
</main>

</body>
</html>
