<?php
session_start();
$pdo = require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';
if (!isset($_SESSION['portal_user']['id'])) { header('Location: /portal/login.php'); exit(); }
$roles = $_SESSION['portal_user']['roles'] ?? [];
$canOperate = in_array('admin', (array)$roles, true) || in_array('helpdesk_operate', (array)$roles, true);
if (!$canOperate) { http_response_code(403); echo 'Acesso negado'; exit(); }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ferramentas do Servidor #<?= (int)$id ?> - Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>.glass{border:1px solid rgba(255,255,255,.08);background:rgba(17,25,40,.35);backdrop-filter:blur(8px)}</style>
  </head>
  <body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg glass">
      <div class="container-fluid">
        <a class="navbar-brand" href="/portal/helpdesk/index.php"><?= render_brand_html(__DIR__ . '/../../assets/logotipo.png'); ?></a> | HELP DESK | Ferramentas do Servidor #<?= (int)$id ?>
        <div class="ms-auto d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="/portal/helpdesk/servers.php">Servidores</a>
          <a class="btn btn-outline-light btn-sm" href="/portal/index.php">Portal</a>
        </div>
      </div>
    </nav>
    <main class="container py-4">
      <h1 class="h5 mb-3">Ferramentas do Servidor #<?= (int)$id ?></h1>

      <div class="row g-3">
        <div class="col-12 col-xl-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6">Limpeza / Otimização</h2>
              <form id="fClean" class="row g-2" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="server_id" value="<?= (int)$id ?>">
                <div class="col-12 small text-secondary">Selecione as opções (algumas podem exigir privilégios).</div>
                <div class="col-12 d-flex flex-wrap gap-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" value="clean_temp" id="cl1"><label class="form-check-label" for="cl1">Limpar temporários</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" value="clean_package_cache" id="cl2"><label class="form-check-label" for="cl2">Limpar cache de pacotes</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" value="clean_thumbnails" id="cl3"><label class="form-check-label" for="cl3">Limpar thumbnails</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" value="clean_logs" id="cl4"><label class="form-check-label" for="cl4">Compactar/limpar logs (admin)</label></div>
                </div>
                <div class="col-12 col-md-6"><input type="password" class="form-control" placeholder="Senha SSH (opcional)" name="ssh_pass" autocomplete="current-password"></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm" id="btnClean">Executar</button><span id="msgClean" class="small text-secondary"></span></div>
                <div class="col-12 d-none" id="outCleanWrap"><pre class="small" id="outClean"></pre></div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-6">
          <div class="card glass h-100">
            <div class="card-body">
              <h2 class="h6">Diagnóstico de Rede</h2>
              <form id="fNet" class="row g-2" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="server_id" value="<?= (int)$id ?>">
                <div class="col-12 col-md-8"><input class="form-control" name="target" placeholder="Alvo (ex.: 8.8.8.8 ou dominio.com)" value="8.8.8.8"></div>
                <div class="col-12 d-flex flex-wrap gap-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" value="dns" id="nt1"><label class="form-check-label" for="nt1">DNS</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" value="traceroute" id="nt2"><label class="form-check-label" for="nt2">Traceroute</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" value="ping_jitter" id="nt3"><label class="form-check-label" for="nt3">Ping/Jitter</label></div>
                </div>
                <div class="col-12 col-md-6"><input type="password" class="form-control" placeholder="Senha SSH (opcional)" name="ssh_pass" autocomplete="current-password"></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm" id="btnNet">Executar</button><span id="msgNet" class="small text-secondary"></span></div>
                <div class="col-12 d-none" id="outNetWrap"><pre class="small" id="outNet"></pre></div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card glass">
            <div class="card-body">
              <h2 class="h6">Playbooks Rápidos</h2>
              <form id="fPlay" class="row g-2" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="server_id" value="<?= (int)$id ?>">
                <div class="col-12 d-flex flex-wrap gap-3">
                  <button class="btn btn-outline-light btn-sm" data-name="reset_print_spooler">Resetar Spooler de Impressão</button>
                  <button class="btn btn-outline-light btn-sm" data-name="flush_dns">Flush DNS</button>
                  <button class="btn btn-outline-danger btn-sm" data-name="repair_network_stack">Reparar Stack de Rede (admin)</button>
                </div>
                <div class="col-12 col-md-6"><input type="password" class="form-control" placeholder="Senha SSH (opcional)" name="ssh_pass" autocomplete="current-password"></div>
                <div class="col-12 d-flex gap-2"><span id="msgPlay" class="small text-secondary"></span></div>
                <div class="col-12 d-none" id="outPlayWrap"><pre class="small" id="outPlay"></pre></div>
              </form>
            </div>
          </div>
        </div>

        <?php if (in_array('admin', (array)$roles, true)): ?>
        <div class="col-12">
          <div class="card glass border-danger-subtle">
            <div class="card-body">
              <h2 class="h6 text-danger">Console SSH (Admin)</h2>
              <p class="small text-warning">Perigoso: comandos executam diretamente no servidor remoto. Use somente se souber o que está fazendo.</p>
              <form id="fConsole" class="row g-2" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="server_id" value="<?= (int)$id ?>">
                <div class="col-12">
                  <textarea class="form-control" name="cmd" rows="4" placeholder="Digite o comando completo (ex.: df -h)"></textarea>
                </div>
                <div class="col-12 col-md-6"><input type="password" class="form-control" placeholder="Senha SSH (opcional)" name="ssh_pass" autocomplete="current-password"></div>
                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-danger btn-sm" id="btnConsole">Executar</button>
                  <span id="msgConsole" class="small text-secondary"></span>
                </div>
                <div class="col-12 d-none" id="outConsoleWrap"><pre class="small" id="outConsole"></pre></div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </main>

    <script>
      async function post(data){ const r=await fetch('helpdesk_api.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data)}); const ok=r.ok; let j=null; try{ j=await r.json(); }catch(e){} if(!ok) throw new Error('api'); return j||{}; }

      document.getElementById('btnClean').addEventListener('click', async (ev)=>{
        ev.preventDefault(); const f=document.getElementById('fClean'); const fd=new FormData(f);
        const checks=[...f.querySelectorAll('.form-check-input:checked')].map(x=>x.value);
        if(checks.length===0){ document.getElementById('msgClean').textContent='Selecione ao menos uma opção.'; return; }
        document.getElementById('msgClean').textContent='Executando...';
        try{
          const d=await post({action:'system_cleanup', csrf: fd.get('csrf')||'', server_id: fd.get('server_id')||'', options: checks.join(','), ssh_pass: fd.get('ssh_pass')||''});
          if(!d.ok){ document.getElementById('msgClean').textContent='Erro: '+(d.error||'falha'); return; }
          const out = d.outputs||{}; const lines=[]; Object.keys(out).forEach(k=>{ lines.push('['+k+']\n'+(out[k]||'')); });
          document.getElementById('outClean').textContent = lines.join('\n\n');
          document.getElementById('outCleanWrap').classList.remove('d-none');
          document.getElementById('msgClean').textContent='Concluído';
        }catch(e){ document.getElementById('msgClean').textContent='Falha na chamada'; }
      });

      document.getElementById('btnNet').addEventListener('click', async (ev)=>{
        ev.preventDefault(); const f=document.getElementById('fNet'); const fd=new FormData(f);
        const tests=[...f.querySelectorAll('.form-check-input:checked')].map(x=>x.value);
        if(tests.length===0){ document.getElementById('msgNet').textContent='Selecione ao menos um teste.'; return; }
        document.getElementById('msgNet').textContent='Executando...';
        try{
          const d=await post({action:'network_diag', csrf: fd.get('csrf')||'', server_id: fd.get('server_id')||'', tests: tests.join(','), target: fd.get('target')||'', ssh_pass: fd.get('ssh_pass')||''});
          if(!d.ok){ document.getElementById('msgNet').textContent='Erro: '+(d.error||'falha'); return; }
          const out = d.results||{}; const lines=[]; Object.keys(out).forEach(k=>{ lines.push('['+k+']\n'+(out[k]||'')); });
          document.getElementById('outNet').textContent = lines.join('\n\n');
          document.getElementById('outNetWrap').classList.remove('d-none');
          document.getElementById('msgNet').textContent='Concluído';
        }catch(e){ document.getElementById('msgNet').textContent='Falha na chamada'; }
      });

      document.getElementById('fPlay').addEventListener('click', async (ev)=>{
        const t = ev.target.closest('button[data-name]'); if(!t) return; ev.preventDefault();
        const f=document.getElementById('fPlay'); const fd=new FormData(f);
        document.getElementById('msgPlay').textContent='Executando '+t.dataset.name+'...';
        try{
          const d=await post({action:'quick_playbook', csrf: fd.get('csrf')||'', server_id: fd.get('server_id')||'', name: t.dataset.name, ssh_pass: fd.get('ssh_pass')||''});
          if(!d.ok){ document.getElementById('msgPlay').textContent='Erro: '+(d.error||'falha'); return; }
          document.getElementById('outPlay').textContent = d.output||'';
          document.getElementById('outPlayWrap').classList.remove('d-none');
          document.getElementById('msgPlay').textContent='Concluído';
        }catch(e){ document.getElementById('msgPlay').textContent='Falha na chamada'; }
      });

      const btnC = document.getElementById('btnConsole');
      if (btnC) {
        btnC.addEventListener('click', async (ev)=>{
          ev.preventDefault(); const f=document.getElementById('fConsole'); const fd=new FormData(f);
          const cmd=(fd.get('cmd')||'').toString().trim(); if(!cmd){ document.getElementById('msgConsole').textContent='Informe um comando.'; return; }
          document.getElementById('msgConsole').textContent='Executando...';
          try{
            const d=await post({action:'ssh_console_exec', csrf: fd.get('csrf')||'', server_id: fd.get('server_id')||'', cmd: cmd, ssh_pass: fd.get('ssh_pass')||''});
            if(!d.ok){ document.getElementById('msgConsole').textContent='Erro: '+(d.error||'falha'); return; }
            document.getElementById('outConsole').textContent = d.output||'';
            document.getElementById('outConsoleWrap').classList.remove('d-none');
            document.getElementById('msgConsole').textContent='Concluído';
          }catch(e){ document.getElementById('msgConsole').textContent='Falha na chamada'; }
        });
      }
    </script>
  </body>
</html>
