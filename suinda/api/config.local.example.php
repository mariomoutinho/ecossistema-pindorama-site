<?php

// ============================================================================
// MODELO de configuracao local/servidor.
// Copie para config.local.php (que e gitignored e NAO vai para o deploy via Git)
// e ajuste os valores para usar MySQL/MariaDB em vez do SQLite padrao.
//
// O array retornado aqui sobrescreve (array_replace_recursive) o config.php.
// ============================================================================

return [
    'database_driver' => 'mysql',
    'mysql' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'database' => 'uXXXXXXXX_suinda',     // crie este banco no hPanel/phpMyAdmin
        'username' => 'uXXXXXXXX_suinda',
        'password' => 'DEFINA-UMA-SENHA-FORTE',
        'charset'  => 'utf8mb4',
    ],

    // Deixe vazio para auto-detectar (/suinda/api). Ajuste se mudar o caminho.
    'base_path' => '',

    // Deixe false em producao. Use o script tools/seed.php para popular dados.
    'seed_on_boot' => false,
];
