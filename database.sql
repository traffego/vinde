-- =========================================
-- VINDE - SISTEMA DE EVENTOS CATÓLICOS
-- Script de criação do banco de dados
-- =========================================

-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS eventos_catolicos 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE eventos_catolicos;

-- =========================================
-- TABELA: usuarios
-- =========================================
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    nivel ENUM('admin', 'operador') DEFAULT 'operador',
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB;

-- =========================================
-- TABELA: eventos
-- =========================================
CREATE TABLE eventos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    descricao TEXT,
    descricao_completa TEXT,
    data_inicio DATE NOT NULL,
    data_fim DATE,
    horario_inicio TIME,
    horario_fim TIME,
    local VARCHAR(200) NOT NULL,
    endereco TEXT,
    cidade VARCHAR(100) NOT NULL,
    estado VARCHAR(2) DEFAULT 'SP',
    valor DECIMAL(10,2) NOT NULL DEFAULT 0,
    limite_participantes INT NOT NULL DEFAULT 100,
    tipo ENUM('presencial', 'online', 'hibrido') DEFAULT 'presencial',
    status ENUM('ativo', 'inativo', 'finalizado', 'esgotado') DEFAULT 'ativo',
    programacao JSON,
    inclui JSON,
    imagem VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_cidade (cidade),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB;

-- =========================================
-- TABELA: participantes
-- =========================================
CREATE TABLE participantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento_id INT NOT NULL,
    nome VARCHAR(200) NOT NULL,
    cpf VARCHAR(14) UNIQUE NOT NULL,
    whatsapp VARCHAR(15) NOT NULL,
    instagram VARCHAR(50),
    email VARCHAR(100),
    idade INT NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    estado VARCHAR(2) DEFAULT 'SP',
    tipo ENUM('normal', 'cortesia') DEFAULT 'normal',
    status ENUM('inscrito', 'pago', 'presente', 'cancelado') DEFAULT 'inscrito',
    qr_token VARCHAR(255) UNIQUE,
    checkin_timestamp TIMESTAMP NULL,
    checkin_operador VARCHAR(50),
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    INDEX idx_evento (evento_id),
    INDEX idx_cpf (cpf),
    INDEX idx_status (status),
    INDEX idx_qr_token (qr_token),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB;

-- =========================================
-- TABELA: pagamentos
-- =========================================
CREATE TABLE pagamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    participante_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    pix_codigo TEXT,
    pix_qr_code TEXT,
    status ENUM('pendente', 'pago', 'cancelado', 'estornado') DEFAULT 'pendente',
    metodo ENUM('pix', 'dinheiro', 'transferencia') DEFAULT 'pix',
    comprovante VARCHAR(255),
    observacoes TEXT,
    pago_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (participante_id) REFERENCES participantes(id) ON DELETE CASCADE,
    INDEX idx_participante (participante_id),
    INDEX idx_status (status),
    INDEX idx_pago_em (pago_em)
) ENGINE=InnoDB;

-- =========================================
-- TABELA: logs_atividades
-- =========================================
CREATE TABLE logs_atividades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50),
    acao VARCHAR(100) NOT NULL,
    detalhes TEXT,
    ip VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_usuario (usuario),
    INDEX idx_acao (acao)
) ENGINE=InnoDB;

-- =========================================
-- TABELA: configuracoes
-- =========================================
CREATE TABLE configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao TEXT,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave)
) ENGINE=InnoDB;

-- =========================================
-- TABELA: newsletter (opcional para futuro)
-- =========================================
CREATE TABLE newsletter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    nome VARCHAR(100),
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB;

-- =========================================
-- DADOS INICIAIS
-- =========================================

-- Usuário administrador padrão (senha: admin123)
INSERT INTO usuarios (username, password, nome, email, nivel) VALUES 
('admin', '$2a$10$Vno9xl/Gfqy14ff3BoSoNeD5o6EDB4kefG5aC1uMFXw727D3x9ED.', 'Administrador', 'admin@vinde.com.br', 'admin');

-- Configurações do sistema
INSERT INTO configuracoes (chave, valor, descricao) VALUES 
('site_nome', 'Vinde - Eventos Católicos', 'Nome do site'),
('site_email', 'contato@vinde.com.br', 'Email principal'),
('site_telefone', '(11) 99999-9999', 'Telefone para contato'),
('whatsapp_contato', '5511999999999', 'WhatsApp para contato'),
('pix_chave', '12345678901', 'Chave PIX para pagamentos'),
('pix_nome', 'PAROQUIA SAO JOSE', 'Nome do beneficiário PIX'),
('pix_cidade', 'SAO PAULO', 'Cidade do beneficiário PIX'),
('backup_automatico', '1', 'Backup automático ativado'),
('manutencao_modo', '0', 'Modo manutenção'),
('registrations_enabled', '1', 'Inscrições habilitadas'),
('max_eventos_por_pagina', '12', 'Máximo de eventos por página'),
('timezone', 'America/Sao_Paulo', 'Fuso horário do sistema'),
('email_smtp_host', '', 'Servidor SMTP para emails'),
('email_smtp_port', '587', 'Porta SMTP'),
('email_smtp_user', '', 'Usuário SMTP'),
('email_smtp_pass', '', 'Senha SMTP'),
('google_analytics_id', '', 'ID do Google Analytics'),
('facebook_pixel_id', '', 'ID do Facebook Pixel');

-- =========================================
-- EVENTOS DE EXEMPLO
-- =========================================

INSERT INTO eventos (nome, slug, descricao, descricao_completa, data_inicio, data_fim, horario_inicio, horario_fim, local, endereco, cidade, estado, valor, limite_participantes, tipo, programacao, inclui, status) VALUES 

('Retiro Espiritual de Advento', 'retiro-advento-2024', 'Um retiro especial para se preparar para o Natal', 'Um fim de semana de profunda reflexão e oração para se preparar adequadamente para a chegada do Menino Jesus. Momentos de adoração, palestras inspiradoras e comunhão fraterna.', '2024-12-15', '2024-12-16', '08:00:00', '18:00:00', 'Centro de Retiros São Francisco', 'Rua das Oliveiras, 123', 'São Paulo', 'SP', 150.00, 50, 'presencial', 
'[
    {"horario": "08:00", "titulo": "Chegada e Café da Manhã", "descricao": "Acolhida dos participantes"},
    {"horario": "09:00", "titulo": "Abertura e Oração Inicial", "palestrante": "Pe. João Silva"},
    {"horario": "10:00", "titulo": "Palestra: O Sentido do Advento", "palestrante": "Pe. João Silva"},
    {"horario": "11:30", "titulo": "Adoração ao Santíssimo", "descricao": "Momento de oração silenciosa"},
    {"horario": "12:30", "titulo": "Almoço", "descricao": ""},
    {"horario": "14:00", "titulo": "Palestra: Maria, Mãe do Salvador", "palestrante": "Irmã Maria José"},
    {"horario": "15:30", "titulo": "Grupos de Partilha", "descricao": "Reflexão em pequenos grupos"},
    {"horario": "16:30", "titulo": "Santa Missa", "palestrante": "Pe. João Silva"},
    {"horario": "18:00", "titulo": "Jantar e Confraternização", "descricao": ""}
]',
'[
    "Todas as refeições (café, almoço, jantar)",
    "Material de apoio",
    "Certificado de participação",
    "Lembrança do retiro"
]', 'ativo'),

('Encontro de Jovens Católicos', 'encontro-jovens-2024', 'Encontro para jovens de 16 a 30 anos', 'Um dia especial para jovens que desejam aprofundar sua fé e conhecer outros jovens católicos. Atividades dinâmicas, música, testemunhos e muito mais.', '2024-12-28', '2024-12-28', '14:00:00', '22:00:00', 'Paróquia São José', 'Av. Paulista, 1000', 'São Paulo', 'SP', 30.00, 100, 'presencial',
'[
    {"horario": "14:00", "titulo": "Chegada e Credenciamento", "descricao": ""},
    {"horario": "14:30", "titulo": "Dinâmica de Apresentação", "descricao": "Conhecendo uns aos outros"},
    {"horario": "15:30", "titulo": "Palestra: Juventude e Fé", "palestrante": "Pe. Carlos Santos"},
    {"horario": "16:30", "titulo": "Lanche", "descricao": ""},
    {"horario": "17:00", "titulo": "Testemunhos de Jovens", "descricao": "Histórias de conversão"},
    {"horario": "18:00", "titulo": "Adoração Musical", "descricao": "Momento de oração com música"},
    {"horario": "19:00", "titulo": "Santa Missa", "palestrante": "Pe. Carlos Santos"},
    {"horario": "20:00", "titulo": "Jantar", "descricao": ""},
    {"horario": "21:00", "titulo": "Show Musical", "descricao": "Banda católica local"}
]',
'[
    "Lanche da tarde",
    "Jantar",
    "Material de apoio",
    "Camiseta do evento"
]', 'ativo'),

('Palestra sobre Família Cristã', 'palestra-familia-crista', 'Reflexões sobre os valores familiares cristãos', 'Uma noite de reflexão sobre como viver os valores cristãos no ambiente familiar, fortalecendo os laços e a fé em família.', '2025-01-15', '2025-01-15', '19:30:00', '21:30:00', 'Salão Paroquial São Pedro', 'Rua da Igreja, 50', 'Santos', 'SP', 0.00, 80, 'presencial',
'[
    {"horario": "19:30", "titulo": "Recepção", "descricao": ""},
    {"horario": "20:00", "titulo": "Palestra: A Família Cristã Hoje", "palestrante": "Dr. Maria Fernanda Lima"},
    {"horario": "21:00", "titulo": "Perguntas e Respostas", "descricao": ""},
    {"horario": "21:15", "titulo": "Oração Final", "descricao": ""},
    {"horario": "21:30", "titulo": "Confraternização", "descricao": "Café e bolo"}
]',
'[
    "Material de apoio",
    "Certificado de participação",
    "Lanche ao final"
]', 'ativo');

-- =========================================
-- PROCEDURES ÚTEIS
-- =========================================

DELIMITER //

-- Procedure para gerar relatório de evento
CREATE PROCEDURE RelatorioEvento(IN evento_id INT)
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
END //

-- Procedure para backup de participantes de um evento
CREATE PROCEDURE BackupParticipantesEvento(IN evento_id INT)
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
END //

-- Procedure para estatísticas gerais
CREATE PROCEDURE EstatisticasGerais()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM eventos WHERE status = 'ativo') as eventos_ativos,
        (SELECT COUNT(*) FROM participantes WHERE status != 'cancelado') as total_participantes,
        (SELECT COUNT(*) FROM participantes WHERE status = 'pago') as participantes_pagos,
        (SELECT SUM(valor) FROM pagamentos WHERE status = 'pago') as receita_total,
        (SELECT COUNT(*) FROM eventos WHERE data_inicio >= CURDATE()) as eventos_futuros;
END //

DELIMITER ;

-- =========================================
-- TRIGGERS PARA LOGS AUTOMÁTICOS
-- =========================================

DELIMITER //

-- Log para inserção de participantes
CREATE TRIGGER log_participante_insert 
AFTER INSERT ON participantes
FOR EACH ROW
BEGIN
    INSERT INTO logs_atividades (usuario, acao, detalhes)
    VALUES ('sistema', 'participante_inscrito', 
            CONCAT('Participante: ', NEW.nome, ' | Evento ID: ', NEW.evento_id));
END //

-- Log para atualização de status de participante
CREATE TRIGGER log_participante_update 
AFTER UPDATE ON participantes
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO logs_atividades (usuario, acao, detalhes)
        VALUES ('sistema', 'status_participante_alterado', 
                CONCAT('Participante: ', NEW.nome, ' | Status: ', OLD.status, ' -> ', NEW.status));
    END IF;
END //

-- Log para pagamentos
CREATE TRIGGER log_pagamento_insert 
AFTER INSERT ON pagamentos
FOR EACH ROW
BEGIN
    INSERT INTO logs_atividades (usuario, acao, detalhes)
    VALUES ('sistema', 'pagamento_criado', 
            CONCAT('Participante ID: ', NEW.participante_id, ' | Valor: ', NEW.valor));
END //

-- Log para atualização de status de pagamento
CREATE TRIGGER log_pagamento_update 
AFTER UPDATE ON pagamentos
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO logs_atividades (usuario, acao, detalhes)
        VALUES ('sistema', 'status_pagamento_alterado', 
                CONCAT('Pagamento ID: ', NEW.id, ' | Status: ', OLD.status, ' -> ', NEW.status));
    END IF;
END //

DELIMITER ;

-- =========================================
-- VIEWS ÚTEIS
-- =========================================

-- View para eventos com estatísticas
CREATE VIEW view_eventos_stats AS
SELECT 
    e.*,
    COUNT(p.id) as total_inscritos,
    COUNT(CASE WHEN p.status = 'pago' THEN 1 END) as total_pagos,
    COUNT(CASE WHEN p.status = 'presente' THEN 1 END) as total_presentes,
    (e.limite_participantes - COUNT(p.id)) as vagas_restantes,
    SUM(CASE WHEN pg.status = 'pago' THEN pg.valor ELSE 0 END) as receita_evento
FROM eventos e
LEFT JOIN participantes p ON e.id = p.evento_id AND p.status != 'cancelado'
LEFT JOIN pagamentos pg ON p.id = pg.participante_id
GROUP BY e.id;

-- View para participantes com informações completas
CREATE VIEW view_participantes_completo AS
SELECT 
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
LEFT JOIN pagamentos pg ON p.id = pg.participante_id;

-- =========================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- =========================================

-- Índices compostos para consultas frequentes
CREATE INDEX idx_eventos_data_status ON eventos(data_inicio, status);
CREATE INDEX idx_participantes_evento_status ON participantes(evento_id, status);
CREATE INDEX idx_pagamentos_status_data ON pagamentos(status, pago_em);
CREATE INDEX idx_logs_usuario_timestamp ON logs_atividades(usuario, timestamp);

-- =========================================
-- CONFIGURAÇÕES FINAIS
-- =========================================

-- Configurar charset para todas as tabelas
ALTER DATABASE eventos_catolicos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Registrar criação do banco
INSERT INTO logs_atividades (usuario, acao, detalhes) VALUES 
('sistema', 'banco_criado', 'Banco de dados eventos_catolicos criado com sucesso');

-- Commit das alterações
COMMIT;

-- Mostrar status do banco
SELECT 'Banco de dados eventos_catolicos criado com sucesso!' as status; 