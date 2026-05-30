<?php
/**
 * Super-Admin · Secrets editor (secrets.json).
 *
 * Never displays the current passwords. Only shows whether they are set + the
 * super-admin email, and lets the super-admin rotate any of them.
 */
declare(strict_types=1);
$PAGE = 'secrets';
$TITLE = 'Secrets';
$JSON_FILE = 'secrets';
$SAVE_OP = 'admin.secrets.save';
$SAVE_FIELD = ''; // we send fields directly, not wrapped
require __DIR__ . '/_layout.php';

$secrets = b30_secrets();
$sec     = b30_security();
$minPw   = (int)($sec['auth']['password_min_length'] ?? 8);

sa_render_header();
?>
<div class="space-y-6">
  <header>
    <h1 class="font-display text-2xl font-bold flex items-center gap-2"><i data-lucide="key-round" class="w-6 h-6 text-amber-500"></i> Secrets</h1>
    <p class="text-sm text-slate-500">Server-only <code class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">secrets.json</code>. Existing passwords are never returned by the API — leave a field empty to keep the current value.</p>
  </header>

  <div class="rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 p-4 text-sm">
    <div class="flex items-start gap-3">
      <i data-lucide="shield-alert" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
      <div>
        <div class="font-semibold mb-1">Changing super-admin password also rotates your live DB password.</div>
        <div class="text-xs">After saving, you'll need to sign in again with the new credentials.</div>
      </div>
    </div>
  </div>

  <form id="sec-form" data-validate class="space-y-4" novalidate>
    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Super-admin</legend>
      <div class="grid sm:grid-cols-2 gap-4 mt-3">
        <label class="block sm:col-span-2"><span class="text-sm font-medium">Email</span>
          <input type="email" name="super_admin_email" required class="b30-input mt-1 w-full" value="<?= sa_h((string)($secrets['super_admin_email'] ?? '')) ?>">
        </label>
        <label class="block"><span class="text-sm font-medium">New password</span>
          <input type="password" name="super_admin_password" autocomplete="new-password" minlength="<?= (int)$minPw ?>" class="b30-input mt-1 w-full" placeholder="Leave empty to keep current">
        </label>
        <label class="block"><span class="text-sm font-medium">Confirm password</span>
          <input type="password" name="super_admin_password_confirm" data-validate-match="super_admin_password" class="b30-input mt-1 w-full" placeholder="Repeat the new password">
        </label>
      </div>
      <div class="text-xs text-slate-500 mt-2">Status: <?= !empty($secrets['super_admin_password']) ? '<span class="text-emerald-500">password set</span>' : '<span class="text-rose-500">no password set</span>' ?></div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Legacy admin shared password</legend>
      <div class="grid sm:grid-cols-2 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">New admin password</span>
          <input type="password" name="admin_password" autocomplete="new-password" minlength="<?= (int)$minPw ?>" class="b30-input mt-1 w-full" placeholder="Leave empty to keep current">
        </label>
        <label class="block"><span class="text-sm font-medium">Confirm admin password</span>
          <input type="password" name="admin_password_confirm" data-validate-match="admin_password" class="b30-input mt-1 w-full" placeholder="Repeat new admin password">
        </label>
      </div>
      <div class="text-xs text-slate-500 mt-2">Status: <?= !empty($secrets['admin_password']) ? '<span class="text-emerald-500">password set</span>' : '<span class="text-rose-500">no password set</span>' ?></div>
    </fieldset>

    <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
      <span id="save-status" class="text-xs text-slate-500 mr-auto"></span>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium">Save secrets</button>
    </div>
  </form>
</div>

<script>
(function () {
  const form = document.getElementById('sec-form');
  const statusEl = document.getElementById('save-status');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (window.B30Validate) {
      const r = window.B30Validate.form(form);
      if (!r.ok) { window.SA.toast(r.errors[0]?.msg || 'Please fix the highlighted fields', 'error'); return; }
    }
    const f = new FormData(form);
    const sup = f.get('super_admin_password');
    const supC = f.get('super_admin_password_confirm');
    const adm = f.get('admin_password');
    const admC = f.get('admin_password_confirm');
    if ((sup || supC) && sup !== supC) { window.SA.toast('Super-admin passwords do not match', 'error'); return; }
    if ((adm || admC) && adm !== admC) { window.SA.toast('Admin passwords do not match', 'error'); return; }
    const payload = { super_admin_email: f.get('super_admin_email') };
    if (sup) payload.super_admin_password = sup;
    if (adm) payload.admin_password = adm;

    if (!await window.SA.confirm('Save secrets?', 'You may need to sign in again afterwards.')) return;
    statusEl.textContent = 'Saving…';
    try {
      await window.SA.fetch('admin.secrets.save', payload);
      statusEl.textContent = 'Saved · ' + new Date().toLocaleTimeString();
      window.SA.toast('Secrets saved');
      // clear password fields
      ['super_admin_password','super_admin_password_confirm','admin_password','admin_password_confirm']
        .forEach(n => { const i = form.querySelector('[name="'+n+'"]'); if (i) i.value = ''; });
    } catch (err) {
      statusEl.textContent = 'Error: ' + (err.message || err);
      window.SA.toast(err.message || 'Save failed', 'error');
    }
  });
})();
</script>
<?php sa_render_footer(); ?>
