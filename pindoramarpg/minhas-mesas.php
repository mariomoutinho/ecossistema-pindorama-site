<?php
/**
 * minhas-mesas.php — visão do JOGADOR/PARTICIPANTE.
 *
 * Lista apenas as mesas em que o usuário logado foi vinculado (via
 * mesa_participantes). Nenhuma ação administrativa: criar/editar/
 * excluir mesa e vincular participantes continuam exclusivos do
 * Facilitador em mesas.php (protegido por exigirFacilitador()).
 *
 * Facilitador que cair aqui é redirecionado para o painel próprio.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/mesa-helpers.php';
require_once __DIR__ . '/config.php';

$usuario = exigirLogin();

// O facilitador tem o painel completo de mesas em mesas.php.
if (isFacilitador()) {
    header('Location: mesas.php');
    exit;
}

$mesas = listarMesasDoParticipante((int) $usuario['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Minhas Mesas — Pindorama RPG</title>
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
            <h1 class="home-title">Minhas Mesas</h1>
            <p class="home-subtitle">As campanhas em que você joga.</p>
        </header>

        <section class="painel-grid">
            <article class="painel-card painel-card--wide">
                <h2>Mesas vinculadas</h2>
                <?php if (empty($mesas)): ?>
                    <p class="painel-empty">
                        Você ainda não foi vinculado a nenhuma mesa. Quando um
                        Facilitador adicionar você a uma campanha, ela aparece aqui.
                    </p>
                <?php else: ?>
                    <ul class="mesa-jogador-lista">
                        <?php foreach ($mesas as $m):
                            $nome   = htmlspecialchars($m['nome'] ?: 'Mesa sem nome');
                            $desc   = htmlspecialchars(mb_substr((string) ($m['descricao'] ?? ''), 0, 180));
                            $status = (string) ($m['status'] ?? 'ativa');
                            $fac    = htmlspecialchars($m['facilitador_nome'] ?? '');
                        ?>
                            <li class="mesa-jogador-item">
                                <div class="mesa-jogador-info">
                                    <strong class="mesa-jogador-nome"><?= $nome ?></strong>
                                    <span class="mesa-jogador-meta">
                                        Facilitador: <strong><?= $fac ?></strong>
                                    </span>
                                    <?php if ($desc !== ''): ?>
                                        <p class="mesa-jogador-desc"><?= $desc ?><?= mb_strlen((string) ($m['descricao'] ?? '')) > 180 ? '…' : '' ?></p>
                                    <?php endif; ?>
                                    <span class="mesa-jogador-status mesa-jogador-status--<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(rotuloStatusMesa($status)) ?></span>
                                </div>
                                <div class="mesa-jogador-acoes">
                                    <a class="home-btn" href="mesa-ver.php?id=<?= (int) $m['id'] ?>">Entrar na mesa</a>
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
