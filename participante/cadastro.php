<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Redirecionar se já estiver logado
if (participante_esta_logado()) {
    redirecionar(SITE_URL . '/participante/');
}

$erro = '';
$sucesso = '';

// Obter CPF da URL se fornecido
$cpf_url = $_GET['cpf'] ?? '';
$evento_id = $_GET['evento_id'] ?? '';

// Processar cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $dados = [
            'nome' => $_POST['nome'] ?? '',
            'cpf' => $_POST['cpf'] ?? '',
            'whatsapp' => $_POST['whatsapp'] ?? '',
            'instagram' => $_POST['instagram'] ?? '',
            'email' => $_POST['email'] ?? '',
            'idade' => $_POST['idade'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
            'estado' => $_POST['estado'] ?? 'SP',
            'senha' => $_POST['senha'] ?? ''
        ];

        // Validar confirmação de senha
        if ($dados['senha'] !== ($_POST['confirmar_senha'] ?? '')) {
            $erro = 'As senhas não coincidem.';
        } else {
            $resultado = participante_criar_conta($dados);
            
            if ($resultado['sucesso']) {
                $sucesso = $resultado['mensagem'];
                
                // Fazer login automático após cadastro
                $login_result = participante_fazer_login($dados['cpf'], $dados['senha']);
                
                if ($login_result['sucesso']) {
                    // Redirecionar para inscrição no evento ou área do participante
                    if ($evento_id) {
                        redirecionar(SITE_URL . '/inscricao.php?evento_id=' . urlencode($evento_id));
                    } else {
                        redirecionar(SITE_URL . '/participante/');
                    }
                }
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
    <title>Criar Conta - Vinde</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .cadastro-participante {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-primaria-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .cadastro-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .cadastro-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .cadastro-logo {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-primaria);
            margin-bottom: 8px;
        }

        .cadastro-subtitle {
            color: var(--cor-texto-secundario);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--cor-texto-principal);
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--cor-primaria);
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .btn-cadastrar {
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
        }

        .btn-cadastrar:hover {
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

        .cadastro-footer {
            text-align: center;
            margin-top: 30px;
        }

        .cadastro-footer a {
            color: var(--cor-primaria);
            text-decoration: none;
            font-size: 14px;
        }

        .cadastro-info {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--cor-texto-secundario);
        }

        .required {
            color: #e74c3c;
        }

        .password-hint {
            font-size: 12px;
            color: var(--cor-texto-secundario);
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body class="cadastro-participante">
    <div class="cadastro-container">
        <div class="cadastro-header">
            <div class="cadastro-logo">Criar Conta</div>
            <div class="cadastro-subtitle">Preencha os dados para criar sua conta</div>
        </div>

        <div class="cadastro-info">
            <strong>Nova conta:</strong> Seus dados serão utilizados para futuras inscrições em eventos.
            Campos marcados com <span class="required">*</span> são obrigatórios.
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
            
            <!-- Nome completo -->
            <div class="form-group">
                <label for="nome" class="form-label">Nome Completo <span class="required">*</span></label>
                <input type="text" 
                       id="nome" 
                       name="nome" 
                       class="form-input" 
                       placeholder="Seu nome completo"
                       required 
                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
            </div>
            
            <!-- CPF e Idade -->
            <div class="form-row">
                <div class="form-group">
                    <label for="cpf" class="form-label">CPF <?= cpf_obrigatorio() ? '<span class="required">*</span>' : '(opcional)' ?></label>
                    <input type="text" 
                           id="cpf" 
                           name="cpf" 
                           class="form-input" 
                           placeholder="000.000.000-00"
                           <?= cpf_obrigatorio() ? 'required' : '' ?> 
                           maxlength="14"
                           value="<?= htmlspecialchars($_POST['cpf'] ?? $cpf_url) ?>">
                </div>
                
                <div class="form-group">
                    <label for="idade" class="form-label">Idade <span class="required">*</span></label>
                    <input type="number" 
                           id="idade" 
                           name="idade" 
                           class="form-input" 
                           placeholder="Ex: 25"
                           required 
                           min="1" 
                           max="120"
                           value="<?= htmlspecialchars($_POST['idade'] ?? '') ?>">
                </div>
            </div>
            
            <!-- Email -->
            <div class="form-group">
                <label for="email" class="form-label">Email <span class="required">*</span></label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="form-input" 
                       placeholder="seu@email.com"
                       required 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <!-- WhatsApp e Instagram -->
            <div class="form-row">
                <div class="form-group">
                    <label for="whatsapp" class="form-label">WhatsApp <span class="required">*</span></label>
                    <input type="text" 
                           id="whatsapp" 
                           name="whatsapp" 
                           class="form-input" 
                           placeholder="(11) 99999-9999"
                           required 
                           maxlength="15"
                           value="<?= htmlspecialchars($_POST['whatsapp'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="instagram" class="form-label">Instagram</label>
                    <input type="text" 
                           id="instagram" 
                           name="instagram" 
                           class="form-input" 
                           placeholder="@seuinstagram"
                           value="<?= htmlspecialchars($_POST['instagram'] ?? '') ?>">
                </div>
            </div>
            
            <!-- Cidade e Estado -->
            <div class="form-row">
                <div class="form-group">
                    <label for="cidade" class="form-label">Cidade <span class="required">*</span></label>
                    <input type="text" 
                           id="cidade" 
                           name="cidade" 
                           class="form-input" 
                           placeholder="Sua cidade"
                           required 
                           value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="estado" class="form-label">Estado</label>
                    <select id="estado" name="estado" class="form-select">
                        <option value="SP" <?= ($_POST['estado'] ?? 'SP') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                        <option value="RJ" <?= ($_POST['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                        <option value="MG" <?= ($_POST['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                        <option value="ES" <?= ($_POST['estado'] ?? '') === 'ES' ? 'selected' : '' ?>>Espírito Santo</option>
                        <option value="PR" <?= ($_POST['estado'] ?? '') === 'PR' ? 'selected' : '' ?>>Paraná</option>
                        <option value="SC" <?= ($_POST['estado'] ?? '') === 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                        <option value="RS" <?= ($_POST['estado'] ?? '') === 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                        <!-- Adicione outros estados conforme necessário -->
                    </select>
                </div>
            </div>
            
            <!-- Senha e Confirmação -->
            <div class="form-group">
                <label for="senha" class="form-label">Senha <span class="required">*</span></label>
                <input type="password" 
                       id="senha" 
                       name="senha" 
                       class="form-input" 
                       placeholder="Crie uma senha"
                       required 
                       minlength="6">
                <div class="password-hint">Mínimo de 6 caracteres</div>
            </div>
            
            <div class="form-group">
                <label for="confirmar_senha" class="form-label">Confirmar Senha <span class="required">*</span></label>
                <input type="password" 
                       id="confirmar_senha" 
                       name="confirmar_senha" 
                       class="form-input" 
                       placeholder="Confirme sua senha"
                       required 
                       minlength="6">
            </div>
            
            <button type="submit" class="btn-cadastrar">
                Criar Conta e Continuar
            </button>
        </form>
        
        <div class="cadastro-footer">
            <a href="<?= SITE_URL ?>/participante/login.php">← Já tenho conta</a>
        </div>
    </div>

    <script>
        // Configuração global para validação de CPF
        window.cpfObrigatorio = <?= cpf_obrigatorio() ? 'true' : 'false' ?>;
        
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

            // Validação de confirmação de senha
            const senhaInput = document.getElementById('senha');
            const confirmarSenhaInput = document.getElementById('confirmar_senha');
            
            function validarSenhas() {
                if (senhaInput.value !== confirmarSenhaInput.value) {
                    confirmarSenhaInput.setCustomValidity('As senhas não coincidem');
                } else {
                    confirmarSenhaInput.setCustomValidity('');
                }
            }
            
            senhaInput.addEventListener('input', validarSenhas);
            confirmarSenhaInput.addEventListener('input', validarSenhas);
        });
    </script>
</body>
</html>