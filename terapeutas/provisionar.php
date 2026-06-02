<?php
// ================================
// Provisionamento VIA WEB do terapeuta inicial — para ambientes SEM acesso
// a terminal/SSH (ex.: Hostinger via File Manager).
//
// SEGURANÇA:
//   - Fica INERTE por padrão: só funciona se existir um token configurado em
//     terapeutas/config-provision.php (gitignored) ou na variável de ambiente
//     TERAP_PROVISION_TOKEN. Sem token => 403.
//   - A senha é digitada no formulário (sob HTTPS), vira hash na hora e NUNCA
//     é gravada em arquivo nem versionada. Só o token fica no arquivo.
//   - Idempotente (não duplica) e marca troca obrigatória de senha.
//
// USO (uma vez):
//   1. Crie terapeutas/config-provision.php no servidor (copie do .example).
//   2. Acesse  /terapeutas/provisionar.php?token=SEU_TOKEN
//   3. Confirme nome/e-mail, digite a senha temporária e envie.
//   4. APAGUE config-provision.php e provisionar.php do servidor.
// ================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/provision.php';

// Carrega o token do arquivo gitignored, se existir.
$cfg = __DIR__ . '/config-provision.php';
if (is_file($cfg)) require_once $cfg;

$token = (string)terap_env('TERAP_PROVISION_TOKEN', '');

// Inerte se não configurado.
if ($token === '') {
  http_response_code(403);
  echo 'Provisionamento desabilitado.';
  exit;
}

// Token deve ser fornecido e bater.
$fornecido = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if ($fornecido === '' || !hash_equals($token, $fornecido)) {
  http_response_code(403);
  echo 'Acesso negado.';
  exit;
}

// Valores padrão (podem vir do ambiente; senão, do terapeuta inicial).
$defNome  = (string)terap_env('INITIAL_THERAPIST_NAME', 'Luiz Mario Barros Moutinho');
$defEmail = (string)terap_env('INITIAL_THERAPIST_EMAIL', 'luizmariomoutinho1@gmail.com');

$erro = null;
$ok   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    $nome  = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $res = provision_terapeuta($nome, $email, $senha);
    if ($res['status'] === 'error') {
      $erro = $res['message'];
    } else {
      $ok = $res;
    }
  }
}

$pageTitle = 'Provisionamento • Espaço Pindorama';
$activeApp = 'login';
$csrf = auth_csrf_token();
require __DIR__ . '/partials/header.php';
?>

<div class="terap-auth-card" style="text-align:left;width:min(480px,100%);">
  <h1 style="text-align:center;">Provisionar terapeuta</h1>
  <p class="terap-auth-sub" style="text-align:center;">Use uma vez e depois apague este arquivo do servidor.</p>

  <?php if ($erro): ?>
    <div class="terap-alert terap-alert--error"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="terap-alert terap-alert--success">
      <?= htmlspecialchars($ok['message']) ?>
    </div>
    <div class="terap-alert terap-alert--info">
      <strong>Importante:</strong> por segurança, apague agora do servidor os arquivos
      <code>terapeutas/provisionar.php</code> e <code>terapeutas/config-provision.php</code>.
    </div>
    <a class="terap-btn terap-btn--primary" href="login.php" style="width:100%;">Ir para o login</a>
  <?php else: ?>
    <form method="post" class="terap-form" action="provisionar.php?token=<?= htmlspecialchars(urlencode($token)) ?>" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="terap-field">
        <label for="nome">Nome completo</label>
        <input id="nome" name="nome" required value="<?= htmlspecialchars($defNome) ?>">
      </div>
      <div class="terap-field">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($defEmail) ?>">
      </div>
      <div class="terap-field">
        <label for="senha">Senha temporária</label>
        <input id="senha" name="senha" type="password" required minlength="8" autocomplete="new-password" placeholder="Senha inicial (será trocada no 1º acesso)">
      </div>
      <button type="submit" class="terap-btn terap-btn--primary">Provisionar</button>
      <p class="pac-help" style="margin-top:6px;">A senha vira hash na hora e não é gravada em arquivo. No primeiro login, o terapeuta define a senha definitiva por código de e-mail.</p>
    </form>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
