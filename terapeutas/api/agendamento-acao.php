<?php
// ================================
// Endpoint AJAX de AÇÕES da agenda: mover, redimensionar e clonar.
// Toda a autorização e validação é feita AQUI (backend) — o frontend nunca é
// fonte de verdade. Reutiliza as regras do lib/agendamentos.php (conflito de
// sala, validação de intervalo, propriedade) e a reserva de pacotes existente.
//
// Entrada: POST (JSON ou form-urlencoded) com:
//   acao = mover | redimensionar | clonar
//   mover/redimensionar: id, data, hora_inicio, hora_fim
//   clonar:              source_id (ou id), data, hora_inicio, hora_fim
//   csrf (campo) ou header X-CSRF-Token
// Saída: JSON { ok: bool, ... } | { ok:false, error:"..." }
// ================================
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function ag_out($arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ag_out(['ok' => false, 'error' => 'Método não permitido.'], 405);
if (!auth_logged_in())                     ag_out(['ok' => false, 'error' => 'Sessão expirada.'], 401);

// Corpo: aceita JSON ou form-urlencoded.
$raw = file_get_contents('php://input');
$in = [];
if (is_string($raw) && $raw !== '' && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
  $dec = json_decode($raw, true);
  if (is_array($dec)) $in = $dec;
}
if (!$in) $in = $_POST;

// CSRF: header ou campo.
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf'] ?? null);
if (!auth_csrf_check(is_string($token) ? $token : null)) {
  ag_out(['ok' => false, 'error' => 'Falha de segurança (CSRF). Recarregue a página.'], 403);
}

$acao = (string)($in['acao'] ?? '');
$data = trim((string)($in['data'] ?? ''));
$hi   = substr(trim((string)($in['hora_inicio'] ?? '')), 0, 5);
$hf   = substr(trim((string)($in['hora_fim'] ?? '')), 0, 5);

// Geometria devolvida ao cliente para reposicionar o bloco sem recarregar.
function ag_geom(string $hi, string $hf): array {
  $ini = agenda_minutos($hi); $fim = agenda_minutos($hf);
  $top = ($ini - AG_GRID_START_HOUR * 60) * AG_PIXELS_PER_HOUR / 60;
  $alt = max(20, ($fim - $ini) * AG_PIXELS_PER_HOUR / 60);
  return ['top' => $top, 'height' => $alt];
}

// ---------------------------------------------------------------
// MOVER / REDIMENSIONAR — reagenda um atendimento existente.
// ---------------------------------------------------------------
if ($acao === 'mover' || $acao === 'redimensionar') {
  $id = (int)($in['id'] ?? 0);
  $atend = $id ? store_find('agendamentos', $id) : null;
  if (!$atend) ag_out(['ok' => false, 'error' => 'Atendimento não encontrado.'], 404);

  // Autorização no backend: só o dono (ou admin) pode reagendar.
  if (!agenda_pode_gerir($atend, $terapeutaLogado)) {
    ag_out(['ok' => false, 'error' => 'Você só pode alterar os seus próprios atendimentos.'], 403);
  }
  if (($atend['status'] ?? '') === 'cancelado') {
    ag_out(['ok' => false, 'error' => 'Atendimento cancelado não pode ser reagendado pela grade.'], 422);
  }

  // Redimensionar mantém o dia original; mover pode trocar de dia.
  if ($acao === 'redimensionar') $data = (string)$atend['data'];
  $sala = (string)($atend['sala'] ?? '');

  $v = agenda_validar_intervalo($data, $hi, $hf);
  if (!$v['ok']) ag_out(['ok' => false, 'error' => $v['erro']], 422);

  if (agenda_ha_conflito(store_all('agendamentos'), $data, $hi, $hf, $sala, $id)) {
    $salaLabel = $salasDisponiveis[$sala] ?? $sala;
    ag_out(['ok' => false, 'error' => 'Conflito de horário: a ' . $salaLabel . ' já está ocupada nesse intervalo.'], 409);
  }

  $atualizado = store_update('agendamentos', $id, [
    'data'        => $data,
    'hora_inicio' => $hi,
    'hora_fim'    => $hf,
  ]);
  // Reprograma os lembretes para o novo horário (pacote é preservado — reagendar
  // não reconsome sessão).
  if (function_exists('whats_remover_de_atendimento')) whats_remover_de_atendimento($id);
  if (function_exists('whats_enfileirar_para_atendimento') && ($atualizado['status'] ?? '') !== 'cancelado') {
    whats_enfileirar_para_atendimento($atualizado, store_find('terapeutas', (int)$atualizado['terapeuta_id']));
  }

  ag_out(['ok' => true, 'id' => $id, 'data' => $data, 'hora_inicio' => $hi, 'hora_fim' => $hf] + ag_geom($hi, $hf));
}

// ---------------------------------------------------------------
// CLONAR — cria um NOVO atendimento a partir de um existente.
// Não copia id/status/auditoria; não herda o pacote (evita consumir sessão
// silenciosamente — vincule o pacote pela edição completa, se desejar).
// ---------------------------------------------------------------
if ($acao === 'clonar') {
  $srcId = (int)($in['source_id'] ?? $in['id'] ?? 0);
  $src = $srcId ? store_find('agendamentos', $srcId) : null;
  if (!$src) ag_out(['ok' => false, 'error' => 'Atendimento de origem não encontrado.'], 404);

  // Quem pode gerir a origem pode cloná-la (mantém o terapeuta responsável).
  if (!agenda_pode_gerir($src, $terapeutaLogado)) {
    ag_out(['ok' => false, 'error' => 'Você não tem permissão para clonar este atendimento.'], 403);
  }
  $sala = (string)($src['sala'] ?? '');

  $v = agenda_validar_intervalo($data, $hi, $hf);
  if (!$v['ok']) ag_out(['ok' => false, 'error' => $v['erro']], 422);

  if (agenda_ha_conflito(store_all('agendamentos'), $data, $hi, $hf, $sala, null)) {
    $salaLabel = $salasDisponiveis[$sala] ?? $sala;
    ag_out(['ok' => false, 'error' => 'Conflito de horário: a ' . $salaLabel . ' já está ocupada nesse intervalo.'], 409);
  }

  $novo = store_insert('agendamentos', [
    'data'                => $data,
    'hora_inicio'         => $hi,
    'hora_fim'            => $hf,
    'sala'                => $sala,
    'terapeuta_id'        => (int)($src['terapeuta_id'] ?? $terapeutaLogado['id']),
    'paciente'            => (string)($src['paciente'] ?? ''),
    'paciente_id'         => (int)($src['paciente_id'] ?? 0),
    'paciente_package_id' => 0,
    'terapia'             => (string)($src['terapia'] ?? ''),
    'observacoes'         => (string)($src['observacoes'] ?? ''),
    'status'              => 'agendado',
  ]);
  if (function_exists('whats_enfileirar_para_atendimento')) {
    whats_enfileirar_para_atendimento($novo, store_find('terapeutas', (int)$novo['terapeuta_id']));
  }

  ag_out(['ok' => true, 'id' => (int)$novo['id'], 'data' => $data, 'hora_inicio' => $hi, 'hora_fim' => $hf] + ag_geom($hi, $hf));
}

ag_out(['ok' => false, 'error' => 'Ação desconhecida.'], 400);
