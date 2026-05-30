/* 2030B P2P Pairing — Shared interactivity (theme, reveal, mobile menu, counters) */
(function () {
  const root = document.documentElement;
  const stored = localStorage.getItem('b30-theme') || localStorage.getItem('wb-theme');
  const initial = stored || 'dark';
  if (initial === 'dark') root.classList.add('dark'); else root.classList.remove('dark');

  function setTheme(mode) {
    if (mode === 'dark') root.classList.add('dark'); else root.classList.remove('dark');
    localStorage.setItem('b30-theme', mode);
    document.querySelectorAll('[data-theme-icon]').forEach(el => {
      el.dataset.themeIcon === 'sun'
        ? el.classList.toggle('hidden', mode !== 'dark')
        : el.classList.toggle('hidden', mode === 'dark');
    });
  }

  document.addEventListener('DOMContentLoaded', wireUp);
  document.addEventListener('shell:rendered', () => setTimeout(wireUp, 30));

  function wireUp() {
    // Theme
    setTheme(root.classList.contains('dark') ? 'dark' : 'light');
    document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
      if (btn.dataset.themeWired) return;
      btn.dataset.themeWired = '1';
      btn.addEventListener('click', () => setTheme(root.classList.contains('dark') ? 'light' : 'dark'));
    });

    // Mobile menu
    document.querySelectorAll('[data-mobile-toggle]').forEach(btn => {
      if (btn.dataset.mobileWired) return;
      btn.dataset.mobileWired = '1';
      btn.addEventListener('click', () => {
        const t = document.getElementById('mobileMenu');
        if (t) t.classList.toggle('hidden');
      });
    });
    document.addEventListener('click', (e) => {
      const a = e.target.closest('#mobileMenu a');
      if (a) document.getElementById('mobileMenu')?.classList.add('hidden');
    });

    // Reveal on scroll
    if (!window.__b30RvBound) {
      window.__b30RvBound = true;
      const rvIO = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('rv-in'); rvIO.unobserve(e.target); } });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
      document.querySelectorAll('[data-rv]').forEach(el => rvIO.observe(el));
    }

    // Counters
    if (!window.__b30CountBound) {
      window.__b30CountBound = true;
      const counters = document.querySelectorAll('[data-counter]');
      if (counters.length) {
        const cio = new IntersectionObserver(entries => {
          entries.forEach(en => {
            if (!en.isIntersecting) return;
            const el = en.target;
            const target = parseFloat(el.dataset.counter);
            const decimals = parseInt(el.dataset.decimals || '0', 10);
            const suffix = el.dataset.suffix || '';
            let cur = 0;
            const step = target / 60;
            const t = setInterval(() => {
              cur += step;
              if (cur >= target) { cur = target; clearInterval(t); }
              el.textContent = cur.toFixed(decimals) + suffix;
            }, 16);
            cio.unobserve(el);
          });
        }, { threshold: 0.4 });
        counters.forEach(c => cio.observe(c));
      }
    }
    if (window.lucide) try { lucide.createIcons(); } catch (e) {}
  }

  // Apply admin's locally-saved config override before B30Config loads
  document.addEventListener('DOMContentLoaded', () => {
    const stored = localStorage.getItem('b30-config-override');
    if (!stored || !window.B30Config) return;
    try {
      const parsed = JSON.parse(stored);
      window.B30Config.onLoad((c) => Object.assign(c, parsed));
    } catch (e) {}
  });
})();
