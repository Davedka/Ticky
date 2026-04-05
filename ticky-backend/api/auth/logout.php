<?php
// api/auth/logout.php
// POST /api/auth/logout

require_once __DIR__ . '/../../utils/helpers.php';
handle_cors(['POST', 'OPTIONS']);

$is_https = ticky_is_https();
setcookie('ticky_user_session', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => $is_https,
    'httponly' => true,
    'samesite' => 'Strict',
]);

json_response(['ok' => true]);
