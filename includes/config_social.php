<?php
/**
 * Configurações para redes sociais e Open Graph
 * Configure aqui os IDs e tokens das suas aplicações
 */

// Facebook App ID
// Para obter: https://developers.facebook.com/apps/
// 1. Crie uma nova aplicação
// 2. Copie o "App ID" da página principal
define('FACEBOOK_APP_ID', '889248661451051'); // Substitua pelo seu ID real

// Twitter/X Handle
define('TWITTER_HANDLE', '@vinde'); // Substitua pelo seu handle real

// Instagram (se necessário)
define('INSTAGRAM_HANDLE', '@vinde');

// Configurações gerais do site para Open Graph
define('SITE_NAME', 'Vinde - Eventos Católicos');
define('SITE_LOCALE', 'pt_BR');

// Imagens padrão para fallback
define('DEFAULT_OG_IMAGE_WIDTH', '1200');
define('DEFAULT_OG_IMAGE_HEIGHT', '630');

/**
 * Instruções para configurar Facebook App:
 * 
 * 1. Acesse: https://developers.facebook.com/
 * 2. Vá em "Meus Aplicativos" > "Criar Aplicativo"
 * 3. Escolha "Outro" > "Consumidor"
 * 4. Nome do app: "Vinde - Eventos Católicos"
 * 5. Adicione o domínio: vinde.traffego.agency
 * 6. Copie o "App ID" e substitua na constante FACEBOOK_APP_ID acima
 * 
 * Benefícios:
 * - Insights de compartilhamento
 * - Controle de como links aparecem no Facebook
 * - Evitar avisos de "propriedades ausentes"
 */
?>
