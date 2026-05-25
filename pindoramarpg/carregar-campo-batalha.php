<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$usuarioCB = exigirLogin();
require_once __DIR__ . '/includes/aventuras-helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Contexto opcional de Aventura: rotear para data/aventuras/<id>/cenas.json
// quando ?aventura_id=N e o usuário for dono da aventura. Senão, estado global.
$aventuraId = isset($_GET['aventura_id']) ? (int) $_GET['aventura_id'] : 0;
if ($aventuraId > 0) {
    $av = carregarAventura($aventuraId);
    if (!$av || (int) $av['usuario_id'] !== (int) $usuarioCB['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Aventura inválida ou não pertence a você.'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $stateFile = aventuraCenasFile($aventuraId);
} else {
    $stateFile = __DIR__ . '/data/campo-batalha-state.json';
}

if (!is_file($stateFile)) {
    echo json_encode([
        'success' => true,
        'state' => null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents($stateFile);
if ($raw === false || trim($raw) === '') {
    echo json_encode([
        'success' => true,
        'state' => null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$state = json_decode($raw, true);
if (!is_array($state)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'O estado salvo da Mesa de Jogo está inválido.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success' => true,
    'state' => $state,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
