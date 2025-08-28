<?php
require_once '../includes/init.php';
requer_login();

// Verificar qual ação está sendo executada
$acao = $_GET['acao'] ?? 'listar';
$participante_id = $_GET['id'] ?? null;
$evento_id = $_GET['evento'] ?? null;

echo "<h1>Debug da Ação</h1>";
echo "<p><strong>Ação atual:</strong> " . htmlspecialchars($acao) . "</p>";
echo "<p><strong>Participante ID:</strong> " . htmlspecialchars($participante_id ?? 'null') . "</p>";
echo "<p><strong>Evento ID:</strong> " . htmlspecialchars($evento_id ?? 'null') . "</p>";
echo "<p><strong>URL atual:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</p>";
echo "<p><strong>Query string:</strong> " . htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') . "</p>";
echo "<p><strong>GET params:</strong> " . htmlspecialchars(print_r($_GET, true)) . "</p>";

echo "<h2>Teste de Condição</h2>";
if ($acao === 'listar') {
    echo "<p style='color: green;'>✅ Condição \$acao === 'listar' é VERDADEIRA</p>";
    echo "<p>O JavaScript DEVERIA ser carregado!</p>";
} else {
    echo "<p style='color: red;'>❌ Condição \$acao === 'listar' é FALSA</p>";
    echo "<p>O JavaScript NÃO será carregado!</p>";
}

echo "<h2>Links de Teste</h2>";
echo "<p><a href='participantes.php'>participantes.php (sem parâmetros)</a></p>";
echo "<p><a href='participantes.php?acao=listar'>participantes.php?acao=listar</a></p>";
echo "<p><a href='debug_simple.php'>debug_simple.php</a></p>";
?>