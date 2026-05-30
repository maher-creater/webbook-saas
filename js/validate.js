/* 2030B P2P Pairing — Form validation helper
 *
 * Adds a small, framework-free validator that:
 *   - runs HTML5 constraint validity (required / type / pattern / min / max)
 *   - allows custom rules via data attributes:
 *       data-validate-rule="email|usdt|uid|regex:^abc$|min:1|max:100"
 *       data-validate-match="<inputName>"   (for confirm-password)
 *   - decorates invalid inputs with data-error="1" → CSS shake animation
 *   - inserts <span class="b30-error-text"> messages
 *   - i18n-aware via window.B30I18n.t('form.err.*')
 *
 * Public API:
 *   B30Validate.form(formEl)               -> {ok, errors:[{name,msg}], firstInvalid}
 *   B30Validate.field(inputEl)             -> {ok, msg}
 *   B30Validate.wireForm(formEl, opts?)    -> attaches live validation on blur/input
 *   B30Validate.reset(formEl)              -> clears error states
 *
 * Mark forms with `data-validate` to auto-wire on shell:rendered.
 */
(function () {
  if (window.B30Validate) return;

  function t(key, fallback) {
    try {
      const v = window.B30I18n && window.B30I18n.t && window.B30I18n.t(key);
      return (v && v !== key) ? v : fallback;
    } catch (e) { return fallback; }
  }

  // ---- Rule registry ----
  const RULES = {
    email: (v) => !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
                  ? null
                  : t('form.err.email', 'Invalid email address'),
    usdt: (v) => {
      if (!v) return null;
      const n = parseFloat(v);
      if (isNaN(n) || n <= 0) return t('form.err.usdt', 'Enter a positive USDT amount');
      return null;
    },
    positive: (v) => {
      if (!v) return null;
      const n = parseFloat(v);
      if (isNaN(n) || n <= 0) return t('form.err.positive', 'Value must be greater than zero');
      return null;
    },
    uid: (v, _el, param) => {
      if (!v) return null;
      // param is the provider code; look up from B30Store
      try {
        const prov = window.B30Store && window.B30Store.getProvider && window.B30Store.getProvider(param);
        if (prov && prov.uid_regex) {
          const re = new RegExp(prov.uid_regex);
          if (!re.test(v)) return t('form.err.uid', 'Invalid UID for this provider');
        }
      } catch (e) {}
      return null;
    },
    regex: (v, _el, param) => {
      if (!v || !param) return null;
      try { return new RegExp(param).test(v) ? null : t('form.err.regex', 'Invalid format'); }
      catch (e) { return null; }
    },
    min: (v, _el, param) => {
      if (!v) return null;
      const n = parseFloat(v), m = parseFloat(param);
      return n < m ? t('form.err.min', 'Minimum') + ' ' + m : null;
    },
    max: (v, _el, param) => {
      if (!v) return null;
      const n = parseFloat(v), m = parseFloat(param);
      return n > m ? t('form.err.max', 'Maximum') + ' ' + m : null;
    },
    minLength: (v, _el, param) => {
      if (!v) return null;
      const m = parseInt(param, 10);
      return v.length < m
        ? (t('form.err.minLength', 'Minimum {n} characters') || '').replace('{n}', m)
        : null;
    },
    file: (_v, el) => {
      const f = el && el.files && el.files[0];
      if (!f && el && el.required) return t('form.err.fileRequired', 'Please choose a file');
      if (f) {
        const maxBytes = parseInt(el.dataset.maxBytes || (5 * 1024 * 1024), 10);
        if (f.size > maxBytes) return t('form.err.fileTooBig', 'File is too big');
        const allowed = (el.dataset.acceptMime || '').split(',').map(s => s.trim()).filter(Boolean);
        if (allowed.length && !allowed.includes(f.type)) return t('form.err.fileMime', 'Unsupported file type');
      }
      return null;
    },
  };

  function parseRules(spec) {
    if (!spec) return [];
    return spec.split('|').map(part => {
      const idx = part.indexOf(':');
      if (idx === -1) return { name: part.trim(), param: null };
      return { name: part.slice(0, idx).trim(), param: part.slice(idx + 1) };
    }).filter(r => r.name);
  }

  function getValue(el) {
    if (!el) return '';
    if (el.type === 'checkbox' || el.type === 'radio') return el.checked ? (el.value || 'on') : '';
    if (el.type === 'file') return el.files && el.files[0] ? el.files[0].name : '';
    return (el.value == null ? '' : String(el.value)).trim();
  }

  function ensureErrorEl(el) {
    if (!el || !el.parentElement) return null;
    let next = el.nextElementSibling;
    if (next && next.classList && next.classList.contains('b30-error-text')) return next;
    next = document.createElement('span');
    next.className = 'b30-error-text';
    el.parentElement.insertBefore(next, el.nextSibling);
    return next;
  }

  function showError(el, msg) {
    if (!el) return;
    el.setAttribute('data-error', '1');
    el.setAttribute('aria-invalid', 'true');
    const er = ensureErrorEl(el);
    if (er) { er.textContent = msg; er.classList.add('is-visible'); }
  }
  function clearError(el) {
    if (!el) return;
    el.removeAttribute('data-error');
    el.removeAttribute('aria-invalid');
    const next = el.nextElementSibling;
    if (next && next.classList && next.classList.contains('b30-error-text')) {
      next.textContent = ''; next.classList.remove('is-visible');
    }
  }

  function validateField(el) {
    if (!el || el.disabled) return { ok: true };
    if (el.type === 'hidden') return { ok: true };
    const v = getValue(el);

    // 1. HTML5 native validity
    if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
      let msg = el.validationMessage || t('form.err.invalid', 'Invalid value');
      if (el.validity && el.validity.valueMissing) msg = t('form.err.required', 'This field is required');
      else if (el.validity && el.validity.typeMismatch && el.type === 'email') msg = t('form.err.email', 'Invalid email address');
      else if (el.validity && el.validity.patternMismatch) msg = t('form.err.regex', 'Invalid format');
      else if (el.validity && el.validity.rangeUnderflow) msg = (t('form.err.min', 'Minimum') + ' ' + (el.min || ''));
      else if (el.validity && el.validity.rangeOverflow)  msg = (t('form.err.max', 'Maximum') + ' ' + (el.max || ''));
      return { ok: false, msg: msg };
    }

    // 2. Match another input (e.g. confirm-password)
    const matchName = el.dataset.validateMatch;
    if (matchName) {
      const peer = (el.form || document).querySelector('[name="' + matchName + '"]');
      if (peer && getValue(peer) !== v) {
        return { ok: false, msg: t('form.err.match', 'Fields do not match') };
      }
    }

    // 3. Custom rule chain
    const rules = parseRules(el.dataset.validateRule);
    for (const r of rules) {
      const fn = RULES[r.name];
      if (!fn) continue;
      const msg = fn(v, el, r.param);
      if (msg) return { ok: false, msg: msg };
    }

    return { ok: true };
  }

  function validateForm(form) {
    const errors = [];
    let firstInvalid = null;
    const els = form.querySelectorAll('input, select, textarea');
    els.forEach(el => {
      if (el.type === 'submit' || el.type === 'button') return;
      const r = validateField(el);
      if (!r.ok) {
        errors.push({ name: el.name || el.id || '?', msg: r.msg });
        showError(el, r.msg);
        if (!firstInvalid) firstInvalid = el;
      } else {
        clearError(el);
      }
    });
    return { ok: errors.length === 0, errors: errors, firstInvalid: firstInvalid };
  }

  function reset(form) {
    form.querySelectorAll('[data-error]').forEach(el => clearError(el));
  }

  function wireForm(form, opts) {
    if (!form || form.dataset.validateWired) return;
    form.dataset.validateWired = '1';
    opts = opts || {};
    // Use noValidate so we can show our own errors (still keep HTML5 rules)
    form.setAttribute('novalidate', '');
    form.addEventListener('submit', (e) => {
      const res = validateForm(form);
      if (!res.ok) {
        e.preventDefault();
        e.stopPropagation();
        if (res.firstInvalid) {
          res.firstInvalid.focus();
          res.firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (typeof opts.onInvalid === 'function') opts.onInvalid(res);
        return;
      }
      if (typeof opts.onValid === 'function') opts.onValid(res, e);
    });
    // live blur validation
    form.addEventListener('blur', (e) => {
      const el = e.target;
      if (!el || !el.matches('input,select,textarea')) return;
      const r = validateField(el);
      if (r.ok) clearError(el); else showError(el, r.msg);
    }, true);
    // clear error on input
    form.addEventListener('input', (e) => {
      const el = e.target;
      if (el && el.hasAttribute('data-error')) clearError(el);
    });
  }

  // Auto-wire on shell:rendered and DOM ready
  function autoWire() {
    document.querySelectorAll('form[data-validate]:not([data-validate-wired])').forEach(f => wireForm(f));
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoWire);
  else autoWire();
  document.addEventListener('shell:rendered', autoWire);

  window.B30Validate = {
    form: validateForm,
    field: validateField,
    wireForm: wireForm,
    reset: reset,
    rules: RULES,
  };
})();
