<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Redirecionar se já estiver logado
if (participante_esta_logado()) {
    redirecionar(SITE_URL . '/participante/');
}

$erro = '';
$sucesso = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $cpf = $_POST['cpf'] ?? '';
        $whatsapp = $_POST['whatsapp'] ?? '';
        
        if (empty($cpf) || empty($whatsapp)) {
            $erro = 'Por favor, preencha todos os campos.';
        } else {
            $resultado = participante_fazer_login($cpf, $whatsapp);
            
            if ($resultado['sucesso']) {
                redirecionar(SITE_URL . '/participante/');
            } else {
                $erro = $resultado['mensagem'];
            }
        }
    }
}

$csrf_token = gerar_csrf_token();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Participante - Vinde</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .login-participante {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-primaria-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-primaria);
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: var(--cor-texto-secundario);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--cor-texto-principal);
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--cor-primaria);
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .btn-login {
            width: 100%;
            background: var(--cor-primaria);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .btn-login:hover {
            background: var(--cor-primaria-dark);
            transform: translateY(-2px);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success-message {
            background: #efe;
            color: #3c3;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
        }

        .login-footer a {
            color: var(--cor-primaria);
            text-decoration: none;
            font-size: 14px;
        }

        .login-info {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--cor-texto-secundario);
        }

        .login-info strong {
            color: var(--cor-texto-principal);
        }
    </style>
</head>
<body class="login-participante">
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">Área do Participante</div>
            <div class="login-subtitle">Acesse seus eventos e QR codes</div>
        </div>

        <div class="login-info">
            <strong>Como acessar:</strong><br>
            Use o CPF e WhatsApp que você informou na inscrição do evento.
        </div>
        
        <?php if ($erro): ?>
            <div class="error-message">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="success-message">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="cpf" class="form-label">CPF</label>
                <input type="text" 
                       id="cpf" 
                       name="cpf" 
                       class="form-input" 
                       placeholder="000.000.000-00"
                       required 
                       maxlength="14"
                       value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="whatsapp" class="form-label">WhatsApp</label>
                <input type="text" 
                       id="whatsapp" 
                       name="whatsapp" 
                       class="form-input" 
                       placeholder="(11) 99999-9999"
                       required
                       maxlength="15"
                       value="<?= htmlspecialchars($_POST['whatsapp'] ?? '') ?>">
            </div>
            
            <button type="submit" class="btn-login">
                Entrar
            </button>
        </form>
        
        <div class="login-footer">
            <a href="<?= SITE_URL ?>">← Voltar ao site</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Máscara para CPF
            const cpfInput = document.getElementById('cpf');
            cpfInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            });

            // Máscara para WhatsApp
            const whatsappInput = document.getElementById('whatsapp');
            whatsappInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 10) {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
                e.target.value = value;
            });
        });
    </script>
</body>
</html> 