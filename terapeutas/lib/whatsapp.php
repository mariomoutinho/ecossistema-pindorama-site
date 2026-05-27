<?php
// ================================
// Camada de serviço para LEMBRETES de WhatsApp do Espaço Pindorama.
//
// Estado atual (MVP): as mensagens são MONTADAS e armazenadas em uma fila
// (data/terapeutas/lembretes.json) com status "pendente". Nenhum envio real
// acontece — a tela "Lembretes" apenas exibe o que está programado.
//
// >>> INTEGRAÇÃO FUTURA <<<
// Para enviar de verdade, basta implementar whatsapp_send_real($telefone, $mensagem)
// (ver função abaixo) chamando uma API externa (Z-API, Twilio, WhatsApp Cloud API,
// Evolution API, etc.) e depois passar a marcar status = 'enviado' em vez de
// 'pendente' aqui.
// ================================

require_once __DIR__ . '/storage.php';

const LEMBRETE_TIPOS = [
  'pre_consulta'  => '1 dia antes',
  'dia_consulta'  => 'No dia',
  'pos_consulta'  => '2 dias depois',
];

function whats_format_data(string $dataIso): string {
  $ts = strtotime($dataIso);
  if (!$ts) return $dataIso;
  $dias = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
  $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
  return $dias[(int)date('w', $ts)] . ', ' . (int)date('d', $ts) . ' de ' . $meses[(int)date('n', $ts) - 1];
}

function whats_msg_pre_consulta(array $atendimento, array $terapeuta): string {
  $paciente = trim($atendimento['paciente'] ?? '') ?: 'a pessoa atendida';
  $data = whats_format_data($atendimento['data']);
  $hora = substr($atendimento['hora_inicio'] ?? '', 0, 5);
  $nomeT = explode(' ', trim($terapeuta['nome'] ?? 'Terapeuta'))[0];
  return "Oi, {$nomeT}! 🌿 Passando pra lembrar que amanhã ({$data}) você tem atendimento com *{$paciente}* às {$hora}. Que tal já confirmar com a pessoa? Bom cuidado! — Espaço Pindorama";
}

function whats_msg_dia_consulta(array $atendimento, array $terapeuta): string {
  $paciente = trim($atendimento['paciente'] ?? '') ?: 'a pessoa atendida';
  $hora = substr($atendimento['hora_inicio'] ?? '', 0, 5);
  $sala = trim($atendimento['sala_label'] ?? $atendimento['sala'] ?? '') ?: 'sua sala de costume';
  $nomeT = explode(' ', trim($terapeuta['nome'] ?? 'Terapeuta'))[0];
  return "Bom dia, {$nomeT}! ☀️ Hoje você atende *{$paciente}* às {$hora}, em {$sala}. Que o encontro seja cuidadoso e potente. — Espaço Pindorama";
}

function whats_msg_pos_consulta(array $atendimento, array $terapeuta): string {
  $paciente = trim($atendimento['paciente'] ?? '') ?: 'a pessoa atendida';
  $nomeT = explode(' ', trim($terapeuta['nome'] ?? 'Terapeuta'))[0];
  return "Oi, {$nomeT} 💛 Já se passaram dois dias do atendimento com *{$paciente}*. Que tal mandar uma mensagem perguntando como ela tem se sentido depois da sessão? Esse cuidadinho fortalece muito o vínculo. — Espaço Pindorama";
}

/**
 * Gera e enfileira os 3 lembretes (pre, dia, pos) para um atendimento.
 * Idempotente: se já existir lembrete daquele tipo para aquele atendimento,
 * não duplica.
 */
function whats_enfileirar_para_atendimento(array $atendimento, array $terapeuta): array {
  $criados = [];
  $atendId = (int)$atendimento['id'];

  $existentes = store_where('lembretes', fn($r) => (int)($r['atendimento_id'] ?? 0) === $atendId);
  $jaExistem = array_fill_keys(array_column($existentes, 'tipo'), true);

  $tsAtend = strtotime(($atendimento['data'] ?? '') . ' ' . substr($atendimento['hora_inicio'] ?? '08:00', 0, 5));

  $jobs = [
    'pre_consulta' => [
      'agendado_para' => $tsAtend ? date('c', strtotime('-1 day', $tsAtend)) : null,
      'mensagem'      => whats_msg_pre_consulta($atendimento, $terapeuta),
    ],
    'dia_consulta' => [
      'agendado_para' => $tsAtend ? date('Y-m-d\TH:i:sP', strtotime('07:00', strtotime(date('Y-m-d', $tsAtend)))) : null,
      'mensagem'      => whats_msg_dia_consulta($atendimento, $terapeuta),
    ],
    'pos_consulta' => [
      'agendado_para' => $tsAtend ? date('c', strtotime('+2 days', $tsAtend)) : null,
      'mensagem'      => whats_msg_pos_consulta($atendimento, $terapeuta),
    ],
  ];

  foreach ($jobs as $tipo => $j) {
    if (!empty($jaExistem[$tipo])) continue;
    $criados[] = store_insert('lembretes', [
      'atendimento_id' => $atendId,
      'terapeuta_id'   => (int)$terapeuta['id'],
      'destinatario'   => $terapeuta['nome'] ?? '',
      'telefone'       => $terapeuta['telefone'] ?? '',
      'tipo'           => $tipo,
      'tipo_label'     => LEMBRETE_TIPOS[$tipo] ?? $tipo,
      'mensagem'       => $j['mensagem'],
      'agendado_para'  => $j['agendado_para'],
      'status'         => 'pendente',
    ]);
  }

  return $criados;
}

/**
 * Remove todos os lembretes vinculados a um atendimento (uso em cancelamento).
 */
function whats_remover_de_atendimento(int $atendimentoId): int {
  $rows = store_all('lembretes');
  $kept = array_values(array_filter($rows, fn($r) => (int)($r['atendimento_id'] ?? 0) !== $atendimentoId));
  $diff = count($rows) - count($kept);
  if ($diff > 0) store_save('lembretes', $kept);
  return $diff;
}

/**
 * STUB — envio real via API externa.
 * Hoje apenas registra um log e devolve false. Aqui vai entrar a integração futura:
 *   - Z-API / Evolution API / Twilio / WhatsApp Cloud API
 *   - Tratar token, número remetente, retries, webhook de status
 */
function whatsapp_send_real(string $telefone, string $mensagem): bool {
  // TODO(integration): substituir pelo POST à API escolhida.
  $tel = preg_replace('/\D/', '', $telefone);
  error_log(sprintf('[whatsapp_send_real STUB] %s -> %d caracteres (sem envio real ainda).', $tel, strlen($mensagem)));
  return false;
}
