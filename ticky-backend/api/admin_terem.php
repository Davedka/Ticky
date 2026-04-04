<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

// CORS és JSON fejléc
handle_cors();
header('Content-Type: application/json');

// 1. Jogosultság ellenőrzése (Ugyanaz, ami a tanárnál működik)
// Feltételezve, hogy a helpers.php-ban van egy is_admin() vagy hasonló függvényed
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs jogosultságod a termek szerkesztéséhez.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Útvonalból kinyerjük a terem számát (pl. /api/admin/terem/102)
$path_parts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$terem_szam = end($path_parts);

if ($method === 'POST' || $method === 'PUT') {
    // Terem adatainak frissítése a Supabase-ben
    if (!$terem_szam) {
        http_response_code(400);
        echo json_encode(['error' => 'Hiányzó terem azonosító.']);
        exit;
    }

    // Itt hívjuk meg a Supabase-t a frissítéshez
    // Példa: update_terem_adatok($terem_szam, $input);
    
    echo json_encode(['success' => true, 'message' => "A(z) $terem_szam terem frissítve!"]);
    exit;
}

if ($method === 'GET') {
    // Egy konkrét terem adatainak lekérése szerkesztéshez
    echo json_encode(['terem' => $terem_szam, 'status' => 'adatok betöltve']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Nem támogatott metódus.']);
