/* 2030B P2P Pairing — Client-side store (IndexedDB + localStorage simulation)
 *
 * This is the *client-side* simulation of the PHP+SQLite backend specified
 * in the project brief. It enables the full pairings → credits → levels
 * logic to work end-to-end in the static demo. The PHP/SQLite version in
 * /backend/ mirrors this schema exactly.
 *
 * Stores (all in one IndexedDB database "b30_p2p"):
 *   users        : { id, email, password_hash, full_name, country_code, language,
 *                    level, total_credits, total_pairings,
 *                    redotpay_ops_count, binance_ops_count,
 *                    redotpay_pairings_count, binance_pairings_count,
 *                    created_at, last_login, is_active }
 *   transactions : per-transaction record (buy or sell), keyPath id, indexed by user_id
 *   pairings     : grouped buy+sell, keyPath pairing_id, indexed by user_id
 *   screenshots  : { id, user_id, transaction_id, data_url, name, uploaded_at }
 *   settings     : { key, value }
 *
 * Public API (window.B30Store):
 *   await ready()
 *   await register(payload)            -> user
 *   await signIn(email, password)      -> user
 *   getSession()                       -> { id, email, full_name, level, total_credits } | null
 *   clearSession()
 *   updateSession()                    -> refresh cached session from store
 *   async addPairing({ platform, buy:{...}, sell:{...} })
 *   async listPairings(userId?)
 *   async listTransactions(userId?)
 *   async listUsers()                  -> admin
 *   async setTransactionStatus(txId, status, notes)
 *   async recomputeCounters(userId)
 *   async saveScreenshot(file, txId)
 *   async getScreenshot(id)
 *   admin login/logout
 *   exchangeRates()                    -> { base, rates, updated_at }
 *   formatFiat(amountUSD, currency)
 *
 * Credit/level logic strictly follows config.json (4 levels, redotpay/binance
 * counters, thresholds). When status flips pending→verified, counters update
 * and level is re-evaluated.
 */
(function () {
  const DB_NAME = 'b30_p2p';
  const DB_VER  = 3;
  const SESSION_KEY = 'b30-session';
  const ADMIN_SESSION_KEY = 'b30-admin-session';
  const REF_COOKIE_KEY = 'b30-ref';
  // Default super-admin password (override via config.json -> super_admin_password)
  const DEFAULT_SUPER_ADMIN_PW = 'super2030';

  let dbPromise = null;
  let sessionCache = null;

  function uid(prefix) {
    return (prefix || '') + Math.random().toString(36).slice(2, 9) + Date.now().toString(36).slice(-4);
  }

  // ---------- Tiny bcrypt-ish hash (NOT real bcrypt; SHA-256 + salt). The PHP backend uses real bcrypt. ----------
  async function hashPw(pw, salt) {
    salt = salt || uid('s');
    const enc = new TextEncoder().encode(salt + ':' + pw);
    const buf = await crypto.subtle.digest('SHA-256', enc);
    const hex = Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2,'0')).join('');
    return `sha256$${salt}$${hex}`;
  }
  async function verifyPw(pw, stored) {
    if (!stored) return false;
    const parts = stored.split('$');
    if (parts.length !== 3) return false;
    const re = await hashPw(pw, parts[1]);
    return re === stored;
  }

  // ---------- IndexedDB plumbing ----------
  function openDB() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VER);
      req.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains('users')) {
          const s = db.createObjectStore('users', { keyPath: 'id' });
          s.createIndex('email', 'email', { unique: true });
        }
        if (!db.objectStoreNames.contains('transactions')) {
          const s = db.createObjectStore('transactions', { keyPath: 'id' });
          s.createIndex('user_id', 'user_id');
          s.createIndex('pairing_id', 'pairing_id');
        }
        if (!db.objectStoreNames.contains('pairings')) {
          const s = db.createObjectStore('pairings', { keyPath: 'pairing_id' });
          s.createIndex('user_id', 'user_id');
        }
        if (!db.objectStoreNames.contains('screenshots')) {
          const s = db.createObjectStore('screenshots', { keyPath: 'id' });
          s.createIndex('user_id', 'user_id');
          s.createIndex('transaction_id', 'transaction_id');
        }
        if (!db.objectStoreNames.contains('settings')) {
          db.createObjectStore('settings', { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains('api_keys')) {
          const s = db.createObjectStore('api_keys', { keyPath: 'id' });
          s.createIndex('user_id', 'user_id');
          s.createIndex('key', 'key', { unique: true });
        }
        if (!db.objectStoreNames.contains('auth_events')) {
          const s = db.createObjectStore('auth_events', { keyPath: 'id' });
          s.createIndex('user_id', 'user_id');
          s.createIndex('created_at', 'created_at');
        }
        // v3 — admin tree, affiliate, referrals
        if (!db.objectStoreNames.contains('admins')) {
          const s = db.createObjectStore('admins', { keyPath: 'id' });
          s.createIndex('email', 'email', { unique: true });
          s.createIndex('role', 'role');
          s.createIndex('parent_id', 'parent_id');
        }
        if (!db.objectStoreNames.contains('referrals')) {
          const s = db.createObjectStore('referrals', { keyPath: 'id' });
          s.createIndex('referrer_id', 'referrer_id');
          s.createIndex('referee_id', 'referee_id', { unique: true });
          s.createIndex('created_at', 'created_at');
        }
        if (!db.objectStoreNames.contains('affiliate_rewards')) {
          const s = db.createObjectStore('affiliate_rewards', { keyPath: 'id' });
          s.createIndex('referrer_id', 'referrer_id');
          s.createIndex('referee_id', 'referee_id');
          s.createIndex('created_at', 'created_at');
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    });
    return dbPromise;
  }

  async function tx(stores, mode, fn) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction(stores, mode);
      const out = fn(t);
      t.oncomplete = () => resolve(out);
      t.onerror    = () => reject(t.error);
      t.onabort    = () => reject(t.error);
    });
  }

  function reqProm(req) {
    return new Promise((resolve, reject) => {
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    });
  }

  // ---------- Session ----------
  function getSession() {
    if (sessionCache) return sessionCache;
    try {
      const raw = localStorage.getItem(SESSION_KEY);
      if (!raw) return null;
      sessionCache = JSON.parse(raw);
      return sessionCache;
    } catch (e) { return null; }
  }
  function setSession(user) {
    if (!user) return clearSession();
    const lite = {
      id: user.id, email: user.email, full_name: user.full_name,
      country_code: user.country_code, language: user.language,
      level: user.level, total_credits: user.total_credits,
      total_pairings: user.total_pairings,
      redotpay_ops_count: user.redotpay_ops_count, binance_ops_count: user.binance_ops_count,
      redotpay_pairings_count: user.redotpay_pairings_count,
      binance_pairings_count: user.binance_pairings_count
    };
    sessionCache = lite;
    localStorage.setItem(SESSION_KEY, JSON.stringify(lite));
  }
  function clearSession() {
    sessionCache = null;
    localStorage.removeItem(SESSION_KEY);
  }

  async function updateSession() {
    const cur = getSession(); if (!cur) return null;
    const user = await getUser(cur.id);
    if (user) setSession(user);
    return sessionCache;
  }

  // ---------- Users ----------
  async function findUserByEmail(email) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('users', 'readonly');
      const idx = t.objectStore('users').index('email');
      const req = idx.get(email.toLowerCase());
      req.onsuccess = () => resolve(req.result || null);
      req.onerror   = () => reject(req.error);
    });
  }
  async function getUser(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('users', 'readonly');
      const req = t.objectStore('users').get(id);
      req.onsuccess = () => resolve(req.result || null);
      req.onerror   = () => reject(req.error);
    });
  }
  async function listUsers() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('users', 'readonly');
      const req = t.objectStore('users').getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror   = () => reject(req.error);
    });
  }
  async function putUser(user) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('users', 'readwrite');
      const req = t.objectStore('users').put(user);
      req.onsuccess = () => resolve(user);
      req.onerror   = () => reject(req.error);
    });
  }

  function generateReferralCode(seed) {
    const s = (seed || '').replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 5);
    const r = Math.random().toString(36).slice(2, 6).toUpperCase();
    return ('B30' + s + r).slice(0, 12);
  }

  // ---------- Auth-API bridge (default Keys) ----------
  // When the page was served by the PHP backend, window.B30_SERVER is injected
  // with { csrf, api, ... }. We also fetch the public config (which includes
  // auth_api.default_key) so register/signin can call the real backend with
  // an X-API-Key header. If the bridge is unavailable, we fall back silently
  // to the pure-IndexedDB demo path (so the static demo still works).
  let __authApi = null; // { endpoint, key, csrf }
  async function ensureAuthApi() {
    if (__authApi !== null) return __authApi;
    const srv = (typeof window !== 'undefined') ? window.B30_SERVER : null;
    if (!srv || !srv.api) { __authApi = false; return false; }
    try {
      const res = await fetch(srv.api + '?op=config.public', { credentials: 'same-origin' });
      const cfg = await res.json();
      const auth = (cfg && cfg.auth_api) || null;
      if (!auth || !auth.default_key) { __authApi = false; return false; }
      __authApi = { endpoint: srv.api, key: auth.default_key, csrf: srv.csrf || '' };
      return __authApi;
    } catch (e) { __authApi = false; return false; }
  }
  async function authApiCall(op, payload) {
    const a = await ensureAuthApi();
    if (!a) return null;
    const res = await fetch(a.endpoint + '?op=' + encodeURIComponent(op), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': a.key,
        'X-CSRF':    a.csrf,
      },
      body: JSON.stringify(Object.assign({ csrf: a.csrf }, payload || {})),
    });
    let data = null;
    try { data = await res.json(); } catch (e) {}
    if (!res.ok) {
      const err = new Error((data && data.error) || ('http_' + res.status));
      err.api = data; err.status = res.status;
      throw err;
    }
    return data;
  }

  async function register({ email, password, full_name, country_code, language, phone, ref_code }) {
    if (!email || !password) throw new Error('Email and password required');
    email = email.toLowerCase().trim();
    // Bridge: try the backend Auth API (default key) first. If it succeeds we
    // still mirror the user locally so the SPA dashboard works offline.
    try {
      const apiRes = await authApiCall('register', {
        email, password, full_name, country_code, language, phone, ref_code,
      });
      if (apiRes && apiRes.ok) {
        // success path — fall through to local mirror below
      }
    } catch (e) {
      // If the backend says exists/csrf/etc., still try local — local is the source of truth in demo mode
      if (e && e.status === 401 && e.api && e.api.error === 'api_key_invalid') {
        throw new Error(window.B30I18n ? window.B30I18n.t('auth.api.invalid_key') : 'Default API key is missing or invalid.');
      }
    }
    const existing = await findUserByEmail(email);
    if (existing) throw new Error('Email already registered');
    const hash = await hashPw(password);
    // Resolve referrer if a referral code was provided (URL/cookie/explicit)
    let referrer = null;
    const code = ref_code || (typeof getReferralCookie === 'function' ? getReferralCookie() : null);
    if (code) {
      const all = await listUsers();
      referrer = all.find(u => u.referral_code === code) || null;
    }
    const myRef = generateReferralCode(full_name || email);
    const user = {
      id: uid('u_'),
      email, password_hash: hash,
      full_name: (full_name || '').trim() || email.split('@')[0],
      country_code: country_code || 'TN',
      language: language || (window.B30I18n ? window.B30I18n.current() : 'en'),
      phone: phone || '',
      level: 0, total_credits: 0, total_pairings: 0, total_operations: 0,
      redotpay_ops_count: 0, binance_ops_count: 0,
      redotpay_pairings_count: 0, binance_pairings_count: 0,
      // Profile completion (Binance & RedotPay IDs)
      binance_id: '',
      redotpay_id: '',
      profile_completed: false,
      // Verification status (email + optional social)
      email_verified: false,
      email_verification_code: null,
      auth_provider: 'password',
      // Affiliate program
      referral_code: myRef,
      referred_by: referrer ? referrer.id : null,
      referrals_count: 0,
      referral_credits_earned: 0,
      created_at: Date.now(), last_login: Date.now(),
      is_active: true, role: 'user'
    };
    await putUser(user);
    // Record referral relationship
    if (referrer) {
      await tx(['referrals'], 'readwrite', t => t.objectStore('referrals').put({
        id: uid('ref_'),
        referrer_id: referrer.id,
        referee_id: user.id,
        ref_code: code,
        created_at: Date.now()
      }));
      // Bump referrer's count
      referrer.referrals_count = (referrer.referrals_count || 0) + 1;
      await putUser(referrer);
    }
    setSession(user);
    return user;
  }

  // ---------- Referral cookie helpers (URL ?ref= captures into a small store) ----------
  function setReferralCookie(code) {
    try { localStorage.setItem(REF_COOKIE_KEY, code); } catch (e) {}
  }
  function getReferralCookie() {
    try { return localStorage.getItem(REF_COOKIE_KEY) || null; } catch (e) { return null; }
  }
  function clearReferralCookie() {
    try { localStorage.removeItem(REF_COOKIE_KEY); } catch (e) {}
  }

  async function signIn(email, password) {
    email = (email || '').toLowerCase().trim();
    // Bridge: hit the backend Auth API with the default key so the server-side
    // PHP session is established. Local IndexedDB stays the SPA's source of truth.
    try {
      await authApiCall('signin', { email, password });
    } catch (e) {
      if (e && e.status === 401 && e.api && e.api.error === 'api_key_invalid') {
        throw new Error(window.B30I18n ? window.B30I18n.t('auth.api.invalid_key') : 'Default API key is missing or invalid.');
      }
      // bad_credentials / disabled / network: still try local
    }
    const user = await findUserByEmail(email);
    if (!user) throw new Error('User not found');
    const ok = await verifyPw(password, user.password_hash);
    if (!ok) throw new Error('Wrong password');
    user.last_login = Date.now();
    await putUser(user);
    setSession(user);
    return user;
  }

  // Expose the helpers (so admin pages / tests can call the Auth API directly)
  function getAuthApi() { return __authApi; }

  // ---------- Admin tree (super-admin + sub-admins) ----------
  // Permissions:
  //   ['*'] = all permissions (super-admin)
  //   'verify_transactions' | 'manage_users' | 'manage_keys' | 'manage_ecosystem'
  //   'manage_affiliate' | 'manage_admins' | 'edit_config'
  const ALL_ADMIN_PERMS = [
    'verify_transactions',
    'manage_users',
    'manage_keys',
    'manage_ecosystem',
    'manage_affiliate',
    'manage_admins',
    'edit_config',
    'view_dashboard'
  ];

  function getAdminSession() {
    try { return JSON.parse(localStorage.getItem(ADMIN_SESSION_KEY) || 'null'); } catch (e) { return null; }
  }
  async function ensureSuperAdmin() {
    // Seed the super-admin from config.json (or default) on first run.
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const email = (cfg.super_admin_email || 'super@2030b.com').toLowerCase();
    const pw = cfg.super_admin_password || DEFAULT_SUPER_ADMIN_PW;
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('admins', 'readwrite');
      const idx = t.objectStore('admins').index('email');
      const req = idx.get(email);
      req.onsuccess = async () => {
        if (req.result) { resolve(req.result); return; }
        // Create initial super-admin
        const hash = await hashPw(pw);
        const rec = {
          id: 'adm_super_' + Math.random().toString(36).slice(2,8),
          email, password_hash: hash,
          full_name: 'Super Admin',
          role: 'super_admin',
          permissions: ['*'],
          parent_id: null,
          enabled: true,
          created_at: Date.now(),
          last_login: null
        };
        const t2 = db.transaction('admins', 'readwrite');
        t2.objectStore('admins').put(rec);
        t2.oncomplete = () => resolve(rec);
        t2.onerror = () => reject(t2.error);
      };
      req.onerror = () => reject(req.error);
    });
  }
  async function adminSignIn(emailOrPw, password) {
    // Backwards compat: if called with a single arg, treat it as the legacy password.
    await ensureSuperAdmin();
    if (password === undefined) {
      // Legacy single-arg form ("admin2030") — login the super-admin if matched.
      const cfg = (window.B30Config && window.B30Config.get()) || {};
      const legacyPw = cfg.admin_password || 'admin2030';
      const superPw = cfg.super_admin_password || DEFAULT_SUPER_ADMIN_PW;
      if (emailOrPw === legacyPw || emailOrPw === superPw) {
        const supEmail = (cfg.super_admin_email || 'super@2030b.com').toLowerCase();
        const adm = await findAdminByEmail(supEmail);
        if (adm) {
          adm.last_login = Date.now();
          await tx(['admins'], 'readwrite', t => t.objectStore('admins').put(adm));
          localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify({
            at: Date.now(), id: adm.id, email: adm.email, role: adm.role, permissions: adm.permissions
          }));
          return adm;
        }
      }
      return false;
    }
    // New 2-arg flow
    const adm = await findAdminByEmail(String(emailOrPw || '').toLowerCase());
    if (!adm || !adm.enabled) return false;
    const ok = await verifyPw(password, adm.password_hash);
    if (!ok) return false;
    adm.last_login = Date.now();
    await tx(['admins'], 'readwrite', t => t.objectStore('admins').put(adm));
    localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify({
      at: Date.now(), id: adm.id, email: adm.email, role: adm.role, permissions: adm.permissions
    }));
    return adm;
  }
  function adminSignOut() { localStorage.removeItem(ADMIN_SESSION_KEY); }

  async function findAdminByEmail(email) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('admins', 'readonly');
      const idx = t.objectStore('admins').index('email');
      const req = idx.get((email || '').toLowerCase());
      req.onsuccess = () => resolve(req.result || null);
      req.onerror = () => reject(req.error);
    });
  }
  async function listAdmins() {
    await ensureSuperAdmin();
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('admins', 'readonly');
      const req = t.objectStore('admins').getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
  }
  function adminHasPermission(admin, perm) {
    if (!admin) return false;
    const perms = admin.permissions || [];
    return perms.includes('*') || perms.includes(perm);
  }
  function requireAdminPerm(perm) {
    const sess = getAdminSession();
    if (!sess) throw new Error('Admin sign-in required');
    if (!adminHasPermission(sess, perm)) {
      throw new Error('Permission denied: ' + perm);
    }
    return sess;
  }
  async function createSubAdmin({ email, password, full_name, permissions }) {
    const sess = requireAdminPerm('manage_admins');
    if (!email || !password) throw new Error('Email and password required');
    email = email.toLowerCase().trim();
    const exists = await findAdminByEmail(email);
    if (exists) throw new Error('Admin email already exists');
    const hash = await hashPw(password);
    const allowed = (permissions || []).filter(p => ALL_ADMIN_PERMS.includes(p));
    const rec = {
      id: 'adm_' + Math.random().toString(36).slice(2,10),
      email, password_hash: hash,
      full_name: full_name || email.split('@')[0],
      role: 'admin',
      permissions: allowed.length ? allowed : ['verify_transactions', 'view_dashboard'],
      parent_id: sess.id,
      enabled: true,
      created_at: Date.now(),
      last_login: null
    };
    await tx(['admins'], 'readwrite', t => t.objectStore('admins').put(rec));
    return rec;
  }
  async function updateSubAdmin(id, patch) {
    requireAdminPerm('manage_admins');
    const db = await openDB();
    const t = db.transaction('admins', 'readwrite');
    const store = t.objectStore('admins');
    const cur = await reqProm(store.get(id));
    if (!cur) throw new Error('Admin not found');
    if (cur.role === 'super_admin' && (patch.role !== undefined || patch.enabled === false)) {
      throw new Error('Cannot modify the super-admin role/state');
    }
    const next = Object.assign({}, cur);
    if (patch.full_name !== undefined) next.full_name = patch.full_name;
    if (patch.enabled !== undefined)   next.enabled = !!patch.enabled;
    if (patch.permissions !== undefined) {
      next.permissions = (patch.permissions || []).filter(p => ALL_ADMIN_PERMS.includes(p));
    }
    if (patch.password) next.password_hash = await hashPw(patch.password);
    await reqProm(store.put(next));
    return next;
  }
  async function deleteSubAdmin(id) {
    requireAdminPerm('manage_admins');
    const db = await openDB();
    const t = db.transaction('admins', 'readwrite');
    const store = t.objectStore('admins');
    const cur = await reqProm(store.get(id));
    if (!cur) return false;
    if (cur.role === 'super_admin') throw new Error('Cannot delete the super-admin');
    await reqProm(store.delete(id));
    return true;
  }

  // ---------- Email verification ----------
  async function startEmailVerification(userId) {
    const u = await getUser(userId);
    if (!u) throw new Error('User not found');
    const code = String(Math.floor(100000 + Math.random() * 900000));
    u.email_verification_code = code;
    u.email_verified = false;
    await putUser(u);
    // Demo: surface the code via console (real backend emails it)
    console.info('[B30] Verification code for ' + u.email + ':', code);
    return code;
  }
  async function confirmEmailVerification(userId, code) {
    const u = await getUser(userId);
    if (!u) throw new Error('User not found');
    if (!u.email_verification_code || String(u.email_verification_code) !== String(code)) {
      throw new Error('Invalid verification code');
    }
    u.email_verified = true;
    u.email_verification_code = null;
    await putUser(u);
    setSession(u);
    return u;
  }

  // ---------- Profile completion (Binance + RedotPay IDs) ----------
  async function updateProfile(userId, payload) {
    const u = await getUser(userId);
    if (!u) throw new Error('User not found');
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const pcfg = (cfg.profile || {});
    if ('binance_id' in payload) {
      const v = String(payload.binance_id || '').trim();
      if (v) {
        const re = new RegExp(pcfg.binance_id_regex || '^[0-9]{6,20}$');
        if (!re.test(v)) throw new Error('Invalid Binance ID format');
      }
      u.binance_id = v;
    }
    if ('redotpay_id' in payload) {
      const v = String(payload.redotpay_id || '').trim();
      if (v) {
        const re = new RegExp(pcfg.redotpay_id_regex || '^[A-Za-z0-9_\\-]{4,32}$');
        if (!re.test(v)) throw new Error('Invalid RedotPay ID format');
      }
      u.redotpay_id = v;
    }
    if ('full_name' in payload && payload.full_name) u.full_name = String(payload.full_name).trim();
    if ('phone' in payload) u.phone = String(payload.phone || '').trim();
    u.profile_completed = !!(u.binance_id || u.redotpay_id);
    await putUser(u);
    setSession(u);
    return u;
  }

  // ---------- API Keys (auth-as-a-service) ----------
  function randomKey(prefix) {
    const bytes = new Uint8Array(24);
    crypto.getRandomValues(bytes);
    return (prefix || 'b30_') + Array.from(bytes).map(b => b.toString(16).padStart(2,'0')).join('');
  }
  async function createApiKey({ userId, label, scopes, origins }) {
    const rec = {
      id: uid('k_'),
      user_id: userId || null,
      label: label || 'Untitled key',
      key: randomKey('b30k_'),
      scopes: Array.isArray(scopes) ? scopes : ['auth:read', 'auth:popup'],
      origins: Array.isArray(origins) ? origins : ['*'],
      created_at: Date.now(),
      last_used: null,
      enabled: true
    };
    await tx(['api_keys'], 'readwrite', t => t.objectStore('api_keys').put(rec));
    return rec;
  }
  async function listApiKeys(userId) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('api_keys', 'readonly');
      const store = t.objectStore('api_keys');
      let req;
      if (userId) req = store.index('user_id').getAll(userId);
      else req = store.getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror   = () => reject(req.error);
    });
  }
  async function updateApiKey(id, patch) {
    const db = await openDB();
    const t = db.transaction('api_keys', 'readwrite');
    const store = t.objectStore('api_keys');
    const cur = await reqProm(store.get(id));
    if (!cur) return null;
    Object.assign(cur, patch || {});
    await reqProm(store.put(cur));
    return cur;
  }
  async function deleteApiKey(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('api_keys', 'readwrite');
      const req = t.objectStore('api_keys').delete(id);
      req.onsuccess = () => resolve(true);
      req.onerror   = () => reject(req.error);
    });
  }
  async function findApiKey(key) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('api_keys', 'readonly');
      const idx = t.objectStore('api_keys').index('key');
      const req = idx.get(key);
      req.onsuccess = () => resolve(req.result || null);
      req.onerror   = () => reject(req.error);
    });
  }

  // ---------- Single-leg P2P operation (Level 1) ----------
  // Creates ONE transaction (buy OR sell) — no pairing, no companion leg.
  // Used to unlock Level 1 per config.json `levels.1.required_operations`.
  async function createOperation({ userId, platform, type, amount_usdt, amount_fiat, fiat_currency, fee_percent, transaction_date, screenshot }) {
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const fees = cfg.fees || { buy_fee_percent: 3, sell_fee_percent: 0 };
    const fp = (fee_percent != null) ? +fee_percent : (type === 'buy' ? fees.buy_fee_percent : fees.sell_fee_percent);
    const sign = (type === 'buy') ? 1 : -1;
    const total = +amount_fiat * (1 + sign * (fp / 100));
    const opTx = {
      id: uid('t_'), user_id: userId, platform, type,
      amount_usdt: +amount_usdt,
      amount_fiat: +amount_fiat,
      fiat_currency: fiat_currency || cfg.default_fiat_currency || 'TND',
      fee_percent: fp,
      fee_amount: +(+amount_fiat * fp / 100).toFixed(4),
      total_cost: +total.toFixed(4),
      transaction_date: transaction_date || Date.now(),
      status: 'pending', verification_notes: '',
      paired_with: null, pairing_id: null,
      screenshot_id: null,
      single_op: true,
      created_at: Date.now()
    };
    if (screenshot) {
      const id = await saveScreenshot(screenshot, userId, opTx.id);
      opTx.screenshot_id = id;
    }
    await tx(['transactions'], 'readwrite', t => t.objectStore('transactions').put(opTx));
    return opTx;
  }

  // ---------- Transactions / Pairings ----------
  async function createPairing({ userId, platform, buy, sell, screenshots }) {
    // platform: 'binance' | 'redotpay'
    // buy/sell: { amount_usdt, amount_fiat, fiat_currency, fee_percent, fee_amount, total_cost, transaction_date, screenshot_file? }
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const buyFee  = (buy.fee_percent  != null) ? buy.fee_percent  : (cfg.fees ? cfg.fees.buy_fee_percent  : 3);
    const sellFee = (sell.fee_percent != null) ? sell.fee_percent : (cfg.fees ? cfg.fees.sell_fee_percent : 0);

    const pairingId = uid('p_');
    const now = Date.now();
    const buyTx = {
      id: uid('t_'), user_id: userId, platform, type: 'buy',
      amount_usdt: +buy.amount_usdt,
      amount_fiat: +buy.amount_fiat,
      fiat_currency: buy.fiat_currency || cfg.default_fiat_currency || 'TND',
      fee_percent: +buyFee,
      fee_amount: +(buy.fee_amount != null ? buy.fee_amount : (buy.amount_fiat * buyFee / 100)),
      total_cost: +(buy.total_cost != null ? buy.total_cost : (buy.amount_fiat * (1 + buyFee / 100))),
      transaction_date: buy.transaction_date || now,
      status: 'pending', verification_notes: '',
      paired_with: null, pairing_id: pairingId,
      screenshot_id: null, created_at: now
    };
    const sellTx = {
      id: uid('t_'), user_id: userId, platform, type: 'sell',
      amount_usdt: +sell.amount_usdt,
      amount_fiat: +sell.amount_fiat,
      fiat_currency: sell.fiat_currency || cfg.default_fiat_currency || 'TND',
      fee_percent: +sellFee,
      fee_amount: +(sell.fee_amount != null ? sell.fee_amount : (sell.amount_fiat * sellFee / 100)),
      total_cost: +(sell.total_cost != null ? sell.total_cost : (sell.amount_fiat * (1 - sellFee / 100))),
      transaction_date: sell.transaction_date || now,
      status: 'pending', verification_notes: '',
      paired_with: null, pairing_id: pairingId,
      screenshot_id: null, created_at: now
    };
    buyTx.paired_with = sellTx.id;
    sellTx.paired_with = buyTx.id;

    // Save screenshots
    if (screenshots && screenshots.buy) {
      const id = await saveScreenshot(screenshots.buy, userId, buyTx.id);
      buyTx.screenshot_id = id;
    }
    if (screenshots && screenshots.sell) {
      const id = await saveScreenshot(screenshots.sell, userId, sellTx.id);
      sellTx.screenshot_id = id;
    }

    const profitFiat = sellTx.total_cost - buyTx.total_cost;
    const profitUsdt = sellTx.amount_usdt > 0 ? (profitFiat / (sellTx.amount_fiat / sellTx.amount_usdt)) : 0;
    const pairing = {
      pairing_id: pairingId, user_id: userId, platform,
      buy_tx_id: buyTx.id, sell_tx_id: sellTx.id,
      profit_fiat: +profitFiat.toFixed(4),
      profit_usdt: +profitUsdt.toFixed(4),
      fiat_currency: buyTx.fiat_currency,
      credits_earned: 0,   // granted when verified
      status: 'pending',
      pairing_date: now
    };

    await tx(['transactions', 'pairings'], 'readwrite', t => {
      t.objectStore('transactions').put(buyTx);
      t.objectStore('transactions').put(sellTx);
      t.objectStore('pairings').put(pairing);
    });

    return { pairing, buyTx, sellTx };
  }

  async function listTransactions(userId) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('transactions', 'readonly');
      const store = t.objectStore('transactions');
      let req;
      if (userId) {
        const idx = store.index('user_id');
        req = idx.getAll(userId);
      } else {
        req = store.getAll();
      }
      req.onsuccess = () => resolve(req.result || []);
      req.onerror   = () => reject(req.error);
    });
  }

  async function listPairings(userId) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('pairings', 'readonly');
      const store = t.objectStore('pairings');
      let req;
      if (userId) {
        const idx = store.index('user_id');
        req = idx.getAll(userId);
      } else {
        req = store.getAll();
      }
      req.onsuccess = () => resolve(req.result || []);
      req.onerror   = () => reject(req.error);
    });
  }

  async function setTransactionStatus(txId, status, notes) {
    const db = await openDB();
    const t  = db.transaction('transactions', 'readwrite');
    const store = t.objectStore('transactions');
    const cur = await reqProm(store.get(txId));
    if (!cur) return null;
    cur.status = status;
    cur.verification_notes = notes || '';
    await reqProm(store.put(cur));
    await new Promise((res) => { t.oncomplete = res; });

    // If part of a pairing, finalize that pairing. Otherwise recompute single-op counters.
    if (cur.pairing_id) await maybeFinalizePairing(cur.pairing_id);
    else await recomputeUserCounters(cur.user_id);
    return cur;
  }

  // Recompute level/credits/counters for a user without requiring a pairing.
  async function recomputeUserCounters(userId) {
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const creditsPer = cfg.credits_per_pairing || 1;
    const creditsSingle = cfg.credits_per_single_op || 0.5;
    const db = await openDB();
    const t = db.transaction(['transactions', 'pairings', 'users'], 'readwrite');
    const txs = await reqProm(t.objectStore('transactions').index('user_id').getAll(userId));
    const prs = await reqProm(t.objectStore('pairings').index('user_id').getAll(userId));
    const userStore = t.objectStore('users');
    const u = await reqProm(userStore.get(userId));
    if (!u) return null;

    let rOps = 0, bOps = 0, rPairs = 0, bPairs = 0, totalPairs = 0;
    let totalOps = 0; let credits = 0;
    txs.forEach(x => {
      if (x.status !== 'verified') return;
      totalOps++;
      if (x.platform === 'redotpay') rOps++;
      else if (x.platform === 'binance') bOps++;
    });
    prs.forEach(p => {
      const pt = txs.filter(x => x.pairing_id === p.pairing_id);
      const ok = pt.length === 2 && pt.every(x => x.status === 'verified');
      if (ok) {
        totalPairs++;
        if (p.platform === 'redotpay') rPairs++;
        else if (p.platform === 'binance') bPairs++;
        credits += creditsPer;
      }
    });
    // Single-leg ops (not part of a pairing) award fractional credits
    const singleVerified = txs.filter(x => x.status === 'verified' && !x.pairing_id).length;
    credits += singleVerified * creditsSingle;

    const prevCredits = u.total_credits || 0;
    u.redotpay_ops_count = rOps;
    u.binance_ops_count  = bOps;
    u.redotpay_pairings_count = rPairs;
    u.binance_pairings_count  = bPairs;
    u.total_pairings   = totalPairs;
    u.total_operations = totalOps;
    u.total_credits    = +credits.toFixed(2);
    u.level = computeLevel(u, cfg);

    await reqProm(userStore.put(u));
    await new Promise(r => { t.oncomplete = r; });
    const ses = getSession();
    if (ses && ses.id === u.id) setSession(u);

    // Affiliate: reward referrer on any newly-earned credits.
    try {
      const delta = (u.total_credits || 0) - prevCredits;
      if (delta > 0) await maybeAwardAffiliate(u.id, null, 'op', delta);
    } catch (e) {}
    return u;
  }

  async function maybeFinalizePairing(pairingId) {
    if (!pairingId) return;
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const creditsPer = cfg.credits_per_pairing || 1;

    const db = await openDB();
    const t = db.transaction(['transactions', 'pairings', 'users'], 'readwrite');
    const txs = await reqProm(t.objectStore('transactions').index('pairing_id').getAll(pairingId));
    const pStore = t.objectStore('pairings');
    const pair = await reqProm(pStore.get(pairingId));
    if (!pair) { return; }

    const buy  = txs.find(x => x.type === 'buy');
    const sell = txs.find(x => x.type === 'sell');
    const anyRejected = txs.some(x => x.status === 'rejected');
    const allVerified = txs.length === 2 && txs.every(x => x.status === 'verified');

    const userStore = t.objectStore('users');
    const user = await reqProm(userStore.get(pair.user_id));
    if (!user) return;

    // Ensure counters reflect *current* status across all of user's transactions/pairings.
    // We recompute from scratch for correctness.
    const allTxs   = await reqProm(t.objectStore('transactions').index('user_id').getAll(user.id));
    const allPairs = await reqProm(pStore.index('user_id').getAll(user.id));

    let rOps = 0, bOps = 0, rPairs = 0, bPairs = 0, totalPairs = 0, credits = 0;
    allTxs.forEach(x => {
      if (x.status !== 'verified') return;
      if (x.platform === 'redotpay') rOps++;
      else if (x.platform === 'binance') bOps++;
    });
    allPairs.forEach(p => {
      const ptxs = allTxs.filter(x => x.pairing_id === p.pairing_id);
      const ok = ptxs.length === 2 && ptxs.every(x => x.status === 'verified');
      if (ok) {
        totalPairs++;
        if (p.platform === 'redotpay') rPairs++;
        else if (p.platform === 'binance') bPairs++;
        credits += creditsPer;
      }
    });

    // Add single-leg op credits + count
    const creditsSingle = cfg.credits_per_single_op || 0.5;
    const singleVerified = allTxs.filter(x => x.status === 'verified' && !x.pairing_id).length;
    credits += singleVerified * creditsSingle;
    const totalOps = allTxs.filter(x => x.status === 'verified').length;

    user.redotpay_ops_count = rOps;
    user.binance_ops_count = bOps;
    user.redotpay_pairings_count = rPairs;
    user.binance_pairings_count = bPairs;
    user.total_pairings = totalPairs;
    user.total_operations = totalOps;
    user.total_credits  = +credits.toFixed(2);
    user.level = computeLevel(user, cfg);

    // Update this pairing's status/credits
    pair.status = anyRejected ? 'rejected' : (allVerified ? 'verified' : 'pending');
    pair.credits_earned = allVerified ? creditsPer : 0;

    await reqProm(pStore.put(pair));
    await reqProm(userStore.put(user));

    await new Promise(res => { t.oncomplete = res; });

    // If current session belongs to this user, refresh
    const ses = getSession();
    if (ses && ses.id === user.id) setSession(user);

    // Affiliate kick-back if this pairing just turned verified
    if (allVerified) {
      try { await maybeAwardAffiliate(user.id, pair.pairing_id, 'pairing', creditsPer); } catch (e) {}
    }
  }

  // ---------- Affiliate program ----------
  // Awards a percentage of newly-earned credits to the referrer on each
  // verified pairing or single-op. Configured via config.affiliate_program.
  async function maybeAwardAffiliate(refereeId, sourceId, sourceType, creditsEarned) {
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const ap = (cfg.affiliate_program || {});
    if (!ap.enabled) return null;
    const referee = await getUser(refereeId);
    if (!referee || !referee.referred_by) return null;
    const pct = +ap.percent || 10; // default 10% kick-back
    const reward = +(creditsEarned * (pct / 100)).toFixed(4);
    if (reward <= 0) return null;
    const max = +ap.max_credits_per_referee_per_year || 0;
    if (max > 0) {
      // Sum existing rewards in last 365 days
      const db = await openDB();
      const all = await new Promise((res, rej) => {
        const t = db.transaction('affiliate_rewards', 'readonly');
        const idx = t.objectStore('affiliate_rewards').index('referee_id');
        const req = idx.getAll(refereeId);
        req.onsuccess = () => res(req.result || []);
        req.onerror = () => rej(req.error);
      });
      const yearAgo = Date.now() - 365 * 86400 * 1000;
      const tally = all.filter(r => r.created_at > yearAgo).reduce((a, b) => a + (b.credits || 0), 0);
      if (tally + reward > max) return null;
    }
    const rec = {
      id: uid('arw_'),
      referrer_id: referee.referred_by,
      referee_id: refereeId,
      source_id: sourceId,
      source_type: sourceType,    // 'pairing' | 'op'
      credits: reward,
      created_at: Date.now()
    };
    await tx(['affiliate_rewards'], 'readwrite', t => t.objectStore('affiliate_rewards').put(rec));
    // Credit the referrer
    const referrer = await getUser(referee.referred_by);
    if (referrer) {
      referrer.total_credits = +((referrer.total_credits || 0) + reward).toFixed(2);
      referrer.referral_credits_earned = +((referrer.referral_credits_earned || 0) + reward).toFixed(2);
      referrer.level = computeLevel(referrer, cfg);
      await putUser(referrer);
      // Refresh referrer's session if active
      const ses = getSession();
      if (ses && ses.id === referrer.id) setSession(referrer);
    }
    return rec;
  }
  async function listAffiliateRewards({ referrerId, refereeId } = {}) {
    const db = await openDB();
    return new Promise((res, rej) => {
      const t = db.transaction('affiliate_rewards', 'readonly');
      const store = t.objectStore('affiliate_rewards');
      let req;
      if (referrerId) req = store.index('referrer_id').getAll(referrerId);
      else if (refereeId) req = store.index('referee_id').getAll(refereeId);
      else req = store.getAll();
      req.onsuccess = () => res(req.result || []);
      req.onerror = () => rej(req.error);
    });
  }
  async function listReferrals(referrerId) {
    const db = await openDB();
    return new Promise((res, rej) => {
      const t = db.transaction('referrals', 'readonly');
      const store = t.objectStore('referrals');
      const req = referrerId ? store.index('referrer_id').getAll(referrerId) : store.getAll();
      req.onsuccess = () => res(req.result || []);
      req.onerror = () => rej(req.error);
    });
  }

  /**
   * Adjust a user's credits by a (positive or negative) delta. Used for penalties.
   * Caps at 0 — total_credits never goes below 0.
   */
  async function adjustUserCredits(userId, delta, reason) {
    const u = await getUser(userId);
    if (!u) throw new Error('User not found');
    const before = +u.total_credits || 0;
    const after = Math.max(0, +(before + (+delta || 0)).toFixed(2));
    u.total_credits = after;
    u.credits_adjustments = (u.credits_adjustments || 0) + 1;
    u.last_credit_reason = String(reason || '').slice(0, 200);
    u.level = computeLevel(u, (window.B30Config && window.B30Config.get()) || {});
    await putUser(u);
    const ses = getSession();
    if (ses && ses.id === u.id) setSession(u);
    return { before, after };
  }

  function computeLevel(u, cfg) {
    const L = (cfg && cfg.levels) || {};
    const L1 = L['1'] || { required_operations: 1, required_pairings: 0 };
    const L2 = L['2'] || { required_redotpay_operations: 3, required_redotpay_pairings: 1 };
    const L3 = L['3'] || { required_binance_operations: 20, required_binance_pairings: 1 };
    const L4 = L['4'] || { required_pairings: 100 };

    let level = 0;
    // Level 1 — single verified op OR pairing (NOT a full pairing required)
    const reqOps   = L1.required_operations != null ? L1.required_operations : 1;
    const reqPairs = L1.required_pairings   != null ? L1.required_pairings   : 0;
    if ((u.total_operations || 0) >= reqOps && (u.total_pairings || 0) >= reqPairs) level = 1;
    if (u.redotpay_ops_count >= (L2.required_redotpay_operations || 3) &&
        u.redotpay_pairings_count >= (L2.required_redotpay_pairings || 1) &&
        level >= 1) level = 2;
    if (u.binance_ops_count >= (L3.required_binance_operations || 20) &&
        u.binance_pairings_count >= (L3.required_binance_pairings || 1) &&
        level >= 2) level = 3;
    if ((u.total_pairings || 0) >= (L4.required_pairings || 100) && level >= 3) level = 4;
    return level;
  }

  async function recomputeCounters(userId) {
    // Just trigger finalize on each pairing (no-op for non-finalized).
    const pairs = await listPairings(userId);
    for (const p of pairs) await maybeFinalizePairing(p.pairing_id);
  }

  // ---------- Screenshots ----------
  function readFileAsDataURL(file) {
    return new Promise((resolve, reject) => {
      const fr = new FileReader();
      fr.onload = () => resolve(fr.result);
      fr.onerror = () => reject(fr.error);
      fr.readAsDataURL(file);
    });
  }
  async function saveScreenshot(file, userId, txId) {
    if (!file) return null;
    if (file.size > 5 * 1024 * 1024) throw new Error('Screenshot exceeds 5MB');
    const dataUrl = await readFileAsDataURL(file);
    const rec = {
      id: uid('s_'), user_id: userId, transaction_id: txId,
      name: file.name, type: file.type, size: file.size,
      data_url: dataUrl, uploaded_at: Date.now()
    };
    await tx(['screenshots'], 'readwrite', t => t.objectStore('screenshots').put(rec));
    return rec.id;
  }
  async function getScreenshot(id) {
    if (!id) return null;
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('screenshots', 'readonly');
      const req = t.objectStore('screenshots').get(id);
      req.onsuccess = () => resolve(req.result || null);
      req.onerror = () => reject(req.error);
    });
  }

  // ---------- Settings ----------
  async function getSetting(key) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('settings', 'readonly');
      const req = t.objectStore('settings').get(key);
      req.onsuccess = () => resolve(req.result ? req.result.value : null);
      req.onerror   = () => reject(req.error);
    });
  }
  async function setSetting(key, value) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const t = db.transaction('settings', 'readwrite');
      const req = t.objectStore('settings').put({ key, value });
      req.onsuccess = () => resolve(value);
      req.onerror   = () => reject(req.error);
    });
  }

  // ---------- FX rates ----------
  async function exchangeRates(force) {
    const cached = await getSetting('fx_rates');
    const fresh = cached && (Date.now() - cached.updated_at < 24 * 3600 * 1000);
    if (!force && fresh) return cached;
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const base = 'USD';
    try {
      const url = (cfg.exchange_rate_api || 'https://api.exchangerate-api.com/v4/latest/') + base;
      const res = await fetch(url);
      if (res.ok) {
        const j = await res.json();
        const data = { base, rates: j.rates || {}, updated_at: Date.now() };
        await setSetting('fx_rates', data);
        return data;
      }
    } catch (e) { /* fall through */ }
    // fallback static (approximate)
    const fallback = { base, rates: {
      USD:1, EUR:0.92, GBP:0.78, TND:3.13, CAD:1.36, AUD:1.51,
      JPY:151, CNY:7.23, INR:83.4, BRL:5.07, ZAR:18.7, NGN:1450
    }, updated_at: Date.now() };
    if (cached) return cached;
    await setSetting('fx_rates', fallback);
    return fallback;
  }

  function formatFiat(amount, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD', maximumFractionDigits: 2 }).format(amount);
    } catch (e) {
      return (currency || '') + ' ' + (Number(amount) || 0).toFixed(2);
    }
  }

  // ---------- Demo seed (admin convenience) ----------
  async function seedDemo() {
    const existing = await findUserByEmail('demo@2030b.com');
    if (existing) return existing;
    const u = await register({
      email: 'demo@2030b.com', password: 'demo12345',
      full_name: 'Maher (Demo)', country_code: 'TN', language: 'en'
    });
    const { buyTx: b1, sellTx: s1 } = await createPairing({
      userId: u.id, platform: 'redotpay',
      buy:  { amount_usdt: 10, amount_fiat: 30,   fiat_currency: 'TND', fee_percent: 3 },
      sell: { amount_usdt: 10, amount_fiat: 33.9, fiat_currency: 'TND', fee_percent: 0 }
    });
    await setTransactionStatus(b1.id, 'verified', 'Demo seed');
    await setTransactionStatus(s1.id, 'verified', 'Demo seed');
    return await getUser(u.id);
  }

  // ---------- P2P Providers ----------
  // Loads from (in order):
  //   1. localStorage override (admin Preview tab)
  //   2. Backend Ajax with X-API-Key (preferred — server has live JSON)
  //   3. Static JSON file (fallback for fully-static deploys)
  //   4. Hard-coded 2-provider seed (last-resort)
  let providersCache = null;
  async function loadProviders(force) {
    if (providersCache && !force) return providersCache;
    try {
      const stored = localStorage.getItem('b30-providers-override');
      if (stored) {
        providersCache = JSON.parse(stored);
        try { document.dispatchEvent(new CustomEvent('b30:providers-loaded', { detail: providersCache })); } catch(e){}
        return providersCache;
      }
    } catch (e) {}
    // Try secure backend Ajax first
    try {
      const api = await ensureAuthApi();
      const prefix = (document.body && document.body.dataset.prefix) || '';
      const headers = { 'X-Requested-With': 'XMLHttpRequest' };
      if (api && api.api_key_required && api.default_key) headers['X-API-Key'] = api.default_key;
      const res = await fetch(`${prefix}backend/api.php?op=providers.list`, { headers, cache: 'no-cache' });
      if (res.ok) {
        providersCache = await res.json();
        try { document.dispatchEvent(new CustomEvent('b30:providers-loaded', { detail: providersCache })); } catch(e){}
        return providersCache;
      }
    } catch (e) {}
    // Fallback: static JSON file
    try {
      const prefix = (document.body && document.body.dataset.prefix) || '';
      const file = ((window.B30Config && window.B30Config.get()) || {}).p2p_providers_file || 'p2p-providers.json';
      const res = await fetch(`${prefix}${file}`, { cache: 'no-cache' });
      if (res.ok) {
        providersCache = await res.json();
        try { document.dispatchEvent(new CustomEvent('b30:providers-loaded', { detail: providersCache })); } catch(e){}
        return providersCache;
      }
    } catch (e) {}
    providersCache = {
      default_provider: 'binance',
      providers: [
        { code:'binance', name_en:'Binance', enabled:true, logo:'binance', level_required:3, min_usdt_sell:100, min_usdt_buy:10, fee_buy_percent:0, fee_sell_percent:0, supported_fiat:['USD','EUR','TND'], uid_regex:'^[0-9]{6,20}$', uid_label_en:'Binance UID' },
        { code:'redotpay', name_en:'RedotPay', enabled:true, logo:'redotpay', level_required:2, min_usdt_sell:50, min_usdt_buy:5, fee_buy_percent:3, fee_sell_percent:0, supported_fiat:['USD','EUR','TND'], uid_regex:'^[A-Za-z0-9_\\-]{4,32}$', uid_label_en:'RedotPay ID' }
      ]
    };
    return providersCache;
  }
  function getProviders() { return providersCache || { providers: [] }; }
  function getEnabledProviders() {
    const all = getProviders();
    return (all.providers || []).filter(p => p.enabled !== false);
  }
  function getProvider(code) {
    const all = getProviders();
    return (all.providers || []).find(p => (p.code || '').toLowerCase() === (code || '').toLowerCase()) || null;
  }

  // ---------- Security policy ----------
  // Same priority as providers: localStorage > backend Ajax > static JSON > fallback
  let securityCache = null;
  async function loadSecurity(force) {
    if (securityCache && !force) return securityCache;
    try {
      const stored = localStorage.getItem('b30-security-override');
      if (stored) { securityCache = JSON.parse(stored); return securityCache; }
    } catch (e) {}
    // Try secure backend Ajax first (returns only the public subset)
    try {
      const api = await ensureAuthApi();
      const prefix = (document.body && document.body.dataset.prefix) || '';
      const headers = { 'X-Requested-With': 'XMLHttpRequest' };
      if (api && api.api_key_required && api.default_key) headers['X-API-Key'] = api.default_key;
      const res = await fetch(`${prefix}backend/api.php?op=security.public`, { headers, cache: 'no-cache' });
      if (res.ok) { securityCache = await res.json(); return securityCache; }
    } catch (e) {}
    try {
      const prefix = (document.body && document.body.dataset.prefix) || '';
      const file = ((window.B30Config && window.B30Config.get()) || {}).security_file || 'security.json';
      const res = await fetch(`${prefix}${file}`, { cache: 'no-cache' });
      if (res.ok) { securityCache = await res.json(); return securityCache; }
    } catch (e) {}
    securityCache = { auth:{password_min_length:8}, rate_limits:{}, csp:{enabled:false}, upload:{max_size_bytes:5242880, allowed_mime:['image/png','image/jpeg','image/webp']} };
    return securityCache;
  }
  function getSecurity() { return securityCache || {}; }

  // ---------- Ecosystem currencies ----------
  let ecoCache = null;
  async function loadEcosystem(force) {
    if (ecoCache && !force) return ecoCache;
    // 1. Admin local override (set from admin → Ecosystem tab)
    try {
      const stored = localStorage.getItem('b30-ecosystem-override');
      if (stored) {
        ecoCache = JSON.parse(stored);
        try { document.dispatchEvent(new CustomEvent('b30:ecosystem-loaded', { detail: ecoCache })); } catch(e){}
        return ecoCache;
      }
    } catch (e) {}
    // 2. Server file
    try {
      const prefix = (document.body && document.body.dataset.prefix) || '';
      const file = ((window.B30Config && window.B30Config.get()) || {}).ecosystem_currencies_file || 'ecosystem-currencies.json';
      const res = await fetch(`${prefix}${file}`, { cache: 'no-cache' });
      if (res.ok) {
        ecoCache = await res.json();
        try { document.dispatchEvent(new CustomEvent('b30:ecosystem-loaded', { detail: ecoCache })); } catch(e){}
        return ecoCache;
      }
    } catch (e) {}
    ecoCache = { projects: [] };
    try { document.dispatchEvent(new CustomEvent('b30:ecosystem-loaded', { detail: ecoCache })); } catch(e){}
    return ecoCache;
  }
  function getEcosystem() { return ecoCache || { projects: [] }; }

  // ---------- Bootstrap ----------
  const readyPromise = openDB()
    .then(() => ensureSuperAdmin().catch(() => null))
    .then(() => Promise.all([
      loadEcosystem().catch(() => null),
      loadProviders().catch(() => null),
      loadSecurity().catch(() => null)
    ]))
    .then(() => true)
    .catch(() => true);

  window.B30Store = {
    ready: () => readyPromise,
    readyPromise,
    // session
    getSession, clearSession, updateSession,
    // users
    register, signIn, getUser, listUsers, updateProfile,
    // verification
    startEmailVerification, confirmEmailVerification,
    // admin tree
    adminSignIn, adminSignOut, getAdminSession,
    listAdmins, createSubAdmin, updateSubAdmin, deleteSubAdmin,
    adminHasPermission, requireAdminPerm,
    ALL_ADMIN_PERMS,
    // pairings + single ops
    createPairing, createOperation,
    listPairings, listTransactions, setTransactionStatus,
    recomputeCounters, recomputeUserCounters, computeLevel,
    // assets
    saveScreenshot, getScreenshot,
    // api keys
    createApiKey, listApiKeys, updateApiKey, deleteApiKey, findApiKey,
    // auth API bridge (default key)
    ensureAuthApi, authApiCall, getAuthApi,
    // affiliate
    listReferrals, listAffiliateRewards,
    setReferralCookie, getReferralCookie, clearReferralCookie,
    // credit adjustments (penalties etc.)
    adjustUserCredits,
    // ecosystem
    loadEcosystem, getEcosystem,
    // providers
    loadProviders, getProviders, getEnabledProviders, getProvider,
    // security
    loadSecurity, getSecurity,
    // fx
    exchangeRates, formatFiat,
    // settings
    getSetting, setSetting,
    // demo
    seedDemo
  };
})();
