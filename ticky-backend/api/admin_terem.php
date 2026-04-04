<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();
header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs jogosultságod.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Terem kinyerése precízebben: utolsó számjegyek az URL végén
$pathParts = explode('/', rtrim($uri, '/'));
$terem_szam = end($pathParts);

if (($method === 'POST' || $method === 'PATCH' || $method === 'PUT') && $terem_szam) {
    $input = json_decode(file_get_contents('php://input'), true);
    $dataToUpdate = [];

    if (isset($input['emelet'])) {
        // Próbáljuk meg számmá alakítani, hátha az adatbázis azt várja
        $dataToUpdate['emelet'] = is_numeric($input['emelet']) ? (int)$input['emelet'] : $input['emelet'];
    }
    
    if (isset($input['nev'])) {
        $dataToUpdate['nev'] = $input['nev'];
    }

    if (!empty($dataToUpdate)) {
        // A szűrő formátuma: "szam=eq.102"
        $res = sb_update('termek', $dataToUpdate, ['szam' => 'eq.' . $terem_szam], 'service');

        if ($res['success']) {
            echo json_encode(['success' => true, 'message' => 'Sikeres mentés!']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Supabase hiba', 'details' => $res['error']]);
        }
    } else {
        echo json_encode(['error' => 'Nem érkezett adat.']);
    }
    exit;
}

if ($method === 'GET' && $terem_szam) {
    $data = sb_get('termek', ['szam' => 'eq.' . $terem_szam]);
    echo json_encode($data[0] ?? ['error' => 'Nem található']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Hibás metódus']);
