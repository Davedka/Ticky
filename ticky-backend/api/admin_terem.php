<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();
header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs admin jogosultság!']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// A routertől kapjuk meg a számot a GET-en keresztül
$terem_szam = $_GET['szam'] ?? null;

if (!$terem_szam) {
    http_response_code(400);
    echo json_encode(['error' => 'Nem kaptam terem számot a routertől!']);
    exit;
}

if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Hibás JSON formátum a kérésben!']);
        exit;
    }

    $updateData = [];
    if (isset($input['emelet'])) {
        $updateData['emelet'] = is_numeric($input['emelet']) ? intval($input['emelet']) : $input['emelet'];
    }
    if (isset($input['nev'])) {
        $updateData['nev'] = $input['nev'];
    }

    if (!empty($updateData)) {
        // SUPABASE HÍVÁS (A legutóbbi cURL alapú configoddal)
        $res = sb_update('termek', $updateData, ['szam' => 'eq.' . $terem_szam]);

        if ($res['success']) {
            echo json_encode(['success' => true, 'message' => 'Sikeres módosítás!']);
        } else {
            http_response_code(400);
            // EZT NÉZD MEG A BÖNGÉSZŐBEN, HA HIBA VAN:
            echo json_encode([
                'error' => 'Supabase hiba történt',
                'raw_error' => $res['error'] ?? 'Ismeretlen hiba',
                'sent_data' => $updateData,
                'target_room' => $terem_szam
            ]);
        }
    } else {
        echo json_encode(['error' => 'Nem érkezett módosítandó adat (pl. emelet)!']);
    }
    exit;
}

// GET kérés kiszolgálása
if ($method === 'GET') {
    $data = sb_get('termek', ['szam' => 'eq.' . $terem_szam]);
    echo json_encode($data[0] ?? ['error' => 'A terem nem található az adatbázisban.']);
    exit;
}
