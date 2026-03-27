<?php
// utils/helpers.php – Közös segédfüggvények

/**
 * JSON válasz küldése és kilépés
 */
function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Hiba JSON válasz
 */
function json_error(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}

/**
 * Mai nap sorszáma (1=Hétfő ... 5=Péntek, 0=hétvége)
 * Supabase-ben het_napja: 1–5
 * PHP date('N'): 1=Hétfő ... 7=Vasárnap
 */
function mai_nap(): int {
    $n = (int) date('N'); // 1=Mon, 7=Sun
    return $n <= 5 ? $n : 0;  // hétvégén 0
}

/**
 * Aktuális idő 'HH:MM' formátumban (Budapest timezone)
 */
function aktualis_ido(): string {
    return date('H:i');
}

/**
 * Idő összehasonlítás: $ido >= $start AND $ido <= $end ?
 */
function render_time_sync_bootstrap(): void {
    $server_epoch_ms = (int) round(microtime(true) * 1000);
    $timezone = TZ;
    ?>
<script>
(() => {
  const timezone = <?= json_encode($timezone, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const serverEpochMs = <?= $server_epoch_ms ?>;
  const bootPerfNow = typeof performance !== 'undefined' && typeof performance.now === 'function'
    ? performance.now()
    : null;
  const bootClientNow = Date.now();

  const partsFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  });
  const hmFormatter = new Intl.DateTimeFormat('hu-HU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  });
  const hmsFormatter = new Intl.DateTimeFormat('hu-HU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  });
  const weekdayMap = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

  function nowMs() {
    if (bootPerfNow !== null && typeof performance !== 'undefined' && typeof performance.now === 'function') {
      return serverEpochMs + (performance.now() - bootPerfNow);
    }
    return serverEpochMs + (Date.now() - bootClientNow);
  }

  function nowDate() {
    return new Date(nowMs());
  }

  function nowParts() {
    const raw = {};
    for (const part of partsFormatter.formatToParts(nowDate())) {
      if (part.type !== 'literal') raw[part.type] = part.value;
    }
    return {
      year: Number(raw.year),
      month: Number(raw.month),
      day: Number(raw.day),
      weekday: weekdayMap[raw.weekday] ?? 0,
      hour: Number(raw.hour),
      minute: Number(raw.minute),
      second: Number(raw.second),
    };
  }

  function nowMinutes() {
    const parts = nowParts();
    return parts.hour * 60 + parts.minute;
  }

  window.TickyTime = {
    timezone,
    nowDate,
    nowMs,
    nowParts,
    nowMinutes,
    weekdayIndex() {
      return nowParts().weekday;
    },
    schoolDayIndex() {
      const day = nowParts().weekday;
      return (day === 0 || day === 6) ? 1 : day;
    },
    formatHM() {
      return hmFormatter.format(nowDate());
    },
    formatHMS() {
      return hmsFormatter.format(nowDate());
    },
  };
})();
</script>
    <?php
}

function ido_kozott(string $ido, string $start, string $end): bool {
    return $ido >= $start && $ido <= $end;
}

/**
 * URL routing: egyszerű minta illesztés
 * Példa: match_route('/api/terem/{szam}', $uri) → ['szam' => '204'] vagy false
 */
function match_route(string $pattern, string $uri): array|false {
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (preg_match($regex, $uri, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return false;
}

/**
 * CORS preflight kezelés
 */
function handle_cors(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204);
        exit;
    }
}
