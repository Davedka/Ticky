<?php
// api/ai_chat.php – AI asszisztens eltávolítva
require_once __DIR__ . '/../utils/helpers.php';
handle_cors();
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['error' => 'Az AI asszisztens el lett távolítva.']);
exit;
