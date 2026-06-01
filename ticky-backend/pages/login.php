<?php
// login.php — Belépés (/login)
require __DIR__ . '/utils/_layout.php';

// Már be van lépve? -> főoldal
$__u = function_exists('ticky_current_user') ? ticky_current_user() : null;
if (is_array($__u) || (function_exists('admin_is_authenticated') && admin_is_authenticated())) {
    header('Location: /'); exit;
}

/**
 * INTEGRÁCIÓS PONT — a hitelesítést igazítsd a projekted tényleges
 * felhasználó-táblájához (oszlopnevek / jelszó-hash mező). Alapértelmezésként
 * a 'felhasznalok' tábla 'jelszo_hash' oszlopát és password_verify()-t használ.
 * Visszatérés: a felhasználó id-ja siker esetén, különben null.
 */
function ticky_login_verify(string $felhasznalonev, string $jelszo): ?string
{
    if (!function_exists('sb_get')) return null;
    try {
        $rows = sb_get('felhasznalok', [
            'felhasznalonev' => 'eq.' . $felhasznalonev,
            'aktiv'          => 'eq.true',
            'select'         => 'id,felhasznalonev,jelszo_hash,aktiv',
        ], 'service');
    } catch (\Throwable $e) {
        return null;
    }
    $row = $rows[0] ?? null;
    if (!$row || empty($row['jelszo_hash'])) return null;
    if (!password_verify($jelszo, (string) $row['jelszo_hash'])) return null;
    return (string) $row['id'];
}

$error = '';
$felh_value = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $felh_value = trim((string) ($_POST['felhasznalonev'] ?? ''));
    $jelszo     = (string) ($_POST['jelszo'] ?? '');

    if (function_exists('ticky_request_origin_is_same_host') && !ticky_request_origin_is_same_host()) {
        $error = 'Tiltott kérés.';
    } elseif (function_exists('ticky_has_valid_csrf_token') && !ticky_has_valid_csrf_token()) {
        $error = 'Lejárt a munkamenet, töltsd újra az oldalt.';
    } else {
        $wait = function_exists('ticky_rate_limit_wait_seconds') ? ticky_rate_limit_wait_seconds('login_page', 900, 8, 900) : 0;
        if ($wait > 0) {
            $error = 'Túl sok próbálkozás. Próbáld újra pár perc múlva.';
        } elseif ($felh_value === '' || $jelszo === '') {
            $error = 'Add meg a felhasználónevet és a jelszót.';
        } else {
            $uid = ticky_login_verify($felh_value, $jelszo);
            if ($uid !== null) {
                if (function_exists('ticky_set_user_session')) ticky_set_user_session($uid);
                header('Location: /'); exit;
            }
            if (function_exists('ticky_record_rate_limit_failure')) ticky_record_rate_limit_failure('login_page', 900, 8, 900);
            $error = 'Hibás felhasználónév vagy jelszó.';
        }
    }
}

$csrf = function_exists('ticky_csrf_token') ? ticky_csrf_token() : '';

ticky_head('Belépés', 'login', '', ['chrome' => false, 'body_class' => 'login-page']);
?>
<div class="login">
  <div class="box">
    <div class="brand-c">
      <div class="b"><span class="d"></span>Ticky</div>
      <div class="sub">Belépés</div>
    </div>
    <form class="card form" method="post" action="/login" autocomplete="on">
      <?php if ($error !== ''): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <label for="lf-user">Felhasználónév</label>
      <input class="inp" id="lf-user" type="text" name="felhasznalonev" value="<?= htmlspecialchars($felh_value) ?>" autocomplete="username" autofocus>
      <label for="lf-pass">Jelszó</label>
      <input class="inp" id="lf-pass" type="password" name="jelszo" autocomplete="current-password">
      <button class="btn btn-gold submit" type="submit">Belépés</button>
      <p class="hint">Admin vagy tester fiókkal tudsz belépni.</p>
    </form>
    <p class="back"><a href="/">← Vissza a főoldalra</a></p>
  </div>
</div>
<?php ticky_foot(); ?>
