<?php
// ================================
// Camada simples de storage em JSON para o MVP da área restrita.
// Cada "tabela" vira um arquivo JSON em /data/terapeutas/.
// Esta camada foi desenhada para ser substituível por PDO/MySQL no futuro:
// as funções públicas (store_all, store_find, store_insert, store_update,
// store_delete) já mimetizam um CRUD básico.
//
// IMPORTANTE: a pasta /data está no .gitignore — os JSONs vivem só no servidor.
// ================================

if (!defined('TERAP_DATA_DIR')) {
  define('TERAP_DATA_DIR', dirname(__DIR__, 2) . '/data/terapeutas');
}
if (!defined('TERAP_SEED_DIR')) {
  define('TERAP_SEED_DIR', dirname(__DIR__) . '/data-seed');
}

function store_ensure_dir(): void {
  if (!is_dir(TERAP_DATA_DIR)) {
    @mkdir(TERAP_DATA_DIR, 0775, true);
  }
}

function store_path(string $table): string {
  store_ensure_dir();
  return TERAP_DATA_DIR . '/' . basename($table) . '.json';
}

function store_seed_path(string $table): string {
  return TERAP_SEED_DIR . '/' . basename($table) . '.seed.json';
}

function store_bootstrap(string $table): void {
  $path = store_path($table);
  if (is_file($path)) return;

  $seed = store_seed_path($table);
  if (is_file($seed)) {
    @copy($seed, $path);
    @chmod($path, 0664);
    return;
  }

  file_put_contents($path, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Aplica entradas novas do seed a uma tabela já existente, identificando
 * duplicatas por uma chave (e-mail, por padrão). Útil para introduzir novos
 * terapeutas em produção sem precisar mexer no JSON manualmente.
 *
 * Não sobrescreve registros existentes: se a chave já estiver presente,
 * ignora a entrada do seed.
 */
function store_seed_upsert(string $table, string $chave = 'email'): int {
  $seedPath = store_seed_path($table);
  if (!is_file($seedPath)) return 0;

  $seed = json_decode((string)@file_get_contents($seedPath), true);
  if (!is_array($seed)) return 0;

  $existentes = store_all($table);
  $chavesExistentes = [];
  foreach ($existentes as $r) {
    if (!empty($r[$chave])) $chavesExistentes[strtolower((string)$r[$chave])] = true;
  }

  $adicionados = 0;
  foreach ($seed as $entrada) {
    if (empty($entrada[$chave])) continue;
    $k = strtolower((string)$entrada[$chave]);
    if (isset($chavesExistentes[$k])) continue;

    // Garante id único — não confia no id do seed para evitar colisão.
    unset($entrada['id']);
    store_insert($table, $entrada);
    $chavesExistentes[$k] = true;
    $adicionados++;
  }

  return $adicionados;
}

function store_all(string $table): array {
  store_bootstrap($table);
  $raw = @file_get_contents(store_path($table));
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function store_save(string $table, array $rows): bool {
  store_ensure_dir();
  $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  return file_put_contents(store_path($table), $json, LOCK_EX) !== false;
}

function store_find(string $table, $id): ?array {
  foreach (store_all($table) as $row) {
    if (isset($row['id']) && (string)$row['id'] === (string)$id) {
      return $row;
    }
  }
  return null;
}

function store_where(string $table, callable $filter): array {
  $out = [];
  foreach (store_all($table) as $row) {
    if ($filter($row)) $out[] = $row;
  }
  return $out;
}

function store_next_id(string $table): int {
  $rows = store_all($table);
  $max = 0;
  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if ($id > $max) $max = $id;
  }
  return $max + 1;
}

function store_insert(string $table, array $row): array {
  $rows = store_all($table);
  if (empty($row['id'])) {
    $row['id'] = store_next_id($table);
  }
  if (empty($row['criado_em'])) {
    $row['criado_em'] = date('c');
  }
  $rows[] = $row;
  store_save($table, $rows);
  return $row;
}

function store_update(string $table, $id, array $changes): ?array {
  $rows = store_all($table);
  $updated = null;
  foreach ($rows as &$row) {
    if (isset($row['id']) && (string)$row['id'] === (string)$id) {
      $row = array_merge($row, $changes);
      $row['atualizado_em'] = date('c');
      $updated = $row;
      break;
    }
  }
  unset($row);
  if ($updated) store_save($table, $rows);
  return $updated;
}

function store_delete(string $table, $id): bool {
  $rows = store_all($table);
  $new = array_values(array_filter($rows, fn($r) => (string)($r['id'] ?? '') !== (string)$id));
  if (count($new) === count($rows)) return false;
  store_save($table, $new);
  return true;
}
