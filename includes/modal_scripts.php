<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Paths:
list($rootPrefix,) = explode('/public/', $_SERVER['SCRIPT_NAME'], 2);
$basePath = $rootPrefix . '/public/';
$rootPath = $rootPrefix . '/';
?>
<div class="modal fade" id="scriptsModal"
     tabindex="-1"
     aria-labelledby="scriptsModalLabel"
     aria-hidden="true"
     data-basepath="<?= htmlspecialchars($basePath) ?>"
     data-rootpath="<?= htmlspecialchars($rootPath) ?>">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="scriptsModalLabel" class="modal-title">Scripts &amp; Rebuttals</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <form id="scriptsSearchForm" class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" id="scriptsQuery" placeholder="fee objection, discovery, gatekeeper...">
          </div>

          <div class="col-md-3">
            <label class="form-label">Channel</label>
            <div class="d-flex flex-wrap gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="channel" id="ch_phone" value="phone" checked>
                <label class="form-check-label" for="ch_phone">Phone</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="channel" id="ch_email" value="email">
                <label class="form-check-label" for="ch_email">Email</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="channel" id="ch_linkedin" value="linkedin">
                <label class="form-check-label" for="ch_linkedin">LinkedIn</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="channel" id="ch_all" value="">
                <label class="form-check-label" for="ch_all">All</label>
              </div>
            </div>
          </div>

          <div class="col-md-2">
            <label class="form-label">Cadence Stage</label>
            <select id="scriptsStage" class="form-select">
              <option value="">Any</option>
              <?php for ($i=1; $i<=12; $i++): ?>
                <option value="<?= $i ?>"><?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Context</label>
            <select id="scriptsContext" class="form-select">
              <option value="sales" selected>Sales</option>
              <option value="recruiting">Recruiting</option>
              <option value="general">General</option>
            </select>
          </div>

          <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-primary w-100">Find</button>
          </div>
        </form>

        <div class="form-text mt-1">
          Tip: Use “All” to see Phone, Email, and LinkedIn together.
        </div>

        <hr class="my-3">

        <div id="scriptsResults" class="d-grid gap-3"></div>
      </div>

      <div class="modal-footer">
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($basePath) ?>script_editor.php">New Script</a>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// --- 🔹 Merge Placeholder Logic (with aliases) ---
function getAllMergeData() {
  // Primary source: compose page (if present)
  const compose = window.ComposeEmail || {};
  const merged = {
    ...(compose.mergeData || {}),
    ...(compose.userData || {}),
    ...(compose.recipientData || {}),
  };

  // Add a couple of generic computed fallbacks if missing
  if (!merged.today && !merged.date) {
    const pretty = new Date().toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    merged.today = merged.today || pretty;
    merged.date  = merged.date  || pretty;
  }
  return merged;
}

function mergePlaceholders(text) {
  if (!text || typeof text !== 'string') return text;

  const data = getAllMergeData();

  // Aliases → canonical keys
  const aliasMap = {
    // user aliases
    'your_name':  'user_name',
    'your_email': 'user_email',
    'your_phone': 'user_phone',
    // company/recipient aliases
    'company':        'company_name',
    'companyName':    'company_name',
    'name':           'full_name',
    'firstName':      'first_name',
    'lastName':       'last_name',
    'title':          'job_title',
    // date aliases
    'today':          'today',
    'date':           'today',
  };

  return text.replace(/{{\s*([\w\.]+)\s*}}/g, (m, key) => {
    const canon = aliasMap[key] || key;
    if (data[canon] !== undefined && data[canon] !== null) {
      return String(data[canon]);
    }
    return m; // leave unknowns as-is
  });
}

// Copy helper
function copyText(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).catch(()=>{});
  } else {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
  }
}

// Insert-to-email helper (with merge)
function insertToEmail(subject, body) {
  const mergedSubject = mergePlaceholders(subject);
  const mergedBody    = mergePlaceholders(body);

  if (window.ComposeEmail && typeof window.ComposeEmail.insertFromScript === 'function') {
    window.ComposeEmail.insertFromScript({ subject: mergedSubject, body: mergedBody });
    try {
      const inst = bootstrap.Modal.getInstance(document.getElementById('scriptsModal')) 
                || new bootstrap.Modal(document.getElementById('scriptsModal'));
      inst.hide();
    } catch(e) {}
  }
}

// Render a single card (preview shows merged values too)
function renderScriptCard(item) {
  const safe = (s) => (s ?? '');
  const stage = (item.stage !== null && item.stage !== undefined) ? `Stage ${item.stage}` : 'Any Stage';
  const tags = safe(item.tags) ? `<div class="small text-muted">Tags: ${item.tags}</div>` : '';

  const mergedContent = mergePlaceholders(safe(item.content));
  const mergedSubject = mergePlaceholders(safe(item.subject));

  const subj = (item.channel === 'email' && mergedSubject)
    ? `<div class="small">Subject: <code>${mergedSubject}</code></div>`
    : '';

  const canInsertToEmail = (item.channel === 'email') && !!(window.ComposeEmail && typeof window.ComposeEmail.insertFromScript === 'function');
  const insertBtn = canInsertToEmail
    ? `<button type="button" class="btn btn-primary btn-sm"
               onclick="insertToEmail(${JSON.stringify(mergedSubject)}, ${JSON.stringify(mergedContent)})">
         Insert to Email
       </button>`
    : '';

  return `
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h6 class="mb-1">${safe(item.title)}</h6>
            <div class="small text-muted">${safe(item.context)} · ${safe(item.channel)} · ${stage}</div>
            ${tags}
            ${subj}
          </div>
          <div class="d-flex gap-2">
            ${insertBtn}
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="copyText(document.getElementById('script-content-${item.id}').innerText)">
              Copy
            </button>
          </div>
        </div>
        <pre id="script-content-${item.id}" class="mt-3 mb-0" style="white-space:pre-wrap;">${mergedContent}</pre>
      </div>
    </div>
  `;
}

// --- Existing logic ---
function getBasePath() {
  const el = document.getElementById('scriptsModal');
  return (el && el.getAttribute('data-basepath')) ? el.getAttribute('data-basepath') : '/public/';
}
function getRootPath() {
  const el = document.getElementById('scriptsModal');
  return (el && el.getAttribute('data-rootpath')) ? el.getAttribute('data-rootpath') : '/';
}

async function loadScripts(opts = {}) {
  const {
    q = document.getElementById('scriptsQuery')?.value.trim() || '',
    context = document.getElementById('scriptsContext')?.value || 'sales',
    channel = document.querySelector('input[name="channel"]:checked')?.value ?? '',
    stage = document.getElementById('scriptsStage')?.value || ''
  } = opts;

  const params = new URLSearchParams();
  if (q) params.set('q', q);
  if (context) params.set('context', context);
  if (channel !== undefined) params.set('channel', channel);
  if (stage !== '') params.set('stage', stage);

  const target = document.getElementById('scriptsResults');
  if (target) target.innerHTML = '<div class="text-muted">Loading…</div>';

  const url = getRootPath() + 'ajax/search_scripts.php?' + params.toString();

  try {
    const res = await fetch(url, { credentials: 'same-origin' });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      const preview = text.slice(0, 400);
      throw new Error(`Non-JSON response.\nURL: ${url}\nPreview:\n${preview}`);
    }

    if (!data.ok) throw new Error(data.error || 'Query failed');

    if (!data.items || data.items.length === 0) {
      target.innerHTML = '<div class="text-muted">No scripts found. Try different filters.</div>';
      return;
    }
    target.innerHTML = data.items.map(renderScriptCard).join('');
  } catch (err) {
    if (target) target.innerHTML = `<div class="text-danger" style="white-space:pre-wrap;">Error: ${err.message}</div>`;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('scriptsSearchForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      loadScripts();
    });
  }
  const modalEl = document.getElementById('scriptsModal');
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', () => loadScripts());
  }
});

window.OpenTalentScripts = {
  open: function({context='sales', channel='phone', stage='', query=''} = {}) {
    const ctx = document.getElementById('scriptsContext');
    const stg = document.getElementById('scriptsStage');
    const qry = document.getElementById('scriptsQuery');
    const chPhone = document.getElementById('ch_phone');
    const chEmail = document.getElementById('ch_email');
    const chLinked = document.getElementById('ch_linkedin');
    const chAll = document.getElementById('ch_all');

    if (ctx) ctx.value = context;
    if (stg) stg.value = stage || '';
    if (qry) qry.value = query || '';

    if (channel === 'email' && chEmail) chEmail.checked = true;
    else if (channel === 'linkedin' && chLinked) chLinked.checked = true;
    else if (channel === '' && chAll) chAll.checked = true;
    else if (chPhone) chPhone.checked = true;

    const modal = new bootstrap.Modal(document.getElementById('scriptsModal'));
    modal.show();
  }
};
</script>
