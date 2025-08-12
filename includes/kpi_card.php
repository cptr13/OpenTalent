<?php
// includes/kpi_card.php (polished + Today ribbon, selector moved next to “You vs Agency”)
// KPI/Quota card that fetches ajax/kpi_summary.php and renders:
// 1) A Today ribbon (daily targets) and
// 2) The timeframe table (You vs Agency) with progress bars.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
  /* tiny helpers for chips */
  .kpi-chip { border-radius: 999px; padding: .35rem .65rem; background: var(--bs-light); }
  .kpi-chip .mini { font-size: .75rem; opacity: .8; }
  .kpi-mini-bar { height: 6px; border-radius: 4px; background: var(--bs-secondary-bg); overflow: hidden; }
  .kpi-mini-bar > div { height: 6px; }
</style>

<div class="card mb-4" id="kpi-card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div>
      <strong>KPI / Quota Tracker</strong>
      <div class="text-muted small">Personal vs Agency • Monday–Sunday weeks</div>
    </div>
    <!-- (Selector moved out of header to legend area) -->
  </div>

  <!-- TODAY RIBBON -->
  <div class="px-3 pt-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="small text-muted" id="kpi-today-range">Today:</div>
      <div class="small text-muted">Goals reset daily</div>
    </div>
    <div class="row g-2" id="kpi-today-ribbon">
      <!-- chips injected -->
    </div>
  </div>
  <hr class="my-3">

  <div class="card-body">
    <div id="kpi-errors" class="alert alert-danger d-none"></div>

    <!-- Legend + Timeframe selector (moved here) -->
    <div class="d-flex align-items-center flex-wrap gap-3 mb-3 small text-muted">
      <span><span class="badge text-bg-success">Ahead</span> ≥100%</span>
      <span><span class="badge text-bg-warning">On Track</span> 80–99%</span>
      <span><span class="badge text-bg-danger">Behind</span> &lt;80%</span>
      <span><span class="badge text-bg-secondary">No Goal</span> goal=0</span>

      <span class="ms-auto d-flex align-items-center gap-2">
        <span>You vs <span class="text-secondary">Agency</span></span>
        <span class="text-muted small" id="kpi-range"></span>
        <select id="kpi-tf" class="form-select form-select-sm" aria-label="Timeframe">
          <option value="today">Today</option>
          <option value="week" selected>This Week</option>
          <option value="month">This Month</option>
          <option value="qtr">3 Months</option>
          <option value="half">6 Months</option>
          <option value="year">Year</option>
        </select>
      </span>
    </div>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Metric</th>
            <th style="width:52%">You</th>
            <th class="text-nowrap">You: <span class="text-muted small">count / goal</span></th>
            <th style="width:23%">Agency</th>
            <th class="text-nowrap">Agency: <span class="text-muted small">count / goal</span></th>
            <th class="text-end">Status</th>
          </tr>
        </thead>
        <tbody id="kpi-rows">
          <tr>
            <td colspan="6" class="py-4">
              <div class="placeholder-glow">
                <span class="placeholder col-12 mb-2"></span>
                <span class="placeholder col-10 mb-2"></span>
                <span class="placeholder col-8"></span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  const elTF = document.getElementById('kpi-tf');
  const elRows = document.getElementById('kpi-rows');
  const elErr = document.getElementById('kpi-errors');
  const elRange = document.getElementById('kpi-range');
  const elTodayRange = document.getElementById('kpi-today-range');
  const elTodayRibbon = document.getElementById('kpi-today-ribbon');

  const ORDER = ['contact_attempt','conversation','submittal','interview','placement'];
  const LABELS = {
    contact_attempt: 'Contact Attempts',
    conversation: 'Conversations',
    submittal: 'Submittals',
    interview: 'Interviews',
    placement: 'Placements'
  };

  function clip(n){ return Math.max(0, Math.min(100, Math.round(n))); }
  function pct(v,g){ if(!g || g<=0) return (v>0?100:0); return clip((v/g)*100); }
  function tfLabel(tf){
    switch(tf){
      case 'today': return 'Today';
      case 'week':  return 'This Week';
      case 'month': return 'This Month';
      case 'qtr':   return 'This Quarter';
      case 'half':  return 'This Half';
      case 'year':  return 'This Year';
      default:      return 'This Period';
    }
  }
  function statusBadge(v,g){
    if (!g || g <= 0) return '<span class="badge text-bg-secondary" title="No goal set for this period.">No Goal</span>';
    const p = pct(v,g);
    if (p >= 100) return '<span class="badge text-bg-success" title="Goal met or exceeded.">Ahead</span>';
    if (p >= 80)  return '<span class="badge text-bg-warning" title="Within striking distance.">On Track</span>';
    return '<span class="badge text-bg-danger" title="Below pace for the period.">Behind</span>';
  }
  function barClass(v,g){
    if (!g || g <= 0) return 'bg-secondary';
    const p = pct(v,g);
    if (p >= 100) return 'bg-success';
    if (p >= 80)  return 'bg-warning';
    return 'bg-danger';
  }
  function fmtCountGoal(v,g){
    if (!g || g <= 0) return `${v} / —`;
    const over = Math.max(0, v - g);
    return over > 0 ? `${v} / ${g} (+${over})` : `${v} / ${g}`;
  }

  function renderMetricRow(key, data, tfLbl, range){
    const youC = data.you?.count ?? 0, youG = data.you?.goal ?? 0;
    const agC  = data.agency?.count ?? 0, agG  = data.agency?.goal ?? 0;
    const youP = pct(youC, youG), agP = pct(agC, agG);

    const youTitle = `You — ${LABELS[key]||key}\n${tfLbl}: ${range}\n${youC} of ${youG || '—'} (${youP}%)`;
    const agTitle  = `Agency — ${LABELS[key]||key}\n${tfLbl}: ${range}\n${agC} of ${agG || '—'} (${agP}%)`;

    return `
      <tr>
        <td class="text-nowrap">${LABELS[key] || key}</td>

        <td>
          <div class="progress" role="progressbar" aria-valuenow="${youP}" aria-valuemin="0" aria-valuemax="100" title="${youTitle}">
            <div class="progress-bar ${barClass(youC, youG)}" style="width:${youP}%"></div>
          </div>
        </td>
        <td class="text-nowrap" title="${youTitle}">${fmtCountGoal(youC, youG)} · ${youP}%</td>

        <td>
          <div class="progress" role="progressbar" aria-valuenow="${agP}" aria-valuemin="0" aria-valuemax="100" title="${agTitle}">
            <div class="progress-bar bg-secondary" style="width:${agP}%"></div>
          </div>
        </td>
        <td class="text-nowrap" title="${agTitle}">${fmtCountGoal(agC, agG)} · ${agP}%</td>

        <td class="text-end">${statusBadge(youC, youG)}</td>
      </tr>
    `;
  }

  function chipBarClass(p){
    if (p >= 100) return 'bg-success';
    if (p >= 80)  return 'bg-warning';
    return 'bg-danger';
  }
  function renderTodayChip(key, todayMetric){
    const label = LABELS[key] || key;
    const youC = todayMetric?.you?.count ?? 0;
    const youG = todayMetric?.you?.goal ?? 0;
    const rem  = todayMetric?.you?.remaining ?? 0;
    const p    = pct(youC, youG);
    return `
      <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        <div class="kpi-chip d-flex flex-column gap-1" title="${label} — Today\n${youC} of ${youG || '—'} (${p}%)\nRemaining: ${rem}">
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-semibold">${label}</div>
            <div class="mini">${youC} / ${youG || '—'} ${youG>0 && youC>youG ? `(+${youC-youG})` : ''}</div>
          </div>
          <div class="kpi-mini-bar">
            <div class="${chipBarClass(p)}" style="width:${p}%;"></div>
          </div>
          <div class="mini">Remaining: <strong>${rem}</strong></div>
        </div>
      </div>
    `;
  }

  async function loadKPI(){
    elErr.classList.add('d-none');
    elErr.textContent = '';
    elRows.innerHTML = `
      <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
    `;
    elTodayRibbon.innerHTML = `
      <div class="col-12"><div class="text-muted small">Loading today’s targets…</div></div>
    `;
    try {
      const tf = elTF.value;
      const res = await fetch('../ajax/kpi_summary.php?tf=' + encodeURIComponent(tf), { credentials: 'same-origin' });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      if (json.error) throw new Error(json.error);

      // Range labels
      const start = json.start?.replace(' 00:00:00','');
      const end   = json.end?.replace(' 00:00:00','');
      const range = (start && end) ? `${start} → ${end}` : '';
      elRange.textContent = range ? `${tfLabel(json.timeframe||tf)}: ${range}` : tfLabel(json.timeframe||tf);

      // Today ribbon
      const tStart = json.today?.start?.replace(' 00:00:00','');
      const tEnd   = json.today?.end?.replace(' 00:00:00','');
      const tRange = (tStart && tEnd) ? `${tStart} → ${tEnd}` : '';
      elTodayRange.textContent = `Today: ${tRange}`;

      let chips = '';
      ORDER.forEach(k => {
        if (json.today?.metrics?.[k]) chips += renderTodayChip(k, json.today.metrics[k]);
      });
      elTodayRibbon.innerHTML = chips || `<div class="col-12"><div class="text-muted small">No activity yet today.</div></div>`;

      // Main table rows
      let html = '';
      ORDER.forEach(k => {
        if (json.metrics && json.metrics[k]) {
          html += renderMetricRow(k, json.metrics[k], tfLabel(json.timeframe||tf), range);
        }
      });
      elRows.innerHTML = html || '<tr><td colspan="6" class="text-center text-muted py-4">No data</td></tr>';
    } catch (e) {
      elErr.textContent = 'Error loading KPI data: ' + e.message;
      elErr.classList.remove('d-none');
      elRows.innerHTML = '';
      elTodayRibbon.innerHTML = `<div class="col-12"><div class="text-danger small">Failed to load today’s targets.</div></div>`;
    }
  }

  elTF.addEventListener('change', loadKPI);
  document.addEventListener('DOMContentLoaded', loadKPI);
  if (document.readyState !== 'loading') loadKPI();
})();
</script>
