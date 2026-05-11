<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/_nav.php';

if (function_exists('ticky_logout')) {
    ticky_logout();
}
header('Location: /login');
exit;
