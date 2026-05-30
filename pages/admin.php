<?php
/**
 * Admin panel — server-rendered PHP shell for the JS admin SPA.
 *
 * Security wrapper added in v3:
 *   - Forces strict no-cache headers so the markup never lives in shared caches.
 *   - Adds defence-in-depth headers (X-Frame-Options, X-Content-Type-Options,
 *     Referrer-Policy, Permissions-Policy) at the page level too — not just .htaccess.
 *   - Soft-requires the backend bootstrap if available so the page can later be
 *     gated by `b30_require_admin()`. If the backend is missing we fall back to
 *     pure-JS gating (the SPA already redirects unauthenticated visitors).
 */
$bootstrap = __DIR__ . '/../backend/inc/bootstrap.php';
if (is_file($bootstrap)) {
    require_once $bootstrap;
    // Soft-gate: if the visitor already has an admin session, b30_current_admin()
    // returns the row; otherwise we still render the SPA which itself shows the
    // sign-in screen and then signs in through the Auth API (default key).
    if (function_exists('b30_current_admin')) { b30_current_admin(); }
}
// Strict cache + security headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">
<head>
<meta charset="UTF-8" />
<link rel="icon" type="image/svg+xml" href="../favicon.svg" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin — 2030B P2P Pairing</title>
<meta name="description" content="Restricted admin panel: validate pending transactions, view users, edit configuration." />
<meta name="referrer" content="strict-origin-when-cross-origin" />
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { darkMode:'class', theme:{ extend:{
    fontFamily: { sans:['Inter','system-ui','sans-serif'], display:['"Space Grotesk"','"Inter"','sans-serif'] }
  }}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="../css/style.css">

<script src="../js/logos.js"></script>
<script src="../js/config.js"></script>
<script src="../js/i18n.js"></script>
<script src="../js/store.js"></script>
<script src="../js/shell.js" defer></script>
<script src="../js/main.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body data-prefix="../" class="bg-slate-50 dark:bg-[#0a0f1f] text-slate-800 dark:text-slate-200 antialiased font-sans">

<div data-shell></div>

<main class="pt-28 pb-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <!-- Sign-in gate -->
    <section id="adminGate" class="b30-card max-w-md mx-auto p-6 text-center">
      <span data-b30-icon="admin" data-size="64" class="inline-block"></span>
      <h1 class="mt-3 font-display font-extrabold text-2xl" data-i18n="admin.signin.title">Restricted area</h1>
      <p class="mt-2 text-sm opacity-70" data-i18n="admin.signin.hint">Sign in with your administrator account.</p>
      <form id="adminLogin" class="mt-5 space-y-3" autocomplete="off">
        <input type="email" class="b30-input" required name="email" placeholder="admin@2030b.com" autocomplete="username">
        <input type="password" class="b30-input" required name="pw" placeholder="••••••••" autocomplete="current-password" minlength="6">
        <button type="submit" class="wb-btn-primary rounded-lg px-5 py-2.5 text-sm font-semibold w-full" data-i18n="admin.signin.submit">Sign in</button>
        <p class="text-[11px] opacity-60" data-i18n="admin.signin.tree">Sub-admins are created and permissioned by the super-admin.</p>
      </form>
    </section>

    <!-- Panel -->
    <section id="adminPanel" class="hidden">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display font-extrabold text-3xl" data-i18n="admin.title">Admin panel</h1>
        <div class="flex items-center gap-2 flex-wrap">
          <span id="adminWho" class="b30-pill b30-pill-verified hidden"></span>
          <button id="seedDemoBtn" class="wb-btn-ghost rounded-lg px-3 py-1.5 text-xs font-semibold inline-flex items-center gap-1" data-perm="manage_users">
            <i data-lucide="database" class="w-3.5 h-3.5"></i> <span data-i18n="admin.seedDemo">Seed demo</span>
          </button>
          <button id="adminLogout" class="wb-btn-ghost rounded-lg px-3 py-1.5 text-xs font-semibold inline-flex items-center gap-1">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i> <span data-i18n="cta.signOut">Sign out</span>
          </button>
        </div>
      </div>

      <!-- Stat row -->
      <div class="grid sm:grid-cols-4 gap-4 mt-6">
        <div class="b30-card">
          <p class="text-xs uppercase tracking-widest opacity-70" data-i18n="admin.users.count">Users</p>
          <p class="wb-stat-num font-display text-3xl" id="kpiUsers">0</p>
        </div>
        <div class="b30-card">
          <p class="text-xs uppercase tracking-widest opacity-70" data-i18n="admin.txs.count">Pending transactions</p>
          <p class="wb-stat-num font-display text-3xl" id="kpiPending">0</p>
        </div>
        <div class="b30-card">
          <p class="text-xs uppercase tracking-widest opacity-70" data-i18n="admin.kpi.verifiedPairs">Verified pairings</p>
          <p class="wb-stat-num font-display text-3xl" id="kpiVerified">0</p>
        </div>
        <div class="b30-card">
          <p class="text-xs uppercase tracking-widest opacity-70" data-i18n="admin.kpi.penalties">Open penalties</p>
          <p class="wb-stat-num font-display text-3xl" id="kpiPenalties">0</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mt-8 flex items-center gap-2 border-b border-slate-200/60 dark:border-white/10 overflow-x-auto" id="adminTabs">
        <button data-tab="pending" data-perm="verify_transactions" class="tab is-active px-4 py-2 -mb-px border-b-2 border-blue-500 text-blue-500 font-semibold text-sm whitespace-nowrap">
          <i data-lucide="clock" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.transactions">Pending transactions</span>
        </button>
        <button data-tab="all-pairings" data-perm="verify_transactions" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="layers" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.allPairings">All pairings</span>
        </button>
        <button data-tab="users" data-perm="manage_users" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="users" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.users">Users</span>
        </button>
        <button data-tab="admins" data-perm="manage_admins" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="shield-check" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.admins">Admins</span>
        </button>
        <button data-tab="affiliate" data-perm="manage_affiliate" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="gift" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.affiliate">Affiliate</span>
        </button>
        <button data-tab="penalties" data-perm="manage_users" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="alert-triangle" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.penalties">Penalties</span>
        </button>
        <button data-tab="keys" data-perm="manage_keys" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="key-round" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.keys">API keys</span>
        </button>
        <button data-tab="ecosystem" data-perm="manage_ecosystem" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="circuit-board" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.ecosystem">Ecosystem</span>
        </button>
        <button data-tab="providers" data-perm="manage_ecosystem" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="plug-zap" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.providers">P2P providers</span>
        </button>
        <button data-tab="security" data-perm="edit_config" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="shield" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.security">Security</span>
        </button>
        <button data-tab="config" data-perm="edit_config" class="tab px-4 py-2 -mb-px border-b-2 border-transparent text-sm whitespace-nowrap">
          <i data-lucide="settings-2" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.config">Configuration</span>
        </button>
        <a href="super-admin/index.php" data-perm="edit_config" class="ml-auto px-3 py-2 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-sm font-medium hover:opacity-90 whitespace-nowrap">
          <i data-lucide="crown" class="w-4 h-4 inline"></i> <span data-i18n="admin.tab.superadmin">Super-Admin Panel</span> →
        </a>
      </div>

      <!-- Pending transactions -->
      <div data-tab-panel="pending" class="mt-6">
        <div id="pendingList" class="space-y-3"></div>
        <p id="pendingEmpty" class="b30-card text-center py-10 hidden opacity-70" data-i18n="admin.pending.empty">No pending transactions.</p>
      </div>

      <!-- All pairings -->
      <div data-tab-panel="all-pairings" class="mt-6 hidden">
        <div class="flex items-center gap-2 mb-4 flex-wrap">
          <h3 class="font-display font-bold text-lg" data-i18n="admin.allPairings.title">Every user's pairings &amp; ops</h3>
          <div class="ml-auto flex items-center gap-1 text-xs">
            <input id="allFilterText" type="search" class="b30-input !py-1 !text-xs !w-44" placeholder="Email / pair id">
            <button data-allf="all"      class="px-2.5 py-1 rounded-full border border-slate-300/50 dark:border-white/10 hover:bg-blue-500/10 is-active" data-i18n="filter.all">All</button>
            <button data-allf="pending"  class="px-2.5 py-1 rounded-full border border-slate-300/50 dark:border-white/10 hover:bg-blue-500/10" data-i18n="status.pending">Pending</button>
            <button data-allf="verified" class="px-2.5 py-1 rounded-full border border-slate-300/50 dark:border-white/10 hover:bg-blue-500/10" data-i18n="status.verified">Verified</button>
            <button data-allf="rejected" class="px-2.5 py-1 rounded-full border border-slate-300/50 dark:border-white/10 hover:bg-blue-500/10" data-i18n="status.rejected">Rejected</button>
          </div>
        </div>
        <div id="allPairingsList" class="space-y-3"></div>
        <p id="allPairingsEmpty" class="b30-card text-center py-10 hidden opacity-70" data-i18n="admin.allPairings.empty">No pairings yet.</p>
      </div>

      <!-- Users -->
      <div data-tab-panel="users" class="mt-6 hidden">
        <div class="b30-card overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs uppercase opacity-70">
              <tr>
                <th class="text-left p-2" data-i18n="admin.users.col.user">User</th>
                <th class="text-left p-2" data-i18n="admin.users.col.country">Country</th>
                <th class="text-right p-2" data-i18n="admin.users.col.level">Level</th>
                <th class="text-right p-2" data-i18n="admin.users.col.credits">Credits</th>
                <th class="text-right p-2" data-i18n="admin.users.col.pairings">Pairings</th>
                <th class="text-right p-2" data-i18n="admin.users.col.referral">Referral</th>
                <th class="text-right p-2" data-i18n="admin.users.col.joined">Joined</th>
              </tr>
            </thead>
            <tbody id="usersTbody"></tbody>
          </table>
        </div>
      </div>

      <!-- Admins (sub-admin CRUD, super-admin only) -->
      <div data-tab-panel="admins" class="mt-6 hidden">
        <div class="b30-card">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <h3 class="font-display font-bold" data-i18n="admin.admins.title">Admin tree</h3>
              <p class="text-sm opacity-70 mt-1" data-i18n="admin.admins.sub">Super-admin creates, permissions, enables/disables sub-admins.</p>
            </div>
            <button id="btnNewAdmin" class="wb-btn-primary rounded-xl px-4 py-2 text-sm font-semibold inline-flex items-center gap-2" data-perm="manage_admins">
              <i data-lucide="user-plus" class="w-4 h-4"></i> <span data-i18n="admin.admins.new">New sub-admin</span>
            </button>
          </div>
          <div id="adminsList" class="mt-4 space-y-3"></div>
        </div>
      </div>

      <!-- Affiliate -->
      <div data-tab-panel="affiliate" class="mt-6 hidden">
        <div class="b30-card">
          <h3 class="font-display font-bold" data-i18n="admin.affiliate.title">Referrals &amp; rewards</h3>
          <p class="text-sm opacity-70 mt-1" data-i18n="admin.affiliate.sub">Track who referred who and how many credits have been kicked back.</p>
          <div class="grid sm:grid-cols-3 gap-3 mt-4">
            <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
              <p class="text-xs uppercase opacity-70" data-i18n="admin.affiliate.kpi.referrals">Referrals</p>
              <p class="wb-stat-num font-display text-2xl" id="affKpiRef">0</p>
            </div>
            <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
              <p class="text-xs uppercase opacity-70" data-i18n="admin.affiliate.kpi.rewards">Rewards paid</p>
              <p class="wb-stat-num font-display text-2xl" id="affKpiRew">0</p>
            </div>
            <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
              <p class="text-xs uppercase opacity-70" data-i18n="admin.affiliate.kpi.credits">Credits kicked-back</p>
              <p class="wb-stat-num font-display text-2xl" id="affKpiCr">0</p>
            </div>
          </div>
          <div class="overflow-x-auto mt-5">
            <table class="w-full text-sm">
              <thead class="text-xs uppercase opacity-70">
                <tr>
                  <th class="text-left p-2" data-i18n="admin.affiliate.col.when">When</th>
                  <th class="text-left p-2" data-i18n="admin.affiliate.col.referrer">Referrer</th>
                  <th class="text-left p-2" data-i18n="admin.affiliate.col.referee">Referee</th>
                  <th class="text-right p-2" data-i18n="admin.affiliate.col.credits">Credits</th>
                  <th class="text-left p-2" data-i18n="admin.affiliate.col.source">Source</th>
                </tr>
              </thead>
              <tbody id="affTbody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Penalties -->
      <div data-tab-panel="penalties" class="mt-6 hidden">
        <div class="b30-card">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <h3 class="font-display font-bold" data-i18n="admin.penalties.title">Penalties &amp; fraud control</h3>
              <p class="text-sm opacity-70 mt-1" data-i18n="admin.penalties.sub">Punish fake submissions (users) or wrongful approvals (admins). Rules from <code>penalties.json</code>.</p>
            </div>
            <div class="flex items-center gap-2">
              <button id="btnEditPenaltyCfg" class="wb-btn-ghost rounded-lg px-3 py-1.5 text-xs font-semibold inline-flex items-center gap-1" data-perm="edit_config">
                <i data-lucide="settings" class="w-3.5 h-3.5"></i> <span data-i18n="admin.penalties.edit">Edit rules</span>
              </button>
              <button id="btnNewPenalty" class="wb-btn-primary rounded-lg px-3 py-1.5 text-xs font-semibold inline-flex items-center gap-1">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> <span data-i18n="admin.penalties.new">New penalty</span>
              </button>
            </div>
          </div>
          <div id="penList" class="mt-4 space-y-3"></div>
          <p id="penEmpty" class="text-sm opacity-70 hidden" data-i18n="admin.penalties.empty">No penalties recorded.</p>
        </div>
      </div>

      <!-- API keys -->
      <div data-tab-panel="keys" class="mt-6 hidden">
        <div class="b30-card">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <h3 class="font-display font-bold" data-i18n="admin.keys.title">Auth API keys</h3>
              <p class="text-sm opacity-70 mt-1" data-i18n="admin.keys.sub">Manage every key in the system. Keys let partner sites open the 2030B auth popup.</p>
            </div>
            <button id="adminNewKey" class="wb-btn-primary rounded-xl px-4 py-2 text-sm font-semibold inline-flex items-center gap-2">
              <i data-lucide="plus" class="w-4 h-4"></i> <span data-i18n="admin.keys.new">New key</span>
            </button>
          </div>
          <div id="adminKeysList" class="mt-4 space-y-3"></div>
          <p id="adminKeysEmpty" class="text-sm opacity-70 hidden" data-i18n="admin.keys.empty">No API keys yet.</p>
        </div>
      </div>

      <!-- Ecosystem currencies -->
      <div data-tab-panel="ecosystem" class="mt-6 hidden">
        <div class="b30-card">
          <h3 class="font-display font-bold" data-i18n="admin.ecosystem.title">Ecosystem currencies &amp; rates</h3>
          <p class="text-sm opacity-70 mt-1" data-i18n="admin.ecosystem.sub">Live view of <code>ecosystem-currencies.json</code>. Edit rates here.</p>
          <div id="ecoGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4"></div>
          <div class="mt-4 flex items-center gap-2">
            <button id="ecoSave" class="wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold" data-i18n="admin.ecosystem.save">Save rates</button>
            <button id="ecoReset" class="wb-btn-ghost rounded-lg px-4 py-2 text-sm font-semibold" data-i18n="admin.ecosystem.reset">Reset to defaults</button>
            <span id="ecoStatus" class="text-xs opacity-70 ml-2"></span>
          </div>
        </div>
      </div>

      <!-- P2P Providers -->
      <div data-tab-panel="providers" class="mt-6 hidden">
        <div class="b30-card">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <h3 class="font-display font-bold" data-i18n="admin.providers.title">P2P providers</h3>
              <p class="text-sm opacity-70 mt-1" data-i18n="admin.providers.sub">Enable / disable any provider, edit fiat support, fees, min/max, UID format. Every system page reads from <code>p2p-providers.json</code>.</p>
            </div>
            <div class="flex items-center gap-2">
              <button id="btnNewProvider" class="wb-btn-primary rounded-lg px-3 py-1.5 text-xs font-semibold inline-flex items-center gap-1">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> <span data-i18n="admin.providers.new">Add provider</span>
              </button>
              <button id="provReset" class="wb-btn-ghost rounded-lg px-3 py-1.5 text-xs font-semibold inline-flex items-center gap-1">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> <span data-i18n="admin.providers.reset">Reset to file</span>
              </button>
            </div>
          </div>
          <div id="provList" class="mt-4 space-y-3"></div>
          <div class="mt-4 flex items-center gap-2">
            <button id="provSave" class="wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold" data-i18n="admin.providers.save">Save providers</button>
            <span id="provStatus" class="text-xs opacity-70 ml-2"></span>
          </div>
        </div>
      </div>

      <!-- Security -->
      <div data-tab-panel="security" class="mt-6 hidden">
        <div class="b30-card">
          <h3 class="font-display font-bold" data-i18n="admin.security.title">Security policy</h3>
          <p class="text-sm opacity-70 mt-1" data-i18n="admin.security.sub">Auth, rate-limits, CSRF, CORS, CSP, upload, IP policy, fraud detection, audit, GDPR. Persisted to <code>security.json</code> by the PHP backend.</p>

          <div class="mt-4 grid lg:grid-cols-2 gap-4" id="secCards"></div>

          <details class="mt-6">
            <summary class="cursor-pointer text-sm font-semibold opacity-80" data-i18n="admin.security.rawEdit">Advanced: raw JSON editor</summary>
            <textarea id="securityEditor" class="b30-input font-mono text-xs mt-3" rows="18" spellcheck="false"></textarea>
          </details>

          <div class="mt-4 flex items-center gap-2">
            <button id="secSave" class="wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold" data-i18n="admin.security.save">Save policy</button>
            <button id="secReset" class="wb-btn-ghost rounded-lg px-4 py-2 text-sm font-semibold" data-i18n="admin.security.reset">Reset to file</button>
            <span id="secStatus" class="text-xs opacity-70 ml-2"></span>
          </div>
        </div>
      </div>

      <!-- Config -->
      <div data-tab-panel="config" class="mt-6 hidden">
        <div class="b30-card">
          <p class="text-sm opacity-70 mb-2" data-i18n="admin.config.hint">Edit <code>config.json</code> live (saved to <code>localStorage</code> in this demo; PHP backend writes the file).</p>
          <textarea id="configEditor" class="b30-input font-mono text-xs" rows="22" spellcheck="false"></textarea>
          <div class="mt-3 flex items-center gap-2">
            <button id="configSave" class="wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold" data-i18n="admin.config.save">Save changes</button>
            <button id="configReset" class="wb-btn-ghost rounded-lg px-4 py-2 text-sm font-semibold" data-i18n="admin.config.reset">Reset to file</button>
            <span id="configStatus" class="text-xs opacity-70 ml-2"></span>
          </div>
        </div>
      </div>
    </section>

  </div>
</main>

<div data-shell-foot></div>

<script src="../js/enhancements.js" defer></script>
<script>
(function () {
  'use strict';
  let cfg = null;
  let penaltyCfg = null;
  function whenReady(fn) {
    Promise.all([
      new Promise(r => window.B30Config.onLoad(r)),
      window.B30I18n.ready(),
      window.B30Store.ready()
    ]).then(([c]) => { cfg = c; loadPenaltyCfg().then(()=>fn(c)); });
  }
  async function loadPenaltyCfg() {
    try {
      const stored = localStorage.getItem('b30-penalties-override');
      if (stored) { penaltyCfg = JSON.parse(stored); return; }
      const res = await fetch('../penalties.json', { cache:'no-cache' });
      if (res.ok) penaltyCfg = await res.json();
    } catch(e) { penaltyCfg = { rules:{} }; }
  }
  function t(k, fb){ const v = window.B30I18n.t(k); return (v && v !== k) ? v : (fb||k); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function imgPopup(url, title) {
    Swal.fire({
      title: title || t('admin.imagePopup.title','Verification screenshot'),
      imageUrl: url, imageAlt: 'screenshot',
      width: 'auto', imageWidth:'auto', imageHeight:'auto',
      showCloseButton: true, showConfirmButton: false,
      background: '#0f172a',
      customClass: { image: 'rounded-lg max-h-[80vh]' }
    });
  }

  function currentAdmin() { try { return window.B30Store.getAdminSession() || null; } catch(e) { return null; } }
  function hasPerm(perm) {
    const a = currentAdmin(); if (!a) return false;
    try { return window.B30Store.adminHasPermission(a, perm); } catch(e) { return a.role === 'super_admin'; }
  }
  function applyPermVisibility() {
    document.querySelectorAll('[data-perm]').forEach(el => {
      const p = el.dataset.perm;
      el.style.display = hasPerm(p) ? '' : 'none';
    });
  }

  function showPanel() {
    document.getElementById('adminGate').classList.add('hidden');
    document.getElementById('adminPanel').classList.remove('hidden');
    const a = currentAdmin();
    if (a) {
      const w = document.getElementById('adminWho');
      w.classList.remove('hidden');
      w.innerHTML = `<i data-lucide="user-check" class="w-3 h-3"></i> ${esc(a.full_name || a.email)} · ${esc(a.role || 'admin')}`;
    }
    applyPermVisibility();
    refreshAll();
  }
  function showGate() {
    document.getElementById('adminPanel').classList.add('hidden');
    document.getElementById('adminGate').classList.remove('hidden');
  }

  async function refreshAll() {
    const users = await window.B30Store.listUsers();
    const txs   = await window.B30Store.listTransactions();
    const pairs = await window.B30Store.listPairings();

    document.getElementById('kpiUsers').textContent = users.length;
    const pending = txs.filter(t => t.status === 'pending');
    document.getElementById('kpiPending').textContent = pending.length;
    document.getElementById('kpiVerified').textContent = pairs.filter(p => p.status === 'verified').length;

    // open penalties
    const allPens = await listPenalties();
    document.getElementById('kpiPenalties').textContent = allPens.filter(p => p.status === 'open').length;

    if (hasPerm('verify_transactions')) await renderPending(pending, users);
    if (hasPerm('verify_transactions')) await renderAllPairings(pairs, txs, users);
    if (hasPerm('manage_users')) renderUsers(users);
    if (hasPerm('manage_admins')) await renderAdmins();
    if (hasPerm('manage_affiliate')) await renderAffiliate(users);
    if (hasPerm('manage_users')) await renderPenalties(allPens, users);
    if (hasPerm('manage_keys')) await renderKeys();
    if (hasPerm('manage_ecosystem')) await renderEcosystem();
    if (hasPerm('manage_ecosystem')) await renderProviders();
    if (hasPerm('edit_config')) await renderSecurity();
    if (hasPerm('edit_config')) renderConfig();
    if (window.B30Logos) window.B30Logos.renderAuto();
    if (window.lucide) lucide.createIcons();
  }

  // ---------- Penalties ----------
  const PEN_KEY = 'b30-penalties';
  async function listPenalties() {
    try { return JSON.parse(localStorage.getItem(PEN_KEY) || '[]'); }
    catch(e) { return []; }
  }
  async function savePenalty(rec) {
    const list = await listPenalties();
    list.unshift(rec);
    localStorage.setItem(PEN_KEY, JSON.stringify(list));
  }
  function makeId() { return 'p_' + Math.random().toString(36).slice(2,10) + Date.now().toString(36); }

  async function renderPenalties(items, users) {
    const list = document.getElementById('penList');
    const empty = document.getElementById('penEmpty');
    if (!items.length) { list.innerHTML=''; empty.classList.remove('hidden'); return; }
    empty.classList.add('hidden');
    const uMap = Object.fromEntries((users||[]).map(u => [u.id, u]));
    list.innerHTML = items.map(p => `
      <div class="rounded-xl border ${p.status==='open'?'border-rose-500/40 bg-rose-500/5':'border-slate-200/60 dark:border-white/10'} p-3 flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="b30-pen-pill"><i data-lucide="${p.target_type==='admin'?'shield-alert':'user-x'}" class="w-3 h-3"></i> ${esc(p.target_type)}</span>
            <span class="font-semibold">${esc((uMap[p.target_id]||{}).email || p.target_email || p.target_id)}</span>
            <span class="text-xs opacity-60">· ${esc(p.rule||'manual')}</span>
            <span class="b30-pill ${p.status==='open'?'b30-pill-pending':'b30-pill-verified'}">${esc(p.status)}</span>
          </div>
          <p class="text-xs opacity-70 mt-1">${esc(p.reason||'')}</p>
          <p class="text-[11px] opacity-60 mt-1">${new Date(p.created_at).toLocaleString()} · ${esc(p.actor_email||'admin')}</p>
        </div>
        <div class="text-right">
          <p class="text-xs uppercase opacity-60">${t('admin.penalties.amount','Penalty')}</p>
          <p class="font-bold text-rose-500">−${(+p.credits_penalty||0).toFixed(2)} C</p>
        </div>
        <div class="flex items-center gap-2">
          ${p.status==='open' ? `<button data-resolve="${p.id}" class="wb-btn-ghost rounded-lg px-3 py-1.5 text-xs font-semibold">${t('admin.penalties.resolve','Resolve')}</button>` : ''}
        </div>
      </div>`).join('');
    list.querySelectorAll('[data-resolve]').forEach(b => b.addEventListener('click', async () => {
      const id = b.dataset.resolve;
      const all = await listPenalties();
      const rec = all.find(x => x.id === id);
      if (rec) rec.status = 'resolved';
      localStorage.setItem(PEN_KEY, JSON.stringify(all));
      refreshAll();
    }));
    if (window.lucide) lucide.createIcons();
  }

  async function openNewPenaltyDialog() {
    const users = await window.B30Store.listUsers();
    const admins = await window.B30Store.listAdmins();
    const rules = (penaltyCfg && penaltyCfg.rules) || {};
    const ruleOpts = Object.keys(rules).map(k =>
      `<option value="${esc(k)}" data-amt="${rules[k].credits_penalty||0}" data-t="${rules[k].target_type||'user'}">${esc(rules[k].label || k)} (−${rules[k].credits_penalty||0} C)</option>`).join('');
    const userOpts = users.map(u => `<option value="user:${u.id}" data-email="${esc(u.email)}">USER · ${esc(u.email)}</option>`).join('');
    const adminOpts = admins.map(a => `<option value="admin:${a.id}" data-email="${esc(a.email)}">ADMIN · ${esc(a.email)} (${esc(a.role)})</option>`).join('');
    const { value: form } = await Swal.fire({
      title: t('admin.penalties.new','New penalty'),
      html: `
        <select id="penTarget" class="swal2-select">${userOpts}${adminOpts}</select>
        <select id="penRule" class="swal2-select"><option value="manual">${esc(t('admin.penalties.manual','Manual'))}</option>${ruleOpts}</select>
        <input id="penAmt" class="swal2-input" type="number" step="0.01" placeholder="${esc(t('admin.penalties.amount','Credits penalty'))}">
        <textarea id="penReason" class="swal2-textarea" placeholder="${esc(t('admin.penalties.reason','Reason'))}"></textarea>`,
      focusConfirm:false,
      preConfirm:() => {
        const tgt = document.getElementById('penTarget').value || '';
        const [tt, tid] = tgt.split(':');
        const ruleEl = document.getElementById('penRule');
        const ruleVal = ruleEl.value;
        const amt = +document.getElementById('penAmt').value;
        if (!tt || !tid) { Swal.showValidationMessage(t('admin.penalties.errTarget','Choose a target')); return false; }
        if (!(amt > 0)) { Swal.showValidationMessage(t('admin.penalties.errAmt','Enter a positive credit amount')); return false; }
        const reason = document.getElementById('penReason').value || '';
        const sel = ruleEl.options[ruleEl.selectedIndex];
        return { target_type: tt, target_id: tid, rule: ruleVal, credits_penalty: amt, reason, target_email: sel ? sel.getAttribute('data-email') : '' };
      }
    });
    if (!form) return;
    const me = currentAdmin();
    const targetEmail = (function() {
      if (form.target_type === 'user') return (users.find(u => u.id === form.target_id) || {}).email || '';
      return (admins.find(a => a.id === form.target_id) || {}).email || '';
    })();
    const rec = {
      id: makeId(),
      created_at: Date.now(),
      target_type: form.target_type,
      target_id:   form.target_id,
      target_email: targetEmail || form.target_email,
      rule:        form.rule,
      credits_penalty: form.credits_penalty,
      reason:      form.reason,
      actor_id:    me ? me.id : null,
      actor_email: me ? me.email : 'super_admin',
      status: 'open'
    };
    await savePenalty(rec);
    // Try to debit credits for users (best-effort)
    if (form.target_type === 'user') {
      try {
        const u = await window.B30Store.getUser(form.target_id);
        if (u && typeof window.B30Store.adjustUserCredits === 'function') {
          await window.B30Store.adjustUserCredits(u.id, -Math.abs(form.credits_penalty), `penalty:${rec.id}`);
        }
      } catch(e) {}
    }
    Swal.fire({ icon:'success', timer:1100, showConfirmButton:false, title: t('admin.penalties.created','Penalty recorded.') });
    refreshAll();
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('#btnNewPenalty')) openNewPenaltyDialog();
    if (e.target.closest('#btnEditPenaltyCfg')) editPenaltyRules();
  });
  async function editPenaltyRules() {
    const current = JSON.stringify(penaltyCfg || { rules:{} }, null, 2);
    const { value } = await Swal.fire({
      title: t('admin.penalties.editTitle','Edit penalty rules'),
      html: `<textarea id="penEditor" class="swal2-textarea" style="height:300px;font-family:monospace;font-size:.75rem">${esc(current)}</textarea>`,
      width: 720,
      focusConfirm:false,
      preConfirm:() => {
        try {
          const v = JSON.parse(document.getElementById('penEditor').value);
          return v;
        } catch(e) { Swal.showValidationMessage('Invalid JSON: ' + e.message); return false; }
      }
    });
    if (!value) return;
    penaltyCfg = value;
    localStorage.setItem('b30-penalties-override', JSON.stringify(value));
    Swal.fire({ icon:'success', timer:900, showConfirmButton:false, title:'Saved.' });
  }

  // ---------- Pending tx ----------
  async function renderPending(pending, users) {
    const container = document.getElementById('pendingList');
    const empty = document.getElementById('pendingEmpty');
    if (pending.length === 0) {
      container.innerHTML = ''; empty.classList.remove('hidden'); return;
    }
    empty.classList.add('hidden');
    const userMap = Object.fromEntries(users.map(u => [u.id, u]));
    const blocks = await Promise.all(pending.map(async tx => {
      const u = userMap[tx.user_id] || { email: 'unknown' };
      const shot = tx.screenshot_id ? await window.B30Store.getScreenshot(tx.screenshot_id) : null;
      return `
        <div class="b30-card flex flex-wrap items-start gap-4">
          ${shot ? `<img src="${shot.data_url}" class="b30-thumb" data-imgpop="${shot.id}" style="width:96px;height:96px" alt="proof">`
                 : `<div class="w-24 h-24 rounded-lg bg-slate-200 dark:bg-white/5 flex items-center justify-center text-xs opacity-60">${esc(t('admin.noImage','no image'))}</div>`}
          <div class="flex-1 min-w-[220px]">
            <p class="text-sm opacity-70">${esc(u.email)} · ${esc(tx.platform.toUpperCase())}</p>
            <p class="font-semibold">${esc(tx.type.toUpperCase())} · ${tx.amount_usdt} USDT for ${tx.amount_fiat} ${esc(tx.fiat_currency)}</p>
            <p class="text-xs opacity-60 mt-1">${t('admin.pending.fee','Fee')} ${tx.fee_percent}% · ${t('admin.pending.total','total')} ${(+tx.total_cost||0).toFixed(2)} ${esc(tx.fiat_currency)} · ${new Date(tx.transaction_date).toLocaleDateString()}</p>
            ${tx.single_op ? `<p class="text-[11px] opacity-60">${esc(t('activity.kindOp','Single op'))}</p>` : ''}
          </div>
          <div class="flex items-center gap-2">
            ${shot ? `<button class="wb-btn-ghost rounded-lg px-3 py-2 text-xs font-semibold" data-imgpop="${shot.id}"><i data-lucide="image" class="w-3.5 h-3.5 inline"></i> ${esc(t('admin.viewImage','View'))}</button>` : ''}
            <button data-action="approve" data-tx="${tx.id}" class="wb-btn-primary rounded-lg px-3 py-2 text-xs font-semibold">
              <i data-lucide="check" class="w-3.5 h-3.5 inline"></i> <span>${esc(t('admin.approve','Approve'))}</span>
            </button>
            <button data-action="reject" data-tx="${tx.id}" class="wb-btn-ghost rounded-lg px-3 py-2 text-xs font-semibold">
              <i data-lucide="x" class="w-3.5 h-3.5 inline"></i> <span>${esc(t('admin.reject','Reject'))}</span>
            </button>
          </div>
        </div>`;
    }));
    container.innerHTML = blocks.join('');
    container.querySelectorAll('[data-imgpop]').forEach(el => el.addEventListener('click', async () => {
      const sh = await window.B30Store.getScreenshot(el.dataset.imgpop);
      if (sh) imgPopup(sh.data_url);
    }));
    container.querySelectorAll('button[data-action]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.tx; const act = btn.dataset.action;
        let notes = '';
        if (act === 'reject') {
          const { value } = await Swal.fire({ title: t('admin.notes','Reason / notes'), input: 'text' });
          notes = value || '';
        }
        await window.B30Store.setTransactionStatus(id, act === 'approve' ? 'verified' : 'rejected', notes);
        Swal.fire({ icon:'success', timer:1100, showConfirmButton:false,
          title: t(act === 'approve' ? 'admin.toast.approved' : 'admin.toast.rejected', 'Done') });
        refreshAll();
      });
    });
    if (window.lucide) lucide.createIcons();
  }

  // ---------- All pairings (every user) ----------
  let allCache = [];
  let allFilter = 'all';
  let allText = '';
  async function renderAllPairings(pairs, txs, users) {
    const list  = document.getElementById('allPairingsList');
    const empty = document.getElementById('allPairingsEmpty');
    const uMap  = Object.fromEntries(users.map(u => [u.id, u]));
    // Build items: each pairing + each single-op tx
    const items = [];
    async function thumbFor(id) {
      if (!id) return null;
      try { const sc = await window.B30Store.getScreenshot(id); return (sc && sc.data_url) || null; }
      catch(e) { return null; }
    }
    for (const p of pairs) {
      const b = await thumbFor(p.buy_screenshot_id);
      const s = await thumbFor(p.sell_screenshot_id);
      items.push({
        kind:'pair', id:p.pairing_id, ts:p.pairing_date,
        user:uMap[p.user_id] || { email:'?' }, platform:p.platform, status:p.status,
        title:`${p.platform[0].toUpperCase()+p.platform.slice(1)} · pair ${p.pairing_id.slice(-5)}`,
        amounts:`${(+p.buy_amount_usdt||0)}↓ / ${(+p.sell_amount_usdt||0)}↑ USDT`,
        thumbs: [b,s].filter(Boolean), credits:p.credits_earned||0
      });
    }
    const singleOps = txs.filter(t => t.single_op === true);
    for (const t of singleOps) {
      const th = await thumbFor(t.screenshot_id);
      items.push({
        kind:'op', id:t.id, ts:t.transaction_date || t.created_at,
        user:uMap[t.user_id] || { email:'?' }, platform:t.platform, status:t.status,
        title:`${(t.platform||'').toUpperCase()} · ${(t.type||'').toUpperCase()} ${t.amount_usdt} USDT`,
        amounts:`${t.amount_usdt} USDT @ ${t.amount_fiat} ${t.fiat_currency||''}`,
        thumbs: th ? [th] : [], credits:t.credits_earned||0
      });
    }
    items.sort((a,b) => (b.ts||0) - (a.ts||0));
    allCache = items;
    paintAll();
    document.querySelectorAll('[data-allf]').forEach(b => b.addEventListener('click', () => {
      document.querySelectorAll('[data-allf]').forEach(x => x.classList.remove('is-active','bg-blue-500/10'));
      b.classList.add('is-active','bg-blue-500/10');
      allFilter = b.dataset.allf;
      paintAll();
    }));
    document.getElementById('allFilterText').oninput = (e) => { allText = (e.target.value||'').toLowerCase().trim(); paintAll(); };

    function paintAll() {
      const filt = allCache.filter(it =>
        (allFilter==='all' || it.status===allFilter) &&
        (!allText || (it.user.email||'').toLowerCase().includes(allText) || (it.id||'').toLowerCase().includes(allText))
      );
      if (!filt.length) { list.innerHTML=''; empty.classList.remove('hidden'); return; }
      empty.classList.add('hidden');
      list.innerHTML = filt.map(it => `
        <div class="b30-card flex flex-wrap items-center justify-between gap-3 b30-tile">
          <div class="flex items-center gap-3 min-w-0">
            <span data-b30-platform="${it.platform}" data-size="36"></span>
            <div class="min-w-0">
              <p class="text-xs uppercase opacity-60">${new Date(it.ts).toLocaleString()}</p>
              <p class="font-semibold truncate">${esc(it.title)}</p>
              <p class="text-[11px] opacity-70 mt-0.5">${esc(it.user.email)} · ${esc(it.amounts)}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            ${it.thumbs.map(src => `<img src="${src}" class="b30-thumb" data-img-popup alt="screenshot">`).join('') || `<span class="text-[11px] opacity-60">${esc(t('activity.noShot','No screenshot'))}</span>`}
          </div>
          <div class="flex items-center gap-3">
            <div class="text-right">
              <p class="text-[10px] uppercase opacity-60">${esc(t('dash.credits','Credits'))}</p>
              <p class="font-bold">${it.credits}</p>
            </div>
            <span class="b30-pill b30-pill-${it.status}">${esc(t('status.'+it.status, it.status))}</span>
            ${it.status==='pending' && it.kind==='op' ? `
              <button data-app="${it.id}" class="wb-btn-primary rounded-lg px-2 py-1 text-[11px] font-semibold">${esc(t('admin.approve','Approve'))}</button>
              <button data-rej="${it.id}" class="wb-btn-ghost rounded-lg px-2 py-1 text-[11px] font-semibold">${esc(t('admin.reject','Reject'))}</button>` : ''}
          </div>
        </div>`).join('');
      list.querySelectorAll('[data-img-popup]').forEach(img => img.addEventListener('click', () => imgPopup(img.src)));
      list.querySelectorAll('[data-app]').forEach(b => b.addEventListener('click', async () => {
        await window.B30Store.setTransactionStatus(b.dataset.app, 'verified', '');
        refreshAll();
      }));
      list.querySelectorAll('[data-rej]').forEach(b => b.addEventListener('click', async () => {
        const { value } = await Swal.fire({ title: t('admin.notes','Reason / notes'), input:'text' });
        await window.B30Store.setTransactionStatus(b.dataset.rej, 'rejected', value||'');
        refreshAll();
      }));
      if (window.B30Logos) window.B30Logos.renderAuto();
      if (window.lucide) lucide.createIcons();
    }
  }

  // ---------- Admins tree ----------
  async function renderAdmins() {
    const all = await window.B30Store.listAdmins();
    const me = currentAdmin();
    const isSuper = me && me.role === 'super_admin';
    const list = document.getElementById('adminsList');
    list.innerHTML = all.map(a => `
      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="b30-pill ${a.role==='super_admin' ? 'b30-pill-verified' : a.enabled ? 'b30-pill-verified' : 'b30-pill-rejected'}">
              <i data-lucide="${a.role==='super_admin'?'crown':'shield'}" class="w-3 h-3"></i> ${esc(a.role)}
            </span>
            <span class="font-semibold">${esc(a.full_name || a.email)}</span>
            <span class="text-xs opacity-60">· ${esc(a.email)}</span>
            ${!a.enabled && a.role !== 'super_admin' ? `<span class="b30-pen-pill">${esc(t('admin.admins.disabled','disabled'))}</span>` : ''}
          </div>
          <p class="text-xs opacity-70 mt-1">${esc(t('admin.admins.permissions','Permissions'))}: ${(a.permissions||[]).join(', ') || '—'}</p>
        </div>
        <div class="flex items-center gap-1.5">
          ${isSuper && a.role!=='super_admin' ? `
            <button class="wb-btn-ghost rounded-lg px-2 py-1.5 text-[11px] font-semibold" data-edit="${a.id}"><i data-lucide="edit-3" class="w-3 h-3 inline"></i> ${esc(t('cta.edit','Edit'))}</button>
            <button class="wb-btn-ghost rounded-lg px-2 py-1.5 text-[11px] font-semibold" data-toggle="${a.id}">${a.enabled ? esc(t('admin.admins.disable','Disable')) : esc(t('admin.admins.enable','Enable'))}</button>
            <button class="wb-btn-ghost rounded-lg px-2 py-1.5 text-[11px] font-semibold text-rose-500" data-del="${a.id}"><i data-lucide="trash-2" class="w-3 h-3 inline"></i> ${esc(t('admin.admins.delete','Delete'))}</button>
          ` : ''}
        </div>
      </div>`).join('');
    list.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', async () => {
      const a = (await window.B30Store.listAdmins()).find(x => x.id === b.dataset.toggle);
      if (!a) return;
      await window.B30Store.updateSubAdmin(a.id, { enabled: !a.enabled });
      refreshAll();
    }));
    list.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', async () => {
      const ok = await Swal.fire({ icon:'warning', title:t('admin.admins.delConfirm','Delete this admin?'), showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:t('admin.admins.delete','Delete'), cancelButtonText:t('cta.cancel','Cancel') });
      if (!ok.isConfirmed) return;
      try { await window.B30Store.deleteSubAdmin(b.dataset.del); refreshAll(); }
      catch(e) { Swal.fire({ icon:'error', title:e.message }); }
    }));
    list.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', async () => {
      const a = (await window.B30Store.listAdmins()).find(x => x.id === b.dataset.edit);
      if (!a) return;
      openAdminDialog(a);
    }));
    if (window.lucide) lucide.createIcons();
  }
  async function openAdminDialog(existing) {
    const ALL = (window.B30Store.ALL_ADMIN_PERMS || []);
    const has = (p) => (existing && existing.permissions || []).includes(p) || (existing && existing.permissions && existing.permissions[0] === '*');
    const permCheckboxes = ALL.map(p => `
      <label class="flex items-center gap-2 text-xs px-2 py-1 rounded hover:bg-blue-500/10 cursor-pointer">
        <input type="checkbox" data-pcb value="${p}" ${has(p)?'checked':''}> ${esc(p)}
      </label>`).join('');
    const { value } = await Swal.fire({
      title: existing ? t('admin.admins.edit','Edit sub-admin') : t('admin.admins.new','New sub-admin'),
      html: `
        <input id="adEmail" class="swal2-input" placeholder="email" value="${esc((existing||{}).email||'')}" ${existing?'disabled':''}>
        <input id="adName" class="swal2-input" placeholder="${esc(t('admin.admins.name','Full name'))}" value="${esc((existing||{}).full_name||'')}">
        ${existing ? '' : `<input id="adPass" type="password" class="swal2-input" placeholder="${esc(t('admin.admins.password','Password (min 8)'))}" minlength="8">`}
        <div style="text-align:left;max-height:200px;overflow:auto;border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem;margin-top:.35rem">${permCheckboxes}</div>`,
      width: 520,
      focusConfirm:false,
      preConfirm:() => {
        const perms = Array.from(document.querySelectorAll('[data-pcb]:checked')).map(c=>c.value);
        const email = (document.getElementById('adEmail')||{}).value || (existing||{}).email;
        const full_name = (document.getElementById('adName')||{}).value || '';
        const password = existing ? null : ((document.getElementById('adPass')||{}).value || '');
        if (!existing && (!email || !password || password.length < 8)) {
          Swal.showValidationMessage(t('admin.admins.errCreate','Email + password (8+ chars) required')); return false;
        }
        if (!perms.length) { Swal.showValidationMessage(t('admin.admins.errPerm','Select at least one permission')); return false; }
        return { email, full_name, password, permissions: perms };
      }
    });
    if (!value) return;
    try {
      if (existing) {
        await window.B30Store.updateSubAdmin(existing.id, { full_name: value.full_name, permissions: value.permissions });
      } else {
        await window.B30Store.createSubAdmin(value);
      }
      Swal.fire({ icon:'success', timer:1000, showConfirmButton:false, title: t('admin.admins.saved','Saved.') });
      refreshAll();
    } catch(e) { Swal.fire({ icon:'error', title:e.message }); }
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('#btnNewAdmin')) openAdminDialog(null);
  });

  // ---------- Affiliate ----------
  async function renderAffiliate(users) {
    const uMap = Object.fromEntries((users||[]).map(u=>[u.id,u]));
    const refs    = await window.B30Store.listReferrals();
    const rewards = await window.B30Store.listAffiliateRewards();
    document.getElementById('affKpiRef').textContent = refs.length;
    document.getElementById('affKpiRew').textContent = rewards.length;
    document.getElementById('affKpiCr').textContent  = rewards.reduce((s,r)=>s+(+r.credits_awarded||0),0).toFixed(2);
    const tb = document.getElementById('affTbody');
    rewards.sort((a,b)=>(b.created_at||0)-(a.created_at||0));
    if (!rewards.length) {
      tb.innerHTML = `<tr><td class="p-3 text-center opacity-60" colspan="5">${esc(t('admin.affiliate.empty','No rewards yet.'))}</td></tr>`;
      return;
    }
    tb.innerHTML = rewards.map(r => {
      const ref = uMap[r.referrer_id] || {email:'?'};
      const ree = uMap[r.referee_id]  || {email:'?'};
      return `<tr class="border-t border-slate-200/40 dark:border-white/5">
        <td class="p-2 text-xs opacity-70">${new Date(r.created_at).toLocaleString()}</td>
        <td class="p-2">${esc(ref.email)}</td>
        <td class="p-2">${esc(ree.email)}</td>
        <td class="p-2 text-right font-semibold text-emerald-500">+${(+r.credits_awarded||0).toFixed(2)}</td>
        <td class="p-2 text-xs opacity-70">${esc(r.source_type||'')}</td>
      </tr>`;
    }).join('');
  }

  // ---------- Users ----------
  function renderUsers(users) {
    const tb = document.getElementById('usersTbody');
    if (users.length === 0) {
      tb.innerHTML = `<tr><td class="p-3 text-center opacity-60" colspan="7">${esc(t('admin.users.empty','No users yet.'))}</td></tr>`;
      return;
    }
    tb.innerHTML = users.map(u => `
      <tr class="border-t border-slate-200/40 dark:border-white/5">
        <td class="p-2">
          <div class="font-medium">${esc(u.full_name || u.email)}</div>
          <div class="text-xs opacity-60">${esc(u.email)}</div>
        </td>
        <td class="p-2">${esc(u.country_code || '—')}</td>
        <td class="p-2 text-right"><span class="b30-pill b30-pill-verified">L${u.level || 0}</span></td>
        <td class="p-2 text-right font-semibold">${(+u.total_credits || 0).toFixed(2)}</td>
        <td class="p-2 text-right">${u.total_pairings || 0}</td>
        <td class="p-2 text-right text-xs opacity-70">${esc(u.referral_code||'—')}<br>${u.referrals_count||0} ref</td>
        <td class="p-2 text-right text-xs opacity-70">${new Date(u.created_at).toLocaleDateString()}</td>
      </tr>`).join('');
  }

  // ---------- API Keys ----------
  async function renderKeys () {
    const keys  = await window.B30Store.listApiKeys();
    const users = await window.B30Store.listUsers();
    const umap  = Object.fromEntries(users.map(u => [u.id, u]));
    const list  = document.getElementById('adminKeysList');
    const empty = document.getElementById('adminKeysEmpty');
    if (!keys.length) { list.innerHTML = ''; empty.classList.remove('hidden'); return; }
    empty.classList.add('hidden');
    list.innerHTML = keys.map(k => {
      const u = umap[k.user_id] || { email: k.user_id ? '(missing)' : '(global)' };
      return `
      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="b30-pill ${k.enabled ? 'b30-pill-verified' : 'b30-pill-rejected'}">
              <i data-lucide="${k.enabled?'check':'pause'}" class="w-3 h-3"></i> ${k.enabled? esc(t('admin.keys.active','Active')) : esc(t('admin.keys.disabled','Disabled'))}
            </span>
            <span class="font-semibold">${esc(k.label || 'Untitled')}</span>
            <span class="text-xs opacity-60">· ${esc(t('admin.keys.owner','owner'))}: ${esc(u.email)}</span>
          </div>
          <code class="block mt-1 text-xs bg-slate-100 dark:bg-white/5 px-2 py-1 rounded break-all">${esc(k.key)}</code>
          <p class="text-xs opacity-60 mt-1">${esc(t('admin.keys.scopes','Scopes'))}: ${(k.scopes||[]).join(', ')} · ${esc(t('admin.keys.origins','Origins'))}: ${(k.origins||['*']).join(', ')}</p>
        </div>
        <div class="flex items-center gap-2">
          <button class="wb-btn-ghost rounded-lg px-3 py-1.5 text-xs font-semibold" data-tk="${k.id}">${k.enabled ? esc(t('admin.keys.disable','Disable')) : esc(t('admin.keys.enable','Enable'))}</button>
          <button class="wb-btn-ghost rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-500" data-dk="${k.id}"><i data-lucide="trash-2" class="w-3 h-3 inline"></i> ${esc(t('admin.keys.delete','delete'))}</button>
        </div>
      </div>`;
    }).join('');
    list.querySelectorAll('[data-tk]').forEach(b => b.addEventListener('click', async () => {
      const rec = keys.find(x => x.id === b.dataset.tk);
      await window.B30Store.updateApiKey(b.dataset.tk, { enabled: !rec.enabled });
      renderKeys();
    }));
    list.querySelectorAll('[data-dk]').forEach(b => b.addEventListener('click', async () => {
      const ok = await Swal.fire({ icon:'warning', title:t('admin.keys.delConfirm','Delete this key?'), showCancelButton:true, confirmButtonText:t('admin.keys.delete','Delete'), confirmButtonColor:'#dc2626', cancelButtonText:t('cta.cancel','Cancel') });
      if (!ok.isConfirmed) return;
      await window.B30Store.deleteApiKey(b.dataset.dk);
      renderKeys();
    }));
    if (window.lucide) lucide.createIcons();
  }

  // ---------- Ecosystem ----------
  let ecoData = null;
  async function loadEcoConfig () {
    const stored = localStorage.getItem('b30-ecosystem-override');
    if (stored) { try { return JSON.parse(stored); } catch (e) {} }
    try {
      const res = await fetch('../ecosystem-currencies.json', { cache:'no-cache' });
      return await res.json();
    } catch (e) { return { projects: [] }; }
  }
  async function renderEcosystem () {
    ecoData = await loadEcoConfig();
    const grid = document.getElementById('ecoGrid');
    grid.innerHTML = (ecoData.projects || []).map(p => `
      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <div class="flex items-center gap-3">
          <span data-b30-currency="${p.currency}" data-primary="${p.color_primary}" data-accent="${p.color_accent}" data-size="44" class="block flex-shrink-0"></span>
          <div class="min-w-0">
            <p class="font-display font-bold text-sm truncate">${esc(p.name_en)}</p>
            <p class="text-xs opacity-60">${esc(p.currency_name_en||'')}</p>
          </div>
        </div>
        <label class="block mt-3">
          <span class="text-xs uppercase tracking-widest opacity-70">${esc(t('admin.ecosystem.rate','Rate per 1 B30C'))}</span>
          <input type="number" step="0.01" min="0" data-eco-rate="${p.code}" value="${p.credit_to_currency_rate}" class="b30-input mt-1">
        </label>
        <p class="text-xs opacity-60 mt-2">${esc(t('admin.ecosystem.status','Status'))}: ${esc(p.status)} · ${esc(t('admin.ecosystem.year','Year'))}: ${p.year}</p>
      </div>`).join('');
    if (window.B30Logos) window.B30Logos.renderAuto();
  }
  document.getElementById('ecoSave')?.addEventListener('click', () => {
    if (!ecoData) return;
    document.querySelectorAll('[data-eco-rate]').forEach(inp => {
      const code = inp.dataset.ecoRate;
      const p = (ecoData.projects || []).find(x => x.code === code);
      if (p) p.credit_to_currency_rate = +inp.value || p.credit_to_currency_rate;
    });
    localStorage.setItem('b30-ecosystem-override', JSON.stringify(ecoData));
    document.getElementById('ecoStatus').textContent = t('admin.ecosystem.saved','Saved locally ·') + ' ' + new Date().toLocaleTimeString();
    Swal.fire({ icon:'success', timer:900, showConfirmButton:false, title: t('admin.ecosystem.toast','Ecosystem rates saved') });
    if (typeof window.B30Store.loadEcosystem === 'function') window.B30Store.loadEcosystem(true);
  });
  document.getElementById('ecoReset')?.addEventListener('click', async () => {
    localStorage.removeItem('b30-ecosystem-override');
    await renderEcosystem();
    document.getElementById('ecoStatus').textContent = t('admin.ecosystem.reset','Reset to defaults ·') + ' ' + new Date().toLocaleTimeString();
  });

  // ====================== P2P PROVIDERS TAB ======================
  let provData = null;
  async function loadProvConfig () {
    const stored = localStorage.getItem('b30-providers-override');
    if (stored) { try { return JSON.parse(stored); } catch (e) {} }
    try {
      const res = await fetch('../p2p-providers.json', { cache:'no-cache' });
      return await res.json();
    } catch (e) { return { default_provider:'binance', providers: [] }; }
  }
  const FIAT_LIST = ['USD','EUR','GBP','TND','MAD','EGP','SAR','AED','TRY','BRL','NGN','INR','RUB','CNY','JPY','CAD','AUD','CHF'];
  async function renderProviders () {
    provData = await loadProvConfig();
    const list = document.getElementById('provList');
    if (!list) return;
    const provs = provData.providers || [];
    list.innerHTML = provs.map((p, idx) => `
      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4" data-prov-idx="${idx}">
        <div class="flex items-center gap-3 flex-wrap">
          <span data-b30-platform="${esc(p.code)}" data-size="40" class="inline-block flex-shrink-0"></span>
          <div class="min-w-0 flex-1">
            <p class="font-display font-bold text-sm truncate">${esc(p.name_en || p.code)}</p>
            <p class="text-xs opacity-60">${esc(p.code)} · ${esc(p.website || '')}</p>
          </div>
          <label class="inline-flex items-center gap-2 text-xs font-semibold cursor-pointer">
            <input type="checkbox" data-prov-enabled ${p.enabled !== false ? 'checked' : ''}>
            <span data-i18n="admin.providers.enabled">Enabled</span>
          </label>
          <button class="wb-btn-ghost rounded-lg px-2.5 py-1 text-xs" data-prov-del="${idx}" title="${esc(t('admin.providers.delete','Delete'))}">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
          </button>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2 mt-3">
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.code">Code</span>
            <input data-prov-field="code" value="${esc(p.code)}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.nameEn">Name (EN)</span>
            <input data-prov-field="name_en" value="${esc(p.name_en||'')}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.website">Website</span>
            <input data-prov-field="website" value="${esc(p.website||'')}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.verifyUrl">Verify URL</span>
            <input data-prov-field="verify_url" value="${esc(p.verify_url||'')}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.level">Level required</span>
            <input type="number" min="1" max="4" data-prov-field="level_required" value="${p.level_required||1}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.minBuy">Min buy USDT</span>
            <input type="number" step="0.01" data-prov-field="min_usdt_buy" value="${p.min_usdt_buy||0}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.minSell">Min sell USDT</span>
            <input type="number" step="0.01" data-prov-field="min_usdt_sell" value="${p.min_usdt_sell||0}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.maxOp">Max USDT / op</span>
            <input type="number" step="0.01" data-prov-field="max_usdt_per_op" value="${p.max_usdt_per_op||0}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.feeBuy">Fee buy %</span>
            <input type="number" step="0.01" data-prov-field="fee_buy_percent" value="${p.fee_buy_percent||0}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.feeSell">Fee sell %</span>
            <input type="number" step="0.01" data-prov-field="fee_sell_percent" value="${p.fee_sell_percent||0}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.uidLabel">UID label (EN)</span>
            <input data-prov-field="uid_label_en" value="${esc(p.uid_label_en||'')}" class="b30-input mt-1 text-xs">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.uidRegex">UID regex</span>
            <input data-prov-field="uid_regex" value="${esc(p.uid_regex||'')}" class="b30-input mt-1 text-xs font-mono">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.colorPrimary">Primary</span>
            <input type="color" data-prov-field="color_primary" value="${esc(p.color_primary||'#F0B90B')}" class="b30-input mt-1 h-8 p-0">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase opacity-70" data-i18n="admin.providers.colorAccent">Accent</span>
            <input type="color" data-prov-field="color_accent" value="${esc(p.color_accent||'#0B0E11')}" class="b30-input mt-1 h-8 p-0">
          </label>
        </div>
        <div class="mt-3">
          <p class="text-[11px] uppercase opacity-70 mb-1" data-i18n="admin.providers.fiat">Supported fiat</p>
          <div class="flex flex-wrap gap-1.5">
            ${FIAT_LIST.map(f => `
              <label class="inline-flex items-center gap-1 text-xs cursor-pointer rounded-full border border-slate-300/40 dark:border-white/10 px-2 py-0.5">
                <input type="checkbox" data-prov-fiat="${f}" ${ (p.supported_fiat||[]).includes(f) ? 'checked' : '' }> ${f}
              </label>
            `).join('')}
          </div>
        </div>
      </div>
    `).join('');
    if (window.B30Logos) window.B30Logos.renderAuto();
    if (window.lucide) lucide.createIcons();
  }

  function collectProvData () {
    if (!provData) return null;
    const cards = document.querySelectorAll('#provList [data-prov-idx]');
    const out = { ...provData, providers: [] };
    cards.forEach(card => {
      const idx = +card.dataset.provIdx;
      const orig = (provData.providers || [])[idx] || {};
      const obj = { ...orig };
      obj.enabled = !!card.querySelector('[data-prov-enabled]')?.checked;
      card.querySelectorAll('[data-prov-field]').forEach(inp => {
        const k = inp.dataset.provField;
        let v = inp.value;
        if (inp.type === 'number') v = +v || 0;
        obj[k] = v;
      });
      obj.supported_fiat = Array.from(card.querySelectorAll('[data-prov-fiat]'))
        .filter(c => c.checked).map(c => c.dataset.provFiat);
      out.providers.push(obj);
    });
    return out;
  }

  document.getElementById('provSave')?.addEventListener('click', () => {
    const next = collectProvData();
    if (!next) return;
    localStorage.setItem('b30-providers-override', JSON.stringify(next));
    document.getElementById('provStatus').textContent = t('admin.providers.saved','Saved ·') + ' ' + new Date().toLocaleTimeString();
    Swal.fire({ icon:'success', timer:900, showConfirmButton:false, title: t('admin.providers.toast','Providers saved') });
    if (typeof window.B30Store.loadProviders === 'function') window.B30Store.loadProviders(true);
  });
  document.getElementById('provReset')?.addEventListener('click', async () => {
    localStorage.removeItem('b30-providers-override');
    await renderProviders();
    document.getElementById('provStatus').textContent = t('admin.providers.reset','Reset to file ·') + ' ' + new Date().toLocaleTimeString();
    if (typeof window.B30Store.loadProviders === 'function') window.B30Store.loadProviders(true);
  });
  document.getElementById('provList')?.addEventListener('click', async (e) => {
    const del = e.target.closest('[data-prov-del]');
    if (!del) return;
    const idx = +del.dataset.provDel;
    const next = collectProvData(); if (!next) return;
    const p = next.providers[idx];
    const conf = await Swal.fire({
      icon:'warning',
      title: t('admin.providers.deleteTitle','Delete provider?'),
      text: p ? (p.name_en || p.code) : '',
      showCancelButton:true,
      confirmButtonText: t('admin.providers.delete','Delete')
    });
    if (!conf.isConfirmed) return;
    next.providers.splice(idx, 1);
    provData = next;
    localStorage.setItem('b30-providers-override', JSON.stringify(next));
    await renderProviders();
    if (typeof window.B30Store.loadProviders === 'function') window.B30Store.loadProviders(true);
  });
  document.getElementById('btnNewProvider')?.addEventListener('click', async () => {
    const { value: form } = await Swal.fire({
      title: t('admin.providers.newTitle','New P2P provider'),
      html:
        `<input id="npCode"  class="swal2-input" placeholder="${esc(t('admin.providers.code','Code (e.g. bybit)'))}"/>` +
        `<input id="npName"  class="swal2-input" placeholder="${esc(t('admin.providers.nameEn','Name (EN)'))}"/>` +
        `<input id="npSite"  class="swal2-input" placeholder="${esc(t('admin.providers.website','Website https://...'))}"/>`,
      showCancelButton:true,
      focusConfirm:false,
      preConfirm: () => {
        const code = document.getElementById('npCode').value.trim().toLowerCase();
        const name = document.getElementById('npName').value.trim();
        const site = document.getElementById('npSite').value.trim();
        if (!code || !name) { Swal.showValidationMessage(t('admin.providers.requireCodeName','Code and name are required')); return false; }
        return { code, name_en:name, website:site };
      }
    });
    if (!form) return;
    if (!provData) provData = { providers: [] };
    if ((provData.providers||[]).some(p => (p.code||'').toLowerCase() === form.code)) {
      return Swal.fire({ icon:'error', title: t('admin.providers.exists','Code already exists') });
    }
    provData.providers.push({
      code: form.code, name_en: form.name_en, website: form.website,
      enabled: false, level_required: 1,
      min_usdt_buy: 1, min_usdt_sell: 1, max_usdt_per_op: 100000,
      fee_buy_percent: 0, fee_sell_percent: 0,
      supported_fiat: ['USD','EUR'],
      uid_label_en: 'UID', uid_regex: '^[A-Za-z0-9]{4,32}$',
      color_primary: '#6366F1', color_accent: '#0B0E11', logo: ''
    });
    localStorage.setItem('b30-providers-override', JSON.stringify(provData));
    await renderProviders();
    if (typeof window.B30Store.loadProviders === 'function') window.B30Store.loadProviders(true);
  });

  // ====================== SECURITY POLICY TAB ======================
  let secData = null;
  async function loadSecConfig () {
    const stored = localStorage.getItem('b30-security-override');
    if (stored) { try { return JSON.parse(stored); } catch (e) {} }
    try {
      const res = await fetch('../security.json', { cache:'no-cache' });
      return await res.json();
    } catch (e) { return {}; }
  }
  function secNumber(path, def) {
    const v = secData; const parts = path.split('.'); let cur = v;
    for (const p of parts) { if (cur && p in cur) cur = cur[p]; else return def; }
    return cur == null ? def : cur;
  }
  function setSecPath(path, val) {
    const parts = path.split('.'); let cur = secData;
    for (let i=0; i<parts.length-1; i++) {
      if (!cur[parts[i]] || typeof cur[parts[i]] !== 'object') cur[parts[i]] = {};
      cur = cur[parts[i]];
    }
    cur[parts[parts.length-1]] = val;
  }
  async function renderSecurity () {
    secData = await loadSecConfig();
    const wrap = document.getElementById('secCards');
    if (!wrap) return;
    const num = (p, d) => `<input type="number" data-sec="${p}" value="${secNumber(p, d)}" class="b30-input mt-1 text-xs">`;
    const bool = (p, d) => `<label class="inline-flex items-center gap-2 text-xs cursor-pointer mt-1"><input type="checkbox" data-sec="${p}" data-sec-type="bool" ${secNumber(p, d) ? 'checked' : ''}> <span data-i18n="admin.security.enable">Enabled</span></label>`;
    const txt = (p, d) => `<input type="text" data-sec="${p}" value="${esc(secNumber(p, d))}" class="b30-input mt-1 text-xs">`;
    const csvArr = (p) => { const a = secNumber(p, []); return Array.isArray(a) ? a.join(', ') : ''; };
    const csv = (p) => `<input type="text" data-sec="${p}" data-sec-type="csv" value="${esc(csvArr(p))}" class="b30-input mt-1 text-xs" placeholder="comma, separated">`;

    wrap.innerHTML = `
      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm" data-i18n="admin.security.auth">Authentication</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.bcryptCost">bcrypt cost</span>${num('auth.bcrypt_cost', 12)}</label>
          <label><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.sessionTtl">Session TTL (min)</span>${num('auth.session_ttl_minutes', 60)}</label>
          <label><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.minPwdLen">Min password length</span>${num('auth.password_min_length', 8)}</label>
          <label><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.require2fa">Require 2FA</span>${bool('auth.require_2fa_for_admin', true)}</label>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm" data-i18n="admin.security.rateLimit">Rate limits (per minute)</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70">signin</span>${num('rate_limits.signin_per_minute', 5)}</label>
          <label><span class="text-[11px] uppercase opacity-70">signup</span>${num('rate_limits.signup_per_minute', 3)}</label>
          <label><span class="text-[11px] uppercase opacity-70">admin</span>${num('rate_limits.admin_per_minute', 30)}</label>
          <label><span class="text-[11px] uppercase opacity-70">forgot</span>${num('rate_limits.forgot_per_minute', 2)}</label>
          <label><span class="text-[11px] uppercase opacity-70">api</span>${num('rate_limits.api_per_minute', 60)}</label>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm" data-i18n="admin.security.upload">Uploads</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.maxSize">Max size (MB)</span>${num('upload.max_size_mb', 5)}</label>
          <label><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.stripExif">Strip EXIF</span>${bool('upload.strip_exif', true)}</label>
          <label class="sm:col-span-2"><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.allowMime">Allowed MIME (csv)</span>${csv('upload.allowed_mime')}</label>
          <label><span class="text-[11px] uppercase opacity-70" data-i18n="admin.security.pHash">Perceptual-hash dup check</span>${bool('upload.perceptual_hash_dup_check', true)}</label>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm">CSRF / CORS / CSP</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70">CSRF enabled</span>${bool('csrf.enabled', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">CSRF token TTL (min)</span>${num('csrf.token_ttl_minutes', 60)}</label>
          <label class="sm:col-span-2"><span class="text-[11px] uppercase opacity-70">CORS allowed origins (csv)</span>${csv('cors.allowed_origins')}</label>
          <label class="sm:col-span-2"><span class="text-[11px] uppercase opacity-70">CSP Content-Security-Policy</span>${txt('csp.policy', "default-src 'self'")}</label>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm" data-i18n="admin.security.ip">IP policy</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70">Block Tor</span>${bool('ip_policy.block_tor', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Warn on VPN</span>${bool('ip_policy.warn_vpn', true)}</label>
          <label class="sm:col-span-2"><span class="text-[11px] uppercase opacity-70">Blocked countries (csv ISO-2)</span>${csv('ip_policy.blocked_countries')}</label>
          <label class="sm:col-span-2"><span class="text-[11px] uppercase opacity-70">Allowed-only countries (csv, empty = any)</span>${csv('ip_policy.allowed_only_countries')}</label>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm" data-i18n="admin.security.fraud">Fraud detection</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70">Duplicate screenshot check</span>${bool('fraud_detection.duplicate_screenshot_check', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Max ops / user / day</span>${num('fraud_detection.max_ops_per_user_per_day', 50)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Strikes before ban</span>${num('fraud_detection.strikes_before_ban', 3)}</label>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm" data-i18n="admin.security.audit">Audit</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70">Log admin actions</span>${bool('audit.log_admin_actions', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Log logins</span>${bool('audit.log_logins', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Log uploads</span>${bool('audit.log_uploads', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Retention (days)</span>${num('audit.retention_days', 365)}</label>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200/60 dark:border-white/10 p-4">
        <h4 class="font-display font-bold text-sm" data-i18n="admin.security.gdpr">GDPR / privacy</h4>
        <div class="grid sm:grid-cols-2 gap-2 mt-2">
          <label><span class="text-[11px] uppercase opacity-70">Allow user export</span>${bool('data_privacy.allow_user_export', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Allow user delete</span>${bool('data_privacy.allow_user_delete', true)}</label>
          <label><span class="text-[11px] uppercase opacity-70">Anonymize after (days)</span>${num('data_privacy.anonymize_after_days', 730)}</label>
        </div>
      </div>
    `;
    document.getElementById('securityEditor').value = JSON.stringify(secData, null, 2);
  }
  function collectSecData () {
    if (!secData) return null;
    document.querySelectorAll('#secCards [data-sec]').forEach(inp => {
      const path = inp.dataset.sec;
      const type = inp.dataset.secType;
      let v;
      if (type === 'bool') v = inp.checked;
      else if (type === 'csv') v = inp.value.split(',').map(s => s.trim()).filter(Boolean);
      else if (inp.type === 'number') v = +inp.value || 0;
      else v = inp.value;
      setSecPath(path, v);
    });
    // raw textarea override (advanced)
    try {
      const raw = document.getElementById('securityEditor').value.trim();
      if (raw) {
        const parsed = JSON.parse(raw);
        // only honor raw editor if user actually changed it (different from current secData)
        if (JSON.stringify(parsed) !== JSON.stringify(secData)) {
          // prefer fine-grained edits over raw — only use raw if cards section is empty
          // Here: use raw as authoritative since user typed JSON directly
          secData = parsed;
        }
      }
    } catch(e) { /* invalid raw JSON → ignore */ }
    return secData;
  }
  document.getElementById('secSave')?.addEventListener('click', () => {
    const next = collectSecData(); if (!next) return;
    localStorage.setItem('b30-security-override', JSON.stringify(next));
    document.getElementById('secStatus').textContent = t('admin.security.saved','Saved ·') + ' ' + new Date().toLocaleTimeString();
    Swal.fire({ icon:'success', timer:900, showConfirmButton:false, title: t('admin.security.toast','Security policy saved') });
    if (typeof window.B30Store.loadSecurity === 'function') window.B30Store.loadSecurity(true);
  });
  document.getElementById('secReset')?.addEventListener('click', async () => {
    localStorage.removeItem('b30-security-override');
    await renderSecurity();
    document.getElementById('secStatus').textContent = t('admin.security.reset','Reset to file ·') + ' ' + new Date().toLocaleTimeString();
    if (typeof window.B30Store.loadSecurity === 'function') window.B30Store.loadSecurity(true);
  });

  document.getElementById('adminNewKey')?.addEventListener('click', async () => {
    const { value: form } = await Swal.fire({
      title: t('admin.keys.newTitle','New API key (admin)'),
      html:
        `<input id="kLabel" class="swal2-input" placeholder="${esc(t('admin.keys.label','Label'))}"/>` +
        `<input id="kOrigins" class="swal2-input" placeholder="${esc(t('admin.keys.originsPh','Allowed origins (comma sep, * for any)'))}"/>`,
      preConfirm: () => ({
        label:   document.getElementById('kLabel').value || 'Admin key',
        origins: (document.getElementById('kOrigins').value || '*').split(',').map(s=>s.trim()).filter(Boolean)
      })
    });
    if (!form) return;
    const rec = await window.B30Store.createApiKey({ ...form });
    Swal.fire({ icon:'success', title: t('admin.keys.created','Key created'), html:`<code style="font-size:.75rem;word-break:break-all">${esc(rec.key)}</code>` });
    renderKeys();
  });

  function renderConfig() {
    const text = JSON.stringify(window.B30Config.get(), null, 2);
    const stored = localStorage.getItem('b30-config-override');
    document.getElementById('configEditor').value = stored || text;
  }

  function wireTabs() {
    const tabs = document.querySelectorAll('#adminTabs .tab');
    tabs.forEach(tt => tt.addEventListener('click', () => {
      // Permission gate
      const perm = tt.dataset.perm;
      if (perm && !hasPerm(perm)) {
        Swal.fire({ icon:'warning', title: t('admin.permDenied','Permission denied'), text: perm });
        return;
      }
      tabs.forEach(x => {
        x.classList.toggle('is-active', x === tt);
        x.classList.toggle('border-blue-500', x === tt);
        x.classList.toggle('text-blue-500', x === tt);
        x.classList.toggle('border-transparent', x !== tt);
      });
      document.querySelectorAll('[data-tab-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.tabPanel !== tt.dataset.tab));
    }));
    // Hide tabs the user has no perm for
    tabs.forEach(tt => {
      const perm = tt.dataset.perm;
      if (perm && !hasPerm(perm)) tt.style.display = 'none';
    });
  }

  whenReady((cfg) => {
    if (currentAdmin()) showPanel();
    else showGate();

    document.getElementById('adminLogin').addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = e.target.email.value.trim().toLowerCase();
      const pw    = e.target.pw.value;
      try {
        // Rate limit (client)
        const k = 'b30-admin-fail';
        const fails = +(localStorage.getItem(k) || '0');
        if (fails >= 6) {
          const next = +(localStorage.getItem(k+'-next') || '0');
          if (Date.now() < next) {
            return Swal.fire({ icon:'error', title:t('admin.rateLimit','Too many tries'), text:t('admin.rateLimitBody','Please wait a minute before trying again.') });
          } else {
            localStorage.removeItem(k); localStorage.removeItem(k+'-next');
          }
        }
        const ok = await window.B30Store.adminSignIn(email, pw);
        if (ok) {
          localStorage.removeItem(k); localStorage.removeItem(k+'-next');
          Swal.fire({ icon:'success', timer:900, showConfirmButton:false, title: t('admin.toast.signedIn','Welcome, admin.') });
          setTimeout(() => { showPanel(); wireTabs(); }, 600);
        } else {
          const f2 = fails + 1; localStorage.setItem(k, f2);
          if (f2 >= 6) localStorage.setItem(k+'-next', Date.now() + 60*1000);
          Swal.fire({ icon:'error', title: t('admin.toast.wrong','Wrong email or password.') });
        }
      } catch(err) {
        Swal.fire({ icon:'error', title: err.message || 'Sign-in error' });
      }
    });

    document.getElementById('adminLogout').addEventListener('click', () => {
      window.B30Store.adminSignOut();
      showGate();
    });

    document.getElementById('seedDemoBtn').addEventListener('click', async () => {
      await window.B30Store.seedDemo();
      Swal.fire({ icon:'success', timer:1100, showConfirmButton:false, title: t('admin.seedDone','Demo user created · demo@2030b.com / demo12345') });
      refreshAll();
    });

    wireTabs();

    document.getElementById('configSave').addEventListener('click', () => {
      try {
        const parsed = JSON.parse(document.getElementById('configEditor').value);
        localStorage.setItem('b30-config-override', JSON.stringify(parsed));
        Object.assign(window.B30Config.get(), parsed);
        document.getElementById('configStatus').textContent = t('admin.config.savedLocal','Saved locally ·') + ' ' + new Date().toLocaleTimeString();
        Swal.fire({ icon:'success', timer:900, showConfirmButton:false, title: t('admin.config.savedToast','Config saved (local override).') });
      } catch (e) {
        Swal.fire({ icon:'error', title: t('admin.config.invalidJson','Invalid JSON') + ': ' + e.message });
      }
    });
    document.getElementById('configReset').addEventListener('click', () => {
      localStorage.removeItem('b30-config-override');
      Swal.fire({ icon:'info', timer:900, showConfirmButton:false, title: t('admin.config.cleared','Local override cleared. Reloading…') });
      setTimeout(() => location.reload(), 800);
    });
  });

  document.addEventListener('DOMContentLoaded', () => {
    const stored = localStorage.getItem('b30-config-override');
    if (!stored) return;
    try {
      const parsed = JSON.parse(stored);
      window.B30Config.onLoad((c) => Object.assign(c, parsed));
    } catch (e) {}
  });
  document.addEventListener('b30:lang-changed', () => { if (currentAdmin()) refreshAll(); });
})();
</script>
</body>
</html>
