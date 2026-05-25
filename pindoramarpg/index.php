<?php
require_once __DIR__ . '/includes/auth.php';

iniciarSessao();
$usuario = usuarioLogado();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pindorama RPG</title>

    <link rel="stylesheet" href="assets/css/ficha.css" />
    <link rel="stylesheet" href="assets/css/home.css?v=20260513i" />
    <link rel="stylesheet" href="assets/css/auth.css?v=20260513i" />
    <link rel="stylesheet" href="assets/css/transitions.css?v=20260508u" />
</head>
<body class="home-body home-page">
    <script src="assets/js/transitions.js?v=20260508u"></script>

    <main class="home-shell home-shell-home">

        <section class="home-hero">
            <div class="home-logo-frame">
                <img src="assets/img/branding/pindorama-logo-nova.png" alt="Logo do Pindorama RPG" />
            </div>
        </section>

        <?php $ehFacilitador = $usuario && ($usuario['role'] ?? '') === 'facilitador'; ?>

        <div class="home-auth-status">
            <?php if ($usuario): ?>
                <span>
                    Olá, <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
                    <span class="auth-role-tag"><?= htmlspecialchars(ucfirst($usuario['role'])) ?></span>
                </span>
                <a class="home-auth-link" href="logout.php">Sair</a>
            <?php else: ?>
                <a class="home-auth-link" href="login.php">Entrar</a>
                <a class="home-auth-link" href="register.php">Criar conta</a>
            <?php endif; ?>
        </div>

        <?php if ($ehFacilitador): ?>
        <!-- ============ Menu do FACILITADOR ============ -->
        <nav class="home-grid-ref home-grid-home" aria-label="Menu do facilitador">
            <a class="home-card-ref home-card-home home-card-nova-ficha" href="ficha.php">
                <strong>Nova Ficha</strong>
                <span>Crie um novo personagem.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-listar-fichas" href="fichas.php">
                <strong>Listar Fichas</strong>
                <span>Veja personagens salvos.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-campo-batalha" href="mesa-jogo.php">
                <strong>Mesa de Jogo</strong>
                <span>Cenas em grid, mapas e tokens.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-aventuras" href="aventuras.php">
                <strong>Aventuras</strong>
                <span>Crie e narre aventuras prontas.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-minhas-mesas" href="mesas.php">
                <strong>Minhas Mesas</strong>
                <span>Crie e administre suas campanhas.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-participantes" href="mesas.php?focus=participantes">
                <strong>Participantes</strong>
                <span>Adicione e vincule jogadores às mesas.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-fichas-participantes" href="fichas.php">
                <strong>Fichas dos Participantes</strong>
                <span>Personagens criados pelos jogadores.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-npcs" href="mesa-conteudos.php?tipo=npc">
                <strong>NPCs</strong>
                <span>Personagens controlados pela mesa.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-bestiario" href="bestiario.php">
                <strong>Bestiário</strong>
                <span>Criaturas e ameaças.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-magias-poderes" href="mesa-conteudos.php?tipo=magia">
                <strong>Magias e Poderes</strong>
                <span>Conteúdos customizados da mesa.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-narrativas" href="mesa-conteudos.php?tipo=narrativa">
                <strong>Narrativas</strong>
                <span>Cenas e descrições preparadas.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-mapas-cenas" href="mesa-jogo.php">
                <strong>Mapas e Cenas</strong>
                <span>Mesa de Jogo: tabuleiro, tokens e iniciativa.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-conteudos-liberados" href="mesa-conteudos.php?visibilidade=participantes">
                <strong>Conteúdos Liberados</strong>
                <span>O que os Participantes já podem ver.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-acervo" href="referencia.php">
                <strong>Acervo</strong>
                <span>Catálogos e regras do sistema.</span>
            </a>
        </nav>
        <?php elseif ($usuario): ?>
        <!-- ============ Menu do JOGADOR / PARTICIPANTE ============
             Sem acessos administrativos. As páginas de admin de mesa
             continuam protegidas por exigirFacilitador(). -->
        <nav class="home-grid-ref home-grid-home" aria-label="Menu do jogador">
            <a class="home-card-ref home-card-home home-card-minhas-mesas" href="minhas-mesas.php">
                <strong>Minhas Mesas</strong>
                <span>As campanhas em que você joga.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-nova-ficha" href="ficha.php">
                <strong>Nova Ficha</strong>
                <span>Crie um novo personagem.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-listar-fichas" href="fichas.php">
                <strong>Listar Fichas</strong>
                <span>Veja seus personagens salvos.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-conteudos-liberados" href="meus-conteudos.php">
                <strong>Conteúdos Liberados</strong>
                <span>O que os facilitadores liberaram para você.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-acervo" href="referencia.php">
                <strong>Acervo</strong>
                <span>Catálogos e regras do sistema.</span>
            </a>
        </nav>
        <?php else: ?>
        <!-- ============ Visitante deslogado ============ -->
        <nav class="home-grid-ref home-grid-home" aria-label="Menu principal">
            <a class="home-card-ref home-card-home home-card-nova-ficha" href="ficha.php">
                <strong>Nova Ficha</strong>
                <span>Crie um novo personagem.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-listar-fichas" href="fichas.php">
                <strong>Listar Fichas</strong>
                <span>Veja personagens salvos.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-campo-batalha" href="mesa-jogo.php">
                <strong>Mesa de Jogo</strong>
                <span>Cenas em grid, mapas e tokens.</span>
            </a>
            <a class="home-card-ref home-card-home home-card-acervo" href="referencia.php">
                <strong>Acervo</strong>
                <span>Catálogos e regras do sistema.</span>
            </a>
        </nav>
        <?php endif; ?>

    </main>

</body>
</html>
