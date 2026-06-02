<?php

declare(strict_types=1);

// Router para o servidor PHP embutido (desenvolvimento/testes):
//
//   php -S 127.0.0.1:8013 -t suinda/api suinda/api/router.php
//
// Como SCRIPT_NAME aponta para router.php (raiz), o App nao remove prefixo e as
// rotas batem em /auth/login, /decks, etc. Em producao quem responde e index.php.

ini_set('display_errors', '1');
error_reporting(E_ALL);

/** @var App $app */
$app = require __DIR__ . '/bootstrap.php';
$app->handle();
