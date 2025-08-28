<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

/* Admin-only gate (uses your existing role in the session) */
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied. Admins only.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>
<div class="mt-4">
  <!-- Module Tabs -->
  <ul class="nav nav-tabs mb-3" id="module-tabs">
    <li class="nav-item">
      <button class="nav-link" id="tab-sales" data-module="sales">Sales</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" id="tab-recruiting" data-module="recruiting">Recruiting</button>
    </li>
  </ul>

  <h2>KPI Goals (<span id="mod-label">Sales</span>)</h2>
  <p class="text-muted">Manage agency defaults and user overrides for the selected module. Bulk push and copy supported. Audit log below.</p>

  <!-- Filters + Actions -->
  <div class="d-flex gap-2 align-items-end mb-3">
    <div>
      <label class="form-label mb-1">User</label>
      <select id="f-user" class="form-select">
        <option value="">All</option>
        <option value="null">Agency Default</option>
      </select>
    </div>
    <div>
      <label class="form-label mb-1">Metric</label>
      <select id="f-metric" class="form-select">
        <option value="">All</option>
        <!-- options populated by JS per module -->
      </select>
    </div>
    <div>
      <label class="form-label mb-1">Period</label>
      <select id="f-period" class="form-select">
        <option value="">All</option>
        <option>daily</option><option>weekly</option><option>monthly</option>
        <option>quarterly</option><option>half_year</option><option>yearly</option>
      </select>
    </div>
    <button class="btn btn-primary" id="btn-add">Add Goal</button>
    <button class="btn btn-outline-primary" id="btn-push">Push Agency → Users</button>
    <button class="btn btn-outline-secondary" id="btn-copy">Copy User → User</button>
  </div>

  <!-- Goals table -->
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Target</th><th>Metric</th><th>Period</th><th>Goal</th><th></th>
        </tr>
      </thead>
      <tbody id="rows"><tr><td colspan="5" class="text-muted">Loading…</td></tr></tbody>
    </table>
  </div>

  <!-- Audit table -->
  <h6 class="mt-4">Audit (recent)</h6>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Metric</th><th>Period</th><th>Old→New</th><th>Note</th></tr></thead>
      <tbody id="audit"><tr><td colspan="8" class="text-muted">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Modal: Add/Edit -->
<div class="modal" tabindex="-1" id="mdl-edit">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Goal</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="e-id" />
        <div class="mb-2">
          <label class="form-label">Target</label>
          <select id="e-user" class="form-select">
            <option value="null">Agency Default</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Metric</label>
          <select id="e-metric" class="form-select"><!-- populated by JS --></select>
        </div>
        <div class="mb-2">
          <label class="form-label">Period</label>
          <select id="e-period" class="form-select">
            <option>daily</option><option>weekly</option><option>monthly</option>
            <option>quarterly</option><option>half_year</option><option>yearly</option>
          </select>
        </div>
        <div>
          <label class="form-label">Goal</label>
          <input id="e-goal" type="number" min="0" class="form-control" />
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger me-auto d-none" id="btn-del">Delete</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="btn-save">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Push -->
<div class="modal" tabindex="-1" id="mdl-push">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Push Agency → Users</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Metric</label>
          <select id="p-metric" class="form-select"><!-- populated by JS --></select>
        </div>
        <div class="mb-2">
          <label class="form-label">Period</label>
          <select id="p-period" class="form-select">
            <option>daily</option><option>weekly</option><option>monthly</option>
            <option>quarterly</option><option>half_year</option><option>yearly</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Mode</label>
          <select id="p-mode" class="form-select">
            <option value="full">Full Copy</option>
            <option value="even">Even Split</option>
            <option value="manual">Manual Assign</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Users</label>
          <div id="p-users" class="form-check" style="max-height:220px; overflow:auto;"></div>
          <div class="small text-muted">Manual values (if mode = Manual): enter numbers next to each user.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="btn-push-run">Run Push</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Copy -->
<div class="modal" tabindex="-1" id="mdl-copy">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Copy Goals From User</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Source User</label>
          <select id="c-source" class="form-select"></select>
        </div>
        <div class="mb-2">
          <label class="form-label">Target Users</label>
          <div id="c-users" class="form-check" style="max-height:220px; overflow:auto;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="btn-copy-run">Copy</button>
      </div>
    </div>
  </div>
</div>

<script>
const $ = s => document.querySelector(s);

/* Module + metric catalogs */
const METRICS = {
  sales: ['leads_added','contact_attempts','conversations','agreements_signed','job_orders_received'],
  recruiting: ['contact_attempts','conversations','submittals','interviews','offers_made','hires']
};

let MODULE = (new URLSearchParams(location.search).get('module') || 'sales').toLowerCase();
if (!['sales','recruiting'].includes(MODULE)) MODULE = 'sales';

let USERS = [];
let DEFAULTS = {};

function updateModuleUI(){
  // tabs
  document.getElementById('tab-sales').classList.toggle('active', MODULE==='sales');
  document.getElementById('tab-recruiting').classList.toggle('active', MODULE==='recruiting');

  // title chip
  $('#mod-label').textContent = MODULE === 'sales' ? 'Sales' : 'Recruiting';

  // rebuild metric dropdowns
  populateMetricSelect($('#f-metric'), true);   // include "All"
  populateMetricSelect($('#e-metric'), false);  // no "All"
  populateMetricSelect($('#p-metric'), false);  // no "All"
}

/** populate a <select> with metrics for current MODULE */
function populateMetricSelect(sel, includeAll){
  const current = sel.value;  // try to preserve selection if still valid
  sel.innerHTML = '';
  if (includeAll) {
    const o = document.createElement('option');
    o.value = ''; o.textContent = 'All';
    sel.appendChild(o);
  }
  METRICS[MODULE].forEach(m=>{
    const o = document.createElement('option');
    o.value = m; o.textContent = m;
    sel.appendChild(o);
  });
  // restore selection if still valid
  if (current && (includeAll ? (current==='' || METRICS[MODULE].includes(current)) : METRICS[MODULE].includes(current))) {
    sel.value = current;
  } else {
    sel.selectedIndex = 0; // default to first (or All for filter)
  }
}

async function loadGoals(){
  const u = $('#f-user').value, m = $('#f-metric').value, p = $('#f-period').value;
  const url = new URL('../ajax/kpi_goals_list.php', location.href);
  url.searchParams.set('module', MODULE);
  if (u) url.searchParams.set('user_id', u);
  if (m) url.searchParams.set('metric', m);
  if (p) url.searchParams.set('period', p);
  const res = await fetch(url, {credentials:'same-origin'});
  const json = await res.json();
  USERS = json.users || [];
  DEFAULTS = json.defaults || {};
  renderUserDropdowns();
  renderRows(json.items || []);
  loadAudit();
}

function renderUserDropdowns(){
  const f = $('#f-user'), e = $('#e-user'), c = $('#c-source');

  // Preserve first TWO options in the FILTER ("All", "Agency Default")
  const fKeep = Array.from(f.querySelectorAll('option')).slice(0, 2);
  f.innerHTML = '';
  fKeep.forEach(o => f.appendChild(o));

  // Preserve first option in EDIT ("Agency Default")
  const eKeep = e.querySelector('option');
  e.innerHTML = '';
  if (eKeep) e.appendChild(eKeep);

  // Add users to Filter + Edit + Copy Source
  const addUserOpt = (sel) => {
    USERS.forEach(u=>{
      const opt = document.createElement('option');
      opt.value = u.id; opt.textContent = u.full_name;
      sel.appendChild(opt);
    });
  };
  addUserOpt(f);
  addUserOpt(e);

  c.innerHTML = '';
  addUserOpt(c);

  // Bulk lists
  const pu = $('#p-users'); pu.innerHTML='';
  const cu = $('#c-users'); cu.innerHTML='';
  USERS.forEach(u=>{
    pu.insertAdjacentHTML('beforeend',
      `<div class="d-flex align-items-center gap-2 mb-1">
         <input class="form-check-input" type="checkbox" value="${u.id}" id="p-u-${u.id}">
         <label class="form-check-label" for="p-u-${u.id}">${u.full_name}</label>
         <input class="form-control form-control-sm ms-auto d-none" style="width:120px" type="number" id="p-val-${u.id}" min="0" placeholder="manual">
       </div>`);
    cu.insertAdjacentHTML('beforeend',
      `<div class="form-check">
         <input class="form-check-input" type="checkbox" value="${u.id}" id="c-u-${u.id}">
         <label class="form-check-label" for="c-u-${u.id}">${u.full_name}</label>
       </div>`);
  });

  // Toggle manual inputs (no duplicate listeners)
  document.getElementById('p-mode').onchange = ()=>{
    const manual = $('#p-mode').value==='manual';
    USERS.forEach(u=>{
      const el = document.getElementById('p-val-'+u.id);
      if (manual) el.classList.remove('d-none'); else el.classList.add('d-none');
    });
  };
}

function renderRows(items){
  const tb = $('#rows'); tb.innerHTML='';
  if (!items.length){ tb.innerHTML = '<tr><td colspan="5" class="text-muted">No goals yet</td></tr>'; return; }
  items.forEach(r=>{
    const tgt = r.user_id===null ? 'Agency Default' : (USERS.find(u=>+u.id===+r.user_id)?.full_name || 'User '+r.user_id);
    const defKey = r.metric+'|'+r.period;
    const isOverride = r.user_id!==null && (defKey in DEFAULTS);
    tb.insertAdjacentHTML('beforeend', `
      <tr>
        <td>${tgt} ${isOverride?'<span class="badge text-bg-warning ms-1">overrides default</span>':''}</td>
        <td><code>${r.metric}</code></td>
        <td>${r.period}</td>
        <td>${r.goal}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary" onclick='editRow(${JSON.stringify(r)})'>Edit</button>
        </td>
      </tr>`);
  });
}

function editRow(r){
  $('#e-id').value = r?.id || '';
  const valOrNull = v => (v===null ? 'null' : String(v));
  $('#e-user').value = valOrNull(r?.user_id ?? 'null');

  // ensure e-metric options match MODULE, then set value (default to first metric)
  populateMetricSelect($('#e-metric'), false);
  const defaultMetric = METRICS[MODULE][0];
  $('#e-metric').value = r?.metric && METRICS[MODULE].includes(r.metric) ? r.metric : defaultMetric;

  $('#e-period').value = r?.period || 'daily';
  $('#e-goal').value = r?.goal ?? 0;
  document.getElementById('btn-del').classList.toggle('d-none', !r?.id);
  new bootstrap.Modal('#mdl-edit').show();
}

async function saveGoal(){
  const payload = {
    module: MODULE,
    id: $('#e-id').value ? +$('#e-id').value : undefined,
    user_id: $('#e-user').value==='null' ? null : +$('#e-user').value,
    metric: $('#e-metric').value,
    period: $('#e-period').value,
    goal: +$('#e-goal').value
  };
  const res = await fetch('../ajax/kpi_goal_upsert.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
  if (!res.ok){ const j=await res.json().catch(()=>({})); alert(j.error||'Save failed'); return; }
  bootstrap.Modal.getInstance($('#mdl-edit')).hide();
  loadGoals();
}

async function deleteGoal(){
  const id = +$('#e-id').value;
  if (!id) return;
  if (!confirm('Delete this goal?')) return;
  const res = await fetch('../ajax/kpi_goal_delete.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json'}, body: JSON.stringify({id, module: MODULE})});
  if (!res.ok){ const j=await res.json().catch(()=>({})); alert(j.error||'Delete failed'); return; }
  bootstrap.Modal.getInstance($('#mdl-edit')).hide();
  loadGoals();
}

async function runPush(){
  const metric = $('#p-metric').value, period = $('#p-period').value, mode = $('#p-mode').value;
  const user_ids = USERS.filter(u=>document.getElementById('p-u-'+u.id).checked).map(u=>+u.id);
  const manual = {};
  if (mode==='manual'){
    USERS.forEach(u=>{
      const el = document.getElementById('p-val-'+u.id);
      if (el && !el.classList.contains('d-none')) manual[u.id] = +el.value || 0;
    });
  }
  const res = await fetch('../ajax/kpi_goals_bulk_push.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({module: MODULE, metric, period, mode, user_ids, manual})});
  const json = await res.json().catch(()=>({}));
  if (!res.ok){ alert(json.error||'Push failed'); return; }
  bootstrap.Modal.getInstance($('#mdl-push')).hide();
  loadGoals();
}

async function runCopy(){
  const source_user = +$('#c-source').value;
  const target_users = USERS.filter(u=>document.getElementById('c-u-'+u.id).checked).map(u=>+u.id);
  const res = await fetch('../ajax/kpi_goals_copy.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({module: MODULE, source_user, target_users})});
  const json = await res.json().catch(()=>({}));
  if (!res.ok){ alert(json.error||'Copy failed'); return; }
  bootstrap.Modal.getInstance($('#mdl-copy')).hide();
  loadGoals();
}

async function loadAudit(){
  // Global audit (module-agnostic). If you later add module to the audit table, thread it here.
  const res = await fetch('../ajax/kpi_goal_audit_list.php', {credentials:'same-origin'});
  const json = await res.json();
  const tb = $('#audit'); tb.innerHTML='';
  (json.items||[]).forEach(a=>{
    tb.insertAdjacentHTML('beforeend', `
      <tr>
        <td>${a.changed_at}</td>
        <td>${a.changed_by_name || a.changed_by}</td>
        <td>${a.action}</td>
        <td>${a.target_user_name || (a.target_user_id===null?'Agency Default':'User '+a.target_user_id)}</td>
        <td><code>${a.metric}</code></td>
        <td>${a.period}</td>
        <td>${a.old_goal ?? '—'} → <strong>${a.new_goal ?? '—'}</strong></td>
        <td>${a.note || ''}</td>
      </tr>`);
  });
  if (!json.items?.length) tb.innerHTML='<tr><td colspan="8" class="text-muted">No audit entries yet</td></tr>';
}

/* Event wiring */
$('#btn-add').addEventListener('click', ()=> editRow(null));
$('#btn-save').addEventListener('click', saveGoal);
$('#btn-del').addEventListener('click', deleteGoal);
$('#btn-push').addEventListener('click', ()=>{
  populateMetricSelect($('#p-metric'), false);
  new bootstrap.Modal('#mdl-push').show();
});
$('#btn-push-run').addEventListener('click', runPush);
$('#btn-copy').addEventListener('click', ()=> {
  const src = $('#c-source'); src.innerHTML='';
  USERS.forEach(u=>{ const o=document.createElement('option'); o.value=u.id; o.textContent=u.full_name; src.appendChild(o); });
  new bootstrap.Modal('#mdl-copy').show();
});
['#f-user','#f-metric','#f-period'].forEach(s=> $(s).addEventListener('change', loadGoals));

/* Module tab clicks */
document.getElementById('tab-sales').addEventListener('click', ()=>{
  if (MODULE==='sales') return;
  MODULE = 'sales';
  const url = new URL(location.href); url.searchParams.set('module','sales'); history.replaceState({},'',url);
  updateModuleUI();
  loadGoals();
});
document.getElementById('tab-recruiting').addEventListener('click', ()=>{
  if (MODULE==='recruiting') return;
  MODULE = 'recruiting';
  const url = new URL(location.href); url.searchParams.set('module','recruiting'); history.replaceState({},'',url);
  updateModuleUI();
  loadGoals();
});

/* Initial load */
document.addEventListener('DOMContentLoaded', ()=>{
  updateModuleUI();
  populateMetricSelect($('#f-metric'), true);
  populateMetricSelect($('#e-metric'), false);
  populateMetricSelect($('#p-metric'), false);
  loadGoals();
});
if (document.readyState!=='loading') {
  updateModuleUI();
  populateMetricSelect($('#f-metric'), true);
  populateMetricSelect($('#e-metric'), false);
  populateMetricSelect($('#p-metric'), false);
  loadGoals();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
