<?php

declare(strict_types=1);

// Entrada alternativa para rodar a API com docroot em api/public:
//   php -S 127.0.0.1:8013 -t suinda/api/public
// Em producao o front controller e ../index.php (ver .htaccess).

ini_set('display_errors', '0');
error_reporting(E_ALL);

/** @var App $app */
$app = require __DIR__ . '/../bootstrap.php';
$app->handle();
