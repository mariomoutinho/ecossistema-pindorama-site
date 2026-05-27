<?php
// ================================
// Agenda do Espaço Pindorama.
// - Lista visão semanal (segunda a domingo).
// - Cria/edita atendimentos com validação de conflito por sala+horário.
// - Cancela atendimentos (e remove lembretes correspondentes).
// - Ao salvar, enfileira automaticamente os 3 lembretes de WhatsApp.
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

// ----------- helpers -----------
function inicio_semana(string $isoDate): string {
  $ts = strtotime($isoDate);
  $dow = (int)date('N', $ts); // 1=segunda ... 7=domingo
  return date('Y-m-d', strtotime("-" . ($dow - 1) . " day", $ts));
}
function fmt_data_pt(string $isoDate): string {
  $ts = strtotime($isoDate);
  $meses = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
  return (int)date('d', $ts) . ' de ' . $meses[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}
function ha_conflito(array $todos, string $data, string $hi, string $hf, string $sala, ?int $ignorarId = null): bool {
  $hiNew = strtotime($data . ' ' . $hi);
  $hfNew = strtotime($data . ' ' . $hf);
  foreach ($todos as $a) {
    if ($ignorarId !== null && (int)$a['id'] === $ignorarId) continue;
    if (($a['status'] ?? '') === 'cancelado') continue;
    if (($a['data'] ?? '') !== $data) continue;
    if (($a['sala'] ?? '') !== $sala) continue;
    $hiE = strtotime($data . ' ' . substr($a['hora_inicio'] ?? '', 0, 5));
    $hfE = strtotime($data . ' ' . substr($a['hora_fim']    ?? '', 0, 5));
    if ($hiE === false || $hfE === false) continue;
    // overlap: começa antes do outro terminar e termina depois do outro começar
    if ($hiNew < $hfE && $hfNew > $hiE) return true;
  }
  return false;
}

// ----------- estado -----------
$erros   = [];
$avisoOk = null;
$editarId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$mostrarForm = isset($_GET['novo']) || $editarId > 0;
$registroEdit = $editarId ? store_find('agendamentos', $editarId) : null;

// ----------- POST: salvar / cancelar -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página.';
  } else {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cancelar') {
      $id = (int)($_POST['id'] ?? 0);
      $atend = store_find('agendamentos', $id);
      if ($atend) {
        store_update('agendamentos', $id, ['status' => 'cancelado']);
        whats_remover_de_atendimento($id);
        flash_set('success', 'Atendimento cancelado e lembretes removidos.');
      } else {
        flash_set('error', 'Atendimento não encontrado.');
      }
      header('Location: agenda.php');
      exit;
    }

    if ($acao === 'salvar') {
      $id          = (int)($_POST['id'] ?? 0);
      $data        = trim($_POST['data'] ?? '');
      $hi          = trim($_POST['hora_inicio'] ?? '');
      $hf          = trim($_POST['hora_fim'] ?? '');
      $sala        = trim($_POST['sala'] ?? '');
      $terapId     = (int)($_POST['terapeuta_id'] ?? $terapeutaLogado['id']);
      $paciente    = trim($_POST['paciente'] ?? '');
      $observacoes = trim($_POST['observacoes'] ?? '');

      if ($data === '' || $hi === '' || $hf === '' || $sala === '' || $paciente === '') {
        $erros[] = 'Preencha data, horários, sala e identificação do paciente.';
      }
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $erros[] = 'Data inválida.';
      if (!preg_match('/^\d{2}:\d{2}$/', $hi))           $erros[] = 'Hora de início inválida.';
      if (!preg_match('/^\d{2}:\d{2}$/', $hf))           $erros[] = 'Hora de fim inválida.';
      if (!isset($salasDisponiveis[$sala]))              $erros[] = 'Sala/espaço inválido.';

      if (!$erros) {
        if (strtotime($data . ' ' . $hf) <= strtotime($data . ' ' . $hi)) {
          $erros[] = 'Hora de fim deve ser maior que hora de início.';
        }
      }

      if (!$erros) {
        $todos = store_all('agendamentos');
        if (ha_conflito($todos, $data, $hi, $hf, $sala, $id ?: null)) {
          $erros[] = 'Já existe outro atendimento nesse horário e sala. Escolha outro horário ou sala.';
        }
      }

      if (!$erros) {
        $payload = [
          'data'         => $data,
          'hora_inicio'  => $hi,
          'hora_fim'     => $hf,
          'sala'         => $sala,
          'terapeuta_id' => $terapId,
          'paciente'     => $paciente,
          'observacoes'  => $observacoes,
          'status'       => 'agendado',
        ];

        if ($id) {
          $atualizado = store_update('agendamentos', $id, $payload);
          // Regenera lembretes (remove antigos e cria de novo)
          whats_remover_de_atendimento($id);
          whats_enfileirar_para_atendimento($atualizado, store_find('terapeutas', $terapId));
          flash_set('success', 'Atendimento atualizado.');
        } else {
          $criado = store_insert('agendamentos', $payload);
          whats_enfileirar_para_atendimento($criado, store_find('terapeutas', $terapId));
          flash_set('success', 'Atendimento marcado e lembretes programados.');
        }

        header('Location: agenda.php');
        exit;
      } else {
        // Repopular form com os valores enviados
        $registroEdit = [
          'id'           => $id,
          'data'         => $data,
          'hora_inicio'  => $hi,
          'hora_fim'     => $hf,
          'sala'         => $sala,
          'terapeuta_id' => $terapId,
          'paciente'     => $paciente,
          'observacoes'  => $observacoes,
        ];
        $mostrarForm = true;
      }
    }
  }
}

// ----------- semana visualizada -----------
$semanaParam = isset($_GET['semana']) ? trim((string)$_GET['semana']) : '';
$baseDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $semanaParam) ? $semanaParam : date('Y-m-d');
$inicio   = inicio_semana($baseDate);
$diasSem  = [];
for ($i = 0; $i < 7; $i++) {
  $diasSem[] = date('Y-m-d', strtotime("+$i day", strtotime($inicio)));
}
$prevSemana = date('Y-m-d', strtotime('-7 day', strtotime($inicio)));
$nextSemana = date('Y-m-d', strtotime('+7 day', strtotime($inicio)));

// Eventos da semana
$todosAtend = store_all('agendamentos');
$eventosPorDia = array_fill_keys($diasSem, []);
foreach ($todosAtend as $a) {
  if (($a['status'] ?? '') === 'cancelado') continue;
  $d = $a['data'] ?? '';
  if (isset($eventosPorDia[$d])) $eventosPorDia[$d][] = $a;
}

$terapeutasAtivos = store_where('terapeutas', fn($r) => !empty($r['ativo']));
$flash = flash_get();

$pageTitle = 'Agenda • Espaço Pindorama';
$activeApp = 'agenda';
require __DIR__ . '/partials/header.php';

$horasGrade = range(7, 21); // 07h–21h
$diasLabel  = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
?>

<div class="terap-page-head">
  <div>
    <h1>Agenda do espaço</h1>
    <p>Visão semanal · começa na segunda · marcações bloqueiam choque de horários por sala.</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="terap-btn" href="agenda.php?semana=<?= htmlspecialchars($prevSemana) ?>">← Semana</a>
    <a class="terap-btn" href="agenda.php">Hoje</a>
    <a class="terap-btn" href="agenda.php?semana=<?= htmlspecialchars($nextSemana) ?>">Semana →</a>
    <a class="terap-btn terap-btn--primary" href="agenda.php?novo=1">+ Novo atendimento</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<?php if ($mostrarForm):
  $csrf = auth_csrf_token();
  $valData    = $registroEdit['data']         ?? date('Y-m-d');
  $valHi      = $registroEdit['hora_inicio']  ?? '09:00';
  $valHf      = $registroEdit['hora_fim']     ?? '10:00';
  $valSala    = $registroEdit['sala']         ?? 'sala-1';
  $valTerap   = (int)($registroEdit['terapeuta_id'] ?? $terapeutaLogado['id']);
  $valPaciente= $registroEdit['paciente']     ?? '';
  $valObs     = $registroEdit['observacoes']  ?? '';
  $valId      = (int)($registroEdit['id']     ?? 0);
?>
  <section class="terap-card terap-span-12" style="margin-bottom:18px;">
    <h2><?= $valId ? 'Editar atendimento' : 'Novo atendimento' ?></h2>
    <p style="margin-bottom:14px;">Sala/espaço com horário ocupado será bloqueado automaticamente.</p>

    <form method="post" class="terap-form" action="agenda.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= $valId ?>">

      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:140px;">
          <label for="data">Data</label>
          <input id="data" type="date" name="data" required value="<?= htmlspecialchars($valData) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:120px;">
          <label for="hora_inicio">Início</label>
          <input id="hora_inicio" type="time" name="hora_inicio" required value="<?= htmlspecialchars($valHi) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:120px;">
          <label for="hora_fim">Fim</label>
          <input id="hora_fim" type="time" name="hora_fim" required value="<?= htmlspecialchars($valHf) ?>">
        </div>
      </div>

      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:220px;">
          <label for="sala">Sala / espaço</label>
          <select id="sala" name="sala" required>
            <?php foreach ($salasDisponiveis as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $valSala === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="terap-field" style="flex:1;min-width:220px;">
          <label for="terapeuta_id">Terapeuta</label>
          <select id="terapeuta_id" name="terapeuta_id" required>
            <?php foreach ($terapeutasAtivos as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $valTerap === (int)$t['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="terap-field">
        <label for="paciente">Identificação do paciente *</label>
        <input id="paciente" name="paciente" required maxlength="120" value="<?= htmlspecialchars($valPaciente) ?>" placeholder="Ex.: Maria S. (cuidado: evitar dados sensíveis no nome)">
      </div>

      <div class="terap-field">
        <label for="observacoes">Observações</label>
        <textarea id="observacoes" name="observacoes" rows="3" placeholder="Anotações rápidas sobre a sessão, ajustes de horário etc."><?= htmlspecialchars($valObs) ?></textarea>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="terap-btn terap-btn--primary"><?= $valId ? 'Salvar alterações' : 'Marcar atendimento' ?></button>
        <a href="agenda.php" class="terap-btn terap-btn--ghost">Cancelar</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<!-- VISÃO SEMANAL -->
<section class="terap-card terap-span-12">
  <h2>Semana de <?= htmlspecialchars(fmt_data_pt($inicio)) ?></h2>
  <p style="margin-bottom:14px;">Cada coluna é um dia; cada bloco colorido é um atendimento. Clique para editar.</p>

  <div class="terap-week" role="table" aria-label="Agenda semanal">
    <div class="terap-week__hourHead">Hora</div>
    <?php foreach ($diasSem as $i => $d):
      $isToday = $d === date('Y-m-d');
    ?>
      <div class="terap-week__dayHead <?= $isToday ? 'is-today' : '' ?>">
        <?= $diasLabel[$i] ?>
        <small><?= date('d/m', strtotime($d)) ?></small>
      </div>
    <?php endforeach; ?>

    <?php foreach ($horasGrade as $h): ?>
      <div class="terap-week__hour"><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?>h</div>
      <?php foreach ($diasSem as $d):
        $eventos = array_filter($eventosPorDia[$d] ?? [], function ($e) use ($h) {
          $eh = (int)substr($e['hora_inicio'] ?? '', 0, 2);
          return $eh === $h;
        });
      ?>
        <div class="terap-week__cell">
          <?php foreach ($eventos as $e):
            $mine = (int)($e['terapeuta_id'] ?? 0) === (int)$terapeutaLogado['id'];
            $cls = $mine ? '' : ' terap-week__event--sand';
            $tNome = store_find('terapeutas', (int)$e['terapeuta_id']);
            $tNomeStr = $tNome['nome'] ?? '—';
          ?>
            <a class="terap-week__event<?= $cls ?>" href="agenda.php?editar=<?= (int)$e['id'] ?>" title="Editar">
              <strong><?= htmlspecialchars(substr($e['hora_inicio'], 0, 5)) ?>–<?= htmlspecialchars(substr($e['hora_fim'], 0, 5)) ?></strong>
              <?= htmlspecialchars($e['paciente'] ?? '—') ?>
              <small><?= htmlspecialchars($salasDisponiveis[$e['sala'] ?? ''] ?? '') ?> · <?= htmlspecialchars(explode(' ', $tNomeStr)[0]) ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</section>

<!-- LISTA DETALHADA DA SEMANA -->
<section class="terap-card terap-span-12" style="margin-top:18px;">
  <h2>Atendimentos da semana</h2>
  <p style="margin-bottom:12px;">Lista completa para edição/cancelamento.</p>

  <?php
    $atendSemana = [];
    foreach ($diasSem as $d) {
      foreach ($eventosPorDia[$d] ?? [] as $e) $atendSemana[] = $e;
    }
    usort($atendSemana, fn($a, $b) => strcmp(($a['data'] ?? '') . ($a['hora_inicio'] ?? ''), ($b['data'] ?? '') . ($b['hora_inicio'] ?? '')));
  ?>

  <?php if (!$atendSemana): ?>
    <div class="terap-alert terap-alert--info">Nenhum atendimento ativo nesta semana.</div>
  <?php else: ?>
    <table class="terap-table">
      <thead>
        <tr>
          <th>Dia</th>
          <th>Horário</th>
          <th>Sala</th>
          <th>Terapeuta</th>
          <th>Paciente</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($atendSemana as $e):
          $tNome = store_find('terapeutas', (int)$e['terapeuta_id']);
          $tNomeStr = $tNome['nome'] ?? '—';
        ?>
          <tr>
            <td><?= htmlspecialchars(date('d/m', strtotime($e['data']))) ?> <span style="color:var(--muted)"><?= htmlspecialchars($diasLabel[(int)date('N', strtotime($e['data'])) - 1] ?? '') ?></span></td>
            <td><?= htmlspecialchars(substr($e['hora_inicio'], 0, 5)) ?>–<?= htmlspecialchars(substr($e['hora_fim'], 0, 5)) ?></td>
            <td><?= htmlspecialchars($salasDisponiveis[$e['sala'] ?? ''] ?? '') ?></td>
            <td><?= htmlspecialchars($tNomeStr) ?></td>
            <td><?= htmlspecialchars($e['paciente'] ?? '') ?></td>
            <td style="white-space:nowrap;">
              <a class="terap-btn terap-btn--sm" href="agenda.php?editar=<?= (int)$e['id'] ?>">Editar</a>
              <a class="terap-btn terap-btn--sm" href="evolucoes.php?nova=1&atendimento_id=<?= (int)$e['id'] ?>">Evolução</a>
              <form method="post" action="agenda.php" style="display:inline" onsubmit="return confirm('Cancelar este atendimento?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button class="terap-btn terap-btn--sm terap-btn--danger" type="submit">Cancelar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
