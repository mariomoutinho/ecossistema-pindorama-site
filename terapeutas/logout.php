<?php
// ================================
// Logout — encerra a sessão e volta para o login.
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_logout();
header('Location: login.php');
exit;
