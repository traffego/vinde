<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Habilitar todos os erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Se já está logado, redirecionar
if (participante_esta_logado()) {
    redirecionar(SITE_URL . '/participante/');
}

$erro = '';
$sucesso = '';
$dados_form = [];

// Processar cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>🔍 DEBUG: Dados recebidos do formulário</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
        echo "<div style='color: red;'>❌ CSRF Token inválido</div>";
    } else {
        echo "<div style='color: green;'>✅ CSRF Token válido</div>";
        
        $dados_form = [
            'nome' => trim($_POST['nome'] ?? ''),
            'cpf' => trim($_POST['cpf'] ?? ''),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'idade' => trim($_POST['idade'] ?? ''),
            'cidade' => trim($_POST['cidade'] ?? ''),
            'estado' => trim($_POST['estado'] ?? 'SP'),
            'senha' => trim($_POST['senha'] ?? '')
        ];
        
        echo "<h3>📋 Dados processados:</h3>";
        $dados_debug = $dados_form;
        $dados_debug['senha'] = '*** (oculta por segurança)';
        echo "<pre>";
        print_r($dados_debug);
        echo "</pre>";
        
        echo "<h3>🧪 Executando participante_criar_conta()...</h3>";
        
        try {
            $resultado = participante_criar_conta($dados_form);
            
            echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<strong>📊 Resultado completo da função:</strong><br>";
            echo "<pre>";
            print_r($resultado);
            echo "</pre>";
            echo "</div>";
            
            if ($resultado['sucesso']) {
                $sucesso = $resultado['mensagem'];
                
                // Fazer login automático
                $login_result = participante_fazer_login($dados_form['cpf'], $dados_form['senha']);
                
                if ($login_result['sucesso']) {
                    $redirect_to = $_GET['redirect_to'] ?? SITE_URL . '/participante/';
                    echo "<script>
                        alert('Conta criada e login realizado com sucesso!');
                        window.location.href = '" . $redirect_to . "';
                    </script>";
                } else {
                    echo "<div style='color: orange;'>⚠️ Conta criada mas erro no login automático: " . $login_result['mensagem'] . "</div>";
                }
            } else {
                $erro = $resultado['mensagem'];
                echo "<div style='color: red;'>❌ Erro na criação: " . $erro . "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; margin: 10px 0; color: #c62828;'>";
            echo "<strong>💥 EXCEÇÃO CAPTURADA:</strong><br>";
            echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
            echo "<strong>Arquivo:</strong> " . $e->getFile() . "<br>";
            echo "<strong>Linha:</strong> " . $e->getLine() . "<br>";
            echo "<strong>Stack Trace:</strong><br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
            echo "</div>";
            
            $erro = 'Erro técnico detectado (veja detalhes acima)';
        }
    }
}

// Pre-preencher CPF se fornecido via GET
$cpf_prepreencher = $_GET['cpf'] ?? '';
$evento_id = $_GET['evento_id'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Debug</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
        }
        .error-debug {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .success-debug {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>🔍 Criar Conta - Modo Debug</h1>
            <p>Esta versão mostra detalhes técnicos para diagnóstico</p>
        </div>

        <?php if ($erro): ?>
            <div class="error-debug">
                <strong>❌ Erro:</strong> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="success-debug">
                <strong>✅ Sucesso:</strong> <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
            
            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" required 
                       value="<?= htmlspecialchars($dados_form['nome'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="cpf">CPF</label>
                <input type="text" id="cpf" name="cpf" required maxlength="14"
                       value="<?= htmlspecialchars($dados_form['cpf'] ?? $cpf_prepreencher) ?>">
            </div>

            <div class="form-group">
                <label for="whatsapp">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" required maxlength="15"
                       value="<?= htmlspecialchars($dados_form['whatsapp'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($dados_form['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="idade">Idade</label>
                <input type="number" id="idade" name="idade" required min="1" max="120"
                       value="<?= htmlspecialchars($dados_form['idade'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="cidade">Cidade</label>
                <input type="text" id="cidade" name="cidade" required
                       value="<?= htmlspecialchars($dados_form['cidade'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                    <option value="SP" <?= ($dados_form['estado'] ?? '') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                    <option value="RJ" <?= ($dados_form['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                    <option value="MG" <?= ($dados_form['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                    <!-- Adicione outros estados conforme necessário -->
                </select>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required minlength="6">
                <small>Mínimo 6 caracteres</small>
            </div>

            <button type="submit" class="btn-login">Criar Conta (Debug)</button>
        </form>

        <div class="auth-footer">
            <p>
                Já tem uma conta? 
                <a href="login.php<?= $evento_id ? '?evento_id=' . urlencode($evento_id) : '' ?>">
                    Fazer login
                </a>
            </p>
            <p>
                <a href="<?= SITE_URL ?>">← Voltar ao início</a>
            </p>
        </div>
    </div>

    <script>
        // Máscaras
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });

        document.getElementById('whatsapp').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });
    </script>
</body>
</html> 