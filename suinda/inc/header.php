<?php
// Cabeçalho compartilhado das páginas do Suindá (landing, login, painel).
// Integrado ao ecossistema Pindorama, com link de retorno ao Coletivo.
// Variáveis esperadas (opcionais): $suindaPageTitle, $suindaPageDesc,
// $suindaActiveNav (home|estudar), $suindaBodyClass.
$suindaPageTitle = $suindaPageTitle ?? 'Suindá — espaço de estudos do Coletivo Pindorama';
$suindaPageDesc  = $suindaPageDesc  ?? 'Suindá: cursos, trilhas de aprendizagem e ferramentas de estudo do Coletivo Pindorama.';
$suindaActiveNav = $suindaActiveNav ?? 'home';
$suindaBodyClass = $suindaBodyClass ?? '';
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($suindaPageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($suindaPageDesc) ?>" />
  <meta name="theme-color" content="#1f4d5c" />
  <link rel="stylesheet" href="/suinda/assets/css/suinda-site.css" />
</head>
<body class="<?= htmlspecialchars($suindaBodyClass) ?>">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <header class="suinda-header">
    <div class="container suinda-header__inner">
      <a class="suinda-brand" href="/suinda/">
        <span class="suinda-brand__mark" aria-hidden="true">🦉</span>
        <span class="suinda-brand__text">
          <span class="suinda-brand__name">Suindá</span>
          <span class="suinda-brand__tag">Estudos · Coletivo Pindorama</span>
        </span>
      </a>
      <nav class="suinda-nav" aria-label="Navegação do Suindá">
        <a href="/suinda/"<?= $suindaActiveNav === 'home' ? ' aria-current="page"' : '' ?>>Início</a>
        <a href="/suinda/estudar/"<?= $suindaActiveNav === 'estudar' ? ' aria-current="page"' : '' ?>>Estudar</a>
        <a class="back-link" href="/">Coletivo Pindorama</a>
      </nav>
    </div>
  </header>
