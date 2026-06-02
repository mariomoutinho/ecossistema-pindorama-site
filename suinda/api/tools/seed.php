<?php

declare(strict_types=1);

// ============================================================================
// Popula dados de demonstracao (area, trilha, curso, modulo, baralho vinculado
// e matricula do aluno de teste). Idempotente — pode rodar mais de uma vez.
//
//   php suinda/api/tools/seed.php
//
// Respeita config.local.php (MySQL) se existir; caso contrario usa o SQLite
// padrao em api/storage/.
// ============================================================================

require_once __DIR__ . '/../src/App.php';

$config = require __DIR__ . '/../config.php';

$localConfig = __DIR__ . '/../config.local.php';
if (is_file($localConfig)) {
    $override = require $localConfig;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

$config['seed_on_boot'] = true;

new App($config); // o construtor executa migrate() + seed()

fwrite(STDOUT, "Seed concluido.\n");
fwrite(STDOUT, "  Aluno: aluno@suinda.com / 123456 (matriculado em 'Biologia Basica')\n");
fwrite(STDOUT, "  Admin: admin@suinda.com / admin123\n");
