-- Script para atualizar senha do administrador
-- Senha: admin123

USE eventos_catolicos;

-- Atualizar senha do usuário admin
UPDATE usuarios 
SET password = '$2a$10$Vno9xl/Gfqy14ff3BoSoNeD5o6EDB4kefG5aC1uMFXw727D3x9ED.' 
WHERE username = 'admin';

-- Verificar se a atualização foi bem-sucedida
SELECT username, nome, nivel, ativo, criado_em 
FROM usuarios 
WHERE username = 'admin';

-- Registrar no log
INSERT INTO logs_atividades (usuario, acao, detalhes) 
VALUES ('sistema', 'senha_admin_atualizada', 'Senha do administrador atualizada para hash correto');

SELECT 'Senha do administrador atualizada com sucesso!' as status; 