<?php
// ================================
// Gestão da equipe — tela administrativa (spec §5, §7).
// Acesso restrito a administradores (auth_require_admin). Toda regra de negócio
// vive em lib/admin.php; aqui só tratamos requisição/PRG/exibição.
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_admin('index.php');

$erros = [];

// ----------- POST: ações administrativas (PRG) -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'criar') {
      $res = admin_criar_usuario($terapeutaLogado, [
        'nome'          => $_POST['nome'] ?? '',
        'email'         => $_POST['email'] ?? '',
        'telefone'      => $_POST['telefone'] ?? '',
        'especialidade' => $_POST['especialidade'] ?? '',
        'papel'         => $_POST['papel'] ?? '',
        'senha'         => $_POST['senha'] ?? '',
        'confirma'      => $_POST['confirma'] ?? '',
      ]);
      if ($res['ok']) {
        flash_set('success', 'Usuário cadastrado. Informe a senha temporária ao novo usuário: no primeiro acesso ele será obrigado a definir uma nova senha pessoal.');
        header('Location: equipe.php');
        exit;
      }
      $erros = $res['erros'];

    } elseif ($acao === 'atualizar') {
      $res = admin_atualizar_dados($terapeutaLogado, $id, [
        'nome'          => $_POST['nome'] ?? '',
        'email'         => $_POST['email'] ?? '',
        'telefone'      => $_POST['telefone'] ?? '',
        'especialidade' => $_POST['especialidade'] ?? '',
      ]);
      if ($res['ok']) { flash_set('success', 'Dados atualizados.'); header('Location: equipe.php?editar=' . $id); exit; }
      $erros = $res['erros'];

    } elseif ($acao === 'papel') {
      $res = admin_alterar_papel($terapeutaLogado, $id, (string)($_POST['papel'] ?? ''));
      if ($res['ok']) { flash_set('success', 'Perfil atualizado.'); }
      else            { flash_set('error', $res['erros'][0] ?? 'Não foi possível alterar o perfil.'); }
      header('Location: equipe.php?editar=' . $id); exit;

    } elseif ($acao === 'ativar' || $acao === 'desativar') {
      $res = admin_definir_status($terapeutaLogado, $id, $acao === 'ativar');
      if ($res['ok']) { flash_set('success', $acao === 'ativar' ? 'Conta ativada.' : 'Conta desativada.'); }
      else            { flash_set('error', $res['erros'][0] ?? 'Não foi possível alterar o status.'); }
      header('Location: equipe.php'); exit;

    } elseif ($acao === 'redefinir_senha') {
      $res = admin_redefinir_senha_temp($terapeutaLogado, $id, (string)($_POST['senha'] ?? ''), (string)($_POST['confirma'] ?? ''));
      if ($res['ok']) { flash_set('success', 'Senha temporária redefinida. O usuário precisará trocá-la no próximo acesso.'); header('Location: equipe.php?editar=' . $id); exit; }
      $erros = $res['erros'];
    }
  }
}

// ----------- estado da tela -----------
$usuarios = admin_listar_usuarios();
$editarId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$editar   = $editarId ? admin_obter_usuario($editarId) : null;
$flash    = flash_get();
$csrf     = auth_csrf_token();
$papeis   = auth_papeis();

function equipe_badge_status(array $u): string {
  $ativo = !empty($u['ativo']);
  $cor = $ativo ? 'leaf' : 'clay';
  $txt = $ativo ? 'Ativa' : 'Desativada';
  return '<span class="terap-tag terap-tag--' . $cor . '">' . $txt . '</span>';
}
function equipe_fmt_data(string $iso): string {
  $ts = strtotime($iso);
  return $ts ? date('d/m/Y', $ts) : '—';
}

$pageTitle = 'Gestão da equipe • Espaço Pindorama';
$activeApp = 'equipe';
require __DIR__ . '/partials/header.php';
?>

<div class="terap-page-head">
  <div>
    <h1>Gestão da equipe</h1>
    <p>Cadastre e administre os perfis de <strong>Administrador</strong> e <strong>Terapeuta</strong> do Espaço Pindorama.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div class="terap-grid">

  <!-- LISTAGEM -->
  <section class="terap-card terap-span-12">
    <h2>Usuários da equipe</h2>
    <p style="margin-bottom:14px;">Perfis, status e primeiro acesso pendente. Use <strong>Detalhes</strong> para editar, redefinir senha temporária ou alterar o perfil.</p>

    <div class="terap-table-wrap" style="overflow-x:auto;">
      <table class="terap-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid rgba(255,255,255,.12);">
            <th style="padding:8px 10px;">Nome</th>
            <th style="padding:8px 10px;">E-mail</th>
            <th style="padding:8px 10px;">Perfil</th>
            <th style="padding:8px 10px;">Status</th>
            <th style="padding:8px 10px;">1º acesso</th>
            <th style="padding:8px 10px;">Criado em</th>
            <th style="padding:8px 10px;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u):
            $uid = (int)$u['id'];
            $ativo = !empty($u['ativo']);
            $souEu = $uid === (int)$terapeutaLogado['id'];
          ?>
          <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
            <td style="padding:8px 10px;"><?= htmlspecialchars($u['nome'] ?? '—') ?><?= $souEu ? ' <span style="color:var(--muted);font-size:12px;">(você)</span>' : '' ?></td>
            <td style="padding:8px 10px;"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td style="padding:8px 10px;"><?= htmlspecialchars(auth_papel_label($u['papel'] ?? 'terapeuta')) ?></td>
            <td style="padding:8px 10px;"><?= equipe_badge_status($u) ?></td>
            <td style="padding:8px 10px;"><?= !empty($u['must_change_password']) ? '<span class="terap-tag terap-tag--sand">Pendente</span>' : '—' ?></td>
            <td style="padding:8px 10px;"><?= htmlspecialchars(equipe_fmt_data((string)($u['criado_em'] ?? ''))) ?></td>
            <td style="padding:8px 10px;white-space:nowrap;">
              <a class="terap-link" href="equipe.php?editar=<?= $uid ?>">Detalhes</a>
              <?php if ($ativo): ?>
                <?php if (!$souEu): ?>
                  · <form method="post" style="display:inline;" onsubmit="return confirm('Desativar esta conta? O usuário não poderá entrar até ser reativado.');">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="acao" value="desativar">
                      <input type="hidden" name="id" value="<?= $uid ?>">
                      <button type="submit" class="terap-link" style="background:none;border:0;color:var(--clay2);cursor:pointer;padding:0;font:inherit;">Desativar</button>
                    </form>
                <?php endif; ?>
              <?php else: ?>
                · <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="acao" value="ativar">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button type="submit" class="terap-link" style="background:none;border:0;color:var(--leaf,#66b48f);cursor:pointer;padding:0;font:inherit;">Ativar</button>
                  </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($editar): $eid = (int)$editar['id']; $ehUltimoAdmin = admin_eh_ultimo_admin_ativo($eid); ?>
  <!-- PAINEL DE DETALHES / EDIÇÃO -->
  <section class="terap-card terap-span-6">
    <h2>Editar: <?= htmlspecialchars($editar['nome'] ?? '') ?></h2>
    <?php if ($ehUltimoAdmin): ?>
      <div class="terap-alert terap-alert--info">Este é o <strong>último administrador ativo</strong>: não é possível rebaixá-lo nem desativá-lo.</div>
    <?php endif; ?>

    <form method="post" class="terap-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="atualizar">
      <input type="hidden" name="id" value="<?= $eid ?>">
      <div class="terap-field">
        <label>Nome completo</label>
        <input name="nome" required maxlength="120" value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
      </div>
      <div class="terap-field">
        <label>E-mail</label>
        <input name="email" type="email" required value="<?= htmlspecialchars($editar['email'] ?? '') ?>">
      </div>
      <div class="terap-field">
        <label>Telefone</label>
        <input name="telefone" maxlength="20" value="<?= htmlspecialchars($editar['telefone'] ?? '') ?>">
      </div>
      <div class="terap-field">
        <label>Especialidade</label>
        <input name="especialidade" maxlength="80" value="<?= htmlspecialchars($editar['especialidade'] ?? '') ?>">
      </div>
      <button type="submit" class="terap-btn terap-btn--primary">Salvar dados</button>
      <a class="terap-link" href="equipe.php" style="margin-left:10px;">Fechar</a>
    </form>
  </section>

  <section class="terap-card terap-span-6">
    <h3>Perfil de acesso</h3>
    <form method="post" class="terap-form" style="margin-bottom:22px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="papel">
      <input type="hidden" name="id" value="<?= $eid ?>">
      <div class="terap-field">
        <label>Perfil</label>
        <select name="papel" <?= $ehUltimoAdmin ? 'disabled' : '' ?>>
          <?php foreach ($papeis as $val => $lab): ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= ($editar['papel'] ?? 'terapeuta') === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="terap-btn" <?= $ehUltimoAdmin ? 'disabled' : '' ?>>Alterar perfil</button>
    </form>

    <h3>Redefinir senha temporária</h3>
    <p style="margin-bottom:10px;color:var(--muted);font-size:13px;">Gera uma nova senha temporária. O usuário será obrigado a trocá-la no próximo acesso. A senha atual não é recuperável.</p>
    <form method="post" class="terap-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="redefinir_senha">
      <input type="hidden" name="id" value="<?= $eid ?>">
      <div class="terap-field">
        <label>Nova senha temporária</label>
        <input name="senha" type="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8, com letra e número">
      </div>
      <div class="terap-field">
        <label>Confirmar</label>
        <input name="confirma" type="password" required minlength="8" autocomplete="new-password">
      </div>
      <button type="submit" class="terap-btn">Redefinir senha</button>
    </form>
  </section>
  <?php endif; ?>

  <!-- CADASTRO DE NOVO USUÁRIO -->
  <section class="terap-card terap-span-12">
    <h2>Cadastrar novo usuário</h2>
    <p style="margin-bottom:14px;">A senha definida aqui é <strong>temporária</strong>: no primeiro acesso o usuário deverá criar a própria senha.</p>
    <form method="post" class="terap-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="criar">
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label>Nome completo *</label>
          <input name="nome" required maxlength="120">
        </div>
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label>E-mail *</label>
          <input name="email" type="email" required>
        </div>
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label>Perfil *</label>
          <select name="papel" required>
            <?php foreach ($papeis as $val => $lab): ?>
              <option value="<?= htmlspecialchars($val) ?>" <?= $val === 'terapeuta' ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label>Telefone</label>
          <input name="telefone" maxlength="20">
        </div>
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label>Senha temporária *</label>
          <input name="senha" type="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8, com letra e número">
        </div>
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label>Confirmar senha *</label>
          <input name="confirma" type="password" required minlength="8" autocomplete="new-password">
        </div>
      </div>
      <button type="submit" class="terap-btn terap-btn--primary">Cadastrar usuário</button>
    </form>
  </section>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
