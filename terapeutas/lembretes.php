<?php
// ================================
// Tela de "Lembretes programados" — fila de mensagens de WhatsApp.
// No MVP, exibe o que está pendente. Permite marcar como enviado manualmente
// ("já mandei pelo meu celular") ou descartar.
//
// >>> A integração real com API de WhatsApp deve ser feita dentro de
//     whatsapp_send_real() em lib/whatsapp.php, e disparada por um worker/cron
//     que percorra os lembretes com agendado_para <= now() e status = pendente.
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

$flash = flash_get();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página.';
  } else {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    $lemb = store_find('lembretes', $id);
    if (!$lemb) {
      $erros[] = 'Lembrete não encontrado.';
    } elseif ($acao === 'enviado') {
      store_update('lembretes', $id, ['status' => 'enviado', 'enviado_em' => date('c'), 'enviado_via' => 'manual']);
      flash_set('success', 'Lembrete marcado como enviado.');
      header('Location: lembretes.php');
      exit;
    } elseif ($acao === 'descartar') {
      store_update('lembretes', $id, ['status' => 'descartado']);
      flash_set('success', 'Lembrete descartado.');
      header('Location: lembretes.php');
      exit;
    }
  }
}

$todos = store_all('lembretes');

// Filtros simples por query string
$filtro = $_GET['status'] ?? 'pendente';
if (!in_array($filtro, ['pendente', 'enviado', 'descartado', 'todos'], true)) $filtro = 'pendente';

$lista = array_filter($todos, function ($r) use ($filtro, $terapeutaLogado) {
  if ((int)($r['terapeuta_id'] ?? 0) !== (int)$terapeutaLogado['id']) return false;
  if ($filtro === 'todos') return true;
  return ($r['status'] ?? '') === $filtro;
});
usort($lista, fn($a, $b) => strcmp($a['agendado_para'] ?? '', $b['agendado_para'] ?? ''));

$contagens = [
  'pendente'   => 0,
  'enviado'    => 0,
  'descartado' => 0,
];
foreach ($todos as $r) {
  if ((int)($r['terapeuta_id'] ?? 0) !== (int)$terapeutaLogado['id']) continue;
  $s = $r['status'] ?? '';
  if (isset($contagens[$s])) $contagens[$s]++;
}

$pageTitle = 'Lembretes WhatsApp • Espaço Pindorama';
$activeApp = 'lembretes';
require __DIR__ . '/partials/header.php';
?>

<div class="terap-page-head">
  <div>
    <h1>Lembretes de WhatsApp</h1>
    <p>Mensagens montadas automaticamente para cada atendimento. <strong>Envio real ainda não conectado</strong> — por enquanto, copie e mande pelo seu WhatsApp, ou aguarde a integração com API.</p>
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <a class="terap-btn terap-btn--sm <?= $filtro === 'pendente' ? 'terap-btn--primary' : '' ?>" href="?status=pendente">Pendentes (<?= $contagens['pendente'] ?>)</a>
    <a class="terap-btn terap-btn--sm <?= $filtro === 'enviado' ? 'terap-btn--primary' : '' ?>" href="?status=enviado">Enviados (<?= $contagens['enviado'] ?>)</a>
    <a class="terap-btn terap-btn--sm <?= $filtro === 'descartado' ? 'terap-btn--primary' : '' ?>" href="?status=descartado">Descartados (<?= $contagens['descartado'] ?>)</a>
    <a class="terap-btn terap-btn--sm <?= $filtro === 'todos' ? 'terap-btn--primary' : '' ?>" href="?status=todos">Todos</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div class="terap-alert terap-alert--info">
  <strong>Como funciona:</strong> ao marcar um atendimento na agenda, três mensagens são geradas — 1 dia antes, no dia, e 2 dias depois.
  Quando ligarmos a API (Z-API, Twilio, WhatsApp Cloud API ou Evolution API),
  cada item desta fila será disparado automaticamente. A função que vai cuidar disso
  já existe: <code>whatsapp_send_real()</code> em <code>lib/whatsapp.php</code>.
</div>

<section class="terap-card terap-span-12">
  <h2>Seus lembretes (<?= count($lista) ?>)</h2>

  <?php if (!$lista): ?>
    <p style="color:var(--muted);">Nenhum lembrete nesse filtro.</p>
  <?php else: ?>
    <div class="terap-feed">
      <?php foreach ($lista as $l):
        $atend = $l['atendimento_id'] ? store_find('agendamentos', (int)$l['atendimento_id']) : null;
        $waLink = !empty($l['telefone']) ? ('https://wa.me/' . preg_replace('/\D/', '', $l['telefone']) . '?text=' . rawurlencode($l['mensagem'] ?? '')) : null;
      ?>
        <div class="terap-feed__item">
          <div class="terap-feed__icon" style="background:rgba(196,106,74,.18);color:var(--clay2)">♨</div>
          <div class="terap-feed__body">
            <strong>
              <?= htmlspecialchars($l['tipo_label'] ?? $l['tipo']) ?>
              <span style="color:var(--muted);font-weight:400">·
                Agendado para <?= htmlspecialchars(date('d/m H:i', strtotime($l['agendado_para'] ?? 'now'))) ?>
              </span>
            </strong>
            <p style="white-space:pre-wrap"><?= htmlspecialchars($l['mensagem']) ?></p>
            <?php if ($atend): ?>
              <div class="terap-feed__meta">
                Atendimento de <?= htmlspecialchars($atend['paciente']) ?> em <?= htmlspecialchars(date('d/m H:i', strtotime($atend['data'] . ' ' . $atend['hora_inicio']))) ?>.
              </div>
            <?php endif; ?>

            <?php if (($l['status'] ?? 'pendente') === 'pendente'): ?>
              <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <?php if ($waLink): ?>
                  <a class="terap-btn terap-btn--sm terap-btn--primary" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener">Abrir no WhatsApp</a>
                <?php endif; ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                  <input type="hidden" name="acao" value="enviado">
                  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                  <button class="terap-btn terap-btn--sm" type="submit">Marcar como enviado</button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Descartar este lembrete?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                  <input type="hidden" name="acao" value="descartar">
                  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                  <button class="terap-btn terap-btn--sm terap-btn--danger" type="submit">Descartar</button>
                </form>
              </div>
            <?php else: ?>
              <div class="terap-feed__meta" style="margin-top:6px;">
                Status: <span class="terap-tag <?= ($l['status'] === 'enviado') ? 'terap-tag--leaf' : 'terap-tag--clay' ?>"><?= htmlspecialchars($l['status']) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
