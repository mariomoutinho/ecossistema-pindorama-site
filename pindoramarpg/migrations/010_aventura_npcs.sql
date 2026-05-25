-- Migration 010 — NPCs vinculados a uma aventura.
--
-- Cada aventura pode cadastrar seus próprios NPCs/criaturas. Esses
-- registros são server-side (diferente do bestiario estático em JSON +
-- localStorage do cliente) e são injetados no Bestiário do usuário
-- automaticamente (server-side, em bestiario.php), aparecendo na lista
-- geral sem duplicação de dados.
--
-- Cenas continuam em arquivo (data/aventuras/<id>/cenas.json) para
-- reaproveitar 100% do formato da Mesa de Jogo sem migração extra.

CREATE TABLE IF NOT EXISTS `aventura_npcs` (
    `id`              INT(11)      NOT NULL AUTO_INCREMENT,
    `aventura_id`     INT(11)      NOT NULL,
    `usuario_id`      INT(11)      NOT NULL,
    `nome`            VARCHAR(180) NOT NULL,
    `tipo`            VARCHAR(60)  NULL,
    `nd`              VARCHAR(20)  NULL,
    `tamanho`         VARCHAR(40)  NULL,
    `bioma`           VARCHAR(80)  NULL,
    `papel_tatico`    VARCHAR(80)  NULL,
    `pv_max`          INT          NULL,
    `defesa`          INT          NULL,
    `deslocamento`    VARCHAR(40)  NULL,
    `descricao`       TEXT         NULL,
    `habilidades`     TEXT         NULL,
    `imagem`          VARCHAR(255) NULL,
    `dados_json`      LONGTEXT     NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `aventura_npcs_aventura_idx` (`aventura_id`),
    KEY `aventura_npcs_usuario_idx`  (`usuario_id`),
    CONSTRAINT `aventura_npcs_aventura_fk`
        FOREIGN KEY (`aventura_id`) REFERENCES `aventuras` (`id`) ON DELETE CASCADE,
    CONSTRAINT `aventura_npcs_usuario_fk`
        FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
