-- CodeShowcase — migração para bases já criadas
-- 1) Garante utf8mb4 (corrige emoji 🚀 gravando "?" )
-- 2) Adiciona projetos.funcionalidades (uma por linha)
-- Execute após `database/schema_local.sql` em MySQL 8.0+.

ALTER DATABASE `codeshowcase_local`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `usuario`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `usuario_dev`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `categorias`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `projetos`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `projetos`
    ADD COLUMN IF NOT EXISTS `funcionalidades` TEXT NULL COMMENT 'Uma funcionalidade por linha' AFTER `descricao`;
