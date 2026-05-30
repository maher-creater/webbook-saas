<?php
/**
 * Super-Admin · SaaS subscription pack management.
 *
 * Lists Free/Pro/Enterprise packs, current tenant pack, history, and a form to
 * change the active pack. Single-tenant SaaS — there is exactly one tenant row.
 */
declare(strict_types=1);
$PAGE = 'subscription';
$TITLE = 'Subscription';
$JSON_FILE = '';
$SAVE_OP = '';
require __DIR__ . '/_layout.php';

// Make sure tables exist (b30_pdo runs the ensure_subscription_packs / ensure_tenant migrations).
b30_pdo();
$packs = b30_pdo()->query("SELECT * FROM subscription_packs WHERE enabled = 1 ORDER BY sort_order ASC")->fetchAll();
$tenant = b30_current_tenant();
$history = [];
try { $history = b30_pdo()->query("SELECT * FROM subscription_history ORDER BY created_at DESC LIMIT 20")->fetchAll(); } catch (Throwable $e) {}

sa_render_header();
?>
<div class="space-y-6">
  <header>
    <h1 class="font-display text-2xl font-bold flex items-center gap-2"><i data-lucide="sparkles" class="w-6 h-6 text-violet-500"></i> Subscription</h1>
    <p class="text-sm text-slate-500">Manage this SaaS instance's pack. Current pack: <span class="font-semibold capitalize text-slate-700 dark:text-slate-200"><?= sa_h($tenant['pack_code'] ?? 'free') ?></span> (<?= sa_h($tenant['billing_cycle'] ?? 'monthly') ?>)</p>
  </header>

  <form id="tenant-form" data-validate class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5" novalidate>
    <div class="text-sm font-semibold mb-3">Instance owner</div>
    <div class="grid sm:grid-cols-3 gap-3">
      <label class="block"><span class="text-xs font-medium">Site name</span>
        <input type="text" name="site_name" required minlength="2" maxlength="120" class="b30-input mt-1 w-full" value="<?= sa_h($tenant['site_name'] ?? '') ?>"></label>
      <label class="block"><span class="text-xs font-medium">Owner email</span>
        <input type="email" name="owner_email" required data-validate-rule="email" class="b30-input mt-1 w-full" value="<?= sa_h($tenant['owner_email'] ?? '') ?>"></label>
      <label class="block"><span class="text-xs font-medium">Owner name</span>
        <input type="text" name="owner_name" required minlength="2" maxlength="80" class="b30-input mt-1 w-full" value="<?= sa_h($tenant['owner_name'] ?? '') ?>"></label>
    </div>
    <div class="mt-3 flex justify-end">
      <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm">Save owner</button>
    </div>
  </form>

  <div class="grid md:grid-cols-3 gap-4">
    <?php foreach ($packs as $p):
      $current = ($p['code'] === ($tenant['pack_code'] ?? '')); ?>
      <div class="rounded-2xl bg-white dark:bg-slate-900 border-2 <?= $current ? 'border-indigo-500 shadow-lg' : 'border-slate-200 dark:border-slate-800' ?> p-6 flex flex-col">
        <?php if ($current): ?>
          <span class="self-start text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300 mb-2 font-semibold">CURRENT</span>
        <?php endif; ?>
        <h3 class="font-display text-xl font-bold"><?= sa_h($p['name']) ?></h3>
        <div class="mt-2">
          <span class="text-3xl font-bold">$<?= sa_h(number_format((float)$p['price_monthly'], 0)) ?></span>
          <span class="text-sm text-slate-500">/ month</span>
          <div class="text-xs text-slate-500">or $<?= sa_h(number_format((float)$p['price_yearly'], 0)) ?> / year</div>
        </div>
        <p class="text-sm text-slate-500 mt-3"><?= sa_h($p['description']) ?></p>
        <ul class="text-sm mt-4 space-y-1 flex-1">
          <li class="flex items-center gap-2"><i data-lucide="users" class="w-4 h-4 text-slate-400"></i> <?= (int)$p['max_users'] === 0 ? 'Unlimited users' : ((int)$p['max_users'] . ' users') ?></li>
          <li class="flex items-center gap-2"><i data-lucide="shuffle" class="w-4 h-4 text-slate-400"></i> <?= (int)$p['max_providers'] === 0 ? 'Unlimited providers' : ((int)$p['max_providers'] . ' providers') ?></li>
          <li class="flex items-center gap-2"><i data-lucide="user-cog" class="w-4 h-4 text-slate-400"></i> <?= (int)$p['max_admins'] === 0 ? 'Unlimited sub-admins' : ((int)$p['max_admins'] . ' sub-admins') ?></li>
          <?php if ((int)$p['feature_audit']): ?><li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> Audit log</li><?php endif; ?>
          <?php if ((int)$p['feature_affiliate']): ?><li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> Affiliate program</li><?php endif; ?>
          <?php if ((int)$p['feature_api_keys']): ?><li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> API keys</li><?php endif; ?>
          <?php if ((int)$p['feature_white_label']): ?><li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> White-label branding</li><?php endif; ?>
        </ul>
        <div class="flex flex-col gap-2 mt-5">
          <button type="button" data-pack="<?= sa_h($p['code']) ?>" data-cycle="monthly" class="change-btn px-3 py-2 rounded-lg <?= $current ? 'bg-slate-200 dark:bg-slate-800 text-slate-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 text-white' ?> text-sm font-medium" <?= $current ? 'disabled' : '' ?>>
            Switch to <?= sa_h($p['name']) ?> (monthly)
          </button>
          <button type="button" data-pack="<?= sa_h($p['code']) ?>" data-cycle="yearly" class="change-btn px-3 py-2 rounded-lg border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 text-sm font-medium <?= $current ? 'opacity-50 cursor-not-allowed' : 'hover:bg-indigo-50 dark:hover:bg-indigo-950/40' ?>" <?= $current ? 'disabled' : '' ?>>
            Yearly billing
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 font-semibold text-sm">Subscription history</div>
    <?php if (!$history): ?>
      <div class="p-4 text-sm text-slate-500">No history yet.</div>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-900/40 text-xs uppercase text-slate-500">
          <tr><th class="text-left px-4 py-2">Date</th><th class="text-left px-4 py-2">Pack</th><th class="text-left px-4 py-2">Cycle</th><th class="text-right px-4 py-2">Amount</th><th class="text-left px-4 py-2">Event</th><th class="text-left px-4 py-2">Notes</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <?php foreach ($history as $h): ?>
            <tr>
              <td class="px-4 py-2 text-slate-500"><?= sa_h(date('Y-m-d H:i', (int)$h['created_at'])) ?></td>
              <td class="px-4 py-2 capitalize font-medium"><?= sa_h($h['pack_code']) ?></td>
              <td class="px-4 py-2"><?= sa_h($h['billing_cycle']) ?></td>
              <td class="px-4 py-2 text-right">$<?= sa_h(number_format((float)$h['amount'], 2)) ?></td>
              <td class="px-4 py-2"><?= sa_h($h['event']) ?></td>
              <td class="px-4 py-2 text-slate-500"><?= sa_h($h['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  document.querySelectorAll('.change-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (btn.disabled) return;
      const pack = btn.dataset.pack;
      const cycle = btn.dataset.cycle;
      if (!await window.SA.confirm('Switch pack?', 'Activate "' + pack + '" with ' + cycle + ' billing?')) return;
      try {
        await window.SA.fetch('subscription.change', { pack_code: pack, billing_cycle: cycle });
        window.SA.toast('Pack activated — reloading…');
        setTimeout(() => location.reload(), 800);
      } catch (e) {
        window.SA.toast(e.message || 'Failed to switch pack', 'error');
      }
    });
  });

  const form = document.getElementById('tenant-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (window.B30Validate) {
      const r = window.B30Validate.form(form);
      if (!r.ok) { window.SA.toast('Please fix the highlighted fields', 'error'); return; }
    }
    const f = new FormData(form);
    try {
      await window.SA.fetch('tenant.update', { site_name: f.get('site_name'), owner_email: f.get('owner_email'), owner_name: f.get('owner_name') });
      window.SA.toast('Instance owner updated');
    } catch (err) {
      window.SA.toast(err.message || 'Update failed', 'error');
    }
  });
})();
</script>
<?php sa_render_footer(); ?>
