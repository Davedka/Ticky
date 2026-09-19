<?php
// ticky-backend/api/admin_support.php
// GET   /api/admin/support            – beérkezett üzenetek listája
// PATCH /api/admin/support/{id}       – ügykezelési státusz módosítása
//
// A támogatási üzenet magánlevél: benne a küldo neve és email címe. Ezért
// kizárólag bejelentkezett adminnak válaszolunk, és a választ nem cache-eljük.

declare(strict_types=1);

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/support_repo.php';

if (!admin_can_see_ui()) {
    json_error('Bejelentkezés szükséges', 401);
}

require_admin_api_request(['GET', 'PATCH']);
private_response_headers();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

if ($method === 'GET') {
    $statusz = ticky_support_egysoros((string) ($_GET['statusz'] ?? 'mind'));
    $limit   = (int) ($_GET['limit'] ?? 100);

    $uzenetek = ticky_support_lista($statusz, $limit);

    json_response([
        'ok'           => true,
        'uzenetek'     => $uzenetek,
        'darab'        => count($uzenetek),
        'nyitott'      => ticky_support_nyitott_darab(),
        'statuszok'    => TICKY_SUPPORT_STATUSZOK,
        'kategoriak'   => TICKY_SUPPORT_KATEGORIAK,
        // Az adminnak látnia kell, hogy a levélküldés egyáltalán be van-e
        // kapcsolva – enélkül "miért nem jön email" kérdésbol nem derül ki,
        // hogy csak a SUPPORT_MAIL_API_KEY hiányzik.
        'email_kuldes' => trim((string) getenv('SUPPORT_MAIL_API_KEY')) !== '',
    ]);
}

// ── PATCH: státusz módosítás ─────────────────────────────────────
// Friss bejelentkezést (ticky_require_fresh_admin_auth) szándékosan NEM
// követelünk: a fresh_until csak 900 mp, és ez a művelet visszafordítható
// állapotjelölés, nem az éles adatot cserélő import.

$utvonal = match_route('/api/admin/support/{id}', $uri);
if ($utvonal === false) {
    json_error('Hiányzó azonosító', 400);
}

$id = (int) $utvonal['id'];
if ($id <= 0) {
    json_error('Érvénytelen azonosító', 400);
}

$adat = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($adat)) {
    json_error('Hibás kérés formátum', 400);
}

$statusz = ticky_support_egysoros((string) ($adat['statusz'] ?? ''));
if (!in_array($statusz, TICKY_SUPPORT_STATUSZOK, true)) {
    json_error('Érvénytelen státusz. Lehetséges: ' . implode(', ', TICKY_SUPPORT_STATUSZOK), 400);
}

if (!ticky_support_statusz_allitas($id, $statusz)) {
    json_error('A státusz módosítása nem sikerült', 502);
}

json_response(['ok' => true, 'id' => $id, 'statusz' => $statusz]);
