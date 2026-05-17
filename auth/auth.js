/* 2030B Auth — client logic
 *
 * Modes:
 *   - Standalone (default): /auth/index.html opened directly. On success, the
 *     user is redirected to /pages/dashboard.html.
 *   - Popup-embedded: /auth/index.html?api_key=XXX&origin=https://foo.com&popup=1
 *     opened in a popup from a partner site. On success, posts a message
 *     to window.opener (and closes the popup):
 *       { type: 'b30:auth', ok: true, token: '...', user: {...} }
 *
 *   - The popup mode is intentionally simple and works without a server: the
 *     IndexedDB-based store still validates credentials. For the production
 *     server-bound flow, /auth/api/*.php endpoints implement the same
 *     behavior with real bcrypt and SQLite.
 */
(function () {
  // ---------- helpers ----------
  function $ (s, r) { return (r || document).querySelector(s); }
  function $$ (s, r) { return Array.from((r || document).querySelectorAll(s)); }
  function showPanel (name) {
    $$('[data-auth-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.authPanel !== name));
    $$('.auth-tab').forEach(t => t.classList.toggle('is-active', t.dataset.authTab === name));
  }
  function qs (key) {
    const m = new URLSearchParams(location.search);
    return m.get(key);
  }
  function passwordScore (pw) {
    let s = 0;
    if (!pw) return 0;
    if (pw.length >= 8) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/[0-9]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return Math.min(4, s);
  }
  function whenReady () {
    return Promise.all([
      new Promise(r => window.B30Config.onLoad(r)),
      window.B30I18n.ready(),
      window.B30Store.ready()
    ]);
  }
  function postToOpener (payload) {
    if (!window.opener) return false;
    try {
      const origin = qs('origin') || '*';
      window.opener.postMessage(Object.assign({ type: 'b30:auth' }, payload), origin);
      return true;
    } catch (e) { return false; }
  }
  function postToParent (payload) {
    if (window.parent === window) return false;
    try {
      const origin = qs('origin') || '*';
      window.parent.postMessage(Object.assign({ type: 'b30:auth' }, payload), origin);
      return true;
    } catch (e) { return false; }
  }
  function notifyAndExit (user) {
    const popup = qs('popup') === '1';
    const payload = {
      ok: true,
      user: {
        id: user.id, email: user.email, full_name: user.full_name,
        country_code: user.country_code, language: user.language,
        level: user.level, total_credits: user.total_credits,
        binance_id: user.binance_id, redotpay_id: user.redotpay_id,
        profile_completed: user.profile_completed,
        email_verified: user.email_verified
      },
      token: 'b30_' + user.id
    };
    if (popup) {
      postToOpener(payload) || postToParent(payload);
      setTimeout(() => { try { window.close(); } catch (e) {} }, 300);
    } else {
      location.href = '../pages/dashboard.html';
    }
  }

  // ---------- social providers (visual demo) ----------
  function renderSocial (target) {
    const providers = [
      { id:'google', label:'Google',  color:'#ea4335' },
      { id:'github', label:'GitHub',  color:'#24292e' },
      { id:'x',      label:'X',       color:'#0f172a' },
      { id:'facebook',label:'Facebook',color:'#1877f2' }
    ];
    target.innerHTML = providers.slice(0, 2).map(p => `
      <button type="button" class="btn" data-social="${p.id}" title="Continue with ${p.label}">
        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${p.color}"></span>
        Continue with ${p.label}
      </button>`).join('');
    $$('button[data-social]', target).forEach(b => b.addEventListener('click', async () => {
      // Visual: simulate a social handshake with SweetAlert2.
      const id = b.dataset.social;
      const fake = `${id}_${Math.floor(Math.random()*1000)}@2030b.social`;
      await Swal.fire({
        icon:'info', timer: 1200, showConfirmButton:false,
        title: `Connecting via ${id}…`
      });
      try {
        let u = await window.B30Store.signIn(fake, 'social-demo-pw').catch(() => null);
        if (!u) {
          u = await window.B30Store.register({
            email: fake, password: 'social-demo-pw',
            full_name: id[0].toUpperCase() + id.slice(1) + ' User',
            country_code: 'TN', language: window.B30I18n.current()
          });
          u.email_verified = true; // Social = trusted
          u.auth_provider = id;
          // Persist verified state
          if (window.B30Store.updateProfile) await window.B30Store.updateProfile(u.id, {});
        }
        notifyAndExit(u);
      } catch (e) {
        Swal.fire({ icon:'error', title: e.message || 'Social sign-in failed' });
      }
    }));
  }

  // ---------- tabs ----------
  $$('button[data-auth-tab]').forEach(b => b.addEventListener('click', () => showPanel(b.dataset.authTab)));

  // ---------- password reveal ----------
  $$('button[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
    const inp = b.previousElementSibling.tagName === 'INPUT' ? b.previousElementSibling : b.parentElement.querySelector('input[type=password],input[type=text]');
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }));

  // ---------- popup mode styling ----------
  if (qs('popup') === '1') document.body.classList.add('b30-auth-popup');

  // ---------- API-key gate (for popup-embedded usage) ----------
  async function checkApiKey () {
    const k = qs('api_key');
    if (!k) return true; // not embedded
    try {
      const rec = await window.B30Store.findApiKey(k);
      if (!rec || !rec.enabled) throw new Error('Invalid API key');
      // origin check (lenient: wildcard supported)
      const origin = qs('origin') || '';
      if (rec.origins && rec.origins.length && !rec.origins.includes('*') && origin && !rec.origins.includes(origin)) {
        throw new Error('Origin not allowed for this API key');
      }
      // Touch last_used
      await window.B30Store.updateApiKey(rec.id, { last_used: Date.now() });
      return true;
    } catch (e) {
      Swal.fire({ icon:'error', title:'Auth API key error', text: e.message || 'Forbidden', confirmButtonText:'OK' });
      return false;
    }
  }

  // ---------- forms ----------
  function bindSignIn () {
    const f = $('#signinForm');
    if (!f) return;
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = new FormData(f);
      try {
        const u = await window.B30Store.signIn(d.get('email'), d.get('password'));
        Swal.fire({ icon:'success', timer:900, showConfirmButton:false, title:'Welcome back!' });
        notifyAndExit(u);
      } catch (err) {
        Swal.fire({ icon:'error', title: err.message || 'Sign-in failed' });
      }
    });
  }
  function bindSignUp () {
    const f = $('#signupForm');
    if (!f) return;
    // password meter
    const pw = f.querySelector('input[name=password]');
    const meter = f.querySelector('.auth-pw-meter');
    pw.addEventListener('input', () => meter.dataset.strength = passwordScore(pw.value));
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = new FormData(f);
      if (!d.get('terms')) {
        return Swal.fire({ icon:'warning', title:'Please accept the terms' });
      }
      if (passwordScore(d.get('password')) < 2) {
        return Swal.fire({ icon:'warning', title:'Password too weak', text:'Use 8+ characters with letters and numbers.' });
      }
      try {
        const u = await window.B30Store.register({
          email: d.get('email'),
          password: d.get('password'),
          full_name: d.get('full_name'),
          country_code: 'TN',
          language: window.B30I18n.current()
        });
        await window.B30Store.startEmailVerification(u.id);
        await Swal.fire({
          icon:'info', timer:1400, showConfirmButton:false,
          title:'Account created — verify your email',
          text:'Check your inbox for the 6-digit code. (Demo: open the browser console.)'
        });
        showPanel('verify');
      } catch (err) {
        Swal.fire({ icon:'error', title: err.message || 'Could not create account' });
      }
    });
  }
  function bindVerify () {
    const cells = $$('#otpGrid .otp-cell');
    cells.forEach((c, i) => {
      c.addEventListener('input', () => {
        c.value = (c.value || '').replace(/\D/g,'').slice(0,1);
        if (c.value && cells[i+1]) cells[i+1].focus();
      });
      c.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !c.value && cells[i-1]) cells[i-1].focus();
      });
    });
    const f = $('#verifyForm');
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const code = cells.map(c => c.value).join('');
      const ses = window.B30Store.getSession();
      if (!ses) return Swal.fire({ icon:'error', title:'Session expired' });
      try {
        const u = await window.B30Store.confirmEmailVerification(ses.id, code);
        Swal.fire({ icon:'success', timer:1000, showConfirmButton:false, title:'Email verified ✔' });
        showPanel('profile');
        // Pre-fill profile form
        const ses2 = window.B30Store.getSession();
        if (ses2) {
          $('#profileFormAuth [name=binance_id]').value = ses2.binance_id || '';
          $('#profileFormAuth [name=redotpay_id]').value = ses2.redotpay_id || '';
        }
      } catch (err) {
        Swal.fire({ icon:'error', title: err.message || 'Invalid code' });
      }
    });
    $('#resendCode').addEventListener('click', async () => {
      const ses = window.B30Store.getSession();
      if (!ses) return;
      await window.B30Store.startEmailVerification(ses.id);
      Swal.fire({ icon:'info', timer:1200, showConfirmButton:false, title:'New code sent (see console)' });
    });
  }
  function bindProfileAuth () {
    const f = $('#profileFormAuth');
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = new FormData(f);
      const ses = window.B30Store.getSession();
      if (!ses) return;
      try {
        const u = await window.B30Store.updateProfile(ses.id, {
          binance_id: d.get('binance_id'),
          redotpay_id: d.get('redotpay_id')
        });
        Swal.fire({ icon:'success', timer:1000, showConfirmButton:false, title:'Profile saved' });
        notifyAndExit(u);
      } catch (err) {
        Swal.fire({ icon:'error', title: err.message || 'Could not save profile' });
      }
    });
    $('#profileSkipAuth').addEventListener('click', async () => {
      const ses = window.B30Store.getSession();
      if (!ses) return;
      const u = await window.B30Store.getUser(ses.id);
      notifyAndExit(u || ses);
    });
  }
  function bindForgot () {
    $('#forgotLink').addEventListener('click', () => showPanel('forgot'));
    const f = $('#forgotForm');
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      // Demo: no real email transport. Just show a happy toast.
      Swal.fire({
        icon:'success', timer:1500, showConfirmButton:false,
        title:'Reset link sent', text:'Check your inbox. (Demo: nothing actually sent.)'
      });
      showPanel('signin');
    });
  }

  // ---------- boot ----------
  whenReady().then(async () => {
    if (!(await checkApiKey())) return;
    renderSocial($('#socialButtons'));
    renderSocial($('#socialButtonsUp'));
    bindSignIn(); bindSignUp(); bindVerify(); bindProfileAuth(); bindForgot();
    if (window.lucide) lucide.createIcons();
    if (window.B30Logos) window.B30Logos.renderAuto();

    // If already signed in (and not in popup mode), bounce to dashboard.
    if (qs('popup') !== '1') {
      const ses = window.B30Store.getSession();
      if (ses && !qs('force')) {
        // Soft bounce after small delay so the page is visible
        setTimeout(() => {
          if (ses.email_verified === false) showPanel('verify');
          else if (!ses.profile_completed) showPanel('profile');
          // else stay on signin — user can re-enter or click into dashboard
        }, 100);
      }
    }
  });
})();
