<?php

declare(strict_types=1);

// Bootstrap unico da API: carrega a classe, monta a config (com override local
// opcional e gitignored) e devolve a instancia pronta. Usado por index.php
// (Apache), router.php (servidor PHP embutido) e public/index.php.

require_once __DIR__ . '/src/App.php';

$config = require __DIR__ . '/config.php';

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $override = require $localConfig;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

return new App($config);
