<?php
/**
 * Super-Admin · Platform Config editor (config.json)
 */
declare(strict_types=1);
$PAGE = 'config';
$TITLE = 'Platform Config';
$JSON_FILE = 'config';
$SAVE_OP = 'admin.config';
$SAVE_FIELD = 'config';
require __DIR__ . '/_layout.php';

$cfg = b30_config();
unset($cfg['admin_password'], $cfg['super_admin_email'], $cfg['super_admin_password'], $cfg['super_admin_name'], $cfg['super_admin']);

$platform = $cfg['platform']    ?? ['name'=>'','version'=>'','support_email'=>''];
$fees     = $cfg['fees']        ?? ['buy_fee_percent'=>0,'sell_fee_percent'=>0];
$profitPc = (float)($cfg['profit_percent'] ?? 0);
$credPair = (float)($cfg['credits_per_pairing'] ?? 1);
$credOp   = (float)($cfg['credits_per_single_op'] ?? 0.5);
$fiats    = $cfg['supported_fiat_currencies'] ?? [];
$langs    = $cfg['supported_languages'] ?? [];
$defFiat  = $cfg['default_fiat_currency'] ?? 'USD';

sa_render_header();
?>
<div class="space-y-6">
  <header class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="font-display text-2xl font-bold">Platform Config</h1>
      <p class="text-sm text-slate-500">Edits the public <code class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">config.json</code> file.</p>
    </div>
    <button type="button" id="reset-btn" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
      <i data-lucide="rotate-ccw" class="w-4 h-4 inline -mt-0.5"></i> Reload
    </button>
  </header>

  <form id="cfg-form" data-validate class="space-y-6" novalidate>
    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Platform identity</legend>
      <div class="grid sm:grid-cols-2 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">Platform name</span>
          <input type="text" name="platform_name" required minlength="2" maxlength="120" class="b30-input mt-1 w-full" value="<?= sa_h($platform['name'] ?? '') ?>"></label>
        <label class="block"><span class="text-sm font-medium">Version</span>
          <input type="text" name="platform_version" maxlength="32" class="b30-input mt-1 w-full" value="<?= sa_h($platform['version'] ?? '') ?>"></label>
        <label class="block sm:col-span-2"><span class="text-sm font-medium">Support email</span>
          <input type="email" name="support_email" data-validate-rule="email" class="b30-input mt-1 w-full" value="<?= sa_h($platform['support_email'] ?? '') ?>"></label>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Fees &amp; credits</legend>
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">Buy fee (%)</span>
          <input type="number" step="0.01" min="0" max="100" name="buy_fee" class="b30-input mt-1 w-full" value="<?= sa_h((string)($fees['buy_fee_percent'] ?? 0)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Sell fee (%)</span>
          <input type="number" step="0.01" min="0" max="100" name="sell_fee" class="b30-input mt-1 w-full" value="<?= sa_h((string)($fees['sell_fee_percent'] ?? 0)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Profit (%)</span>
          <input type="number" step="0.01" min="0" max="100" name="profit_percent" class="b30-input mt-1 w-full" value="<?= sa_h((string)$profitPc) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Credits / pairing</span>
          <input type="number" step="0.01" min="0" name="credits_per_pairing" class="b30-input mt-1 w-full" value="<?= sa_h((string)$credPair) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Credits / single op</span>
          <input type="number" step="0.01" min="0" name="credits_per_single_op" class="b30-input mt-1 w-full" value="<?= sa_h((string)$credOp) ?>"></label>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Level unlocks</legend>
      <div class="grid lg:grid-cols-2 gap-4 mt-3">
        <?php foreach (['1','2','3','4'] as $L): $lvl = $cfg['levels'][$L] ?? []; ?>
          <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="font-semibold">Level <?= $L ?></span>
              <input type="text" name="lvl_<?= $L ?>_title" class="b30-input text-sm py-1 w-44" value="<?= sa_h($lvl['title'] ?? '') ?>" placeholder="Title">
            </div>
            <textarea name="lvl_<?= $L ?>_desc" rows="2" class="b30-input mt-1 w-full text-sm" placeholder="Description"><?= sa_h($lvl['description'] ?? '') ?></textarea>
            <div class="grid sm:grid-cols-2 gap-2 mt-2">
              <label class="text-xs"><span>Required pairings</span>
                <input type="number" min="0" name="lvl_<?= $L ?>_pairs" class="b30-input mt-1 w-full" value="<?= sa_h((string)($lvl['required_pairings'] ?? 0)) ?>"></label>
              <label class="text-xs"><span>Unlocks key</span>
                <input type="text" name="lvl_<?= $L ?>_unlocks" class="b30-input mt-1 w-full" value="<?= sa_h($lvl['unlocks'] ?? '') ?>"></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Locale</legend>
      <div class="grid sm:grid-cols-2 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">Default fiat (ISO)</span>
          <input type="text" name="default_fiat" maxlength="3" pattern="[A-Za-z]{3}" class="b30-input mt-1 w-full uppercase" value="<?= sa_h($defFiat) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Supported fiats</span>
          <input type="text" name="supported_fiats" class="b30-input mt-1 w-full" value="<?= sa_h(implode(',', (array)$fiats)) ?>" placeholder="USD,EUR,…"></label>
        <label class="block sm:col-span-2"><span class="text-sm font-medium">Supported languages</span>
          <input type="text" name="supported_langs" class="b30-input mt-1 w-full" value="<?= sa_h(implode(',', (array)$langs)) ?>" placeholder="en,ar,fr,…"></label>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Affiliate program</legend>
      <?php $ap = $cfg['affiliate_program'] ?? []; ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-3">
        <label class="flex items-center gap-2"><input type="checkbox" name="aff_enabled" <?= !empty($ap['enabled']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Enabled</span></label>
        <label class="block"><span class="text-sm font-medium">Percent</span>
          <input type="number" step="0.01" min="0" max="100" name="aff_percent" class="b30-input mt-1 w-full" value="<?= sa_h((string)($ap['percent'] ?? 10)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Cookie days</span>
          <input type="number" min="0" max="365" name="aff_cookie_days" class="b30-input mt-1 w-full" value="<?= sa_h((string)($ap['cookie_days'] ?? 30)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Max / referee / year</span>
          <input type="number" min="0" name="aff_max_year" class="b30-input mt-1 w-full" value="<?= sa_h((string)($ap['max_credits_per_referee_per_year'] ?? 0)) ?>"></label>
      </div>
    </fieldset>

    <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
      <span id="save-status" class="text-xs text-slate-500 mr-auto"></span>
      <button type="button" id="raw-btn" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">Show raw JSON</button>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium">Save changes</button>
    </div>
  </form>

  <details class="rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 p-4">
    <summary class="cursor-pointer text-sm font-medium">Raw JSON preview</summary>
    <pre id="raw-json" class="mt-3 text-xs overflow-auto max-h-96"></pre>
  </details>
</div>

<script>
(function () {
  const form = document.getElementById('cfg-form');
  const statusEl = document.getElementById('save-status');
  const rawEl  = document.getElementById('raw-json');
  const baseCfg = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE) ?>;

  function csvList(v) { return (v || '').split(',').map(s => s.trim()).filter(Boolean); }

  function buildPayload() {
    const f = new FormData(form);
    const cfg = JSON.parse(JSON.stringify(baseCfg));
    cfg.platform = cfg.platform || {};
    cfg.platform.name = f.get('platform_name') || '';
    cfg.platform.version = f.get('platform_version') || '';
    cfg.platform.support_email = f.get('support_email') || '';
    cfg.fees = cfg.fees || {};
    cfg.fees.buy_fee_percent  = parseFloat(f.get('buy_fee'))  || 0;
    cfg.fees.sell_fee_percent = parseFloat(f.get('sell_fee')) || 0;
    cfg.profit_percent        = parseFloat(f.get('profit_percent')) || 0;
    cfg.credits_per_pairing   = parseFloat(f.get('credits_per_pairing')) || 0;
    cfg.credits_per_single_op = parseFloat(f.get('credits_per_single_op')) || 0;
    cfg.default_fiat_currency = (f.get('default_fiat') || 'USD').toUpperCase();
    cfg.supported_fiat_currencies = csvList(f.get('supported_fiats'));
    cfg.supported_languages       = csvList(f.get('supported_langs'));
    cfg.levels = cfg.levels || {};
    ['1','2','3','4'].forEach(L => {
      cfg.levels[L] = cfg.levels[L] || {};
      cfg.levels[L].title             = f.get('lvl_' + L + '_title') || '';
      cfg.levels[L].description       = f.get('lvl_' + L + '_desc')  || '';
      cfg.levels[L].required_pairings = parseInt(f.get('lvl_' + L + '_pairs'), 10) || 0;
      cfg.levels[L].unlocks           = f.get('lvl_' + L + '_unlocks') || '';
    });
    cfg.affiliate_program = cfg.affiliate_program || {};
    cfg.affiliate_program.enabled     = !!f.get('aff_enabled');
    cfg.affiliate_program.percent     = parseFloat(f.get('aff_percent')) || 0;
    cfg.affiliate_program.cookie_days = parseInt(f.get('aff_cookie_days'), 10) || 30;
    cfg.affiliate_program.max_credits_per_referee_per_year = parseInt(f.get('aff_max_year'), 10) || 0;
    return cfg;
  }

  document.getElementById('raw-btn').addEventListener('click', () => {
    rawEl.textContent = JSON.stringify(buildPayload(), null, 2);
    rawEl.closest('details').open = true;
  });
  document.getElementById('reset-btn').addEventListener('click', async () => {
    if (await window.SA.confirm('Reload?', 'Unsaved changes will be lost.')) location.reload();
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (window.B30Validate) {
      const r = window.B30Validate.form(form);
      if (!r.ok) { window.SA.toast('Please fix the highlighted fields', 'error'); return; }
    }
    statusEl.textContent = 'Saving…';
    try {
      await window.SA.savePayload(buildPayload());
      statusEl.textContent = 'Saved · ' + new Date().toLocaleTimeString();
      window.SA.toast('Configuration saved');
    } catch (err) {
      statusEl.textContent = 'Error: ' + (err.message || err);
      window.SA.toast(err.message || 'Save failed', 'error');
    }
  });
})();
</script>
<?php sa_render_footer(); ?>
