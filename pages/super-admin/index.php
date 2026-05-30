<?php
/**
 * Super-Admin Dashboard — entry page.
 * Lists every JSON config manager + subscription state.
 */
declare(strict_types=1);
$PAGE  = 'dashboard';
$TITLE = 'Dashboard';
require __DIR__ . '/_layout.php';
sa_render_header();

$tiles = [
    ['key'=>'config',      'icon'=>'settings',     'title'=>'Platform Config',      'desc'=>'Levels, fees, profit %, credits, supported currencies.', 'href'=>'config.php',      'color'=>'from-sky-500 to-cyan-500'],
    ['key'=>'providers',   'icon'=>'shuffle',      'title'=>'P2P Providers',        'desc'=>'Add, enable, disable Binance, RedotPay, Bybit, OKX… ('.count(b30_providers()['providers'] ?? []).' total).', 'href'=>'providers.php',   'color'=>'from-amber-500 to-orange-500'],
    ['key'=>'security',    'icon'=>'shield-check', 'title'=>'Security Policy',      'desc'=>'Password rules, sessions, rate-limits, CSP, uploads.', 'href'=>'security.php',    'color'=>'from-emerald-500 to-teal-500'],
    ['key'=>'penalties',   'icon'=>'gavel',        'title'=>'Penalty Rules',        'desc'=>'Fake-pairing penalties, admin reputation rules, appeals.', 'href'=>'penalties.php',   'color'=>'from-rose-500 to-red-500'],
    ['key'=>'ecosystem',   'icon'=>'globe',        'title'=>'Ecosystem Currencies', 'desc'=>'Project currencies, conversion rates, multilingual labels.', 'href'=>'ecosystem.php',   'color'=>'from-violet-500 to-fuchsia-500'],
    ['key'=>'secrets',     'icon'=>'key-round',    'title'=>'Secrets',              'desc'=>'Admin + super-admin passwords (never round-tripped).', 'href'=>'secrets.php',     'color'=>'from-slate-700 to-slate-900'],
    ['key'=>'subscription','icon'=>'sparkles',     'title'=>'Subscription',         'desc'=>'Free / Pro / Enterprise SaaS pack management.', 'href'=>'subscription.php','color'=>'from-indigo-500 to-violet-600'],
];
$tenant = b30_current_tenant();
$packCode = $tenant['pack_code'] ?? 'free';
?>
<div class="space-y-6">
  <div class="rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white p-6 sm:p-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="text-xs uppercase tracking-wider opacity-80">Welcome back, super-admin</div>
        <h1 class="text-2xl sm:text-3xl font-display font-bold mt-1"><?= sa_h($me['full_name'] ?? 'Super-Admin') ?></h1>
        <p class="opacity-90 mt-2 max-w-xl">Single-pane-of-glass for every JSON config file on this 2030B SaaS instance. All writes are CSRF-protected, gated by X-API-Key, and recorded in the audit log.</p>
      </div>
      <div class="rounded-xl bg-white/10 backdrop-blur px-5 py-4 border border-white/20">
        <div class="text-xs uppercase tracking-wider opacity-80">Current pack</div>
        <div class="text-2xl font-bold capitalize"><?= sa_h($packCode) ?></div>
        <a href="subscription.php" class="text-xs mt-2 inline-flex items-center gap-1 underline opacity-90 hover:opacity-100">Change pack →</a>
      </div>
    </div>
  </div>

  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($tiles as $t): ?>
      <a href="<?= sa_h($t['href']) ?>" class="group rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 hover:shadow-lg hover:-translate-y-0.5 transition">
        <div class="w-12 h-12 rounded-xl grid place-items-center bg-gradient-to-br <?= sa_h($t['color']) ?> text-white mb-3">
          <i data-lucide="<?= sa_h($t['icon']) ?>" class="w-6 h-6"></i>
        </div>
        <div class="font-semibold text-lg"><?= sa_h($t['title']) ?></div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?= sa_h($t['desc']) ?></p>
        <span class="inline-flex items-center gap-1 mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 group-hover:gap-2 transition-all">Open <i data-lucide="arrow-right" class="w-4 h-4"></i></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php
sa_render_footer();
