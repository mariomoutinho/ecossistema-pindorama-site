<?php
// ================================
// Regras de domínio dos AGENDAMENTOS (área restrita).
// Começa pela propriedade/permissão (Fase 1); crescerá com pacotes,
// recorrência e movimentação por arraste nas fases seguintes.
//
// Modelo de acesso adotado:
//   - A agenda é VISÍVEL para toda a equipe (evita choque de sala).
//   - Cada terapeuta só PODE GERIR (criar/editar/mover/cancelar/excluir) os
//     próprios agendamentos.
//   - Um perfil 'admin' (papel = 'admin') pode gerir todos.
//   - A validação é sempre no backend — nunca confiando no frontend.
// ================================

require_once __DIR__ . '/storage.php';

// ----------------------------------------------------------------------
// Geometria da grade semanal (fonte ÚNICA de verdade, espelhada no JS via
// window.AG_GRID). A renderização (PHP), o arraste e o redimensionamento
// (JS) DEVEM usar estes mesmos números para não divergirem.
// ----------------------------------------------------------------------
const AG_GRID_START_HOUR = 7;   // primeira linha da grade (07h)
const AG_GRID_END_HOUR   = 22;  // última linha (22h) — comporta eventos até 22:00
const AG_PIXELS_PER_HOUR = 64;  // altura de 1 hora na grade
const AG_SNAP_MIN        = 15;  // encaixe (arraste/redimensionamento)
const AG_MIN_DURACAO_MIN = 15;  // duração mínima de um atendimento

/** "HH:MM" -> minutos desde 00:00, ou null se inválido. */
function agenda_minutos(?string $hhmm): ?int {
  if (!is_string($hhmm) || !preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m)) return null;
  $h = (int)$m[1]; $min = (int)$m[2];
  if ($h < 0 || $h > 23 || $min < 0 || $min > 59) return null;
  return $h * 60 + $min;
}

/** minutos -> "HH:MM". */
function agenda_hhmm(int $minutos): string {
  $minutos = max(0, $minutos);
  return sprintf('%02d:%02d', intdiv($minutos, 60) % 24, $minutos % 60);
}

/**
 * Conflito de SALA: dois atendimentos não-cancelados na mesma data e sala não
 * podem se sobrepor no tempo. (A regra do projeto é por SALA, não por
 * terapeuta — preservada.) Reutilizada pela tela e pelo endpoint AJAX.
 */
function agenda_ha_conflito(array $todos, string $data, string $hi, string $hf, string $sala, ?int $ignorarId = null): bool {
  $ini = agenda_minutos($hi); $fim = agenda_minutos($hf);
  if ($ini === null || $fim === null) return false;
  foreach ($todos as $a) {
    if ($ignorarId !== null && (int)($a['id'] ?? 0) === $ignorarId) continue;
    if (($a['status'] ?? '') === 'cancelado') continue;
    if (($a['data'] ?? '') !== $data) continue;
    if (($a['sala'] ?? '') !== $sala) continue;
    $aIni = agenda_minutos(substr((string)($a['hora_inicio'] ?? ''), 0, 5));
    $aFim = agenda_minutos(substr((string)($a['hora_fim'] ?? ''), 0, 5));
    if ($aIni === null || $aFim === null) continue;
    if ($ini < $aFim && $fim > $aIni) return true;
  }
  return false;
}

/**
 * Valida data + intervalo de um atendimento para mover/redimensionar/criar.
 * Retorna ['ok'=>bool, 'erro'=>string]. Garante formato, fim>início, duração
 * mínima e que o evento caiba na faixa visível da grade (07h–22h).
 */
function agenda_validar_intervalo(string $data, string $hi, string $hf): array {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return ['ok' => false, 'erro' => 'Data inválida.'];
  $ini = agenda_minutos($hi); $fim = agenda_minutos($hf);
  if ($ini === null) return ['ok' => false, 'erro' => 'Hora de início inválida.'];
  if ($fim === null) return ['ok' => false, 'erro' => 'Hora de fim inválida.'];
  if ($fim <= $ini) return ['ok' => false, 'erro' => 'A hora de fim deve ser maior que a de início.'];
  if (($fim - $ini) < AG_MIN_DURACAO_MIN) return ['ok' => false, 'erro' => 'Duração mínima de ' . AG_MIN_DURACAO_MIN . ' minutos.'];
  if ($ini < AG_GRID_START_HOUR * 60 || $fim > AG_GRID_END_HOUR * 60) {
    return ['ok' => false, 'erro' => 'Horário fora da faixa da agenda (' . AG_GRID_START_HOUR . 'h–' . AG_GRID_END_HOUR . 'h).'];
  }
  return ['ok' => true, 'erro' => ''];
}

// Status possíveis de um atendimento (Fase 3).
const AGENDA_STATUS = [
  'agendado'   => 'Agendado',
  'confirmado' => 'Confirmado',
  'realizado'  => 'Realizado',
  'cancelado'  => 'Cancelado',
  'falta'      => 'Falta',
  'reagendado' => 'Reagendado',
];

function agenda_status_label(string $s): string {
  return AGENDA_STATUS[$s] ?? ucfirst($s);
}

/** Status que ainda "ocupam" a sessão do pacote (reserva pendente). */
function agenda_status_ativo(string $s): bool {
  return in_array($s, ['agendado', 'confirmado', 'reagendado'], true);
}

function agenda_is_admin(?array $terapeuta): bool {
  return ($terapeuta['papel'] ?? '') === 'admin';
}

/**
 * Posição "Sessão N de M" de um agendamento dentro do pacote.
 * N = ordem cronológica entre os atendimentos do pacote que consomem sessão
 * (qualquer status menos 'cancelado'); M = total de sessões do pacote.
 * Retorna ['n'=>int,'m'=>int] ou null se não houver pacote.
 */
function agenda_sessao_no_pacote(array $atend): ?array {
  $pkgId = (int)($atend['paciente_package_id'] ?? 0);
  if ($pkgId <= 0) return null;
  $pkg = store_find('pacotes', $pkgId);
  if (!$pkg) return null;

  $irmaos = store_where('agendamentos', function ($a) use ($pkgId) {
    return (int)($a['paciente_package_id'] ?? 0) === $pkgId
        && ($a['status'] ?? 'agendado') !== 'cancelado';
  });
  usort($irmaos, fn($a, $b) => strcmp(
    ($a['data'] ?? '') . ($a['hora_inicio'] ?? ''),
    ($b['data'] ?? '') . ($b['hora_inicio'] ?? '')
  ));
  $n = 0;
  foreach ($irmaos as $i => $a) {
    if ((int)$a['id'] === (int)$atend['id']) { $n = $i + 1; break; }
  }
  return ['n' => $n, 'm' => (int)($pkg['total_sessoes'] ?? 0)];
}

/**
 * Pode o terapeuta logado gerir este agendamento?
 * - admin: sempre.
 * - demais: somente se for o dono (terapeuta_id do agendamento).
 * Agendamento inexistente => false.
 */
function agenda_pode_gerir(?array $atend, ?array $terapeuta): bool {
  if (!$atend || !$terapeuta) return false;
  if (agenda_is_admin($terapeuta)) return true;
  return (int)($atend['terapeuta_id'] ?? 0) === (int)($terapeuta['id'] ?? -1);
}

/**
 * Resolve o terapeuta dono de um novo/editado agendamento, com base nas
 * permissões. Não confia no frontend:
 *   - admin: pode atribuir a qualquer terapeuta ATIVO (valida o id enviado);
 *            se inválido, cai no próprio.
 *   - demais: sempre o próprio terapeuta logado (ignora o que vier do form).
 * Em edição, $atendExistente garante que não-admin não "rouba" o registro.
 */
function agenda_resolver_terapeuta_id(?array $terapeutaLogado, int $terapIdEnviado, ?array $atendExistente = null): int {
  $logadoId = (int)($terapeutaLogado['id'] ?? 0);

  if (!agenda_is_admin($terapeutaLogado)) {
    // Não-admin: dono é sempre ele. Em edição preserva o dono original
    // (que, por já ter passado pela checagem de permissão, é ele mesmo).
    return $atendExistente ? (int)($atendExistente['terapeuta_id'] ?? $logadoId) : $logadoId;
  }

  // Admin: aceita o terapeuta enviado se for um terapeuta ativo.
  if ($terapIdEnviado > 0) {
    $t = store_find('terapeutas', $terapIdEnviado);
    if ($t && !empty($t['ativo'])) return $terapIdEnviado;
  }
  return $atendExistente ? (int)($atendExistente['terapeuta_id'] ?? $logadoId) : $logadoId;
}
