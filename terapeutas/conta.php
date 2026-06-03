<?php
// ================================
// Segurança da conta — troca de senha por código enviado ao e-mail.
// Também atende ao "primeiro acesso" (senha temporária obrigatória).
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

$erros = [];
$flash = flash_get();
$primeiroAcesso = auth_must_change_password();
$devCodigo = null; // só preenchido no transporte "log" (DEV)

// Mantém na sessão o instante do último envio, para mensagem de etapa.
$codigoEnviado = !empty($_SESSION['conta_codigo_enviado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    $acao = $_POST['acao'] ?? '';

    // Troca direta: senha atual + nova + confirmação (não depende de e-mail).
    // É o caminho do primeiro acesso (senha temporária conhecida pelo usuário).
    if ($acao === 'trocar_direta') {
      $atual    = (string)($_POST['senha_atual'] ?? '');
      $nova     = (string)($_POST['nova_senha'] ?? '');
      $confirma = (string)($_POST['confirma_senha'] ?? '');
      $res = account_trocar_senha_direta((int)$terapeutaLogado['id'], $atual, $nova, $confirma);
      if (!empty($res['ok'])) {
        flash_set('success', 'Senha alterada com sucesso. Use a nova senha nos próximos acessos.');
        header('Location: index.php');
        exit;
      }
      switch ($res['motivo']) {
        case 'atual_incorreta': $erros[] = 'A senha atual está incorreta.'; break;
        case 'confirma':        $erros[] = 'A confirmação não confere com a nova senha.'; break;
        case 'senha_igual':     $erros[] = 'A nova senha deve ser diferente da atual.'; break;
        case 'senha_fraca':     $erros[] = $res['detalhe'] ?? 'Senha fora da política mínima.'; break;
        default:                $erros[] = 'Não foi possível alterar a senha. Tente novamente.';
      }
    }

    if ($acao === 'solicitar') {
      $res = account_solicitar_codigo((int)$terapeutaLogado['id'], $devCodigo);
      if (!empty($res['ok'])) {
        $_SESSION['conta_codigo_enviado'] = time();
        $codigoEnviado = true;
        if ($devCodigo) {
          // Apenas DEV: facilita teste manual quando o transporte é "log".
          flash_set('info', 'Código gerado (modo DEV): ' . $devCodigo);
        } else {
          flash_set('success', 'Se o e-mail estiver cadastrado, você receberá um código em instantes. Ele expira em 10 minutos.');
        }
      } elseif (($res['motivo'] ?? '') === 'aguarde') {
        flash_set('info', 'Aguarde um instante antes de pedir um novo código.');
      } else {
        // Mensagem genérica — não revela se o e-mail existe.
        flash_set('success', 'Se o e-mail estiver cadastrado, você receberá um código em instantes.');
      }
      header('Location: conta.php' . ($primeiroAcesso ? '?primeiro_acesso=1' : ''));
      exit;
    }

    if ($acao === 'trocar') {
      $codigo  = (string)($_POST['codigo'] ?? '');
      $nova    = (string)($_POST['nova_senha'] ?? '');
      $confirma= (string)($_POST['confirma_senha'] ?? '');

      $res = account_trocar_senha((int)$terapeutaLogado['id'], $codigo, $nova, $confirma);
      if (!empty($res['ok'])) {
        unset($_SESSION['conta_codigo_enviado']);
        flash_set('success', 'Senha alterada com sucesso. Use a nova senha nos próximos acessos.');
        header('Location: index.php');
        exit;
      }
      switch ($res['motivo']) {
        case 'confirma':    $erros[] = 'A confirmação não confere com a nova senha.'; break;
        case 'senha_fraca': $erros[] = $res['detalhe'] ?? 'Senha fora da política mínima.'; break;
        case 'sem_codigo':  $erros[] = 'Nenhum código válido. Solicite um novo código.'; break;
        case 'expirado':    $erros[] = 'Código expirado. Solicite um novo código.'; break;
        case 'bloqueado':   $erros[] = 'Muitas tentativas. Solicite um novo código e tente de novo.'; break;
        case 'invalido':
          $rest = $res['restantes'] ?? null;
          $erros[] = 'Código inválido.' . ($rest !== null ? " Tentativas restantes: {$rest}." : '');
          break;
        default:            $erros[] = 'Não foi possível alterar a senha. Tente novamente.';
      }
    }
  }
}

// E-mail mascarado para a tela (não expõe o endereço completo).
function conta_mascarar_email(string $email): string {
  $at = strpos($email, '@');
  if ($at === false) return '***';
  $user = substr($email, 0, $at);
  $dom  = substr($email, $at);
  $vis  = mb_substr($user, 0, 2, 'UTF-8');
  return $vis . str_repeat('•', max(1, mb_strlen($user, 'UTF-8') - 2)) . $dom;
}
$emailMasc = conta_mascarar_email((string)($terapeutaLogado['email'] ?? ''));

$pageTitle = 'Segurança da conta • Espaço Pindorama';
$activeApp = 'conta';
$csrf = auth_csrf_token();
require __DIR__ . '/partials/header.php';
?>

<div class="terap-page-head">
  <div>
    <h1>Segurança da conta</h1>
    <p>Altere sua senha com um código de verificação enviado ao seu e-mail cadastrado.</p>
  </div>
</div>

<?php if ($primeiroAcesso): ?>
  <div class="terap-alert terap-alert--info">
    <strong>Primeiro acesso:</strong> sua senha atual é temporária. Defina uma nova senha para continuar usando o painel.
  </div>
<?php endif; ?>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div class="terap-grid">
  <section class="terap-card terap-span-12">
    <h2>Trocar a senha agora<?= $primeiroAcesso ? ' (recomendado)' : '' ?></h2>
    <p style="margin-bottom:14px;">Informe a sua senha atual<?= $primeiroAcesso ? ' (a temporária recebida)' : '' ?> e defina uma nova senha. Mínimo de 8 caracteres, com letra e número.</p>
    <form method="post" class="terap-form" action="conta.php<?= $primeiroAcesso ? '?primeiro_acesso=1' : '' ?>" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="trocar_direta">
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:220px;">
          <label for="senha_atual_d">Senha atual</label>
          <input id="senha_atual_d" name="senha_atual" type="password" required autocomplete="current-password" placeholder="Sua senha atual">
        </div>
        <div class="terap-field" style="flex:1;min-width:220px;">
          <label for="nova_senha_d">Nova senha</label>
          <input id="nova_senha_d" name="nova_senha" type="password" required minlength="8" autocomplete="new-password" placeholder="Nova senha">
        </div>
        <div class="terap-field" style="flex:1;min-width:220px;">
          <label for="confirma_senha_d">Confirmar nova senha</label>
          <input id="confirma_senha_d" name="confirma_senha" type="password" required minlength="8" autocomplete="new-password" placeholder="Repita a nova senha">
        </div>
      </div>
      <button type="submit" class="terap-btn terap-btn--primary">Salvar nova senha</button>
    </form>
  </section>

  <section class="terap-card terap-span-12" style="margin-top:6px;">
    <h2 style="margin-bottom:4px;">Ou troque por código de e-mail</h2>
    <p style="color:var(--muted);font-size:13px;margin:0;">Caso prefira (ou tenha esquecido a senha atual), receba um código no seu e-mail cadastrado.</p>
  </section>

  <section class="terap-card terap-span-6">
    <h2>1. Receber código</h2>
    <p style="margin-bottom:14px;">Enviaremos um código de 6 dígitos para <strong><?= htmlspecialchars($emailMasc) ?></strong>. Ele vale por 10 minutos.</p>
    <form method="post" class="terap-form" action="conta.php<?= $primeiroAcesso ? '?primeiro_acesso=1' : '' ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="solicitar">
      <button type="submit" class="terap-btn terap-btn--primary"><?= $codigoEnviado ? 'Reenviar código' : 'Enviar código por e-mail' ?></button>
    </form>
  </section>

  <section class="terap-card terap-span-6">
    <h2>2. Definir nova senha</h2>
    <p style="margin-bottom:14px;">Informe o código recebido e a nova senha (mínimo 8 caracteres, com letra e número).</p>
    <form method="post" class="terap-form" action="conta.php<?= $primeiroAcesso ? '?primeiro_acesso=1' : '' ?>" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="trocar">

      <div class="terap-field">
        <label for="codigo">Código de verificação</label>
        <input id="codigo" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required
               autocomplete="one-time-code" placeholder="000000">
      </div>
      <div class="terap-field">
        <label for="nova_senha">Nova senha</label>
        <input id="nova_senha" name="nova_senha" type="password" required minlength="8" autocomplete="new-password" placeholder="Nova senha">
      </div>
      <div class="terap-field">
        <label for="confirma_senha">Confirmar nova senha</label>
        <input id="confirma_senha" name="confirma_senha" type="password" required minlength="8" autocomplete="new-password" placeholder="Repita a nova senha">
      </div>

      <button type="submit" class="terap-btn terap-btn--primary">Alterar senha</button>
    </form>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
