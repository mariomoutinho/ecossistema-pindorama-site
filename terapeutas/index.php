<?php
// ================================
// Painel inicial do terapeuta — "feed" com saudação, próximos atendimentos,
// notificações da equipe e atalhos rápidos.
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

$agora      = time();
$hojeStr    = date('Y-m-d', $agora);
$emSeteDias = date('Y-m-d', strtotime('+7 days', $agora));

$todosAtend = store_all('agendamentos');

// Próximos atendimentos do terapeuta logado (até 7 dias)
$meusProximos = array_values(array_filter($todosAtend, function ($r) use ($terapeutaLogado, $hojeStr, $emSeteDias) {
  if ((int)($r['terapeuta_id'] ?? 0) !== (int)$terapeutaLogado['id']) return false;
  if (($r['status'] ?? '') === 'cancelado') return false;
  $d = $r['data'] ?? '';
  return $d >= $hojeStr && $d <= $emSeteDias;
}));
usort($meusProximos, fn($a, $b) => strcmp(($a['data'] ?? '') . ($a['hora_inicio'] ?? ''), ($b['data'] ?? '') . ($b['hora_inicio'] ?? '')));

// Atividades recentes (últimos agendamentos criados na casa por outros terapeutas)
$atividadesRecentes = $todosAtend;
usort($atividadesRecentes, fn($a, $b) => strcmp(($b['criado_em'] ?? ''), ($a['criado_em'] ?? '')));
$atividadesRecentes = array_slice($atividadesRecentes, 0, 6);

// Avisos
$avisos = store_all('notificacoes');
usort($avisos, function ($a, $b) {
  if (($a['fixada'] ?? false) !== ($b['fixada'] ?? false)) {
    return ($a['fixada'] ?? false) ? -1 : 1;
  }
  return strcmp($b['criado_em'] ?? '', $a['criado_em'] ?? '');
});

// Lembretes pendentes do terapeuta
$meusLembretes = store_where('lembretes', function ($r) use ($terapeutaLogado) {
  return (int)($r['terapeuta_id'] ?? 0) === (int)$terapeutaLogado['id'] && ($r['status'] ?? '') === 'pendente';
});
usort($meusLembretes, fn($a, $b) => strcmp($a['agendado_para'] ?? '', $b['agendado_para'] ?? ''));

// Helpers de UI
function nome_terapeuta(int $id): string {
  $t = store_find('terapeutas', $id);
  return $t['nome'] ?? 'Terapeuta';
}
function saudacao_hora(): string {
  $h = (int)date('H');
  if ($h < 12) return 'Bom dia';
  if ($h < 18) return 'Boa tarde';
  return 'Boa noite';
}
function fmt_data_curta(string $iso): string {
  $ts = strtotime($iso);
  if (!$ts) return $iso;
  return date('d/m', $ts);
}
function fmt_data_hora(string $data, string $hora): string {
  $ts = strtotime($data . ' ' . substr($hora, 0, 5));
  if (!$ts) return "$data $hora";
  return date('d/m', $ts) . ' às ' . substr($hora, 0, 5);
}

$primeiroNome = explode(' ', trim($terapeutaLogado['nome']))[0];

$pageTitle = 'Painel • Espaço Pindorama';
$activeApp = 'dashboard';
require __DIR__ . '/partials/header.php';
?>

<div class="terap-page-head">
  <div>
    <h1><?= htmlspecialchars(saudacao_hora()) ?>, <?= htmlspecialchars($primeiroNome) ?>! 🌿</h1>
    <p>O que está acontecendo hoje no Espaço Pindorama e nos seus atendimentos.</p>
  </div>
  <a class="terap-btn terap-btn--primary" href="agenda.php?novo=1">+ Novo atendimento</a>
</div>

<div class="terap-grid">

  <!-- ATALHOS -->
  <a class="terap-shortcut terap-span-3" href="pacientes.php">
    <span class="terap-tag terap-tag--leaf">Pacientes</span>
    <strong>Meus pacientes</strong>
    <span>Cadastrar, buscar e acompanhar fichas dos seus pacientes.</span>
  </a>
  <a class="terap-shortcut terap-span-3" href="agenda.php">
    <span class="terap-tag terap-tag--leaf">Agenda</span>
    <strong>Agenda da semana</strong>
    <span>Ver, marcar e gerenciar atendimentos do espaço.</span>
  </a>
  <a class="terap-shortcut terap-span-3" href="evolucoes.php">
    <span class="terap-tag terap-tag--sand">Evoluções</span>
    <strong>Registrar evolução</strong>
    <span>Documente como foi a sessão e como o paciente está.</span>
  </a>
  <a class="terap-shortcut terap-span-3" href="lembretes.php">
    <span class="terap-tag terap-tag--clay">Lembretes</span>
    <strong>WhatsApp programado</strong>
    <span>Veja os lembretes automáticos pendentes.</span>
  </a>
  <a class="terap-shortcut terap-span-3" href="agenda.php?novo=1">
    <span class="terap-tag">Novo</span>
    <strong>Marcar atendimento</strong>
    <span>Sala, horário, paciente — sem choque de horários.</span>
  </a>

  <!-- PRÓXIMOS ATENDIMENTOS -->
  <section class="terap-card terap-span-8">
    <h2>Seus próximos atendimentos</h2>
    <p style="margin-bottom:14px;">Próximos 7 dias, somente seus.</p>

    <?php if (!$meusProximos): ?>
      <div class="terap-alert terap-alert--info">
        Nenhum atendimento seu nos próximos 7 dias. <a class="terap-link" href="agenda.php?novo=1">Marcar agora</a>.
      </div>
    <?php else: ?>
      <div class="terap-feed">
        <?php foreach ($meusProximos as $a):
          $diaLabel = fmt_data_hora($a['data'] ?? '', $a['hora_inicio'] ?? '');
        ?>
          <div class="terap-feed__item">
            <div class="terap-feed__icon"><?= htmlspecialchars(substr($a['paciente'] ?? '?', 0, 1)) ?></div>
            <div class="terap-feed__body">
              <strong><?= htmlspecialchars($a['paciente'] ?? 'Sem identificação') ?></strong>
              <p><?= htmlspecialchars($salasDisponiveis[$a['sala'] ?? ''] ?? ($a['sala'] ?? '—')) ?> • <?= htmlspecialchars($diaLabel) ?></p>
              <?php if (!empty($a['observacoes'])): ?>
                <p style="opacity:.85"><?= htmlspecialchars(mb_strimwidth($a['observacoes'], 0, 140, '…', 'UTF-8')) ?></p>
              <?php endif; ?>
              <div class="terap-feed__meta">
                <a class="terap-link" href="evolucoes.php?nova=1&atendimento_id=<?= (int)$a['id'] ?>">Registrar evolução</a>
                · <a class="terap-link" href="agenda.php?editar=<?= (int)$a['id'] ?>">Editar</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- AVISOS -->
  <aside class="terap-card terap-span-4">
    <h2>Avisos do espaço</h2>
    <p style="margin-bottom:12px;">Recados importantes do Coletivo Pindorama.</p>
    <div class="terap-feed">
      <?php foreach (array_slice($avisos, 0, 5) as $av): ?>
        <div class="terap-feed__item">
          <div class="terap-feed__icon" style="background:rgba(244,231,211,.15);color:var(--sand);">!</div>
          <div class="terap-feed__body">
            <strong><?= htmlspecialchars($av['titulo'] ?? '') ?></strong>
            <p><?= htmlspecialchars($av['mensagem'] ?? '') ?></p>
            <div class="terap-feed__meta"><?= !empty($av['fixada']) ? 'Fixado · ' : '' ?><?= htmlspecialchars(fmt_data_curta($av['criado_em'] ?? '')) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- ATIVIDADE DA EQUIPE -->
  <section class="terap-card terap-span-8">
    <h2>Atividade recente da equipe</h2>
    <p style="margin-bottom:12px;">Quem marcou o quê — para você se situar no espaço.</p>

    <?php if (!$atividadesRecentes): ?>
      <div class="terap-alert terap-alert--info">Sem atividades registradas ainda.</div>
    <?php else: ?>
      <div class="terap-feed">
        <?php foreach ($atividadesRecentes as $a):
          $tNome = nome_terapeuta((int)($a['terapeuta_id'] ?? 0));
          $statusLabel = ($a['status'] ?? 'agendado') === 'cancelado' ? 'cancelou' : 'marcou';
        ?>
          <div class="terap-feed__item">
            <div class="terap-feed__icon" style="background:rgba(102,180,143,.18)"><?= htmlspecialchars(mb_substr($tNome, 0, 1, 'UTF-8')) ?></div>
            <div class="terap-feed__body">
              <strong><?= htmlspecialchars($tNome) ?> <span style="color:var(--muted);font-weight:400">— <?= $statusLabel ?> atendimento</span></strong>
              <p>
                Paciente: <?= htmlspecialchars($a['paciente'] ?? '—') ?>
                · <?= htmlspecialchars($salasDisponiveis[$a['sala'] ?? ''] ?? ($a['sala'] ?? '—')) ?>
                · <?= htmlspecialchars(fmt_data_hora($a['data'] ?? '', $a['hora_inicio'] ?? '')) ?>
              </p>
              <div class="terap-feed__meta"><?= htmlspecialchars(fmt_data_curta($a['criado_em'] ?? '')) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- LEMBRETES PENDENTES -->
  <aside class="terap-card terap-span-4">
    <h2>Seus lembretes (WhatsApp)</h2>
    <p style="margin-bottom:12px;">Programados para serem disparados — integração real virá em seguida.</p>

    <?php if (!$meusLembretes): ?>
      <div class="terap-alert terap-alert--info">Nenhum lembrete pendente.</div>
    <?php else: ?>
      <div class="terap-feed">
        <?php foreach (array_slice($meusLembretes, 0, 5) as $l): ?>
          <div class="terap-feed__item">
            <div class="terap-feed__icon" style="background:rgba(196,106,74,.18);color:var(--clay2)">♨</div>
            <div class="terap-feed__body">
              <strong><?= htmlspecialchars($l['tipo_label'] ?? $l['tipo'] ?? '') ?></strong>
              <p><?= htmlspecialchars(mb_strimwidth($l['mensagem'] ?? '', 0, 110, '…', 'UTF-8')) ?></p>
              <div class="terap-feed__meta">Agendado para <?= htmlspecialchars(fmt_data_curta($l['agendado_para'] ?? '')) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="margin-top:10px;"><a class="terap-link" href="lembretes.php">Ver todos os lembretes →</a></p>
    <?php endif; ?>
  </aside>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
