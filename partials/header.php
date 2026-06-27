<?php
// ================================
// Header compartilhado.
// Espera variáveis: $activePage (home|terapias|cuidar|rpg|sobre|contato),
// $pageTitle, $pageDescription, $extraStyles (array opcional).
// Depende de variáveis definidas em partials/bootstrap.php.
// ================================

$active           = $activePage         ?? 'home';
$pageTitle        = $pageTitle          ?? 'Coletivo Pindorama • Saúde Integrativa & Bem-Estar';
$pageDescription  = $pageDescription    ?? 'Saúde Integrativa & Bem-Estar em Recife/PE. Terapias, atividades coletivas, formações e a metodologia Cuidar+.';
$extraStyles      = $extraStyles        ?? [];
$asyncStyles      = $asyncStyles        ?? [];
$criticalCss      = $criticalCss        ?? '';
$preloadImages    = $preloadImages      ?? [];

// Prefixo para âncoras de seções que só existem na home (#sobre, #contato).
$homePrefix = ($active === 'home') ? '' : htmlspecialchars($homeUrl);
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>" />
  <meta name="theme-color" content="#0E1C17" />
  <link rel="icon" type="image/png" href="assets/img/logo-pindorama-64.png" />

  <?php foreach ($preloadImages as $img): ?>
    <link
      rel="preload"
      as="image"
      href="<?= htmlspecialchars($img['href']) ?>"
      <?php if (!empty($img['type'])): ?>type="<?= htmlspecialchars($img['type']) ?>"<?php endif; ?>
      <?php if (!empty($img['imagesrcset'])): ?>imagesrcset="<?= htmlspecialchars($img['imagesrcset']) ?>"<?php endif; ?>
      <?php if (!empty($img['imagesizes'])): ?>imagesizes="<?= htmlspecialchars($img['imagesizes']) ?>"<?php endif; ?>
      <?php if (!empty($img['fetchpriority'])): ?>fetchpriority="<?= htmlspecialchars($img['fetchpriority']) ?>"<?php endif; ?>
    >
  <?php endforeach; ?>

  <?php if ($criticalCss !== ''): ?>
    <style><?= $criticalCss ?></style>
  <?php endif; ?>

  <?php if (!$asyncStyles): ?>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/home.css">
  <?php endif; ?>
  <?php foreach ($extraStyles as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
  <?php endforeach; ?>
  <?php foreach ($asyncStyles as $href): ?>
    <link rel="preload" href="<?= htmlspecialchars($href) ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <?php endforeach; ?>
  <?php if ($asyncStyles): ?>
    <noscript>
      <?php foreach ($asyncStyles as $href): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
      <?php endforeach; ?>
    </noscript>
  <?php endif; ?>
</head>

<body>
<header class="siteHeader">
  <div class="container">
    <div class="nav">
      <a class="brand" href="<?= htmlspecialchars($homeUrl) ?>#topo">
        <img class="logo" src="assets/img/logo-pindorama.svg" width="64" height="64" decoding="async" fetchpriority="low" alt="Coletivo Pindorama">
        <div>
          <h1>Coletivo Pindorama</h1>
          <p>Saúde Integrativa &amp; Bem-Estar</p>
        </div>
      </a>

      <nav class="menu" aria-label="Navegação principal">
        <a href="<?= htmlspecialchars($homeUrl) ?>"<?= $active === 'home' ? ' aria-current="page"' : '' ?>>Início</a>
        <a href="<?= htmlspecialchars($terapiasUrl) ?>"<?= $active === 'terapias' ? ' aria-current="page"' : '' ?>>Terapias</a>
        <a href="<?= htmlspecialchars($cuidarUrl) ?>"<?= $active === 'cuidar' ? ' aria-current="page"' : '' ?>>Cuidar+</a>
        <a href="<?= htmlspecialchars($rpgUrl) ?>" target="_blank" rel="noopener">Pindorama RPG</a>
        <a href="<?= htmlspecialchars($gessUrl) ?>"<?= $active === 'gess' ? ' aria-current="page"' : '' ?>>Sementeira</a>
        <a href="<?= htmlspecialchars($suindaUrl) ?>"<?= $active === 'suinda' ? ' aria-current="page"' : '' ?>>Suindá</a>
        <a href="<?= $homePrefix ?>#sobre">Sobre</a>
        <a href="<?= $homePrefix ?>#contato">Contato</a>
      </nav>

      <div class="cta">
        <a class="btn ghost btn--terapeutas" href="terapeutas/" title="Acesso restrito da equipe de terapeutas">
          <span aria-hidden="true">◐</span> Área dos terapeutas
        </a>
        <a class="btn primary" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">
          Agendar no WhatsApp
        </a>
        <button class="btn hamb" id="btnMenu" type="button" aria-expanded="false" aria-controls="drawer">
          Menu
        </button>
      </div>
    </div>

    <div class="drawer" id="drawer">
      <div class="drawerActions">
        <a class="btn primary" href="<?= htmlspecialchars($whatsLink) ?>" target="_blank" rel="noopener">Agendar no WhatsApp</a>
        <a class="btn" href="<?= $homePrefix ?>#contato">Falar com a gente</a>
        <a class="btn ghost btn--terapeutas" href="terapeutas/"><span aria-hidden="true">◐</span> Área dos terapeutas</a>
      </div>

      <div class="drawerLinks">
        <a href="<?= htmlspecialchars($homeUrl) ?>"<?= $active === 'home' ? ' aria-current="page"' : '' ?>>Início</a>
        <a href="<?= htmlspecialchars($terapiasUrl) ?>"<?= $active === 'terapias' ? ' aria-current="page"' : '' ?>>Terapias</a>
        <a href="<?= htmlspecialchars($cuidarUrl) ?>"<?= $active === 'cuidar' ? ' aria-current="page"' : '' ?>>Cuidar+</a>
        <a href="<?= htmlspecialchars($rpgUrl) ?>" target="_blank" rel="noopener">Pindorama RPG</a>
        <a href="<?= htmlspecialchars($gessUrl) ?>"<?= $active === 'gess' ? ' aria-current="page"' : '' ?>>Sementeira</a>
        <a href="<?= htmlspecialchars($suindaUrl) ?>"<?= $active === 'suinda' ? ' aria-current="page"' : '' ?>>Suindá</a>
        <a href="<?= $homePrefix ?>#sobre">Sobre</a>
        <a href="<?= $homePrefix ?>#contato">Contato</a>
        <a href="terapeutas/">Área dos terapeutas</a>
      </div>
    </div>
  </div>
</header>
