<?php
// utils/szunet.php – Iskolai szünetek
// Ezt include-old minden API fájlban ami szünet-érzékeny

const TICKY_SZUNETEK = [
    ['nev' => 'Őszi szünet',    'start' => '2025-10-27', 'end' => '2025-10-31'],
    ['nev' => 'Téli szünet',    'start' => '2025-12-22', 'end' => '2026-01-02'],
    ['nev' => 'Tavaszi szünet', 'start' => '2026-04-02', 'end' => '2026-04-13'],
];

function aktiv_szunet(): ?array {
    $ma = date('Y-m-d');
    foreach (TICKY_SZUNETEK as $sz) {
        if ($ma >= $sz['start'] && $ma <= $sz['end']) return $sz;
    }
    return null;
}

function is_szunet(): bool {
    return aktiv_szunet() !== null;
}
