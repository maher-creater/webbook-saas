/* 2030B P2P Pairing — UX enhancements (asides, active states, lang switcher)
 * Loader is owned by js/logos.js (booted earliest). This file only adds
 * accessory UI: language aside, user aside, mobile menu wiring.
 */
(function () {
  if (window.__b30Enhanced) return;
  window.__b30Enhanced = true;

  // ========== 1. ACTIVE NAV STATES ==========
  function currentPageKey() {
    const path = location.pathname.split('/').pop() || 'index.html';
    return (path || 'index.html').toLowerCase();
  }
  function applyActiveStates() {
    const here = currentPageKey();
    document.querySelectorAll('[data-nav]').forEach(a => {
      const href = (a.getAttribute('href') || '').split('/').pop().split('#')[0].toLowerCase();
      if (href && href === here) {
        a.classList.add('text-blue-500', 'font-semibold');
        a.setAttribute('data-active-link', '');
      }
    });
  }

  // ========== 2. ASIDES (lang + user) ==========
  // 12+ languages with Arabic RTL
  const LANGS = [
    { code:'en', flag:'🇬🇧', name:'English' },
    { code:'ar', flag:'🇸🇦', name:'العربية', rtl:true },
    { code:'fr', flag:'🇫🇷', name:'Français' },
    { code:'es', flag:'🇪🇸', name:'Español' },
    { code:'de', flag:'🇩🇪', name:'Deutsch' },
    { code:'pt', flag:'🇵🇹', name:'Português' },
    { code:'it', flag:'🇮🇹', name:'Italiano' },
    { code:'zh', flag:'🇨🇳', name:'中文' },
    { code:'hi', flag:'🇮🇳', name:'हिन्दी' },
    { code:'ja', flag:'🇯🇵', name:'日本語' },
    { code:'ru', flag:'🇷🇺', name:'Русский' },
    { code:'tr', flag:'🇹🇷', name:'Türkçe' }
  ];

  function tr(key, fallback) {
    try {
      if (window.B30I18n && window.B30I18n.t) {
        const v = window.B30I18n.t(key);
        if (v && v !== key) return v;
      }
    } catch (e) {}
    return fallback;
  }

  function buildAsides() {
    if (!document.body) return;
    if (document.getElementById('b30-aside-host')) return;
    const host = document.createElement('div');
    host.id = 'b30-aside-host';
    host.innerHTML = `
      <div class="wb-aside-backdrop" data-aside-close></div>

      <aside class="wb-aside" id="b30-aside-user" role="dialog" aria-label="Your account" aria-hidden="true">
        <header>
          <h3>${tr('aside.user.title', 'Your account')}</h3>
          <button class="wb-btn-ghost rounded-lg w-9 h-9 flex items-center justify-center" data-aside-close aria-label="Close">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </header>
        <div class="aside-body">
          <div class="flex items-center gap-3" data-account-summary>
            <span class="user-avatar">2B</span>
            <div>
              <p class="font-semibold" data-account-name>${tr('aside.user.guest', 'Guest trader')}</p>
              <p class="text-xs opacity-70" data-account-meta>${tr('aside.user.signinPrompt','Sign in to start pairing')}</p>
            </div>
          </div>
          <div class="mt-5 space-y-2" data-account-actions>
            <a class="aside-action" data-aside-act="dashboard" href="#"><i data-lucide="layout-dashboard" class="w-4 h-4 text-blue-400"></i><span>${tr('aside.user.dashboard','Open dashboard')}</span></a>
            <a class="aside-action" data-aside-act="signin" href="#"><i data-lucide="log-in" class="w-4 h-4 text-cyan-400"></i><span>${tr('aside.user.signin','Sign in')}</span></a>
            <a class="aside-action" data-aside-act="signup" href="#"><i data-lucide="user-plus" class="w-4 h-4 text-emerald-400"></i><span>${tr('aside.user.signup','Create account')}</span></a>
            <a class="aside-action" data-aside-act="docs" href="#"><i data-lucide="book-open" class="w-4 h-4 text-amber-400"></i><span>${tr('aside.user.docs','Read the guide')}</span></a>
            <a class="aside-action" data-aside-act="signout" href="#" data-needs-auth><i data-lucide="log-out" class="w-4 h-4 text-rose-400"></i><span>${tr('aside.user.signout','Sign out')}</span></a>
          </div>
        </div>
      </aside>

      <aside class="wb-aside" id="b30-aside-lang" role="dialog" aria-label="Choose language" aria-hidden="true">
        <header>
          <h3>${tr('aside.lang.title','Choose your language')}</h3>
          <button class="wb-btn-ghost rounded-lg w-9 h-9 flex items-center justify-center" data-aside-close aria-label="Close">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </header>
        <div class="aside-body">
          <p class="text-sm opacity-70 mb-4">${tr('aside.lang.subtitle','We support 12 languages including Arabic (RTL).')}</p>
          <div class="lang-grid">
            ${LANGS.map(l => `
              <button class="lang-item" data-lang="${l.code}" aria-selected="false">
                <span>${l.flag}</span>
                <span class="font-medium">${l.name}</span>
              </button>`).join('')}
          </div>
        </div>
      </aside>`;
    document.body.appendChild(host);
  }

  function openAside(id) {
    const aside = document.getElementById(id);
    const backdrop = document.querySelector('.wb-aside-backdrop');
    if (!aside) return;
    document.querySelectorAll('.wb-aside').forEach(a => { a.classList.remove('is-open'); a.setAttribute('aria-hidden','true'); });
    aside.classList.add('is-open');
    aside.setAttribute('aria-hidden','false');
    backdrop && backdrop.classList.add('is-open');
  }
  function closeAsides() {
    document.querySelectorAll('.wb-aside').forEach(a => { a.classList.remove('is-open'); a.setAttribute('aria-hidden','true'); });
    document.querySelectorAll('.wb-aside-backdrop').forEach(b => b.classList.remove('is-open'));
  }

  function refreshAccountAside() {
    const session = (window.B30Store && window.B30Store.getSession) ? window.B30Store.getSession() : null;
    const nameEl = document.querySelector('[data-account-name]');
    const metaEl = document.querySelector('[data-account-meta]');
    const avatar = document.querySelector('#b30-aside-user .user-avatar');
    const needAuth = document.querySelectorAll('[data-needs-auth]');
    if (session) {
      if (nameEl) nameEl.textContent = session.full_name || session.email;
      if (metaEl) metaEl.textContent = `${tr('aside.user.level','Level')} ${session.level} · ${session.total_credits || 0} ${tr('aside.user.credits','credits')}`;
      if (avatar) avatar.textContent = (session.full_name || session.email || '2B').slice(0,2).toUpperCase();
      needAuth.forEach(el => el.classList.remove('hidden'));
    } else {
      if (nameEl) nameEl.textContent = tr('aside.user.guest','Guest trader');
      if (metaEl) metaEl.textContent = tr('aside.user.signinPrompt','Sign in to start pairing');
      if (avatar) avatar.textContent = '2B';
      needAuth.forEach(el => el.classList.add('hidden'));
    }
  }

  function wireAsides() {
    document.querySelectorAll('[data-aside-toggle]').forEach(btn => {
      if (btn.dataset.asideWired) return;
      btn.dataset.asideWired = '1';
      btn.addEventListener('click', () => { refreshAccountAside(); openAside(btn.dataset.asideToggle); });
    });
    if (!window.__b30AsideGlobal) {
      window.__b30AsideGlobal = true;
      document.addEventListener('click', (e) => { if (e.target.closest('[data-aside-close]')) closeAsides(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAsides(); });
    }
    const stored = (window.B30I18n && window.B30I18n.current && window.B30I18n.current()) ||
                   localStorage.getItem('b30-lang') || 'en';
    document.querySelectorAll('[data-lang]').forEach(item => {
      if (item.dataset.lang === stored) item.setAttribute('aria-selected','true');
      if (item.dataset.langWired) return;
      item.dataset.langWired = '1';
      item.addEventListener('click', () => {
        document.querySelectorAll('[data-lang]').forEach(x => x.setAttribute('aria-selected','false'));
        item.setAttribute('aria-selected','true');
        const code = item.dataset.lang;
        if (window.B30I18n && window.B30I18n.set) {
          window.B30I18n.set(code);
        } else {
          localStorage.setItem('b30-lang', code);
          document.documentElement.setAttribute('lang', code);
          const isRtl = LANGS.find(l => l.code === code && l.rtl);
          if (isRtl) document.documentElement.setAttribute('dir','rtl');
          else document.documentElement.removeAttribute('dir');
        }
        closeAsides();
      });
    });

    // Wire user actions to routes
    document.querySelectorAll('[data-aside-act]').forEach(b => {
      if (b.dataset.actWired) return;
      b.dataset.actWired = '1';
      b.addEventListener('click', (e) => {
        e.preventDefault();
        const act = b.dataset.asideAct;
        const prefix = document.body.dataset.prefix || '';
        if (act === 'dashboard') location.href = prefix + 'pages/dashboard.html';
        else if (act === 'docs') location.href = prefix + 'pages/docs.html';
        else if (act === 'signin' || act === 'signup') location.href = prefix + 'index.html#join';
        else if (act === 'signout') {
          if (window.B30Store) window.B30Store.clearSession();
          if (window.Swal) Swal.fire({icon:'success', title:tr('toast.signedOut','Signed out'), timer:1200, showConfirmButton:false});
          setTimeout(() => location.href = prefix + 'index.html', 400);
        }
      });
    });
  }

  function initOnce() {
    buildAsides();
    applyActiveStates();
    wireAsides();
    if (window.lucide) try { lucide.createIcons(); } catch(e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(initOnce, 50));
  } else {
    setTimeout(initOnce, 50);
  }
  document.addEventListener('shell:rendered', () => setTimeout(initOnce, 30));
  document.addEventListener('b30:lang-changed', () => {
    // rebuild asides with new translations
    const host = document.getElementById('b30-aside-host');
    if (host) host.remove();
    setTimeout(initOnce, 20);
  });

  window.B30Enhance = { applyActiveStates, openAside, closeAsides, refreshAccountAside };
})();
