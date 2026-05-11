<?php
require_once __DIR__ . '/../utils/auth.php';
ticky_logout();
header('Location: /login');
exit;
