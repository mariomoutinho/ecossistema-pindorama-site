<?php
// ================================
// Agenda do Espaço Pindorama.
// - Lista visão semanal (segunda a domingo).
// - Cria/edita/marca-realizado/cancela atendimentos.
// - Cancelamento NUNCA apaga: o registro fica com status="cancelado".
// - Apenas atendimentos com status "agendado" ou "realizado" bloqueiam horários;
//   "cancelado" é sempre ignorado pela checagem de conflito.
// - Ao salvar, enfileira automaticamente os 3 lembretes de WhatsApp.
// - Hover/touch em cada bloco mostra tooltip com dados do atendimento.
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
  // Delega para o lib (fonte única); preserva os pontos de chamada existentes.
  return agenda_ha_conflito($todos, $data, $hi, $hf, $sala, $ignorarId);
}
function status_label(string $s): string {
  return agenda_status_label($s);
}

// ----------- estado -----------
$erros   = [];
$isAdmin = agenda_is_admin($terapeutaLogado);
$editarId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$mostrarForm = isset($_GET['novo']) || $editarId > 0;
$registroEdit = $editarId ? store_find('agendamentos', $editarId) : null;

// Não-admin não pode nem abrir o formulário de edição de agendamento alheio.
if ($registroEdit && !agenda_pode_gerir($registroEdit, $terapeutaLogado)) {
  flash_set('error', 'Você só pode editar os seus próprios atendimentos.');
  header('Location: agenda.php');
  exit;
}

// Clonar: abre o formulário como NOVO, pré-preenchido com a fonte (sem salvar).
// A sessão do pacote só é consumida quando o novo atendimento for salvo.
$clonando = false;
$clonarId = isset($_GET['clonar']) ? (int)$_GET['clonar'] : 0;
if ($clonarId) {
  $fonte = store_find('agendamentos', $clonarId);
  if (!$fonte || !agenda_pode_gerir($fonte, $terapeutaLogado)) {
    flash_set('error', 'Atendimento para clonar não encontrado.');
    header('Location: agenda.php');
    exit;
  }
  $registroEdit = $fonte;
  unset($registroEdit['id']);          // vira um novo registro
  $registroEdit['status'] = 'agendado';
  $mostrarForm = true;
  $clonando = true;
}

// ----------- POST: salvar / cancelar / realizar -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página.';
  } else {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cancelar') {
      $id = (int)($_POST['id'] ?? 0);
      $atend = store_find('agendamentos', $id);
      if ($atend && !agenda_pode_gerir($atend, $terapeutaLogado)) {
        flash_set('error', 'Você só pode cancelar os seus próprios atendimentos.');
        header('Location: agenda.php');
        exit;
      }
      if ($atend) {
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        store_update('agendamentos', $id, [
          'status'          => 'cancelado',
          'cancelado_em'    => date('c'),
          'cancelado_por'   => (int)$terapeutaLogado['id'],
          'motivo_cancel'   => $motivo,
        ]);
        whats_remover_de_atendimento($id);
        if (!empty($atend['paciente_package_id'])) {
          pacote_devolver((int)$atend['paciente_package_id'], $id, (int)$terapeutaLogado['id'], 'Cancelamento do atendimento');
        }
        flash_set('success', 'Atendimento cancelado. Histórico preservado e lembretes removidos.');
      } else {
        flash_set('error', 'Atendimento não encontrado.');
      }
      header('Location: agenda.php');
      exit;
    }

    if ($acao === 'excluir') {
      $id = (int)($_POST['id'] ?? 0);
      $atend = store_find('agendamentos', $id);
      if ($atend && !agenda_pode_gerir($atend, $terapeutaLogado)) {
        flash_set('error', 'Você só pode excluir os seus próprios atendimentos.');
        header('Location: agenda.php');
        exit;
      }
      if ($atend) {
        // Exclusão definitiva: remove o registro e qualquer lembrete vinculado.
        if (!empty($atend['paciente_package_id'])) {
          pacote_devolver((int)$atend['paciente_package_id'], $id, (int)$terapeutaLogado['id'], 'Exclusão do atendimento');
        }
        whats_remover_de_atendimento($id);
        store_delete('agendamentos', $id);
        flash_set('success', 'Atendimento excluído definitivamente.');
      } else {
        flash_set('error', 'Atendimento não encontrado.');
      }
      header('Location: agenda.php');
      exit;
    }

    if ($acao === 'realizar') {
      $id = (int)($_POST['id'] ?? 0);
      $atend = store_find('agendamentos', $id);
      if ($atend && !agenda_pode_gerir($atend, $terapeutaLogado)) {
        flash_set('error', 'Você só pode alterar os seus próprios atendimentos.');
        header('Location: agenda.php');
        exit;
      }
      if ($atend) {
        store_update('agendamentos', $id, [
          'status'        => 'realizado',
          'realizado_em'  => date('c'),
          'realizado_por' => (int)$terapeutaLogado['id'],
        ]);
        if (!empty($atend['paciente_package_id'])) {
          pacote_marcar_realizada((int)$atend['paciente_package_id'], $id, (int)$terapeutaLogado['id']);
        }
        flash_set('success', 'Atendimento marcado como realizado.');
      } else {
        flash_set('error', 'Atendimento não encontrado.');
      }
      header('Location: agenda.php');
      exit;
    }

    if ($acao === 'confirmar') {
      $id = (int)($_POST['id'] ?? 0);
      $atend = store_find('agendamentos', $id);
      if ($atend && !agenda_pode_gerir($atend, $terapeutaLogado)) {
        flash_set('error', 'Você só pode alterar os seus próprios atendimentos.');
        header('Location: agenda.php');
        exit;
      }
      if ($atend) {
        store_update('agendamentos', $id, ['status' => 'confirmado', 'confirmado_em' => date('c')]);
        flash_set('success', 'Atendimento confirmado.');
      } else {
        flash_set('error', 'Atendimento não encontrado.');
      }
      header('Location: agenda.php');
      exit;
    }

    if ($acao === 'falta') {
      $id = (int)($_POST['id'] ?? 0);
      $atend = store_find('agendamentos', $id);
      if ($atend && !agenda_pode_gerir($atend, $terapeutaLogado)) {
        flash_set('error', 'Você só pode alterar os seus próprios atendimentos.');
        header('Location: agenda.php');
        exit;
      }
      if ($atend) {
        $consumir = (string)($_POST['consumir'] ?? '1') === '1';
        store_update('agendamentos', $id, ['status' => 'falta', 'falta_em' => date('c'), 'falta_consumiu' => $consumir]);
        whats_remover_de_atendimento($id);
        if (!empty($atend['paciente_package_id'])) {
          pacote_falta((int)$atend['paciente_package_id'], $id, (int)$terapeutaLogado['id'], $consumir, $consumir ? 'Falta com consumo da sessão' : 'Falta com devolução ao saldo');
        }
        flash_set('success', $consumir ? 'Falta registrada — sessão consumida.' : 'Falta registrada — sessão devolvida ao saldo.');
      } else {
        flash_set('error', 'Atendimento não encontrado.');
      }
      header('Location: agenda.php');
      exit;
    }

    if ($acao === 'reativar') {
      $id = (int)($_POST['id'] ?? 0);
      $atend = store_find('agendamentos', $id);
      if ($atend && !agenda_pode_gerir($atend, $terapeutaLogado)) {
        flash_set('error', 'Você só pode reativar os seus próprios atendimentos.');
        header('Location: agenda.php');
        exit;
      }
      if ($atend) {
        // Checa conflito antes de reativar
        if (ha_conflito(store_all('agendamentos'), $atend['data'], $atend['hora_inicio'], $atend['hora_fim'], $atend['sala'], $id)) {
          flash_set('error', 'Não foi possível reativar: já existe outro atendimento ativo nesse horário/sala.');
        } else {
          store_update('agendamentos', $id, ['status' => 'agendado']);
          whats_enfileirar_para_atendimento(array_merge($atend, ['status' => 'agendado']), store_find('terapeutas', (int)$atend['terapeuta_id']));
          flash_set('success', 'Atendimento reativado.');
          if (!empty($atend['paciente_package_id'])) {
            $rr = pacote_reservar((int)$atend['paciente_package_id'], $id, (int)$terapeutaLogado['id']);
            if (empty($rr['ok'])) {
              flash_set('error', 'Atendimento reativado, mas sem saldo no pacote para reservar a sessão.');
            }
          }
        }
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

      // Propriedade: não-admin não edita agendamento alheio; e o dono é
      // resolvido no backend (nunca confiando no terapeuta_id do form).
      $atendExistente = $id ? store_find('agendamentos', $id) : null;
      if ($atendExistente && !agenda_pode_gerir($atendExistente, $terapeutaLogado)) {
        flash_set('error', 'Você só pode editar os seus próprios atendimentos.');
        header('Location: agenda.php');
        exit;
      }
      $terapId     = agenda_resolver_terapeuta_id($terapeutaLogado, (int)($_POST['terapeuta_id'] ?? 0), $atendExistente);
      $paciente    = trim($_POST['paciente'] ?? '');
      $observacoes = trim($_POST['observacoes'] ?? '');
      $terapia     = trim($_POST['terapia'] ?? '');

      // Vínculo opcional com paciente cadastrado (do terapeuta logado).
      $pacienteId  = (int)($_POST['paciente_id'] ?? 0);
      if ($pacienteId > 0) {
        $pac = pac_find_do_terapeuta($pacienteId, (int)$terapeutaLogado['id']);
        if (!$pac) {
          $erros[] = 'Paciente selecionado inválido.';
          $pacienteId = 0;
        } else {
          // O nome canônico do paciente prevalece sobre o texto digitado.
          $paciente = pac_nome_exibicao($pac);
        }
      }

      // Vínculo opcional com um PACOTE do paciente (do terapeuta dono).
      $pacotePkgId = (int)($_POST['paciente_package_id'] ?? 0);
      if ($pacotePkgId > 0) {
        $pkgSel = pacote_find_do_terapeuta($pacotePkgId, $terapId);
        if (!$pkgSel || (int)($pkgSel['paciente_id'] ?? 0) !== $pacienteId || ($pkgSel['status'] ?? '') !== 'ativo') {
          $erros[] = 'Pacote selecionado inválido para este paciente.';
          $pacotePkgId = 0;
        }
      }

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

      // Se vai reservar uma sessão nova (novo agendamento ou troca de pacote),
      // exige saldo disponível ANTES de salvar — nunca cria sem cobertura.
      $pkgAntigo = (int)($atendExistente['paciente_package_id'] ?? 0);
      if (!$erros && $pacotePkgId > 0) {
        $precisaReservar = !$atendExistente || $pkgAntigo !== $pacotePkgId;
        if ($precisaReservar && pacote_disponivel($pacotePkgId) < 1) {
          $erros[] = 'O pacote selecionado não tem sessões disponíveis. Ajuste o pacote ou agende sem pacote.';
        }
      }

      if (!$erros) {
        $payload = [
          'data'                => $data,
          'hora_inicio'         => $hi,
          'hora_fim'            => $hf,
          'sala'                => $sala,
          'terapeuta_id'        => $terapId,
          'paciente'            => $paciente,
          'paciente_id'         => $pacienteId,
          'paciente_package_id' => $pacotePkgId,
          'terapia'             => $terapia,
          'observacoes'         => $observacoes,
        ];
        if (!$id) {
          $payload['status'] = 'agendado';
        }

        if ($id) {
          $atualizado = store_update('agendamentos', $id, $payload);
          whats_remover_de_atendimento($id);
          if (($atualizado['status'] ?? 'agendado') !== 'cancelado') {
            whats_enfileirar_para_atendimento($atualizado, store_find('terapeutas', $terapId));
          }
          $novoId = $id;
          flash_set('success', 'Atendimento atualizado.');
        } else {
          $criado = store_insert('agendamentos', $payload);
          whats_enfileirar_para_atendimento($criado, store_find('terapeutas', $terapId));
          $novoId = (int)$criado['id'];
          flash_set('success', 'Atendimento marcado e lembretes programados.');
        }

        // Movimentação de saldo do pacote (idempotente; reagendar não reconsome).
        if ($pkgAntigo > 0 && $pkgAntigo !== $pacotePkgId) {
          pacote_devolver($pkgAntigo, $novoId, $terapId, 'Desvinculado do agendamento');
        }
        if ($pacotePkgId > 0) {
          $rr = pacote_reservar($pacotePkgId, $novoId, $terapId);
          if (empty($rr['ok'])) {
            flash_set('error', 'Atendimento salvo, mas não foi possível reservar a sessão do pacote: ' . ($rr['erro'] ?? 'saldo insuficiente') . '.');
          }
        }

        header('Location: agenda.php');
        exit;
      } else {
        $registroEdit = [
          'id'                  => $id,
          'data'                => $data,
          'hora_inicio'         => $hi,
          'hora_fim'            => $hf,
          'sala'                => $sala,
          'terapeuta_id'        => $terapId,
          'paciente'            => $paciente,
          'paciente_id'         => $pacienteId,
          'paciente_package_id' => $pacotePkgId,
          'terapia'             => $terapia,
          'observacoes'         => $observacoes,
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

// Filtro de status (padrão: ativos = agendado+realizado, cancelados ficam escondidos)
$filtroStatus = $_GET['status'] ?? 'ativos';
if (!in_array($filtroStatus, ['ativos', 'cancelados', 'todos'], true)) $filtroStatus = 'ativos';

function evento_passa_filtro(array $a, string $filtro): bool {
  $s = $a['status'] ?? 'agendado';
  if ($filtro === 'todos')      return true;
  if ($filtro === 'cancelados') return $s === 'cancelado';
  // ativos
  return $s !== 'cancelado';
}

// Filtro por terapeuta — exclusivo de administradores (spec §8.2). 0 = todos.
$filtroTerap = ($isAdmin && isset($_GET['terapeuta'])) ? (int)$_GET['terapeuta'] : 0;

$todosAtend = store_all('agendamentos');
$eventosPorDia = array_fill_keys($diasSem, []);
foreach ($todosAtend as $a) {
  if (!evento_passa_filtro($a, $filtroStatus)) continue;
  if ($filtroTerap > 0 && (int)($a['terapeuta_id'] ?? 0) !== $filtroTerap) continue;
  $d = $a['data'] ?? '';
  if (isset($eventosPorDia[$d])) $eventosPorDia[$d][] = $a;
}

$contagens = ['ativos' => 0, 'cancelados' => 0, 'todos' => 0];
foreach ($todosAtend as $a) {
  $s = $a['status'] ?? 'agendado';
  $contagens['todos']++;
  if ($s === 'cancelado') $contagens['cancelados']++;
  else                    $contagens['ativos']++;
}

$terapeutasAtivos = store_where('terapeutas', fn($r) => !empty($r['ativo']));
$flash = flash_get();

$pageTitle = 'Agenda • Espaço Pindorama';
$activeApp = 'agenda';
require __DIR__ . '/partials/header.php';

$horasGrade = range(7, 21); // 07h–21h
$diasLabel  = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

// helper para montar a string do tooltip (escapado)
function tooltip_render(array $e, array $salas, callable $nomeT): string {
  $tNome = $nomeT((int)($e['terapeuta_id'] ?? 0));
  $status = $e['status'] ?? 'agendado';
  $obs = trim((string)($e['observacoes'] ?? ''));
  $sala = $salas[$e['sala'] ?? ''] ?? ($e['sala'] ?? '—');

  $h  = '<div class="terap-tooltip__head">';
  $h .= '<strong>' . htmlspecialchars($e['paciente'] ?? '—') . '</strong>';
  $h .= '<span class="terap-tooltip__status terap-tooltip__status--' . htmlspecialchars($status) . '">' . htmlspecialchars(status_label($status)) . '</span>';
  $h .= '</div>';
  $h .= '<dl class="terap-tooltip__list">';
  $h .= '<div><dt>Terapeuta</dt><dd>' . htmlspecialchars($tNome) . '</dd></div>';
  $h .= '<div><dt>Data</dt><dd>' . htmlspecialchars(date('d/m/Y', strtotime($e['data']))) . '</dd></div>';
  $h .= '<div><dt>Horário</dt><dd>' . htmlspecialchars(substr($e['hora_inicio'], 0, 5)) . ' – ' . htmlspecialchars(substr($e['hora_fim'], 0, 5)) . '</dd></div>';
  $h .= '<div><dt>Sala</dt><dd>' . htmlspecialchars($sala) . '</dd></div>';
  if ($obs !== '') {
    $h .= '<div><dt>Observações</dt><dd>' . nl2br(htmlspecialchars($obs)) . '</dd></div>';
  }
  if ($status === 'cancelado' && !empty($e['motivo_cancel'])) {
    $h .= '<div><dt>Motivo</dt><dd>' . htmlspecialchars($e['motivo_cancel']) . '</dd></div>';
  }
  $h .= '</dl>';
  return $h;
}
$nomeTerap = function (int $id): string {
  $t = store_find('terapeutas', $id);
  return $t['nome'] ?? '—';
};

// Layout de "lanes": eventos que se sobrepõem no tempo, no mesmo dia, são
// dispostos lado a lado (como no Google Agenda). Retorna id => [lane, lanes].
function agenda_layout_lanes(array $eventos): array {
  $norm = [];
  foreach ($eventos as $e) {
    $ini = agenda_minutos(substr((string)($e['hora_inicio'] ?? ''), 0, 5));
    $fim = agenda_minutos(substr((string)($e['hora_fim'] ?? ''), 0, 5));
    if ($ini === null || $fim === null) continue;
    $norm[] = ['id' => (int)$e['id'], 'ini' => $ini, 'fim' => max($fim, $ini + 1)];
  }
  usort($norm, fn($a, $b) => $a['ini'] <=> $b['ini'] ?: $a['fim'] <=> $b['fim']);

  $res = [];
  $cluster = [];        // eventos do grupo de sobreposição atual
  $laneEnd = [];        // fim (min) ocupado em cada lane
  $clusterFimMax = 0;
  $fechar = function () use (&$cluster, &$res, &$laneEnd) {
    $total = max(1, count($laneEnd));
    foreach ($cluster as $c) $res[$c['id']] = ['lane' => $c['lane'], 'lanes' => $total];
    $cluster = []; $laneEnd = [];
  };
  foreach ($norm as $e) {
    if ($cluster && $e['ini'] >= $clusterFimMax) { $fechar(); $clusterFimMax = 0; }
    $lane = 0;
    while (isset($laneEnd[$lane]) && $laneEnd[$lane] > $e['ini']) $lane++;
    $laneEnd[$lane] = $e['fim'];
    $cluster[] = ['id' => $e['id'], 'lane' => $lane];
    $clusterFimMax = max($clusterFimMax, $e['fim']);
  }
  $fechar();
  return $res;
}
?>

<div class="terap-page-head">
  <div>
    <h1>Agenda do espaço</h1>
    <p>Visão semanal · começa na segunda · marcações bloqueiam choque de horários por sala (cancelados nunca bloqueiam).</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="terap-btn" href="agenda.php?semana=<?= htmlspecialchars($prevSemana) ?>&status=<?= htmlspecialchars($filtroStatus) ?>">← Semana</a>
    <a class="terap-btn" href="agenda.php?status=<?= htmlspecialchars($filtroStatus) ?>">Hoje</a>
    <a class="terap-btn" href="agenda.php?semana=<?= htmlspecialchars($nextSemana) ?>&status=<?= htmlspecialchars($filtroStatus) ?>">Semana →</a>
    <a class="terap-btn terap-btn--primary" href="agenda.php?novo=1">+ Novo atendimento</a>
  </div>
</div>

<!-- FILTROS DE STATUS -->
<div class="terap-filters" role="group" aria-label="Filtrar por status">
  <a class="terap-filter <?= $filtroStatus === 'ativos' ? 'is-active' : '' ?>" href="agenda.php?status=ativos&semana=<?= htmlspecialchars($inicio) ?>">
    Ativos <span><?= $contagens['ativos'] ?></span>
  </a>
  <a class="terap-filter <?= $filtroStatus === 'cancelados' ? 'is-active' : '' ?>" href="agenda.php?status=cancelados&semana=<?= htmlspecialchars($inicio) ?>">
    Cancelados <span><?= $contagens['cancelados'] ?></span>
  </a>
  <a class="terap-filter <?= $filtroStatus === 'todos' ? 'is-active' : '' ?>" href="agenda.php?status=todos&semana=<?= htmlspecialchars($inicio) ?>">
    Todos <span><?= $contagens['todos'] ?></span>
  </a>
</div>

<?php if ($isAdmin): ?>
<!-- FILTRO POR TERAPEUTA (apenas administradores) -->
<form method="get" class="terap-filters" style="align-items:center;gap:8px;margin-top:6px;">
  <input type="hidden" name="status" value="<?= htmlspecialchars($filtroStatus) ?>">
  <input type="hidden" name="semana" value="<?= htmlspecialchars($inicio) ?>">
  <label for="filtroTerap" style="font-size:13px;color:var(--muted);">Terapeuta:</label>
  <select id="filtroTerap" name="terapeuta" onchange="this.form.submit()">
    <option value="0">Todos da equipe</option>
    <?php foreach ($terapeutasAtivos as $t): ?>
      <option value="<?= (int)$t['id'] ?>" <?= $filtroTerap === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
    <?php endforeach; ?>
  </select>
</form>
<?php endif; ?>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<?php if ($mostrarForm):
  $csrf = auth_csrf_token();

  // Pré-preenchimento via querystring (clique em célula vazia da grade).
  // Só vale para "novo" — em edição prevalecem os valores do registro.
  $getData = isset($_GET['data']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data']) ? $_GET['data'] : null;
  $getHi   = isset($_GET['hora_inicio']) && preg_match('/^\d{2}:\d{2}$/', $_GET['hora_inicio']) ? $_GET['hora_inicio'] : null;
  $getSala = isset($_GET['sala']) && isset($salasDisponiveis[$_GET['sala']]) ? $_GET['sala'] : null;
  $getHf   = $getHi ? date('H:i', strtotime($getHi) + 3600) : null;

  $valData    = $registroEdit['data']         ?? $getData ?? date('Y-m-d');
  $valHi      = $registroEdit['hora_inicio']  ?? $getHi   ?? '09:00';
  $valHf      = $registroEdit['hora_fim']     ?? $getHf   ?? '10:00';
  $valSala    = $registroEdit['sala']         ?? $getSala ?? 'sala-1';
  $valTerap   = (int)($registroEdit['terapeuta_id'] ?? $terapeutaLogado['id']);
  $valPaciente= $registroEdit['paciente']     ?? '';
  $valObs     = $registroEdit['observacoes']  ?? '';
  $valTerapia = $registroEdit['terapia']      ?? '';
  $valId      = ($clonando ?? false) ? 0 : (int)($registroEdit['id'] ?? 0);

  // Vínculo com paciente cadastrado: edição traz do registro; novo aceita ?paciente_id=.
  $valPacienteId = (int)($registroEdit['paciente_id'] ?? 0);
  if (!$valPacienteId && !$valId && isset($_GET['paciente_id'])) {
    $pre = pac_find_do_terapeuta((int)$_GET['paciente_id'], (int)$terapeutaLogado['id']);
    if ($pre) { $valPacienteId = (int)$pre['id']; $valPaciente = pac_nome_exibicao($pre); }
  }
  // Pacote vinculado + opções do paciente (renderizadas no servidor; o JS
  // repopula quando o paciente muda).
  $valPkgId  = (int)($registroEdit['paciente_package_id'] ?? 0);
  $pkgOpcoes = $valPacienteId ? pacote_ativos_do_paciente($valPacienteId, (int)$terapeutaLogado['id']) : [];
?>
  <section class="terap-card terap-span-12" style="margin-bottom:18px;">
    <h2><?= $clonando ? 'Clonar atendimento' : ($valId ? 'Editar atendimento' : 'Novo atendimento') ?></h2>
    <?php if ($clonando): ?>
      <div class="terap-alert terap-alert--info">Clonando os dados do atendimento. Confirme/ajuste <strong>data e horário</strong> e salve — uma nova sessão do pacote só é reservada ao salvar.</div>
    <?php else: ?>
      <p style="margin-bottom:14px;">Sala/espaço com horário ocupado será bloqueado automaticamente.</p>
    <?php endif; ?>

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
        <?php if ($isAdmin): ?>
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
        <?php else: ?>
        <?php
          // Não-admin: o atendimento é sempre do terapeuta logado. Em edição,
          // mostra o dono original (read-only) — o backend não aceita troca.
          $donoNome = $registroEdit
            ? (store_find('terapeutas', (int)($registroEdit['terapeuta_id'] ?? $terapeutaLogado['id']))['nome'] ?? $terapeutaLogado['nome'])
            : $terapeutaLogado['nome'];
        ?>
        <div class="terap-field" style="flex:1;min-width:220px;">
          <label>Terapeuta</label>
          <input type="text" value="<?= htmlspecialchars($donoNome) ?>" readonly
                 style="opacity:.8;cursor:not-allowed;">
        </div>
        <?php endif; ?>
      </div>

      <div class="terap-field pac-autocomplete" id="pacAutocomplete">
        <label for="paciente">Paciente *</label>
        <input id="paciente" name="paciente" required maxlength="120" autocomplete="off"
               value="<?= htmlspecialchars($valPaciente) ?>"
               placeholder="Digite para buscar um paciente cadastrado (ou um nome livre)">
        <input type="hidden" id="paciente_id" name="paciente_id" value="<?= (int)$valPacienteId ?>">
        <ul class="pac-autocomplete__list" id="pacAutocompleteList" role="listbox" hidden></ul>
        <small class="pac-help" id="pacAutocompleteHint">
          <?php if ($valPacienteId): ?>
            Vinculado a <a class="terap-link" href="paciente.php?id=<?= (int)$valPacienteId ?>">ficha do paciente</a>.
          <?php else: ?>
            Comece a digitar para vincular a um paciente já cadastrado por você.
          <?php endif; ?>
        </small>
      </div>

      <div class="terap-field" id="pacPacoteWrap" data-orig-pkg="<?= (int)$valPkgId ?>">
        <label for="paciente_package_id">Pacote (opcional)</label>
        <select id="paciente_package_id" name="paciente_package_id">
          <option value="0">Sem pacote</option>
          <?php foreach ($pkgOpcoes as $pk): $disp = max(0, pacote_disponivel((int)$pk['id'])); ?>
            <option value="<?= (int)$pk['id'] ?>" data-disp="<?= $disp ?>" <?= $valPkgId === (int)$pk['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($pk['nome']) ?> — <?= $disp ?> disponível(is)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="pac-saldo-box" id="pacSaldoBox" hidden></div>
        <small class="pac-help">Ao salvar um novo vínculo, 1 sessão é reservada. Cancelar devolve a sessão; reagendar não consome de novo.</small>
      </div>

      <div class="terap-field">
        <label for="terapia">Terapia / tipo de atendimento</label>
        <input id="terapia" name="terapia" maxlength="80" value="<?= htmlspecialchars($valTerapia) ?>" placeholder="Ex.: Massoterapia, escuta, auriculo…">
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

<!-- VISÃO SEMANAL (timeline proporcional por minuto) -->
<section class="terap-card terap-span-12">
  <h2>Semana de <?= htmlspecialchars(fmt_data_pt($inicio)) ?></h2>
  <p style="margin-bottom:14px;">Clique num bloco para ver/editar. Arraste o corpo para mover, ou as alças (topo/base) para mudar a duração. Encaixe a cada <?= AG_SNAP_MIN ?> min.</p>

  <div class="ag-cal-scroll">
    <div class="ag-cal"
         id="agCal"
         style="--ag-pph: <?= AG_PIXELS_PER_HOUR ?>px;"
         data-start-hour="<?= AG_GRID_START_HOUR ?>"
         data-end-hour="<?= AG_GRID_END_HOUR ?>"
         data-snap="<?= AG_SNAP_MIN ?>"
         data-min-dur="<?= AG_MIN_DURACAO_MIN ?>">

      <!-- Cabeçalho de dias -->
      <div class="ag-cal__head">
        <div class="ag-cal__corner">Hora</div>
        <?php foreach ($diasSem as $i => $d): $isToday = $d === date('Y-m-d'); ?>
          <div class="ag-cal__dayhead <?= $isToday ? 'is-today' : '' ?>">
            <?= $diasLabel[$i] ?><small><?= date('d/m', strtotime($d)) ?></small>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Corpo: régua de horas + colunas de dia posicionadas por minuto -->
      <div class="ag-cal__body" style="height: <?= (AG_GRID_END_HOUR - AG_GRID_START_HOUR) * AG_PIXELS_PER_HOUR ?>px;">
        <div class="ag-cal__gutter">
          <?php for ($h = AG_GRID_START_HOUR; $h <= AG_GRID_END_HOUR; $h++): ?>
            <div class="ag-cal__hourline" style="top: <?= ($h - AG_GRID_START_HOUR) * AG_PIXELS_PER_HOUR ?>px;">
              <span><?= sprintf('%02dh', $h) ?></span>
            </div>
          <?php endfor; ?>
        </div>

        <?php foreach ($diasSem as $d):
          $isToday = $d === date('Y-m-d');
          $evs = array_values($eventosPorDia[$d] ?? []);
          $lanes = agenda_layout_lanes($evs);
        ?>
        <div class="ag-cal__col <?= $isToday ? 'is-today' : '' ?>" data-date="<?= htmlspecialchars($d) ?>" role="gridcell" aria-label="<?= htmlspecialchars(date('d/m', strtotime($d))) ?>">
          <?php for ($h = AG_GRID_START_HOUR; $h <= AG_GRID_END_HOUR; $h++): ?>
            <div class="ag-cal__rule" style="top: <?= ($h - AG_GRID_START_HOUR) * AG_PIXELS_PER_HOUR ?>px;"></div>
          <?php endfor; ?>

          <?php foreach ($evs as $e):
            $iniMin = agenda_minutos(substr((string)($e['hora_inicio'] ?? ''), 0, 5));
            $fimMin = agenda_minutos(substr((string)($e['hora_fim'] ?? ''), 0, 5));
            if ($iniMin === null || $fimMin === null) continue;
            $top = ($iniMin - AG_GRID_START_HOUR * 60) * AG_PIXELS_PER_HOUR / 60;
            $alt = max(20, ($fimMin - $iniMin) * AG_PIXELS_PER_HOUR / 60);
            $lay = $lanes[(int)$e['id']] ?? ['lane' => 0, 'lanes' => 1];
            $wPct = 100 / max(1, $lay['lanes']);
            $leftPct = $lay['lane'] * $wPct;

            $mine = (int)($e['terapeuta_id'] ?? 0) === (int)$terapeutaLogado['id'];
            $status = $e['status'] ?? 'agendado';
            $cls = ' ag-ev--' . htmlspecialchars($status);
            if ($status === 'agendado' && !$mine) $cls .= ' ag-ev--sand';
            $pode = agenda_pode_gerir($e, $terapeutaLogado);
            $tNomeStr = ($nomeTerap((int)($e['terapeuta_id'] ?? 0)));
            $hi5 = substr((string)$e['hora_inicio'], 0, 5);
            $hf5 = substr((string)$e['hora_fim'], 0, 5);
            $tituloHover = $hi5 . '–' . $hf5 . ' · ' . ($e['paciente'] ?? '—')
              . ' · ' . ($salasDisponiveis[$e['sala'] ?? ''] ?? ($e['sala'] ?? '—'))
              . ' · ' . explode(' ', $tNomeStr)[0];
            $curto = $alt < 44;
          ?>
            <a class="ag-ev<?= $cls ?><?= $pode ? '' : ' ag-ev--readonly' ?><?= $curto ? ' ag-ev--curto' : '' ?>"
               href="agenda.php?editar=<?= (int)$e['id'] ?>"
               data-ag-id="<?= (int)$e['id'] ?>"
               data-date="<?= htmlspecialchars($d) ?>"
               data-start="<?= htmlspecialchars($hi5) ?>"
               data-end="<?= htmlspecialchars($hf5) ?>"
               data-sala="<?= htmlspecialchars($e['sala'] ?? '') ?>"
               data-pode="<?= $pode ? '1' : '0' ?>"
               title="<?= htmlspecialchars($tituloHover) ?>"
               style="top: <?= $top ?>px; height: <?= $alt ?>px; left: calc(<?= $leftPct ?>% + 2px); width: calc(<?= $wPct ?>% - 4px);">
              <?php if ($pode): ?><span class="ag-ev__grip ag-ev__grip--top" data-grip="top" aria-hidden="true"></span><?php endif; ?>
              <span class="ag-ev__body">
                <strong class="ag-ev__time"><?= htmlspecialchars($hi5) ?>–<?= htmlspecialchars($hf5) ?></strong>
                <span class="ag-ev__pac"><?= htmlspecialchars($e['paciente'] ?? '—') ?></span>
                <small class="ag-ev__meta"><?= htmlspecialchars($salasDisponiveis[$e['sala'] ?? ''] ?? '') ?> · <?= htmlspecialchars(explode(' ', $tNomeStr)[0]) ?></small>
                <?php if ($status !== 'agendado'): ?><em class="ag-ev__badge"><?= htmlspecialchars(status_label($status)) ?></em><?php endif; ?>
              </span>
              <?php if ($pode): ?><span class="ag-ev__grip ag-ev__grip--bottom" data-grip="bottom" aria-hidden="true"></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- LISTA DETALHADA DA SEMANA -->
<section class="terap-card terap-span-12" style="margin-top:18px;">
  <h2>Atendimentos da semana</h2>
  <p style="margin-bottom:12px;">Lista completa para editar, marcar como realizado ou cancelar.</p>

  <?php
    $atendSemana = [];
    foreach ($diasSem as $d) {
      foreach ($eventosPorDia[$d] ?? [] as $e) $atendSemana[] = $e;
    }
    usort($atendSemana, fn($a, $b) => strcmp(($a['data'] ?? '') . ($a['hora_inicio'] ?? ''), ($b['data'] ?? '') . ($b['hora_inicio'] ?? '')));
  ?>

  <?php if (!$atendSemana): ?>
    <div class="terap-alert terap-alert--info">Nenhum atendimento nessa semana para esse filtro.</div>
  <?php else: ?>
    <table class="terap-table">
      <thead>
        <tr>
          <th>Dia</th>
          <th>Horário</th>
          <th>Sala</th>
          <th>Terapeuta</th>
          <th>Paciente</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($atendSemana as $e):
          $tNome = store_find('terapeutas', (int)$e['terapeuta_id']);
          $tNomeStr = $tNome['nome'] ?? '—';
          $status = $e['status'] ?? 'agendado';
          $podeGerir = agenda_pode_gerir($e, $terapeutaLogado);
        ?>
          <tr class="terap-row--<?= htmlspecialchars($status) ?>">
            <td><?= htmlspecialchars(date('d/m', strtotime($e['data']))) ?> <span style="color:var(--muted)"><?= htmlspecialchars($diasLabel[(int)date('N', strtotime($e['data'])) - 1] ?? '') ?></span></td>
            <td><?= htmlspecialchars(substr($e['hora_inicio'], 0, 5)) ?>–<?= htmlspecialchars(substr($e['hora_fim'], 0, 5)) ?></td>
            <td><?= htmlspecialchars($salasDisponiveis[$e['sala'] ?? ''] ?? '') ?></td>
            <td><?= htmlspecialchars($tNomeStr) ?></td>
            <td><?= htmlspecialchars($e['paciente'] ?? '') ?></td>
            <td>
              <span class="terap-tooltip__status terap-tooltip__status--<?= htmlspecialchars($status) ?>">
                <?= htmlspecialchars(status_label($status)) ?>
              </span>
            </td>
            <td style="white-space:nowrap;">
              <?php if (!$podeGerir): ?>
                <span style="color:var(--muted);font-size:12px;">Somente leitura</span>
              <?php else: ?>
              <?php if ($status !== 'cancelado'): ?>
                <a class="terap-btn terap-btn--sm" href="agenda.php?editar=<?= (int)$e['id'] ?>">Editar</a>
                <?php if (!empty($e['paciente_id']) && pac_find_do_terapeuta((int)$e['paciente_id'], (int)$terapeutaLogado['id'])): ?>
                  <a class="terap-btn terap-btn--sm" href="paciente.php?id=<?= (int)$e['paciente_id'] ?>">Ficha</a>
                <?php endif; ?>
                <a class="terap-btn terap-btn--sm" href="evolucoes.php?nova=1&atendimento_id=<?= (int)$e['id'] ?>">Evolução</a>
                <?php if ($status === 'agendado'): ?>
                  <form method="post" action="agenda.php" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                    <input type="hidden" name="acao" value="realizar">
                    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                    <button class="terap-btn terap-btn--sm" type="submit" title="Marcar como realizado">✓ Realizado</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="agenda.php" style="display:inline" onsubmit="var m=prompt('Cancelar este atendimento?\nMotivo (opcional):'); if(m===null) return false; this.motivo.value = m; return true;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                  <input type="hidden" name="acao" value="cancelar">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <input type="hidden" name="motivo" value="">
                  <button class="terap-btn terap-btn--sm terap-btn--danger" type="submit">Cancelar</button>
                </form>
              <?php else: ?>
                <form method="post" action="agenda.php" style="display:inline" onsubmit="return confirm('Reativar este atendimento? Se houver conflito de horário, a operação será bloqueada.');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                  <input type="hidden" name="acao" value="reativar">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <button class="terap-btn terap-btn--sm" type="submit">Reativar</button>
                </form>
              <?php endif; ?>
              <form method="post" action="agenda.php" style="display:inline"
                    onsubmit="return confirm('Excluir DEFINITIVAMENTE este atendimento de <?= htmlspecialchars($e['paciente'] ?? '—', ENT_QUOTES) ?>?\nO registro será apagado e não poderá ser recuperado.\n\nSe quiser manter o histórico, use \'Cancelar\' em vez de \'Excluir\'.');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button class="terap-btn terap-btn--sm terap-btn--danger" type="submit" title="Excluir definitivamente">🗑 Excluir</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<script>
// Tooltip: em telas touch, abrir/fechar no tap (não atrapalha clique pra editar).
(function () {
  var hosts = document.querySelectorAll('.terap-tooltip-host');
  if (!hosts.length) return;

  var isTouch = window.matchMedia('(hover: none)').matches;
  if (!isTouch) return;

  function closeAll(except) {
    hosts.forEach(function (h) { if (h !== except) h.classList.remove('is-touched'); });
  }

  hosts.forEach(function (host) {
    var link = host.querySelector('.terap-week__event');
    if (!link) return;
    link.addEventListener('click', function (ev) {
      // Primeiro toque: só abre o tooltip. Segundo toque: navega.
      if (!host.classList.contains('is-touched')) {
        ev.preventDefault();
        closeAll(host);
        host.classList.add('is-touched');
      }
    });
  });

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('.terap-tooltip-host')) closeAll(null);
  }, true);
})();
</script>

<script>
// Autocomplete de pacientes + seletor de pacote (saldo ao vivo).
(function () {
  var wrap = document.getElementById('pacAutocomplete');
  if (!wrap) return;
  var input  = document.getElementById('paciente');
  var hidden = document.getElementById('paciente_id');
  var list   = document.getElementById('pacAutocompleteList');
  var hint   = document.getElementById('pacAutocompleteHint');
  var pkgWrap = document.getElementById('pacPacoteWrap');
  var pkgSel  = document.getElementById('paciente_package_id');
  var saldoBox = document.getElementById('pacSaldoBox');
  var timer = null;

  function fechar() { list.hidden = true; list.innerHTML = ''; }

  function atualizarSaldo() {
    if (!pkgSel || !saldoBox) return;
    var opt = pkgSel.options[pkgSel.selectedIndex];
    if (!opt || opt.value === '0') { saldoBox.hidden = true; return; }
    var disp = parseInt(opt.getAttribute('data-disp') || '0', 10);
    var orig = parseInt((pkgWrap && pkgWrap.dataset.origPkg) || '0', 10);
    var vaiReservar = parseInt(opt.value, 10) !== orig;
    var apos = vaiReservar ? Math.max(0, disp - 1) : disp;
    saldoBox.hidden = false;
    if (vaiReservar && disp < 1) {
      saldoBox.className = 'pac-saldo-box is-warn';
      saldoBox.textContent = 'Este pacote não tem sessões disponíveis.';
    } else {
      saldoBox.className = 'pac-saldo-box';
      saldoBox.textContent = vaiReservar
        ? ('Disponíveis: ' + disp + ' · após salvar: ' + apos + ' (1 sessão reservada).')
        : ('Disponíveis: ' + disp + ' · reagendamento não consome nova sessão.');
    }
  }

  function repovoarPacotes(itens, selId) {
    if (!pkgSel) return;
    pkgSel.innerHTML = '<option value="0">Sem pacote</option>';
    (itens || []).forEach(function (p) {
      var o = document.createElement('option');
      o.value = String(p.id);
      o.setAttribute('data-disp', String(p.disponivel));
      o.textContent = p.nome + ' — ' + p.disponivel + ' disponível(is)';
      if (selId && parseInt(selId, 10) === p.id) o.selected = true;
      pkgSel.appendChild(o);
    });
    atualizarSaldo();
  }

  function carregarPacotes(pacienteId) {
    if (!pkgSel || !pacienteId) { repovoarPacotes([], 0); return; }
    fetch('api/pacotes-do-paciente.php?paciente_id=' + encodeURIComponent(pacienteId), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : { itens: [] }; })
      .then(function (data) { repovoarPacotes((data && data.itens) || [], 0); })
      .catch(function () { repovoarPacotes([], 0); });
  }

  function escolher(item) {
    input.value = item.nome;
    hidden.value = item.id;
    if (hint) hint.innerHTML = 'Vinculado a <a class="terap-link" href="paciente.php?id=' + item.id + '">ficha do paciente</a>.';
    fechar();
    carregarPacotes(item.id);
  }

  function buscar(q) {
    fetch('api/pacientes-busca.php?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : { itens: [] }; })
      .then(function (data) {
        var itens = (data && data.itens) || [];
        list.innerHTML = '';
        if (!itens.length) { fechar(); return; }
        itens.forEach(function (item) {
          var li = document.createElement('li');
          li.setAttribute('role', 'option');
          li.className = 'pac-autocomplete__item';
          li.innerHTML = '<strong></strong>' + (item.detalhe ? '<span></span>' : '');
          li.querySelector('strong').textContent = item.nome;
          if (item.detalhe) li.querySelector('span').textContent = item.detalhe;
          li.addEventListener('mousedown', function (ev) { ev.preventDefault(); escolher(item); });
          list.appendChild(li);
        });
        list.hidden = false;
      })
      .catch(fechar);
  }

  input.addEventListener('input', function () {
    // Edição manual desfaz o vínculo (e os pacotes) até nova escolha.
    hidden.value = '';
    if (hint) hint.textContent = 'Comece a digitar para vincular a um paciente já cadastrado por você.';
    repovoarPacotes([], 0);
    var q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { fechar(); return; }
    timer = setTimeout(function () { buscar(q); }, 220);
  });
  input.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') fechar(); });
  document.addEventListener('click', function (ev) { if (!wrap.contains(ev.target)) fechar(); });
  if (pkgSel) pkgSel.addEventListener('change', atualizarSaldo);

  // Estado inicial (edição/pré-preenchido): saldo já reflete o pacote atual.
  atualizarSaldo();
})();
</script>

<!-- MODAL FLUTUANTE DO AGENDAMENTO -->
<div class="ag-modal" id="agModal" hidden role="dialog" aria-modal="true" aria-labelledby="agModalTitle">
  <div class="ag-modal__backdrop" data-close></div>
  <div class="ag-modal__panel" role="document">
    <button class="ag-modal__x" type="button" data-close aria-label="Fechar">&times;</button>
    <div class="ag-modal__loading" id="agModalLoading">Carregando…</div>
    <div class="ag-modal__content" id="agModalContent" hidden>
      <div class="ag-modal__head">
        <h3 id="agModalTitle"></h3>
        <span class="terap-tooltip__status" id="agModalStatus"></span>
      </div>
      <dl class="ag-modal__list" id="agModalList"></dl>
      <div class="ag-modal__actions" id="agModalActions"></div>
    </div>
  </div>
</div>

<script>
window.AG_CSRF = <?= json_encode(auth_csrf_token()) ?>;
(function () {
  var modal = document.getElementById('agModal');
  if (!modal) return;
  var loading = document.getElementById('agModalLoading');
  var content = document.getElementById('agModalContent');
  var elTitle = document.getElementById('agModalTitle');
  var elStatus = document.getElementById('agModalStatus');
  var elList = document.getElementById('agModalList');
  var elActions = document.getElementById('agModalActions');
  var lastFocus = null;

  function abrir() {
    lastFocus = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);
  }
  function fechar() {
    modal.hidden = true;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function onKey(e) { if (e.key === 'Escape') fechar(); }

  function row(dt, dd) {
    if (!dd) return '';
    var d = document.createElement('div');
    var t = document.createElement('dt'); t.textContent = dt;
    var v = document.createElement('dd'); v.textContent = dd;
    d.appendChild(t); d.appendChild(v);
    return d;
  }

  function postForm(acao, id, extra) {
    var f = document.createElement('form');
    f.method = 'post'; f.action = 'agenda.php';
    function add(n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); }
    add('csrf', window.AG_CSRF); add('acao', acao); add('id', id);
    if (extra) Object.keys(extra).forEach(function (k) { add(k, extra[k]); });
    document.body.appendChild(f); f.submit();
  }

  function botao(label, cls, onClick) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'terap-btn terap-btn--sm ' + (cls || '');
    b.textContent = label;
    b.addEventListener('click', onClick);
    return b;
  }
  function link(label, href, cls) {
    var a = document.createElement('a');
    a.className = 'terap-btn terap-btn--sm ' + (cls || '');
    a.href = href; a.textContent = label;
    return a;
  }

  function render(d) {
    content.querySelectorAll('.ag-resched').forEach(function (n) { n.remove(); });
    elTitle.textContent = d.paciente || 'Atendimento';
    elStatus.textContent = d.status_label || '';
    elStatus.className = 'terap-tooltip__status terap-tooltip__status--' + (d.status || 'agendado');

    elList.innerHTML = '';
    [
      ['Terapia', d.terapia],
      ['Data', d.data_fmt],
      ['Horário', (d.hora_inicio && d.hora_fim) ? (d.hora_inicio + ' – ' + d.hora_fim) : ''],
      ['Sala', d.sala_label],
      ['Terapeuta', d.terapeuta],
      ['Observações', d.observacoes]
    ].forEach(function (p) { var r = row(p[0], p[1]); if (r) elList.appendChild(r); });

    if (d.pacote) {
      var txt = d.pacote.nome;
      if (d.pacote.sessao_n) txt += ' — Sessão ' + d.pacote.sessao_n + ' de ' + d.pacote.sessao_m;
      txt += ' · ' + d.pacote.disponivel + ' disponível(is)';
      var r = row('Pacote', txt); if (r) elList.appendChild(r);
    }

    elActions.innerHTML = '';
    if (!d.pode_gerir) {
      var ro = document.createElement('span');
      ro.className = 'pac-help'; ro.textContent = 'Somente leitura (atendimento de outro terapeuta).';
      elActions.appendChild(ro);
      elActions.appendChild(botao('Fechar', 'terap-btn--ghost', fechar));
      return;
    }
    // Painel "Reagendar" — edição manual de data/horário, sincronizada com a grade.
    if (d.status !== 'cancelado') {
      var rs = document.createElement('div'); rs.className = 'ag-resched';
      rs.innerHTML =
        '<h4>Reagendar</h4>' +
        '<div class="ag-resched__row">' +
        '<div class="terap-field"><label>Data</label><input type="date" data-rs="data"></div>' +
        '<div class="terap-field"><label>Início</label><input type="time" step="900" data-rs="hi"></div>' +
        '<div class="terap-field"><label>Fim</label><input type="time" step="900" data-rs="hf"></div>' +
        '</div>';
      elList.insertAdjacentElement('afterend', rs);
      rs.querySelector('[data-rs="data"]').value = d.data || '';
      rs.querySelector('[data-rs="hi"]').value = d.hora_inicio || '';
      rs.querySelector('[data-rs="hf"]').value = d.hora_fim || '';
      var saveBtn = botao('Salvar horário', 'terap-btn--primary', function () {
        if (window.agReschedule) window.agReschedule(d.id,
          rs.querySelector('[data-rs="data"]').value,
          rs.querySelector('[data-rs="hi"]').value,
          rs.querySelector('[data-rs="hf"]').value,
          function (ok) { if (ok) fechar(); });
      });
      rs.querySelector('.ag-resched__row').appendChild(saveBtn);
    }

    if (d.ficha_url) elActions.appendChild(link('Ver ficha', d.ficha_url));
    elActions.appendChild(link('Editar', 'agenda.php?editar=' + d.id));
    // Clonar: inicia uma cópia arrastável na grade (cancela com Esc). Mantém o
    // fluxo de formulário como alternativa via "Editar".
    elActions.appendChild(botao('Clonar', '', function () {
      fechar();
      if (window.agStartClone) window.agStartClone(d.id, d.paciente || 'Cópia');
      else window.location = 'agenda.php?clonar=' + d.id;
    }));

    if (d.status === 'agendado') {
      elActions.appendChild(botao('Confirmar', '', function () { postForm('confirmar', d.id); }));
    }
    if (d.status !== 'realizado' && d.status !== 'cancelado') {
      elActions.appendChild(botao('Realizado', '', function () { postForm('realizar', d.id); }));
    }
    if (d.status !== 'cancelado' && d.status !== 'falta') {
      elActions.appendChild(botao('Falta', '', function () {
        if (!confirm('Registrar FALTA neste atendimento?')) return;
        var consumir = confirm('Consumir a sessão do pacote?\n\nOK = consumir a sessão · Cancelar = devolver ao saldo');
        postForm('falta', d.id, { consumir: consumir ? '1' : '0' });
      }));
    }
    if (d.status !== 'cancelado') {
      elActions.appendChild(botao('Cancelar', 'terap-btn--danger', function () {
        var m = prompt('Cancelar este atendimento?\nMotivo (opcional):');
        if (m === null) return;
        postForm('cancelar', d.id, { motivo: m });
      }));
    }
    elActions.appendChild(botao('Excluir', 'terap-btn--danger', function () {
      if (confirm('Excluir DEFINITIVAMENTE este atendimento?\nPara manter o histórico, use Cancelar.')) postForm('excluir', d.id);
    }));
    elActions.appendChild(botao('Fechar', 'terap-btn--ghost', fechar));
  }

  function carregar(id) {
    content.hidden = true; loading.hidden = false; abrir();
    fetch('api/agendamento.php?id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) { render(d); loading.hidden = true; content.hidden = false; })
      .catch(function () { loading.textContent = 'Não foi possível carregar o atendimento.'; });
  }

  // Clique num bloco da grade abre o modal (sem navegar). Ignora o clique
  // sintético que encerra um arraste/redimensionamento (flag data-ag-dragged).
  document.querySelectorAll('.ag-ev[data-ag-id]').forEach(function (ev) {
    ev.addEventListener('click', function (e) {
      e.preventDefault();
      if (ev.getAttribute('data-ag-dragged') === '1') { ev.removeAttribute('data-ag-dragged'); return; }
      carregar(ev.getAttribute('data-ag-id'));
    });
  });
  // Exposto para o módulo de arraste reabrir o modal após clonar/editar.
  window.agModalOpen = carregar;

  modal.addEventListener('click', function (e) {
    if (e.target.hasAttribute('data-close')) fechar();
  });
})();
</script>

<script>
// ============================================================
// Edição visual da agenda: mover, redimensionar e clonar.
// Geometria (PPM, faixa, snap) vem do #agCal — mesma fonte do PHP/lib.
// Pointer Events unificam mouse e toque; no toque, "mover" exige pressão
// contínua (long-press) para não brigar com a rolagem.
// ============================================================
(function () {
  var cal = document.getElementById('agCal');
  if (!cal || !window.PointerEvent) return;

  var START = parseInt(cal.dataset.startHour, 10) * 60;
  var END   = parseInt(cal.dataset.endHour, 10) * 60;
  var SNAP  = parseInt(cal.dataset.snap, 10) || 15;
  var MINDUR = parseInt(cal.dataset.minDur, 10) || 15;
  var PPH = parseFloat(getComputedStyle(cal).getPropertyValue('--ag-pph')) || 64;
  var PPM = PPH / 60;
  var cols = Array.prototype.slice.call(cal.querySelectorAll('.ag-cal__col'));

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toHHMM(m) { m = Math.round(m); return pad(Math.floor(m / 60)) + ':' + pad(m % 60); }
  function toMin(s) { var p = (s || '0:0').split(':'); return (+p[0]) * 60 + (+p[1]); }
  function snap(m) { return Math.round(m / SNAP) * SNAP; }
  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

  // ---- Toast ----
  var toastEl = null, toastTimer = null;
  function toast(msg, ok) {
    if (!toastEl) { toastEl = document.createElement('div'); document.body.appendChild(toastEl); }
    toastEl.className = 'ag-toast ' + (ok ? 'ag-toast--ok' : 'ag-toast--err');
    toastEl.textContent = msg;
    requestAnimationFrame(function () { toastEl.classList.add('is-on'); });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, ok ? 2400 : 4200);
  }
  window.agToast = toast;

  function post(payload, cb) {
    fetch('api/agendamento-acao.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.AG_CSRF, 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json().then(function (j) { return j; }).catch(function () { return { ok: false, error: 'Resposta inválida.' }; }); })
      .then(function (j) { cb(!!(j && j.ok), j || {}); })
      .catch(function () { cb(false, { error: 'Falha de conexão. Verifique a internet.' }); });
  }

  function colAt(x) { var best = null; cols.forEach(function (c) { var r = c.getBoundingClientRect(); if (x >= r.left && x < r.right) best = c; }); return best; }
  function evTimes(ev) { return { s: toMin(ev.dataset.start), e: toMin(ev.dataset.end) }; }
  function applyGeom(ev, s, e) {
    ev.style.top = ((s - START) * PPM) + 'px';
    ev.style.height = Math.max(20, (e - s) * PPM) + 'px';
    ev.dataset.start = toHHMM(s); ev.dataset.end = toHHMM(e);
    var t = ev.querySelector('.ag-ev__time'); if (t) t.textContent = toHHMM(s) + '–' + toHHMM(e);
  }
  function toCol(ev, col) {
    if (col && ev.parentNode !== col) { col.appendChild(ev); ev.dataset.date = col.dataset.date; ev.style.left = '2px'; ev.style.width = 'calc(100% - 4px)'; }
  }

  // ---- Arraste (mover / redimensionar) ----
  var d = null;
  function begin(ev, mode, e) {
    var t = evTimes(ev);
    d = {
      ev: ev, mode: mode, type: e.pointerType, pid: e.pointerId,
      x0: e.clientX, y0: e.clientY,
      s0: t.s, e0: t.e, date0: ev.dataset.date, parent0: ev.parentNode,
      left0: ev.style.left, width0: ev.style.width,
      active: false, pending: false, moved: false, timer: null
    };
  }
  function activate() {
    if (!d) return;
    d.active = true; d.pending = false;
    try { d.ev.setPointerCapture(d.pid); } catch (_) {}
    d.ev.classList.add('is-dragging');
  }
  function onDown(e) {
    var ev = e.target.closest('.ag-ev');
    if (!ev || ev.dataset.pode !== '1' || d) return;
    var grip = e.target.closest('.ag-ev__grip');
    var mode = grip ? ('resize-' + grip.dataset.grip) : 'move';
    begin(ev, mode, e);
    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', onCancel);

    if (e.pointerType === 'touch' && mode === 'move' && !ev.classList.contains('ag-ev--ghost')) {
      // Long-press para iniciar o arraste; até lá, a rolagem é permitida.
      d.pending = true;
      d.timer = setTimeout(function () {
        activate();
        if (navigator.vibrate) navigator.vibrate(8);
      }, 280);
    } else {
      activate(); e.preventDefault();
    }
  }
  function onMove(e) {
    if (!d) return;
    var dx = e.clientX - d.x0, dy = e.clientY - d.y0;
    if (d.pending) {
      // Moveu antes do long-press disparar → é rolagem: desiste do arraste.
      if (Math.abs(dx) > 8 || Math.abs(dy) > 10) { cleanup(); }
      return;
    }
    if (!d.active) return;
    if (!d.moved && Math.abs(dx) < 4 && Math.abs(dy) < 4) return;
    d.moved = true;
    e.preventDefault();
    var dm = dy / PPM;
    if (d.mode === 'move') {
      var dur = d.e0 - d.s0;
      var ns = clamp(snap(d.s0 + dm), START, END - dur);
      applyGeom(d.ev, ns, ns + dur);
      var col = colAt(e.clientX); if (col) toCol(d.ev, col);
    } else if (d.mode === 'resize-top') {
      var nt = clamp(snap(d.s0 + dm), START, d.e0 - MINDUR);
      applyGeom(d.ev, nt, d.e0);
    } else {
      var nb = clamp(snap(d.e0 + dm), d.s0 + MINDUR, END);
      applyGeom(d.ev, d.s0, nb);
    }
  }
  function revertFull() {
    if (!d) return;
    if (d.parent0 && d.ev.parentNode !== d.parent0) d.parent0.appendChild(d.ev);
    d.ev.dataset.date = d.date0;
    d.ev.style.left = d.left0; d.ev.style.width = d.width0;
    applyGeom(d.ev, d.s0, d.e0);
  }
  function cleanup() {
    if (!d) return;
    if (d.timer) clearTimeout(d.timer);
    d.ev.classList.remove('is-dragging');
    try { d.ev.releasePointerCapture(d.pid); } catch (_) {}
    document.removeEventListener('pointermove', onMove);
    document.removeEventListener('pointerup', onUp);
    document.removeEventListener('pointercancel', onCancel);
    d = null;
  }
  function onCancel() { if (d && d.moved) revertFull(); cleanup(); }
  function onUp() {
    if (!d) return;
    var ev = d.ev, mode = d.mode, isGhost = ev.classList.contains('ag-ev--ghost');
    if (!d.active || !d.moved) { cleanup(); return; } // toque/clique simples: abre o modal
    ev.setAttribute('data-ag-dragged', '1'); // suprime o "click" sintético

    if (isGhost) { cleanup(); return; } // a cópia só se move; salva pelo botão

    var changed = ev.dataset.date !== d.date0 || ev.dataset.start !== toHHMM(d.s0) || ev.dataset.end !== toHHMM(d.e0);
    if (!changed) { cleanup(); return; }
    var snap0 = d; // captura para o callback
    var acao = mode === 'move' ? 'mover' : 'redimensionar';
    var payload = { acao: acao, id: +ev.dataset.agId, data: ev.dataset.date, hora_inicio: ev.dataset.start, hora_fim: ev.dataset.end };
    ev.style.opacity = '.6';
    post(payload, function (ok, res) {
      ev.style.opacity = '';
      if (ok) { location.reload(); }
      else { d = snap0; revertFull(); d = null; toast(res.error || 'Não foi possível salvar.', false); }
    });
    cleanup();
  }
  cal.addEventListener('pointerdown', onDown);

  // ---- Clonagem (cópia arrastável) ----
  var clone = null;
  function cancelClone() {
    if (!clone) return;
    if (clone.ghost && clone.ghost.parentNode) clone.ghost.parentNode.removeChild(clone.ghost);
    if (clone.hint && clone.hint.parentNode) clone.hint.parentNode.removeChild(clone.hint);
    clone = null;
  }
  window.agStartClone = function (srcId, label) {
    cancelClone();
    var src = cal.querySelector('.ag-ev[data-ag-id="' + srcId + '"]');
    var s, e, col;
    if (src) { var t = evTimes(src); s = t.s; e = t.e; col = src.parentNode; }
    else { s = toMin('09:00'); e = s + 60; col = cols[0]; }
    if (!col) return;
    var g = document.createElement('div');
    g.className = 'ag-ev ag-ev--ghost';
    g.dataset.pode = '1'; g.dataset.clone = String(srcId);
    g.dataset.start = toHHMM(s); g.dataset.end = toHHMM(e); g.dataset.date = col.dataset.date;
    g.style.left = '2px'; g.style.width = 'calc(100% - 4px)';
    g.innerHTML = '<span class="ag-ev__ghosttag">Cópia</span><span class="ag-ev__body"><strong class="ag-ev__time"></strong><span class="ag-ev__pac"></span></span>';
    col.appendChild(g);
    applyGeom(g, s, e);
    g.querySelector('.ag-ev__pac').textContent = label || 'Cópia';

    var hint = document.createElement('div');
    hint.className = 'ag-clone-hint';
    hint.innerHTML = '<span>Arraste a <strong>cópia</strong> para o dia/horário desejado.</span>';
    var ok = document.createElement('button'); ok.className = 'terap-btn terap-btn--sm terap-btn--primary'; ok.textContent = 'Salvar cópia';
    var no = document.createElement('button'); no.className = 'terap-btn terap-btn--sm terap-btn--ghost'; no.textContent = 'Cancelar';
    hint.appendChild(ok); hint.appendChild(no);
    document.body.appendChild(hint);
    clone = { ghost: g, hint: hint, srcId: srcId };

    no.addEventListener('click', cancelClone);
    ok.addEventListener('click', function () {
      ok.disabled = true;
      post({ acao: 'clonar', source_id: srcId, data: g.dataset.date, hora_inicio: g.dataset.start, hora_fim: g.dataset.end }, function (good, res) {
        if (good) { location.reload(); }
        else { ok.disabled = false; toast(res.error || 'Não foi possível clonar.', false); }
      });
    });
  };

  // ---- Reagendar pelo modal ----
  window.agReschedule = function (id, data, hi, hf, cb) {
    post({ acao: 'mover', id: +id, data: data, hora_inicio: hi, hora_fim: hf }, function (ok, res) {
      if (ok) { location.reload(); }
      else { toast(res.error || 'Não foi possível reagendar.', false); if (cb) cb(false); }
    });
  };

  // ---- Criar em espaço vazio (clique na coluna) ----
  cols.forEach(function (col) {
    col.addEventListener('click', function (e) {
      if (e.target.closest('.ag-ev')) return;       // clique num evento já é tratado
      if (d || clone) return;
      var r = col.getBoundingClientRect();
      var min = clamp(snap(START + (e.clientY - r.top) / PPM), START, END - SNAP);
      window.location = 'agenda.php?novo=1&data=' + encodeURIComponent(col.dataset.date) + '&hora_inicio=' + encodeURIComponent(toHHMM(min));
    });
  });

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cancelClone(); });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
