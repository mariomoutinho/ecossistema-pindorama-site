<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$usuarioSCB = exigirLogin();
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/aventuras-helpers.php';

// Contexto opcional: ?aventura_id=N rota o save para a pasta da aventura
// em vez do estado global. Só dono pode gravar.
$aventuraIdSCB = isset($_GET['aventura_id']) ? (int) $_GET['aventura_id'] : 0;
$avSCB = null;
if ($aventuraIdSCB > 0) {
    $avSCB = carregarAventura($aventuraIdSCB);
    if (!$avSCB || (int) $avSCB['usuario_id'] !== (int) $usuarioSCB['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Aventura inválida ou não pertence a você.'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

// Apenas o Facilitador pode editar/salvar a cena da Mesa de Jogo.
if (!isFacilitador()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Apenas o Facilitador pode salvar a cena.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$state = json_decode($raw ?: '', true);

if (!is_array($state)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Dados inválidos para salvar a Mesa de Jogo.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!array_key_exists('pages', $state) || !is_array($state['pages'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A Mesa de Jogo precisa conter ao menos a lista de cenas.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível criar a pasta de dados.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
@chmod($dataDir, 0777);

if (!is_writable($dataDir)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A pasta data não tem permissão de gravação para o servidor.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($avSCB) {
    // Aventura: usa pasta dedicada e cria se necessário.
    aventuraGarantirPastaCenas((int) $avSCB['id']);
    $stateFile = aventuraCenasFile((int) $avSCB['id']);
} else {
    $stateFile = $dataDir . '/campo-batalha-state.json';
}

// ---------------------------------------------------------------
// Proteção contra overwrite acidental do estado:
// se o estado novo está vazio (sem cenas, ou só com cena vazia
// SEM tokens/scenery) MAS o arquivo atual contém dados reais,
// recusar a gravação e responder com 409. Isso evita que um bug
// no JS (ex.: state inicial não populado) zere as cenas salvas.
// O Facilitador pode forçar com ?force=1 (raro, override consciente).
// ---------------------------------------------------------------
$forcar = isset($_GET['force']) && $_GET['force'] === '1';

if (!$forcar && is_file($stateFile)) {
    $existenteRaw = file_get_contents($stateFile);
    $existente = $existenteRaw ? json_decode($existenteRaw, true) : null;
    if (is_array($existente) && estadoTemConteudo($existente) && !estadoTemConteudo($state)) {
        // Backup defensivo do existente — se eventualmente o usuário
        // realmente quer sobrescrever, ele tem o snapshot daqui.
        $backupName = 'campo-batalha-state.backup-' . date('Ymd-His') . '.json';
        @copy($stateFile, $dataDir . '/' . $backupName);
        http_response_code(409);
        error_log('[mesa-jogo] Recusou overwrite vazio do state. Backup gerado: ' . $backupName);
        echo json_encode([
            'success' => false,
            'message' => 'Recusado: o novo estado está vazio mas o existente tem cenas com tokens. Backup criado em data/' . $backupName . '. Recarregue a página para tentar de novo, ou use ?force=1 se quiser realmente zerar.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$tmpFile = $stateFile . '.tmp';
$state = materializarImagensDoCampo($state);
$json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false || file_put_contents($tmpFile, $json, LOCK_EX) === false || !rename($tmpFile, $stateFile)) {
    @unlink($tmpFile);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível salvar o estado da Mesa de Jogo.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/**
 * Determina se um estado da Mesa de Jogo tem conteúdo significativo —
 * ao menos uma cena com pelo menos um token, cenário, terreno marcado
 * ou imagem de fundo.
 */
function estadoTemConteudo(array $state): bool
{
    if (empty($state['pages']) || !is_array($state['pages'])) {
        return false;
    }
    foreach ($state['pages'] as $page) {
        if (!is_array($page)) continue;
        if (!empty($page['tokens']) && is_array($page['tokens']) && count($page['tokens']) > 0) {
            return true;
        }
        if (!empty($page['scenery']) && is_array($page['scenery']) && count($page['scenery']) > 0) {
            return true;
        }
        if (!empty($page['mapBackground'])) {
            return true;
        }
        if (!empty($page['terrainDifficult']) && is_array($page['terrainDifficult']) && count($page['terrainDifficult']) > 0) {
            return true;
        }
        if (!empty($page['terrainBarriers']) && is_array($page['terrainBarriers']) && count($page['terrainBarriers']) > 0) {
            return true;
        }
        if (!empty($page['turns']) && is_array($page['turns']) && count($page['turns']) > 0) {
            return true;
        }
    }
    return false;
}

function materializarImagensDoCampo(array $state): array
{
    if (!isset($state['pages']) || !is_array($state['pages'])) {
        return $state;
    }

    foreach ($state['pages'] as &$page) {
        if (!is_array($page)) {
            continue;
        }

        if (isset($page['mapBackground']) && is_string($page['mapBackground'])) {
            $page['mapBackground'] = salvarDataUrlImagem($page['mapBackground']) ?? $page['mapBackground'];
        }

        if (isset($page['scenery']) && is_array($page['scenery'])) {
            foreach ($page['scenery'] as &$item) {
                if (!is_array($item) || !isset($item['src']) || !is_string($item['src'])) {
                    continue;
                }
                $item['src'] = salvarDataUrlImagem($item['src']) ?? $item['src'];
            }
            unset($item);
        }
    }
    unset($page);

    return $state;
}

function salvarDataUrlImagem(string $valor): ?string
{
    if (strpos($valor, 'data:image/') !== 0) {
        return null;
    }

    if (!preg_match('#^data:image/(png|jpe?g|webp|gif);base64,(.+)$#i', $valor, $matches)) {
        return null;
    }

    $ext = strtolower($matches[1]);
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    $binario = base64_decode($matches[2], true);
    if ($binario === false || $binario === '') {
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/campo-batalha/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        return null;
    }
    @chmod($uploadDir, 0777);
    if (!is_writable($uploadDir)) {
        return null;
    }

    $nome = uniqid('campo_', true) . '.' . $ext;
    $destino = $uploadDir . $nome;
    if (file_put_contents($destino, $binario, LOCK_EX) === false) {
        return null;
    }

    return 'uploads/campo-batalha/' . $nome;
}
