/* 2030B P2P Pairing — SVG asset library + boot loader
 * Exposes window.B30Logos with helpers:
 *   B30Logos.master(size)          -> animated "2030B" P2P pairing logo
 *   B30Logos.wordmark(size)        -> master + "2030B" type
 *   B30Logos.feature(slug, size)   -> animated feature icon
 *   B30Logos.level(n, size)        -> animated level badge (1..4)
 *   B30Logos.platform(slug, size)  -> binance / redotpay glyphs
 *   B30Logos.FEATURES              -> array of feature metadata
 *   B30Logos.renderAuto()          -> scans [data-b30-logo], [data-b30-icon], [data-b30-level], [data-b30-platform]
 *
 * BOOT LOADER: This file is loaded earliest on every page. We use it as the
 * boot vehicle for the page loader (so it shows BEFORE page paint).
 */
(function bootLoader() {
  if (window.__b30Boot) return;
  window.__b30Boot = true;

  var css =
    "#b30-loader{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;"+
    "background:radial-gradient(ellipse at center,#0b1736 0%,#04060f 100%);"+
    "transition:opacity .55s ease,visibility .55s ease;font-family:'Space Grotesk','Inter',sans-serif}"+
    "#b30-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}"+
    "#b30-loader .loader-inner{display:flex;flex-direction:column;align-items:center;gap:1.25rem}"+
    /* Orbit P2P logo */
    ".b30-loader-orbit{width:130px;height:130px;position:relative}"+
    ".b30-loader-orbit .core{position:absolute;left:50%;top:50%;width:54px;height:54px;margin:-27px 0 0 -27px;"+
      "border-radius:14px;background:linear-gradient(135deg,#3b82f6,#06b6d4,#22c55e);"+
      "box-shadow:0 0 30px rgba(59,130,246,.55);display:flex;align-items:center;justify-content:center;"+
      "color:#fff;font-weight:800;font-size:14px;letter-spacing:.04em;animation:b30Pulse 2.2s ease-in-out infinite}"+
    ".b30-loader-orbit .ring{position:absolute;inset:0;border-radius:50%;"+
      "border:2px dashed rgba(59,130,246,.45);animation:b30Spin 6s linear infinite}"+
    ".b30-loader-orbit .ring.outer{inset:-12px;border-color:rgba(6,182,212,.35);"+
      "animation-duration:9s;animation-direction:reverse}"+
    ".b30-loader-orbit .node{position:absolute;width:14px;height:14px;border-radius:50%;"+
      "background:linear-gradient(135deg,#22c55e,#06b6d4);box-shadow:0 0 14px rgba(34,197,94,.6)}"+
    ".b30-loader-orbit .node.n1{top:-6px;left:50%;margin-left:-7px;animation:b30Float 2.4s ease-in-out infinite}"+
    ".b30-loader-orbit .node.n2{bottom:-6px;left:50%;margin-left:-7px;background:linear-gradient(135deg,#f59e0b,#f43f5e);"+
      "box-shadow:0 0 14px rgba(244,63,94,.55);animation:b30Float 2.4s ease-in-out -1.2s infinite}"+
    ".b30-loader-orbit .arrow{position:absolute;left:50%;top:50%;width:96px;height:96px;margin:-48px 0 0 -48px;"+
      "border:2px solid transparent;border-top-color:#3b82f6;border-right-color:#06b6d4;border-radius:50%;"+
      "animation:b30Spin 1.8s linear infinite}"+
    "@keyframes b30Spin{to{transform:rotate(360deg)}}"+
    "@keyframes b30Pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}"+
    "@keyframes b30Float{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}"+
    /* Sliding-gradient text */
    "#b30-loader .loader-text{font-weight:700;font-size:1.05rem;letter-spacing:.02em;"+
      "background:linear-gradient(90deg,#3b82f6,#06b6d4,#22c55e,#f59e0b,#3b82f6);"+
      "-webkit-background-clip:text;background-clip:text;color:transparent;"+
      "background-size:200% 100%;animation:b30Slide 2.6s linear infinite}"+
    "#b30-loader .loader-sub{font-size:.78rem;color:rgba(255,255,255,.55);margin-top:-.5rem}"+
    "#b30-loader .loader-bar{width:240px;height:3px;border-radius:99px;"+
      "background:rgba(255,255,255,.08);overflow:hidden;position:relative}"+
    "#b30-loader .loader-bar::after{content:\"\";position:absolute;inset:0;width:40%;"+
      "background:linear-gradient(90deg,transparent,#3b82f6,#06b6d4,transparent);"+
      "animation:b30Bar 1.4s ease-in-out infinite}"+
    "@keyframes b30Slide{0%{background-position:0 0}100%{background-position:200% 0}}"+
    "@keyframes b30Bar{0%{transform:translateX(-60%)}100%{transform:translateX(260%)}}";

  var s = document.createElement('style'); s.id = 'b30-loader-css'; s.textContent = css;
  (document.head || document.documentElement).appendChild(s);

  function build() {
    if (document.getElementById('b30-loader')) return;
    if (!document.body) return setTimeout(build, 10);
    var l = document.createElement('div');
    l.id = 'b30-loader';
    l.setAttribute('aria-hidden', 'true');
    l.innerHTML =
      '<div class="loader-inner">' +
        '<div class="b30-loader-orbit">' +
          '<div class="ring outer"></div>' +
          '<div class="ring"></div>' +
          '<div class="arrow"></div>' +
          '<div class="node n1"></div>' +
          '<div class="node n2"></div>' +
          '<div class="core">2030B</div>' +
        '</div>' +
        '<div class="loader-text">Pairing your P2P credits…</div>' +
        '<div class="loader-sub">Buy · Sell · Earn credits · Level up</div>' +
        '<div class="loader-bar" role="progressbar" aria-label="Loading"></div>' +
      '</div>';
    document.body.appendChild(l);
  }
  if (document.body) build(); else document.addEventListener('DOMContentLoaded', build, { once: true });

  function hide() {
    var l = document.getElementById('b30-loader'); if (!l) return;
    setTimeout(function () { l.classList.add('is-hidden'); }, 200);
    setTimeout(function () { l.parentNode && l.parentNode.removeChild(l); }, 1200);
  }
  if (document.readyState === 'complete') setTimeout(hide, 700);
  else window.addEventListener('load', function () { setTimeout(hide, 400); });
  setTimeout(hide, 6000);
})();

(function () {
  // ---------- FEATURE METADATA ----------
  const FEATURES = [
    { slug:'pairing',    name:'P2P Pairing',     color:'#3b82f6', accent:'#06b6d4', desc:'Buy + Sell = 1 verified pairing = 1 credit.' },
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
    { slug:'future',     name:'Level 4 Internal',color:'#f43f5e', accent:'#7c3aed', desc:'Future: 2030B native internal P2P engine.' }
  ];

  // ---------- MASTER LOGO ----------
  // P2P orbit: two opposing nodes (buyer/seller) connected by a swap arrow ring,
  // central "2030B" tile with gradient. Animated: ring spin, nodes glow, tile pulse.
  function master(size = 48) {
    return `
<svg viewBox="0 0 200 200" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="2030B P2P Pairing">
  <defs>
    <linearGradient id="b30CoreGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"  stop-color="#3b82f6"/>
      <stop offset="55%" stop-color="#06b6d4"/>
      <stop offset="100%" stop-color="#22c55e"/>
    </linearGradient>
    <linearGradient id="b30NodeA" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#22c55e"/><stop offset="100%" stop-color="#06b6d4"/>
    </linearGradient>
    <linearGradient id="b30NodeB" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#f59e0b"/><stop offset="100%" stop-color="#f43f5e"/>
    </linearGradient>
    <radialGradient id="b30Glow" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#3b82f6" stop-opacity=".35"/>
      <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>
    </radialGradient>
  </defs>

  <!-- background tile -->
  <rect x="6" y="6" width="188" height="188" rx="44" fill="url(#b30CoreGrad)" opacity=".14"/>
  <circle cx="100" cy="100" r="92" fill="url(#b30Glow)"/>

  <!-- orbit ring (rotating) -->
  <g style="transform-origin:100px 100px; animation: b30LogoSpin 14s linear infinite">
    <circle cx="100" cy="100" r="74" fill="none" stroke="#06b6d4" stroke-width="2"
            stroke-dasharray="6 8" opacity=".55"/>
  </g>
  <!-- counter ring -->
  <g style="transform-origin:100px 100px; animation: b30LogoSpin 18s linear infinite reverse">
    <circle cx="100" cy="100" r="86" fill="none" stroke="#3b82f6" stroke-width="1.5"
            stroke-dasharray="2 10" opacity=".4"/>
  </g>

  <!-- swap arrows (buy <-> sell) -->
  <g fill="none" stroke="url(#b30CoreGrad)" stroke-width="3" stroke-linecap="round">
    <path d="M52 86 Q100 60 148 86" data-flow style="animation: b30Flow 2.4s ease-in-out infinite"/>
    <path d="M148 86 L142 78 M148 86 L156 82" />
    <path d="M148 114 Q100 140 52 114" data-flow style="animation: b30Flow 2.4s ease-in-out -1.2s infinite"/>
    <path d="M52 114 L58 122 M52 114 L44 118"/>
  </g>

  <!-- buyer node (top) -->
  <circle cx="100" cy="22" r="11" fill="url(#b30NodeA)">
    <animate attributeName="r" values="11;13;11" dur="2.4s" repeatCount="indefinite"/>
  </circle>
  <!-- seller node (bottom) -->
  <circle cx="100" cy="178" r="11" fill="url(#b30NodeB)">
    <animate attributeName="r" values="11;13;11" dur="2.4s" begin="-1.2s" repeatCount="indefinite"/>
  </circle>

  <!-- central tile with "2030B" -->
  <g data-spine-pulse style="transform-origin:100px 100px; animation: b30TilePulse 3.2s ease-in-out infinite">
    <rect x="58" y="78" width="84" height="44" rx="12" fill="url(#b30CoreGrad)"/>
    <text x="100" y="107" text-anchor="middle"
          font-family="Space Grotesk, Inter, sans-serif"
          font-size="22" font-weight="800" fill="#ffffff" letter-spacing="1">2030B</text>
  </g>

  <style>
    @keyframes b30LogoSpin { to { transform: rotate(360deg); } }
    @keyframes b30TilePulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.06); } }
    @keyframes b30Flow { 0%,100% { stroke-dashoffset: 0; opacity:.95 } 50% { opacity:.55 } }
  </style>
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
                   <circle cx="50" cy="50" r="28" stroke-dasharray="3 4"/>`
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
    // redotpay
    return `
<svg viewBox="0 0 100 100" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" aria-label="RedotPay">
  <rect x="2" y="2" width="96" height="96" rx="20" fill="#ffffff"/>
  <circle cx="50" cy="50" r="28" fill="none" stroke="#ef4444" stroke-width="6"/>
  <circle cx="50" cy="50" r="10" fill="#ef4444"/>
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
  }

  window.B30Logos = { master, wordmark, feature, level, platform, FEATURES, renderAuto };
  // also expose legacy alias for code paths that still expect WBLogos.renderAuto()
  window.WBLogos = window.WBLogos || { renderAuto };
  document.addEventListener('DOMContentLoaded', renderAuto);
})();
