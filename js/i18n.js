/* 2030B P2P Pairing — i18n loader
 * Loads /lang/{code}.json on demand, applies translations to [data-i18n] elements,
 * handles RTL switching for Arabic, persists choice in localStorage('b30-lang').
 *
 * Public API:
 *   B30I18n.current()      -> current code (e.g. "en")
 *   B30I18n.t(key, vars?)  -> translated string (variables: {amount: 10})
 *   B30I18n.set(code)      -> switch to language, reload page strings
 *   B30I18n.apply(root?)   -> apply translations to DOM
 *   B30I18n.ready()        -> Promise resolved when current dict is loaded
 *
 * Mark elements:
 *   <span data-i18n="hero.title"></span>          -> textContent
 *   <input data-i18n-placeholder="form.name" />   -> placeholder attr
 *   <a data-i18n-title="..." data-i18n-aria-label="...">
 */
(function () {
  const RTL_CODES = ['ar', 'he', 'fa', 'ur'];
  const STORAGE = 'b30-lang';
  const cache = {};   // code -> dict
  let current = 'en';
  let readyResolve;
  const readyPromise = new Promise(r => { readyResolve = r; });

  function prefix() {
    return (document.body && document.body.dataset.prefix) || '';
  }

  function fmt(str, vars) {
    if (!vars) return str;
    return String(str).replace(/\{(\w+)\}/g, (_, k) => (k in vars ? vars[k] : `{${k}}`));
  }

  function lookup(dict, key) {
    if (!dict) return undefined;
    if (dict[key] !== undefined) return dict[key];
    // dot path fallback
    const parts = key.split('.');
    let cur = dict;
    for (const p of parts) {
      if (cur && typeof cur === 'object' && p in cur) cur = cur[p];
      else return undefined;
    }
    return typeof cur === 'string' ? cur : undefined;
  }

  function t(key, vars) {
    const dict = cache[current] || cache['en'] || {};
    let v = lookup(dict, key);
    if (v === undefined && current !== 'en') v = lookup(cache['en'], key);
    if (v === undefined) return key;
    return fmt(v, vars);
  }

  async function loadDict(code) {
    if (cache[code]) return cache[code];
    try {
      const res = await fetch(`${prefix()}lang/${code}.json`, { cache: 'no-cache' });
      if (res.ok) cache[code] = await res.json();
      else cache[code] = {};
    } catch (e) { cache[code] = {}; }
    return cache[code];
  }

  function applyDir(code) {
    const html = document.documentElement;
    html.setAttribute('lang', code);
    if (RTL_CODES.includes(code)) html.setAttribute('dir', 'rtl');
    else html.removeAttribute('dir');
  }

  function apply(root) {
    const scope = root || document;
    scope.querySelectorAll('[data-i18n]').forEach(el => {
      const v = t(el.dataset.i18n);
      if (v !== el.dataset.i18n) el.textContent = v;
    });
    scope.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const v = t(el.dataset.i18nPlaceholder);
      if (v) el.setAttribute('placeholder', v);
    });
    scope.querySelectorAll('[data-i18n-title]').forEach(el => {
      const v = t(el.dataset.i18nTitle);
      if (v) el.setAttribute('title', v);
    });
    scope.querySelectorAll('[data-i18n-aria-label]').forEach(el => {
      const v = t(el.dataset.i18nAriaLabel);
      if (v) el.setAttribute('aria-label', v);
    });
    scope.querySelectorAll('[data-i18n-html]').forEach(el => {
      const v = t(el.dataset.i18nHtml);
      if (v) el.innerHTML = v;
    });
  }

  async function set(code) {
    current = code;
    localStorage.setItem(STORAGE, code);
    await loadDict(code);
    if (code !== 'en') await loadDict('en'); // for fallback keys
    applyDir(code);
    apply();
    document.dispatchEvent(new CustomEvent('b30:lang-changed', { detail: { code } }));
  }

  async function init() {
    let code = localStorage.getItem(STORAGE);
    if (!code) {
      const nav = (navigator.language || 'en').slice(0,2).toLowerCase();
      const supported = ['en','ar','fr','es','de','pt','it','zh','hi','ja','ru','tr'];
      code = supported.includes(nav) ? nav : 'en';
    }
    current = code;
    await loadDict('en');     // always load English as fallback
    if (code !== 'en') await loadDict(code);
    applyDir(code);
    apply();
    readyResolve(current);
  }

  window.B30I18n = {
    current: () => current,
    t, set, apply,
    ready: () => readyPromise,
    dict: () => cache[current] || {}
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
