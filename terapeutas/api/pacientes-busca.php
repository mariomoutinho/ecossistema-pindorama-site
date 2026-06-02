<?php
// ================================
// Endpoint de autocomplete de pacientes (uso interno da agenda).
// Retorna JSON dos pacientes ATIVOS do terapeuta logado que casam com a busca.
// Devolve apenas dados mínimos de identificação — nunca dados clínicos.
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
$q = trim((string)($_GET['q'] ?? ''));

$res = pac_listar($terapId, $q, 'ativos', 1, 10);

$itens = array_map(function ($p) {
  $idade = pac_idade($p['data_nascimento'] ?? null);
  $tel = $p['whatsapp'] ?: ($p['telefone'] ?? '');
  $detalhe = [];
  if ($idade !== null) $detalhe[] = $idade . ' anos';
  if ($tel) $detalhe[] = $tel;
  return [
    'id'      => (int)$p['id'],
    'nome'    => pac_nome_exibicao($p),
    'detalhe' => implode(' · ', $detalhe),
  ];
}, $res['itens']);

echo json_encode(['itens' => $itens], JSON_UNESCAPED_UNICODE);
