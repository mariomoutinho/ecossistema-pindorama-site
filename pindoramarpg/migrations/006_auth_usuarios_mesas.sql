-- Migration 006 — autenticação, mesas e vínculo participante↔mesa.
--
-- Cria as tabelas-base do sistema de login do Pindorama RPG:
--   * usuarios            — credenciais e papel global do usuário.
--   * mesas               — campanhas/mesas criadas por um Facilitador.
--   * mesa_participantes  — vínculo N:N entre usuários e mesas, com o
--                           papel exercido naquela mesa específica.
--
-- A coluna usuarios.role guarda o papel "padrão" do usuário (usado em
-- páginas globais como o painel inicial). O papel real ao acessar uma
-- mesa específica é o que está em mesa_participantes.papel — assim é
-- possível, no futuro, que o mesmo usuário seja Facilitador em uma mesa
-- e Participante em outra sem alteração de schema.
--
-- IF NOT EXISTS é usado para permitir reexecução segura. Compatível com
-- MariaDB 10.4+ (sintaxe usada no resto do projeto). Em MySQL 8 puro,
-- o IF NOT EXISTS em CREATE TABLE também é suportado.

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`           INT(11) NOT NULL AUTO_INCREMENT,
    `nome`         VARCHAR(150) NOT NULL,
    `email`        VARCHAR(190) NOT NULL,
    `senha_hash`   VARCHAR(255) NOT NULL,
    `role`         ENUM('facilitador','participante') NOT NULL DEFAULT 'participante',
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usuarios_email_unq` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mesas` (
    `id`              INT(11) NOT NULL AUTO_INCREMENT,
    `facilitador_id`  INT(11) NOT NULL,
    `nome`            VARCHAR(180) NOT NULL,
    `descricao`       TEXT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `mesas_facilitador_idx` (`facilitador_id`),
    CONSTRAINT `mesas_facilitador_fk`
        FOREIGN KEY (`facilitador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mesa_participantes` (
    `id`          INT(11) NOT NULL AUTO_INCREMENT,
    `mesa_id`     INT(11) NOT NULL,
    `usuario_id`  INT(11) NOT NULL,
    `papel`       ENUM('facilitador','participante') NOT NULL DEFAULT 'participante',
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mesa_participantes_mesa_usuario_unq` (`mesa_id`, `usuario_id`),
    KEY `mesa_participantes_usuario_idx` (`usuario_id`),
    CONSTRAINT `mesa_participantes_mesa_fk`
        FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `mesa_participantes_usuario_fk`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
