/* 2030B P2P Pairing — SVG asset library + boot loader (v2)
 *
 * Loader sequence (per user spec):
 *   (1) page elements are HIDDEN immediately (we inject a global rule that
 *       hides <body> until the loader is gone),
 *   (2) loader is rendered overlapping the (still-hidden) page,
 *   (3) once window.load fires, the loader fades out,
 *   (4) THEN .b30-page-ready is added to <html>, which un-hides the body and
 *       launches the data-rv reveal/counter animations.
 *
 * Logo redesign (per user spec): the orbit-style mark is replaced by the
 *   "Five-Beam Diamond" — a faceted diamond shape with five animated beams
 *   converging on a central pulsing "B" tile, with a P2P swap halo. The
 *   five beams encode the 2030B ecosystem's 5 core human dimensions
 *   (Mind · Heart · Body · Imagination · Soul) that all 10 currencies
 *   (CTC, TIC, VTC, INC, SCC, WPC, WDC, JEC, FLC, GRC) extend.
 */
(function bootLoader() {
  if (window.__b30Boot) return;
  window.__b30Boot = true;

  // --- (1) Hide page elements as soon as possible -----------------------
  // Use a <style> injected to <head> so it applies before the body paints.
  var hideCss =
    "html:not(.b30-page-ready) body{visibility:hidden!important}" +
    "html.b30-page-ready body{visibility:visible}" +
    // Reveal-on-scroll: keep elements invisible until .b30-page-ready
    "html:not(.b30-page-ready) [data-rv]{opacity:0;transform:translateY(18px)}" +
    "html.b30-page-ready [data-rv]{transition:opacity .7s ease,transform .7s ease}" +
    "html.b30-page-ready [data-rv].is-in{opacity:1;transform:none}";

  var hs = document.createElement('style');
  hs.id = 'b30-page-hide-css';
  hs.textContent = hideCss;
  (document.head || document.documentElement).appendChild(hs);

  // --- Loader CSS -------------------------------------------------------
  var css =
    "#b30-loader{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;"+
      "visibility:visible;background:radial-gradient(ellipse at center,#0b1736 0%,#04060f 100%);"+
      "transition:opacity .55s ease,visibility .55s ease;font-family:'Space Grotesk','Inter',sans-serif}"+
    "#b30-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}"+
    "#b30-loader .l-inner{display:flex;flex-direction:column;align-items:center;gap:1.25rem}"+
    "#b30-loader .l-text{font-weight:700;font-size:1.05rem;letter-spacing:.02em;"+
      "background:linear-gradient(90deg,#3b82f6,#06b6d4,#22c55e,#f59e0b,#3b82f6);"+
      "-webkit-background-clip:text;background-clip:text;color:transparent;"+
      "background-size:200% 100%;animation:b30Slide 2.6s linear infinite}"+
    "#b30-loader .l-sub{font-size:.78rem;color:rgba(255,255,255,.55);margin-top:-.5rem}"+
    "#b30-loader .l-bar{width:240px;height:3px;border-radius:99px;background:rgba(255,255,255,.08);overflow:hidden;position:relative}"+
    "#b30-loader .l-bar::after{content:'';position:absolute;inset:0;width:40%;"+
      "background:linear-gradient(90deg,transparent,#3b82f6,#06b6d4,transparent);animation:b30Bar 1.4s ease-in-out infinite}"+
    "@keyframes b30Slide{0%{background-position:0 0}100%{background-position:200% 0}}"+
    "@keyframes b30Bar{0%{transform:translateX(-60%)}100%{transform:translateX(260%)}}"+
    /* The loader-version of the 5-beam diamond logo */
    ".b30-diamond{width:140px;height:140px}"+
    "@keyframes b30Pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}"+
    "@keyframes b30BeamSweep{0%,100%{opacity:.35}50%{opacity:1}}"+
    "@keyframes b30Spin{to{transform:rotate(360deg)}}";

  var s = document.createElement('style'); s.id = 'b30-loader-css'; s.textContent = css;
  (document.head || document.documentElement).appendChild(s);

  function diamondSvg() {
    return '' +
      '<svg viewBox="0 0 200 200" class="b30-diamond" xmlns="http://www.w3.org/2000/svg" aria-label="2030B">' +
        '<defs>' +
          '<linearGradient id="b30LdGrad" x1="0" y1="0" x2="1" y2="1">' +
            '<stop offset="0%" stop-color="#3b82f6"/>' +
            '<stop offset="55%" stop-color="#06b6d4"/>' +
            '<stop offset="100%" stop-color="#22c55e"/>' +
          '</linearGradient>' +
          '<linearGradient id="b30LdBeam" x1="0" y1="0" x2="0" y2="1">' +
            '<stop offset="0%" stop-color="#06b6d4" stop-opacity=".95"/>' +
            '<stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>' +
          '</linearGradient>' +
          '<radialGradient id="b30LdGlow" cx="50%" cy="50%" r="50%">' +
            '<stop offset="0%" stop-color="#06b6d4" stop-opacity=".55"/>' +
            '<stop offset="100%" stop-color="#06b6d4" stop-opacity="0"/>' +
          '</radialGradient>' +
        '</defs>' +
        '<circle cx="100" cy="100" r="92" fill="url(#b30LdGlow)"/>' +
        /* Five beams - 5 ecosystem dimensions */
        '<g style="transform-origin:100px 100px">' +
          '<rect x="98" y="6"  width="4" height="50" rx="2" fill="url(#b30LdBeam)" style="animation:b30BeamSweep 2.4s ease-in-out infinite"/>' +
          '<rect x="98" y="6"  width="4" height="50" rx="2" fill="url(#b30LdBeam)" transform="rotate(72 100 100)"  style="animation:b30BeamSweep 2.4s ease-in-out -.5s infinite"/>' +
          '<rect x="98" y="6"  width="4" height="50" rx="2" fill="url(#b30LdBeam)" transform="rotate(144 100 100)" style="animation:b30BeamSweep 2.4s ease-in-out -1s infinite"/>' +
          '<rect x="98" y="6"  width="4" height="50" rx="2" fill="url(#b30LdBeam)" transform="rotate(216 100 100)" style="animation:b30BeamSweep 2.4s ease-in-out -1.5s infinite"/>' +
          '<rect x="98" y="6"  width="4" height="50" rx="2" fill="url(#b30LdBeam)" transform="rotate(288 100 100)" style="animation:b30BeamSweep 2.4s ease-in-out -2s infinite"/>' +
        '</g>' +
        /* Faceted diamond */
        '<g style="transform-origin:100px 100px;animation:b30Pulse 3.2s ease-in-out infinite">' +
          '<polygon points="100,46 142,86 100,154 58,86" fill="url(#b30LdGrad)"/>' +
          '<polygon points="100,46 142,86 100,86 58,86" fill="#ffffff" fill-opacity=".18"/>' +
          '<polygon points="58,86 100,86 100,154" fill="#000000" fill-opacity=".10"/>' +
          '<text x="100" y="112" text-anchor="middle" font-family="Space Grotesk,Inter,sans-serif" font-size="34" font-weight="800" fill="#ffffff" letter-spacing="1">B</text>' +
        '</g>' +
      '</svg>';
  }

  function build() {
    if (document.getElementById('b30-loader')) return;
    if (!document.body) return setTimeout(build, 10);
    var l = document.createElement('div');
    l.id = 'b30-loader';
    l.setAttribute('aria-hidden','true');
    l.innerHTML =
      '<div class="l-inner">' +
        diamondSvg() +
        '<div class="l-text">2030B P2P Pairing…</div>' +
        '<div class="l-sub">Buy · Sell · Earn credits · Level up</div>' +
        '<div class="l-bar" role="progressbar" aria-label="Loading"></div>' +
      '</div>';
    document.body.appendChild(l);
  }
  if (document.body) build(); else document.addEventListener('DOMContentLoaded', build, { once: true });

  // --- (3) hide loader, then (4) reveal page + start animations --------
  function startPage() {
    var l = document.getElementById('b30-loader');
    if (l) l.classList.add('is-hidden');
    // After fade-out completes, unhide the body and start the IntersectionObserver
    setTimeout(function () {
      document.documentElement.classList.add('b30-page-ready');
      // start data-rv reveal animations
      try {
        var obs = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) { if (e.isIntersecting) e.target.classList.add('is-in'); });
        }, { threshold: .12 });
        document.querySelectorAll('[data-rv]').forEach(function (el) {
          // elements in viewport at load should reveal immediately
          var r = el.getBoundingClientRect();
          if (r.top < window.innerHeight && r.bottom > 0) el.classList.add('is-in');
          else obs.observe(el);
        });
      } catch (e) { document.querySelectorAll('[data-rv]').forEach(function (el) { el.classList.add('is-in'); }); }
      // start counters (data-counter="N")
      document.querySelectorAll('[data-counter]').forEach(function (el) {
        var target = +el.dataset.counter || 0;
        var dur = 1200; var start = performance.now();
        function tick(t) {
          var p = Math.min(1, (t - start) / dur);
          el.textContent = Math.floor(target * (0.2 + 0.8 * p)).toLocaleString();
          if (p < 1) requestAnimationFrame(tick); else el.textContent = target.toLocaleString();
        }
        requestAnimationFrame(tick);
      });
      if (l && l.parentNode) setTimeout(function () { l.parentNode.removeChild(l); }, 600);
      document.dispatchEvent(new CustomEvent('b30:page-ready'));
    }, 550);
  }

  function whenReady(fn) {
    if (document.readyState === 'complete') setTimeout(fn, 350);
    else window.addEventListener('load', function () { setTimeout(fn, 250); });
  }
  whenReady(startPage);
  // Safety: never block longer than 6s
  setTimeout(function () {
    if (!document.documentElement.classList.contains('b30-page-ready')) startPage();
  }, 6000);
})();

(function () {
  // ---------- FEATURE METADATA ----------
  const FEATURES = [
    { slug:'pairing',    name:'P2P Pairing',     color:'#3b82f6', accent:'#06b6d4', desc:'Buy + Sell verified pair = 1 credit.' },
    { slug:'credits',    name:'Credits System',  color:'#22c55e', accent:'#10b981', desc:'Real trades convert into the 2030B credit ledger.' },
    { slug:'levels',     name:'4 Levels',        color:'#f59e0b', accent:'#f43f5e', desc:'Climb from Level 1 to Level 4 — internal P2P unlock.' },
    { slug:'screenshot', name:'Proof Uploads',   color:'#8b5cf6', accent:'#6366f1', desc:'Upload buy & sell screenshots, validated on submit.' },
    { slug:'binance',    name:'Binance Ready',   color:'#f59e0b', accent:'#eab308', desc:'Verify via Binance and unlock Level 3 buy access.' },
    { slug:'redotpay',   name:'RedotPay Ready',  color:'#ef4444', accent:'#f43f5e', desc:'Verify via RedotPay and unlock Level 2 buy access.' },
    { slug:'multilang',  name:'12 Languages',    color:'#10b981', accent:'#06b6d4', desc:'Arabic RTL, English, French, Chinese, Hindi & more.' },
    { slug:'multicurr',  name:'Multi-currency',  color:'#0ea5e9', accent:'#3b82f6', desc:'Live FX rates · TND, USD, EUR, GBP, CNY, INR…' },
    { slug:'wallet',     name:'Virtual Wallet',  color:'#ec4899', accent:'#8b5cf6', desc:'Track your virtual profit and credit balance.' },
    { slug:'secure',     name:'Secure DB',       color:'#14b8a6', accent:'#10b981', desc:'Per-user SQLite database isolation.' },
    { slug:'admin',      name:'Admin Validation',color:'#6366f1', accent:'#8b5cf6', desc:'Manual or auto verification of trade proofs.' },
    { slug:'future',     name:'Level 4 Internal',color:'#f43f5e', accent:'#7c3aed', desc:'Future: 2030B native internal P2P engine.' },
    { slug:'auth',       name:'Auth & API keys', color:'#0ea5e9', accent:'#06b6d4', desc:'Beautiful auth, social, popup-loadable via API keys.' },
    { slug:'ecosystem',  name:'Ecosystem bridge',color:'#a855f7', accent:'#ec4899', desc:'Convert P2P credits into 10 ecosystem currencies.' }
  ];

  // ---------- MASTER LOGO — "Five-Beam Diamond" ----------
  // Geometry: a faceted diamond (top triangle, mid band, bottom point) centered
  // on a 200x200 canvas. Five beams shoot from the diamond's vertices outward
  // (one per ecosystem dimension). A subtle outer P2P swap halo signals the
  // pairing intent without the orbit/rings of v1.
  function master(size = 48) {
    return `
<svg viewBox="0 0 200 200" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="2030B P2P Pairing">
  <defs>
    <linearGradient id="b30CoreGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"  stop-color="#3b82f6"/>
      <stop offset="55%" stop-color="#06b6d4"/>
      <stop offset="100%" stop-color="#22c55e"/>
    </linearGradient>
    <linearGradient id="b30Beam" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"  stop-color="#06b6d4" stop-opacity=".95"/>
      <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>
    </linearGradient>
    <radialGradient id="b30HaloGlow" cx="50%" cy="50%" r="50%">
      <stop offset="0%"  stop-color="#3b82f6" stop-opacity=".35"/>
      <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>
    </radialGradient>
  </defs>

  <!-- soft outer glow halo -->
  <circle cx="100" cy="100" r="92" fill="url(#b30HaloGlow)"/>

  <!-- five beams (one per dimension) -->
  <g>
    <rect x="98" y="8"  width="4" height="46" rx="2" fill="url(#b30Beam)">
      <animate attributeName="opacity" values=".35;1;.35" dur="2.4s" repeatCount="indefinite"/>
    </rect>
    <rect x="98" y="8"  width="4" height="46" rx="2" fill="url(#b30Beam)" transform="rotate(72 100 100)">
      <animate attributeName="opacity" values=".35;1;.35" dur="2.4s" begin="-.5s" repeatCount="indefinite"/>
    </rect>
    <rect x="98" y="8"  width="4" height="46" rx="2" fill="url(#b30Beam)" transform="rotate(144 100 100)">
      <animate attributeName="opacity" values=".35;1;.35" dur="2.4s" begin="-1s"  repeatCount="indefinite"/>
    </rect>
    <rect x="98" y="8"  width="4" height="46" rx="2" fill="url(#b30Beam)" transform="rotate(216 100 100)">
      <animate attributeName="opacity" values=".35;1;.35" dur="2.4s" begin="-1.5s" repeatCount="indefinite"/>
    </rect>
    <rect x="98" y="8"  width="4" height="46" rx="2" fill="url(#b30Beam)" transform="rotate(288 100 100)">
      <animate attributeName="opacity" values=".35;1;.35" dur="2.4s" begin="-2s"   repeatCount="indefinite"/>
    </rect>
  </g>

  <!-- P2P swap halo (subtle arrows around the diamond) -->
  <g fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" opacity=".55">
    <path d="M28 100 a72 72 0 0 1 144 0"  stroke-dasharray="4 8">
      <animate attributeName="stroke-dashoffset" from="0" to="-24" dur="3.2s" repeatCount="indefinite"/>
    </path>
    <path d="M172 100 a72 72 0 0 1 -144 0" stroke-dasharray="4 8" stroke="#22c55e">
      <animate attributeName="stroke-dashoffset" from="0" to="24" dur="3.2s" repeatCount="indefinite"/>
    </path>
  </g>

  <!-- faceted diamond -->
  <g>
    <polygon points="100,46 142,86 100,154 58,86" fill="url(#b30CoreGrad)">
      <animateTransform attributeName="transform" type="scale" values="1;1.06;1" dur="3.2s" repeatCount="indefinite" additive="sum"/>
    </polygon>
    <!-- top facet highlight -->
    <polygon points="100,46 142,86 100,86 58,86" fill="#ffffff" fill-opacity=".18"/>
    <!-- bottom facet shadow -->
    <polygon points="58,86 100,86 100,154" fill="#000000" fill-opacity=".10"/>
    <polygon points="142,86 100,86 100,154" fill="#000000" fill-opacity=".18"/>
    <!-- engraved "B" -->
    <text x="100" y="112" text-anchor="middle" font-family="Space Grotesk, Inter, sans-serif"
          font-size="34" font-weight="800" fill="#ffffff" letter-spacing="1">B</text>
  </g>
</svg>`;
  }

  function wordmark(size = 36) {
    return `
<span style="display:inline-flex; align-items:center; gap:.55rem;">
  ${master(size)}
  <span style="font-family:'Space Grotesk','Inter', sans-serif; font-weight:800; letter-spacing:-.01em; font-size:1.15rem;">
    <span style="background:linear-gradient(135deg,#3b82f6,#06b6d4,#22c55e); -webkit-background-clip:text; background-clip:text; color:transparent;">2030B</span>
    <span style="opacity:.7; font-weight:600; font-size:.85rem; letter-spacing:.04em;">P2P</span>
  </span>
</span>`;
  }

  // ---------- FEATURE ICONS ----------
  const FEATURE_GLYPHS = {
    'pairing':    `<circle cx="34" cy="40" r="10"/><circle cx="66" cy="60" r="10"/>
                   <path d="M40 46 L60 54"/><path d="M30 56 L70 44" stroke-dasharray="3 3"/>`,
    'credits':    `<circle cx="50" cy="50" r="24"/><text x="50" y="58" text-anchor="middle"
                   font-family="Inter" font-size="22" font-weight="800" fill="#ffffff" stroke="none">C</text>`,
    'levels':     `<rect x="22" y="60" width="14" height="20"/>
                   <rect x="43" y="48" width="14" height="32"/>
                   <rect x="64" y="32" width="14" height="48"/>`,
    'screenshot': `<rect x="22" y="28" width="56" height="40" rx="4"/>
                   <circle cx="36" cy="44" r="4"/>
                   <path d="M28 64 L42 50 L54 60 L72 42 L72 64 Z"/>`,
    'binance':    `<path d="M50 22 L30 42 L40 52 L50 42 L60 52 L70 42 Z"/>
                   <path d="M22 50 L32 60 L22 70 L12 60 Z" transform="translate(28 0)"/>
                   <path d="M50 58 L30 78 L40 78 L50 68 L60 78 L70 78 Z"/>`,
    'redotpay':   `<circle cx="50" cy="50" r="24"/><circle cx="50" cy="50" r="10" fill="#fff" stroke="none"/>
                   <path d="M40 50 L60 50" stroke="#ef4444" stroke-width="3"/>`,
    'multilang':  `<circle cx="50" cy="50" r="26"/>
                   <path d="M24 50 L76 50"/>
                   <path d="M50 24 Q34 50 50 76 Q66 50 50 24"/>`,
    'multicurr':  `<circle cx="38" cy="40" r="14"/>
                   <text x="38" y="46" text-anchor="middle" font-family="Inter" font-size="14" font-weight="800" fill="#ffffff" stroke="none">$</text>
                   <circle cx="62" cy="60" r="14"/>
                   <text x="62" y="66" text-anchor="middle" font-family="Inter" font-size="14" font-weight="800" fill="#ffffff" stroke="none">€</text>`,
    'wallet':     `<rect x="20" y="32" width="60" height="40" rx="6"/>
                   <path d="M20 42 L80 42"/>
                   <circle cx="66" cy="56" r="4" fill="#fff" stroke="none"/>`,
    'secure':     `<path d="M50 22 L72 32 L72 52 Q72 72 50 80 Q28 72 28 52 L28 32 Z"/>
                   <path d="M40 52 L48 60 L62 44" stroke-width="4"/>`,
    'admin':      `<circle cx="50" cy="40" r="12"/>
                   <path d="M28 76 Q50 56 72 76"/>
                   <path d="M58 32 L66 28 L70 32 L70 38 L66 42 L58 38 Z" fill="#fff" stroke="none"/>`,
    'future':     `<path d="M28 50 L72 50"/>
                   <path d="M60 38 L72 50 L60 62" fill="none"/>
                   <circle cx="50" cy="50" r="28" stroke-dasharray="3 4"/>`,
    'auth':       `<rect x="30" y="44" width="40" height="32" rx="4"/>
                   <path d="M38 44 V36 a12 12 0 0 1 24 0 V44"/>
                   <circle cx="50" cy="60" r="4" fill="#fff" stroke="none"/>`,
    'ecosystem':  `<circle cx="50" cy="50" r="6" fill="#fff" stroke="none"/>
                   <circle cx="50" cy="22" r="6"/><circle cx="74" cy="38" r="6"/>
                   <circle cx="74" cy="62" r="6"/><circle cx="50" cy="78" r="6"/>
                   <circle cx="26" cy="62" r="6"/><circle cx="26" cy="38" r="6"/>
                   <path d="M50 28 L50 44 M68 42 L54 48 M68 58 L54 52 M50 72 L50 56 M32 58 L46 52 M32 42 L46 48"/>`
  };

  function feature(slug, size = 64) {
    const f = FEATURES.find(x => x.slug === slug);
    if (!f) return '';
    const id = `b30-${slug}`;
    return `
<svg viewBox="0 0 100 100" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${f.name}">
  <defs>
    <linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"  stop-color="${f.color}"/>
      <stop offset="100%" stop-color="${f.accent}"/>
    </linearGradient>
  </defs>
  <rect x="2" y="2" width="96" height="96" rx="22" fill="url(#${id})"/>
  <g data-glyph fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">${FEATURE_GLYPHS[slug] || ''}</g>
</svg>`;
  }

  // ---------- LEVEL BADGES ----------
  function level(n, size = 64) {
    const palette = {
      1: { a:'#22c55e', b:'#06b6d4', label:'1' },
      2: { a:'#06b6d4', b:'#3b82f6', label:'2' },
      3: { a:'#f59e0b', b:'#f43f5e', label:'3' },
      4: { a:'#7c3aed', b:'#f43f5e', label:'4' }
    };
    const p = palette[n] || palette[1];
    const id = `b30-lvl-${n}`;
    return `
<svg viewBox="0 0 100 100" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Level ${n}">
  <defs>
    <linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="${p.a}"/>
      <stop offset="100%" stop-color="${p.b}"/>
    </linearGradient>
  </defs>
  <polygon points="50,4 92,28 92,72 50,96 8,72 8,28" fill="url(#${id})"/>
  <polygon points="50,12 84,32 84,68 50,88 16,68 16,32" fill="none" stroke="#ffffff" stroke-opacity=".45" stroke-width="2"/>
  <text x="50" y="62" text-anchor="middle" font-family="Space Grotesk, Inter, sans-serif"
        font-size="34" font-weight="800" fill="#ffffff">${p.label}</text>
</svg>`;
  }

  // ---------- PLATFORM GLYPHS ----------
  function platform(slug, size = 40) {
    if (slug === 'binance') {
      return `
<svg viewBox="0 0 100 100" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" aria-label="Binance">
  <rect x="2" y="2" width="96" height="96" rx="20" fill="#0b0f1a"/>
  <g fill="#f3ba2f">
    <path d="M50 22 L36 36 L42 42 L50 34 L58 42 L64 36 Z"/>
    <path d="M22 50 L28 44 L34 50 L28 56 Z"/>
    <path d="M66 50 L72 44 L78 50 L72 56 Z"/>
    <path d="M50 78 L36 64 L42 58 L50 66 L58 58 L64 64 Z"/>
    <rect x="46" y="46" width="8" height="8" transform="rotate(45 50 50)"/>
  </g>
</svg>`;
    }
    return `
<svg viewBox="0 0 100 100" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" aria-label="RedotPay">
  <rect x="2" y="2" width="96" height="96" rx="20" fill="#ffffff"/>
  <circle cx="50" cy="50" r="28" fill="none" stroke="#ef4444" stroke-width="6"/>
  <circle cx="50" cy="50" r="10" fill="#ef4444"/>
</svg>`;
  }

  // ---------- ECOSYSTEM CURRENCY BADGE ----------
  // Tiny circular badge for ecosystem currency tokens (CTC, TIC, VTC, ...).
  function currency(symbol, primary, accent, size = 56) {
    primary = primary || '#3b82f6'; accent = accent || '#06b6d4';
    const id = `b30-cur-${symbol}`;
    return `
<svg viewBox="0 0 100 100" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" aria-label="${symbol}">
  <defs>
    <linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="${primary}"/>
      <stop offset="100%" stop-color="${accent}"/>
    </linearGradient>
  </defs>
  <circle cx="50" cy="50" r="46" fill="url(#${id})"/>
  <circle cx="50" cy="50" r="42" fill="none" stroke="#ffffff" stroke-opacity=".4" stroke-width="2"/>
  <text x="50" y="58" text-anchor="middle" font-family="Space Grotesk,Inter,sans-serif"
        font-size="22" font-weight="800" fill="#ffffff" letter-spacing="1">${symbol}</text>
</svg>`;
  }

  // ---------- AUTO-RENDER ----------
  function renderAuto() {
    document.querySelectorAll('[data-b30-logo]').forEach(el => {
      const kind = el.dataset.b30Logo;
      const size = parseInt(el.dataset.size || '48', 10);
      el.innerHTML = kind === 'wordmark' ? wordmark(size) : master(size);
      el.classList.add('b30-svg-anim');
    });
    document.querySelectorAll('[data-b30-icon]').forEach(el => {
      const slug = el.dataset.b30Icon;
      const size = parseInt(el.dataset.size || '64', 10);
      el.innerHTML = feature(slug, size);
    });
    document.querySelectorAll('[data-b30-level]').forEach(el => {
      const n = parseInt(el.dataset.b30Level, 10) || 1;
      const size = parseInt(el.dataset.size || '64', 10);
      el.innerHTML = level(n, size);
    });
    document.querySelectorAll('[data-b30-platform]').forEach(el => {
      const slug = el.dataset.b30Platform;
      const size = parseInt(el.dataset.size || '40', 10);
      el.innerHTML = platform(slug, size);
    });
    document.querySelectorAll('[data-b30-currency]').forEach(el => {
      const sym = el.dataset.b30Currency;
      const size = parseInt(el.dataset.size || '56', 10);
      el.innerHTML = currency(sym, el.dataset.primary, el.dataset.accent, size);
    });
  }

  window.B30Logos = { master, wordmark, feature, level, platform, currency, FEATURES, renderAuto };
  window.WBLogos = window.WBLogos || { renderAuto };
  document.addEventListener('DOMContentLoaded', renderAuto);
})();
