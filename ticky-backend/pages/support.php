<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticky – Support</title>
<link rel="icon" type="image/png" href="/favicon.png?v=<?= filemtime('favicon.png') ?>">
<link rel="shortcut icon" href="/favicon.ico?v=<?= filemtime('favicon.ico') ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{font-family:'DM Sans',sans-serif;background-color:#060f1e;min-height:100vh;color:white;
  background-image:radial-gradient(ellipse 70% 55% at 15% 10%,rgba(26,74,138,.55) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 85% 85%,rgba(200,151,42,.18) 0%,transparent 55%);}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:40px 40px;}
a{text-decoration:none;color:inherit;}
svg{display:block;}
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:2px solid rgba(200,151,42,.6);outline-offset:2px;border-radius:8px;}
@media (prefers-reduced-motion: reduce){*{animation:none!important;transition:none!important;}}
.glass{background:rgba(255,255,255,.05);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.10);}
.gold-line{height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#1a4a8a,#c8972a,#1a4a8a);}
.pulse{animation:pd 2s infinite;}
@keyframes pd{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.fade-up  {animation:fu .5s cubic-bezier(.22,1,.36,1) both;}
.fade-up-2{animation:fu .5s .1s cubic-bezier(.22,1,.36,1) both;}
.fade-up-3{animation:fu .5s .2s cubic-bezier(.22,1,.36,1) both;}
.fade-up-4{animation:fu .5s .3s cubic-bezier(.22,1,.36,1) both;}
@keyframes fu{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.card-hover{transition:transform .2s,border-color .2s,background .2s;}
.card-hover:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.2)!important;background:rgba(255,255,255,.07)!important;}
/* select nyíl (idézőjel-biztos, CSS-ben) */
.sel-arrow{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.4)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;cursor:pointer;}
/* FAQ */
.faq-item{border-radius:10px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);overflow:hidden;transition:border-color .2s;margin-bottom:7px;}
.faq-item:hover{border-color:rgba(255,255,255,.14);}
.faq-item.open{border-color:rgba(200,151,42,.3);background:rgba(200,151,42,.04);}
.faq-q{width:100%;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;background:transparent;border:none;color:white;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;cursor:pointer;text-align:left;}
.faq-icon{width:20px;height:20px;border-radius:50%;flex-shrink:0;border:1px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:12px;color:rgba(255,255,255,.4);transition:all .2s;}
.faq-item.open .faq-icon{background:rgba(200,151,42,.15);border-color:rgba(200,151,42,.4);color:#f0c76b;transform:rotate(45deg);}
.faq-a{max-height:0;overflow:hidden;transition:max-height .35s cubic-bezier(.22,1,.36,1),padding .2s;padding:0 16px;font-size:13px;line-height:1.7;color:rgba(255,255,255,.55);}
.faq-item.open .faq-a{max-height:300px;padding:0 16px 14px;}
/* Form */
.inp{width:100%;padding:11px 13px;border-radius:9px;border:1.5px solid rgba(255,255,255,.10);background:rgba(255,255,255,.05);color:white;font-family:'DM Sans',sans-serif;font-size:14px;transition:border-color .2s,background .2s;outline:none;}
.inp::placeholder{color:rgba(255,255,255,.28);}
.inp:focus{border-color:rgba(200,151,42,.45);background:rgba(255,255,255,.07);}
textarea.inp{resize:vertical;min-height:90px;}
.gold-btn{width:100%;padding:13px;border-radius:9px;border:none;cursor:pointer;background:linear-gradient(135deg,#c8972a,#a07020);color:white;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;transition:all .2s;}
.gold-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(200,151,42,.4);}
.gold-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}
/* Toast */
.toast{position:fixed;bottom:20px;right:16px;left:16px;z-index:500;padding:12px 18px;border-radius:12px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;backdrop-filter:blur(16px);animation:toastIn .3s cubic-bezier(.22,1,.36,1);background:rgba(232,51,74,.2);border:1px solid rgba(232,51,74,.4);color:#ff6b82;}
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@media(min-width:480px){.toast{left:auto;width:auto;max-width:360px;}}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../utils/_nav.php'; ticky_nav('','Support'); ?>

<div style="position:relative;z-index:10;max-width:640px;margin:0 auto;padding:28px 16px 60px;">

  <!-- Hero -->
  <div class="fade-up" style="text-align:center;margin-bottom:28px;">
    <div style="display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:99px;font-size:11px;font-weight:600;background:rgba(0,200,150,.12);border:1px solid rgba(0,200,150,.3);color:#00c896;margin-bottom:16px;">
      <span class="pulse" style="width:6px;height:6px;border-radius:50%;background:#00c896;display:inline-block;flex-shrink:0;"></span>
      Minden rendszer működik
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:clamp(36px,10vw,52px);font-weight:700;color:white;line-height:1.05;letter-spacing:-1px;">Hogyan<br>segíthetünk?</h1>
    <p style="font-size:14px;color:rgba(255,255,255,.45);margin-top:12px;line-height:1.7;max-width:340px;margin-left:auto;margin-right:auto;">
      Általában <strong style="color:rgba(255,255,255,.7);">24 órán belül</strong> válaszolunk. Nézd meg a GYIK-et, lehet a válasz már ott van.
    </p>
  </div>

  <!-- Elérhetőség -->
  <div class="fade-up-2" style="margin-bottom:20px;">
    <p style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:12px;">Elérhetőség</p>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <!-- Email -->
      <div>
        <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
        <a href="mailto:tickysupport@gmail.com?subject=Ticky%20support" class="glass card-hover" style="display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:0 0 14px 14px;border-top:none;">
          <div style="width:42px;height:42px;border-radius:10px;background:rgba(200,151,42,.15);border:1px solid rgba(200,151,42,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#f0c76b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
          </div>
          <div style="flex:1;min-width:0;">
            <p style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:white;">Email support</p>
            <p style="font-size:12px;color:rgba(255,255,255,.4);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">tickysupport@gmail.com · ~24h válasz</p>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.3)" stroke-width="2" style="flex-shrink:0;"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
      <!-- Bug -->
      <div>
        <div style="height:2px;border-radius:2px 2px 0 0;background:linear-gradient(90deg,#5a1a1a,#ff5050,#5a1a1a);"></div>
        <a href="https://github.com/Davedka/Ticky/issues/new" target="_blank" rel="noopener" class="glass card-hover" style="display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:0 0 14px 14px;border-top:none;">
          <div style="width:42px;height:42px;border-radius:10px;background:rgba(255,80,80,.10);border:1px solid rgba(255,80,80,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#ff7a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><path d="M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"/><path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6Z"/><path d="M12 20v-9"/><path d="M6.53 9C4.6 8.8 3 7.1 3 5"/><path d="M6 13H2"/><path d="M3 21c0-2.1 1.7-3.9 3.8-4"/><path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"/><path d="M22 13h-4"/><path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"/></svg>
          </div>
          <div style="flex:1;min-width:0;">
            <p style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:white;">Bug report</p>
            <p style="font-size:12px;color:rgba(255,255,255,.4);margin-top:2px;">GitHub Issues · Hibák jelzése</p>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.3)" stroke-width="2" style="flex-shrink:0;"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
    </div>
  </div>

  <!-- Form -->
  <div class="fade-up-3" style="margin-bottom:20px;">
    <p style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:12px;">Üzenet küldése</p>
    <div class="gold-line" style="border-radius:8px 8px 0 0;"></div>
    <div class="glass" style="border-radius:0 0 14px 14px;border-top:none;padding:20px;">
      <div id="support-form" style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <label style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.3);display:block;margin-bottom:6px;">Neved</label>
            <input type="text" id="f-nev" class="inp" placeholder="Kiss Péter">
          </div>
          <div>
            <label style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.3);display:block;margin-bottom:6px;">Email</label>
            <input type="email" id="f-email" class="inp" placeholder="email@iskola.hu">
          </div>
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.3);display:block;margin-bottom:6px;">Tárgy</label>
          <select id="f-targy" class="inp sel-arrow">
            <option value="" style="background:#0b2e59;">— Válassz kategóriát —</option>
            <option value="hiba" style="background:#0b2e59;">🐛 Hibajelentés</option>
            <option value="kerdes" style="background:#0b2e59;">❓ Általános kérdés</option>
            <option value="terem" style="background:#0b2e59;">🏫 Terem / órarend probléma</option>
            <option value="tanar" style="background:#0b2e59;">👩‍🏫 Tanár adat módosítás</option>
            <option value="osztaly" style="background:#0b2e59;">🎓 Osztály / osztálynézet probléma</option>
            <option value="javaslat" style="background:#0b2e59;">💡 Fejlesztési javaslat</option>
            <option value="egyeb" style="background:#0b2e59;">📋 Egyéb</option>
          </select>
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.3);display:block;margin-bottom:6px;">Üzenet</label>
          <textarea id="f-uzenet" class="inp" rows="4" placeholder="Írd le részletesen a problémát…"></textarea>
        </div>
        <button class="gold-btn" id="send-btn" onclick="sendForm()">Üzenet küldése →</button>
        <p style="text-align:center;font-size:11px;color:rgba(255,255,255,.2);">A levél a tickysupport@gmail.com-ra lesz elküldve</p>
      </div>
      <!-- Siker -->
      <div id="form-success" style="display:none;text-align:center;padding:24px 0;">
        <svg viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:44px;height:44px;margin:0 auto 10px;display:block;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
        <p style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#4ade80;margin-bottom:6px;">Elküldve!</p>
        <p style="font-size:13px;color:rgba(255,255,255,.45);">Hamarosan válaszolunk a megadott email címre.</p>
        <button onclick="resetForm()" style="margin-top:16px;font-size:13px;padding:8px 16px;border-radius:8px;color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.1);background:transparent;cursor:pointer;">Új üzenet</button>
      </div>
    </div>
  </div>

  <!-- GYIK -->
  <div class="fade-up-4">
    <p style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:12px;">Gyakori kérdések</p>
    <?php
    $faq = [
      ['q'=>'Miért nem jelenik meg helyesen a terem foglaltsága?','a'=>'Az órarend adatbázisból töltődik be. Ha egy terem tévesen foglaltnak vagy szabadnak látszik, valószínűleg az órarend frissítésre szorul. Jelezd a support emailen a terem számával együtt.'],
      ['q'=>'Hogyan lehet hozzáadni egy új tanárt?','a'=>'Tanárokat az Admin panelen lehet kezelni (⚙️ Admin gomb). Az admin jelszó az iskolai rendszergazdánál van. Ha nincs hozzáférésed, küldj emailt a support címre.'],
      ['q'=>'Miért nem látom az osztályomat a listában?','a'=>'Az osztálylista az órarend adatbázisból épül fel. Ha egy osztály hiányzik, vagy hibás néven jelenik meg, jelezd supporton az osztály nevét és ha tudod, az érintett napot vagy órát is.'],
      ['q'=>'Mit jelent a párhuzamos jelzés az osztálynézetben?','a'=>'A párhuzamos jelzés azt mutatja, hogy ugyanabban az idősávban az osztály több csoportra bontva tanul. Ilyenkor több terem, tanár vagy tantárgy is megjelenhet ugyanazon az óránál.'],
      ['q'=>'Miért látok egyszerre több tanárt vagy több termet az osztálynézetben?','a'=>'Ez általában csoportbontás vagy párhuzamos óra miatt történik. A nézet ilyenkor az összes kapcsolódó csoportot egy idősávban mutatja meg.'],
      ['q'=>'A QR kód nem a megfelelő teremre mutat.','a'=>'A QR kódok a /terem/{szám} URL-re mutatnak. Ha a szám rossz, nyomtasd újra a QR Generátor oldalon.'],
      ['q'=>'Hétvégén miért nem látszanak a foglaltsági adatok?','a'=>'Ez szándékos: az órarend hétfőtől péntekig működik. Hétvégén minden terem szabadnak jelenik meg.'],
      ['q'=>'Az oldal nem tölt be / fehér képernyőt látok.','a'=>'Próbáld Ctrl+Shift+R kombinációval frissíteni. Ha ez nem segít, jelezd a bug reporton a böngésző verzióját és az oldal URL-jét.'],
    ];
    foreach($faq as $i => $item): ?>
      <div class="faq-item" id="faq-<?= $i ?>">
        <button class="faq-q" onclick="toggleFaq(<?= $i ?>)">
          <span style="text-align:left;"><?= htmlspecialchars($item['q']) ?></span>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a"><?= htmlspecialchars($item['a']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <p style="text-align:center;font-size:11px;color:rgba(255,255,255,.15);margin-top:32px;">Ticky v1.0 · MSZC Gépészeti Iskola</p>
</div>

<script>
function toggleFaq(i){
  const el=document.getElementById('faq-'+i),was=el.classList.contains('open')
  document.querySelectorAll('.faq-item').forEach(f=>f.classList.remove('open'))
  if(!was)el.classList.add('open')
}
function sendForm(){
  const nev=document.getElementById('f-nev').value.trim()
  const email=document.getElementById('f-email').value.trim()
  const targy=document.getElementById('f-targy').value
  const uzenet=document.getElementById('f-uzenet').value.trim()
  if(!nev||!email||!targy||!uzenet){showToast('Kérlek töltsd ki az összes mezőt!');return}
  if(!email.includes('@')){showToast('Érvénytelen email cím!');return}
  const btn=document.getElementById('send-btn')
  btn.disabled=true;btn.innerHTML='Küldés…'
  const targyNevek={hiba:'🐛 Hibajelentés',kerdes:'❓ Általános kérdés',terem:'🏫 Terem / órarend',tanar:'👩‍🏫 Tanár adat',osztaly:'🎓 Osztály / osztálynézet probléma',javaslat:'💡 Fejlesztési javaslat',egyeb:'📋 Egyéb'}
  const subject=encodeURIComponent('[Ticky Support] '+(targyNevek[targy]||targy)+' – '+nev)
  const body=encodeURIComponent('Feladó: '+nev+'\nEmail: '+email+'\nKategória: '+(targyNevek[targy]||targy)+'\n\nÜzenet:\n'+uzenet)
  setTimeout(()=>{
    window.location.href='mailto:tickysupport@gmail.com?subject='+subject+'&body='+body
    document.getElementById('support-form').style.display='none'
    document.getElementById('form-success').style.display='block'
    btn.disabled=false;btn.innerHTML='Üzenet küldése →'
  },800)
}
function resetForm(){
  ['f-nev','f-email','f-uzenet'].forEach(id=>document.getElementById(id).value='')
  document.getElementById('f-targy').value=''
  document.getElementById('support-form').style.display='flex'
  document.getElementById('form-success').style.display='none'
}
function showToast(msg){
  const t=document.createElement('div');t.className='toast'
  t.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;flex-shrink:0;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/></svg><span></span>'
  t.querySelector('span').textContent=msg
  document.body.appendChild(t);setTimeout(()=>t.remove(),3500)
}
</script>
</body>
</html>
