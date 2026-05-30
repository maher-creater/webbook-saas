<?php
/**
 * 2030B P2P Pairing — Installation Wizard (multi-step).
 *
 * Self-gated: if backend/database/.installed exists, this page redirects to the
 * super-admin sign-in page. Otherwise it walks the operator through:
 *   Step 1: Environment check (PHP, PDO/SQLite, writable dirs)
 *   Step 2: Instance identity (site name, owner email + name)
 *   Step 3: Super-admin credentials
 *   Step 4: P2P providers (which to enable out of the catalog)
 *   Step 5: Subscription pack pick (Free / Pro / Enterprise)
 *   Step 6: Review & run → POSTs to /backend/api.php?op=install.run
 *
 * All values are validated client-side (B30Validate) and server-side (schema in
 * api.php). The server endpoint refuses to run when .installed already exists.
 */
declare(strict_types=1);
require_once __DIR__ . '/../backend/inc/bootstrap.php';

if (b30_is_installed()) {
    header('Location: ../pages/admin.php');
    exit;
}

$providers = b30_providers()['providers'] ?? [];
// Ensure packs table seeded before reading
b30_pdo();
$packs = b30_pdo()->query("SELECT * FROM subscription_packs WHERE enabled = 1 ORDER BY sort_order ASC")->fetchAll();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install · 2030B P2P Pairing</title>
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="robots" content="noindex,nofollow">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { darkMode:'class', theme:{ extend:{ fontFamily:{ sans:['Inter','system-ui'], display:['"Space Grotesk"','Inter'] } } } }</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/validate.js" defer></script>
</head>
<body class="bg-slate-50 dark:bg-[#0a0f1f] text-slate-800 dark:text-slate-200 antialiased font-sans">
<div class="min-h-screen flex items-center justify-center px-4 py-10">
  <div class="w-full max-w-3xl">
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-3">
        <span class="w-11 h-11 grid place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white font-bold">B30</span>
        <span class="font-display text-2xl font-bold">2030B P2P Pairing</span>
      </div>
      <h1 class="font-display text-3xl font-bold">Welcome — let's install your instance</h1>
      <p class="text-sm text-slate-500 mt-2">6 quick steps. About 2 minutes.</p>
    </div>

    <!-- Step indicator -->
    <ol id="steps-bar" class="flex items-center gap-2 mb-6 text-xs">
      <?php for ($i=1; $i<=6; $i++): ?>
        <li class="flex-1 h-1.5 rounded-full bg-slate-200 dark:bg-slate-800" data-step-bar="<?= $i ?>"></li>
      <?php endfor; ?>
    </ol>

    <form id="install-form" data-validate class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-sm" novalidate>
      <!-- ============ Step 1 ============ -->
      <section data-step="1" class="space-y-4">
        <h2 class="font-display text-xl font-bold flex items-center gap-2"><i data-lucide="server-cog" class="w-5 h-5"></i> Environment check</h2>
        <ul class="text-sm space-y-2" id="env-list">
          <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full grid place-items-center bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40"><i data-lucide="check" class="w-3 h-3"></i></span> PHP <?= sa_h(PHP_VERSION) ?></li>
          <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full grid place-items-center <?= extension_loaded('pdo_sqlite') ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40' : 'bg-rose-100 text-rose-700' ?>"><i data-lucide="<?= extension_loaded('pdo_sqlite') ? 'check' : 'x' ?>" class="w-3 h-3"></i></span> PDO SQLite</li>
          <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full grid place-items-center <?= is_writable(B30_PUBLIC) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40' : 'bg-rose-100 text-rose-700' ?>"><i data-lucide="<?= is_writable(B30_PUBLIC) ? 'check' : 'x' ?>" class="w-3 h-3"></i></span> Project root writable</li>
          <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full grid place-items-center <?= is_writable(B30_DB_DIR) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40' : 'bg-rose-100 text-rose-700' ?>"><i data-lucide="<?= is_writable(B30_DB_DIR) ? 'check' : 'x' ?>" class="w-3 h-3"></i></span> Database dir writable (<?= sa_h(B30_DB_DIR) ?>)</li>
        </ul>
      </section>

      <!-- ============ Step 2 ============ -->
      <section data-step="2" class="space-y-4 hidden">
        <h2 class="font-display text-xl font-bold flex items-center gap-2"><i data-lucide="building" class="w-5 h-5"></i> Instance identity</h2>
        <label class="block"><span class="text-sm font-medium">Site name</span>
          <input type="text" name="site_name" required minlength="2" maxlength="120" class="b30-input mt-1 w-full" value="2030B P2P Pairing"></label>
        <label class="block"><span class="text-sm font-medium">Default site language</span>
          <select name="default_lang" class="b30-input mt-1 w-full">
            <?php foreach (['en'=>'English','ar'=>'العربية','fr'=>'Français','es'=>'Español','de'=>'Deutsch','pt'=>'Português','it'=>'Italiano','zh'=>'中文','hi'=>'हिन्दी','ja'=>'日本語','ru'=>'Русский','tr'=>'Türkçe'] as $c=>$l): ?>
              <option value="<?= sa_h($c) ?>"><?= sa_h($l) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </section>

      <!-- ============ Step 3 ============ -->
      <section data-step="3" class="space-y-4 hidden">
        <h2 class="font-display text-xl font-bold flex items-center gap-2"><i data-lucide="user-cog" class="w-5 h-5"></i> Super-admin account</h2>
        <label class="block"><span class="text-sm font-medium">Full name</span>
          <input type="text" name="super_admin_name" required minlength="2" maxlength="80" class="b30-input mt-1 w-full" value="Maher Kaddoussi"></label>
        <label class="block"><span class="text-sm font-medium">Email</span>
          <input type="email" name="super_admin_email" required data-validate-rule="email" class="b30-input mt-1 w-full" value="super@2030b.com"></label>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block"><span class="text-sm font-medium">Password</span>
            <input type="password" name="super_admin_password" required minlength="8" class="b30-input mt-1 w-full" autocomplete="new-password"></label>
          <label class="block"><span class="text-sm font-medium">Confirm password</span>
            <input type="password" name="super_admin_password_confirm" required data-validate-match="super_admin_password" class="b30-input mt-1 w-full" autocomplete="new-password"></label>
        </div>
      </section>

      <!-- ============ Step 4 ============ -->
      <section data-step="4" class="space-y-4 hidden">
        <h2 class="font-display text-xl font-bold flex items-center gap-2"><i data-lucide="shuffle" class="w-5 h-5"></i> Enable P2P providers</h2>
        <p class="text-sm text-slate-500">Tick the providers you want enabled now. You can change this later from the super-admin panel.</p>
        <div class="grid sm:grid-cols-2 gap-3" id="prov-grid">
          <?php foreach ($providers as $p):
            $code = (string)($p['code'] ?? '');
            $name = (string)($p['name_en'] ?? $code);
            $on   = ($p['enabled'] ?? true) !== false; ?>
            <label class="b30-tile cursor-pointer rounded-xl border-2 border-slate-200 dark:border-slate-700 p-3 flex items-center gap-3 has-[input:checked]:border-indigo-500 has-[input:checked]:bg-indigo-50/40 dark:has-[input:checked]:bg-indigo-950/30">
              <input type="checkbox" name="providers_enabled[]" value="<?= sa_h($code) ?>" <?= $on ? 'checked' : '' ?> class="w-4 h-4">
              <span class="w-2.5 h-2.5 rounded-full" style="background:<?= sa_h($p['color_primary'] ?? '#888') ?>"></span>
              <span class="font-medium"><?= sa_h($name) ?></span>
              <span class="ml-auto text-xs text-slate-500 font-mono"><?= sa_h($code) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- ============ Step 5 ============ -->
      <section data-step="5" class="space-y-4 hidden">
        <h2 class="font-display text-xl font-bold flex items-center gap-2"><i data-lucide="sparkles" class="w-5 h-5"></i> Choose your pack</h2>
        <div class="grid sm:grid-cols-3 gap-3">
          <?php foreach ($packs as $p): ?>
            <label class="b30-tile cursor-pointer rounded-xl border-2 border-slate-200 dark:border-slate-700 p-4 has-[input:checked]:border-indigo-500 has-[input:checked]:bg-indigo-50/40 dark:has-[input:checked]:bg-indigo-950/30">
              <input type="radio" name="pack_code" value="<?= sa_h($p['code']) ?>" <?= $p['code'] === 'free' ? 'checked' : '' ?> class="hidden">
              <div class="font-display text-lg font-bold"><?= sa_h($p['name']) ?></div>
              <div class="text-2xl font-bold mt-1">$<?= sa_h(number_format((float)$p['price_monthly'], 0)) ?><span class="text-xs text-slate-500"> /mo</span></div>
              <p class="text-xs text-slate-500 mt-2"><?= sa_h($p['description']) ?></p>
            </label>
          <?php endforeach; ?>
        </div>
        <fieldset class="mt-2 flex items-center gap-4 text-sm">
          <label class="flex items-center gap-2"><input type="radio" name="billing_cycle" value="monthly" checked class="w-4 h-4"> Monthly</label>
          <label class="flex items-center gap-2"><input type="radio" name="billing_cycle" value="yearly" class="w-4 h-4"> Yearly</label>
        </fieldset>
      </section>

      <!-- ============ Step 6 ============ -->
      <section data-step="6" class="space-y-4 hidden">
        <h2 class="font-display text-xl font-bold flex items-center gap-2"><i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500"></i> Review &amp; install</h2>
        <pre id="summary" class="text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg p-4 overflow-auto max-h-72"></pre>
        <div class="text-xs text-slate-500">Clicking install will write <code>secrets.json</code>, seed the system_default Auth API key, create your super-admin, and lock further installs.</div>
        <div id="install-result" class="hidden rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 p-4 text-sm text-emerald-800 dark:text-emerald-200"></div>
      </section>

      <div class="mt-6 flex items-center justify-between gap-2">
        <button type="button" id="back-btn" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm hidden">← Back</button>
        <span class="text-xs text-slate-500" id="step-label">Step 1 / 6</span>
        <button type="button" id="next-btn" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">Continue →</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('install-form');
  const sections = Array.from(form.querySelectorAll('[data-step]'));
  const bars = Array.from(document.querySelectorAll('[data-step-bar]'));
  const stepLabel = document.getElementById('step-label');
  const backBtn = document.getElementById('back-btn');
  const nextBtn = document.getElementById('next-btn');
  const resultEl = document.getElementById('install-result');
  let cur = 1;
  const max = sections.length;

  function show(n) {
    cur = n;
    sections.forEach(s => s.classList.toggle('hidden', parseInt(s.dataset.step,10) !== n));
    bars.forEach(b => b.classList.toggle('bg-indigo-500', parseInt(b.dataset.stepBar,10) <= n));
    bars.forEach(b => b.classList.toggle('bg-slate-200', parseInt(b.dataset.stepBar,10) > n));
    stepLabel.textContent = 'Step ' + n + ' / ' + max;
    backBtn.classList.toggle('hidden', n === 1);
    nextBtn.textContent = (n === max) ? 'Install now' : 'Continue →';
    if (n === max) refreshSummary();
    if (window.lucide && lucide.createIcons) lucide.createIcons();
  }

  function refreshSummary() {
    const f = new FormData(form);
    const enabled = f.getAll('providers_enabled[]');
    const summary = {
      site_name: f.get('site_name'),
      default_lang: f.get('default_lang'),
      super_admin_name: f.get('super_admin_name'),
      super_admin_email: f.get('super_admin_email'),
      super_admin_password: '••••••••',
      providers_enabled: enabled,
      pack_code: f.get('pack_code'),
      billing_cycle: f.get('billing_cycle'),
    };
    document.getElementById('summary').textContent = JSON.stringify(summary, null, 2);
  }

  function validateStep(n) {
    const sec = sections.find(s => parseInt(s.dataset.step,10) === n);
    if (!sec) return true;
    // Skip the env step
    if (n === 1) return true;
    const fields = sec.querySelectorAll('input, select, textarea');
    let ok = true;
    fields.forEach(f => {
      if (!f.checkValidity()) { f.reportValidity(); ok = false; }
    });
    // Confirm password match
    if (n === 3) {
      const p1 = form.querySelector('[name="super_admin_password"]').value;
      const p2 = form.querySelector('[name="super_admin_password_confirm"]').value;
      if (p1 !== p2) { alert('Passwords do not match'); ok = false; }
    }
    return ok;
  }

  backBtn.addEventListener('click', () => { if (cur > 1) show(cur - 1); });
  nextBtn.addEventListener('click', async () => {
    if (!validateStep(cur)) return;
    if (cur < max) { show(cur + 1); return; }
    // Run install
    nextBtn.disabled = true; nextBtn.textContent = 'Installing…';
    const f = new FormData(form);
    const payload = {
      site_name: f.get('site_name'),
      super_admin_email: f.get('super_admin_email'),
      super_admin_name: f.get('super_admin_name'),
      super_admin_password: f.get('super_admin_password'),
      providers_enabled: f.getAll('providers_enabled[]'),
      pack_code: f.get('pack_code'),
      billing_cycle: f.get('billing_cycle') || 'monthly',
    };
    try {
      const r = await fetch('../backend/api.php?op=install.run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload),
      });
      const data = await r.json();
      if (!r.ok || data.error) throw new Error(data.error || ('HTTP ' + r.status));
      resultEl.classList.remove('hidden');
      resultEl.innerHTML = '<strong>Installation complete!</strong> Redirecting to admin panel…';
      setTimeout(() => { location.href = data.redirect || '../pages/admin.php'; }, 1200);
    } catch (e) {
      nextBtn.disabled = false; nextBtn.textContent = 'Install now';
      alert('Install failed: ' + (e.message || e));
    }
  });

  show(1);
})();
</script>
</body>
</html>
