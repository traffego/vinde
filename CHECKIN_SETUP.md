# 📱 Sistema de Check-in com QR Code - Vinde

## 🚀 Funcionalidades Implementadas

### ✅ **Página de Check-in no Admin**
- **Localização:** `/admin/checkin.php`
- **Scanner QR Code:** Usando biblioteca jsQR
- **Check-in Manual:** Interface para confirmar presença sem QR
- **Lista de Participantes:** Visualização em tempo real
- **Filtros:** Busca por nome e status
- **Estatísticas:** Contadores de presentes/pendentes

### ✅ **API de Check-in**
- **Localização:** `/admin/api/checkin.php`
- **Endpoints:**
  - `checkin_qr` - Check-in via QR Code
  - `checkin_manual` - Check-in manual
  - `undo_checkin` - Desfazer check-in
- **Validações:** Token QR, pagamento, duplicação
- **Logs:** Sistema completo de auditoria

### ✅ **QR Code na Confirmação**
- **Dados do QR:** JSON com participante_id, token, evento_id
- **Segurança:** Token único por participante
- **Download:** Geração de PNG para salvar
- **Compartilhamento:** WhatsApp integrado

## 🛠️ Configuração

### 1. **Atualizar Banco de Dados**
Execute o script de atualização:
```sql
SOURCE database_checkin_update.sql;
```

Ou execute manualmente:
```sql
-- Criar tabela de logs do sistema
CREATE TABLE IF NOT EXISTS logs_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(50) NOT NULL,
    descricao TEXT NOT NULL,
    usuario VARCHAR(100),
    participante_id INT NULL,
    evento_id INT NULL,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_usuario (usuario),
    INDEX idx_data_hora (data_hora)
) ENGINE=InnoDB;

-- Atualizar participantes para gerar QR tokens
UPDATE participantes 
SET qr_token = CONCAT(UUID(), '-', id) 
WHERE qr_token IS NULL OR qr_token = '';
```

### 2. **Permissões de Câmera**
O scanner QR precisa de permissão para acessar a câmera:
- **HTTPS:** Recomendado para produção
- **localhost:** Funciona para desenvolvimento
- **Mobile:** Solicita permissão automaticamente

### 3. **Menu Administrativo**
O item "Check-in" já foi adicionado ao menu lateral do admin.

## 📋 Como Usar

### **1. Preparação do Evento**
1. Acesse **Admin > Check-in**
2. Selecione o evento desejado
3. Verifique a lista de participantes

### **2. Check-in via QR Code**
1. Clique em **"Iniciar Scanner"**
2. Aponte a câmera para o QR Code do participante
3. O sistema processará automaticamente
4. Confirmação instantânea na tela

### **3. Check-in Manual**
1. Cole o código QR no campo manual
2. Ou clique no botão "Check-in" ao lado do participante
3. Confirme a ação

### **4. Gestão**
- **Buscar:** Digite o nome na barra de pesquisa
- **Filtrar:** Selecione status (Todos/Presentes/Pendentes)
- **Desfazer:** Clique em "Desfazer" se necessário
- **Estatísticas:** Visualize em tempo real

## 🔒 Segurança

### **Validações Implementadas**
- ✅ Token QR único por participante
- ✅ Verificação de pagamento (eventos pagos)
- ✅ Prevenção de check-in duplicado
- ✅ Logs de auditoria completos
- ✅ Autenticação de administrador

### **Estrutura do QR Code**
```json
{
    "tipo": "checkin",
    "participante_id": 123,
    "token": "uuid-unique-token",
    "evento_id": 456,
    "nome": "Nome do Participante",
    "evento": "Nome do Evento"
}
```

## 📊 Logs e Auditoria

### **Tipos de Log**
- `checkin_realizado` - Check-in efetuado
- `checkin_desfeito` - Check-in cancelado
- `sistema_atualizado` - Atualizações do sistema

### **Informações Registradas**
- Timestamp exato
- Operador responsável
- Dados do participante
- Ação realizada

## 🎯 Fluxo Completo

1. **Participante se inscreve** → Recebe QR Code
2. **Chega no evento** → Apresenta QR Code
3. **Staff faz scanner** → Sistema valida automaticamente
4. **Check-in confirmado** → Status atualizado
5. **Relatórios** → Dados para análise

## 🔧 Troubleshooting

### **Câmera não funciona**
- Verificar permissões do navegador
- Usar HTTPS em produção
- Testar diferentes navegadores

### **QR Code inválido**
- Verificar se o token existe no banco
- Confirmar se o participante existe
- Verificar se o pagamento foi processado

### **Performance**
- Lista limitada a eventos do dia
- Indexação otimizada no banco
- Cache de participantes

## 📱 Compatibilidade

### **Navegadores Suportados**
- ✅ Chrome/Chromium 60+
- ✅ Firefox 60+
- ✅ Safari 11+
- ✅ Edge 79+

### **Dispositivos**
- ✅ Desktop (webcam)
- ✅ Tablet (câmera traseira)
- ✅ Smartphone (câmera traseira)

## 🎉 Próximos Passos

1. **Relatórios Avançados** - Gráficos de presença
2. **Notificações** - WhatsApp automático
3. **Check-out** - Controle de saída
4. **Integração** - APIs externas
5. **Offline** - Funcionar sem internet

---

**Sistema implementado com sucesso! 🚀** 