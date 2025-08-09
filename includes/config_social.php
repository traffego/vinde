<?php
/**
 * Configurações para redes sociais e Open Graph
 * Configure aqui os IDs e tokens das suas aplicações
 */

// Facebook App ID
// Para obter: https://developers.facebook.com/apps/
// 1. Crie uma nova aplicação
// 2. Copie o "App ID" da página principal
// TEMPORÁRIO: Comentado até configurar app real
// define('FACEBOOK_APP_ID', 'SEU_ID_AQUI');
define('FACEBOOK_APP_ID', false); // Desabilitado temporariamente

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
 * 📱 INSTRUÇÕES PARA CONFIGURAR FACEBOOK APP:
 * 
 * 🔴 SITUAÇÃO ATUAL: Facebook App ID está DESABILITADO
 * Motivo: Evitar erro "ID do aplicativo inválido" no Facebook Debugger
 * 
 * ✅ PARA ATIVAR (OPCIONAL):
 * 1. Acesse: https://developers.facebook.com/
 * 2. Clique em "Meus Aplicativos" > "Criar Aplicativo"
 * 3. Escolha tipo: "Outro" > "Consumidor"
 * 4. Nome do app: "Vinde - Eventos Católicos"
 * 5. Em "Configurações Básicas":
 *    - Adicione domínio: vinde.traffego.agency
 *    - URL da política de privacidade: https://vinde.traffego.agency/privacidade
 * 6. Copie o "App ID" (números como 123456789012345)
 * 7. Substitua 'false' por 'SEU_APP_ID_AQUI' na linha 13 acima
 * 
 * 💡 BENEFÍCIOS:
 * - Insights detalhados de compartilhamento
 * - Controle total sobre como links aparecem no Facebook
 * - Analytics de engajamento social
 * - Recurso de "Login com Facebook" (futuro)
 * 
 * ⚠️ IMPORTANTE: Open Graph funciona PERFEITAMENTE sem App ID!
 * O fb:app_id é apenas para recursos avançados.
 */
?>
