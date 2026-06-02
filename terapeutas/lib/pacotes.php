<?php
// ================================
// PACOTES / COMBOS de sessões + razão (ledger) de saldo.
//
// Tabelas JSON:
//   pacotes               -> patient_packages
//   pacote_movimentacoes  -> patient_package_movements (livro-razão auditável)
//
// Princípios:
//   - O SALDO DISPONÍVEL é derivado do ledger (soma das movimentações), nunca
//     um número solto editável — toda alteração deixa rastro.
//   - Operações que mexem no saldo são IDEMPOTENTES por agendamento (uma
//     reserva por agendamento; devolução só se houver reserva ativa).
//   - Concorrência: as escritas de saldo ocorrem dentro de um LOCK de arquivo
//     (pacote_transacao) para simular transação no storage JSON.
//   - Nunca permite saldo negativo.
//   - Isolamento por terapeuta (pacote_find_do_terapeuta).
// ================================

require_once __DIR__ . '/storage.php';

const PAC_PKG_TABELA = 'pacotes';
const PAC_MOV_TABELA = 'pacote_movimentacoes';

// Tipos de movimentação (movement_type). O sinal padrão está em PAC_MOV_SINAL.
const PAC_MOV_LABELS = [
  'compra'                 => 'Compra / ativação do pacote',
  'reserva'                => 'Reserva por agendamento',
  'realizada'              => 'Sessão realizada',
  'cancelamento_devolucao' => 'Cancelamento com devolução',
  'falta_consumo'          => 'Falta com consumo',
  'falta_devolucao'        => 'Falta com devolução',
  'ajuste_manual'          => 'Ajuste manual',
  'expiracao'              => 'Expiração',
  'cancelamento_pacote'    => 'Cancelamento do pacote',
];

const PAC_PKG_STATUS = ['ativo', 'finalizado', 'expirado', 'cancelado'];

/**
 * Lock coarse-grained para operações de saldo (read-modify-write atômico).
 * Mantém um arquivo de lock em data/terapeutas/. Em caso de falha de lock,
 * executa mesmo assim (melhor esforço), pois o storage usa LOCK_EX por escrita.
 */
function pacote_transacao(callable $fn) {
  if (!defined('TERAP_DATA_DIR')) {
    // storage.php garante a constante; fallback defensivo.
    return $fn();
  }
  $lockPath = TERAP_DATA_DIR . '/.pacotes.lock';
  $fp = @fopen($lockPath, 'c');
  if ($fp && flock($fp, LOCK_EX)) {
    try {
      return $fn();
    } finally {
      flock($fp, LOCK_UN);
      fclose($fp);
    }
  }
  if ($fp) fclose($fp);
  return $fn();
}

/** Pacote pertencente ao terapeuta (ou null). Não vaza pacotes alheios. */
function pacote_find_do_terapeuta($id, int $terapeutaId): ?array {
  $p = store_find(PAC_PKG_TABELA, $id);
  if (!$p) return null;
  if ((int)($p['terapeuta_id'] ?? 0) !== $terapeutaId) return null;
  return $p;
}

/** Movimentações de um pacote, mais antigas primeiro (ordem do ledger). */
function pacote_movimentacoes(int $pacoteId): array {
  $movs = store_where(PAC_MOV_TABELA, fn($r) => (int)($r['pacote_id'] ?? 0) === $pacoteId);
  usort($movs, fn($a, $b) => strcmp((string)($a['criado_em'] ?? ''), (string)($b['criado_em'] ?? '')) ?: ($a['id'] <=> $b['id']));
  return $movs;
}

/** Soma assinada das movimentações = saldo disponível para agendar. */
function pacote_disponivel(int $pacoteId): int {
  $saldo = 0;
  foreach (pacote_movimentacoes($pacoteId) as $m) {
    $saldo += (int)($m['quantidade'] ?? 0);
  }
  return $saldo;
}

/** Conta movimentações de um tipo para um agendamento. */
function pacote_conta_mov(int $pacoteId, int $agendamentoId, string $tipo): int {
  $n = 0;
  foreach (pacote_movimentacoes($pacoteId) as $m) {
    if ((int)($m['agendamento_id'] ?? 0) === $agendamentoId && ($m['tipo'] ?? '') === $tipo) $n++;
  }
  return $n;
}

/**
 * Reserva PENDENTE: foi reservada e ainda não devolvida (cancel/falta-devolução).
 * Usada para IDEMPOTÊNCIA da reserva — enquanto pendente, não reserva de novo
 * (reagendar não reconsome). Após devolução fica 0 → reativar reconsome.
 * Observação: realizar/falta-consumo NÃO zeram a pendência (não se reserva de
 * novo um atendimento já realizado).
 */
function pacote_reserva_pendente(int $pacoteId, int $agendamentoId): bool {
  $net = pacote_conta_mov($pacoteId, $agendamentoId, 'reserva')
       - pacote_conta_mov($pacoteId, $agendamentoId, 'cancelamento_devolucao')
       - pacote_conta_mov($pacoteId, $agendamentoId, 'falta_devolucao');
  return $net > 0;
}

/**
 * Sessão EM ABERTO: reservada, ainda não devolvida E ainda não realizada nem
 * consumida como falta. Só uma sessão em aberto pode ser DEVOLVIDA (não se
 * devolve uma sessão já realizada/consumida).
 */
function pacote_sessao_aberta(int $pacoteId, int $agendamentoId): bool {
  if (!pacote_reserva_pendente($pacoteId, $agendamentoId)) return false;
  if (pacote_conta_mov($pacoteId, $agendamentoId, 'realizada') > 0) return false;
  if (pacote_conta_mov($pacoteId, $agendamentoId, 'falta_consumo') > 0) return false;
  return true;
}

/** Já existe movimentação 'realizada' para este agendamento? */
function pacote_tem_realizada(int $pacoteId, int $agendamentoId): bool {
  return pacote_conta_mov($pacoteId, $agendamentoId, 'realizada') > 0;
}

/** Registra uma movimentação calculando o saldo_apos. Uso interno. */
function pacote_registrar_mov(int $pacoteId, string $tipo, int $quantidade, array $opts = []): array {
  $saldoApos = pacote_disponivel($pacoteId) + $quantidade;
  return store_insert(PAC_MOV_TABELA, [
    'pacote_id'      => $pacoteId,
    'agendamento_id' => (int)($opts['agendamento_id'] ?? 0),
    'terapeuta_id'   => (int)($opts['terapeuta_id'] ?? 0),
    'tipo'           => $tipo,
    'quantidade'     => $quantidade,
    'saldo_apos'     => $saldoApos,
    'motivo'         => (string)($opts['motivo'] ?? ''),
  ]);
}

/**
 * Cria um pacote + movimentação inicial de 'compra' (+total).
 * Retorna ['ok'=>bool, 'pacote'=>?array, 'erro'=>?string].
 */
function pacote_criar(array $dados): array {
  return pacote_transacao(function () use ($dados) {
    $pacienteId  = (int)($dados['paciente_id'] ?? 0);
    $terapeutaId = (int)($dados['terapeuta_id'] ?? 0);
    $total       = (int)($dados['total_sessoes'] ?? 0);
    $nome        = trim((string)($dados['nome'] ?? ''));

    if ($pacienteId <= 0 || $terapeutaId <= 0) return ['ok' => false, 'erro' => 'Paciente/terapeuta inválido.'];
    if ($nome === '') return ['ok' => false, 'erro' => 'Informe o nome do pacote.'];
    if ($total <= 0)  return ['ok' => false, 'erro' => 'Quantidade total de sessões deve ser maior que zero.'];

    $pkg = store_insert(PAC_PKG_TABELA, [
      'paciente_id'   => $pacienteId,
      'terapeuta_id'  => $terapeutaId,
      'nome'          => $nome,
      'terapia'       => trim((string)($dados['terapia'] ?? '')),
      'total_sessoes' => $total,
      'comprado_em'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($dados['comprado_em'] ?? '')) ? $dados['comprado_em'] : date('Y-m-d'),
      'validade'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($dados['validade'] ?? '')) ? $dados['validade'] : '',
      'valor_total'   => (string)($dados['valor_total'] ?? ''),
      'valor_sessao'  => (string)($dados['valor_sessao'] ?? ''),
      'observacoes'   => trim((string)($dados['observacoes'] ?? '')),
      'status'        => 'ativo',
    ]);
    pacote_registrar_mov((int)$pkg['id'], 'compra', $total, [
      'terapeuta_id' => $terapeutaId,
      'motivo'       => 'Ativação do pacote',
    ]);
    return ['ok' => true, 'pacote' => $pkg];
  });
}

/**
 * Reserva 1 sessão para um agendamento (idempotente).
 * Retorna ['ok'=>bool, 'erro'=>?string, 'ja'=>bool].
 */
function pacote_reservar(int $pacoteId, int $agendamentoId, int $terapeutaId): array {
  return pacote_transacao(function () use ($pacoteId, $agendamentoId, $terapeutaId) {
    $pkg = store_find(PAC_PKG_TABELA, $pacoteId);
    if (!$pkg) return ['ok' => false, 'erro' => 'Pacote não encontrado.'];
    if (($pkg['status'] ?? '') !== 'ativo') return ['ok' => false, 'erro' => 'Pacote não está ativo.'];
    if (pacote_reserva_pendente($pacoteId, $agendamentoId)) return ['ok' => true, 'ja' => true];
    if (pacote_disponivel($pacoteId) < 1) return ['ok' => false, 'erro' => 'Sem sessões disponíveis neste pacote.'];

    pacote_registrar_mov($pacoteId, 'reserva', -1, [
      'agendamento_id' => $agendamentoId,
      'terapeuta_id'   => $terapeutaId,
      'motivo'         => 'Reserva por agendamento',
    ]);
    return ['ok' => true, 'ja' => false];
  });
}

/**
 * Devolve a sessão reservada de um agendamento (cancelamento). Idempotente:
 * só devolve se houver reserva ativa.
 */
function pacote_devolver(int $pacoteId, int $agendamentoId, int $terapeutaId, string $motivo = 'Cancelamento com devolução'): array {
  return pacote_transacao(function () use ($pacoteId, $agendamentoId, $terapeutaId, $motivo) {
    if (!pacote_sessao_aberta($pacoteId, $agendamentoId)) return ['ok' => true, 'ja' => true];
    pacote_registrar_mov($pacoteId, 'cancelamento_devolucao', +1, [
      'agendamento_id' => $agendamentoId,
      'terapeuta_id'   => $terapeutaId,
      'motivo'         => $motivo,
    ]);
    return ['ok' => true, 'ja' => false];
  });
}

/**
 * Converte a reserva em 'realizada' (não mexe no saldo). Idempotente.
 */
function pacote_marcar_realizada(int $pacoteId, int $agendamentoId, int $terapeutaId): array {
  return pacote_transacao(function () use ($pacoteId, $agendamentoId, $terapeutaId) {
    if (pacote_tem_realizada($pacoteId, $agendamentoId)) return ['ok' => true, 'ja' => true];
    pacote_registrar_mov($pacoteId, 'realizada', 0, [
      'agendamento_id' => $agendamentoId,
      'terapeuta_id'   => $terapeutaId,
      'motivo'         => 'Sessão realizada',
    ]);
    return ['ok' => true, 'ja' => false];
  });
}

/**
 * Falta: consome a sessão (mantém a reserva como gasta, qty 0) ou devolve (+1).
 */
function pacote_falta(int $pacoteId, int $agendamentoId, int $terapeutaId, bool $consumir, string $motivo = ''): array {
  return pacote_transacao(function () use ($pacoteId, $agendamentoId, $terapeutaId, $consumir, $motivo) {
    if ($consumir) {
      pacote_registrar_mov($pacoteId, 'falta_consumo', 0, [
        'agendamento_id' => $agendamentoId, 'terapeuta_id' => $terapeutaId,
        'motivo' => $motivo ?: 'Falta com consumo da sessão',
      ]);
    } else {
      if (!pacote_sessao_aberta($pacoteId, $agendamentoId)) return ['ok' => true, 'ja' => true];
      pacote_registrar_mov($pacoteId, 'falta_devolucao', +1, [
        'agendamento_id' => $agendamentoId, 'terapeuta_id' => $terapeutaId,
        'motivo' => $motivo ?: 'Falta com devolução ao saldo',
      ]);
    }
    return ['ok' => true];
  });
}

/**
 * Ajuste manual do saldo. Exige motivo. delta pode ser + ou -.
 * Não permite levar o saldo a negativo.
 */
function pacote_ajuste_manual(int $pacoteId, int $terapeutaId, int $delta, string $motivo): array {
  return pacote_transacao(function () use ($pacoteId, $terapeutaId, $delta, $motivo) {
    $motivo = trim($motivo);
    if ($delta === 0) return ['ok' => false, 'erro' => 'Informe uma quantidade diferente de zero.'];
    if ($motivo === '') return ['ok' => false, 'erro' => 'O motivo do ajuste é obrigatório.'];
    if (pacote_disponivel($pacoteId) + $delta < 0) return ['ok' => false, 'erro' => 'O ajuste deixaria o saldo negativo.'];

    pacote_registrar_mov($pacoteId, 'ajuste_manual', $delta, [
      'terapeuta_id' => $terapeutaId,
      'motivo'       => $motivo,
    ]);
    return ['ok' => true];
  });
}

/**
 * Resumo do pacote para exibição. 'disponivel' vem do ledger; as contagens de
 * agendadas/realizadas vêm dos agendamentos vinculados (mais legível ao usuário).
 */
function pacote_resumo(array $pkg): array {
  $id = (int)$pkg['id'];
  $total = (int)($pkg['total_sessoes'] ?? 0);
  $disponivel = pacote_disponivel($id);

  $vinculados = store_where('agendamentos', fn($r) => (int)($r['paciente_package_id'] ?? 0) === $id);
  $agendadas = $realizadas = $canceladas = $faltas = 0;
  foreach ($vinculados as $a) {
    switch ($a['status'] ?? 'agendado') {
      case 'realizado':  $realizadas++; break;
      case 'cancelado':  $canceladas++; break;
      case 'falta':      $faltas++;     break;
      default:           $agendadas++;  break; // agendado, confirmado, reagendado
    }
  }
  return [
    'total'      => $total,
    'disponivel' => max(0, $disponivel),
    'agendadas'  => $agendadas,
    'realizadas' => $realizadas,
    'canceladas' => $canceladas,
    'faltas'     => $faltas,
  ];
}

/** Pacotes ativos de um paciente (do terapeuta), opcionalmente por terapia. */
function pacote_ativos_do_paciente(int $pacienteId, int $terapeutaId, string $terapia = ''): array {
  $lista = store_where(PAC_PKG_TABELA, function ($r) use ($pacienteId, $terapeutaId) {
    return (int)($r['paciente_id'] ?? 0) === $pacienteId
        && (int)($r['terapeuta_id'] ?? 0) === $terapeutaId
        && ($r['status'] ?? '') === 'ativo';
  });
  if ($terapia !== '') {
    $alvo = mb_strtolower(trim($terapia), 'UTF-8');
    $lista = array_values(array_filter($lista, function ($p) use ($alvo) {
      $t = mb_strtolower(trim((string)($p['terapia'] ?? '')), 'UTF-8');
      return $t === '' || $t === $alvo; // pacote sem terapia casa com tudo
    }));
  }
  return $lista;
}
