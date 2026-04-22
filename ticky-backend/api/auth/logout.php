<?php
require_once __DIR__ . '/../../utils/helpers.php';

handle_cors(['POST', 'OPTIONS'], ['Content-Type', 'X-CSRF-Token']);
send_security_headers(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST keres', 405);
}

ticky_require_same_origin_request();
ticky_require_csrf_token();
ticky_destroy_session();

json_response(['ok' => true]);
