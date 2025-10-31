<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ativos - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><i class="bi bi-tools"></i> <?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a>
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/index.php">Help Desk</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="card glass">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Nome</th><th>Host</th><th>IP</th><th>Base</th><th>Status</th><th></th></tr></thead>
            <tbody id="rows"><tr><td colspan="7" class="text-secondary">Carregando...</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="text-secondary small mt-2">Servidores são geridos em Configurações &gt; Servidores.</div>
    </main>

    <script>
      async function api(path){ const r=await fetch('helpdesk_api.php'+path,{cache:'no-store'}); if(!r.ok) throw new Error('api'); return r.json(); }
      async function post(path,data){ const r=await fetch('helpdesk_api.php'+path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}); if(!r.ok) throw new Error('api'); return r.json(); }
      function h(s){return (s||'').replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      async function load(){
        const tb=document.getElementById('rows');
        try{
          const d=await api('?action=assets_list');
          const it=d.items||[];
          if(it.length===0){tb.innerHTML='<tr><td colspan="7" class="text-secondary">Sem servidores cadastrados.</td></tr>';return;}
          tb.innerHTML=it.map(s=>{
            const proto=(s.protocol||'http');
            const p=parseInt(s.port||0,10);
            const host=s.host||s.ip||'';
            const base=proto+'://'+host+(((proto==='http'&&p&&p!==80)||(proto==='https'&&p&&p!==443))?(':'+p):'');
            return `<tr data-id='${s.id}'><td>${s.id}</td><td>${h(s.name||'')}</td><td>${h(s.host||'')}</td><td>${h(s.ip||'')}</td><td class='small text-secondary'>${h(base)}</td><td class='small' id='st-${s.id}'><span class='badge bg-secondary'>...</span></td><td>
              <a class='btn btn-sm btn-outline-primary' href='server_analysis.php?id=${s.id}'>Analisar</a>
            </td></tr>`;
          }).join('');
          // buscar status
          const ids = it.map(s=>s.id).join(',');
          try{
            const st = await api('?action=servers_status&ids='+encodeURIComponent(ids));
            (st.items||[]).forEach(x=>{
              const el = document.getElementById('st-'+x.id);
              if(!el) return;
              const badge = x.up ? `<span class='badge bg-success'>UP</span>` : `<span class='badge bg-danger'>DOWN</span>`;
              const extra = (x.latency_ms!=null?(' '+x.latency_ms+'ms'):'') + (x.http_code!=null?(' • '+x.http_code):'');
              el.innerHTML = badge + `<span class='text-secondary'>${extra}</span>`;
            });
          }catch(e){}
        }catch(e){ tb.innerHTML='<tr><td colspan="6" class="text-danger">Falha ao carregar.</td></tr>'; }
      }
      load();
    </script>
  </body>
</html>
