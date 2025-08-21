<?php
// includes/kpi_card.php
// KPI/Quota card that fetches ajax/kpi_summary.php and renders:
// 1) A Today ribbon (daily targets) and
// 2) The timeframe table (You vs Agency) with progress bars.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
  .kpi-chip { border-radius: 999px; padding: .35rem .65rem; background: var(--bs-light); border: 1px solid var(--bs-border-color); }
  .kpi-chip .mini { font-size: .75rem; opacity: .8; }
  .kpi-mini-bar { height: 6px; border-radius: 4px; background: var(--bs-secondary-bg); overflow: hidden; }
  .kpi-mini-bar > div { height: 6px; }

  /* Ribbon group labels */
  .kpi-group-label { font-weight: 600; font-size: .9rem; padding: .25rem .5rem; border-radius: .5rem; margin: .25rem 0 .25rem .25rem; display: inline-block; }
  .kpi-group-label.recruiting { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); border: 1px solid var(--bs-primary-border-subtle); }
  .kpi-group-label.sales      { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); border: 1px solid var(--bs-success-border-subtle); }

  /* Chip color-coding */
  .kpi-chip.recruiting { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); border-color: var(--bs-primary-border-subtle); }
  .kpi-chip.sales      { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); border-color: var(--bs-success-border-subtle); }

  /* Table section heading (sticky) + domain color coding */
  .kpi-section-heading { position: sticky; top: 0; z-index: 1; }
  .kpi-section-heading.recruiting { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
  .kpi-section-heading.sales      { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
  .kpi-section-heading .pill {
    display:inline-block; padding:.15rem .5rem; border-radius:999px; font-weight:600; border:1px solid transparent;
  }
  .kpi-section-heading.recruiting .pill { border-color: var(--bs-primary-border-subtle); }
  .kpi-section-heading.sales .pill      { border-color: var(--bs-success-border-subtle); }
</style>

<div class="card mb-4" id="kpi-card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div>
      <strong>KPI / Quota Tracker</strong>
      <div class="text-muted small">Personal vs Agency • Monday–Sunday weeks</div>
    </div>
  </div>

  <!-- TODAY RIBBON -->
  <div class="px-3 pt-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="small text-muted" id="kpi-today-range">Today:</div>
      <div class="small text-muted">Goals reset daily</div>
    </div>
    <div class="row g-2" id="kpi-today-ribbon"></div>
  </div>
  <hr class="my-3">

  <div class="card-body">
    <div id="kpi-errors" class="alert alert-danger d-none"></div>

    <!-- Legend + Timeframe selector -->
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

  // Labels aligned to backend keys (Recruiting + Sales) — all PLURAL
  const LABELS = {
    // Recruiting
    contact_attempts:  'Contact Attempts',
    conversations:     'Conversations',
    submittals:        'Submittals',
    interviews:        'Interviews',
    offers_made:       'Offers Made',
    hires:             'Hires',

    // Sales
    opportunities_identified: 'Opportunities Identified',
    meetings:                 'Meetings',
    agreements_signed:        'Agreements Signed',
    job_orders_received:      'Job Orders Received'
  };

  // Grouping for the main table (reads from the correct domain source)
  const GROUPS = [
    { title: 'Recruiting Metrics', domain: 'recruiting', order: ['contact_attempts','conversations','submittals','interviews','offers_made','hires'] },
    { title: 'Sales Metrics',      domain: 'sales',      order: ['contact_attempts','conversations','opportunities_identified','meetings','agreements_signed','job_orders_received'] }
  ];

  // Today ribbon: show selected daily targets
  const RIBBON_RECRUITING_DAILY = ['contact_attempts','conversations','submittals'];
  const RIBBON_SALES_DAILY      = ['contact_attempts','conversations','opportunities_identified'];

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
    if (!g || g <= 0) return '<span class="badge text-bg-secondary">No Goal</span>';
    const p = pct(v,g);
    if (p >= 100) return '<span class="badge text-bg-success">Ahead</span>';
    if (p >= 80)  return '<span class="badge text-bg-warning">On Track</span>';
    return '<span class="badge text-bg-danger">Behind</span>';
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

  function renderMetricRow(key, data){
    const youC = data?.you?.count ?? 0, youG = data?.you?.goal ?? 0;
    const agC  = data?.agency?.count ?? 0, agG  = data?.agency?.goal ?? 0;
    const youP = pct(youC, youG), agP = pct(agC, agG);

    const lbl = LABELS[key] || key;
    return `
      <tr>
        <td class="text-nowrap">${lbl}</td>
        <td>
          <div class="progress"><div class="progress-bar ${barClass(youC,youG)}" style="width:${youP}%"></div></div>
        </td>
        <td class="text-nowrap">${fmtCountGoal(youC,youG)} · ${youP}%</td>
        <td>
          <div class="progress"><div class="progress-bar bg-secondary" style="width:${agP}%"></div></div>
        </td>
        <td class="text-nowrap">${fmtCountGoal(agC,agG)} · ${agP}%</td>
        <td class="text-end">${statusBadge(youC,youG)}</td>
      </tr>
    `;
  }

  function renderTodayChip(key, todayMetric, domain){
    const label = LABELS[key] || key;
    const youC = todayMetric?.you?.count ?? 0;
    const youG = todayMetric?.you?.goal ?? 0;
    const rem  = youG>0 ? Math.max(0, youG - youC) : 0;
    const p    = pct(youC, youG);
    const cls  = domain === 'recruiting' ? 'recruiting' : 'sales';
    return `
      <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        <div class="kpi-chip ${cls}">
          <div class="d-flex justify-content-between">
            <div class="fw-semibold">${label}</div>
            <div class="mini">${youC} / ${youG||'—'}</div>
          </div>
          <div class="kpi-mini-bar"><div class="${barClass(youC,youG)}" style="width:${p}%"></div></div>
          <div class="mini">Remaining: <strong>${rem}</strong></div>
        </div>
      </div>
    `;
  }

  async function loadKPI(){
    elErr.classList.add('d-none'); elErr.textContent='';
    elRows.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>`;
    elTodayRibbon.innerHTML = `<div class="col-12"><div class="text-muted small">Loading today’s targets…</div></div>`;
    try {
      const tf = elTF.value;
      const res = await fetch('../ajax/kpi_summary.php?tf='+encodeURIComponent(tf),{credentials:'same-origin'});
      if (!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      if (json.error) throw new Error(json.error);

      // Ranges
      const start=json.start?.replace(' 00:00:00',''), end=json.end?.replace(' 00:00:00','');
      elRange.textContent = start&&end ? `${tfLabel(json.timeframe||tf)}: ${start} → ${end}` : tfLabel(json.timeframe||tf);
      const tStart=json.today?.start?.replace(' 00:00:00',''), tEnd=json.today?.end?.replace(' 00:00:00','');
      elTodayRange.textContent = tStart&&tEnd ? `Today: ${tStart} → ${tEnd}` : 'Today:';

      // Today ribbon — grouped + color-coded
      let chips='';
      // Recruiting header + daily chips
      chips += `<div class="col-12"><span class="kpi-group-label recruiting">Recruiting</span></div>`;
      RIBBON_RECRUITING_DAILY.forEach(k=>{
        const src = json.today?.metrics; // recruiting
        if (src?.[k]) chips += renderTodayChip(k, src[k], 'recruiting');
      });
      // Sales header + daily chips
      chips += `<div class="col-12"><span class="kpi-group-label sales">Sales</span></div>`;
      RIBBON_SALES_DAILY.forEach(k=>{
        const src = json.today?.sales_metrics; // sales
        if (src?.[k]) chips += renderTodayChip(k, src[k], 'sales');
      });
      elTodayRibbon.innerHTML = chips || `<div class="col-12"><div class="text-muted small">No activity yet today.</div></div>`;

      // Main table — render recruiting from json.metrics, sales from json.sales_metrics
      let html='';
      GROUPS.forEach(group=>{
        const src = group.domain === 'sales' ? (json.sales_metrics || {}) : (json.metrics || {});
        const hasAny = group.order.some(k=>src[k]);
        if(!hasAny) return;
        const domainCls = group.domain === 'recruiting' ? 'recruiting' : 'sales';
        html += `<tr class="kpi-section-heading ${domainCls}"><td colspan="6"><span class="pill">${group.title}</span></td></tr>`;
        group.order.forEach(k=>{
          if(src[k]) html+=renderMetricRow(k, src[k]);
        });
      });
      elRows.innerHTML = html || '<tr><td colspan="6" class="text-center text-muted py-4">No data</td></tr>';
    } catch(e){
      elErr.textContent = 'Error loading KPI data: '+e.message;
      elErr.classList.remove('d-none');
      elRows.innerHTML=''; elTodayRibbon.innerHTML=`<div class="col-12"><div class="text-danger small">Failed to load today’s targets.</div></div>`;
    }
  }

  elTF.addEventListener('change', loadKPI);
  document.addEventListener('DOMContentLoaded', loadKPI);
  if(document.readyState!=='loading') loadKPI();
})();
</script>
