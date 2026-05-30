<?php
/**
 * Super-Admin · Security policy editor (security.json)
 */
declare(strict_types=1);
$PAGE = 'security';
$TITLE = 'Security Policy';
$JSON_FILE = 'security';
$SAVE_OP = 'admin.security.save';
$SAVE_FIELD = 'security_file';
require __DIR__ . '/_layout.php';

$sec = b30_security();
$auth = $sec['auth'] ?? [];
$upload = $sec['upload'] ?? [];
$rl   = $sec['rate_limits'] ?? [];

sa_render_header();
?>
<div class="space-y-6">
  <header>
    <h1 class="font-display text-2xl font-bold">Security Policy</h1>
    <p class="text-sm text-slate-500">Edits <code class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">security.json</code>. Enforced server-side by every API endpoint.</p>
  </header>

  <form id="sec-form" data-validate class="space-y-6" novalidate>
    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Authentication</legend>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">Min password length</span>
          <input type="number" min="6" max="128" name="pw_min" required class="b30-input mt-1 w-full" value="<?= sa_h((string)($auth['password_min_length'] ?? 8)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Bcrypt cost</span>
          <input type="number" min="4" max="15" name="bcrypt_cost" class="b30-input mt-1 w-full" value="<?= sa_h((string)($auth['bcrypt_cost'] ?? 12)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Session TTL (s)</span>
          <input type="number" min="60" name="session_ttl" class="b30-input mt-1 w-full" value="<?= sa_h((string)($auth['session_ttl_seconds'] ?? 86400)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Session idle (s)</span>
          <input type="number" min="60" name="session_idle" class="b30-input mt-1 w-full" value="<?= sa_h((string)($auth['session_idle_seconds'] ?? 3600)) ?>"></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="req_upper" <?= !empty($auth['password_require_upper']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Require uppercase</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="req_digit" <?= !empty($auth['password_require_digit']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Require digit</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="req_symbol" <?= !empty($auth['password_require_symbol']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Require symbol</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="email_verify" <?= !empty($auth['email_verification_required']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Email verification</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="twofa_admin" <?= !empty($auth['twofa_enabled_for_admins']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">2FA for admins</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="twofa_user" <?= !empty($auth['twofa_enabled_for_users']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">2FA for users</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="api_key_required" <?= ($auth['api_key_required'] ?? true) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Require X-API-Key</span></label>
        <label class="block"><span class="text-sm font-medium">API key header</span>
          <input type="text" name="api_key_header" class="b30-input mt-1 w-full" value="<?= sa_h($auth['api_key_header'] ?? 'X-API-Key') ?>"></label>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Uploads</legend>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">Max size (bytes)</span>
          <input type="number" min="1024" name="up_max_bytes" class="b30-input mt-1 w-full" value="<?= sa_h((string)($upload['max_size_bytes'] ?? 5242880)) ?>"></label>
        <label class="block sm:col-span-2"><span class="text-sm font-medium">Allowed MIME (CSV)</span>
          <input type="text" name="up_mime" class="b30-input mt-1 w-full" value="<?= sa_h(implode(',', (array)($upload['allowed_mime'] ?? []))) ?>"></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="up_scan_exif" <?= !empty($upload['scan_exif']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Scan EXIF</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="up_strip_exif" <?= !empty($upload['strip_exif']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">Strip EXIF</span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="up_phash" <?= !empty($upload['perceptual_hash_check']) ? 'checked' : '' ?> class="w-4 h-4"><span class="text-sm">pHash dup check</span></label>
      </div>
    </fieldset>

    <fieldset class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5">
      <legend class="px-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Rate limits</legend>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-3">
        <label class="block"><span class="text-sm font-medium">Signin / IP / min</span>
          <input type="number" min="1" max="120" name="rl_signin_ip" class="b30-input mt-1 w-full" value="<?= sa_h((string)($rl['signin']['per_ip_per_minute'] ?? 6)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Signin lockout (s)</span>
          <input type="number" min="0" name="rl_signin_lockout" class="b30-input mt-1 w-full" value="<?= sa_h((string)($rl['signin']['lockout_seconds'] ?? 60)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Signup / IP / min</span>
          <input type="number" min="1" name="rl_signup_ip" class="b30-input mt-1 w-full" value="<?= sa_h((string)($rl['signup']['per_ip_per_minute'] ?? 4)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">Admin signin / IP / min</span>
          <input type="number" min="1" name="rl_admin_ip" class="b30-input mt-1 w-full" value="<?= sa_h((string)($rl['admin_signin']['per_ip_per_minute'] ?? 4)) ?>"></label>
        <label class="block"><span class="text-sm font-medium">API key / min</span>
          <input type="number" min="1" name="rl_api_key" class="b30-input mt-1 w-full" value="<?= sa_h((string)($rl['api_default']['per_key_per_minute'] ?? 60)) ?>"></label>
      </div>
    </fieldset>

    <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
      <span id="save-status" class="text-xs text-slate-500 mr-auto"></span>
      <button type="button" id="raw-btn" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm">Show raw JSON</button>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium">Save security policy</button>
    </div>
  </form>

  <details class="rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 p-4">
    <summary class="cursor-pointer text-sm font-medium">Raw JSON preview</summary>
    <pre id="raw-json" class="mt-3 text-xs overflow-auto max-h-96"></pre>
  </details>
</div>

<script>
(function () {
  const form = document.getElementById('sec-form');
  const statusEl = document.getElementById('save-status');
  const rawEl = document.getElementById('raw-json');
  const base = <?= json_encode($sec, JSON_UNESCAPED_UNICODE) ?>;

  function buildPayload() {
    const f = new FormData(form);
    const s = JSON.parse(JSON.stringify(base));
    s.auth = s.auth || {};
    s.auth.password_min_length        = parseInt(f.get('pw_min'), 10) || 8;
    s.auth.bcrypt_cost                = parseInt(f.get('bcrypt_cost'), 10) || 12;
    s.auth.session_ttl_seconds        = parseInt(f.get('session_ttl'), 10) || 86400;
    s.auth.session_idle_seconds       = parseInt(f.get('session_idle'), 10) || 3600;
    s.auth.password_require_upper     = !!f.get('req_upper');
    s.auth.password_require_digit     = !!f.get('req_digit');
    s.auth.password_require_symbol    = !!f.get('req_symbol');
    s.auth.email_verification_required= !!f.get('email_verify');
    s.auth.twofa_enabled_for_admins   = !!f.get('twofa_admin');
    s.auth.twofa_enabled_for_users    = !!f.get('twofa_user');
    s.auth.api_key_required           = !!f.get('api_key_required');
    s.auth.api_key_header             = f.get('api_key_header') || 'X-API-Key';
    s.upload = s.upload || {};
    s.upload.max_size_bytes           = parseInt(f.get('up_max_bytes'), 10) || 5242880;
    s.upload.allowed_mime             = (f.get('up_mime') || '').split(',').map(x => x.trim()).filter(Boolean);
    s.upload.scan_exif                = !!f.get('up_scan_exif');
    s.upload.strip_exif               = !!f.get('up_strip_exif');
    s.upload.perceptual_hash_check    = !!f.get('up_phash');
    s.rate_limits = s.rate_limits || {};
    s.rate_limits.signin       = Object.assign({}, s.rate_limits.signin, { per_ip_per_minute: parseInt(f.get('rl_signin_ip'),10)||6, lockout_seconds: parseInt(f.get('rl_signin_lockout'),10)||60 });
    s.rate_limits.signup       = Object.assign({}, s.rate_limits.signup, { per_ip_per_minute: parseInt(f.get('rl_signup_ip'),10)||4 });
    s.rate_limits.admin_signin = Object.assign({}, s.rate_limits.admin_signin, { per_ip_per_minute: parseInt(f.get('rl_admin_ip'),10)||4 });
    s.rate_limits.api_default  = Object.assign({}, s.rate_limits.api_default, { per_key_per_minute: parseInt(f.get('rl_api_key'),10)||60 });
    return s;
  }

  document.getElementById('raw-btn').addEventListener('click', () => {
    rawEl.textContent = JSON.stringify(buildPayload(), null, 2);
    rawEl.closest('details').open = true;
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
      window.SA.toast('Security policy saved');
    } catch (err) {
      statusEl.textContent = 'Error: ' + (err.message || err);
      window.SA.toast(err.message || 'Save failed', 'error');
    }
  });
})();
</script>
<?php sa_render_footer(); ?>
