<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

// Megsemmisítjük a sessiont (file + cookie + legacy cookie-k is)
ticky_destroy_session();

header('Location: /login');
exit;
