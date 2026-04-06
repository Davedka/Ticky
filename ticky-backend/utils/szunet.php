<?php
// utils/szunet.php – Szünet kezelés, Supabase-ből olvas (1 órás temp-fájl cache)

function _szunet_fetch_all(): array {
    $cache = sys_get_temp_dir() . '/ticky_szunetek.json';
    // Cache hit (1 óra)
    if (file_exists($cache) && (time() - filemtime($cache)) < 3600) {
        $data = json_decode(@file_get_contents($cache), true);
        if (is_array($data)) return $data;
    }
    // Supabase lekérés – sb_get már elérhető, mert config/supabase.php be van töltve
    if (!function_exists('sb_get')) return [];
    try {
        $rows = sb_get('szunetek', [
            'select' => 'id,nev,kezdet,vege',
            'order'  => 'kezdet.asc',
        ]);
        @file_put_contents($cache, json_encode($rows ?? []), LOCK_EX);
        return is_array($rows) ? $rows : [];
    } catch (\Throwable $e) {
        return [];
    }
}

function szunet_invalidate_cache(): void {
    @unlink(sys_get_temp_dir() . '/ticky_szunetek.json');
}

function aktiv_szunet(): ?array {
    $ma = date('Y-m-d');
    foreach (_szunet_fetch_all() as $sz) {
        $kezdet = $sz['kezdet'] ?? '';
        $vege   = $sz['vege']   ?? '';
        if ($kezdet !== '' && $vege !== '' && $kezdet <= $ma && $ma <= $vege) {
            return $sz;
        }
    }
    return null;
}

function is_szunet(): bool {
    return aktiv_szunet() !== null;
}
