-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           10.4.32-MariaDB - mariadb.org binary distribution
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Copiando estrutura para procedure eventos_catolicos.BackupParticipantesEvento
DELIMITER //
CREATE PROCEDURE `BackupParticipantesEvento`(IN evento_id INT)
BEGIN
    SELECT 
        p.*,
        e.nome as evento_nome,
        pg.status as status_pagamento,
        pg.valor as valor_pago
    FROM participantes p
    INNER JOIN eventos e ON p.evento_id = e.id
    LEFT JOIN pagamentos pg ON p.id = pg.participante_id
    WHERE p.evento_id = evento_id
    ORDER BY p.nome;
END//
DELIMITER ;

-- Copiando estrutura para tabela eventos_catolicos.configuracoes
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`),
  KEY `idx_chave` (`chave`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.configuracoes: ~22 rows (aproximadamente)
REPLACE INTO `configuracoes` (`id`, `chave`, `valor`, `descricao`, `atualizado_em`) VALUES
	(1, 'site_nome', 'Vinde - Eventos Católicos', 'Nome do site', '2025-08-07 16:05:43'),
	(2, 'site_email', 'contato@vinde.com.br', 'Email principal', '2025-08-07 16:05:43'),
	(3, 'site_telefone', '(11) 99999-9999', 'Telefone para contato', '2025-08-07 16:05:43'),
	(4, 'whatsapp_contato', '5511999999999', 'WhatsApp para contato', '2025-08-07 16:05:43'),
	(5, 'pix_chave', '12345678901', 'Chave PIX para pagamentos', '2025-08-07 16:05:43'),
	(6, 'pix_nome', 'PAROQUIA SAO JOSE', 'Nome do beneficiário PIX', '2025-08-07 16:05:43'),
	(7, 'pix_cidade', 'SAO PAULO', 'Cidade do beneficiário PIX', '2025-08-07 16:05:43'),
	(8, 'backup_automatico', '1', 'Backup automático ativado', '2025-08-07 16:05:43'),
	(9, 'manutencao_modo', '0', 'Modo manutenção', '2025-08-07 16:05:43'),
	(10, 'registrations_enabled', '1', 'Inscrições habilitadas', '2025-08-07 16:05:43'),
	(11, 'max_eventos_por_pagina', '12', 'Máximo de eventos por página', '2025-08-07 16:05:43'),
	(12, 'timezone', 'America/Sao_Paulo', 'Fuso horário do sistema', '2025-08-07 16:05:43'),
	(13, 'email_smtp_host', '', 'Servidor SMTP para emails', '2025-08-07 16:05:43'),
	(14, 'email_smtp_port', '587', 'Porta SMTP', '2025-08-07 16:05:43'),
	(15, 'email_smtp_user', '', 'Usuário SMTP', '2025-08-07 16:05:43'),
	(16, 'email_smtp_pass', '', 'Senha SMTP', '2025-08-07 16:05:43'),
	(17, 'google_analytics_id', '', 'ID do Google Analytics', '2025-08-07 16:05:43'),
	(18, 'facebook_pixel_id', '', 'ID do Facebook Pixel', '2025-08-07 16:05:43'),
	(19, 'efi_webhook_url', 'http://localhost/vinde/webhook_efi.php', 'URL do webhook para notificações EFI Bank', '2025-08-07 17:27:50'),
	(20, 'efi_ambiente', 'producao', 'Ambiente EFI (desenvolvimento|producao)', '2025-08-07 17:27:50'),
	(21, 'efi_ativo', '1', 'EFI Bank ativo (0=inativo, 1=ativo)', '2025-08-07 17:27:50'),
	(22, 'pix_ativo', '1', NULL, '2025-08-07 19:38:37');

-- Copiando estrutura para tabela eventos_catolicos.efi_logs
CREATE TABLE IF NOT EXISTS `efi_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('auth','cobranca','webhook','consulta','erro') NOT NULL,
  `txid` varchar(35) DEFAULT NULL,
  `participante_id` int(11) DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `http_code` int(11) DEFAULT NULL,
  `mensagem` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_txid` (`txid`),
  KEY `idx_participante` (`participante_id`),
  KEY `idx_criado` (`criado_em`),
  CONSTRAINT `efi_logs_ibfk_1` FOREIGN KEY (`participante_id`) REFERENCES `participantes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.efi_logs: ~0 rows (aproximadamente)

-- Copiando estrutura para procedure eventos_catolicos.EstatisticasGerais
DELIMITER //
CREATE PROCEDURE `EstatisticasGerais`()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM eventos WHERE status = 'ativo') as eventos_ativos,
        (SELECT COUNT(*) FROM participantes WHERE status != 'cancelado') as total_participantes,
        (SELECT COUNT(*) FROM participantes WHERE status = 'pago') as participantes_pagos,
        (SELECT SUM(valor) FROM pagamentos WHERE status = 'pago') as receita_total,
        (SELECT COUNT(*) FROM eventos WHERE data_inicio >= CURDATE()) as eventos_futuros;
END//
DELIMITER ;

-- Copiando estrutura para tabela eventos_catolicos.eventos
CREATE TABLE IF NOT EXISTS `eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `descricao_completa` text DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `horario_inicio` time DEFAULT NULL,
  `horario_fim` time DEFAULT NULL,
  `local` varchar(200) NOT NULL,
  `endereco` text DEFAULT NULL,
  `cidade` varchar(100) NOT NULL,
  `estado` varchar(2) DEFAULT 'SP',
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `limite_participantes` int(11) NOT NULL DEFAULT 100,
  `tipo` enum('presencial','online','hibrido') DEFAULT 'presencial',
  `status` enum('ativo','inativo','finalizado','esgotado') DEFAULT 'ativo',
  `programacao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`programacao`)),
  `inclui` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`inclui`)),
  `imagem` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_data_inicio` (`data_inicio`),
  KEY `idx_cidade` (`cidade`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_eventos_data_status` (`data_inicio`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.eventos: ~3 rows (aproximadamente)
REPLACE INTO `eventos` (`id`, `nome`, `slug`, `descricao`, `descricao_completa`, `data_inicio`, `data_fim`, `horario_inicio`, `horario_fim`, `local`, `endereco`, `cidade`, `estado`, `valor`, `limite_participantes`, `tipo`, `status`, `programacao`, `inclui`, `imagem`, `criado_em`, `atualizado_em`) VALUES
	(1, 'Retiro Espiritual de Advento', 'retiro-espiritual-de-advento', 'Um retiro especial para se preparar para o Natal', 'Um fim de semana de profunda reflexão e oração para se preparar adequadamente para a chegada do Menino Jesus. Momentos de adoração, palestras inspiradoras e comunhão fraterna.', '2025-09-24', '2025-09-30', '08:00:00', '18:00:00', 'Centro de Retiros São Francisco', 'Rua das Oliveiras, 123', 'São Paulo', 'SP', 150.00, 50, 'presencial', 'ativo', '[{"horario":"08:00","titulo":"Chegada e Caf\\u00e9 da Manh\\u00e3","descricao":"Acolhida dos participantes","palestrante":""},{"horario":"09:00","titulo":"Abertura e Ora\\u00e7\\u00e3o Inicial","descricao":"","palestrante":"Pe. Jo\\u00e3o Silva"},{"horario":"10:00","titulo":"Palestra: O Sentido do Advento","descricao":"","palestrante":"Pe. Jo\\u00e3o Silva"},{"horario":"11:30","titulo":"Adora\\u00e7\\u00e3o ao Sant\\u00edssimo","descricao":"Momento de ora\\u00e7\\u00e3o silenciosa","palestrante":""},{"horario":"12:30","titulo":"Almo\\u00e7o","descricao":"","palestrante":""},{"horario":"14:00","titulo":"Palestra: Maria, M\\u00e3e do Salvador","descricao":"","palestrante":"Irm\\u00e3 Maria Jos\\u00e9"},{"horario":"15:30","titulo":"Grupos de Partilha","descricao":"Reflex\\u00e3o em pequenos grupos","palestrante":""},{"horario":"16:30","titulo":"Santa Missa","descricao":"","palestrante":"Pe. Jo\\u00e3o Silva"},{"horario":"18:00","titulo":"Jantar e Confraterniza\\u00e7\\u00e3o","descricao":"","palestrante":""}]', '["Todas as refei\\u00e7\\u00f5es (caf\\u00e9, almo\\u00e7o, jantar)\\r","Material de apoio\\r","Certificado de participa\\u00e7\\u00e3o\\r","Lembran\\u00e7a do retiro"]', NULL, '2025-08-07 16:05:43', '2025-08-07 19:36:00'),
	(2, 'Encontro de Jovens Católicos', 'encontro-jovens-2024', 'Encontro para jovens de 16 a 30 anos', 'Um dia especial para jovens que desejam aprofundar sua fé e conhecer outros jovens católicos. Atividades dinâmicas, música, testemunhos e muito mais.', '2024-12-28', '2024-12-28', '14:00:00', '22:00:00', 'Paróquia São José', 'Av. Paulista, 1000', 'São Paulo', 'SP', 30.00, 100, 'presencial', 'ativo', '[\r\n    {"horario": "14:00", "titulo": "Chegada e Credenciamento", "descricao": ""},\r\n    {"horario": "14:30", "titulo": "Dinâmica de Apresentação", "descricao": "Conhecendo uns aos outros"},\r\n    {"horario": "15:30", "titulo": "Palestra: Juventude e Fé", "palestrante": "Pe. Carlos Santos"},\r\n    {"horario": "16:30", "titulo": "Lanche", "descricao": ""},\r\n    {"horario": "17:00", "titulo": "Testemunhos de Jovens", "descricao": "Histórias de conversão"},\r\n    {"horario": "18:00", "titulo": "Adoração Musical", "descricao": "Momento de oração com música"},\r\n    {"horario": "19:00", "titulo": "Santa Missa", "palestrante": "Pe. Carlos Santos"},\r\n    {"horario": "20:00", "titulo": "Jantar", "descricao": ""},\r\n    {"horario": "21:00", "titulo": "Show Musical", "descricao": "Banda católica local"}\r\n]', '[\r\n    "Lanche da tarde",\r\n    "Jantar",\r\n    "Material de apoio",\r\n    "Camiseta do evento"\r\n]', NULL, '2025-08-07 16:05:43', '2025-08-07 16:05:43'),
	(3, 'Palestra sobre Família Cristã', 'palestra-familia-crista', 'Reflexões sobre os valores familiares cristãos', 'Uma noite de reflexão sobre como viver os valores cristãos no ambiente familiar, fortalecendo os laços e a fé em família.', '2025-01-15', '2025-01-15', '19:30:00', '21:30:00', 'Salão Paroquial São Pedro', 'Rua da Igreja, 50', 'Santos', 'SP', 0.00, 80, 'presencial', 'ativo', '[\r\n    {"horario": "19:30", "titulo": "Recepção", "descricao": ""},\r\n    {"horario": "20:00", "titulo": "Palestra: A Família Cristã Hoje", "palestrante": "Dr. Maria Fernanda Lima"},\r\n    {"horario": "21:00", "titulo": "Perguntas e Respostas", "descricao": ""},\r\n    {"horario": "21:15", "titulo": "Oração Final", "descricao": ""},\r\n    {"horario": "21:30", "titulo": "Confraternização", "descricao": "Café e bolo"}\r\n]', '[\r\n    "Material de apoio",\r\n    "Certificado de participação",\r\n    "Lanche ao final"\r\n]', NULL, '2025-08-07 16:05:43', '2025-08-07 16:05:43');

-- Copiando estrutura para tabela eventos_catolicos.logs_atividades
CREATE TABLE IF NOT EXISTS `logs_atividades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_acao` (`acao`),
  KEY `idx_logs_usuario_timestamp` (`usuario`,`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.logs_atividades: ~65 rows (aproximadamente)
REPLACE INTO `logs_atividades` (`id`, `usuario`, `acao`, `detalhes`, `ip`, `user_agent`, `timestamp`) VALUES
	(1, 'sistema', 'banco_criado', 'Banco de dados eventos_catolicos criado com sucesso', NULL, NULL, '2025-08-07 16:05:44'),
	(2, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:08:45'),
	(3, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:08:58'),
	(4, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:14:27'),
	(5, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:14:31'),
	(6, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:14:55'),
	(7, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:24:29'),
	(8, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:24:36'),
	(9, 'sistema', 'senha_admin_atualizada', 'Senha do administrador atualizada para hash correto', NULL, NULL, '2025-08-07 16:25:12'),
	(10, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:25:39'),
	(11, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:25:46'),
	(12, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:26:53'),
	(13, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:26:59'),
	(14, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:28:37'),
	(15, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:28:38'),
	(16, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:28:38'),
	(17, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:28:38'),
	(18, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:28:38'),
	(19, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:28:43'),
	(20, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:29:20'),
	(21, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:30:57'),
	(22, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:31:08'),
	(23, 'admin', 'login_realizado', 'Usuário: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 16:33:50'),
	(24, 'admin', 'efi_config_atualizada', 'Configurações EFI Bank atualizadas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 17:27:50'),
	(25, 'admin', 'logout_realizado', 'Usuário: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 18:35:12'),
	(26, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 18:35:17'),
	(27, 'admin', 'login_realizado', 'Usuário: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 18:35:22'),
	(28, 'admin', 'efi_config_atualizada', 'Configurações EFI Bank e credenciais atualizadas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 18:36:53'),
	(29, 'admin', 'efi_config_atualizada', 'Configurações EFI Bank e credenciais atualizadas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 18:44:07'),
	(30, 'admin', 'evento_editado', 'Evento: Retiro Espiritual de Advento (ID: 1)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 19:36:00'),
	(31, 'sistema', 'participante_inscrito', 'Participante: Jonathas Quintanilha | Evento ID: 1', NULL, NULL, '2025-08-07 19:59:58'),
	(32, 'sistema', 'pagamento_criado', 'Participante ID: 1 | Valor: 150.00', NULL, NULL, '2025-08-07 20:00:00'),
	(33, 'sistema', 'inscricao_criada', 'Participante: Jonathas Quintanilha - Evento ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 20:00:00'),
	(34, 'sistema', 'status_pagamento_alterado', 'Pagamento ID: 1 | Status: pendente -> pago', NULL, NULL, '2025-08-07 20:00:21'),
	(35, 'sistema', 'status_participante_alterado', 'Participante: Jonathas Quintanilha | Status: inscrito -> pago', NULL, NULL, '2025-08-07 20:00:21'),
	(36, 'sistema', 'pagamento_confirmado', 'Participante: Retiro Espiritual de Advento - Valor: 150.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 20:00:21'),
	(37, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: \r\n🎉 Pagamento confirmado!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSeu pagamento foi confirmado c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 20:00:21'),
	(38, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 20:00:21'),
	(39, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 20:05:37'),
	(40, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 20:05:42'),
	(41, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 20:05:43'),
	(42, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:21:59'),
	(43, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:27:16'),
	(44, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:34:08'),
	(45, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:43'),
	(46, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:54'),
	(47, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:54'),
	(48, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:58'),
	(49, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:58'),
	(50, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:58'),
	(51, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:59'),
	(52, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:59'),
	(53, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:59'),
	(54, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:39:59'),
	(55, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:44:53'),
	(56, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 20:50:15'),
	(57, 'admin', 'logout_realizado', 'Usuário: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 21:00:56'),
	(58, 'sistema', 'tentativa_login_falhou', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 21:00:58'),
	(59, 'admin', 'login_realizado', 'Usuário: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 21:01:03'),
	(60, 'admin', 'logout_realizado', 'Usuário: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 21:05:55'),
	(61, 'admin', 'login_realizado', 'Usuário: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 21:06:02'),
	(62, 'sistema', 'whatsapp_enviado', 'Para: 21993652605 | Mensagem: 🎉 Inscrição Confirmada!\r\n\r\nOlá Retiro Espiritual de Advento,\r\n\r\nSua inscrição foi confirmada', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 21:20:19'),
	(63, 'sistema', 'participante_inscrito', 'Participante: Miltom Campos | Evento ID: 1', NULL, NULL, '2025-08-07 21:23:09'),
	(64, 'sistema', 'pagamento_criado', 'Participante ID: 2 | Valor: 150.00', NULL, NULL, '2025-08-07 21:23:11'),
	(65, 'sistema', 'inscricao_criada', 'Participante: Miltom Campos - Evento ID: 1', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-08-07 21:23:12');

-- Copiando estrutura para tabela eventos_catolicos.newsletter
CREATE TABLE IF NOT EXISTS `newsletter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.newsletter: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela eventos_catolicos.pagamentos
CREATE TABLE IF NOT EXISTS `pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participante_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `pix_codigo` text DEFAULT NULL,
  `pix_qr_code` text DEFAULT NULL,
  `status` enum('pendente','pago','cancelado','estornado') DEFAULT 'pendente',
  `metodo` enum('pix','dinheiro','transferencia') DEFAULT 'pix',
  `comprovante` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `pago_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pix_txid` varchar(35) DEFAULT NULL COMMENT 'Transaction ID da EFI Bank',
  `pix_loc_id` varchar(77) DEFAULT NULL COMMENT 'Location ID da cobrança PIX',
  `pix_qrcode_url` text DEFAULT NULL COMMENT 'URL do QR Code da EFI',
  `pix_qrcode_data` text DEFAULT NULL COMMENT 'Dados do QR Code PIX',
  `pix_expires_at` timestamp NULL DEFAULT NULL COMMENT 'Data de expiração do PIX',
  PRIMARY KEY (`id`),
  KEY `idx_participante` (`participante_id`),
  KEY `idx_status` (`status`),
  KEY `idx_pago_em` (`pago_em`),
  KEY `idx_pagamentos_status_data` (`status`,`pago_em`),
  KEY `idx_pix_txid` (`pix_txid`),
  CONSTRAINT `pagamentos_ibfk_1` FOREIGN KEY (`participante_id`) REFERENCES `participantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.pagamentos: ~2 rows (aproximadamente)
REPLACE INTO `pagamentos` (`id`, `participante_id`, `valor`, `pix_codigo`, `pix_qr_code`, `status`, `metodo`, `comprovante`, `observacoes`, `pago_em`, `criado_em`, `atualizado_em`, `pix_txid`, `pix_loc_id`, `pix_qrcode_url`, `pix_qrcode_data`, `pix_expires_at`) VALUES
	(1, 1, 150.00, NULL, NULL, 'pago', 'pix', NULL, NULL, '2025-08-07 20:00:21', '2025-08-07 20:00:00', '2025-08-07 20:00:21', 'VINDE20250807165958000001', NULL, 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=00020101021226660014BR.GOV.BCB.PIX0111123456789010229Inscricao%3A++-+Jonathas+Quinta5204000053039865406150.005802BR5917PAROQUIA+SAO+JOSE6009SAO+PAULO62290525VINDE2025080716595800000163047CB5', '00020101021226660014BR.GOV.BCB.PIX0111123456789010229Inscricao:  - Jonathas Quinta5204000053039865406150.005802BR5917PAROQUIA SAO JOSE6009SAO PAULO62290525VINDE2025080716595800000163047CB5', '2025-08-07 21:00:00'),
	(2, 2, 150.00, NULL, NULL, 'pendente', 'pix', NULL, NULL, NULL, '2025-08-07 21:23:11', '2025-08-07 21:23:11', 'VINDE20250807182309000002', NULL, 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=00020101021226640014BR.GOV.BCB.PIX0111123456789010227Inscricao%3A++-+Miltom+Campos5204000053039865406150.005802BR5917PAROQUIA+SAO+JOSE6009SAO+PAULO62290525VINDE2025080718230900000263046B2D', '00020101021226640014BR.GOV.BCB.PIX0111123456789010227Inscricao:  - Miltom Campos5204000053039865406150.005802BR5917PAROQUIA SAO JOSE6009SAO PAULO62290525VINDE2025080718230900000263046B2D', '2025-08-07 22:23:11');

-- Copiando estrutura para tabela eventos_catolicos.participantes
CREATE TABLE IF NOT EXISTS `participantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evento_id` int(11) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `whatsapp` varchar(15) NOT NULL,
  `instagram` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `idade` int(11) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `estado` varchar(2) DEFAULT 'SP',
  `tipo` enum('normal','cortesia') DEFAULT 'normal',
  `status` enum('inscrito','pago','presente','cancelado') DEFAULT 'inscrito',
  `qr_token` varchar(255) DEFAULT NULL,
  `checkin_timestamp` timestamp NULL DEFAULT NULL,
  `checkin_operador` varchar(50) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpf` (`cpf`),
  UNIQUE KEY `qr_token` (`qr_token`),
  KEY `idx_evento` (`evento_id`),
  KEY `idx_cpf` (`cpf`),
  KEY `idx_status` (`status`),
  KEY `idx_qr_token` (`qr_token`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_participantes_evento_status` (`evento_id`,`status`),
  CONSTRAINT `participantes_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.participantes: ~2 rows (aproximadamente)
REPLACE INTO `participantes` (`id`, `evento_id`, `nome`, `cpf`, `whatsapp`, `instagram`, `email`, `idade`, `cidade`, `estado`, `tipo`, `status`, `qr_token`, `checkin_timestamp`, `checkin_operador`, `observacoes`, `criado_em`, `atualizado_em`) VALUES
	(1, 1, 'Jonathas Quintanilha', '12255175754', '21993652605', 'garrezinho', 'traffego.mkt@gmail.com', 39, 'Queimados', 'RJ', 'normal', 'pago', '0449f5cc392c86795f5f22086f818c7b', NULL, NULL, NULL, '2025-08-07 19:59:58', '2025-08-07 20:00:21'),
	(2, 1, 'Miltom Campos', '75077388768', '21987654321', 'menino', 'teste@gmail.com', 23, 'Queimados', 'SP', 'normal', 'inscrito', 'f009d830c56c5d19dab3c95918b66078', NULL, NULL, NULL, '2025-08-07 21:23:09', '2025-08-07 21:23:09');

-- Copiando estrutura para procedure eventos_catolicos.RelatorioEvento
DELIMITER //
CREATE PROCEDURE `RelatorioEvento`(IN evento_id INT)
BEGIN
    SELECT 
        e.nome as evento_nome,
        e.data_inicio,
        e.data_fim,
        e.local,
        e.cidade,
        e.valor,
        e.limite_participantes,
        COUNT(p.id) as total_inscritos,
        COUNT(CASE WHEN p.status = 'pago' THEN 1 END) as total_pagos,
        COUNT(CASE WHEN p.status = 'presente' THEN 1 END) as total_presentes,
        SUM(CASE WHEN pg.status = 'pago' THEN pg.valor ELSE 0 END) as receita_total
    FROM eventos e
    LEFT JOIN participantes p ON e.id = p.evento_id
    LEFT JOIN pagamentos pg ON p.id = pg.participante_id
    WHERE e.id = evento_id
    GROUP BY e.id;
END//
DELIMITER ;

-- Copiando estrutura para tabela eventos_catolicos.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nivel` enum('admin','operador') DEFAULT 'operador',
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela eventos_catolicos.usuarios: ~1 rows (aproximadamente)
REPLACE INTO `usuarios` (`id`, `username`, `password`, `nome`, `email`, `nivel`, `ativo`, `criado_em`, `atualizado_em`) VALUES
	(1, 'admin', '$2y$10$jysZH0bXg4GhI36xWjfkb.jGKfNA5Wjipf3BMnwoWoVzGu1jJovNS', 'Administrador', 'admin@vinde.com.br', 'admin', 1, '2025-08-07 16:05:43', '2025-08-07 16:33:51');

-- Copiando estrutura para view eventos_catolicos.view_eventos_stats
-- Criando tabela temporária para evitar erros de dependência de VIEW
CREATE TABLE `view_eventos_stats` (
	`id` INT(11) NOT NULL,
	`nome` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`slug` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`descricao` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`descricao_completa` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`data_inicio` DATE NOT NULL,
	`data_fim` DATE NULL,
	`horario_inicio` TIME NULL,
	`horario_fim` TIME NULL,
	`local` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`endereco` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`cidade` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`estado` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`valor` DECIMAL(10,2) NOT NULL,
	`limite_participantes` INT(11) NOT NULL,
	`tipo` ENUM('presencial','online','hibrido') NULL COLLATE 'utf8mb4_unicode_ci',
	`status` ENUM('ativo','inativo','finalizado','esgotado') NULL COLLATE 'utf8mb4_unicode_ci',
	`programacao` LONGTEXT NULL COLLATE 'utf8mb4_bin',
	`inclui` LONGTEXT NULL COLLATE 'utf8mb4_bin',
	`imagem` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`criado_em` TIMESTAMP NOT NULL,
	`atualizado_em` TIMESTAMP NOT NULL,
	`total_inscritos` BIGINT(21) NOT NULL,
	`total_pagos` BIGINT(21) NOT NULL,
	`total_presentes` BIGINT(21) NOT NULL,
	`vagas_restantes` BIGINT(22) NOT NULL,
	`receita_evento` DECIMAL(32,2) NULL
) ENGINE=MyISAM;

-- Copiando estrutura para view eventos_catolicos.view_participantes_completo
-- Criando tabela temporária para evitar erros de dependência de VIEW
CREATE TABLE `view_participantes_completo` (
	`id` INT(11) NOT NULL,
	`evento_id` INT(11) NOT NULL,
	`nome` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`cpf` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`whatsapp` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`instagram` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`email` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`idade` INT(11) NOT NULL,
	`cidade` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`estado` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`tipo` ENUM('normal','cortesia') NULL COLLATE 'utf8mb4_unicode_ci',
	`status` ENUM('inscrito','pago','presente','cancelado') NULL COLLATE 'utf8mb4_unicode_ci',
	`qr_token` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`checkin_timestamp` TIMESTAMP NULL,
	`checkin_operador` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`observacoes` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`criado_em` TIMESTAMP NOT NULL,
	`atualizado_em` TIMESTAMP NOT NULL,
	`evento_nome` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`evento_data` DATE NOT NULL,
	`evento_local` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`evento_cidade` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`status_pagamento` ENUM('pendente','pago','cancelado','estornado') NULL COLLATE 'utf8mb4_unicode_ci',
	`valor_pagamento` DECIMAL(10,2) NULL,
	`metodo_pagamento` ENUM('pix','dinheiro','transferencia') NULL COLLATE 'utf8mb4_unicode_ci',
	`pago_em` TIMESTAMP NULL
) ENGINE=MyISAM;

-- Copiando estrutura para trigger eventos_catolicos.log_pagamento_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER log_pagamento_insert 
AFTER INSERT ON pagamentos
FOR EACH ROW
BEGIN
    INSERT INTO logs_atividades (usuario, acao, detalhes)
    VALUES ('sistema', 'pagamento_criado', 
            CONCAT('Participante ID: ', NEW.participante_id, ' | Valor: ', NEW.valor));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Copiando estrutura para trigger eventos_catolicos.log_pagamento_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER log_pagamento_update 
AFTER UPDATE ON pagamentos
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO logs_atividades (usuario, acao, detalhes)
        VALUES ('sistema', 'status_pagamento_alterado', 
                CONCAT('Pagamento ID: ', NEW.id, ' | Status: ', OLD.status, ' -> ', NEW.status));
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Copiando estrutura para trigger eventos_catolicos.log_participante_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER log_participante_insert 
AFTER INSERT ON participantes
FOR EACH ROW
BEGIN
    INSERT INTO logs_atividades (usuario, acao, detalhes)
    VALUES ('sistema', 'participante_inscrito', 
            CONCAT('Participante: ', NEW.nome, ' | Evento ID: ', NEW.evento_id));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Copiando estrutura para trigger eventos_catolicos.log_participante_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER log_participante_update 
AFTER UPDATE ON participantes
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO logs_atividades (usuario, acao, detalhes)
        VALUES ('sistema', 'status_participante_alterado', 
                CONCAT('Participante: ', NEW.nome, ' | Status: ', OLD.status, ' -> ', NEW.status));
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Removendo tabela temporária e criando a estrutura VIEW final
DROP TABLE IF EXISTS `view_eventos_stats`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_eventos_stats` AS SELECT 
    e.*,
    COUNT(p.id) as total_inscritos,
    COUNT(CASE WHEN p.status = 'pago' THEN 1 END) as total_pagos,
    COUNT(CASE WHEN p.status = 'presente' THEN 1 END) as total_presentes,
    (e.limite_participantes - COUNT(p.id)) as vagas_restantes,
    SUM(CASE WHEN pg.status = 'pago' THEN pg.valor ELSE 0 END) as receita_evento
FROM eventos e
LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
LEFT JOIN pagamentos pg ON p.id = pg.participante_id
GROUP BY e.id 
;

-- Removendo tabela temporária e criando a estrutura VIEW final
DROP TABLE IF EXISTS `view_participantes_completo`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_participantes_completo` AS SELECT 
    p.*,
    e.nome as evento_nome,
    e.data_inicio as evento_data,
    e.local as evento_local,
    e.cidade as evento_cidade,
    pg.status as status_pagamento,
    pg.valor as valor_pagamento,
    pg.metodo as metodo_pagamento,
    pg.pago_em
FROM participantes p
INNER JOIN eventos e ON p.evento_id = e.id
LEFT JOIN pagamentos pg ON p.id = pg.participante_id 
;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
