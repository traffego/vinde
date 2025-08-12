<?php
// Sistema de Manutenção - Vinde
// Arquivo: includes/manutencao.php

// Prevenir acesso direto
if (!defined('SISTEMA_INSCRICOES')) {
    die('Acesso negado');
}

/**
 * Verifica se o sistema está em modo manutenção
 * Se estiver, exibe página de manutenção e interrompe execução
 */
function verificar_modo_manutencao() {
    // Se modo manutenção está desativado, continua normalmente
    if (!MODO_MANUTENCAO) {
        return;
    }
    
    // Verificar se é área admin (liberar acesso)
    $uri = $_SERVER['REQUEST_URI'];
    if (strpos($uri, '/admin/') !== false) {
        return; // Admin sempre pode acessar
    }
    
    // Verificar IPs liberados
    if (MANUTENCAO_IPS_LIBERADOS) {
        $ips_liberados = explode(',', MANUTENCAO_IPS_LIBERADOS);
        $ip_usuario = obter_ip_usuario();
        
        if (in_array(trim($ip_usuario), array_map('trim', $ips_liberados))) {
            return; // IP liberado
        }
    }
    
    // Exibir página de manutenção
    exibir_pagina_manutencao();
}

/**
 * Obtém o IP real do usuário considerando proxies
 */
function obter_ip_usuario() {
    $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Verificar cabeçalhos de proxy
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_usuario = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip_usuario = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        // Para Cloudflare
        $ip_usuario = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    
    return trim($ip_usuario);
}

/**
 * Exibe a página de manutenção e interrompe a execução
 */
function exibir_pagina_manutencao() {
    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Definir cabeçalho HTTP 503
    http_response_code(503);
    header('Retry-After: 3600'); // Tentar novamente em 1 hora
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo MANUTENCAO_TITULO; ?> - <?php echo SITE_NOME; ?></title>
        <meta name="robots" content="noindex, nofollow">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #333;
                line-height: 1.6;
            }
            
            .container {
                background: white;
                padding: 3rem;
                border-radius: 20px;
                box-shadow: 0 25px 80px rgba(0,0,0,0.15);
                text-align: center;
                max-width: 500px;
                margin: 1rem;
                position: relative;
                overflow: hidden;
            }
            
            .container::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #667eea, #764ba2);
            }
            
            .icon {
                font-size: 4rem;
                margin-bottom: 1.5rem;
                animation: bounce 2s infinite;
            }
            
            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% {
                    transform: translateY(0);
                }
                40% {
                    transform: translateY(-10px);
                }
                60% {
                    transform: translateY(-5px);
                }
            }
            
            h1 {
                font-size: 2rem;
                margin-bottom: 1rem;
                color: #2d3748;
                font-weight: 700;
            }
            
            .message {
                font-size: 1.1rem;
                line-height: 1.6;
                margin-bottom: 2rem;
                color: #4a5568;
            }
            
            .previsao {
                background: linear-gradient(135deg, #f7fafc, #edf2f7);
                padding: 1.25rem;
                border-radius: 12px;
                margin-bottom: 2rem;
                font-weight: 600;
                color: #2d3748;
                border-left: 4px solid #667eea;
            }
            
            .contato {
                margin-top: 2rem;
                padding-top: 2rem;
                border-top: 1px solid #e2e8f0;
            }
            
            .whatsapp-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: linear-gradient(135deg, #25d366, #1da851);
                color: white;
                text-decoration: none;
                padding: 0.875rem 2rem;
                border-radius: 25px;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
            }
            
            .whatsapp-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
            }
            
            .footer {
                margin-top: 3rem;
                font-size: 0.9rem;
                color: #718096;
            }
            
            .loading-dots {
                display: inline-block;
                position: relative;
                margin-left: 0.5rem;
            }
            
            .loading-dots::after {
                content: '';
                animation: dots 1.5s infinite;
            }
            
            @keyframes dots {
                0% { content: ''; }
                25% { content: '.'; }
                50% { content: '..'; }
                75% { content: '...'; }
                100% { content: ''; }
            }
            
            @media (max-width: 600px) {
                .container {
                    padding: 2rem 1.5rem;
                    margin: 0.5rem;
                }
                
                h1 {
                    font-size: 1.5rem;
                }
                
                .icon {
                    font-size: 3rem;
                }
                
                .whatsapp-btn {
                    padding: 0.75rem 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">🔧</div>
            <h1><?php echo MANUTENCAO_TITULO; ?></h1>
            <div class="message">
                <?php echo MANUTENCAO_MENSAGEM; ?>
                <span class="loading-dots"></span>
            </div>
            
            <?php if (MANUTENCAO_PREVISAO): ?>
                <div class="previsao">
                    ⏰ <?php echo MANUTENCAO_PREVISAO; ?>
                </div>
            <?php endif; ?>
            
            <div class="contato">
                <p style="margin-bottom: 1rem; color: #4a5568;">Precisa de ajuda urgente?</p>
                <a href="https://wa.me/<?php echo MANUTENCAO_CONTATO; ?>?text=Olá! Preciso de ajuda urgente com o sistema Vinde." 
                   class="whatsapp-btn" target="_blank" rel="noopener">
                    📱 Falar no WhatsApp
                </a>
            </div>
            
            <div class="footer">
                <p><?php echo SITE_NOME; ?> - Voltaremos em breve!</p>
                <p style="margin-top: 0.5rem; font-size: 0.8rem;">
                    Página atualizada automaticamente a cada 5 minutos
                </p>
            </div>
        </div>
        
        <script>
            // Recarregar página a cada 5 minutos para verificar se manutenção acabou
            setTimeout(function() {
                location.reload();
            }, 300000);
            
            // Adicionar indicador visual de quando será a próxima verificação
            let countdown = 300; // 5 minutos em segundos
            
            function updateCountdown() {
                const minutes = Math.floor(countdown / 60);
                const seconds = countdown % 60;
                
                if (countdown <= 0) {
                    location.reload();
                    return;
                }
                
                countdown--;
                setTimeout(updateCountdown, 1000);
            }
            
            // Iniciar countdown
            updateCountdown();
        </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Verifica se o usuário atual tem permissão para acessar durante manutenção
 * @return bool
 */
function pode_acessar_durante_manutencao() {
    if (!MODO_MANUTENCAO) {
        return true;
    }
    
    // Verificar se é área admin
    $uri = $_SERVER['REQUEST_URI'];
    if (strpos($uri, '/admin/') !== false) {
        return true;
    }
    
    // Verificar IPs liberados
    if (MANUTENCAO_IPS_LIBERADOS) {
        $ips_liberados = explode(',', MANUTENCAO_IPS_LIBERADOS);
        $ip_usuario = obter_ip_usuario();
        
        if (in_array(trim($ip_usuario), array_map('trim', $ips_liberados))) {
            return true;
        }
    }
    
    return false;
}

?>
