<?php
// ================================
// Carregador da base de pontos e regiões do módulo experimental.
//
// Por que JSON e não MySQL no MVP:
//  - Permite editar a base sem precisar de migrations.
//  - Estrutura já é compatível com o que entraria numa tabela `pontos`
//    no futuro (basta mapear chaves -> colunas + JOIN para arrays).
//
// >>> Para crescer pra 100+ pontos: basta adicionar entradas em
//     seed/pontos.json no mesmo formato. As funções abaixo derivam
//     catálogos automaticamente, então sintomas/síndromes/ações novos
//     aparecem sozinhos no formulário.
// ================================

if (!defined('ACUP_BASE_DIR')) {
  define('ACUP_BASE_DIR', dirname(__DIR__));
}

function acup_load_json(string $relative): array {
  $path = ACUP_BASE_DIR . '/seed/' . ltrim($relative, '/');
  if (!is_file($path)) return [];
  $raw = (string)file_get_contents($path);
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function acup_pontos(): array {
  static $cache = null;
  if ($cache === null) $cache = acup_load_json('pontos.json');
  return $cache;
}

function acup_regioes(): array {
  static $cache = null;
  if ($cache === null) $cache = acup_load_json('regioes.json');
  return $cache;
}

function acup_find_ponto(string $codigo): ?array {
  foreach (acup_pontos() as $p) {
    if (strcasecmp($p['codigo'] ?? '', $codigo) === 0) return $p;
  }
  return null;
}

/**
 * Catálogo único e ordenado de valores de um campo "lista" presente em
 * cada ponto. Usado para montar checkboxes dinâmicos no formulário.
 */
function acup_catalogo(string $campo): array {
  $bag = [];
  foreach (acup_pontos() as $p) {
    $vals = $p[$campo] ?? [];
    if (!is_array($vals)) continue;
    foreach ($vals as $v) {
      $k = mb_strtolower(trim((string)$v), 'UTF-8');
      if ($k === '') continue;
      // mantém a primeira forma "natural" encontrada
      if (!isset($bag[$k])) $bag[$k] = $v;
    }
  }
  $valores = array_values($bag);
  sort($valores, SORT_STRING | SORT_FLAG_CASE);
  return $valores;
}

/**
 * Lista distinta de meridianos representados na base.
 */
function acup_meridianos(): array {
  $bag = [];
  foreach (acup_pontos() as $p) {
    $m = trim((string)($p['meridiano'] ?? ''));
    if ($m !== '') $bag[$m] = true;
  }
  $out = array_keys($bag);
  sort($out, SORT_STRING | SORT_FLAG_CASE);
  return $out;
}

/**
 * Lista de pontos para autocomplete: ['IG4 — Hegu', ...] (label legível).
 * O value retornado é o código (IG4), usado tanto na recomendação
 * (sinergia com pontos já utilizados) quanto na exibição.
 */
function acup_pontos_para_autocomplete(): array {
  $out = [];
  foreach (acup_pontos() as $p) {
    $codigo = (string)($p['codigo'] ?? '');
    $nome   = (string)($p['nome']   ?? '');
    if ($codigo === '') continue;
    $out[] = ['value' => $codigo, 'label' => $codigo . ' — ' . $nome];
  }
  usort($out, fn($a, $b) => strcmp($a['label'], $b['label']));
  return $out;
}

/**
 * Práticas associadas à acupuntura no Espaço Pindorama.
 * Lista curada — não derivada da base de pontos.
 */
function acup_praticas(): array {
  $lista = [
    'Acupuntura tradicional (agulha filiforme)',
    'Auriculoterapia',
    'Cromoterapia',
    'Cupping/Ventosaterapia',
    'Eletroacupuntura',
    'Escalpoacupuntura',
    'Esfera/Cristal aderente',
    'Magnetoterapia',
    'Massagem Tui Ná',
    'Moxabustão direta',
    'Moxabustão indireta',
    'Sangria/Microsangria',
    'Sementes de mostarda',
    'Shiatsu',
  ];
  sort($lista, SORT_STRING | SORT_FLAG_CASE);
  return $lista;
}
