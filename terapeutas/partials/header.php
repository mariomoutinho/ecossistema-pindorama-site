<?php
// ================================
// Header da área restrita de terapeutas.
// Espera: $pageTitle, $activeApp (dashboard|agenda|evolucoes|lembretes|login),
//         $terapeutaLogado (array|null) — fornecido pelo bootstrap.
// ================================
$pageTitle  = $pageTitle ?? 'Área restrita • Espaço Pindorama';
$activeApp  = $activeApp ?? '';
$showSidebar = isset($terapeutaLogado) && $terapeutaLogado !== null;

$navItems = [
  'dashboard'  => ['label' => 'Início',       'href' => 'index.php',      'icon' => '◉'],
  'pacientes'  => ['label' => 'Pacientes',    'href' => 'pacientes.php',  'icon' => '◍'],
  'agenda'     => ['label' => 'Agenda',       'href' => 'agenda.php',     'icon' => '◗'],
  'evolucoes'  => ['label' => 'Evoluções',    'href' => 'evolucoes.php',  'icon' => '◇'],
  'lembretes'  => ['label' => 'Lembretes',    'href' => 'lembretes.php',  'icon' => '◐'],
  'conta'      => ['label' => 'Segurança da conta', 'href' => 'conta.php', 'icon' => '◔'],
];

// Item exclusivo de administradores (spec §7.1) — só aparece para quem é admin.
$ehAdmin = isset($terapeutaLogado) && function_exists('auth_is_admin') && auth_is_admin($terapeutaLogado);
if ($ehAdmin) {
  $navItems['equipe'] = ['label' => 'Gestão da equipe', 'href' => 'equipe.php', 'icon' => '◈'];
}

// Rótulo do perfil para exibição discreta (spec §7.2).
$perfilLabel = (isset($terapeutaLogado) && function_exists('auth_papel_label'))
  ? auth_papel_label($terapeutaLogado['papel'] ?? 'terapeuta')
  : 'Terapeuta';
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#0E1C17" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-pindorama.svg" />
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/terapeutas.css">
</head>
<body class="terap-body<?= $showSidebar ? ' terap-body--app' : ' terap-body--auth' ?>">

<?php if ($showSidebar): ?>
<header class="terap-topbar">
  <div class="terap-topbar__inner">
    <a class="terap-brand" href="index.php">
      <img src="../assets/img/logo-pindorama.svg" alt="Pindorama" class="terap-brand__logo">
      <div>
        <strong>Espaço Pindorama</strong>
        <span>Área dos terapeutas</span>
      </div>
    </a>

    <button class="terap-burger" id="terapBurger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="terapSidebar">
      <span></span><span></span><span></span>
    </button>

    <div class="terap-topbar__user">
      <span class="terap-avatar" aria-hidden="true"><?= htmlspecialchars(mb_substr($terapeutaLogado['nome'] ?? 'P', 0, 1, 'UTF-8')) ?></span>
      <div class="terap-topbar__userMeta">
        <strong><?= htmlspecialchars(explode(' ', trim($terapeutaLogado['nome'] ?? 'Terapeuta'))[0]) ?></strong>
        <span><?= htmlspecialchars($perfilLabel) ?></span>
      </div>
      <!-- No mobile estas ações somem do cabeçalho (CSS) e migram para o drawer. -->
      <a class="terap-btn terap-btn--ghost terap-topbar__act" href="conta.php" title="Segurança da conta">Minha senha</a>
      <a class="terap-btn terap-btn--ghost terap-topbar__act" href="logout.php" title="Sair da conta">Sair</a>
    </div>
  </div>
</header>

<div class="terap-shell">
  <aside class="terap-sidebar" id="terapSidebar" aria-label="Menu da área restrita">
    <nav>
      <ul>
        <?php foreach ($navItems as $key => $item): ?>
          <li>
            <a href="<?= htmlspecialchars($item['href']) ?>"
               class="<?= $activeApp === $key ? 'is-active' : '' ?>"
               <?= $activeApp === $key ? 'aria-current="page"' : '' ?>>
              <span class="terap-nav__icon" aria-hidden="true"><?= $item['icon'] ?></span>
              <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="terap-sidebar__foot">
      <a href="logout.php" class="terap-logout" title="Encerrar a sessão">
        <svg class="terap-logout__ico" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        <span>Sair da conta</span>
      </a>
      <a href="../index.php" class="terap-link">← Voltar ao site</a>
    </div>
  </aside>

  <main class="terap-main">
<?php else: ?>
  <main class="terap-auth-main">
<?php endif; ?>
