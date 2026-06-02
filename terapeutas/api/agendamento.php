<?php
// ================================
// Endpoint: detalhes de um agendamento para o modal flutuante da agenda.
// Toda a equipe vê os dados básicos (a grade já os mostra); apenas o DONO
// (ou admin) recebe observações, pacote e link da ficha, e pode agir.
// ================================
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!auth_logged_in()) {
  http_response_code(401);
  echo json_encode(['error' => 'nao_autenticado']);
  exit;
}

$salas = $salasDisponiveis ?? [];
$id = (int)($_GET['id'] ?? 0);
$a = store_find('agendamentos', $id);
if (!$a) {
  http_response_code(404);
  echo json_encode(['error' => 'nao_encontrado']);
  exit;
}

$podeGerir = agenda_pode_gerir($a, $terapeutaLogado);
$terap = store_find('terapeutas', (int)($a['terapeuta_id'] ?? 0));
$status = $a['status'] ?? 'agendado';

$out = [
  'id'           => (int)$a['id'],
  'pode_gerir'   => $podeGerir,
  'paciente'     => (string)($a['paciente'] ?? ''),
  'data'         => (string)($a['data'] ?? ''),
  'data_fmt'     => !empty($a['data']) ? date('d/m/Y', strtotime($a['data'])) : '',
  'hora_inicio'  => substr((string)($a['hora_inicio'] ?? ''), 0, 5),
  'hora_fim'     => substr((string)($a['hora_fim'] ?? ''), 0, 5),
  'sala'         => (string)($a['sala'] ?? ''),
  'sala_label'   => $salas[$a['sala'] ?? ''] ?? ($a['sala'] ?? '—'),
  'terapeuta'    => $terap['nome'] ?? '—',
  'terapia'      => (string)($a['terapia'] ?? ''),
  'status'       => $status,
  'status_label' => agenda_status_label($status),
];

// Detalhes restritos ao dono/admin.
if ($podeGerir) {
  $out['observacoes'] = (string)($a['observacoes'] ?? '');
  $pacId = (int)($a['paciente_id'] ?? 0);
  if ($pacId > 0 && pac_find_do_terapeuta($pacId, (int)$terapeutaLogado['id'])) {
    $out['ficha_url'] = 'paciente.php?id=' . $pacId;
  }
  $pkgId = (int)($a['paciente_package_id'] ?? 0);
  if ($pkgId > 0) {
    $pkg = store_find('pacotes', $pkgId);
    if ($pkg) {
      $pos = agenda_sessao_no_pacote($a);
      if ($out['terapia'] === '' && !empty($pkg['terapia'])) $out['terapia'] = (string)$pkg['terapia'];
      $out['pacote'] = [
        'id'         => $pkgId,
        'nome'       => (string)($pkg['nome'] ?? ''),
        'sessao_n'   => $pos['n'] ?? 0,
        'sessao_m'   => $pos['m'] ?? 0,
        'disponivel' => max(0, pacote_disponivel($pkgId)),
      ];
    }
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
