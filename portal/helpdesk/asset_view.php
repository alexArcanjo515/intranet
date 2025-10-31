<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ativo #<?= (int)$id ?> - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><i class="bi bi-tools"></i> <?= render_brand_html(); ?></a>
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/assets.php">Ativos</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div id="assetCard" class="card glass mb-3 d-none">
        <div class="card-body">
          <h1 class="h5 mb-0">Ativo <span class="text-secondary">#</span><span id="aId"></span> - <span id="aName"></span></h1>
          <div class="small text-secondary mt-1">Hostname: <span id="aHost"></span> • IP: <span id="aIp"></span> • SO: <span id="aOs"></span></div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-5">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6">Coletar via SSH</h2>
              <form id="fSSH" class="row g-2" onsubmit="return false;">
                <input type="hidden" name="asset_id" value="<?= (int)$id ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="col-12"><input class="form-control" name="ssh_pass" type="password" placeholder="Senha SSH" autocomplete="current-password"></div>
                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-primary btn-sm"><i class="bi bi-download"></i> Coletar agora</button>
                  <span id="sshMsg" class="small text-secondary"></span>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-7">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6 d-flex justify-content-between align-items-center">Último Scan <span id="scanStatus" class="badge bg-secondary d-none"></span></h2>
              <div id="scanResults" class="small" style="white-space:pre-wrap"></div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script>
      const assetId = <?= (int)$id ?>;
      function h(s){return (s||'').replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      async function api(path){ const r=await fetch('helpdesk_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function post(path,data){ const r=await fetch('helpdesk_api.php'+path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }

      async function loadAsset(){
        const d = await api('?action=asset_get&id='+assetId);
        const a = d.asset;
        document.getElementById('aId').textContent = a.id;
        document.getElementById('aName').textContent = a.name||'';
        document.getElementById('aHost').textContent = a.hostname||'';
        document.getElementById('aIp').textContent = a.ip||'';
        document.getElementById('aOs').textContent = (a.os_name||'')+(a.os_version?(' '+a.os_version):'');
        document.getElementById('assetCard').classList.remove('d-none');
      }

      async function loadLastScan(){
        // naive: busca último scan pelo maior id do asset
        try{
          const r = await fetch('helpdesk_api.php?action=scan_get&id='+(window.lastScanId||0));
          if(!r.ok) return;
          const d = await r.json();
          const s = d.scan || {}; const res = d.results||[];
          if (s && s.id){
            const badge = document.getElementById('scanStatus');
            badge.classList.remove('d-none');
            badge.textContent = s.status||'done';
            badge.className = 'badge ' + (s.status==='done'?'bg-success':(s.status==='error'?'bg-danger':'bg-secondary'));
            const wrap = document.getElementById('scanResults');
            wrap.innerHTML = res.map(x=>`# ${h(x.key_name)}\n${h(x.value_text||'')}\n`).join('\n');
          }
        }catch(e){}
      }

      document.getElementById('fSSH').addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const msg = document.getElementById('sshMsg');
        msg.textContent = 'Executando...';
        try{
          const fd = new FormData(ev.target);
          const data = {action:'run_collect_ssh', csrf: fd.get('csrf')||'', asset_id: assetId, ssh_pass: fd.get('ssh_pass')||''};
          const res = await post('', data);
          if(res.ok){
            window.lastScanId = res.scan_id;
            msg.textContent = 'Coleta iniciada/concluída: ' + (res.summary||'');
            await loadLastScan();
          } else {
            msg.textContent = 'Erro: ' + (res.error||'falha');
          }
        }catch(e){ msg.textContent = 'Falha na chamada'; }
      });

      loadAsset();
    </script>
  </body>
</html>
