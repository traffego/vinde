<?php
// Inicialização do Sistema Vinde
// Arquivo: includes/init.php

// Definir constante do sistema
define('SISTEMA_INSCRICOES', true);

// Carregar configurações
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/debug_config.php';
// Carregar configurações locais apenas se o arquivo existir
if (file_exists(__DIR__ . '/config_local.php')) {
    require_once __DIR__ . '/config_local.php';
}

// Carregar dependências
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/qrcode.php';
require_once __DIR__ . '/qr_generator.php';
require_once __DIR__ . '/efi_bank.php';
require_once __DIR__ . '/pix_simples.php';

// Inicializar sessão
iniciar_sessao();

// Conectar ao banco de dados
try {
    conectar_banco();
} catch (Exception $e) {
    if (defined('AMBIENTE') && AMBIENTE === 'desenvolvimento') {
        die("Erro de conexão: " . $e->getMessage());
    } else {
        die("Sistema temporariamente indisponível. Tente novamente mais tarde.");
    }
}

// Funções de template
function obter_cabecalho($titulo = 'Vinde - Eventos Católicos', $pagina = 'home', $meta_tags = []) {
    $csrf_token = gerar_csrf_token();
    $mensagem = obter_mensagem();
    
    echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='csrf-token' content='{$csrf_token}'>
    <title>{$titulo}</title>
    <link rel='stylesheet' href='" . SITE_URL . "/assets/css/style.css'>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
    <meta name='description' content='Sistema de inscrições para eventos católicos'>
    <meta name='robots' content='index, follow'>
    <link rel='icon' type='image/x-icon' href='" . SITE_URL . "/assets/images/favicon.ico'>";
    
    // Adicionar meta tags personalizadas (Open Graph, Twitter, etc.)
    if (!empty($meta_tags)) {
        echo "\n    <!-- Open Graph / Social Media Meta Tags -->";
        foreach ($meta_tags as $property => $content) {
            if (strpos($property, 'twitter:') === 0) {
                echo "\n    <meta name='{$property}' content='{$content}'>";
            } else {
                echo "\n    <meta property='{$property}' content='{$content}'>";
            }
        }
    }
    
    echo "
</head>
<body class='pagina-{$pagina}'>
    <header class='header-principal'>
        <div class='container'>
            <div class='header-content'>
                <div class='logo'>
                    <a href='" . SITE_URL . "'>";
    
    // Verificar se existe logo
    $logo_path = __DIR__ . '/../assets/images/logo.png';
    if (file_exists($logo_path)) {
        echo "<img src='" . SITE_URL . "/assets/images/logo.png' alt='Vinde' class='logo-img'>
                        <span class='logo-text'>Vinde</span>";
    } else {
        echo "<span class='logo-text-only'>Vinde</span>";
    }
    
    echo "</a>
                </div>
                <nav class='nav-principal'>
                    <ul>
                        <li><a href='" . SITE_URL . "'>Eventos</a></li>
                        <li><a href='" . SITE_URL . "/contato.php'>Contato</a></li>
                        <li><a href='" . SITE_URL . "/participante' class='btn-participante'>Área do Participante</a></li>
                    </ul>
                </nav>
                <div class='header-mobile'>
                    <button class='menu-toggle' aria-label='Menu'>
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </div>
    </header>";
    
    // Exibir mensagens do sistema
    if ($mensagem) {
        echo "<div class='mensagem mensagem-{$mensagem['tipo']}'>
                <div class='container'>
                    <p>{$mensagem['texto']}</p>
                    <button class='mensagem-fechar'>&times;</button>
                </div>
              </div>";
    }
}

function obter_rodape() {
    $ano_atual = date('Y');
    $whatsapp = WHATSAPP_CONTATO;
    
    echo "<footer class='footer-principal'>
        <div class='container'>
            <div class='footer-content'>
                <div class='footer-section'>
                    <h3>Vinde</h3>
                    <p>Sistema de inscrições para eventos católicos. Conectando fiéis através da fé.</p>
                    <div class='footer-social'>
                        <a href='https://wa.me/{$whatsapp}' target='_blank' class='whatsapp-link'>
                            <i class='icon-whatsapp'></i>
                            WhatsApp
                        </a>
                    </div>
                </div>
                <div class='footer-section'>
                    <h3>Contato</h3>
                    <p>Email: " . SITE_EMAIL . "</p>
                    <p>WhatsApp: " . formatar_telefone($whatsapp) . "</p>
                </div>
                <div class='footer-section'>
                    <h3>Links Úteis</h3>
                    <ul>
                        <li><a href='" . SITE_URL . "'>Eventos</a></li>
                        <li><a href='" . SITE_URL . "/politica-privacidade.php'>Política de Privacidade</a></li>
                        <li><a href='" . SITE_URL . "/termos-uso.php'>Termos de Uso</a></li>
                    </ul>
                </div>
            </div>
            <div class='footer-bottom'>
                <p>&copy; {$ano_atual} Vinde. Todos os direitos reservados.</p>
                <p>Versão " . SISTEMA_VERSAO . "</p>
            </div>
        </div>
    </footer>
    
    <script src='" . SITE_URL . "/assets/js/main.js'></script>
</body>
</html>";
}

function obter_cabecalho_admin($titulo = 'Painel Administrativo', $pagina = 'dashboard') {
    $csrf_token = gerar_csrf_token();
    $mensagem = obter_mensagem();
    
    // Compatibilidade com sistema existente
    $nome_usuario = $_SESSION['admin_nome'] ?? $_SESSION['admin_user'] ?? 'Admin';
    $usuario_atual = ['nome' => $nome_usuario];
    
    echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='csrf-token' content='{$csrf_token}'>
    <title>{$titulo} - Vinde Admin</title>
    <link rel='stylesheet' href='" . SITE_URL . "/assets/css/style.css'>
    <link rel='stylesheet' href='" . SITE_URL . "/assets/css/admin.css'>
    <link rel='stylesheet' href='" . SITE_URL . "/assets/css/crud.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
</head>
<body class='admin-body pagina-{$pagina}'>
    <div class='admin-layout'>
        <aside class='admin-sidebar'>
            <div class='sidebar-header'>
                <a href='" . SITE_URL . "/admin/' class='sidebar-logo'>
                    <span>Vinde Admin</span>
                </a>
            </div>
            <nav class='sidebar-nav'>
                <ul>
                    <li><a href='" . SITE_URL . "/admin/' class='nav-link'><i class='icon-dashboard'></i> Dashboard</a></li>
                    <li><a href='" . SITE_URL . "/admin/eventos.php' class='nav-link'><i class='icon-calendar'></i> Eventos</a></li>
                    <li><a href='" . SITE_URL . "/admin/participantes.php' class='nav-link'><i class='icon-users'></i> Participantes</a></li>
                    <li><a href='" . SITE_URL . "/admin/checkin.php' class='nav-link'><i class='icon-qr'></i> Check-in</a></li>
                    <li><a href='" . SITE_URL . "/admin/relatorios.php' class='nav-link'><i class='icon-chart'></i> Relatórios</a></li>
                    <li><a href='" . SITE_URL . "/admin/efi_config.php' class='nav-link'><i class='icon-payment'></i> API Pagamento</a></li>
                    <li><a href='" . SITE_URL . "/admin/configuracoes.php' class='nav-link'><i class='icon-settings'></i> Configurações</a></li>
                    <li><a href='" . SITE_URL . "/admin/limpar_cache.php' class='nav-link'><i class='icon-refresh'></i> Limpar Cache</a></li>
                    <li><a href='" . SITE_URL . "/admin/logs.php' class='nav-link'><i class='icon-log'></i> Logs</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class='admin-main'>
            <header class='admin-header'>
                <div class='header-left'>
                    <button class='sidebar-toggle'>
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <h1>{$titulo}</h1>
                </div>
                <div class='header-right'>
                    <div class='user-menu'>
                        <span class='user-name'>{$usuario_atual['nome']}</span>
                        <div class='user-dropdown'>
                            <a href='" . SITE_URL . "/admin/perfil.php'>Meu Perfil</a>
                            <a href='" . SITE_URL . "' target='_blank'>Ver Site</a>
                            <a href='" . SITE_URL . "/admin/logout.php'>Sair</a>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class='admin-content'>";
    
    // Exibir mensagens do sistema
    if ($mensagem) {
        echo "<div class='admin-mensagem mensagem-{$mensagem['tipo']}'>
                <p>{$mensagem['texto']}</p>
                <button class='mensagem-fechar'>&times;</button>
              </div>";
    }
}

function obter_rodape_admin() {
    echo "</main>
        </div>
    </div>
    
    <!-- Botão flutuante para limpeza de cache -->
    <a href='" . SITE_URL . "/admin/limpar_cache.php' class='cache-float-btn' title='Limpar Cache'>🧹</a>
    
    <script src='" . SITE_URL . "/assets/js/main.js'></script>
    <script src='" . SITE_URL . "/assets/js/admin.js'></script>
</body>
</html>";
}

// Funções utilitárias de template
function exibir_loading() {
    echo "<div class='loading-overlay'>
            <div class='loading-spinner'></div>
            <p>Carregando...</p>
          </div>";
}

function exibir_erro_404() {
    http_response_code(404);
    obter_cabecalho('Página não encontrada');
    echo "<main class='container'>
            <div class='erro-404'>
                <h1>404</h1>
                <h2>Página não encontrada</h2>
                <p>A página que você procura não existe ou foi removida.</p>
                <a href='" . SITE_URL . "' class='btn btn-primary'>Voltar ao início</a>
            </div>
          </main>";
    obter_rodape();
    exit;
}

function exibir_erro_500($mensagem = 'Erro interno do servidor') {
    http_response_code(500);
    obter_cabecalho('Erro interno');
    echo "<main class='container'>
            <div class='erro-500'>
                <h1>500</h1>
                <h2>Erro interno do servidor</h2>
                <p>{$mensagem}</p>
                <a href='" . SITE_URL . "' class='btn btn-primary'>Voltar ao início</a>
            </div>
          </main>";
    obter_rodape();
    exit;
}

?> 