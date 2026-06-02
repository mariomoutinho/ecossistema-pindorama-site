<?php

// ============================================================================
// Configuracao da API educacional do Suinda.
//
// Por padrao usa SQLite (zero configuracao) gravando em api/storage/ — pasta
// protegida por .htaccess e fora do versionamento. Para MySQL/MariaDB
// (Hostinger, XAMPP), defina as variaveis de ambiente SUINDA_DB_* OU crie um
// arquivo api/config.local.php (gitignored) retornando um array que sobrescreve
// estes valores. Veja config.local.example.php.
// ============================================================================

return [
    'database_driver' => getenv('SUINDA_DB_DRIVER') ?: 'sqlite',
    'database_path'   => getenv('SUINDA_SQLITE_PATH') ?: __DIR__ . '/storage/suinda.sqlite',
    'storage_path'    => getenv('SUINDA_STORAGE_PATH') ?: __DIR__ . '/storage',

    // Prefixo de URL onde a API esta montada. Vazio = auto-detecta pelo
    // diretorio do script (ex.: /suinda/api no Apache). Sobrescreva com
    // SUINDA_BASE_PATH se necessario.
    'base_path' => getenv('SUINDA_BASE_PATH') ?: '',

    'mysql' => [
        'host'     => getenv('SUINDA_DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('SUINDA_DB_PORT') ?: '3306',
        'database' => getenv('SUINDA_DB_NAME') ?: 'suinda',
        'username' => getenv('SUINDA_DB_USER') ?: 'root',
        'password' => getenv('SUINDA_DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ],

    'seed_on_boot' => filter_var(getenv('SUINDA_SEED_ON_BOOT'), FILTER_VALIDATE_BOOLEAN),
];
