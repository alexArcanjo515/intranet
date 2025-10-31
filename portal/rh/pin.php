<?php
session_start();
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/security_headers.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
$roles = $_SESSION['portal_user']['roles'] ?? [];
$canView = in_array('admin',(array)$roles,true) || in_array('rh_view',(array)$roles,true) || in_array('rh_operate',(array)$roles,true) || in_array('rh_manage',(array)$roles,true);
if (!$canView) { http_response_code(403); echo 'Acesso negado'; exit(); }
$lockedUntil = (int)($_SESSION['rh_pin_lock_until'] ?? 0);
$now = time();
$locked = $lockedUntil > $now;
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RH - PIN de Acesso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      .glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}
      .pin-box{width:40px;height:48px;text-align:center;font-size:1.25rem}
      @media (max-width: 420px){ .pin-box{width:34px;height:44px} }
    </style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH | PIN
        <div class="ms-auto d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a></div>
      </div>
    </nav>
    <main class="container py-5" style="max-width:600px;">
      <div class="card glass">
        <div class="card-body p-4">
          <h1 class="h5 mb-3">Acesso ao RH</h1>
          <p class="text-secondary small mb-4">Digite o PIN de 8 dígitos para acessar o painel do RH.</p>
          <form id="f" onsubmit="return false;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="d-flex gap-2 mb-3 flex-wrap">
              <?php for($i=0;$i<8;$i++): ?>
                <input class="form-control pin-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" id="d<?= $i ?>" <?= $locked? 'disabled':'' ?>>
              <?php endfor; ?>
            </div>
            <div id="msg" class="small <?= $locked? 'text-warning':'text-secondary' ?>">
              <?php if ($locked): ?>
                Tentativas esgotadas. Tente novamente após <?= date('H:i', $lockedUntil) ?>.
              <?php else: ?>
                Você tem até 3 tentativas.
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </main>
    <script>
      const boxes = Array.from({length:8},(_,i)=>document.getElementById('d'+i)).filter(Boolean);
      const locked = <?= $locked? 'true':'false' ?>;
      function collect(){ return boxes.map(b=> (b.value||'').trim()).join(''); }
      function clearAll(){ boxes.forEach(b=>b.value=''); if(boxes[0]) boxes[0].focus(); }
      async function verify(){
        const pin = collect(); if(pin.length!==8) return;
        try{
          const csrf = document.querySelector('input[name=csrf]').value || '';
          const r = await fetch('pin_api.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'verify_pin', csrf: csrf, pin: pin})});
          const d = await r.json();
          if(d.ok){ window.location.href = '/portal/rh/index.php'; return; }
          if(d.locked){ boxes.forEach(b=>b.disabled=true); document.getElementById('msg').textContent = 'Tentativas esgotadas. Tente novamente em 1 hora.'; return; }
          document.getElementById('msg').textContent = 'PIN incorreto. Restam '+(d.remaining_attempts||0)+' tentativa(s).';
          clearAll();
        }catch(e){ document.getElementById('msg').textContent='Falha ao verificar. Tente novamente.'; }
      }
      if(!locked){
        boxes.forEach((b,i)=>{
          b.addEventListener('input',()=>{ if(b.value.length===1){ if(i<boxes.length-1) boxes[i+1].focus(); else verify(); } });
          b.addEventListener('keydown',(ev)=>{ if(ev.key==='Backspace' && !b.value && i>0){ boxes[i-1].focus(); } });
        });
        if (boxes[0]) boxes[0].focus();
      }
    </script>
  </body>
</html>
