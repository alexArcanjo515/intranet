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
    <title>Análise do Servidor #<?= (int)$id ?> - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)} pre{white-space:pre-wrap}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><i class="bi bi-tools"></i> <?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a>
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/servers.php">Servidores</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <div class="card glass mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-secondary small">Servidor</div>
              <div class="h5 mb-0"><span id="sName">#<?= (int)$id ?></span> <span class="text-secondary" id="sBase"></span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card glass mb-3">
        <div class="card-body">
          <h2 class="h6">Selecionar análises</h2>
          <form id="fRun" class="row g-2" onsubmit="return false;">
            <input type="hidden" name="server_id" value="<?= (int)$id ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="col-12 d-flex flex-wrap gap-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="tPackages" value="packages">
                <label class="form-check-label" for="tPackages">Pacotes instalados</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="tSec" value="security_baseline">
                <label class="form-check-label" for="tSec">Baseline de segurança</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="tNet" value="network">
                <label class="form-check-label" for="tNet">Rede/Portas</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="tVuln" value="vulnerabilities">
                <label class="form-check-label" for="tVuln">Pacotes (para avaliação CVE)</label>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Senha SSH</label>
              <input type="password" class="form-control" id="sshPass" placeholder="Informe a senha SSH do servidor">
            </div>
            <div class="col-12 d-flex align-items-center gap-2">
              <button class="btn btn-primary" id="btnRun"><i class="bi bi-play"></i> Executar</button>
              <span class="small text-secondary" id="runMsg"></span>
            </div>
          </form>
        </div>
      </div>

      <div id="resultsWrap" class="d-flex flex-column gap-3">
        <div class="card glass d-none" id="boxPackages"><div class="card-body"><h3 class="h6">Pacotes</h3><pre id="outPackages" class="small"></pre></div></div>
        <div class="card glass d-none" id="boxSec"><div class="card-body"><h3 class="h6">Baseline de segurança</h3><pre id="outSec" class="small"></pre></div></div>
        <div class="card glass d-none" id="boxNet"><div class="card-body"><h3 class="h6">Rede/Portas</h3><pre id="outNet" class="small"></pre></div></div>
        <div class="card glass d-none" id="boxVuln"><div class="card-body"><h3 class="h6">Pacotes (para avaliação CVE)</h3><pre id="outVuln" class="small"></pre></div></div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-light btn-sm" id="btnExport"><i class="bi bi-download"></i> Exportar JSON</button>
          <button class="btn btn-outline-primary btn-sm" id="btnExportPdf"><i class="bi bi-filetype-pdf"></i> Exportar PDF</button>
        </div>
      </div>

      <div class="card glass mt-3">
        <div class="card-body">
          <h2 class="h6">Hardening (com confirmação)</h2>
          <form id="fHard" class="row g-2" onsubmit="return false;">
            <input type="hidden" name="server_id" value="<?= (int)$id ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="col-12">
              <div class="small text-secondary">Selecione as ações (Linux/Windows):</div>
              <div class="d-flex flex-wrap gap-3 mt-1">
                <div class="form-check"><input class="form-check-input" type="checkbox" value="linux_enable_ufw" id="h1"><label class="form-check-label" for="h1">Linux: habilitar UFW</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" value="linux_ufw_basic" id="h2"><label class="form-check-label" for="h2">Linux: liberar OpenSSH/80/443</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" value="linux_disable_root_ssh" id="h3"><label class="form-check-label" for="h3">Linux: desabilitar root via SSH</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" value="linux_harden_sshd" id="h4"><label class="form-check-label" for="h4">Linux: endurecer SSH</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" value="linux_update_packages" id="h5"><label class="form-check-label" for="h5">Linux: atualizar pacotes</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" value="win_firewall_enable" id="w1"><label class="form-check-label" for="w1">Windows: habilitar Firewall</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" value="win_rdp_disable" id="w2"><label class="form-check-label" for="w2">Windows: desabilitar RDP</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" value="win_updates_check" id="w3"><label class="form-check-label" for="w3">Windows: listar updates</label></div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Senha SSH (opcional)</label>
              <input type="password" class="form-control" id="sshPassH" placeholder="Se não definida no cadastro">
            </div>
            <div class="col-12 col-md-3 form-check">
              <input class="form-check-input" type="checkbox" id="dryRun" checked>
              <label class="form-check-label" for="dryRun">Dry-run</label>
            </div>
            <div class="col-12 col-md-5">
              <label class="form-label">Confirmação (digite YES para aplicar)</label>
              <input class="form-control" id="confirmTxt" placeholder="YES">
            </div>
            <div class="col-12 d-flex align-items-center gap-2">
              <button class="btn btn-danger" id="btnHarden"><i class="bi bi-shield-lock"></i> Executar hardening</button>
              <span class="small text-secondary" id="hardMsg"></span>
            </div>
          </form>
          <div class="mt-3 d-none" id="hardOutWrap">
            <h3 class="h6">Resultados</h3>
            <pre id="hardOut" class="small"></pre>
          </div>
        </div>
      </div>
    </main>

    <script>
      const serverId = <?= (int)$id ?>;
      function h(s){return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m]));}
      async function api(path, opt){ const r=await fetch('helpdesk_api.php'+path, opt); if(!r.ok) throw new Error('api'); return r.json(); }

      async function loadServer(){
        try{
          const d = await api('?action=servers_list');
          const s = (d.items||[]).find(x=>parseInt(x.id,10)===serverId);
          if(!s) return;
          const proto=(s.protocol||'http'); const p=parseInt(s.port||0,10); const host=s.host||s.ip||''; const base=proto+'://'+host+(((proto==='http'&&p&&p!==80)||(proto==='https'&&p&&p!==443))?(':'+p):'');
          document.getElementById('sName').textContent = s.name||('#'+serverId);
          document.getElementById('sBase').textContent = base;
        }catch(e){}
      }

      async function run(){
        const msg = document.getElementById('runMsg');
        msg.textContent = 'Executando...';
        const types = [];
        if(document.getElementById('tPackages').checked) types.push('packages');
        if(document.getElementById('tSec').checked) types.push('security_baseline');
        if(document.getElementById('tNet').checked) types.push('network');
        if(document.getElementById('tVuln').checked) types.push('vulnerabilities');
        if(types.length===0){ msg.textContent = 'Selecione ao menos uma análise.'; return; }
        const pass = document.getElementById('sshPass').value||'';
        try{
          const csrf = document.querySelector('#fRun input[name=csrf]').value||'';
          const data = new URLSearchParams({action:'server_analyze', csrf: csrf, server_id:String(serverId), types:types.join(','), ssh_pass:pass});
          const d = await api('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data});
          if(!d.ok){ msg.textContent = 'Erro: '+(d.error||'falha'); return; }
          msg.textContent = 'Concluído';
          window.lastResults = d;
          // render
          if(d.results.packages){ document.getElementById('boxPackages').classList.remove('d-none'); document.getElementById('outPackages').textContent = d.results.packages; }
          if(d.results.security_baseline){ document.getElementById('boxSec').classList.remove('d-none'); document.getElementById('outSec').textContent = d.results.security_baseline; }
          if(d.results.network){ document.getElementById('boxNet').classList.remove('d-none'); document.getElementById('outNet').textContent = d.results.network; }
          if(d.results.vulnerabilities){ document.getElementById('boxVuln').classList.remove('d-none'); document.getElementById('outVuln').textContent = d.results.vulnerabilities; }
        }catch(e){ msg.textContent = 'Falha na chamada'; }
      }

      document.getElementById('btnRun').addEventListener('click', (ev)=>{ ev.preventDefault(); run(); });
      loadServer();

      async function harden(){
        const msg = document.getElementById('hardMsg');
        msg.textContent = 'Executando...';
        const checks = Array.from(document.querySelectorAll('#fHard .form-check-input[type=checkbox]'));
        const sels = checks.filter(c=>c.checked).map(c=>c.value);
        if (sels.length===0){ msg.textContent='Selecione ao menos uma ação.'; return; }
        const pass = document.getElementById('sshPassH').value||'';
        const dry = document.getElementById('dryRun').checked;
        const confirmTxt = document.getElementById('confirmTxt').value||'';
        try{
          const csrf = document.querySelector('#fHard input[name=csrf]').value||'';
          const data = new URLSearchParams({action:'server_harden', csrf: csrf, server_id:String(serverId), actions:sels.join(','), ssh_pass:pass, dry_run: dry? '1':'0', confirm: confirmTxt});
          const d = await api('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data});
          if(!d.ok){ msg.textContent = 'Erro: '+(d.error||'falha'); return; }
          msg.textContent = d.dry_run ? 'Plano gerado (dry-run)' : 'Concluído';
          const out = [];
          (d.plan||[]).forEach(p=>{ out.push('# '+(p.action||'')+'\n'+(p.cmd||'(sem comando)')); });
          const outs = d.outputs||{}; Object.keys(outs).forEach(k=>{ out.push('\n$ '+k+'\n'+(outs[k]||'')); });
          document.getElementById('hardOut').textContent = out.join('\n\n');
          document.getElementById('hardOutWrap').classList.remove('d-none');
        }catch(e){ msg.textContent='Falha na chamada'; }
      }
      document.getElementById('btnHarden').addEventListener('click', (ev)=>{ ev.preventDefault(); harden(); });
      document.getElementById('btnExport').addEventListener('click', (ev)=>{ ev.preventDefault(); try{ const data = window.lastResults || {}; const blob = new Blob([JSON.stringify(data,null,2)], {type:'application/json'}); const url = URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download='server_analysis_'+serverId+'.json'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);}catch(e){} });
      document.getElementById('btnExportPdf').addEventListener('click', async (ev)=>{ ev.preventDefault(); try{ const data = window.lastResults || {}; const csrf = document.querySelector('#fRun input[name=csrf]').value||''; const body = new URLSearchParams({action:'server_analysis_pdf', csrf: csrf, server_id: String(serverId), payload: JSON.stringify(data)}); const resp = await fetch('helpdesk_api.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body}); if(!resp.ok){ alert('Falha ao gerar PDF'); return; } const blob = await resp.blob(); const url = URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download='server_analysis_'+serverId+'.pdf'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);}catch(e){ alert('Falha ao gerar PDF'); } });
      </script>
  </body>
</html>
