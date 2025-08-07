<?php
require_once '../includes/init.php';

// Redirecionar se já estiver logado
if (esta_logado()) {
    redirecionar(SITE_URL . '/admin/');
}

$erro = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $username = sanitizar_entrada($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $erro = 'Por favor, preencha todos os campos.';
        } else {
            $resultado = fazer_login($username, $password);
            
            if ($resultado === true) {
                redirecionar(SITE_URL . '/admin/');
            } else {
                $erro = $resultado;
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
    <title>Login - Vinde Admin</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-primaria-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--espaco-md);
        }
        
        .login-container {
            background: var(--cor-branco);
            border-radius: var(--borda-radius-grande);
            box-shadow: var(--sombra-grande);
            padding: var(--espaco-2xl);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: var(--espaco-xl);
        }
        
        .login-logo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--cor-primaria);
            margin-bottom: var(--espaco-sm);
        }
        
        .login-subtitle {
            color: var(--cor-cinza-medio);
            font-size: 0.875rem;
        }
        
        .form-group {
            margin-bottom: var(--espaco-lg);
        }
        
        .form-label {
            display: block;
            margin-bottom: var(--espaco-sm);
            font-weight: 500;
            color: var(--cor-cinza-escuro);
        }
        
        .form-input {
            width: 100%;
            padding: var(--espaco-md);
            border: 2px solid #e5e7eb;
            border-radius: var(--borda-radius);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--cor-primaria);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: var(--espaco-md);
            background-color: var(--cor-primaria);
            color: var(--cor-branco);
            border: none;
            border-radius: var(--borda-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .btn-login:hover {
            background-color: var(--cor-primaria-hover);
        }
        
        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
            padding: var(--espaco-md);
            border-radius: var(--borda-radius);
            margin-bottom: var(--espaco-lg);
            border-left: 4px solid #dc2626;
            font-size: 0.875rem;
        }
        
        .login-footer {
            text-align: center;
            margin-top: var(--espaco-xl);
            padding-top: var(--espaco-lg);
            border-top: 1px solid #e5e7eb;
        }
        
        .login-footer a {
            color: var(--cor-primaria);
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .loading {
            display: none;
            margin-left: var(--espaco-sm);
        }
        
        .loading.active {
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">Vinde Admin</div>
            <div class="login-subtitle">Sistema de Gestão de Eventos Católicos</div>
        </div>
        
        <?php if ($erro): ?>
            <div class="error-message">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="username" class="form-label">Usuário</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-input" 
                       required 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Senha</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input" 
                       required>
            </div>
            
            <button type="submit" class="btn-login" id="loginButton">
                Entrar
                <span class="loading" id="loginLoading">...</span>
            </button>
        </form>
        
        <div class="login-footer">
            <a href="<?= SITE_URL ?>">← Voltar ao site</a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const loginLoading = document.getElementById('loginLoading');
            
            loginForm.addEventListener('submit', function() {
                loginButton.disabled = true;
                loginLoading.classList.add('active');
                loginButton.textContent = 'Entrando...';
            });
            
            // Auto-focus no primeiro campo se estiver vazio (com delay para evitar conflitos)
            setTimeout(() => {
                const usernameField = document.getElementById('username');
                if (!usernameField.value) {
                    usernameField.focus();
                } else {
                    document.getElementById('password').focus();
                }
            }, 100);
            
            // Animação de entrada
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html> 