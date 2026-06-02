<?php
// ================================
// Provisiona o pacote inicial do Ronaldo (Massoterapia — 10 sessões).
// Idempotente e seguro: não duplica paciente nem pacote; trata ambiguidade
// de nomes sem escolher silenciosamente; não presume sessões já realizadas.
//
// Uso (na raiz do repositório):
//   php terapeutas/bin/provisionar-pacote-ronaldo.php
//   php terapeutas/bin/provisionar-pacote-ronaldo.php --paciente-id=12   (desambiguação)
//   php terapeutas/bin/provisionar-pacote-ronaldo.php --aplicar          (cria de fato)
//
// Sem --aplicar, roda em modo "dry-run" (apenas relata o que faria).
// ================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Somente CLI.\n"); }

require_once __DIR__ . '/../lib/env.php';
$dataDirEnv = getenv('TERAP_DATA_DIR');
if ($dataDirEnv !== false && $dataDirEnv !== '' && !defined('TERAP_DATA_DIR')) {
  define('TERAP_DATA_DIR', $dataDirEnv);
}
require_once __DIR__ . '/../lib/storage.php';
require_once __DIR__ . '/../lib/pacientes.php';
require_once __DIR__ . '/../lib/pacotes.php';

$opts = getopt('', ['paciente-id::', 'aplicar']);
$pacienteIdArg = isset($opts['paciente-id']) ? (int)$opts['paciente-id'] : 0;
$aplicar = isset($opts['aplicar']);

foreach (['pacientes', 'pacotes', 'pacote_movimentacoes', 'agendamentos'] as $t) store_bootstrap($t);

// 1) Localiza paciente(s) "Ronaldo" (nome completo ou social).
$alvo = pac_normaliza('ronaldo');
$candidatos = store_where('pacientes', function ($p) use ($alvo) {
  return strpos(pac_normaliza((string)($p['nome_completo'] ?? '')), $alvo) !== false
      || strpos(pac_normaliza((string)($p['nome_social'] ?? '')), $alvo) !== false;
});

if (!$candidatos) {
  fwrite(STDERR, "Nenhum paciente 'Ronaldo' encontrado. Cadastre o paciente primeiro.\n");
  exit(1);
}

$paciente = null;
if ($pacienteIdArg > 0) {
  foreach ($candidatos as $c) if ((int)$c['id'] === $pacienteIdArg) { $paciente = $c; break; }
  if (!$paciente) {
    fwrite(STDERR, "O --paciente-id={$pacienteIdArg} não corresponde a nenhum 'Ronaldo'.\n");
    exit(2);
  }
} elseif (count($candidatos) > 1) {
  fwrite(STDERR, "Há mais de um paciente 'Ronaldo' — não vou escolher sozinho. Reexecute com --paciente-id=ID:\n");
  foreach ($candidatos as $c) {
    fwrite(STDERR, sprintf("  - id=%d | %s | terapeuta_id=%d\n", (int)$c['id'], $c['nome_completo'] ?? '', (int)($c['terapeuta_id'] ?? 0)));
  }
  exit(2);
} else {
  $paciente = $candidatos[0];
}

$pid = (int)$paciente['id'];
$terapId = (int)($paciente['terapeuta_id'] ?? 0);
echo "Paciente: #{$pid} {$paciente['nome_completo']} (terapeuta_id={$terapId}).\n";

// 2) Idempotência: já existe pacote de Massoterapia (10 sessões) para ele?
$jaExiste = store_where('pacotes', function ($p) use ($pid) {
  if ((int)($p['paciente_id'] ?? 0) !== $pid) return false;
  $terapia = mb_strtolower(trim((string)($p['terapia'] ?? '')), 'UTF-8');
  return $terapia === 'massoterapia' && (int)($p['total_sessoes'] ?? 0) === 10;
});
if ($jaExiste) {
  echo "Pacote de Massoterapia (10 sessões) JÁ existe (#{$jaExiste[0]['id']}) — nada a fazer (idempotente).\n";
  exit(0);
}

// 3) Não presume sessões realizadas: lista agendamentos de massoterapia do Ronaldo.
$agdMasso = store_where('agendamentos', function ($a) use ($pid) {
  if ((int)($a['paciente_id'] ?? 0) !== $pid) return false;
  $hay = mb_strtolower((string)($a['terapia'] ?? '') . ' ' . (string)($a['observacoes'] ?? ''), 'UTF-8');
  return strpos($hay, 'massoter') !== false;
});
if ($agdMasso) {
  echo "Atenção: encontrei " . count($agdMasso) . " agendamento(s) de massoterapia já existentes para o Ronaldo.\n";
  echo "Eles NÃO serão vinculados/consumidos automaticamente. Decida manualmente na agenda quais devem consumir sessões:\n";
  foreach ($agdMasso as $a) {
    echo sprintf("  - atend. #%d | %s %s | status=%s\n", (int)$a['id'], $a['data'] ?? '?', substr($a['hora_inicio'] ?? '', 0, 5), $a['status'] ?? '?');
  }
}

// 4) Cria (ou apenas relata em dry-run).
if (!$aplicar) {
  echo "[dry-run] Criaria o pacote 'Massoterapia — 10 sessões' (10 sessões, status ativo). Reexecute com --aplicar para confirmar.\n";
  exit(0);
}

$res = pacote_criar([
  'paciente_id'   => $pid,
  'terapeuta_id'  => $terapId,
  'nome'          => 'Massoterapia — 10 sessões',
  'terapia'       => 'Massoterapia',
  'total_sessoes' => 10,
  'comprado_em'   => date('Y-m-d'),
]);
if (empty($res['ok'])) {
  fwrite(STDERR, 'Falha ao criar o pacote: ' . ($res['erro'] ?? 'erro') . "\n");
  exit(1);
}
echo "Pacote criado (#{$res['pacote']['id']}) com 10 sessões disponíveis. Saldo: " . pacote_disponivel((int)$res['pacote']['id']) . ".\n";
echo "Concluído.\n";
exit(0);
