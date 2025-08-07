# 🙏 Vinde - Sistema de Eventos Católicos

Sistema completo de gestão de inscrições para eventos católicos desenvolvido em **PHP procedural puro** com arquitetura LAMP (Linux, Apache, MySQL, PHP).

## ✨ Características Principais

- **100% PHP Procedural** - Sem frameworks ou dependências externas
- **Design Católico Moderno** - Interface responsiva com cores azul e dourado
- **Sistema PIX Integrado** - Geração automática de QR codes para pagamento
- **QR Code para Check-in** - Sistema completo de presença
- **Painel Administrativo** - Dashboard completo com estatísticas
- **Segurança Avançada** - Proteção CSRF, XSS, SQL Injection
- **Relatórios Completos** - PDF e Excel
- **Mobile First** - Totalmente responsivo

## 🚀 Funcionalidades

### Frontend Público
- ✅ Landing page com lista de eventos
- ✅ Página individual do evento com detalhes completos
- ✅ Sistema de inscrição com validação em tempo real
- ✅ Processo de pagamento PIX com QR Code dinâmico
- ✅ Confirmação com QR Code para check-in
- ✅ Design responsivo católico (azul #1e40af, dourado #f59e0b)

### Painel Administrativo
- ✅ Login seguro com controle de sessão
- ✅ Dashboard com estatísticas em tempo real
- ✅ Gestão completa de eventos (CRUD)
- ✅ Gestão de participantes
- ✅ Sistema de check-in via QR Code
- ✅ Relatórios em PDF e Excel
- ✅ Logs completos de atividades
- ✅ Sistema de permissões (admin/operador)

### Funcionalidades Avançadas
- ✅ Geração de QR Code PIX e check-in
- ✅ Scanner QR Code via WebRTC
- ✅ Notificações WhatsApp (simuladas)
- ✅ Backup automático do banco
- ✅ Cache e otimização de performance
- ✅ URLs amigáveis (.htaccess)

## 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 8.0+ (Procedural Puro)
- **Banco de Dados**: MySQL 8.0+ (InnoDB)
- **Frontend**: HTML5, CSS3 (Grid/Flexbox), JavaScript (Vanilla)
- **Servidor**: Apache 2.4+ com mod_rewrite
- **Segurança**: bcrypt, CSRF tokens, Prepared Statements

## 📋 Requisitos do Sistema

### Servidor
- **SO**: Linux (Ubuntu 20.04+ recomendado)
- **Servidor Web**: Apache 2.4+ com mod_rewrite habilitado
- **PHP**: 8.0+ com extensões:
  - mysqli ou PDO MySQL
  - gd (manipulação de imagens)
  - mbstring (strings multibyte)
  - json (manipulação JSON)
- **MySQL**: 8.0+ ou MariaDB 10.5+

### Módulos Apache Necessários
```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod deflate
sudo a2enmod expires
```

## 🔧 Instalação

### 1. Configuração do Servidor

```bash
# Instalar LAMP Stack (Ubuntu/Debian)
sudo apt update
sudo apt install apache2 mysql-server php8.0 php8.0-mysql php8.0-gd php8.0-mbstring php8.0-json

# Configurar MySQL
sudo mysql_secure_installation

# Configurar Apache
sudo a2enmod rewrite headers deflate expires
sudo systemctl restart apache2
```

### 2. Configuração do Banco de Dados

```bash
# Acessar MySQL
sudo mysql -u root -p

# Criar banco e usuário
CREATE DATABASE eventos_catolicos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vinde_user'@'localhost' IDENTIFIED BY 'sua_senha_forte';
GRANT ALL PRIVILEGES ON eventos_catolicos.* TO 'vinde_user'@'localhost';
FLUSH PRIVILEGES;
exit;

# Importar estrutura do banco
mysql -u vinde_user -p eventos_catolicos < database.sql
```

### 3. Configuração dos Arquivos

```bash
# Fazer download/clone do projeto
cd /var/www/html/
# (copiar arquivos do sistema para este diretório)

# Configurar permissões
sudo chown -R www-data:www-data /var/www/html/vinde/
sudo chmod -R 755 /var/www/html/vinde/
sudo chmod -R 777 /var/www/html/vinde/uploads/
sudo chmod -R 777 /var/www/html/vinde/backup/
```

### 4. Configuração do Sistema

Editar o arquivo `includes/config.php`:

```php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'eventos_catolicos');
define('DB_USER', 'vinde_user');
define('DB_PASS', 'sua_senha_forte');

// Configurações do Site
define('SITE_URL', 'http://seu-dominio.com');
define('SITE_NOME', 'Sua Paróquia - Eventos');
define('SITE_EMAIL', 'contato@suaparoquia.com.br');

// Configurações PIX
define('PIX_CHAVE', 'sua_chave_pix');
define('PIX_NOME', 'NOME DA PAROQUIA');
define('PIX_CIDADE', 'SUA CIDADE');

// WhatsApp
define('WHATSAPP_CONTATO', 'seu_numero_whatsapp');
```

### 5. Configuração Virtual Host (Opcional)

Criar arquivo `/etc/apache2/sites-available/vinde.conf`:

```apache
<VirtualHost *:80>
    ServerName vinde.local
    DocumentRoot /var/www/html/vinde
    
    <Directory /var/www/html/vinde>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/vinde_error.log
    CustomLog ${APACHE_LOG_DIR}/vinde_access.log combined
</VirtualHost>
```

```bash
# Habilitar site
sudo a2ensite vinde.conf
sudo systemctl reload apache2
```

## 🔐 Configuração de Segurança

### 1. HTTPS (Produção)
```bash
# Instalar Certbot
sudo apt install certbot python3-certbot-apache

# Obter certificado SSL
sudo certbot --apache -d seu-dominio.com
```

### 2. Configurações PHP (php.ini)
```ini
; Segurança
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Upload
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300

; Sessão
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### 3. Firewall
```bash
# Configurar UFW
sudo ufw enable
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
```

## 📱 Primeiro Acesso

### Login Administrativo
- **URL**: `http://seu-dominio.com/admin/`
- **Usuário**: `admin`
- **Senha**: `admin123`

> ⚠️ **IMPORTANTE**: Altere a senha padrão imediatamente após o primeiro login!

### Configurações Iniciais
1. Acesse **Admin > Configurações**
2. Configure dados da paróquia/organização
3. Configure chave PIX para pagamentos
4. Configure WhatsApp para contato
5. Teste criação de um evento

## 🎨 Personalização

### Cores e Branding
Editar arquivo `assets/css/style.css`:

```css
:root {
    --cor-primaria: #1e40af;        /* Azul principal */
    --cor-dourado: #f59e0b;         /* Dourado */
    --cor-primaria-hover: #1d4ed8;  /* Azul hover */
    /* ... outras variáveis */
}
```

### Logo
- Substituir `assets/images/logo.png`
- Tamanho recomendado: 200x60px
- Formato: PNG com fundo transparente

### Favicon
- Substituir `assets/images/favicon.ico`
- Tamanho: 32x32px

## 📊 Backup e Manutenção

### Backup Automático
O sistema possui backup automático configurado. Para backup manual:

```bash
# Backup do banco de dados
mysqldump -u vinde_user -p eventos_catolicos > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup dos arquivos
tar -czf backup_arquivos_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/vinde/
```

### Limpeza de Logs
```bash
# Limpar logs antigos (manter últimos 30 dias)
find /var/log/ -name "*.log" -type f -mtime +30 -delete
```

### Atualização
1. Fazer backup completo
2. Substituir arquivos (exceto `includes/config.php`)
3. Executar scripts de migração se houver
4. Testar funcionalidades

## 🐛 Solução de Problemas

### Erro de Conexão com Banco
```bash
# Verificar status MySQL
sudo systemctl status mysql

# Verificar conectividade
mysql -u vinde_user -p -e "SELECT 1;"
```

### Erro de Permissões
```bash
# Corrigir permissões
sudo chown -R www-data:www-data /var/www/html/vinde/
sudo chmod -R 755 /var/www/html/vinde/
sudo chmod -R 777 /var/www/html/vinde/uploads/
```

### Erro 500 - Internal Server Error
1. Verificar logs: `tail -f /var/log/apache2/error.log`
2. Verificar sintaxe PHP: `php -l arquivo.php`
3. Verificar .htaccess

### QR Code não funciona
1. Verificar extensão GD: `php -m | grep gd`
2. Verificar permissões de escrita
3. Testar geração manual

## 📞 Suporte

### Logs do Sistema
- **Apache**: `/var/log/apache2/`
- **PHP**: `/var/log/php_errors.log`
- **Sistema**: Admin > Logs

### Debug Mode
Para desenvolvimento, adicionar em `config.php`:
```php
define('AMBIENTE', 'desenvolvimento');
```

### Comunidade
- 📧 Email: suporte@vinde.com.br
- 📱 WhatsApp: (11) 99999-9999
- 🌐 Site: https://vinde.com.br

## 📄 Licença

Este projeto está licenciado sob a Licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.

## 🙏 Contribuindo

1. Faça um Fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📈 Roadmap

### Versão 1.1
- [ ] Integração com API do WhatsApp
- [ ] Sistema de emails automáticos
- [ ] Certificados digitais
- [ ] App mobile

### Versão 1.2
- [ ] Pagamento com cartão
- [ ] Sistema de avaliações
- [ ] Integração com calendário
- [ ] Multi-idiomas

---

**Desenvolvido com ❤️ para a comunidade católica**

*"Vinde a mim, todos os que estais cansados e oprimidos, e eu vos aliviarei." - Mateus 11:28* 
