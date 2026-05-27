<?php
// ================================
// Evoluções de atendimentos.
// - Lista evoluções (mais recentes primeiro).
// - Permite registrar uma evolução (opcionalmente vinculada a um atendimento).
// - Apenas o terapeuta dono enxerga o detalhe completo; outros veem o resumo.
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

$erros = [];
$flash = flash_get();

// Pré-popular a partir de um atendimento (quando vem da agenda)
$atendId  = isset($_GET['atendimento_id']) ? (int)$_GET['atendimento_id'] : 0;
$novaEvol = isset($_GET['nova']);
$atendBase = $atendId ? store_find('agendamentos', $atendId) : null;

$valores = [
  'data'            => $atendBase['data'] ?? date('Y-m-d'),
  'atendimento_id'  => $atendId,
  'paciente'        => $atendBase['paciente'] ?? '',
  'demandas'        => '',
  'praticas'        => '',
  'descricao'       => '',
  'percepcao'       => '',
  'encaminhamentos' => '',
  'acompanhamento'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página.';
  } else {
    foreach (array_keys($valores) as $k) {
      $valores[$k] = trim((string)($_POST[$k] ?? ''));
    }

    if ($valores['paciente'] === '')        $erros[] = 'Informe o paciente.';
    if ($valores['descricao'] === '')       $erros[] = 'Descreva o que foi feito na sessão.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores['data'])) $erros[] = 'Data inválida.';

    if (!$erros) {
      store_insert('evolucoes', [
        'terapeuta_id'    => (int)$terapeutaLogado['id'],
        'atendimento_id'  => (int)$valores['atendimento_id'],
        'data'            => $valores['data'],
        'paciente'        => $valores['paciente'],
        'demandas'        => $valores['demandas'],
        'praticas'        => $valores['praticas'],
        'descricao'       => $valores['descricao'],
        'percepcao'       => $valores['percepcao'],
        'encaminhamentos' => $valores['encaminhamentos'],
        'acompanhamento'  => $valores['acompanhamento'],
      ]);
      flash_set('success', 'Evolução registrada com sucesso.');
      header('Location: evolucoes.php');
      exit;
    }
    $novaEvol = true;
  }
}

$minhas = store_where('evolucoes', fn($r) => (int)($r['terapeuta_id'] ?? 0) === (int)$terapeutaLogado['id']);
usort($minhas, fn($a, $b) => strcmp(($b['data'] ?? '') . ($b['criado_em'] ?? ''), ($a['data'] ?? '') . ($a['criado_em'] ?? '')));

$pageTitle = 'Evoluções • Espaço Pindorama';
$activeApp = 'evolucoes';
require __DIR__ . '/partials/header.php';
?>

<div class="terap-page-head">
  <div>
    <h1>Evoluções</h1>
    <p>Registro de como foram as sessões — vinculado a você e, quando possível, ao atendimento na agenda.</p>
  </div>
  <a class="terap-btn terap-btn--primary" href="evolucoes.php?nova=1">+ Registrar evolução</a>
</div>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<?php if ($novaEvol):
  $csrf = auth_csrf_token();
?>
  <section class="terap-card terap-span-12" style="margin-bottom:18px;">
    <h2>Nova evolução</h2>
    <p style="margin-bottom:14px;">As evoluções ficam vinculadas a você. Vincular a um atendimento ajuda a montar o histórico.</p>

    <form method="post" class="terap-form" action="evolucoes.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="atendimento_id" value="<?= (int)$valores['atendimento_id'] ?>">

      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:160px;">
          <label for="data">Data do atendimento</label>
          <input id="data" type="date" name="data" required value="<?= htmlspecialchars($valores['data']) ?>">
        </div>
        <div class="terap-field" style="flex:2;min-width:200px;">
          <label for="paciente">Identificação do paciente *</label>
          <input id="paciente" name="paciente" required value="<?= htmlspecialchars($valores['paciente']) ?>">
        </div>
      </div>

      <?php if ($atendBase): ?>
        <div class="terap-alert terap-alert--info">
          Vinculada ao atendimento #<?= (int)$atendBase['id'] ?> em <?= htmlspecialchars($atendBase['data']) ?> · <?= htmlspecialchars(substr($atendBase['hora_inicio'], 0, 5)) ?>.
        </div>
      <?php endif; ?>

      <div class="terap-field">
        <label for="demandas">Demandas apresentadas</label>
        <textarea id="demandas" name="demandas" rows="2" placeholder="O que a pessoa trouxe hoje?"><?= htmlspecialchars($valores['demandas']) ?></textarea>
      </div>

      <div class="terap-field">
        <label for="praticas">Práticas realizadas</label>
        <textarea id="praticas" name="praticas" rows="2" placeholder="Massagem? Auriculo? Escuta? Vivência?"><?= htmlspecialchars($valores['praticas']) ?></textarea>
      </div>

      <div class="terap-field">
        <label for="descricao">Descrição do que foi feito *</label>
        <textarea id="descricao" name="descricao" rows="3" required><?= htmlspecialchars($valores['descricao']) ?></textarea>
      </div>

      <div class="terap-field">
        <label for="percepcao">Percepção de como o paciente está</label>
        <textarea id="percepcao" name="percepcao" rows="2"><?= htmlspecialchars($valores['percepcao']) ?></textarea>
      </div>

      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label for="encaminhamentos">Encaminhamentos / observações</label>
          <textarea id="encaminhamentos" name="encaminhamentos" rows="2"><?= htmlspecialchars($valores['encaminhamentos']) ?></textarea>
        </div>
        <div class="terap-field" style="flex:1;min-width:240px;">
          <label for="acompanhamento">Acompanhamento posterior</label>
          <textarea id="acompanhamento" name="acompanhamento" rows="2" placeholder="O que observar até a próxima sessão?"><?= htmlspecialchars($valores['acompanhamento']) ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="terap-btn terap-btn--primary">Salvar evolução</button>
        <a href="evolucoes.php" class="terap-btn terap-btn--ghost">Cancelar</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<section class="terap-card terap-span-12">
  <h2>Suas evoluções (<?= count($minhas) ?>)</h2>
  <p style="margin-bottom:12px;">As mais recentes primeiro. Conteúdo visível apenas para você.</p>

  <?php if (!$minhas): ?>
    <div class="terap-alert terap-alert--info">Você ainda não registrou nenhuma evolução. <a class="terap-link" href="evolucoes.php?nova=1">Começar agora</a>.</div>
  <?php else: ?>
    <div class="terap-feed">
      <?php foreach ($minhas as $ev):
        $vinc = $ev['atendimento_id'] ? store_find('agendamentos', (int)$ev['atendimento_id']) : null;
      ?>
        <div class="terap-feed__item">
          <div class="terap-feed__icon"><?= htmlspecialchars(mb_substr($ev['paciente'] ?? '?', 0, 1, 'UTF-8')) ?></div>
          <div class="terap-feed__body">
            <strong>
              <?= htmlspecialchars($ev['paciente'] ?? '—') ?>
              <span style="color:var(--muted);font-weight:400">· <?= htmlspecialchars(date('d/m/Y', strtotime($ev['data']))) ?></span>
            </strong>
            <?php if (!empty($ev['demandas'])): ?><p><strong>Demandas:</strong> <?= nl2br(htmlspecialchars($ev['demandas'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['praticas'])): ?><p><strong>Práticas:</strong> <?= nl2br(htmlspecialchars($ev['praticas'])) ?></p><?php endif; ?>
            <p><strong>Descrição:</strong> <?= nl2br(htmlspecialchars($ev['descricao'])) ?></p>
            <?php if (!empty($ev['percepcao'])): ?><p><strong>Percepção:</strong> <?= nl2br(htmlspecialchars($ev['percepcao'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['encaminhamentos'])): ?><p><strong>Encaminhamentos:</strong> <?= nl2br(htmlspecialchars($ev['encaminhamentos'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['acompanhamento'])): ?><p><strong>Acompanhamento:</strong> <?= nl2br(htmlspecialchars($ev['acompanhamento'])) ?></p><?php endif; ?>
            <?php if ($vinc): ?>
              <div class="terap-feed__meta">
                Vinculada ao atendimento de <?= htmlspecialchars(date('d/m', strtotime($vinc['data']))) ?> ·
                <a class="terap-link" href="agenda.php?editar=<?= (int)$vinc['id'] ?>">Ver na agenda</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
