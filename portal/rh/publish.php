<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
$roles = $_SESSION['portal_user']['roles'] ?? [];
$canManage = in_array('admin',(array)$roles,true) || in_array('rh_manage',(array)$roles,true);
if (!$canManage) { http_response_code(403); echo 'Acesso negado'; exit(); }
// PIN guard similar ao index RH
$ttl = 900; if (empty($_SESSION['rh_pin_ok'])) { header('Location: /portal/rh/pin.php'); exit(); }
if (!empty($_SESSION['rh_pin_last']) && (time() - (int)$_SESSION['rh_pin_last']) > $ttl) { unset($_SESSION['rh_pin_ok'], $_SESSION['rh_pin_at'], $_SESSION['rh_pin_last']); header('Location: /portal/rh/pin.php'); exit(); }
$_SESSION['rh_pin_last'] = time();
$csrf = htmlspecialchars(csrf_token());
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RH | Publicar Notícias e Documentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/portal/rh/index.php"><?php echo render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | RH | Publicar
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/news.php">Ver notícias</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/documents.php">Ver documentos</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/rh/index.php">RH</a>
        </div>
      </div>
    </nav>
    <main class="container py-4">
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <div class="card glass">
            <div class="card-header d-flex justify-content-between align-items-center"><strong>Publicar notícia</strong><small class="text-secondary">Visível no Portal</small></div>
            <div class="card-body">
              <form id="fNews" onsubmit="return false;" class="row g-2">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="col-12"><label class="form-label">Título</label><input class="form-control" name="title" required></div>
                <div class="col-12"><label class="form-label">Conteúdo (HTML simples permitido)</label><textarea class="form-control" name="content" rows="6"></textarea></div>
                <div class="col-12 d-flex gap-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="nPub" name="is_published" checked><label for="nPub" class="form-check-label">Publicar</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="nPin" name="is_pinned"><label for="nPin" class="form-check-label">Fixar</label></div>
                </div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary" id="btnNews"><i class="bi bi-newspaper"></i> Publicar</button><span id="msgNews" class="small text-secondary"></span></div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card glass">
            <div class="card-header d-flex justify-content-between align-items-center"><strong>Enviar documento</strong><small class="text-secondary">Visível no Portal</small></div>
            <div class="card-body">
              <form id="fDoc" onsubmit="return false;" class="row g-2" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="col-12"><label class="form-label">Título</label><input class="form-control" name="title" required></div>
                <div class="col-6"><label class="form-label">Categoria</label><input class="form-control" name="category" placeholder="Opcional"></div>
                <div class="col-6"><label class="form-label">Versão</label><input class="form-control" name="version" type="number" min="1" value="1"></div>
                <div class="col-12"><label class="form-label">Ficheiro</label><input class="form-control" type="file" name="file" required></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary" id="btnDoc"><i class="bi bi-upload"></i> Enviar</button><span id="msgDoc" class="small text-secondary"></span></div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>
    <script>
      async function postJSON(data){ const fd=new FormData(); Object.entries(data).forEach(([k,v])=>fd.append(k,v)); const r=await fetch('/portal/rh/rh_api.php',{method:'POST', body:fd}); if(!r.ok) throw new Error('api'); return r.json(); }
      document.getElementById('btnNews').addEventListener('click', async (ev)=>{
        ev.preventDefault(); const f=document.getElementById('fNews'); const msg=document.getElementById('msgNews');
        const data={action:'news_create', csrf:f.csrf.value, title:f.title.value.trim(), content:f.content.value, is_published: f.is_published.checked?1:0, is_pinned: f.is_pinned.checked?1:0};
        if(!data.title){ return; }
        msg.textContent='Publicando...';
        try{ const res=await postJSON(data); if(res.ok){ msg.textContent='Publicado'; f.reset(); document.getElementById('nPub').checked=true; document.getElementById('nPin').checked=false; } else { msg.textContent='Falha'; } }
        catch(e){ msg.textContent='Erro'; }
      });
      document.getElementById('btnDoc').addEventListener('click', async (ev)=>{
        ev.preventDefault(); const f=document.getElementById('fDoc'); const msg=document.getElementById('msgDoc');
        if(!f.file.files.length){ return; }
        msg.textContent='Enviando...';
        const formData=new FormData(f); formData.append('action','public_doc_upload');
        try{ const r=await fetch('/portal/rh/rh_api.php',{method:'POST', body:formData}); const d=await r.json(); if(d.ok){ msg.textContent='Enviado'; f.reset(); } else { msg.textContent='Falha'; } }
        catch(e){ msg.textContent='Erro'; }
      });
    </script>
  </body>
</html>
