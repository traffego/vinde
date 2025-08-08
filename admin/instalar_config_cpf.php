<?php
/**
 * Script de instalação da configuração de CPF
 * Execute este arquivo uma vez para adicionar a configuração no banco
 * Acesse: https://seudominio.com/admin/instalar_config_cpf.php
 */

require_once '../includes/init.php';

// Verificar se é admin (opcional - remova se quiser executar sem login)
// requer_login('admin');

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Instalação - Configuração CPF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Instalação da Configuração de CPF</h1>";

try {
    // Verificar se a configuração já existe
    $existe = buscar_um("SELECT * FROM configuracoes WHERE chave = 'verificar_cpf'");
    
    if ($existe) {
        echo "<div class='info'>
                <h3>✅ Configuração já existe!</h3>
                <p><strong>Valor atual:</strong> " . ($existe['valor'] === '1' ? 'CPF Obrigatório' : 'CPF Opcional') . "</p>
                <p><strong>Descrição:</strong> " . htmlspecialchars($existe['descricao'] ?? 'N/A') . "</p>
                <p><strong>Criado em:</strong> " . ($existe['criado_em'] ?? 'N/A') . "</p>
              </div>";
              
        echo "<h3>🔄 Atualizar configuração</h3>";
        echo "<p>Para alterar a configuração, vá em: <strong>Admin > Configurações do Sistema</strong></p>";
        
    } else {
        // Inserir a configuração
        $sucesso = salvar_configuracao(
            'verificar_cpf', 
            '1', 
            'Verificação de CPF obrigatória nas inscrições (1 = ativo, 0 = inativo)'
        );
        
        if ($sucesso) {
            echo "<div class='success'>
                    <h3>✅ Configuração instalada com sucesso!</h3>
                    <p>A configuração de verificação de CPF foi adicionada ao sistema.</p>
                    <p><strong>Status:</strong> CPF Obrigatório (padrão)</p>
                  </div>";
                  
            // Verificar se foi inserida corretamente
            $config_inserida = buscar_um("SELECT * FROM configuracoes WHERE chave = 'verificar_cpf'");
            if ($config_inserida) {
                echo "<div class='info'>
                        <h4>📋 Detalhes da configuração:</h4>
                        <pre>" . print_r($config_inserida, true) . "</pre>
                      </div>";
            }
            
        } else {
            echo "<div class='error'>
                    <h3>❌ Erro ao instalar configuração</h3>
                    <p>Não foi possível inserir a configuração no banco de dados.</p>
                    <p>Verifique os logs do sistema para mais detalhes.</p>
                  </div>";
        }
    }
    
    echo "<h3>🎯 Como usar:</h3>
          <ol>
            <li>Vá em <strong>Admin > Configurações do Sistema</strong></li>
            <li>Na seção <strong>\"⚙️ Configurações do Sistema\"</strong></li>
            <li>Encontre <strong>\"Verificação de CPF Obrigatória\"</strong></li>
            <li>Marque/desmarque conforme necessário</li>
            <li>Clique em <strong>\"💾 Salvar Configurações\"</strong></li>
          </ol>";
          
    echo "<h3>🧹 Limpeza:</h3>
          <p><strong>IMPORTANTE:</strong> Após executar este script, você pode deletar este arquivo por segurança:</p>
          <pre>admin/instalar_config_cpf.php</pre>";
    
} catch (Exception $e) {
    echo "<div class='error'>
            <h3>💥 Erro no sistema</h3>
            <p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>Arquivo:</strong> " . $e->getFile() . "</p>
            <p><strong>Linha:</strong> " . $e->getLine() . "</p>
          </div>";
}

echo "    </div>
</body>
</html>";
?> 