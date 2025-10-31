<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Análise de URL - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)} pre{white-space:pre-wrap}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | HELP DESK | Análise de URL
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/index.php">Help Desk</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="card glass mb-3">
        <div class="card-body">
          <h1 class="h6 mb-3">Análise de URL</h1>
          <form id="fUrl" class="row g-2" onsubmit="return false;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="col-12 col-md-8">
              <input class="form-control" id="url" placeholder="Cole a URL (ex.: https://exemplo.com)">
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
              <button class="btn btn-primary" id="btnRun"><i class="bi bi-search"></i> Analisar</button>
              <span class="small text-secondary align-self-center" id="msg"></span>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6">Resumo</h2>
              <div class="small text-secondary">URL final: <span id="finalUrl"></span></div>
              <div class="small text-secondary">HTTP code: <span id="httpCode"></span></div>
              <div class="small text-secondary">IPs resolvidos:</div>
              <ul id="ipList" class="small mb-2"></ul>
              <div class="small text-secondary">TLS:</div>
              <pre id="tls" class="small mb-0"></pre>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6">Achados</h2>
              <ul id="findings" class="mb-0"></ul>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6">Headers</h2>
              <pre id="headers" class="small mb-0"></pre>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script>
      async function post(path, data){ const r=await fetch('helpdesk_api.php'+path, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      const urlEl = document.getElementById('url');
      const msg = document.getElementById('msg');
      async function run(){
        const u = (urlEl.value||'').trim(); if(!u){ msg.textContent='Informe a URL.'; return; }
        msg.textContent='Executando...';
        try{
          const csrf = document.querySelector('#fUrl input[name=csrf]').value||'';
          const d = await post('', {action:'url_analyze', csrf: csrf, url:u});
          msg.textContent='Concluído';
          document.getElementById('finalUrl').textContent = d.final_url||'';
          document.getElementById('httpCode').textContent = String(d.http_code||0);
          const ips = d.ips||[]; const ipList = document.getElementById('ipList'); ipList.innerHTML = ips.map(ip=>`<li>${ip}</li>`).join('') || '<li class="text-secondary">(nenhum)</li>';
          document.getElementById('tls').textContent = JSON.stringify(d.tls||{}, null, 2);
          const f = d.findings||[]; document.getElementById('findings').innerHTML = f.map(x=>`<li>${x}</li>`).join('') || '<li class="text-secondary">Nenhum achado</li>';
          document.getElementById('headers').textContent = (d.headers||[]).join('\n');
        }catch(e){ msg.textContent='Falha na chamada'; }
      }
      document.getElementById('btnRun').addEventListener('click', (ev)=>{ ev.preventDefault(); run(); });
    </script>
  </body>
</html>
