<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();
header('Content-Type: application/json');

// 1. Jogosultság ellenőrzése
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs admin jogosultságod a művelethez.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Terem szám kinyerése (pl. /api/admin/terem/102 -> 102)
$parts = explode('/', trim($uri, '/'));
$terem_szam = end($parts);

// Csak POST vagy PATCH metódust fogadunk el a módosításhoz
if (($method === 'POST' || $method === 'PATCH' || $method === 'PUT') && $terem_szam) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $dataToUpdate = [];
    
    // Emelet frissítése
    if (isset($input['emelet'])) {
        $dataToUpdate['emelet'] = (int)$input['emelet'];
    }
    
    // Terem név frissítése (opcionális)
    if (isset($input['nev'])) {
        $dataToUpdate['nev'] = $input['nev'];
    }

    if (!empty($dataToUpdate)) {
        // A szűrés formátuma a Supabase REST API-hoz: eq.ERTEK
        $filters = ['szam' => 'eq.' . $terem_szam];
        
        // Frissítés indítása a Service Key-el (megkerüli az RLS korlátokat)
        $success = sb_update('termek', $dataToUpdate, $filters, 'service');

        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => "A(z) $terem_szam számú terem adatai sikeresen frissítve lettek."
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Szerver hiba: Nem sikerült kommunikálni az adatbázissal.']);
        }
    } else {
        echo json_encode(['error' => 'Nem érkezett módosítandó adat a kérésben.']);
    }
    exit;
}

// Ha csak sima lekérdezés történik (GET)
if ($method === 'GET' && $terem_szam) {
    $data = sb_get('termek', ['szam' => 'eq.' . $terem_szam]);
    echo json_encode($data[0] ?? ['error' => 'Terem nem található']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Nem támogatott HTTP metódus.']);
