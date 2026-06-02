<?php

declare(strict_types=1);

// Front controller da API em producao (Apache/Hostinger). O .htaccess deste
// diretorio encaminha todas as rotas para ca.

ini_set('display_errors', '0');
error_reporting(E_ALL);

/** @var App $app */
$app = require __DIR__ . '/bootstrap.php';
$app->handle();
