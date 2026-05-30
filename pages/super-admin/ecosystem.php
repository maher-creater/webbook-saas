<?php
/**
 * Super-Admin · Ecosystem currencies editor (ecosystem-currencies.json).
 *
 * The ecosystem file is large + deeply nested (10 projects × 12-lang labels),
 * so this page exposes:
 *   1. a high-level form for top-level metadata (version, updated_at, credit symbol/name)
 *   2. project list (enable/disable + edit code/name/conversion rate)
 *   3. a Monaco-less raw JSON textarea for power-edits
 */
declare(strict_types=1);
$PAGE = 'ecosystem';
$TITLE = 'Ecosystem Currencies';
$JSON_FILE = 'ecosystem';
$SAVE_OP = 'admin.ecosystem.save';
$SAVE_FIELD = 'ecosystem_file';
require __DIR__ . '/_layout.php';

$eco = b30_ecosystem_cfg();
$projects = $eco['projects'] ?? [];

sa_render_header();
?>
<div class="space-y-6">
  <header class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="font-display text-2xl font-bold">Ecosystem Currencies</h1>
      <p class="text-sm text-slate-500"><code class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">ecosystem-currencies.json</code> · <?= count($projects) ?> project(s)</p>
    </div>
    <button type="button" id="raw-edit-btn" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">
      <i data-lucide="code-2" class="w-4 h-4 inline -mt-0.5"></i> Raw JSON editor
    </button>
  </header>

  <form id="eco-form" data-validate class="space-y-4" novalidate>
    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Top-level metadata</legend>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">Version</span>
          <input type="text" name="version" class="b30-input mt-1 w-full" value="<?= sa_h((string)($eco['version'] ?? '')) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Updated at</span>
          <input type="date" name="updated_at" class="b30-input mt-1 w-full" value="<?= sa_h((string)($eco['updated_at'] ?? date('Y-m-d'))) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Credit symbol</span>
          <input type="text" name="credit_symbol" class="b30-input mt-1 w-full" value="<?= sa_h((string)($eco['credit']['symbol'] ?? 'B30C')) ?>"></label>
        <label class="block sm:col-span-2 lg:col-span-3"><span class="text-sm font-medium">Credit display name</span>
          <input type="text" name="credit_name" class="b30-input mt-1 w-full" value="<?= sa_h((string)($eco['credit']['name'] ?? '')) ?>"></label>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Projects (<?= count($projects) ?>)</legend>
      <div id="projects" class="space-y-2 mt-3"></div>
    </fieldset>

    <div id="raw-section" class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 hidden">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-semibold">Raw JSON editor</span>
        <button type="button" id="raw-apply" class="px-3 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs">Apply to payload</button>
      </div>
      <textarea id="raw-json" rows="20" class="w-full font-mono text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded p-3"></textarea>
      <p class="text-xs text-amber-600 mt-2">Edits here override the form values — must be valid JSON.</p>
    </div>

    <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
      <span id="save-status" class="text-xs text-slate-500 mr-auto"></span>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium">Save ecosystem</button>
    </div>
  </form>
</div>

<template id="proj-tpl">
  <div class="proj rounded-lg border border-slate-200 dark:border-slate-700 p-3">
    <div class="flex items-center gap-3 flex-wrap">
      <input type="checkbox" data-k="enabled" class="w-4 h-4">
      <input type="text" data-k="code" class="b30-input text-sm py-1 w-32 font-mono" placeholder="code">
      <input type="text" data-k="name" class="b30-input text-sm py-1 flex-1 min-w-[180px]" placeholder="Name">
      <span class="text-xs text-slate-500">Rate per credit:</span>
      <input type="number" step="0.0001" data-k="rate" class="b30-input text-sm py-1 w-28">
      <input type="text" data-k="currency" class="b30-input text-sm py-1 w-24 uppercase" placeholder="USD">
      <button type="button" data-act="del" class="p-1 rounded hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
    </div>
  </div>
</template>

<script>
(function () {
  const projEl = document.getElementById('projects');
  const tpl = document.getElementById('proj-tpl');
  const rawSec = document.getElementById('raw-section');
  const rawTa  = document.getElementById('raw-json');
  const statusEl = document.getElementById('save-status');
  const form = document.getElementById('eco-form');
  const base = <?= json_encode($eco, JSON_UNESCAPED_UNICODE) ?>;
  let projects = JSON.parse(JSON.stringify(base.projects || []));
  let overrideRaw = null;

  function rowFor(p, i) {
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.dataset.idx = i;
    const enChk = node.querySelector('[data-k="enabled"]');
    enChk.checked = p.enabled !== false;
    enChk.addEventListener('change', () => p.enabled = enChk.checked);
    ['code','name','currency'].forEach(k => {
      const inp = node.querySelector('[data-k="' + k + '"]');
      if (p[k] !== undefined) inp.value = p[k];
      inp.addEventListener('input', () => p[k] = inp.value);
    });
    const rateInp = node.querySelector('[data-k="rate"]');
    rateInp.value = p.rate_per_credit || p.rate || 1;
    rateInp.addEventListener('input', () => { p.rate_per_credit = parseFloat(rateInp.value) || 0; });
    node.querySelector('[data-act="del"]').addEventListener('click', async () => {
      if (await window.SA.confirm('Remove project "' + (p.code || '') + '"?')) {
        projects.splice(i, 1); render();
      }
    });
    return node;
  }

  function render() {
    projEl.innerHTML = '';
    projects.forEach((p, i) => projEl.appendChild(rowFor(p, i)));
    if (window.lucide && lucide.createIcons) lucide.createIcons();
  }

  document.getElementById('raw-edit-btn').addEventListener('click', () => {
    rawSec.classList.toggle('hidden');
    if (!rawSec.classList.contains('hidden')) {
      rawTa.value = JSON.stringify(buildPayload(), null, 2);
    }
  });
  document.getElementById('raw-apply').addEventListener('click', () => {
    try {
      overrideRaw = JSON.parse(rawTa.value);
      window.SA.toast('Raw JSON staged — click Save to commit');
    } catch (e) {
      window.SA.toast('Invalid JSON: ' + e.message, 'error');
    }
  });

  function buildPayload() {
    if (overrideRaw) return overrideRaw;
    const f = new FormData(form);
    const out = JSON.parse(JSON.stringify(base));
    out.version = f.get('version') || out.version;
    out.updated_at = f.get('updated_at') || out.updated_at;
    out.credit = out.credit || {};
    out.credit.symbol = f.get('credit_symbol') || out.credit.symbol;
    out.credit.name   = f.get('credit_name')   || out.credit.name;
    out.projects = projects;
    return out;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    statusEl.textContent = 'Saving…';
    try {
      await window.SA.savePayload(buildPayload());
      statusEl.textContent = 'Saved · ' + new Date().toLocaleTimeString();
      window.SA.toast('Ecosystem saved');
      overrideRaw = null;
    } catch (err) {
      statusEl.textContent = 'Error: ' + (err.message || err);
      window.SA.toast(err.message || 'Save failed', 'error');
    }
  });

  render();
})();
</script>
<?php sa_render_footer(); ?>
