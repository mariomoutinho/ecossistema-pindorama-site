<?php
// ============================================================================
// Suindá — instalador de uso único (cria o PRIMEIRO administrador no servidor).
//
// Necessário porque, num servidor novo, o banco não tem usuários ainda. Este
// instalador só funciona ENQUANTO NÃO EXISTIR NENHUM ADMIN — depois fica inerte.
// Por segurança, APAGUE este arquivo (suinda/instalar.php) após criar o admin.
//
// Acesse: https://SEU-DOMINIO/suinda/instalar.php
// ============================================================================
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

$apiDir = __DIR__ . '/api';
require_once $apiDir . '/src/App.php';

// Config (igual à da API: SQLite por padrão; respeita config.local.php).
$config = require $apiDir . '/config.php';
$localConfig = $apiDir . '/config.local.php';
if (is_file($localConfig)) {
    $o = require $localConfig;
    if (is_array($o)) { $config = array_replace_recursive($config, $o); }
}
$config['seed_on_boot'] = false;

try {
    new App($config); // roda migrate() — garante o schema
    // conexão própria (igual ao create-admin/importer)
    if (($config['database_driver'] ?? 'sqlite') === 'mysql') {
        $m = $config['mysql'];
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $m['host'], $m['port'], $m['database'], $m['charset'] ?? 'utf8mb4');
        $db = new PDO($dsn, $m['username'], $m['password']);
    } else {
        $db = new PDO('sqlite:' . $config['database_path']);
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erro ao acessar o banco. Verifique se a pasta suinda/api/storage/ é gravável. Detalhe: ' . htmlspecialchars($e->getMessage()));
}

// Guarda principal: só permite se NÃO houver admin ativo.
$adminCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn();

$done = null;
$error = null;

if ($adminCount === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $importEnem = !empty($_POST['import_enem']);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Preencha o nome, um e-mail válido e uma senha com pelo menos 8 caracteres.';
    } else {
        try {
            // cria o admin
            $exists = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $exists->execute([$email]);
            if ($exists->fetchColumn()) {
                $upd = $db->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, active = 1 WHERE email = ?');
                $upd->execute([$name, password_hash($password, PASSWORD_DEFAULT), 'admin', $email]);
            } else {
                $ins = $db->prepare('INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, ?, 1)');
                $ins->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
            }

            $imported = 0;
            if ($importEnem) {
                require_once $apiDir . '/tools/enem/EnemImporter.php';
                $imageDir = __DIR__ . '/assets/enem/questions';
                @mkdir($imageDir, 0775, true);
                $importer = new EnemImporter($db, $imageDir, '/suinda/assets/enem/questions');
                $importer->seedMatriz(require $apiDir . '/tools/enem/matriz.php');
                $courseId = $importer->ensureCourse('preparatorio-enem', 'Preparatório para o ENEM',
                    'Banco de questões do ENEM organizado por áreas, disciplinas, competências e habilidades, com repetição espaçada.');
                $batch = require $apiDir . '/tools/enem/batch-2024-d2-c5.php';
                $examMeta = $batch['exam'];
                $examId = $importer->importExam($examMeta);
                foreach ($batch['questions'] as $q) { $importer->importQuestion($examId, $courseId, $q, $examMeta); $imported++; }
                $explFile = $apiDir . '/tools/enem/explanations-2024-d2-c5.php';
                if (is_file($explFile)) { $importer->applyExplanations($examId, require $explFile); }
            }

            $done = ['email' => $email, 'imported' => $imported];
        } catch (Throwable $e) {
            $error = 'Falha ao concluir: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalar Suindá</title>
<link rel="stylesheet" href="/suinda/assets/css/suinda-site.css">
</head>
<body>
<main class="auth-wrap">
  <section class="auth-card">
    <div class="auth-card__head"><div class="auth-card__owl">🦉</div><h1>Instalar o Suindá</h1><p>Criação do primeiro administrador</p></div>

    <?php if ($done): ?>
      <div class="alert alert--info" style="background:#dcefe2;border-color:#bfe3cd;color:#2f7d54">
        ✓ Administrador criado: <strong><?= htmlspecialchars($done['email']) ?></strong>.
        <?php if ($done['imported']): ?><br>Banco de questões ENEM importado (<?= (int) $done['imported'] ?> questões).<?php endif; ?>
      </div>
      <div class="alert alert--error">
        <strong>Apague este arquivo agora</strong> (<code>suinda/instalar.php</code>) pelo Gerenciador de Arquivos da Hostinger, por segurança.
      </div>
      <p><a class="btn btn--primary btn--block btn--lg" href="/suinda/login/">Ir para o login</a></p>

    <?php elseif ($adminCount > 0): ?>
      <div class="alert alert--info">O Suindá já está configurado (já existe um administrador).</div>
      <div class="alert alert--error"><strong>Apague este arquivo</strong> (<code>suinda/instalar.php</code>) por segurança.</div>
      <p><a class="btn btn--primary btn--block" href="/suinda/login/">Ir para o login</a></p>

    <?php else: ?>
      <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="field"><label for="name">Seu nome</label><input id="name" name="name" required></div>
        <div class="field"><label for="email">E-mail (será seu login)</label><input id="email" name="email" type="email" required></div>
        <div class="field"><label for="password">Senha (mín. 8)</label><input id="password" name="password" type="password" minlength="8" required></div>
        <label style="display:flex;gap:.5rem;align-items:flex-start;margin:.4rem 0 1rem;font-size:.92rem">
          <input type="checkbox" name="import_enem" value="1" checked>
          <span>Também importar o banco de questões do ENEM (90 questões do piloto) e disponibilizar o curso.</span>
        </label>
        <button class="btn btn--primary btn--block btn--lg" type="submit">Criar administrador</button>
      </form>
      <p class="auth-meta">Como admin, depois você cria estudantes e matrículas em <code>/suinda/admin</code>.</p>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
