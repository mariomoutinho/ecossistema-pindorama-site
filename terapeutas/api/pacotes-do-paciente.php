<?php
// ================================
// Endpoint: pacotes ATIVOS de um paciente (do terapeuta logado), com saldo.
// Usado pelo formulário de agendamento para mostrar pacotes compatíveis.
// Só dados de saldo/identificação — nada clínico.
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

$terapId = (int)$terapeutaLogado['id'];
$pacienteId = (int)($_GET['paciente_id'] ?? 0);
$terapia = trim((string)($_GET['terapia'] ?? ''));

// Confirma que o paciente é do terapeuta (não vaza pacotes alheios).
if ($pacienteId <= 0 || !pac_find_do_terapeuta($pacienteId, $terapId)) {
  echo json_encode(['itens' => []]);
  exit;
}

$pkgs = pacote_ativos_do_paciente($pacienteId, $terapId, $terapia);
$itens = array_map(function ($p) {
  return [
    'id'         => (int)$p['id'],
    'nome'       => (string)($p['nome'] ?? ''),
    'terapia'    => (string)($p['terapia'] ?? ''),
    'total'      => (int)($p['total_sessoes'] ?? 0),
    'disponivel' => max(0, pacote_disponivel((int)$p['id'])),
  ];
}, $pkgs);

echo json_encode(['itens' => $itens], JSON_UNESCAPED_UNICODE);
