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
