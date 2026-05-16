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

  function navHTML(prefix) {
    const homeLabel    = tr('nav.home',    'Home');
    const dashLabel    = tr('nav.dashboard','Dashboard');
    const docsLabel    = tr('nav.docs',    'Docs');
    const lvl4Label    = tr('nav.level4',  'Level 4');
    const adminLabel   = tr('nav.admin',   'Admin');
    const startLabel   = tr('cta.start',   'Start free');
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
            <li><a class="wb-link-underline hover:text-blue-500" data-nav href="${prefix}pages/docs.html">${docsLabel}</a></li>
            <li><a class="wb-link-underline hover:text-blue-500" data-nav href="${prefix}pages/level4.html">${lvl4Label}</a></li>
            <li><a class="wb-link-underline hover:text-blue-500" data-nav href="${prefix}pages/admin.html">${adminLabel}</a></li>
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
            <a href="${prefix}index.html#join" class="hidden sm:inline-flex wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold items-center gap-1 ml-1">
              ${startLabel} <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
            <button data-mobile-toggle class="lg:hidden wb-btn-ghost rounded-lg w-10 h-10 flex items-center justify-center" aria-label="Menu">
              <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
          </div>
        </nav>
        <div id="mobileMenu" class="hidden lg:hidden wb-glass rounded-2xl mt-2 p-4 max-h-[80vh] overflow-y-auto">
          <ul class="space-y-2 text-sm font-medium">
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}index.html">${homeLabel}</a></li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/dashboard.html">${dashLabel}</a></li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/docs.html">${docsLabel}</a></li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/level4.html">${lvl4Label}</a></li>
            <li><a class="block px-2 py-1.5 rounded hover:bg-blue-500/10" href="${prefix}pages/admin.html">${adminLabel}</a></li>
          </ul>
          <a href="${prefix}index.html#join" class="wb-btn-primary rounded-lg px-4 py-2 text-sm font-semibold inline-flex mt-3">${startLabel}</a>
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
            <li><a href="${link('admin.html')}" class="hover:text-blue-500">${tr('nav.admin','Admin panel')}</a></li>
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

  function renderShell() {
    const prefix = (document.body && document.body.dataset.prefix) || '';
    loadCssOnce(`${prefix}css/enhancements.css`);
    loadOnce(`${prefix}js/enhancements.js`);

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

  // Re-render shell when language changes (so labels update)
  document.addEventListener('b30:lang-changed', function () {
    const head = document.querySelector('[data-shell]');
    const foot = document.querySelector('[data-shell-foot]');
    if (head) head.dataset.shellRendered = '';
    if (foot) foot.dataset.shellRendered = '';
    renderShell();
  });
})();
