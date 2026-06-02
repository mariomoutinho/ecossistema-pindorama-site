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
  $hiNew = strtotime($data . ' ' . $hi);
  $hfNew = strtotime($data . ' ' . $hf);
  foreach ($todos as $a) {
    if ($ignorarId !== null && (int)$a['id'] === $ignorarId) continue;
    // Cancelados nunca bloqueiam horário.
    if (($a['status'] ?? '') === 'cancelado') continue;
    if (($a['data'] ?? '') !== $data) continue;
    if (($a['sala'] ?? '') !== $sala) continue;
    $hiE = strtotime($data . ' ' . substr($a['hora_inicio'] ?? '', 0, 5));
    $hfE = strtotime($data . ' ' . substr($a['hora_fim']    ?? '', 0, 5));
    if ($hiE === false || $hfE === false) continue;
    if ($hiNew < $hfE && $hfNew > $hiE) return true;
  }
  return false;
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

$todosAtend = store_all('agendamentos');
$eventosPorDia = array_fill_keys($diasSem, []);
foreach ($todosAtend as $a) {
  if (!evento_passa_filtro($a, $filtroStatus)) continue;
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
  $valId      = (int)($registroEdit['id']     ?? 0);

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
  <p style="margin-bottom:14px;">Passe o mouse (ou toque, no celular) sobre um bloco para ver os detalhes do atendimento.</p>

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
        $celulaVazia = empty($eventos);
        $novoHref = 'agenda.php?novo=1'
                  . '&data=' . urlencode($d)
                  . '&hora_inicio=' . urlencode(sprintf('%02d:00', $h));
      ?>
        <?php if ($celulaVazia): ?>
          <a class="terap-week__cell terap-week__cell--empty"
             href="<?= htmlspecialchars($novoHref) ?>"
             title="Marcar atendimento neste horário"
             aria-label="Marcar atendimento em <?= htmlspecialchars(date('d/m', strtotime($d))) ?> às <?= sprintf('%02d:00', $h) ?>">
            <span class="terap-week__cell__plus" aria-hidden="true">+</span>
          </a>
        <?php else: ?>
        <div class="terap-week__cell">
          <?php foreach ($eventos as $e):
            $mine = (int)($e['terapeuta_id'] ?? 0) === (int)$terapeutaLogado['id'];
            $status = $e['status'] ?? 'agendado';
            $cls = ' terap-week__event--' . htmlspecialchars($status);
            if ($status === 'agendado' && !$mine) $cls .= ' terap-week__event--sand';
            $tNome = store_find('terapeutas', (int)$e['terapeuta_id']);
            $tNomeStr = $tNome['nome'] ?? '—';
            $tipId = 'tip-' . (int)$e['id'];
          ?>
            <span class="terap-tooltip-host">
              <a class="terap-week__event<?= $cls ?>"
                 href="agenda.php?editar=<?= (int)$e['id'] ?>"
                 aria-describedby="<?= $tipId ?>">
                <strong><?= htmlspecialchars(substr($e['hora_inicio'], 0, 5)) ?>–<?= htmlspecialchars(substr($e['hora_fim'], 0, 5)) ?></strong>
                <?= htmlspecialchars($e['paciente'] ?? '—') ?>
                <small><?= htmlspecialchars($salasDisponiveis[$e['sala'] ?? ''] ?? '') ?> · <?= htmlspecialchars(explode(' ', $tNomeStr)[0]) ?></small>
                <?php if ($status !== 'agendado'): ?>
                  <em class="terap-week__event__badge"><?= htmlspecialchars(status_label($status)) ?></em>
                <?php endif; ?>
              </a>
              <div class="terap-tooltip" id="<?= $tipId ?>" role="tooltip"><?= tooltip_render($e, $salasDisponiveis, $nomeTerap) ?></div>
            </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
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

<?php require __DIR__ . '/partials/footer.php'; ?>
