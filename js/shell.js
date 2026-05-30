/* 2030B P2P Pairing — Renders nav + footer on every page.
 * Place <div data-shell></div> at top and <div data-shell-foot></div> at bottom.
 * Set <body data-prefix=""> for root pages, or data-prefix="../" for /pages/.
 */
(function () {
  function tr(key, fallback) {
    try {
      if (window.B30I18n && typeof window.B30I18n.t === 'function') {
        const v = window.B30I18n.t(key);
        if (v && v !== key) return v;
      }
    } catch (e) {}
    return fallback;
  }

  function lang() {
    try { return (window.B30I18n && window.B30I18n.current()) || 'en'; } catch (e) { return 'en'; }
  }
  function ecosystem() {
    try {
      if (window.B30Store && typeof window.B30Store.getEcosystem === 'function') {
        return window.B30Store.getEcosystem() || { projects: [] };
      }
    } catch (e) {}
    return { projects: [] };
  }
  function localized(p, field) {
    const code = lang();
    return p[`${field}_${code}`] || p[`${field}_en`] || '';
  }

  function megaMenuHTML(prefix) {
    const eco = ecosystem();
    const title = tr('nav.solutions.title', 'Solutions');
    // Even if the ecosystem JSON hasn't loaded yet (or 403'd), render a
    // skeleton mega-menu so the trigger has something to open. We'll re-render
    // on the b30:ecosystem-loaded event with the real tiles.
    if (!eco.projects || !eco.projects.length) {
      const sub = tr('nav.solutions.sub', '10 ecosystem projects · 10 currencies · 1 credit fuel');
      return `
        <div class="mega-menu" data-mega-menu>
          <div class="mega-head">
            <div>
              <p class="font-display font-extrabold text-lg text-slate-900 dark:text-white">${title}</p>
              <p class="text-xs opacity-70">${sub}</p>
            </div>
            <a href="${prefix}pages/solutions.html" class="mega-link">
              ${tr('nav.solutions.viewAll', 'View all solutions')} <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
          </div>
          <div class="mega-grid">
            ${Array.from({length:5}).map(() => `
              <div class="mega-tile opacity-50 animate-pulse">
                <span class="mega-tile-badge bg-slate-300 dark:bg-slate-700 inline-block rounded-full" style="width:40px;height:40px"></span>
                <div class="h-2 rounded bg-slate-200 dark:bg-slate-700 w-3/4 mt-2"></div>
                <div class="h-2 rounded bg-slate-200 dark:bg-slate-700 w-1/2 mt-1"></div>
              </div>`).join('')}
          </div>
          <div class="mega-foot">
            <i data-lucide="loader-2" class="w-3.5 h-3.5 text-amber-500 animate-spin"></i>
            <span>${tr('nav.solutions.loading', 'Loading ecosystem…')}</span>
          </div>
        </div>`;
    }
    const sub = tr('nav.solutions.sub', '10 ecosystem projects · 10 currencies · 1 credit fuel');
    const STATUS_LBL = {
      live:     tr('eco.status.live',     'Live'),
      building: tr('eco.status.building', 'Building'),
      design:   tr('eco.status.design',   'Design')
    };
    const tiles = eco.projects.map(p => `
      <a href="${prefix}pages/solution.html?code=${encodeURIComponent(p.code)}" class="mega-tile group" data-mega-tile>
        <span class="mega-tile-badge" data-b30-currency="${p.currency}" data-primary="${p.color_primary}" data-accent="${p.color_accent}" data-size="40"></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="font-display font-bold text-sm truncate text-slate-900 dark:text-white">${localized(p,'name')}</p>
            <span class="mega-pill mega-pill-${p.status}">${STATUS_LBL[p.status] || p.status}</span>
          </div>
          <p class="text-xs opacity-70 mt-0.5 line-clamp-1">${localized(p,'tagline')}</p>
          <div class="flex items-center gap-2 mt-1.5 text-[11px] opacity-70">
            <span class="font-mono">${p.currency}</span>
            <span class="w-1 h-1 rounded-full bg-current opacity-40"></span>
            <span><strong class="text-blue-500">×${p.credit_to_currency_rate}</strong> / B30C</span>
          </div>
        </div>
      </a>`).join('');
    return `
      <div class="mega-menu" data-mega-menu>
        <div class="mega-head">
          <div>
            <p class="font-display font-extrabold text-lg text-slate-900 dark:text-white">${title}</p>
            <p class="text-xs opacity-70">${sub}</p>
          </div>
          <a href="${prefix}pages/solutions.html" class="mega-link">
            ${tr('nav.solutions.viewAll', 'View all solutions')} <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
          </a>
        </div>
        <div class="mega-grid">${tiles}</div>
        <div class="mega-foot">
          <i data-lucide="key-round" class="w-3.5 h-3.5 text-amber-500"></i>
          <span>${tr('nav.solutions.keyHint', 'Your B30C credits are the keys to every premium feature in the ecosystem.')}</span>
        </div>
      </div>`;
  }

  function mobileSolutionsHTML(prefix) {
    const eco = ecosystem();
    if (!eco.projects || !eco.projects.length) return '';
    const items = eco.projects.map(p => `
      <a href="${prefix}pages/solution.html?code=${encodeURIComponent(p.code)}" class="flex items-center gap-3 px-2 py-1.5 rounded hover:bg-blue-500/10">
        <span data-b30-currency="${p.currency}" data-primary="${p.color_primary}" data-accent="${p.color_accent}" data-size="22"></span>
        <span class="flex-1 truncate text-sm">${localized(p,'name')}</span>
        <span class="text-[10px] opacity-60 font-mono">${p.currency}</span>
      </a>`).join('');
    return `
      <details class="mt-1" data-mobile-solutions>
        <summary class="cursor-pointer flex items-center gap-2 px-2 py-1.5 rounded hover:bg-blue-500/10 font-semibold text-sm">
          <i data-lucide="grid-3x3" class="w-4 h-4"></i> ${tr('nav.solutions.title','Solutions')}
          <i data-lucide="chevron-down" class="w-4 h-4 ml-auto"></i>
        </summary>
        <div class="mt-1 pl-4 space-y-0.5 border-l border-slate-200/40 dark:border-white/10">
          ${items}
          <a href="${prefix}pages/solutions.html" class="block px-2 py-1.5 rounded text-xs text-blue-500 hover:bg-blue-500/10 font-semibold">${tr('nav.solutions.viewAll','View all')} →</a>
        </div>
      </details>`;
  }

  function navHTML(prefix) {
    const homeLabel    = tr('nav.home',    'Home');
    const dashLabel    = tr('nav.dashboard','Dashboard');
    const docsLabel    = tr('nav.docs',    'Docs');
    const lvl4Label    = tr('nav.level4',  'Level 4');
    const adminLabel   = tr('nav.admin',   'Admin');
    const startLabel   = tr('cta.start',   'Start free');
    const solutionsLabel = tr('nav.solutions.title', 'Solutions');
    const affiliateLabel = tr('nav.affiliate', 'Affiliate');
    return `
    <header class="fixed top-0 inset-x-0 z-50" data-brand-id-hide>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
        <nav class="wb-glass rounded-2xl flex items-center justify-between px-4 sm:px-6 py-3 relative">
          <a href="${prefix}index.html" class="flex items-center gap-2 group" aria-label="2030B P2P home">
            <span class="relative inline-flex w-9 h-9 items-center justify-center">
              <span data-b30-logo="master" data-size="36" class="block"></span>
            </span>
            <span class="brand-id-text font-display font-bold text-lg tracking-tight text-slate-900 dark:text-white" style="font-family:'Space Grotesk','Inter', sans-serif;">
              <span class="wb-gradient-text">2030B</span><span class="opacity-70 ml-1 text-sm font-semibold">P2P</span>
            </span>
          </a>

          <ul class="hidden lg:flex items-center gap-7 text-sm font-medium" data-nav-links>
            <li><a class="wb-link-underline hover:text-blue-500" data-nav href="${prefix}index.html">${homeLabel}</a></li>
            <li><a class="wb-link-underline hover:text-blue-500" data-nav href="${prefix}pages/dashboard.html">${dashLabel}</a></li>
            <li class="relative group" data-mega-root>
              <button class="wb-link-underline hover:text-blue-500 inline-flex items-center gap-1 cursor-pointer" data-mega-trigger>
                ${solutionsLabel} <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
              </button>
              ${megaMenuHTML(prefix)}
            </li>
            <li><a class="wb-link-underline hover:text-blue-500" data-nav href="${prefix}pages/affiliate.html">${affiliateLabel}</a></li>
            <li class="relative group" data-docs-root>
              <button class="wb-link-underline hover:text-blue-500 inline-flex items-center gap-1 cursor-pointer" data-docs-trigger>
                ${docsLabel} <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
              </button>
              <div class="docs-menu absolute right-0 mt-2 w-64 wb-glass rounded-xl shadow-xl border border-white/10 p-2 hidden" data-docs-menu>
                <a class="flex items-start gap-2 px-3 py-2 rounded-lg hover:bg-blue-500/10 text-sm" href="${prefix}pages/docs/user.html">
                  <i data-lucide="user" class="w-4 h-4 mt-0.5 text-blue-500"></i>
                  <span><span class="block font-semibold">${tr('nav.docs.user','User guide')}</span><span class="block text-xs text-slate-500">${tr('nav.docs.user.sub','Sign up, pair, level up')}</span></span>
                </a>
                <a class="flex items-start gap-2 px-3 py-2 rounded-lg hover:bg-blue-500/10 text-sm" href="${prefix}pages/docs/affiliate.html">
                  <i data-lucide="link-2" class="w-4 h-4 mt-0.5 text-fuchsia-500"></i>
                  <span><span class="block font-semibold">${tr('nav.docs.affiliate','Affiliate program')}</span><span class="block text-xs text-slate-500">${tr('nav.docs.affiliate.sub','Referral kickbacks')}</span></span>
                </a>
                <a class="flex items-start gap-2 px-3 py-2 rounded-lg hover:bg-blue-500/10 text-sm" href="${prefix}pages/docs/admin.html">
                  <i data-lucide="shield" class="w-4 h-4 mt-0.5 text-amber-500"></i>
                  <span><span class="block font-semibold">${tr('nav.docs.admin','Sub-admin manual')}</span><span class="block text-xs text-slate-500">${tr('nav.docs.admin.sub','Verify, single-ops, penalties')}</span></span>
                </a>
                <a class="flex items-start gap-2 px-3 py-2 rounded-lg hover:bg-blue-500/10 text-sm" href="${prefix}pages/docs/super-admin.html">
                  <i data-lucide="crown" class="w-4 h-4 mt-0.5 text-emerald-500"></i>
                  <span><span class="block font-semibold">${tr('nav.docs.superadmin','Super-admin guide')}</span><span class="block text-xs text-slate-500">${tr('nav.docs.superadmin.sub','Providers, security, keys')}</span></span>
                </a>
                <a class="flex items-start gap-2 px-3 py-2 rounded-lg hover:bg-blue-500/10 text-sm" href="${prefix}pages/docs/api.html">
                  <i data-lucide="code-2" class="w-4 h-4 mt-0.5 text-cyan-500"></i>
                  <span><span class="block font-semibold">${tr('nav.docs.api','Auth API reference')}</span><span class="block text-xs text-slate-500">${tr('nav.docs.api.sub','Keys, endpoints, examples')}</span></span>
                </a>
                <div class="border-t border-white/10 mt-2 pt-2">
                  <a class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-blue-500/10 text-xs text-slate-500" href="${prefix}pages/docs.html">
                    <i data-lucide="book-open" class="w-3.5 h-3.5"></i> ${tr('nav.docs.legacy','Legacy docs')}
                  </a>
                </div>
              </div>
            </li>
            <li><a class="wb-link-underline hover:text-blue-500" data-nav href="${prefix}pages/admin.php">${adminLabel}</a></li>
          </ul>

          <div class="flex items-center gap-1.5">
            <button class="nav-icon-btn wb-btn-ghost text-slate-700 dark:text-slate-200" data-aside-toggle="b30-aside-lang" aria-label="Choose language" title="Language">
              <i data-lucide="languages" class="w-5 h-5"></i>
              <span class="ring-pulse"></span>
            </button>
            <button class="nav-icon-btn wb-btn-ghost text-slate-700 dark:text-slate-200" data-aside-toggle="b30-aside-user" aria-label="User account" title="Account">
              <i data-lucide="user-circle" class="w-5 h-5"></i>
            </button>
            <button data-theme-toggle class="nav-icon-btn wb-btn-ghost" aria-label="Toggle theme">
              <i data-theme-icon="sun" data-lucide="sun" class="w-5 h-5"></i>
              <i data-theme-icon="moon" data-lucide="moon" class="w-5 h-5 hidden"></i>
            </button>
            <a href="${prefix}auth/index.html" class="hidden sm:inline-flex wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold items-center gap-1 ml-1">
              ${startLabel} <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
            <button data-mobile-toggle class="lg:hidden wb-btn-ghost rounded-lg w-10 h-10 flex items-center justify-center" aria-label="Menu">
              <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
          </div>
        </nav>
        <div id="mobileMenu" class="hidden lg:hidden wb-glass rounded-2xl mt-2 p-4 max-h-[80vh] overflow-y-auto">
          <ul class="space-y-1 text-sm font-medium">
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}index.html">${homeLabel}</a></li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/dashboard.html">${dashLabel}</a></li>
            <li>${mobileSolutionsHTML(prefix)}</li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/affiliate.html">${affiliateLabel}</a></li>
            <li>
              <details class="group">
                <summary class="cursor-pointer flex items-center gap-2 px-2 py-1.5 rounded hover:bg-blue-500/10">
                  <i data-lucide="book-open" class="w-4 h-4"></i> ${docsLabel}
                  <i data-lucide="chevron-down" class="w-4 h-4 ml-auto group-open:rotate-180 transition-transform"></i>
                </summary>
                <div class="mt-1 pl-4 space-y-0.5 border-l border-slate-200/40 dark:border-white/10">
                  <a class="block px-2 py-1.5 rounded text-xs hover:bg-blue-500/10" href="${prefix}pages/docs/user.html">${tr('nav.docs.user','User guide')}</a>
                  <a class="block px-2 py-1.5 rounded text-xs hover:bg-blue-500/10" href="${prefix}pages/docs/affiliate.html">${tr('nav.docs.affiliate','Affiliate program')}</a>
                  <a class="block px-2 py-1.5 rounded text-xs hover:bg-blue-500/10" href="${prefix}pages/docs/admin.html">${tr('nav.docs.admin','Sub-admin manual')}</a>
                  <a class="block px-2 py-1.5 rounded text-xs hover:bg-blue-500/10" href="${prefix}pages/docs/super-admin.html">${tr('nav.docs.superadmin','Super-admin guide')}</a>
                  <a class="block px-2 py-1.5 rounded text-xs hover:bg-blue-500/10" href="${prefix}pages/docs/api.html">${tr('nav.docs.api','Auth API reference')}</a>
                </div>
              </details>
            </li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/level4.html">${lvl4Label}</a></li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/admin.php">${adminLabel}</a></li>
          </ul>
          <a href="${prefix}auth/index.html" class="wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold inline-flex mt-3">${startLabel}</a>
        </div>
      </div>
    </header>`;
  }

  function footHTML(prefix) {
    const p = prefix;
    const inPages = p === '../';
    const link = (slug) => inPages ? slug : `pages/${slug}`;
    const tagline = tr('footer.tagline',
      'Turn your real P2P trades into 2030B credits. Climb 4 levels. Unlock the internal economy.');
    return `
    <footer class="border-t border-slate-200/60 dark:border-white/5 pt-16 pb-8 mt-16 relative">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 grid md:grid-cols-5 gap-10">
        <div class="md:col-span-2">
          <a href="${p}index.html" class="flex items-center gap-2">
            <span data-b30-logo="master" data-size="40" class="block"></span>
            <span class="font-display font-bold text-lg text-slate-900 dark:text-white" style="font-family:'Space Grotesk','Inter', sans-serif;">
              <span class="wb-gradient-text">2030B</span> P2P
            </span>
          </a>
          <p class="mt-4 text-sm text-slate-600 dark:text-slate-400 max-w-sm">${tagline}</p>
          <div class="mt-5 flex items-center gap-2 text-xs text-slate-500">
            <i data-lucide="shield-check" class="w-4 h-4"></i> ${tr('footer.security','SQLite per user · CSRF · bcrypt')}
          </div>
        </div>

        <div>
          <h4 class="font-semibold text-slate-900 dark:text-white text-sm">${tr('footer.product','Platform')}</h4>
          <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
            <li><a href="${p}index.html" class="hover:text-blue-500">${tr('nav.home','Home')}</a></li>
            <li><a href="${link('dashboard.html')}" class="hover:text-blue-500">${tr('nav.dashboard','Dashboard')}</a></li>
            <li><a href="${link('docs.html')}" class="hover:text-blue-500">${tr('nav.docs','Documentation')}</a></li>
            <li><a href="${link('level4.html')}" class="hover:text-blue-500">${tr('nav.level4','Level 4 · Coming soon')}</a></li>
          </ul>
        </div>

        <div>
          <h4 class="font-semibold text-slate-900 dark:text-white text-sm">${tr('footer.levels','Levels')}</h4>
          <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
            <li><a href="${link('docs.html')}#level-1" class="hover:text-blue-500">${tr('level.1','Level 1 — First pairing')}</a></li>
            <li><a href="${link('docs.html')}#level-2" class="hover:text-blue-500">${tr('level.2','Level 2 — RedotPay')}</a></li>
            <li><a href="${link('docs.html')}#level-3" class="hover:text-blue-500">${tr('level.3','Level 3 — Binance')}</a></li>
            <li><a href="${link('docs.html')}#level-4" class="hover:text-blue-500">${tr('level.4','Level 4 — Internal P2P')}</a></li>
          </ul>
        </div>

        <div>
          <h4 class="font-semibold text-slate-900 dark:text-white text-sm">${tr('footer.resources','Resources')}</h4>
          <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
            <li><a href="${link('docs.html')}#binance" class="hover:text-blue-500">${tr('docs.binance','Binance verification')}</a></li>
            <li><a href="${link('docs.html')}#redotpay" class="hover:text-blue-500">${tr('docs.redotpay','RedotPay verification')}</a></li>
            <li><a href="${link('docs.html')}#screenshots" class="hover:text-blue-500">${tr('docs.screenshots','Screenshot guide')}</a></li>
            <li><a href="${link('admin.php')}" class="hover:text-blue-500">${tr('nav.admin','Admin panel')}</a></li>
          </ul>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 pt-6 border-t border-slate-200/60 dark:border-white/5 flex flex-wrap items-center justify-between gap-4">
        <p class="text-xs text-slate-500">© 2030 · 2030B P2P Pairing · ${tr('footer.rights','All rights reserved')}.</p>
        <div class="flex items-center gap-3 text-slate-500">
          <span class="b30-pill b30-pill-verified"><i data-lucide="bitcoin" class="w-3 h-3"></i> USDT</span>
          <span class="b30-pill b30-pill-pending"><i data-lucide="repeat" class="w-3 h-3"></i> P2P</span>
        </div>
      </div>
    </footer>`;
  }

  function loadOnce(src) {
    if (document.querySelector(`script[data-auto-src="${src}"]`)) return;
    const s = document.createElement('script');
    s.src = src; s.dataset.autoSrc = src; s.defer = true;
    document.head.appendChild(s);
  }
  function loadCssOnce(href) {
    if (document.querySelector(`link[data-auto-css="${href}"]`)) return;
    const l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = href; l.dataset.autoCss = href;
    document.head.appendChild(l);
  }

  function wireMegaMenu() {
    document.querySelectorAll('[data-mega-root]').forEach(root => {
      if (root.dataset.megaWired) return;
      root.dataset.megaWired = '1';
      const trigger = root.querySelector('[data-mega-trigger]');
      const menu = root.querySelector('[data-mega-menu]');
      if (!trigger || !menu) return;
      let hoverTimer = null;
      const open = () => { clearTimeout(hoverTimer); menu.classList.add('is-open'); trigger.setAttribute('aria-expanded','true'); };
      const close = () => { menu.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); };
      const closeSoon = () => { clearTimeout(hoverTimer); hoverTimer = setTimeout(close, 180); };
      // Hover (desktop)
      root.addEventListener('mouseenter', open);
      root.addEventListener('mouseleave', closeSoon);
      // Keyboard / click
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        if (menu.classList.contains('is-open')) close(); else open();
      });
      // Esc / outside click
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
      document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) close();
      });
    });
  }

  function wireDocsMenu() {
    document.querySelectorAll('[data-docs-root]').forEach(root => {
      if (root.dataset.docsWired) return;
      root.dataset.docsWired = '1';
      const trigger = root.querySelector('[data-docs-trigger]');
      const menu = root.querySelector('[data-docs-menu]');
      if (!trigger || !menu) return;
      let hoverTimer = null;
      const open = () => { clearTimeout(hoverTimer); menu.classList.remove('hidden'); trigger.setAttribute('aria-expanded','true'); };
      const close = () => { menu.classList.add('hidden'); trigger.setAttribute('aria-expanded','false'); };
      const closeSoon = () => { clearTimeout(hoverTimer); hoverTimer = setTimeout(close, 180); };
      root.addEventListener('mouseenter', open);
      root.addEventListener('mouseleave', closeSoon);
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        if (menu.classList.contains('hidden')) open(); else close();
      });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
      document.addEventListener('click', (e) => { if (!root.contains(e.target)) close(); });
    });
  }

  function rerenderShell() {
    const head = document.querySelector('[data-shell]');
    const foot = document.querySelector('[data-shell-foot]');
    if (head) head.dataset.shellRendered = '';
    if (foot) foot.dataset.shellRendered = '';
    renderShell();
  }

  function renderShell() {
    const prefix = (document.body && document.body.dataset.prefix) || '';
    loadCssOnce(`${prefix}css/enhancements.css`);
    loadOnce(`${prefix}js/enhancements.js`);
    loadOnce(`${prefix}js/validate.js`);

    const head = document.querySelector('[data-shell]');
    const foot = document.querySelector('[data-shell-foot]');
    if (head && !head.dataset.shellRendered) {
      head.innerHTML = navHTML(prefix);
      head.dataset.shellRendered = '1';
    }
    if (foot && !foot.dataset.shellRendered) {
      foot.innerHTML = footHTML(prefix);
      foot.dataset.shellRendered = '1';
    }
    if (window.B30Logos) try { window.B30Logos.renderAuto(); } catch (e) {}
    if (window.lucide) try { lucide.createIcons(); } catch (e) {}
    wireMegaMenu();
    wireDocsMenu();
    // Mark active nav link
    try {
      const here = location.pathname.split('/').pop() || 'index.html';
      document.querySelectorAll('[data-nav]').forEach(a => {
        const target = a.getAttribute('href').split('/').pop();
        if (target === here) {
          a.classList.add('text-blue-500', 'font-semibold');
        }
      });
    } catch (e) {}
    document.dispatchEvent(new CustomEvent('shell:rendered'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderShell);
  } else {
    renderShell();
  }

  // Re-render shell when language changes (covers user switch AND initial load)
  document.addEventListener('b30:lang-changed', rerenderShell);
  // Re-render once ecosystem JSON finishes loading (mega-menu populates)
  document.addEventListener('b30:ecosystem-loaded', rerenderShell);

  // Belt-and-braces: if i18n is already initialised (or finishes after we
  // attach), make sure we re-render. The "b30:lang-changed" event from
  // i18n.init() may have fired BEFORE this listener was attached.
  if (window.B30I18n && typeof window.B30I18n.ready === 'function') {
    window.B30I18n.ready().then(() => {
      try { rerenderShell(); } catch (e) {}
    }).catch(() => {});
  }

  // Try once when store is ready (in case ecosystem already loaded before this script ran)
  function tryEcoOnce() {
    if (!window.B30Store) return;
    if (typeof window.B30Store.getEcosystem === 'function') {
      const eco = window.B30Store.getEcosystem();
      if (eco && eco.projects && eco.projects.length) rerenderShell();
    }
  }
  if (window.B30Store && window.B30Store.readyPromise) {
    window.B30Store.readyPromise.then(tryEcoOnce).catch(()=>{});
  } else {
    setTimeout(tryEcoOnce, 600);
    setTimeout(tryEcoOnce, 1800);
  }

  // Expose for other scripts that may want to force re-render
  window.B30Shell = { rerender: rerenderShell };
})();
