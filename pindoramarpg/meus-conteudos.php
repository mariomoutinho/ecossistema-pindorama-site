<?php
/**
 * meus-conteudos.php — conteúdos que os facilitadores liberaram para
 * o JOGADOR/PARTICIPANTE (visibilidade 'participantes' ou 'publico'),
 * cruzando apenas as mesas em que ele está vinculado.
 *
 * Read-only. A administração de conteúdos continua em mesa-conteudos.php
 * (protegido por exigirFacilitador()).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/mesa-helpers.php';
require_once __DIR__ . '/config.php';

$usuario = exigirLogin();

// Facilitador tem a visão administrativa própria.
if (isFacilitador()) {
    header('Location: mesa-conteudos.php?visibilidade=participantes');
    exit;
}

$conteudos = listarConteudosLiberadosParaParticipante((int) $usuario['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Conteúdos Liberados — Pindorama RPG</title>
    <link rel="stylesheet" href="assets/css/ficha.css" />
    <link rel="stylesheet" href="assets/css/home.css?v=20260513i" />
    <link rel="stylesheet" href="assets/css/auth.css?v=20260513i" />
    <link rel="stylesheet" href="assets/css/transitions.css?v=20260508u" />
    <link rel="stylesheet" href="assets/css/painel-facilitador.css?v=20260513d" />
</head>
<body class="home-body">
    <script src="assets/js/transitions.js?v=20260508u"></script>

    <main class="home-shell painel-shell">
        <header class="home-hero home-hero-compact">
            <a href="index.php" class="home-back" aria-label="Voltar ao menu">&larr;</a>
            <h1 class="home-title">Conteúdos Liberados</h1>
            <p class="home-subtitle">O que os facilitadores liberaram para você.</p>
        </header>

        <section class="painel-grid">
            <article class="painel-card painel-card--wide">
                <h2>Disponível para você</h2>
                <?php if (empty($conteudos)): ?>
                    <p class="painel-empty">
                        Nenhum conteúdo liberado ainda. Quando um Facilitador
                        publicar NPCs, narrativas ou outros materiais para os
                        participantes das suas mesas, eles aparecem aqui.
                    </p>
                <?php else: ?>
                    <ul class="painel-list">
                        <?php foreach ($conteudos as $c):
                            $dt = $c['updated_at'] ? date('d/m/Y', strtotime($c['updated_at'])) : '';
                        ?>
                            <li class="painel-item">
                                <div>
                                    <strong><?= htmlspecialchars($c['titulo']) ?></strong>
                                    <span class="painel-item-meta">
                                        <?= htmlspecialchars(rotuloTipoConteudo($c['tipo'])) ?>
                                        · Mesa: <?= htmlspecialchars($c['mesa_nome']) ?>
                                        <?= $dt !== '' ? ' · ' . $dt : '' ?>
                                    </span>
                                    <?php if (!empty($c['descricao'])): ?>
                                        <p class="painel-item-desc"><?= nl2br(htmlspecialchars((string) $c['descricao'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </section>

        <div class="home-list-footer auth-footer">
            <a class="home-btn home-btn-ghost" href="index.php">Voltar ao menu</a>
        </div>
    </main>
</body>
</html>
