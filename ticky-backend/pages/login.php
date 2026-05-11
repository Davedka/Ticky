<?php
require_once __DIR__ . '/../utils/auth.php';

// Ha már be van lépve, ne mutassuk újra a formot
if (ticky_aktualis_user()) {
    header('Location: /admin');
    exit;
}

$hiba = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = ticky_login(
        trim($_POST['felhasznalonev'] ?? ''),
        $_POST['jelszo'] ?? ''
    );
    if ($result['ok']) {
        header('Location: /admin');
        exit;
    }
    $hiba = $result['hiba'];
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Belépés – Ticky</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center">
  <form method="POST" class="bg-slate-900 p-8 rounded-xl w-full max-w-sm space-y-4 border border-slate-800">
    <h1 class="text-2xl font-bold">Ticky – Belépés</h1>
    <?php if ($hiba): ?>
      <div class="bg-red-900/40 border border-red-700 text-red-200 px-3 py-2 rounded text-sm">
        <?= htmlspecialchars($hiba) ?>
      </div>
    <?php endif; ?>
    <input name="felhasznalonev" placeholder="Felhasználónév" required autofocus
           class="w-full bg-slate-800 border border-slate-700 rounded px-3 py-2">
    <input name="jelszo" type="password" placeholder="Jelszó" required
           class="w-full bg-slate-800 border border-slate-700 rounded px-3 py-2">
    <button class="w-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold py-2 rounded">
      Belépés
    </button>
  </form>
</body>
</html>
