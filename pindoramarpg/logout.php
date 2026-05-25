<?php
require_once __DIR__ . '/includes/auth.php';

encerrarSessao();

header('Location: index.php');
exit;
