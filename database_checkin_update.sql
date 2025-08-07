-- =========================================
-- VINDE - ATUALIZAÇÃO PARA SISTEMA DE CHECK-IN
-- Script de atualização do banco de dados
-- =========================================

USE eventos_catolicos;

-- Criar tabela de logs do sistema se não existir
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
    INDEX idx_data_hora (data_hora),
    INDEX idx_participante (participante_id),
    INDEX idx_evento (evento_id)
) ENGINE=InnoDB;

-- Atualizar tabela participantes para garantir que os campos de check-in existem
ALTER TABLE participantes 
ADD COLUMN IF NOT EXISTS checkin_timestamp TIMESTAMP NULL AFTER status,
ADD COLUMN IF NOT EXISTS checkin_operador VARCHAR(50) NULL AFTER checkin_timestamp;

-- Garantir que o campo qr_token existe e tem índice
ALTER TABLE participantes 
ADD COLUMN IF NOT EXISTS qr_token VARCHAR(255) UNIQUE NULL AFTER status;

-- Atualizar participantes que não têm qr_token para gerar um
UPDATE participantes 
SET qr_token = CONCAT(UUID(), '-', id) 
WHERE qr_token IS NULL OR qr_token = '';

-- Criar índice no qr_token se não existir
CREATE INDEX IF NOT EXISTS idx_qr_token ON participantes(qr_token);

-- Verificar se o status 'presente' existe no ENUM
-- Se não existir, precisará ser adicionado manualmente via ALTER TABLE

-- Registrar a atualização
INSERT INTO logs_sistema (tipo, descricao, usuario) VALUES 
('sistema_atualizado', 'Sistema de check-in implementado com sucesso', 'sistema');

-- Mostrar status da atualização
SELECT 'Sistema de check-in implementado com sucesso!' as status; 