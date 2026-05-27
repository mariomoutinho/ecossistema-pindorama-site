<?php
// ================================
// Autenticação simples para área restrita de terapeutas (MVP).
// Usa sessão PHP + password_hash/verify. Os terapeutas vivem em
// data/terapeutas/terapeutas.json (criado a partir do seed na 1ª execução).
// ================================

require_once __DIR__ . '/storage.php';

function auth_user(): ?array {
  if (!isset($_SESSION['terapeuta_id'])) return null;
  $row = store_find('terapeutas', $_SESSION['terapeuta_id']);
  if (!$row || empty($row['ativo'])) return null;
  unset($row['senha_hash']);
  return $row;
}

function auth_logged_in(): bool {
  return auth_user() !== null;
}

function auth_require_login(string $loginUrl = 'login.php'): void {
  if (auth_logged_in()) return;
  $back = $_SERVER['REQUEST_URI'] ?? '';
  $qs = $back ? ('?next=' . urlencode($back)) : '';
  header('Location: ' . $loginUrl . $qs);
  exit;
}

function auth_attempt(string $email, string $senha): ?array {
  $email = strtolower(trim($email));
  if ($email === '' || $senha === '') return null;

  $candidatos = store_where('terapeutas', fn($r) => strtolower($r['email'] ?? '') === $email);
  if (!$candidatos) return null;

  $u = $candidatos[0];
  if (empty($u['ativo'])) return null;
  if (!password_verify($senha, $u['senha_hash'] ?? '')) return null;

  // Rehash se algoritmo evoluiu
  if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
    store_update('terapeutas', $u['id'], ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT)]);
  }

  session_regenerate_id(true);
  $_SESSION['terapeuta_id'] = $u['id'];
  $_SESSION['terapeuta_nome'] = $u['nome'] ?? '';
  $_SESSION['terapeuta_login_em'] = time();

  store_update('terapeutas', $u['id'], ['ultimo_acesso' => date('c')]);

  unset($u['senha_hash']);
  return $u;
}

function auth_logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

function auth_csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function auth_csrf_check(?string $token): bool {
  return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function auth_hash(string $senha): string {
  return password_hash($senha, PASSWORD_DEFAULT);
}
