<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
handle_cors();
header('Content-Type: application/json');

if (!is_admin()) { http_response_code(403); echo json_encode(['error' => 'No admin access']); exit; }

$szam = $_GET['szam'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'PATCH' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    if (isset($input['emelet'])) $data['emelet'] = (int)$input['emelet'];
    if (isset($input['nev'])) $data['nev'] = $input['nev'];

    $res = sb_update('termek', $data, ['szam' => 'eq.' . $szam]);

    if ($res['success']) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'API hiba', 'details' => $res['error']]);
    }
    exit;
}
