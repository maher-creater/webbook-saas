<?php
/**
 * Super-Admin · P2P Providers editor (p2p-providers.json)
 */
declare(strict_types=1);
$PAGE = 'providers';
$TITLE = 'P2P Providers';
$JSON_FILE = 'providers';
$SAVE_OP = 'admin.providers.save';
$SAVE_FIELD = 'providers_file';
require __DIR__ . '/_layout.php';

$pf = b30_providers();
$providers = $pf['providers'] ?? [];
$defaultProvider = $pf['default_provider'] ?? ($providers[0]['code'] ?? '');

sa_render_header();
?>
<div class="space-y-6">
  <header class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="font-display text-2xl font-bold">P2P Providers</h1>
      <p class="text-sm text-slate-500">Manage <code class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">p2p-providers.json</code>. <?= count($providers) ?> provider(s) configured.</p>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" id="add-btn" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
        <i data-lucide="plus" class="w-4 h-4 inline -mt-0.5"></i> Add provider
      </button>
      <button type="button" id="reload-btn" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">
        <i data-lucide="rotate-ccw" class="w-4 h-4 inline -mt-0.5"></i> Reload
      </button>
    </div>
  </header>

  <form id="prov-form" class="space-y-4">
    <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3 flex-wrap">
      <label class="text-sm font-medium">Default provider:</label>
      <select name="default_provider" id="default-provider" class="b30-input">
        <?php foreach ($providers as $p):
          $c = (string)($p['code'] ?? ''); if (!$c) continue; ?>
          <option value="<?= sa_h($c) ?>" <?= $c === $defaultProvider ? 'selected' : '' ?>><?= sa_h($p['name_en'] ?? $c) ?> (<?= sa_h($c) ?>)</option>
        <?php endforeach; ?>
      </select>
      <span class="text-xs text-slate-500 ml-auto"><?= count($providers) ?> total · <?= count(array_filter($providers, fn($p) => ($p['enabled'] ?? true) !== false)) ?> enabled</span>
    </div>

    <div id="rows" class="space-y-3"></div>

    <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
      <span id="save-status" class="text-xs text-slate-500 mr-auto"></span>
      <button type="button" id="raw-btn" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">Show raw JSON</button>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium">Save providers</button>
    </div>
  </form>

  <details class="rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 p-4">
    <summary class="cursor-pointer text-sm font-medium">Raw JSON preview</summary>
    <pre id="raw-json" class="mt-3 text-xs overflow-auto max-h-96"></pre>
  </details>
</div>

<template id="row-tpl">
  <div class="row rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4" data-code="">
    <div class="flex items-center gap-3 flex-wrap">
      <span class="row-color w-3 h-3 rounded-full flex-shrink-0"></span>
      <span class="font-semibold row-title">…</span>
      <span class="row-badge text-xs px-2 py-0.5 rounded-full"></span>
      <div class="ml-auto flex items-center gap-2">
        <label class="text-xs flex items-center gap-1"><input type="checkbox" data-k="enabled" class="w-4 h-4"><span>Enabled</span></label>
        <button type="button" data-act="up" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Move up"><i data-lucide="arrow-up" class="w-4 h-4"></i></button>
        <button type="button" data-act="down" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Move down"><i data-lucide="arrow-down" class="w-4 h-4"></i></button>
        <button type="button" data-act="del" class="p-1.5 rounded hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600" title="Remove"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
        <button type="button" data-act="toggle" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="chevron-down" class="w-4 h-4"></i></button>
      </div>
    </div>
    <div class="row-body mt-4 grid lg:grid-cols-3 gap-3 hidden">
      <label class="block text-xs"><span class="font-medium">Code (slug)</span>
        <input type="text" data-k="code" required pattern="[a-z0-9_-]{2,32}" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Name (EN)</span>
        <input type="text" data-k="name_en" required class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Logo key</span>
        <input type="text" data-k="logo" class="b30-input mt-1 w-full" placeholder="binance, redotpay, …"></label>
      <label class="block text-xs"><span class="font-medium">Primary color</span>
        <input type="color" data-k="color_primary" class="mt-1 h-9 w-full rounded border border-slate-300 dark:border-slate-700"></label>
      <label class="block text-xs"><span class="font-medium">Accent color</span>
        <input type="color" data-k="color_accent" class="mt-1 h-9 w-full rounded border border-slate-300 dark:border-slate-700"></label>
      <label class="block text-xs"><span class="font-medium">Level required</span>
        <input type="number" min="1" max="4" data-k="level_required" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Website</span>
        <input type="url" data-k="website" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Verify URL</span>
        <input type="url" data-k="verify_url" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Supported fiats (CSV)</span>
        <input type="text" data-k="supported_fiat" class="b30-input mt-1 w-full" placeholder="USD,EUR,…"></label>
      <label class="block text-xs"><span class="font-medium">Min USDT buy</span>
        <input type="number" step="0.01" min="0" data-k="min_usdt_buy" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Min USDT sell</span>
        <input type="number" step="0.01" min="0" data-k="min_usdt_sell" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Max USDT / op</span>
        <input type="number" step="0.01" min="0" data-k="max_usdt_per_op" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Buy fee %</span>
        <input type="number" step="0.01" min="0" max="100" data-k="fee_buy_percent" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">Sell fee %</span>
        <input type="number" step="0.01" min="0" max="100" data-k="fee_sell_percent" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs"><span class="font-medium">UID label (EN)</span>
        <input type="text" data-k="uid_label_en" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs lg:col-span-2"><span class="font-medium">UID regex</span>
        <input type="text" data-k="uid_regex" class="b30-input mt-1 w-full font-mono" placeholder="^[0-9]{6,20}$"></label>
      <label class="block text-xs lg:col-span-3"><span class="font-medium">UID hint (EN)</span>
        <input type="text" data-k="uid_hint_en" class="b30-input mt-1 w-full"></label>
      <label class="block text-xs lg:col-span-3"><span class="font-medium">Notes (EN)</span>
        <textarea data-k="notes_en" rows="2" class="b30-input mt-1 w-full"></textarea></label>
    </div>
  </div>
</template>

<script>
(function () {
  const rowsEl  = document.getElementById('rows');
  const tpl     = document.getElementById('row-tpl');
  const rawEl   = document.getElementById('raw-json');
  const status  = document.getElementById('save-status');
  const form    = document.getElementById('prov-form');
  const defSel  = document.getElementById('default-provider');
  const base    = <?= json_encode($pf, JSON_UNESCAPED_UNICODE) ?>;
  let state     = JSON.parse(JSON.stringify(base.providers || []));

  const FIELDS = ['code','name_en','logo','color_primary','color_accent','level_required',
                  'website','verify_url','min_usdt_buy','min_usdt_sell','max_usdt_per_op',
                  'fee_buy_percent','fee_sell_percent','uid_label_en','uid_regex','uid_hint_en','notes_en'];

  function paintBadge(el, enabled) {
    el.textContent = enabled ? 'Enabled' : 'Disabled';
    el.className = 'row-badge text-xs px-2 py-0.5 rounded-full ' +
      (enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
               : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400');
  }

  function rowFromProvider(p, idx) {
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.dataset.idx = idx;
    node.dataset.code = p.code || '';
    node.querySelector('.row-title').textContent = (p.name_en || p.code || 'New provider');
    node.querySelector('.row-color').style.backgroundColor = p.color_primary || '#888';
    paintBadge(node.querySelector('.row-badge'), p.enabled !== false);

    const enChk = node.querySelector('[data-k="enabled"]');
    enChk.checked = p.enabled !== false;
    enChk.addEventListener('change', () => {
      p.enabled = enChk.checked;
      paintBadge(node.querySelector('.row-badge'), p.enabled);
    });

    FIELDS.forEach(k => {
      const inp = node.querySelector('[data-k="' + k + '"]');
      if (!inp) return;
      let v = p[k];
      if (k === 'supported_fiat' && Array.isArray(v)) v = v.join(',');
      if (inp.type === 'color' && (!v || !/^#/.test(String(v)))) v = '#888888';
      if (v !== undefined && v !== null) inp.value = v;
      inp.addEventListener('input', () => {
        if (k === 'name_en') node.querySelector('.row-title').textContent = inp.value || p.code || 'New provider';
        if (k === 'color_primary') node.querySelector('.row-color').style.backgroundColor = inp.value;
        p[k] = (inp.type === 'number') ? (parseFloat(inp.value) || 0) : inp.value;
      });
    });
    const sfInp = node.querySelector('[data-k="supported_fiat"]');
    if (sfInp) {
      sfInp.value = Array.isArray(p.supported_fiat) ? p.supported_fiat.join(',') : (p.supported_fiat || '');
      sfInp.addEventListener('input', () => {
        p.supported_fiat = sfInp.value.split(',').map(s => s.trim()).filter(Boolean);
      });
    }

    // Body actions
    node.querySelector('[data-act="toggle"]').addEventListener('click', () => {
      node.querySelector('.row-body').classList.toggle('hidden');
    });
    node.querySelector('[data-act="up"]').addEventListener('click', () => move(idx, -1));
    node.querySelector('[data-act="down"]').addEventListener('click', () => move(idx, +1));
    node.querySelector('[data-act="del"]').addEventListener('click', async () => {
      if (await window.SA.confirm('Remove this provider?', p.name_en || p.code)) {
        state.splice(idx, 1); render();
      }
    });
    return node;
  }

  function move(idx, delta) {
    const j = idx + delta;
    if (j < 0 || j >= state.length) return;
    const tmp = state[idx]; state[idx] = state[j]; state[j] = tmp;
    render();
  }

  function refreshDefaultSel() {
    const cur = defSel.value;
    defSel.innerHTML = '';
    state.forEach(p => {
      if (!p.code) return;
      const o = document.createElement('option');
      o.value = p.code;
      o.textContent = (p.name_en || p.code) + ' (' + p.code + ')';
      if (p.code === cur) o.selected = true;
      defSel.appendChild(o);
    });
  }

  function render() {
    rowsEl.innerHTML = '';
    state.forEach((p, i) => rowsEl.appendChild(rowFromProvider(p, i)));
    refreshDefaultSel();
    if (window.lucide && lucide.createIcons) lucide.createIcons();
  }

  document.getElementById('add-btn').addEventListener('click', () => {
    state.push({
      code: 'new_' + Math.random().toString(36).slice(2, 6),
      name_en: 'New Provider',
      enabled: false,
      logo: '',
      color_primary: '#6366f1',
      color_accent: '#0f172a',
      level_required: 1,
      website: '',
      min_usdt_buy: 0, min_usdt_sell: 0, max_usdt_per_op: 0,
      fee_buy_percent: 0, fee_sell_percent: 0,
      supported_fiat: ['USD'],
      uid_regex: '^[A-Za-z0-9_-]{3,32}$',
    });
    render();
    // open the last row's body
    setTimeout(() => {
      const last = rowsEl.lastElementChild;
      if (last) last.querySelector('.row-body').classList.remove('hidden');
    }, 0);
  });

  document.getElementById('reload-btn').addEventListener('click', async () => {
    if (await window.SA.confirm('Reload?', 'Unsaved changes will be lost.')) location.reload();
  });

  function buildPayload() {
    return Object.assign({}, base, {
      default_provider: defSel.value || (state[0]?.code || ''),
      providers: state,
    });
  }

  document.getElementById('raw-btn').addEventListener('click', () => {
    rawEl.textContent = JSON.stringify(buildPayload(), null, 2);
    rawEl.closest('details').open = true;
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    // server-style validation: every row must have a code + name_en + valid level
    const errs = [];
    const codes = new Set();
    state.forEach((p, i) => {
      if (!p.code || !/^[a-z0-9_-]{2,32}$/.test(p.code)) errs.push('Row ' + (i+1) + ': invalid code');
      else if (codes.has(p.code)) errs.push('Row ' + (i+1) + ': duplicate code "' + p.code + '"');
      else codes.add(p.code);
      if (!p.name_en) errs.push('Row ' + (i+1) + ': name (EN) is required');
    });
    if (errs.length) { window.SA.toast(errs[0], 'error'); return; }

    status.textContent = 'Saving…';
    try {
      await window.SA.savePayload(buildPayload());
      status.textContent = 'Saved · ' + new Date().toLocaleTimeString();
      window.SA.toast('Providers saved');
    } catch (err) {
      status.textContent = 'Error: ' + (err.message || err);
      window.SA.toast(err.message || 'Save failed', 'error');
    }
  });

  render();
})();
</script>
<?php sa_render_footer(); ?>
