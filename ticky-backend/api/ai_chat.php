<?php
// api/ai_chat.php – Claude AI asszisztens proxy
// POST /api/ai/chat  { "message": "...", "history": [...] }

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST kérés', 405);
}

$body    = json_decode(file_get_contents('php://input'), true);
$message = trim($body['message'] ?? '');
$history = $body['history'] ?? [];

if (empty($message)) json_error('Üres üzenet', 400);

// Max 10 üzenet history
$history = array_slice($history, -10);

// Rendszer prompt – Ticky-specifikus
$system = "Te a Ticky rendszer AI asszisztense vagy. A Ticky egy digitális terem-órarend rendszer az MSZC Gépészeti iskolában.
A rendszer PHP + Supabase alapú, Render.com-on fut. QR kódos terem azonosítókat használ.

Amit tudsz:
- A Ticky megmutatja ki tart óát melyik teremben valós időben
- URL struktúra: /termek (összes terem), /terem/{szám} (QR oldal), /tanar (tanár kereső), /kijelzo (folyosói kijelző), /qr (QR generátor), /admin (admin panel)
- Az iskola 53 termet és 31+ tanárt tartalmaz
- Órarend: hétfőtől péntekig, 1-8 óra (07:30-14:20)

Röviden és magyarul válaszolj. Ha az aktuális terem foglaltságáról kérdeznek, mondd meg hogy ez a /termek oldalon élőben látható.";

// Messages összeállítása
$messages = [];
foreach ($history as $h) {
    if (isset($h['role'], $h['content'])) {
        $messages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

// Anthropic API hívás
$apiKey = getenv('ANTHROPIC_API_KEY') ?: '';

if (empty($apiKey)) {
    json_response(['reply' => 'Az AI asszisztens jelenleg nem elérhető. (API kulcs hiányzik)']);
}

$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 400,
    'system'     => $system,
    'messages'   => $messages,
];

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ]),
        'content' => json_encode($payload),
        'timeout' => 15,
    ],
]);

$raw = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctx);

if ($raw === false) {
    json_response(['reply' => 'Kapcsolati hiba az AI-val. Próbáld újra!']);
}

$resp = json_decode($raw, true);
$reply = $resp['content'][0]['text'] ?? 'Nem sikerült választ kapni.';

json_response(['reply' => $reply]);
