<?php
/**
 * painel.php — fluxo unificado.
 *
 * O painel do Facilitador foi incorporado ao index.php (que passou a
 * agrupar todos os cards num único menu visual coeso). Esta página fica
 * apenas como redirecionamento de compatibilidade para links antigos
 * (bookmarks, e-mails, URLs já espalhadas em conteúdos).
 */
require_once __DIR__ . '/includes/auth.php';
iniciarSessao();
header('Location: index.php', true, 302);
exit;
