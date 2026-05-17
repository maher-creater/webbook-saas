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
  const DB_VER  = 2;
  const SESSION_KEY = 'b30-session';
  const ADMIN_SESSION_KEY = 'b30-admin-session';

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

  async function register({ email, password, full_name, country_code, language, phone }) {
    if (!email || !password) throw new Error('Email and password required');
    email = email.toLowerCase().trim();
    const existing = await findUserByEmail(email);
    if (existing) throw new Error('Email already registered');
    const hash = await hashPw(password);
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
      created_at: Date.now(), last_login: Date.now(),
      is_active: true, role: 'user'
    };
    await putUser(user);
    setSession(user);
    return user;
  }

  async function signIn(email, password) {
    email = (email || '').toLowerCase().trim();
    const user = await findUserByEmail(email);
    if (!user) throw new Error('User not found');
    const ok = await verifyPw(password, user.password_hash);
    if (!ok) throw new Error('Wrong password');
    user.last_login = Date.now();
    await putUser(user);
    setSession(user);
    return user;
  }

  // ---------- Admin ----------
  function getAdminSession() {
    try { return JSON.parse(localStorage.getItem(ADMIN_SESSION_KEY) || 'null'); } catch (e) { return null; }
  }
  function adminSignIn(password) {
    // Default admin password is "admin2030" — overridable in config.json via admin_password.
    const cfg = (window.B30Config && window.B30Config.get()) || {};
    const expected = cfg.admin_password || 'admin2030';
    if (password !== expected) return false;
    localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify({ at: Date.now() }));
    return true;
  }
  function adminSignOut() { localStorage.removeItem(ADMIN_SESSION_KEY); }

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
    const existing = await findUserByEmail('demo@2030b.io');
    if (existing) return existing;
    const u = await register({
      email: 'demo@2030b.io', password: 'demo12345',
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

  // ---------- Bootstrap ----------
  const readyPromise = openDB().then(() => true).catch(() => true);

  window.B30Store = {
    ready: () => readyPromise,
    // session
    getSession, clearSession, updateSession,
    // users
    register, signIn, getUser, listUsers, updateProfile,
    // verification
    startEmailVerification, confirmEmailVerification,
    // admin
    adminSignIn, adminSignOut, getAdminSession,
    // pairings + single ops
    createPairing, createOperation,
    listPairings, listTransactions, setTransactionStatus,
    recomputeCounters, recomputeUserCounters, computeLevel,
    // assets
    saveScreenshot, getScreenshot,
    // api keys
    createApiKey, listApiKeys, updateApiKey, deleteApiKey, findApiKey,
    // fx
    exchangeRates, formatFiat,
    // settings
    getSetting, setSetting,
    // demo
    seedDemo
  };
})();
