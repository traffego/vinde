-- Atualização do banco para integração EFI Bank
-- Execute este script após criar o banco inicial

-- Adicionar campos na tabela pagamentos para EFI Bank
ALTER TABLE pagamentos 
ADD COLUMN pix_txid VARCHAR(35) NULL COMMENT 'Transaction ID da EFI Bank',
ADD COLUMN pix_loc_id VARCHAR(77) NULL COMMENT 'Location ID da cobrança PIX',
ADD COLUMN pix_qrcode_url TEXT NULL COMMENT 'URL do QR Code da EFI',
ADD COLUMN pix_qrcode_data TEXT NULL COMMENT 'Dados do QR Code PIX',
ADD COLUMN pix_expires_at TIMESTAMP NULL COMMENT 'Data de expiração do PIX',
ADD INDEX idx_pix_txid (pix_txid);

-- Adicionar configurações para EFI Bank
INSERT INTO configuracoes (chave, valor, descricao) VALUES 
('efi_webhook_url', '', 'URL do webhook para notificações EFI Bank'),
('efi_ambiente', 'desenvolvimento', 'Ambiente EFI (desenvolvimento|producao)'),
('efi_ativo', '0', 'EFI Bank ativo (0=inativo, 1=ativo)');

-- Criar tabela para logs específicos da EFI
CREATE TABLE efi_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('auth', 'cobranca', 'webhook', 'consulta', 'erro') NOT NULL,
    txid VARCHAR(35) NULL,
    participante_id INT NULL,
    request_data JSON NULL,
    response_data JSON NULL,
    http_code INT NULL,
    mensagem TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_txid (txid),
    INDEX idx_participante (participante_id),
    INDEX idx_criado (criado_em),
    FOREIGN KEY (participante_id) REFERENCES participantes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 