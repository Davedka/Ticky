<?php
// api/ai_chat.php – Unified Ticky AI endpoint
// POST /api/ai/chat
// Body: { message, history?, context?, contextData? }
// Returns: { reply, cards?, actions?, suggestions? }
//
// Logika:
//   1. Rule-based handler (ingyenes, azonnali)
//   2. Ha nem illeszkedik → Claude Haiku fallback (max 180 token!)
//   Így API hívás csak akkor megy ki, ha valóban szükséges.

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Csak POST kérés', 405);
}

$body        = json_decode(file_get_contents('php://input') ?: '', true);
$message     = trim((string) ($body['message']     ?? ''));
$history     = array_slice($body['history']     ?? [], -8);
$context     = (string) ($body['context']     ?? 'general');
$ctx_data    = is_array($body['contextData']  ?? null) ? $body['contextData'] : [];

if ($message === '') json_error('Üres üzenet', 400);

// ── Helpers ──────────────────────────────────────────
function ai_n(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u']);
}

function ai_has(string $text, array $needles): bool {
    foreach ($needles as $n) {
        if ($n !== '' && str_contains($text, (string)$n)) return true;
    }
    return false;
}

function ai_respond(
    string $reply,
    array  $cards       = [],
    array  $actions     = [],
    array  $suggestions = []
): never {
    json_response(compact('reply', 'cards', 'actions', 'suggestions'));
}

function ai_extract_room(string $text): ?string {
    if (preg_match('/(?<!\d)(\d{1,4}[A-Za-z]?)(?!\d)/', $text, $m)) {
        return strtoupper($m[1]);
    }
    return null;
}

function ai_context_room(array $ctx_data): ?string {
    $r = trim((string) ($ctx_data['room'] ?? ''));
    return $r !== '' ? strtoupper($r) : null;
}

// ── DB helpers ───────────────────────────────────────
function ai_get_room_status(string $szam): ?array {
    $nap = mai_nap();
    $ido = aktualis_ido();

    $termek = sb_get('termek', [
        'terem_szam' => 'eq.' . strtoupper($szam),
        'select'     => 'id,terem_szam,emelet',
    ]);
    if (empty($termek)) return null;
    $t = $termek[0];

    if ($nap === 0) {
        return ['terem' => $t['terem_szam'], 'allapot' => 'szabad', 'aktualis' => null, 'kovetkezo' => null];
    }

    $orak = sb_get('orarendek', [
        'terem_id'  => 'eq.' . $t['id'],
        'het_napja' => 'eq.' . $nap,
        'aktiv'     => 'eq.true',
        'select'    => 'ora_sorszam,osztaly,tantargy,kezdes,vegzes,tanar_id',
        'order'     => 'kezdes.asc',
    ]);

    $tanar_map = [];
    if (!empty($orak)) {
        $ids = array_unique(array_column($orak, 'tanar_id'));
        foreach (sb_get('tanarok', ['id' => 'in.(' . implode(',', $ids) . ')', 'select' => 'id,rovid_nev,nev']) as $tr) {
            $tanar_map[$tr['id']] = $tr;
        }
    }
    foreach ($orak as &$o) {
        $tr = $tanar_map[$o['tanar_id']] ?? null;
        $o['tanar']     = $tr['rovid_nev'] ?? '?';
        $o['tanar_nev'] = $tr['nev'] ?? null;
        unset($o['tanar_id']);
    }
    unset($o);

    $current = $next = null;
    foreach ($orak as $o) {
        $k = substr($o['kezdes'], 0, 5);
        $v = substr($o['vegzes'], 0, 5);
        if ($ido >= $k && $ido <= $v) $current = $o;
        elseif ($ido < $k && $next === null) $next = $o;
    }

    return [
        'terem'     => $t['terem_szam'],
        'allapot'   => $current ? 'foglalt' : 'szabad',
        'aktualis'  => $current,
        'kovetkezo' => $next,
    ];
}

function ai_get_rooms_snapshot(): array {
    $nap = mai_nap();
    $ido = aktualis_ido();

    $rooms = sb_get('termek', ['select' => 'id,terem_szam,emelet', 'order' => 'terem_szam.asc']);
    if (empty($rooms)) return [];
    if ($nap === 0) {
        return array_map(fn($r) => ['terem_szam' => $r['terem_szam'], 'allapot' => 'szabad', 'aktualis' => null], $rooms);
    }

    $ids = array_column($rooms, 'id');
    $aktiv = sb_get('orarendek', [
        'terem_id'  => 'in.(' . implode(',', $ids) . ')',
        'het_napja' => 'eq.' . $nap,
        'aktiv'     => 'eq.true',
        'kezdes'    => 'lte.' . $ido . ':00',
        'vegzes'    => 'gte.' . $ido . ':00',
        'select'    => 'terem_id,osztaly,tantargy,kezdes,vegzes,tanar_id',
    ]);
    $tanar_ids = array_unique(array_column($aktiv, 'tanar_id'));
    $tanar_map = [];
    if (!empty($tanar_ids)) {
        foreach (sb_get('tanarok', ['id' => 'in.(' . implode(',', $tanar_ids) . ')', 'select' => 'id,rovid_nev']) as $tr) {
            $tanar_map[$tr['id']] = $tr['rovid_nev'];
        }
    }
    $busy = [];
    foreach ($aktiv as $o) {
        $busy[$o['terem_id']] = [
            'tanar'    => $tanar_map[$o['tanar_id']] ?? '?',
            'osztaly'  => $o['osztaly'],
            'tantargy' => $o['tantargy'],
            'kezdes'   => substr($o['kezdes'], 0, 5),
            'vegzes'   => substr($o['vegzes'], 0, 5),
        ];
    }

    return array_map(fn($r) => [
        'terem_szam' => $r['terem_szam'],
        'allapot'    => isset($busy[$r['id']]) ? 'foglalt' : 'szabad',
        'aktualis'   => $busy[$r['id']] ?? null,
    ], $rooms);
}

function ai_find_teacher(string $message): ?array {
    $normalized = ai_n($message);
    $tanarok = sb_get('tanarok', ['select' => 'id,rovid_nev,nev', 'order' => 'rovid_nev.asc']);

    // Kód egyezés (2-6 nagybetű)
    preg_match_all('/\b([A-ZÁÉÍÓÖŐÚÜŰ]{2,6})\b/u', strtoupper($message), $m);
    foreach (($m[1] ?? []) as $cand) {
        foreach ($tanarok as $t) {
            if (strtoupper($t['rovid_nev']) === $cand) return $t;
        }
    }
    // Teljes név egyezés
    foreach ($tanarok as $t) {
        if ($t['nev'] && str_contains($normalized, ai_n($t['nev']))) return $t;
    }
    return null;
}

function ai_get_teacher_status(array $teacher): array {
    $nap = mai_nap();
    $ido = aktualis_ido();

    if ($nap === 0) return ['teacher' => $teacher, 'current' => null, 'next' => null, 'lessons' => []];

    $raw = sb_get('orarendek', [
        'tanar_id'  => 'eq.' . $teacher['id'],
        'het_napja' => 'eq.' . $nap,
        'aktiv'     => 'eq.true',
        'select'    => 'ora_sorszam,kezdes,vegzes,osztaly,tantargy,termek(terem_szam)',
        'order'     => 'kezdes.asc',
    ]);

    // Csoportbontás összevonása
    $grouped = [];
    foreach ($raw as $o) {
        $k = substr($o['kezdes'], 0, 5) . '_' . substr($o['vegzes'], 0, 5);
        if (!isset($grouped[$k])) {
            $grouped[$k] = ['kezdes' => substr($o['kezdes'],0,5), 'vegzes' => substr($o['vegzes'],0,5), 'ora_sorszam' => $o['ora_sorszam'], 'tantargy' => $o['tantargy'], 'termek' => [], 'osztalyok' => []];
        }
        $tr = $o['termek']['terem_szam'] ?? '?';
        $os = $o['osztaly'] ?? '?';
        if (!in_array($tr, $grouped[$k]['termek'], true)) $grouped[$k]['termek'][] = $tr;
        if (!in_array($os, $grouped[$k]['osztalyok'], true)) $grouped[$k]['osztalyok'][] = $os;
    }

    $lessons = [];
    foreach ($grouped as $g) {
        $lessons[] = [
            'kezdes'      => $g['kezdes'],
            'vegzes'      => $g['vegzes'],
            'ora_sorszam' => $g['ora_sorszam'],
            'tantargy'    => $g['tantargy'],
            'terem'       => implode(' / ', $g['termek']),
            'osztaly'     => implode(', ', $g['osztalyok']),
        ];
    }
    usort($lessons, fn($a,$b) => strcmp($a['kezdes'], $b['kezdes']));

    $current = $next = null;
    foreach ($lessons as $l) {
        if ($ido >= $l['kezdes'] && $ido <= $l['vegzes']) $current = $l;
        elseif ($ido < $l['kezdes'] && $next === null) $next = $l;
    }

    return ['teacher' => $teacher, 'current' => $current, 'next' => $next, 'lessons' => $lessons];
}

// ── Claude Haiku fallback (csak ha rules nem fogják el) ──
function ai_claude_fallback(string $message, array $history): string {
    $apiKey = getenv('ANTHROPIC_API_KEY') ?: '';
    if (!$apiKey) return 'Az AI asszisztens most nem elérhető.';

    $nap_nevek = [0=>'Hétvége',1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek'];
    $nap  = $nap_nevek[mai_nap()] ?? '';
    $ido  = aktualis_ido();

    $system = "Te a Ticky iskolai teremkezelő rendszer asszisztense vagy (MSZC Gépészeti iskola). Most $nap van, $ido. Röviden, magyarul válaszolj, max 2-3 mondatban. Oldalak: /termek (összes terem), /tanar (tanár kereső), /kijelzo (folyosói kijelző), /qr (QR generátor).";

    $messages = [];
    foreach ($history as $h) {
        if (isset($h['role'], $h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => (string)$h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ]),
        'content' => json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 180,   // alacsony – free tier kímélése
            'system'     => $system,
            'messages'   => $messages,
        ]),
        'timeout' => 12,
    ]]);

    $raw = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctx);
    if (!$raw) return 'Kapcsolati hiba. Próbáld újra!';
    $resp = json_decode($raw, true);
    return $resp['content'][0]['text'] ?? 'Nem sikerült választ kapni.';
}

// ════════════════════════════════════════════════════
// RULE-BASED HANDLER (ingyenes, gyors)
// ════════════════════════════════════════════════════

$norm = ai_n($message);

// ── Üdvözlés / Help ──────────────────────────────────
if (ai_has($norm, ['szia','hello','hey','segit','mit tudsz','help'])) {
    ai_respond(
        'Szia! 👋 Kérdezhetsz szabad/foglalt termekről, egy adott terem állapotáról, vagy hogy éppen hol van egy tanár.',
        [],
        [
            ['label' => 'Összes terem',  'href' => '/termek'],
            ['label' => 'Tanár kereső', 'href' => '/tanar'],
        ],
        ['Melyik termek szabadok most?', 'Foglalt termek listája', 'Tanár kereső megnyitása']
    );
}

// ── Navigáció ────────────────────────────────────────
if (ai_has($norm, ['tanar kereso','tanar oldal']) || (ai_has($norm, ['tanar']) && ai_has($norm, ['nyisd','megnyit','oldal']))) {
    ai_respond('Megnyitom a Tanár kereső oldalt.', [], [['label' => 'Tanár kereső', 'href' => '/tanar']]);
}
if (ai_has($norm, ['qr'])) {
    ai_respond('Megnyitom a QR generátort.', [], [['label' => 'QR Generátor', 'href' => '/qr']]);
}
if (ai_has($norm, ['kijelzo'])) {
    ai_respond('A folyosói kijelző nézethez itt tudsz lépni.', [], [['label' => 'Kijelző', 'href' => '/kijelzo']]);
}
if (ai_has($norm, ['admin'])) {
    ai_respond('Az admin panel elérési pontját nem adom ki publikusan.', []);
}
if (ai_has($norm, ['termek lista','osszes terem','termek oldal','megnyit']) && ai_has($norm, ['terem'])) {
    ai_respond('Megnyitom a termek oldalt.', [], [['label' => 'Összes terem', 'href' => '/termek']]);
}

// ── Konkrét terem lekérdezés ─────────────────────────
$room_code = ai_extract_room($message);
// Ha terem-context van és nincs explicit room a szövegben
if ($room_code === null && in_array($context, ['terem','napirend'], true)) {
    $room_code = ai_context_room($ctx_data);
}

if ($room_code !== null) {
    $rs = ai_get_room_status($room_code);
    if ($rs === null) {
        ai_respond("Nem találom a $room_code termet.", [], [['label' => 'Összes terem', 'href' => '/termek']]);
    }

    $actions = [
        ['label' => 'Terem oldal',    'href' => '/terem/' . rawurlencode($rs['terem'])],
        ['label' => 'Napirend nézet', 'href' => '/terem/' . rawurlencode($rs['terem']) . '/nap'],
    ];

    if ($rs['allapot'] === 'foglalt' && $rs['aktualis']) {
        $a   = $rs['aktualis'];
        $k   = substr($a['kezdes'], 0, 5);
        $v   = substr($a['vegzes'], 0, 5);
        $min = max(0, (int)(((strtotime($v) - strtotime(aktualis_ido())) / 60)));
        $cards = [[
            'eyebrow' => 'Most foglalt',
            'title'   => $a['tanar_nev'] ?: $a['tanar'],
            'meta'    => ($a['osztaly'] ?? '') . ' · ' . ($a['tantargy'] ?? ''),
            'detail'  => "$k – $v · még $min perc",
        ]];
        if ($rs['kovetkezo']) {
            $nxt = $rs['kovetkezo'];
            $cards[] = [
                'eyebrow' => 'Következő',
                'title'   => $nxt['tanar'] ?? '?',
                'meta'    => ($nxt['osztaly'] ?? '') . ' · ' . ($nxt['tantargy'] ?? ''),
                'detail'  => substr($nxt['kezdes'],0,5) . ' – ' . substr($nxt['vegzes'],0,5),
            ];
        }
        ai_respond("A {$rs['terem']}. terem most **foglalt**.", $cards, $actions);
    } else {
        $cards = [];
        if ($rs['kovetkezo']) {
            $nxt = $rs['kovetkezo'];
            $cards[] = [
                'eyebrow' => 'Következő óra',
                'title'   => substr($nxt['kezdes'],0,5) . ' – ' . substr($nxt['vegzes'],0,5),
                'meta'    => ($nxt['tanar'] ?? '?') . ' · ' . ($nxt['osztaly'] ?? ''),
                'detail'  => $nxt['tantargy'] ?? '',
            ];
        }
        $extra = $rs['kovetkezo'] ? '' : ' Ma már nincs több óra benne.';
        ai_respond("A {$rs['terem']}. terem most **szabad**.$extra", $cards, $actions);
    }
}

// ── Szabad termek ─────────────────────────────────────
if (ai_has($norm, ['szabad']) && ai_has($norm, ['terem','termek'])) {
    $snap  = ai_get_rooms_snapshot();
    $free  = array_values(array_filter($snap, fn($r) => $r['allapot'] === 'szabad'));
    if (empty($free)) {
        ai_respond('Most egyetlen szabad termet sem találtam.', [], [['label' => 'Összes terem', 'href' => '/termek']]);
    }
    $cards = [];
    foreach (array_slice($free, 0, 8) as $r) {
        $cards[] = ['eyebrow' => 'Szabad', 'title' => $r['terem_szam']];
    }
    $extra = count($free) > 8 ? ' (első 8 mutatva)' : '';
    ai_respond(count($free) . " szabad terem van most$extra.", $cards, [['label' => 'Összes terem', 'href' => '/termek']]);
}

// ── Foglalt termek ────────────────────────────────────
if (ai_has($norm, ['foglalt']) && ai_has($norm, ['terem','termek'])) {
    $snap = ai_get_rooms_snapshot();
    $busy = array_values(array_filter($snap, fn($r) => $r['allapot'] === 'foglalt'));
    if (empty($busy)) {
        ai_respond('Most nem látok foglalt termet.', [], [['label' => 'Összes terem', 'href' => '/termek']]);
    }
    $cards = [];
    foreach (array_slice($busy, 0, 8) as $r) {
        $a = $r['aktualis'];
        $cards[] = [
            'eyebrow' => 'Foglalt',
            'title'   => $r['terem_szam'],
            'meta'    => ($a['tanar'] ?? '?') . ' · ' . ($a['osztaly'] ?? ''),
            'detail'  => ($a['tantargy'] ?? '') . ' · ' . ($a['kezdes'] ?? '') . '–' . ($a['vegzes'] ?? ''),
        ];
    }
    $extra = count($busy) > 8 ? ' (első 8 mutatva)' : '';
    ai_respond(count($busy) . " foglalt terem van most$extra.", $cards, [['label' => 'Összes terem', 'href' => '/termek']]);
}

// ── Általános „most mi van" ───────────────────────────
if (ai_has($norm, ['most','aktualis','jelenlegi']) && !ai_has($norm, ['tanar'])) {
    $snap   = ai_get_rooms_snapshot();
    $f_cnt  = count(array_filter($snap, fn($r) => $r['allapot'] === 'foglalt'));
    $sz_cnt = count($snap) - $f_cnt;
    ai_respond(
        "Jelenleg $sz_cnt szabad és $f_cnt foglalt terem van.",
        [],
        [['label' => 'Összes terem', 'href' => '/termek'], ['label' => 'Tanár kereső', 'href' => '/tanar']],
        ['Melyik termek szabadok?', 'Foglalt termek listája']
    );
}

// ── Tanár keresés ─────────────────────────────────────
if (ai_has($norm, ['hol van','hol tanit','melyik teremben','merre van','hol tart'])) {
    $teacher = ai_find_teacher($message);
    if ($teacher === null) {
        ai_respond(
            'Nem találtam tanárt a megadott névben vagy monogramban. Próbáld a tanár kereső oldalt.',
            [],
            [['label' => 'Tanár kereső', 'href' => '/tanar']]
        );
    }
    $ts   = ai_get_teacher_status($teacher);
    $name = $teacher['nev'] ?: $teacher['rovid_nev'];
    $kod  = $teacher['rovid_nev'];
    $actions = [['label' => 'Tanár oldal', 'href' => '/tanar/' . rawurlencode($kod)]];

    if ($ts['current']) {
        $c = $ts['current'];
        ai_respond(
            "$name most a {$c['terem']}. teremben van.",
            [[
                'eyebrow' => 'Most',
                'title'   => $c['terem'] . '. terem',
                'meta'    => $c['osztaly'] . ' · ' . $c['tantargy'],
                'detail'  => $c['kezdes'] . '–' . $c['vegzes'],
            ]],
            $actions
        );
    } elseif ($ts['next']) {
        $n = $ts['next'];
        ai_respond(
            "$name most szabad. Következő órája {$n['kezdes']}-kor a {$n['terem']}. teremben.",
            [[
                'eyebrow' => 'Következő',
                'title'   => $n['terem'] . '. terem',
                'meta'    => $n['osztaly'] . ' · ' . $n['tantargy'],
                'detail'  => $n['kezdes'] . '–' . $n['vegzes'],
            ]],
            $actions
        );
    } else {
        ai_respond("$name számára ma már nincs több óra.", [], $actions);
    }
}

// ════════════════════════════════════════════════════
// CLAUDE HAIKU FALLBACK (csak ha rules nem fogtak el)
// ════════════════════════════════════════════════════

$claude_reply = ai_claude_fallback($message, $history);
ai_respond($claude_reply, [], [], ['Szabad termek most', 'Foglalt termek most', 'Tanár kereső']);
