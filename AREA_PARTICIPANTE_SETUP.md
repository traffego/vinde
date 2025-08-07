# 🎫 Área do Participante - Sistema Vinde

## 📋 Visão Geral

O sistema de Área do Participante permite que os inscritos acessem uma área privada onde podem:

- ✅ Visualizar todos os eventos onde estão inscritos
- 📱 Gerar QR codes para check-in nos eventos
- 📊 Acompanhar status de inscrição e pagamento
- ℹ️ Acessar detalhes dos eventos

## 🔐 Sistema de Autenticação

### Login Seguro
- **Credenciais**: CPF + WhatsApp (dados da inscrição)
- **Validação**: Busca na base de participantes ativos
- **Sessão**: Controle de timeout e segurança

### Segurança
- Tokens CSRF para formulários
- Sanitização de dados de entrada
- Sessões com timeout automático
- Máscaras de input para CPF e WhatsApp

## 📱 Funcionalidades Principais

### 1. Dashboard de Eventos
- **Listagem**: Todos os eventos do participante
- **Status Visual**: Badges coloridos para status
- **Informações**: Data, local, valor, check-in
- **Ações Rápidas**: Ver QR Code e detalhes do evento

### 2. QR Code para Check-in
- **Geração Dinâmica**: Dados completos do participante + evento
- **Segurança**: Token único por participante
- **Download**: Possibilidade de baixar PNG
- **Compatibilidade**: Funciona com sistema de check-in existente

### 3. Status de Participação
- **Inscrição**: Inscrito, Pago, Presente, Cancelado
- **Pagamento**: Pendente, Pago, Gratuito, Cancelado
- **Check-in**: Timestamp quando realizado

## 🗂️ Estrutura de Arquivos

```
participante/
├── login.php          # Página de login
├── index.php          # Dashboard principal
├── logout.php         # Logout do participante
└── qr.php             # API para gerar QR codes

includes/
└── auth_participante.php  # Funções de autenticação
```

## 🛠️ Configuração e Instalação

### 1. Não é necessária instalação adicional
O sistema utiliza a estrutura existente do Vinde.

### 2. Dependências
- Sistema base Vinde funcionando
- Tabela `participantes` com campo `qr_token`
- JavaScript: QRCode.js (CDN)

### 3. Permissões
Certifique-se que o diretório `participante/` é acessível via web.

## 🔄 Fluxo de Uso

### 1. Acesso
```
Participante → /participante/login.php → CPF + WhatsApp → Dashboard
```

### 2. Visualização de Eventos
```
Dashboard → Lista de eventos → Status e informações → Ações disponíveis
```

### 3. Geração de QR Code
```
Botão "Ver QR Code" → API gera dados → QRCode.js renderiza → Modal exibe
```

### 4. Check-in
```
QR Code → Scanner admin → Validação → Check-in registrado
```

## 📊 Estrutura dos Dados do QR

```json
{
  "type": "checkin",
  "participante_id": 123,
  "token": "abc123def456...",
  "evento_id": 45,
  "evento_nome": "Nome do Evento",
  "participante_nome": "Nome do Participante",
  "data_evento": "2024-01-15",
  "timestamp": 1705123456
}
```

## 🎨 Interface e UX

### Design Responsivo
- **Mobile-first**: Otimizado para dispositivos móveis
- **Cards**: Layout em cards para melhor organização
- **Cores**: Sistema de cores consistente com o Vinde
- **Tipografia**: Inter font para melhor legibilidade

### Elementos Visuais
- **Status Badges**: Cores diferentes para cada status
- **Ícones**: Emojis para ações e informações
- **Modais**: Interface limpa para QR codes
- **Gradientes**: Fundos atrativos e modernos

## 🔧 Funções Principais

### Autenticação
```php
participante_esta_logado()           // Verifica se está logado
participante_fazer_login($cpf, $whatsapp)  // Fazer login
participante_fazer_logout()          // Fazer logout
obter_participante_logado()          // Dados do participante
requer_login_participante()          // Middleware de proteção
```

### Dados
```php
obter_eventos_participante($id)      // Lista eventos do participante
gerar_qr_checkin($participante_id, $evento_id)  // Gera QR para check-in
```

### Formatação
```php
formatar_status($status)             // Badge de status da participação
formatar_status_pagamento($status, $valor)  // Badge de pagamento
```

## 🛡️ Segurança

### Validações
- **Login**: CPF e WhatsApp obrigatórios
- **Acesso**: Verificação de sessão ativa
- **QR Code**: Validação de proprietário
- **CSRF**: Proteção contra ataques

### Logs e Auditoria
- Login/logout registrados automaticamente
- Erros logados no sistema
- Acesso aos QR codes controlado

## 📱 Compatibilidade

### Browsers
- Chrome/Edge: ✅ Totalmente compatível
- Firefox: ✅ Totalmente compatível  
- Safari: ✅ Totalmente compatível
- Mobile: ✅ Responsivo e touch-friendly

### Dispositivos
- **Desktop**: Interface ampla com cards em grid
- **Tablet**: Layout adaptativo
- **Mobile**: Interface otimizada, uma coluna

## 🚀 Próximos Passos

1. **Notificações**: Sistema de lembretes por email/WhatsApp
2. **Histórico**: Histórico completo de participações
3. **Certificados**: Download de certificados quando disponíveis
4. **Avaliações**: Sistema de feedback dos eventos

## 📞 Suporte

Para suporte técnico ou dúvidas sobre o sistema:
- Verifique os logs de erro do PHP
- Confirme permissões de arquivo
- Valide configuração do banco de dados

---

**Sistema desenvolvido para Vinde - Eventos Católicos** 🙏 