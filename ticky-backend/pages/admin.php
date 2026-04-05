<?php
// pages/admin.php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../utils/helpers.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_pw  = trim((string) (getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? '')));
$no_pw_set = $admin_pw === '';


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


function makeToken(string $pw): string {
    return hash_hmac('sha256', 'ticky_admin_' . $pw, $pw);
}

$token  = $no_pw_set ? '' : makeToken($admin_pw);
$cookie = $_COOKIE['ticky_auth'] ?? '';
$authed = !$no_pw_set && $cookie !== '' && hash_equals($token, $cookie);


if (isset($_GET['logout'])) {
    $isSecure = ticky_is_https();
    setcookie('ticky_auth', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    header('Location: /admin');
    exit;
}


$login_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    sleep(1); 
    $input = trim($_POST['pw']);
    if (!$no_pw_set && hash_equals($admin_pw, $input)) {
        $isSecure = ticky_is_https();
        setcookie('ticky_auth', $token, [
            'expires' => time() + 8 * 3600, 
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        header('Location: /admin');
        exit;
    }
    $login_error = true;
}

if ($no_pw_set || !$authed):
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticky Admin - Belépés</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background-color: #060f1e; color: white; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="glass p-8 rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold mb-2">Ticky Admin</h1>
            <p class="text-white/40 text-sm">Kérlek add meg a mesterjelszót</p>
        </div>

        <?php if ($no_pw_set): ?>
            <div class="bg-red-500/20 border border-red-500/50 p-4 rounded-lg text-red-200 text-sm mb-4">
                <strong>HIBA:</strong> Az <code>ADMIN_PASSWORD</code> nincs beállítva a környezeti változók között!
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-4">
                <div>
                    <input type="password" name="pw" autofocus placeholder="Jelszó" 
                           class="w-full bg-black/40 border border-white/10 p-4 rounded-xl outline-none focus:border-yellow-500/50 transition-all text-center text-lg">
                </div>
                <?php if ($login_error): ?>
                    <p class="text-red-400 text-center text-sm font-medium">Hibás jelszó!</p>
                <?php endif; ?>
                <button class="w-full bg-yellow-600 hover:bg-yellow-500 text-white font-bold py-4 rounded-xl transition-all shadow-lg active:scale-95">
                    Belépés
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticky Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background-color: #060f1e; color: white; min-height: 100vh; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .toast { position: fixed; bottom: 24px; right: 24px; padding: 12px 24px; border-radius: 12px; z-index: 1000; animation: slideUp 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28); }
        @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .btn-action { transition: all 0.2s; }
        .btn-action:hover { transform: translateY(-1px); }
        .btn-action:active { transform: translateY(0); scale: 0.98; }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 style="font-family:'Playfair Display'" class="text-3xl md:text-4xl">⚙️ Admin Panel</h1>
                <p class="text-white/40 text-sm">Felhasználók és jogosultságok kezelése</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="/" class="hidden md:block text-white/60 hover:text-white transition">Megtekintés</a>
                <a href="?logout" class="bg-white/5 hover:bg-red-500/20 hover:text-red-300 border border-white/10 px-4 py-2 rounded-lg transition-all text-sm">Kijelentkezés</a>
            </div>
        </header>

        <main class="grid gap-8">
            <section class="glass rounded-3xl overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
                    <h2 class="font-bold text-lg">Felhasználók listája</h2>
                    <button onclick="loadFelhasznalok()" class="text-xs bg-white/5 hover:bg-white/10 px-3 py-1 rounded-full transition">Frissítés</button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-white/30 text-xs uppercase tracking-wider">
                                <th class="p-6 font-medium">Név / Felhasználó</th>
                                <th class="p-6 font-medium">Szerepkör</th>
                                <th class="p-6 font-medium text-center">Állapot</th>
                                <th class="p-6 font-medium text-right">Műveletek</th>
                            </tr>
                        </thead>
                        <tbody id="user-list" class="divide-y divide-white/5">
                            </tbody>
                    </table>
                </div>
                <div id="loader" class="p-10 text-center text-white/20">Betöltés...</div>
            </section>
        </main>
    </div>

    <div id="pw-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[500] hidden items-center justify-center p-4">
        <div class="glass p-8 rounded-3xl w-full max-w-md shadow-2xl animate-in zoom-in-95 duration-200">
            <h3 class="text-2xl font-bold mb-2">Új jelszó beállítása</h3>
            <p id="pw-modal-subtitle" class="text-white/40 text-sm mb-6">Módosítás: ...</p>
            
            <input type="hidden" id="pw-modal-id">
            <div class="space-y-4">
                <input type="password" id="pw-modal-pw" placeholder="Új jelszó (min. 6 karakter)" 
                       class="w-full bg-black/40 border border-white/10 p-4 rounded-xl outline-none focus:border-yellow-500/50 transition-all">
                
                <div id="pw-modal-msg" class="hidden text-red-400 text-sm bg-red-400/10 p-3 rounded-lg border border-red-400/20"></div>
                
                <div class="flex gap-3 pt-2">
                    <button onclick="closePwModal()" class="flex-1 bg-white/5 hover:bg-white/10 py-3 rounded-xl transition font-medium">Mégse</button>
                    <button onclick="savePw()" class="flex-1 bg-yellow-600 hover:bg-yellow-500 py-3 rounded-xl transition font-bold shadow-lg">Mentés</button>
                </div>
            </div>
        </div>
    </div>

    <script>

        window.CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?>";


        async function adminFetch(url, opts = {}) {
            if (!opts.headers) opts.headers = {};

            opts.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
            opts.headers['X-Ticky-Admin'] = '1';
            
            if (opts.body && !(opts.body instanceof FormData)) {
                opts.headers['Content-Type'] = 'application/json';
            }
            
            const res = await fetch(url, opts);
            if (res.status === 401 || res.status === 403) {
                toast('Munkamenet lejárt, kérlek jelentkezz be újra!', 'err');
                setTimeout(() => window.location.reload(), 2000);
            }
            return res;
        }

        const esc = (t) => String(t).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

        function toast(msg, type='success') {
            const t = document.createElement('div');
            t.className = `toast ${type === 'err' ? 'bg-red-600 text-white' : 'bg-green-600 text-white'} font-bold shadow-2xl`;
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 500); }, 3000);
        }

        async function loadFelhasznalok() {
            const list = document.getElementById('user-list');
            const loader = document.getElementById('loader');
            loader.style.display = 'block';
            
            try {
                const res = await adminFetch('/api/admin/felhasznalok');
                const data = await res.json();
                
                loader.style.display = 'none';
                list.innerHTML = '';

                if (!data.users || data.users.length === 0) {
                    list.innerHTML = '<tr><td colspan="4" class="p-10 text-center text-white/20">Nincsenek felhasználók.</td></tr>';
                    return;
                }

                data.users.forEach(u => {
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-white/[0.02] transition-colors group';
                    row.innerHTML = `
                        <td class="p-6">
                            <div class="font-bold text-white/90">${esc(u.nev || 'Névtelen')}</div>
                            <div class="text-xs text-white/30 italic">@${esc(u.felhasznalonev)}</div>
                        </td>
                        <td class="p-6 text-sm">
                            <span class="px-2 py-1 rounded text-xs ${u.szerep === 'admin' ? 'bg-yellow-500/20 text-yellow-500 border border-yellow-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'}">
                                ${u.szerep.toUpperCase()}
                            </span>
                        </td>
                        <td class="p-6 text-center">
                            <button onclick="toggleAktiv('${u.id}', ${!u.aktiv})" class="transition-all ${u.aktiv ? 'text-green-500' : 'text-red-500'} hover:scale-110">
                                ${u.aktiv ? '● Aktív' : '○ Inaktív'}
                            </button>
                        </td>
                        <td class="p-6 text-right space-x-2">
                            <button onclick="openPwModal('${u.id}', '${esc(u.felhasznalonev)}')" class="btn-action bg-white/5 hover:bg-white/10 px-4 py-2 rounded-lg text-xs font-medium border border-white/5">Jelszó</button>
                            <button onclick="deleteUser('${u.id}', '${esc(u.felhasznalonev)}')" class="btn-action bg-red-500/10 hover:bg-red-500/20 text-red-400 px-4 py-2 rounded-lg text-xs font-medium border border-red-500/10">Törlés</button>
                        </td>
                    `;
                    list.appendChild(row);
                });
            } catch (e) {
                loader.textContent = 'Hiba történt a betöltéskor.';
                toast('Nem sikerült betölteni a listát', 'err');
            }
        }

        async function toggleAktiv(id, val) {
            try {
                const res = await adminFetch(`/api/admin/felhasznalo/${id}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ aktiv: val })
                });
                const d = await res.json();
                if (d.ok) { toast('Állapot frissítve'); loadFelhasznalok(); }
                else toast(d.error || 'Hiba', 'err');
            } catch (e) { toast('API hiba', 'err'); }
        }

        async function deleteUser(id, fnev) {
            if (!confirm(`Biztosan törlöd a(z) ${fnev} felhasználót? A művelet nem vonható vissza.`)) return;
            try {
                const res = await adminFetch(`/api/admin/felhasznalo/${id}`, { method: 'DELETE' });
                const d = await res.json();
                if (d.ok) { toast('Felhasználó törölve'); loadFelhasznalok(); }
                else toast(d.error || 'Hiba', 'err');
            } catch (e) { toast('API hiba', 'err'); }
        }

        function openPwModal(id, fnev) {
            document.getElementById('pw-modal-id').value = id;
            document.getElementById('pw-modal-subtitle').textContent = `Módosítás: @${fnev}`;
            document.getElementById('pw-modal-pw').value = '';
            document.getElementById('pw-modal-msg').classList.add('hidden');
            document.getElementById('pw-modal').style.display = 'flex';
            setTimeout(() => document.getElementById('pw-modal-pw').focus(), 100);
        }

        function closePwModal() {
            document.getElementById('pw-modal').style.display = 'none';
        }

        async function savePw() {
            const id = document.getElementById('pw-modal-id').value;
            const pw = document.getElementById('pw-modal-pw').value;
            const msg = document.getElementById('pw-modal-msg');
            
            if (pw.length < 6) {
                msg.textContent = 'A jelszónak legalább 6 karakternek kell lennie.';
                msg.classList.remove('hidden');
                return;
            }

            try {
                const res = await adminFetch(`/api/admin/felhasznalo/${id}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ jelszo: pw })
                });
                const d = await res.json();
                if (d.ok) {
                    toast('✅ Jelszó sikeresen módosítva');
                    closePwModal();
                } else {
                    msg.textContent = d.error || 'Szerver hiba történt.';
                    msg.classList.remove('hidden');
                }
            } catch (e) {
                msg.textContent = 'API kapcsolódási hiba.';
                msg.classList.remove('hidden');
            }
        }


        loadFelhasznalok();


        window.onclick = function(event) {
            const modal = document.getElementById('pw-modal');
            if (event.target == modal) closePwModal();
        }
    </script>
</body>
</html>
