<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/mesa-helpers.php';
require_once __DIR__ . '/config.php';

$usuario = exigirLogin();
exigirFacilitador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarCsrf($_POST['csrf'] ?? null)) {
    header('Location: mesas.php?type=error&msg=' . urlencode('Requisição inválida.'));
    exit;
}

$mesaId = (int) ($_POST['mesa_id'] ?? 0);
$mesa = $mesaId > 0 ? carregarMesa($mesaId) : null;
if (!$mesa || (int) $mesa['facilitador_id'] !== (int) $usuario['id']) {
    header('Location: acesso-negado.php?m=' . urlencode('Você não pode editar esta mesa.'));
    exit;
}

// Modo 1: vinculação em lote via checkboxes participantes[] (lista de IDs).
// Modo 2: adicionar por e-mail (fallback). Os dois modos convergem no
//          mesmo INSERT IGNORE, então o handler aceita os dois sem
//          duplicar lógica.
$idsParaVincular = [];
if (isset($_POST['participantes']) && is_array($_POST['participantes'])) {
    foreach ($_POST['participantes'] as $idRaw) {
        $id = (int) $idRaw;
        if ($id > 0) $idsParaVincular[$id] = true;
    }
}

$email = trim((string) ($_POST['email'] ?? ''));
if ($email !== '') {
    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $alvo = $stmt->fetch();
    if (!$alvo) {
        header('Location: mesas.php?id=' . $mesaId . '&type=error&msg=' . urlencode('Usuário com esse email não foi encontrado.'));
        exit;
    }
    $idsParaVincular[(int) $alvo['id']] = true;
}

if (empty($idsParaVincular)) {
    header('Location: mesas.php?id=' . $mesaId . '&type=error&msg=' . urlencode('Selecione ao menos um participante ou informe um e-mail.'));
    exit;
}

$stmt = $pdo->prepare(
    "INSERT IGNORE INTO mesa_participantes (mesa_id, usuario_id, papel)
     VALUES (:mesa, :uid, 'participante')"
);
$adicionados = 0;
foreach (array_keys($idsParaVincular) as $uid) {
    $stmt->execute(['mesa' => $mesaId, 'uid' => $uid]);
    if ($stmt->rowCount() > 0) $adicionados++;
}

$msg = $adicionados === 0
    ? 'Nada novo vinculado (os selecionados já estavam na mesa).'
    : ($adicionados === 1 ? '1 participante vinculado.' : $adicionados . ' participantes vinculados.');

header('Location: mesas.php?id=' . $mesaId . '&type=success&msg=' . urlencode($msg));
