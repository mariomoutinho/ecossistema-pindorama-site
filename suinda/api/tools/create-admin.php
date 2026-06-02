<?php

declare(strict_types=1);

// ============================================================================
// Cria (ou promove) um ADMINISTRADOR real do Suindá, com senha definida por
// você — sem usar/expor o usuário de demonstração.
//
// USO (no servidor, via SSH; este script é bloqueado para acesso web):
//
//   Interativo (recomendado — a senha não aparece na tela nem no histórico):
//     php suinda/api/tools/create-admin.php
//
//   Não-interativo (ex.: provisionamento automatizado):
//     SUINDA_ADMIN_NAME="Fulana" SUINDA_ADMIN_EMAIL="fulana@dominio" \
//     SUINDA_ADMIN_PASSWORD='senha-forte' SUINDA_DISABLE_DEMO=1 \
//     php suinda/api/tools/create-admin.php
//
// Observações de segurança:
//   - A senha é guardada com password_hash (bcrypt). Nunca em texto puro.
//   - Não passe a senha por argumento de linha de comando (fica no histórico
//     e na lista de processos). Use o modo interativo ou a variável de ambiente.
//   - SUINDA_DISABLE_DEMO=1 (ou a confirmação interativa) desativa os usuários
//     de demonstração (aluno@suinda.com / admin@suinda.com) para que não fiquem
//     acessíveis em produção.
// ============================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.\n");
}

require_once __DIR__ . '/../src/App.php';

$config = require __DIR__ . '/../config.php';
$localConfig = __DIR__ . '/../config.local.php';
if (is_file($localConfig)) {
    $override = require $localConfig;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}
// Nunca semear dados de demonstração ao rodar este utilitário.
$config['seed_on_boot'] = false;

function fail(string $message): void
{
    fwrite(STDERR, "✗ " . $message . "\n");
    exit(1);
}

function ask(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
}

function askHidden(string $label): string
{
    fwrite(STDOUT, $label);
    $isTty = function_exists('stream_isatty') ? @stream_isatty(STDIN) : false;
    if ($isTty && DIRECTORY_SEPARATOR !== '\\') {
        @shell_exec('stty -echo');
        $line = fgets(STDIN);
        @shell_exec('stty echo');
        fwrite(STDOUT, "\n");
        return $line === false ? '' : rtrim($line, "\r\n");
    }
    // Sem terminal interativo: lê normalmente (ex.: vindo de um pipe).
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
}

function connect(array $config): PDO
{
    if (($config['database_driver'] ?? 'sqlite') === 'mysql') {
        $m = $config['mysql'];
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $m['host'], $m['port'], $m['database'], $m['charset'] ?? 'utf8mb4');
        $db = new PDO($dsn, $m['username'], $m['password']);
    } else {
        $db = new PDO('sqlite:' . $config['database_path']);
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}

// 1) Garante o schema (em SQLite cria tudo; em MySQL exige import prévio).
try {
    new App($config); // o construtor roda migrate() (sem seed)
    $pdo = connect($config);
    $pdo->query('SELECT 1 FROM users LIMIT 1');
} catch (Throwable $e) {
    fail(
        "Banco indisponível ou tabela 'users' inexistente.\n" .
        "  Em SQLite isso é criado automaticamente — verifique se api/storage/ é gravável.\n" .
        "  Em MySQL, importe antes database/schema.mysql.sql e database/schema.suinda.sql.\n" .
        "  Detalhe: " . $e->getMessage()
    );
}

// 2) Coleta de dados (env > prompt interativo). Senha nunca via argv.
$name = (string) (getenv('SUINDA_ADMIN_NAME') ?: '');
$email = (string) (getenv('SUINDA_ADMIN_EMAIL') ?: '');
$password = (string) (getenv('SUINDA_ADMIN_PASSWORD') ?: '');

if ($name === '') { $name = ask('Nome do administrador: '); }
if ($email === '') { $email = ask('E-mail: '); }
$email = strtolower(trim($email));

if ($password === '') {
    $p1 = askHidden('Senha (mín. 8 caracteres): ');
    $p2 = askHidden('Confirme a senha: ');
    if ($p1 !== $p2) {
        fail('As senhas não conferem.');
    }
    $password = $p1;
}

// 3) Validações.
if (trim($name) === '') {
    fail('Informe um nome.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('E-mail inválido.');
}
if (strlen($password) < 8) {
    fail('A senha deve ter ao menos 8 caracteres.');
}

// 4) Cria ou promove.
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$existingId = $stmt->fetchColumn();

if ($existingId !== false) {
    $upd = $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, active = 1 WHERE id = ?');
    $upd->execute([trim($name), $hash, 'admin', (int) $existingId]);
    fwrite(STDOUT, "✓ Administrador atualizado (#{$existingId}): {$email}\n");
} else {
    $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, ?, 1)');
    $ins->execute([trim($name), $email, $hash, 'admin']);
    $newId = (int) $pdo->lastInsertId();
    fwrite(STDOUT, "✓ Administrador criado (#{$newId}): {$email}\n");
}

// 5) Desativa usuários de demonstração (opcional).
$demoEmails = ['aluno@suinda.com', 'admin@suinda.com'];
$demoEmails = array_values(array_filter($demoEmails, fn ($e) => $e !== $email));

$placeholders = implode(',', array_fill(0, count($demoEmails), '?'));
$check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE active = 1 AND email IN ($placeholders)");
$check->execute($demoEmails);
$demoActive = (int) $check->fetchColumn();

if ($demoActive > 0) {
    $disable = getenv('SUINDA_DISABLE_DEMO');
    $shouldDisable = ($disable === '1' || strtolower((string) $disable) === 'true');

    if ($disable === false || $disable === '') {
        $isTty = function_exists('stream_isatty') ? @stream_isatty(STDIN) : false;
        if ($isTty) {
            $answer = strtolower(ask("Há {$demoActive} usuário(s) de demonstração ativo(s). Desativar agora? (s/N): "));
            $shouldDisable = in_array($answer, ['s', 'sim', 'y', 'yes'], true);
        }
    }

    if ($shouldDisable) {
        $upd = $pdo->prepare("UPDATE users SET active = 0 WHERE email IN ($placeholders)");
        $upd->execute($demoEmails);
        fwrite(STDOUT, "✓ Usuários de demonstração desativados ({$demoActive}).\n");
    } else {
        fwrite(STDOUT, "• Usuários de demonstração mantidos. Rode com SUINDA_DISABLE_DEMO=1 para desativá-los.\n");
    }
}

fwrite(STDOUT, "Pronto. Acesse /suinda/login e depois /suinda/admin.\n");
