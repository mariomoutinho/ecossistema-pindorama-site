<?php
// ================================
// Login da área restrita dos terapeutas.
// ================================
require_once __DIR__ . '/bootstrap.php';

// Já logado? Manda direto pro painel.
if (auth_logged_in()) {
  header('Location: index.php');
  exit;
}

$erro = null;
$emailPre = '';
$next = isset($_GET['next']) ? (string)$_GET['next'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf'] ?? null;
  $emailPre = trim($_POST['email'] ?? '');
  $senha = (string)($_POST['senha'] ?? '');

  if (!auth_csrf_check($token)) {
    $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
  } elseif ($emailPre === '' || $senha === '') {
    $erro = 'Informe e-mail e senha.';
  } else {
    $u = auth_attempt($emailPre, $senha);
    if ($u) {
      // Senha temporária? Vai direto para a troca obrigatória.
      if (!empty($u['must_change_password'])) {
        header('Location: conta.php?primeiro_acesso=1');
        exit;
      }
      $destino = 'index.php';
      // Anti open-redirect: aceita apenas caminhos relativos sem esquema/host.
      if ($next !== '' && preg_match('#^[a-zA-Z0-9_\-./?=&%]+$#', $next) && strpos($next, '//') === false) {
        $destino = $next;
      }
      header('Location: ' . $destino);
      exit;
    }
    $erro = 'E-mail ou senha incorretos.';
  }
}

$pageTitle = 'Entrar • Espaço Pindorama';
$activeApp = 'login';
$csrf = auth_csrf_token();
require __DIR__ . '/partials/header.php';
?>

<div class="terap-auth-card">
  <img src="../assets/img/logo-pindorama.svg" alt="Pindorama" class="terap-auth-card__logo">
  <h1>Área dos terapeutas</h1>
  <p class="terap-auth-sub">Acesse com seu e-mail e senha para entrar no painel do Espaço Pindorama.</p>

  <?php if ($erro): ?>
    <div class="terap-alert terap-alert--error"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" class="terap-form" autocomplete="on" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="terap-field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required autofocus value="<?= htmlspecialchars($emailPre) ?>" placeholder="seu@email.com">
    </div>

    <div class="terap-field">
      <label for="senha">Senha</label>
      <input id="senha" name="senha" type="password" required placeholder="Sua senha">
    </div>

    <button class="terap-btn terap-btn--primary" type="submit">Entrar</button>
    <p style="font-size:12px;color:var(--muted);margin:6px 0 0;text-align:center;">
      Esqueceu? Fale com a coordenação pelo WhatsApp.
    </p>
  </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
