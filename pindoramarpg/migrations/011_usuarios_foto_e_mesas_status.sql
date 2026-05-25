-- Migration 011 — foto de perfil em usuários + status em mesas.
--
-- A migration 008 tentava adicionar `status` em `mesas` com a sintaxe
-- ADD COLUMN IF NOT EXISTS, que só roda em MariaDB. Em MySQL nativo a
-- migration falhava silenciosamente e a coluna nunca foi criada, o que
-- quebrava o handler salvar-mesa.php (INSERT com `status` inexistente).
--
-- Esta migration usa um bloco condicional via information_schema.

-- 1) usuarios.foto_path: foto de perfil opcional do participante.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'foto_path'
);
SET @stmt := IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN foto_path VARCHAR(255) NULL AFTER senha_hash',
    'SELECT 1');
PREPARE s1 FROM @stmt; EXECUTE s1; DEALLOCATE PREPARE s1;

-- 2) mesas.status: rascunho / ativa / arquivada (tenta criar caso a
--    migration 008 não tenha aplicado em MySQL nativo).
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mesas' AND COLUMN_NAME = 'status'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE mesas ADD COLUMN status ENUM('rascunho','ativa','arquivada') NOT NULL DEFAULT 'ativa' AFTER descricao",
    'SELECT 1');
PREPARE s2 FROM @stmt; EXECUTE s2; DEALLOCATE PREPARE s2;
