<?php
/**
 * Super-Admin JSON Config Manager — shared layout
 *
 * Usage:
 *   <?php
 *   $PAGE = 'providers';                  // sidebar highlight
 *   $TITLE = 'P2P Providers';
 *   $JSON_FILE = 'providers';             // file key for admin.json.load / *.save
 *   $SAVE_OP = 'admin.providers.save';    // backend op
 *   $SAVE_FIELD = 'providers_file';       // body field name carrying the JSON payload
 *   $REQUIRE_SUPER = true;                // hard super-admin gate (defaults true)
 *   require __DIR__ . '/_layout.php';
 *   ?>
 *   <?php sa_render_header(); ?>
 *     ... your form HTML here ...
 *   <?php sa_render_footer(); ?>
 *
 * The layout boots auth, prints header + sidebar, and injects window.SA bridge
 * with: csrf, apiKey, loadJson(), savePayload(), validateForm().
 */

declare(strict_types=1);

require_once __DIR__ . '/../../backend/inc/bootstrap.php';

// Hard-gate: only super-admin can access these pages.
$me = b30_current_admin();
if (!$me || ($me['enabled'] ?? 1) != 1) {
    header('Location: ../admin.php');
    exit;
}
if (($me['role'] ?? '') !== 'super_admin' && !($REQUIRE_SUPER ?? true) === false) {
    // not super and not explicitly allowed
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>403</title><body style="font:14px system-ui;padding:2rem">Super-admin required.</body>';
    exit;
}
if (($REQUIRE_SUPER ?? true) && ($me['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>403</title><body style="font:14px system-ui;padding:2rem">Super-admin required.</body>';
    exit;
}

// Strict security headers (defence-in-depth on top of .htaccess).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Robots-Tag: noindex, nofollow');

$csrfToken = b30_csrf_token();
$dk = b30_default_api_key();
$apiKey = $dk['key'] ?? '';
$tenant = b30_current_tenant();

$PAGE       = $PAGE       ?? 'config';
$TITLE      = $TITLE      ?? 'Super-Admin';
$JSON_FILE  = $JSON_FILE  ?? '';
$SAVE_OP    = $SAVE_OP    ?? '';
$SAVE_FIELD = $SAVE_FIELD ?? '';

// Sidebar manifest — drives /pages/super-admin/ navigation.
$SA_PAGES = [
    ['key' => 'dashboard',   'href' => 'index.php',     'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['key' => 'config',      'href' => 'config.php',    'icon' => 'settings',         'label' => 'Platform Config'],
    ['key' => 'providers',   'href' => 'providers.php', 'icon' => 'shuffle',          'label' => 'P2P Providers'],
    ['key' => 'security',    'href' => 'security.php',  'icon' => 'shield-check',     'label' => 'Security Policy'],
    ['key' => 'penalties',   'href' => 'penalties.php', 'icon' => 'gavel',            'label' => 'Penalties'],
    ['key' => 'ecosystem',   'href' => 'ecosystem.php', 'icon' => 'globe',            'label' => 'Ecosystem'],
    ['key' => 'secrets',     'href' => 'secrets.php',   'icon' => 'key-round',        'label' => 'Secrets'],
    ['key' => 'subscription','href' => 'subscription.php','icon' => 'sparkles',       'label' => 'Subscription'],
];

function sa_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function sa_render_header(): void {
    global $TITLE, $PAGE, $SA_PAGES, $csrfToken, $apiKey, $JSON_FILE, $SAVE_OP, $SAVE_FIELD, $me, $tenant;
?>
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sa_h($TITLE) ?> · Super-Admin · 2030B</title>
<link rel="icon" type="image/svg+xml" href="../../favicon.svg">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="robots" content="noindex,nofollow">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { darkMode:'class', theme:{ extend:{
    fontFamily:{ sans:['Inter','system-ui','sans-serif'], display:['"Space Grotesk"','Inter','sans-serif'] }
  }}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="../../css/style.css">
<script src="../../js/i18n.js" defer></script>
<script src="../../js/validate.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Bootstrap config carried from PHP → window.SA bridge -->
<script>
window.SA = {
  csrf:    <?= json_encode($csrfToken) ?>,
  apiKey:  <?= json_encode($apiKey) ?>,
  file:    <?= json_encode($JSON_FILE) ?>,
  saveOp:  <?= json_encode($SAVE_OP) ?>,
  saveField: <?= json_encode($SAVE_FIELD) ?>,
  endpoint: '../../backend/api.php',
  me:      <?= json_encode([
                'id'=>(int)($me['id']??0),
                'email'=>(string)($me['email']??''),
                'full_name'=>(string)($me['full_name']??''),
                'role'=>(string)($me['role']??''),
             ]) ?>,
  tenant:  <?= json_encode($tenant) ?>,
};
</script>
</head>
<body class="bg-slate-50 dark:bg-[#0a0f1f] text-slate-800 dark:text-slate-200 antialiased font-sans">

<header class="sticky top-0 z-30 backdrop-blur bg-white/80 dark:bg-[#0a0f1f]/80 border-b border-slate-200 dark:border-slate-800">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <button class="lg:hidden p-2 rounded-lg bg-slate-100 dark:bg-slate-800" onclick="document.getElementById('sa-sidebar').classList.toggle('hidden')">
        <i data-lucide="menu" class="w-5 h-5"></i>
      </button>
      <a href="index.php" class="flex items-center gap-2">
        <span class="w-9 h-9 grid place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white font-bold">B30</span>
        <div class="leading-tight">
          <div class="text-xs uppercase tracking-wider text-slate-500">Super-Admin</div>
          <div class="font-display font-bold"><?= sa_h($TITLE) ?></div>
        </div>
      </a>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <span class="hidden md:inline text-slate-500"><?= sa_h($me['email'] ?? '') ?></span>
      <a href="../admin.php" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">Admin panel</a>
      <a href="../../index.html" class="px-3 py-1.5 rounded-lg bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-medium">Site →</a>
    </div>
  </div>
</header>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 grid lg:grid-cols-[240px_1fr] gap-6">
  <aside id="sa-sidebar" class="hidden lg:block">
    <nav class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
      <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($SA_PAGES as $p): ?>
          <li>
            <a href="<?= sa_h($p['href']) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 <?= $p['key'] === $PAGE ? 'bg-indigo-50 dark:bg-indigo-950/40 border-l-4 border-indigo-500 font-medium' : '' ?>">
              <i data-lucide="<?= sa_h($p['icon']) ?>" class="w-4 h-4 text-slate-500"></i>
              <span><?= sa_h($p['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-xs text-amber-900 dark:text-amber-200">
      <div class="font-semibold mb-1 flex items-center gap-1.5"><i data-lucide="shield-alert" class="w-4 h-4"></i> Super-admin only</div>
      All writes are CSRF-protected, gated by X-API-Key, and audited.
    </div>
  </aside>
  <main class="min-w-0">
<?php
}

function sa_render_footer(): void {
?>
  </main>
</div>

<script>
(function () {
  // ---- Common SA bridge: secure fetch with CSRF + X-API-Key + audit ---------
  window.SA.fetch = async function (op, payload, opts = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'X-CSRF': window.SA.csrf,
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (window.SA.apiKey) headers['X-API-Key'] = window.SA.apiKey;
    const method = opts.method || (payload ? 'POST' : 'GET');
    const url = window.SA.endpoint + '?op=' + encodeURIComponent(op) + (opts.qs ? '&' + opts.qs : '');
    const init = { method, headers, credentials: 'same-origin' };
    if (payload) init.body = JSON.stringify(payload);
    const r = await fetch(url, init);
    const txt = await r.text();
    let data; try { data = JSON.parse(txt); } catch (e) { data = { error: 'bad_json', body: txt }; }
    if (!r.ok || data.error) {
      const msg = data.error || ('HTTP ' + r.status);
      throw Object.assign(new Error(msg), { data, status: r.status });
    }
    return data;
  };

  window.SA.loadJson = async function () {
    if (!window.SA.file) return null;
    const r = await window.SA.fetch('admin.json.load', null, { qs: 'file=' + encodeURIComponent(window.SA.file) });
    return r.data;
  };

  window.SA.savePayload = async function (data) {
    if (!window.SA.saveOp) throw new Error('no_save_op');
    const body = {};
    body[window.SA.saveField] = data;
    return window.SA.fetch(window.SA.saveOp, body);
  };

  window.SA.toast = function (msg, type = 'success') {
    if (window.Swal) {
      Swal.fire({ toast: true, icon: type, title: msg, position: 'top-end', showConfirmButton: false, timer: 2400 });
    } else {
      alert(msg);
    }
  };

  window.SA.confirm = async function (title, text) {
    if (!window.Swal) return confirm(title);
    const r = await Swal.fire({ title, text, icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes' });
    return !!r.isConfirmed;
  };

  // ---- Wire all forms with data-validate when DOM is ready ---------------
  function wireAll() {
    if (!window.B30Validate) return;
    document.querySelectorAll('form[data-validate]').forEach(f => {
      try { window.B30Validate.wireForm(f); } catch (e) {}
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wireAll);
  else wireAll();

  // ---- Lucide icons --------------------------------------------------
  function paintIcons() { if (window.lucide && lucide.createIcons) lucide.createIcons(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', paintIcons);
  else paintIcons();
  // re-paint after late DOM updates
  const obs = new MutationObserver(() => paintIcons());
  obs.observe(document.body, { childList: true, subtree: true });
})();
</script>
</body>
</html>
<?php
}
