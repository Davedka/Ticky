<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();
header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', rtrim($uri, '/'));
$terem_szam = end($parts);

if (($method === 'POST' || $method === 'PATCH' || $method === 'PUT') && $terem_szam) {
    $input = json_decode(file_get_contents('php://input'), true);
    $updateData = [];

    // Kényszerítsük az emeletet számmá, ha létezik
    if (isset($input['emelet'])) {
        $updateData['emelet'] = intval($input['emelet']);
    }

    if (!empty($updateData)) {
        // A szűrésnél a terem számát stringként küldjük (biztos, ami biztos)
        $res = sb_update('termek', $updateData, ['szam' => 'eq.' . (string)$terem_szam]);

        if ($res['success']) {
            echo json_encode(['success' => true, 'message' => 'Frissítve!']);
        } else {
            http_response_code(400);
            // Itt most már látni fogod a PONTOS hibaüzenetet a válaszban!
            echo json_encode([
                'error' => 'API hiba történt',
                'debug_info' => $res['error'],
                'details' => $res['details'] ?? 'Nincs részlet'
            ]);
        }
    }
    exit;
}
