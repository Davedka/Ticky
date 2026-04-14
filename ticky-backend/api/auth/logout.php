<?php
require_once __DIR__ . '/../../utils/helpers.php';

handle_cors(['POST', 'OPTIONS']);

admin_clear_auth_cookie();
admin_clear_visibility_cookie();
ticky_clear_user_session();

json_response(['ok' => true]);
