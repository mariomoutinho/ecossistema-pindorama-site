<?php

declare(strict_types=1);

// ============================================================================
// Esteira reutilizável de importação de provas ENEM para o Suindá (CLI).
//
// Uso:
//   php suinda/api/tools/import-enem.php \
//       --ano=2024 --dia=2 --caderno=5 --cor=amarelo \
//       --curso=preparatorio-enem \
//       --matriz=tools/enem/matriz.php \
//       --batch=tools/enem/batch-2024-d2-c5.php \
//       [--prova=/caminho/prova.pdf] [--gabarito=/caminho/gabarito.pdf] \
//       [--render] [--crops=arquivo.json] [--enroll-demo]
//
// - --batch: arquivo .php (return array) ou .json com {exam, questions[]}.
//   É a forma normalizada da prova. Quando houver PDF + ferramentas, um passo
//   de extração (futuro) pode gerar esse batch automaticamente; aqui ele já
//   vem pronto (lote-piloto transcrito).
// - --render: só tem efeito se houver pdftoppm + (cwebp|convert) e --prova.
//   Sem isso, as imagens ficam "pendentes" (texto provisório no card).
// - Idempotente: rodar de novo não duplica questões/cards.
// ============================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$apiDir = dirname(__DIR__);                 // .../suinda/api
$suindaDir = dirname($apiDir);              // .../suinda
require_once $apiDir . '/src/App.php';
require_once __DIR__ . '/enem/EnemImporter.php';

// ---- args ----
$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}
$rel = function (string $p) use ($apiDir): string {
    return (str_starts_with($p, '/')) ? $p : $apiDir . '/' . $p;
};

$ano = (int) ($opts['ano'] ?? 2024);
$dia = (int) ($opts['dia'] ?? 2);
$courseSlug = (string) ($opts['curso'] ?? 'preparatorio-enem');
$matrizFile = $rel((string) ($opts['matriz'] ?? 'tools/enem/matriz.php'));
$batchFile = $rel((string) ($opts['batch'] ?? 'tools/enem/batch-2024-d2-c5.php'));

function loadData(string $file): array
{
    if (!is_file($file)) {
        fwrite(STDERR, "✗ Arquivo não encontrado: $file\n");
        exit(1);
    }
    if (str_ends_with($file, '.json')) {
        $d = json_decode((string) file_get_contents($file), true);
        return is_array($d) ? $d : [];
    }
    return require $file;
}

// ---- config + DB (mesma do app; migrate cria as tabelas ENEM) ----
$config = require $apiDir . '/config.php';
$local = $apiDir . '/config.local.php';
if (is_file($local)) {
    $o = require $local;
    if (is_array($o)) { $config = array_replace_recursive($config, $o); }
}
$config['seed_on_boot'] = false;
new App($config); // roda migrate() — garante o schema

function connectPdo(array $config): PDO
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
    if (($config['database_driver'] ?? 'sqlite') !== 'mysql') {
        $db->exec('PRAGMA foreign_keys = ON');
    }
    return $db;
}
$db = connectPdo($config);

// ---- diretórios de imagens (públicos) e relatórios (storage) ----
$imageDir = $suindaDir . '/assets/enem/questions';
$imageUrlBase = '/suinda/assets/enem/questions';
@mkdir($imageDir, 0775, true);
$reportDir = ($config['storage_path'] ?? ($apiDir . '/storage')) . '/enem/reports';
@mkdir($reportDir, 0775, true);

// ---- detecta ferramentas de imagem ----
function hasBin(string $bin): bool
{
    $which = @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null');
    return is_string($which) && trim($which) !== '';
}
$tooling = [
    'pdftoppm' => hasBin('pdftoppm'),
    'cwebp' => hasBin('cwebp'),
    'convert' => hasBin('convert'),
];
$canRender = !empty($opts['render']) && $tooling['pdftoppm'] && ($tooling['cwebp'] || $tooling['convert']) && !empty($opts['prova']) && is_file($rel((string) $opts['prova']));

// ============================================================================
$importer = new EnemImporter($db, $imageDir, $imageUrlBase);

echo "→ Semeando Matriz de Referência…\n";
$matriz = loadData($matrizFile);
$importer->seedMatriz($matriz);

echo "→ Garantindo curso ($courseSlug) + módulos + baralhos por disciplina…\n";
$courseId = $importer->ensureCourse(
    $courseSlug,
    'Preparatório para o ENEM',
    'Banco de questões do ENEM organizado por áreas, disciplinas, conteúdos, competências e habilidades da Matriz de Referência, integrado à repetição espaçada do Suindá.'
);

$batch = loadData($batchFile);
$examMeta = $batch['exam'] ?? [];
$examMeta['year'] = $examMeta['year'] ?? $ano;
$examMeta['day'] = $examMeta['day'] ?? $dia;
echo "→ Importando prova: " . ($examMeta['name'] ?? 'desconhecida') . "\n";
$examId = $importer->importExam($examMeta);

$rows = [];
$counts = ['total' => 0, 'ativa' => 0, 'anulada' => 0, 'review' => 0, 'images_pending' => 0, 'images_present' => 0];
$byDiscipline = [];
foreach (($batch['questions'] ?? []) as $q) {
    $r = $importer->importQuestion($examId, $courseId, $q, $examMeta);
    $rows[] = $r;
    $counts['total']++;
    $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
    if ($r['review_needed']) { $counts['review']++; }
    $counts[$r['image']['status'] === 'present' ? 'images_present' : 'images_pending']++;
    $byDiscipline[$r['discipline']] = ($byDiscipline[$r['discipline']] ?? 0) + 1;
}

// Comentários pedagógicos revisados (opcional; arquivo irmão do batch por padrão:
// batch-XXXX.php -> explanations-XXXX.php).
$explFile = !empty($opts['explanations']) ? $rel((string) $opts['explanations'])
    : str_replace('batch-', 'explanations-', $batchFile);
$counts['explained'] = is_file($explFile) ? $importer->applyExplanations($examId, require $explFile) : 0;
if ($counts['explained'] > 0) {
    echo "→ Comentários revisados aplicados: {$counts['explained']}\n";
}

if (!$canRender) {
    $missing = [];
    foreach (['pdftoppm', 'cwebp'] as $t) { if (!$tooling[$t]) { $missing[] = $t; } }
    if (empty($opts['render'])) { $missing[] = 'flag --render'; }
    if (empty($opts['prova'])) { $missing[] = 'arquivo --prova'; }
    echo "ⓘ Recorte de imagens NÃO executado (faltam: " . implode(', ', $missing) . "). "
        . "Questões usam transcrição como visual provisório; rode novamente com as ferramentas para preencher os recortes.\n";
}

// ---- enrolla o aluno de demonstração para teste de ponta a ponta ----
if (!empty($opts['enroll-demo'])) {
    $uid = $db->prepare('SELECT id FROM users WHERE email = ?');
    $uid->execute(['aluno@suinda.com']);
    $studentId = $uid->fetchColumn();
    if ($studentId) {
        $ex = $db->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
        $ex->execute([$studentId, $courseId]);
        if (!$ex->fetchColumn()) {
            $db->prepare('INSERT INTO enrollments (user_id, course_id, status) VALUES (?, ?, ?)')
                ->execute([$studentId, $courseId, 'active']);
        }
        echo "→ Aluno de demonstração matriculado no curso (para teste).\n";
    }
}

// ---- relatórios (JSON + CSV + lista de revisão) ----
$stamp = (string) ($opts['stamp'] ?? 'last'); // sem Date.now no ambiente; carimbo externo opcional
$base = $reportDir . '/' . $examMeta['slug'] . '-' . $stamp;
$manifest = [
    'exam' => $examMeta, 'courseId' => $courseId, 'examId' => $examId,
    'tooling' => $tooling, 'imagesRendered' => $canRender,
    'counts' => $counts, 'byDiscipline' => $byDiscipline,
    'imageUrlBase' => $imageUrlBase, 'questions' => $rows,
];
file_put_contents($base . '.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$fh = fopen($base . '.csv', 'w');
fputcsv($fh, ['numero', 'disciplina', 'conteudo', 'competencia', 'habilidade', 'correta', 'status', 'confianca', 'revisar', 'pdf_pagina', 'imagem']);
foreach ($rows as $r) {
    fputcsv($fh, [$r['number'], $r['discipline'], $r['content'], $r['competency'], $r['skill'],
        $r['correct'], $r['status'], $r['confidence'], $r['review_needed'] ? 'sim' : 'nao', $r['pdf_page'], $r['image']['status']]);
}
fclose($fh);

$reviewList = array_values(array_filter($rows, fn ($r) => $r['review_needed'] || $r['status'] === 'anulada'));
file_put_contents($base . '-revisao.json', json_encode($reviewList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// ---- resumo ----
echo "\n================ RESUMO DA IMPORTAÇÃO ================\n";
echo "Curso #$courseId · Prova #$examId · " . ($examMeta['name'] ?? '') . "\n";
echo "Questões: {$counts['total']}  |  ativas: " . ($counts['ativa'] ?? 0) . "  |  anuladas: " . ($counts['anulada'] ?? 0) . "  |  p/ revisão: {$counts['review']}\n";
echo "Imagens: presentes " . $counts['images_present'] . " · pendentes " . $counts['images_pending'] . "\n";
echo "Por disciplina: "; foreach ($byDiscipline as $d => $n) { echo "$d=$n "; } echo "\n";
echo "Relatórios: $base.json · $base.csv · $base-revisao.json\n";
echo "=====================================================\n";
