<?php
// ================================
// Bootstrap compartilhado entre as páginas do site.
// Carrega credenciais, abre PDO, define helpers de flash e variáveis globais
// (links de WhatsApp, Instagram, e-mail, endereço e URLs internas do ecossistema).
// ================================

require_once __DIR__ . '/../config-db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!function_exists('flash_set')) {
  function flash_set($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
  }
}
if (!function_exists('flash_get')) {
  function flash_get() {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
  }
}

$pdo = null;
$db_ok = false;
$db_error = null;

try {
  $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $db_ok = true;
} catch (Throwable $e) {
  $db_ok = false;
  $db_error = $e->getMessage();
}

// Ambiente: APP_ENV manda; sem ela, considera dev se o host de banco for local.
$is_dev = defined('APP_ENV')
  ? (APP_ENV === 'development')
  : in_array(DB_HOST, ['127.0.0.1', 'localhost', '::1'], true);

// Contato + redes
$whatsNumber  = '5581995216450';
$whatsLink    = 'https://wa.me/' . $whatsNumber . '?text=' . rawurlencode('Olá! Vim pelo site do Coletivo Pindorama e gostaria de agendar um horário.');
$insta        = 'https://www.instagram.com/coletivo_pindorama';
$instaHandle  = '@coletivo_pindorama';
$emailContato = 'contato@coletivopindorama.com';
$endereco     = 'Espaço Pindorama — Rua Dom Carlos Coelho, 86 — Boa Vista, Recife/PE';

// URLs internas / ecossistema
$homeUrl     = 'index.php';
$terapiasUrl = 'terapias.php';
$cuidarUrl   = 'cuidar-mais/';
$rpgUrl      = 'https://pindoramarpg.coletivopindorama.com.br';
$gessUrl     = 'gess/';
