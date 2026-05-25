<?php
/**
 * mesa-ver.php — visão de UMA mesa pelo participante.
 *
 * Acesso restrito: o usuário precisa estar vinculado à mesa em
 * mesa_participantes (carregarMesaSeParticipante devolve null caso
 * contrário → bloqueio). Facilitadores também passam por aqui se
 * estiverem vinculados; quem administra a mesa usa mesas.php.
 *
 * Read-only: sem editar/excluir/vincular. Estrutura preparada para,
 * em etapas futuras, listar conteúdos liberados e abrir a Mesa de Jogo.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/mesa-helpers.php';
require_once __DIR__ . '/config.php';

$usuario = exigirLogin();

$mesaId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mesa = $mesaId > 0 ? carregarMesaSeParticipante($mesaId, (int) $usuario['id']) : null;

if (!$mesa) {
    header('Location: acesso-negado.php?m=' . urlencode('Você não participa desta mesa.'));
    exit;
}

$ehFacilitadorDaMesa = ($mesa['meu_papel'] ?? '') === 'facilitador';
$participantes = listarParticipantesDaMesa($mesaId);
$aventuras     = listarAventurasDaMesa($mesaId, (int) $mesa['facilitador_id']);
$status        = (string) ($mesa['status'] ?? 'ativa');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($mesa['nome'] ?: 'Mesa') ?> — Pindorama RPG</title>
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
            <a href="<?= $ehFacilitadorDaMesa ? 'mesas.php' : 'minhas-mesas.php' ?>" class="home-back" aria-label="Voltar">&larr;</a>
            <h1 class="home-title"><?= htmlspecialchars($mesa['nome'] ?: 'Mesa') ?></h1>
            <p class="home-subtitle">
                Facilitador: <strong><?= htmlspecialchars($mesa['facilitador_nome'] ?? '') ?></strong>
                <span class="mesa-jogador-status mesa-jogador-status--<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(rotuloStatusMesa($status)) ?></span>
            </p>
        </header>

        <section class="painel-grid">
            <?php if (!empty($mesa['descricao'])): ?>
                <article class="painel-card painel-card--wide">
                    <h2>Sobre a mesa</h2>
                    <p class="mesa-jogador-desc"><?= nl2br(htmlspecialchars((string) $mesa['descricao'])) ?></p>
                </article>
            <?php endif; ?>

            <article class="painel-card painel-card--wide">
                <h2>Aventuras desta mesa</h2>
                <?php if (empty($aventuras)): ?>
                    <p class="painel-empty">O Facilitador ainda não vinculou aventuras a esta mesa.</p>
                <?php else: ?>
                    <ul class="mesa-aventuras-lista">
                        <?php foreach ($aventuras as $av):
                            $titulo = htmlspecialchars($av['titulo'] ?: 'Aventura sem título');
                            $sub    = htmlspecialchars($av['subtitulo'] ?? '');
                        ?>
                            <li class="mesa-aventura-item">
                                <div class="mesa-aventura-info">
                                    <span class="mesa-aventura-titulo"><?= $titulo ?></span>
                                    <?php if ($sub !== ''): ?>
                                        <span class="mesa-aventura-sub"><?= $sub ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="painel-empty">O Facilitador conduz estas aventuras durante as sessões.</p>
                <?php endif; ?>
            </article>

            <article class="painel-card painel-card--wide">
                <h2>Participantes</h2>
                <?php if (empty($participantes)): ?>
                    <p class="painel-empty">Nenhum participante listado.</p>
                <?php else: ?>
                    <ul class="participantes-grid">
                        <?php foreach ($participantes as $p):
                            $avatar = urlAvatarUsuario($p['foto_path'] ?? null);
                        ?>
                            <li class="participante-card">
                                <div class="participante-avatar<?= $avatar === '' ? ' is-empty' : '' ?>">
                                    <?php if ($avatar !== ''): ?>
                                        <img src="<?= htmlspecialchars($avatar) ?>" alt="" loading="lazy"
                                             onerror="this.remove();this.closest('.participante-avatar').classList.add('is-empty');this.closest('.participante-avatar').textContent='<?= htmlspecialchars(inicialAvatarUsuario($p['nome'])) ?>';" />
                                    <?php else: ?>
                                        <?= htmlspecialchars(inicialAvatarUsuario($p['nome'])) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="participante-info">
                                    <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                    <span class="participante-papel participante-papel--<?= htmlspecialchars($p['papel']) ?>"><?= htmlspecialchars($p['papel']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </section>

        <div class="home-list-footer auth-footer">
            <a class="home-btn home-btn-ghost" href="<?= $ehFacilitadorDaMesa ? 'mesas.php' : 'minhas-mesas.php' ?>">Voltar</a>
        </div>
    </main>
</body>
</html>
