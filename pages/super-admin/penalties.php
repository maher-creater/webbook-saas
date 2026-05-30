<?php
/**
 * Super-Admin · Penalty rules editor (penalties.json)
 */
declare(strict_types=1);
$PAGE = 'penalties';
$TITLE = 'Penalty Rules';
$JSON_FILE = 'penalties';
$SAVE_OP = 'admin.penalties.cfg.save';
$SAVE_FIELD = 'penalties_file';
require __DIR__ . '/_layout.php';

$pen = b30_penalties_cfg();
$global = $pen['global'] ?? [];
$rules  = $pen['rules']  ?? [];
$appeal = $pen['appeal'] ?? [];

sa_render_header();
?>
<div class="space-y-6">
  <header class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="font-display text-2xl font-bold">Penalty Rules</h1>
      <p class="text-sm text-slate-500"><code class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">penalties.json</code> · <?= count($rules) ?> rule(s)</p>
    </div>
    <button type="button" id="add-btn" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
      <i data-lucide="plus" class="w-4 h-4 inline -mt-0.5"></i> Add rule
    </button>
  </header>

  <form id="pen-form" class="space-y-4">
    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Global behavior</legend>
      <div class="grid sm:grid-cols-3 gap-4 mt-3">
        <label class="flex items-center gap-2"><input type="checkbox" name="auto_apply" <?= !empty($global['auto_apply']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Auto-apply on trigger</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="notify_user" <?= !empty($global['notify_user']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Notify user</span></label>
        <label class="block"><span class="text-sm font-medium">Max credits negative</span>
          <input type="number" name="max_neg" class="b30-input mt-1 w-full" value="<?= sa_h((string)($global['max_credits_negative'] ?? 0)) ?>"></label>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Appeal policy</legend>
      <div class="grid sm:grid-cols-3 gap-4 mt-3">
        <label class="flex items-center gap-2"><input type="checkbox" name="ap_enabled" <?= !empty($appeal['enabled']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Enabled</span></label>
        <label class="block"><span class="text-sm font-medium">Window (days)</span>
          <input type="number" min="0" name="ap_window" class="b30-input mt-1 w-full" value="<?= sa_h((string)($appeal['window_days'] ?? 14)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Max appeals / user</span>
          <input type="number" min="0" name="ap_max" class="b30-input mt-1 w-full" value="<?= sa_h((string)($appeal['max_appeals_per_user'] ?? 2)) ?>"></label>
      </div>
    </fieldset>

    <div id="rules" class="space-y-3"></div>

    <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
      <span id="save-status" class="text-xs text-slate-500 mr-auto"></span>
      <button type="button" id="raw-btn" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">Show raw JSON</button>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium">Save penalties</button>
    </div>
  </form>

  <details class="rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 p-4">
    <summary class="cursor-pointer text-sm font-medium">Raw JSON preview</summary>
    <pre id="raw-json" class="mt-3 text-xs overflow-auto max-h-96"></pre>
  </details>
</div>

<template id="rule-tpl">
  <div class="rule rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4">
    <div class="flex items-center gap-3 flex-wrap">
      <i data-lucide="gavel" class="w-4 h-4 text-rose-500"></i>
      <input type="text" data-k="code" class="b30-input text-sm py-1 w-48 font-mono" placeholder="rule_code">
      <input type="text" data-k="label" class="b30-input text-sm py-1 flex-1 min-w-[200px]" placeholder="Human label">
      <button type="button" data-act="del" class="ml-auto p-1.5 rounded hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
      <label class="text-xs"><span>Target type</span>
        <select data-k="target_type" class="b30-input mt-1 w-full"><option value="user">user</option><option value="admin">admin</option></select></label>
      <label class="text-xs"><span>Credits penalty</span>
        <input type="number" min="0" data-k="credits_penalty" class="b30-input mt-1 w-full"></label>
      <label class="text-xs"><span>Block (days)</span>
        <input type="number" min="0" data-k="block_days" class="b30-input mt-1 w-full"></label>
      <label class="text-xs"><span>Auto trigger on</span>
        <input type="text" data-k="auto_trigger_on" class="b30-input mt-1 w-full" placeholder="manual or transaction_status:rejected_fake"></label>
      <label class="text-xs sm:col-span-2 lg:col-span-4"><span>Description</span>
        <textarea data-k="description" rows="2" class="b30-input mt-1 w-full"></textarea></label>
    </div>
  </div>
</template>

<script>
(function () {
  const rulesEl = document.getElementById('rules');
  const tpl = document.getElementById('rule-tpl');
  const rawEl = document.getElementById('raw-json');
  const statusEl = document.getElementById('save-status');
  const form = document.getElementById('pen-form');
  const base = <?= json_encode($pen, JSON_UNESCAPED_UNICODE) ?>;
  let rules = Object.entries(base.rules || {}).map(([code, r]) => Object.assign({ code }, r));

  function rowFor(r, i) {
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.dataset.idx = i;
    ['code','label','target_type','credits_penalty','block_days','auto_trigger_on','description'].forEach(k => {
      const inp = node.querySelector('[data-k="' + k + '"]');
      if (!inp) return;
      if (r[k] !== undefined && r[k] !== null) inp.value = r[k];
      inp.addEventListener('input', () => {
        r[k] = (inp.type === 'number') ? (parseFloat(inp.value) || 0) : inp.value;
      });
    });
    node.querySelector('[data-act="del"]').addEventListener('click', async () => {
      if (await window.SA.confirm('Remove rule "' + (r.code || '') + '"?')) {
        rules.splice(i, 1); render();
      }
    });
    return node;
  }

  function render() {
    rulesEl.innerHTML = '';
    rules.forEach((r, i) => rulesEl.appendChild(rowFor(r, i)));
    if (window.lucide && lucide.createIcons) lucide.createIcons();
  }

  document.getElementById('add-btn').addEventListener('click', () => {
    rules.push({ code: 'new_rule_' + Math.random().toString(36).slice(2, 5), label: 'New rule', target_type: 'user', credits_penalty: 1, block_days: 0, auto_trigger_on: 'manual', description: '' });
    render();
  });

  function buildPayload() {
    const f = new FormData(form);
    const out = JSON.parse(JSON.stringify(base));
    out.global = { auto_apply: !!f.get('auto_apply'), notify_user: !!f.get('notify_user'), max_credits_negative: parseInt(f.get('max_neg'), 10) || 0 };
    out.appeal = { enabled: !!f.get('ap_enabled'), window_days: parseInt(f.get('ap_window'), 10) || 0, max_appeals_per_user: parseInt(f.get('ap_max'), 10) || 0 };
    out.rules = {};
    rules.forEach(r => {
      if (!r.code) return;
      const c = r.code; const copy = Object.assign({}, r); delete copy.code;
      out.rules[c] = copy;
    });
    return out;
  }

  document.getElementById('raw-btn').addEventListener('click', () => {
    rawEl.textContent = JSON.stringify(buildPayload(), null, 2);
    rawEl.closest('details').open = true;
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const codes = new Set(); const errs = [];
    rules.forEach((r, i) => {
      if (!r.code || !/^[a-z0-9_]{2,40}$/.test(r.code)) errs.push('Rule ' + (i+1) + ': invalid code');
      else if (codes.has(r.code)) errs.push('Rule ' + (i+1) + ': duplicate code');
      else codes.add(r.code);
    });
    if (errs.length) { window.SA.toast(errs[0], 'error'); return; }
    statusEl.textContent = 'Saving…';
    try {
      await window.SA.savePayload(buildPayload());
      statusEl.textContent = 'Saved · ' + new Date().toLocaleTimeString();
      window.SA.toast('Penalties saved');
    } catch (err) {
      statusEl.textContent = 'Error: ' + (err.message || err);
      window.SA.toast(err.message || 'Save failed', 'error');
    }
  });

  render();
})();
</script>
<?php sa_render_footer(); ?>
