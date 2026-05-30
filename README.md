# 2030B P2P Pairing — turn real P2P trades into 2030B credits

> **Buy + Sell on Binance or RedotPay → upload screenshots → earn credits → climb 4 levels → unlock 2030B's internal P2P.**
>
> *2030B refuses fiat money. The only currency it accepts is human satisfaction encoded as P2P credits — and P2P credits power every project's currency in the broader 2030B ecosystem (CTC, TIC, VTC, INC, SCC, WPC, WDC, JEC, FLC, GRC).*

A multilingual, multi-currency, gamified credit ecosystem built on **PHP 8 + SQLite** (per-user databases), **vanilla JS + Tailwind CSS + SweetAlert2**, and a fully working **client-side simulation** (IndexedDB) so the whole product can also run as a static demo without a server.

## What's new in v2

- **Level 1 = ONE single P2P op** (buy *or* sell). No pairing required. After your first verified op, the dashboard recommends adding your Binance / RedotPay ID.
- **`ecosystem-currencies.json`** — single source of truth for the 10 ecosystem currencies (Be Smarter / CTC, Be Honester / TIC, Be Healthier / VTC, Be Creater / INC, Be Kinder / SCC, Be Braver / WPC, Be Wiser / WDC, Be Fairer / JEC, Be Freer / FLC, Be Grateful / GRC). Includes per-project `credit_to_currency_rate` and is consumed by P2P, every ecosystem website, and the admin panel.
- **`auth/` folder** — beautiful split-screen authentication UI with email verification, social sign-in (Google / GitHub / X / Facebook), forgot-password flow, post-verify profile completion, and a JSON API (`auth/api/`) that can be opened in a popup from any external domain using auth API keys.
- **API key management** — user-facing page at `auth/api-keys.html` to create/revoke their own keys + embed snippet generator; an `API keys` tab in the admin panel to see and gate every key in the system.
- **Profile completion** — after auth + email verification, users get a beautiful modal asking for Binance ID (UID) and RedotPay ID, with regex validation and SweetAlert2 notifications.
- **Five-Beam Diamond logo** (replaces v1's orbit). Five animated beams converge on a faceted "B" diamond — one beam per ecosystem human dimension.
- **Fixed loader sequence**: page elements are hidden first (via a CSS rule injected in `<head>`) → loader is shown → on `window.load` the loader fades → `html.b30-page-ready` is added, which un-hides the body and only then starts data-rv reveals and counters.
- **Full translations** for all 12 languages (es, de, pt, it, zh, hi, ja, ru, tr were stubs in v1 — now translated for all major UI strings; i18n still falls back to English for any gaps).

---

## ✨ What's in the box

| File / folder | Purpose |
|---|---|
| `index.html` | Public landing page — hero, how-it-works, level grid, **3-step subscribe wizard**. |
| `pages/dashboard.html` | Authenticated user dashboard — credits, level, progress bars, **4-step Add Pairing wizard** with screenshot upload + drop zone. |
| `pages/docs.html` | Documentation — Binance & RedotPay verification (Maher's affiliate links from `config.json`), screenshot rules, level requirements. |
| `pages/admin.html` | Admin panel — pending transactions with **screenshot thumbnails**, users table, config editor. Default password: `admin2030`. |
| `pages/level4.html` | Level 4 *coming soon* — invite-only waitlist form. |
| `pages/404.html` | Not-found page. |
| `config.json` | Single source of truth for **levels, thresholds, fees, currencies, languages, affiliate links, admin password, screenshot rules**. Edit without touching code. |
| `lang/{en,ar,fr,es,de,pt,it,zh,hi,ja,ru,tr}.json` | 12 language packs. **`ar.json` and `fr.json` are fully translated;** the others contain key strings and fall back to English for missing keys. |
| `css/style.css` + `css/enhancements.css` | Brand theme (blue → cyan → green), animated gradients, glass cards, RTL-aware. |
| `js/logos.js` | **Animated SVG 2030B logo** + **fixed page loader** (orbiting P2P nodes, sliding-gradient text, animated progress bar) injected synchronously into `<head>`. |
| `js/config.js` | Loads & caches `config.json` (with built-in fallback). |
| `js/i18n.js` | Loads `lang/{code}.json`, applies translations to `[data-i18n]`, switches `<html dir>` to `rtl` for Arabic. Persists choice in `localStorage`. |
| `js/store.js` | **Full client-side credits/levels engine** — IndexedDB schema mirrors the PHP/SQLite schema. Computes levels, credits, profits, status transitions, screenshot storage. |
| `js/shell.js` | Renders the shared nav + footer on every page. Re-renders on language change. |
| `js/enhancements.js` | Asides (account + language switcher), active-nav highlighting. |
| `js/main.js` | Theme toggle, reveal-on-scroll, counters, mobile menu. |
| `favicon.svg` | Brand favicon. |
| `.htaccess` | Root rewrite rules — prefer PHP backend when installed, otherwise serves static. |
| **`backend/`** | **Full PHP 8 + SQLite backend** (see below). |

---

## 🧮 Core logic — pairing → credit → level

1. A **pairing** = 1 buy transaction + 1 sell transaction (same platform).
2. Each pairing grants `credits_per_pairing` (default = `1`) once both legs are verified.
3. **Virtual profit** = `sell_total - buy_total`, displayed but not withdrawable (gamification).
4. Levels (from `config.json`):

| Level | Requirement | Unlocks |
|------:|---|---|
| **1** | 1 verified pairing | Basic membership |
| **2** | 3 RedotPay ops + 1 RedotPay pairing | RedotPay buy access |
| **3** | 20 Binance ops + 1 Binance pairing | Binance buy access |
| **4** | 100 verified pairings | Internal 2030B P2P (coming soon — invite only) |

All thresholds, fees, currencies and affiliate links live in **one** `config.json`.

---

## 🌍 Multi-language & multi-currency

* 12 languages: English, **العربية (RTL)**, Français, Español, Deutsch, Português, Italiano, 中文, हिन्दी, 日本語, Русский, Türkçe.
* Country selector on signup auto-syncs the currency. 23 countries pre-configured.
* Live FX rates fetched daily from `exchangerate-api.com` (free tier) and cached in the system DB. CRON script: `backend/cron_fx.php`.
* Fallback static rate table if the API is unreachable.

---

## 🚀 Run as a static demo (no backend)

The frontend is fully functional **without PHP** thanks to the IndexedDB-backed `B30Store` (`js/store.js`).

```bash
cd /path/to/2030b
python3 -m http.server 8080
# open http://localhost:8080/
```

Walk-through:

1. Open `/`, scroll to **Join**, complete the 3-step wizard → an IndexedDB user is created.
2. You land on `/pages/dashboard.html` — Level 0, 0 credits.
3. Click **Add new pairing** → 4-step modal: pick **Binance** or **RedotPay**, fill buy + sell legs, drop in PNG/JPG screenshots, review, submit.
4. Open `/pages/admin.html` → enter `admin2030` → approve both pending transactions → user is bumped to Level 1, +1 credit.
5. Switch language via the navbar **🌐** button — try **العربية** to see full RTL.
6. The admin panel can **edit `config.json` live** (local override stored in `localStorage`).

---

## ⚙️ Deploy the PHP + SQLite backend

### Requirements

* **PHP 8.1+** with `pdo_sqlite`, `gd` (or `imagick`) for `getimagesize()`, `openssl` (random_bytes).
* Apache 2.4+ **or** Nginx (sample rules below).
* SSL certificate (mandatory).
* Write permissions for `backend/database/`, `backend/db_users/`, `backend/uploads/`.

### Install

```bash
# 1. Drop the project on your VPS
git clone https://github.com/your-org/2030b.git /var/www/2030b

# 2. Permissions
cd /var/www/2030b
sudo chown -R www-data:www-data backend/database backend/db_users backend/uploads
sudo chmod -R 775 backend/database backend/db_users backend/uploads

# 3. Initialise system DB + fetch first FX snapshot
php backend/setup.php

# 4. (Optional) seed a real admin user with bcrypt password
php backend/setup.php 'mySecureAdminPwd!'

# 5. Set admin panel password in config.json
sed -i 's/"admin_password": "admin2030"/"admin_password": "mySecureAdminPwd!"/' config.json

# 6. Schedule daily FX refresh
echo "0 3 * * * www-data php /var/www/2030b/backend/cron_fx.php" \
  | sudo tee /etc/cron.d/2030b-fx
```

### Apache vhost

```apache
<VirtualHost *:443>
    ServerName 2030b.com
    DocumentRoot /var/www/2030b

    <Directory /var/www/2030b>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/2030b.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/2030b.com/privkey.pem
</VirtualHost>
```

### Nginx (alternative)

```nginx
server {
    listen 443 ssl http2;
    server_name 2030b.com;
    root /var/www/2030b;
    index index.html;

    # SQLite + sql + log files are NEVER served
    location ~* \.(sqlite|sqlite-journal|sql|log)$ { deny all; }
    location ^~ /backend/database/  { deny all; }
    location ^~ /backend/db_users/  { deny all; }
    location ^~ /backend/sql/       { deny all; }
    location ^~ /backend/inc/       { deny all; }

    # Clean URLs → PHP entry points
    location = /          { try_files /backend/index.php   =404; fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; include fastcgi.conf; }
    location = /dashboard { try_files /backend/dashboard.php =404; fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; include fastcgi.conf; }
    location = /docs      { try_files /backend/docs.php      =404; fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; include fastcgi.conf; }
    location = /admin     { try_files /backend/admin.php     =404; fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; include fastcgi.conf; }
    location = /level4    { try_files /backend/level4.php    =404; fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; include fastcgi.conf; }
    location /api/        { rewrite ^/api/(.*)$ /backend/api.php?op=$1 last; }

    location ~ \.php$ { fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; include fastcgi.conf; }
    ssl_certificate     /etc/letsencrypt/live/2030b.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/2030b.com/privkey.pem;
}
```

### API surface

All endpoints are JSON. Mutating endpoints require `X-CSRF` header **or** `csrf` form field equal to `B30_SERVER.csrf`.

```
GET  /api/csrf
POST /api/register        { email, password, full_name, country_code, language, phone }
POST /api/signin          { email, password }
POST /api/signout
GET  /api/user.me
POST /api/pairing.create  multipart: platform, buy_usdt, buy_fiat, buy_currency, buy_date_ts, buy_file,
                                     sell_usdt, sell_fiat, sell_currency, sell_date_ts, sell_file
GET  /api/pairing.list
GET  /api/fx              (force=1 to refresh)
POST /api/admin.signin    { password }
GET  /api/admin.users
GET  /api/admin.pending
POST /api/admin.review    { user_id, tx_id, status: verified|rejected, notes }
POST /api/admin.config    { config: {...} }
POST /api/lvl4.request    { email, full_name, reason }
```

### Database layout

```
backend/
├── database/system.sqlite     # users, sessions, settings, audit_log, lvl4_waitlist
└── db_users/
    ├── user_1.sqlite          # one file per user
    │   ├── transactions
    │   ├── pairings
    │   └── screenshots
    └── user_2.sqlite ...
```

Both schemas are in `backend/sql/system.sql` and `backend/sql/user.sql`.

### Security hardening

* **bcrypt** password hashing (`PASSWORD_BCRYPT`).
* **CSRF tokens** on every mutating endpoint.
* **`getimagesize()`** + MIME whitelist + 5 MB cap on every uploaded screenshot.
* **30-minute session timeout** with absolute expiry.
* **`PDO` prepared statements** everywhere — no string concatenation.
* **`htmlspecialchars`** helper (`b30_h`) for any server-rendered output.
* **`.htaccess`** denies direct access to `.sqlite`, `.sql`, `.log`, `database/`, `db_users/`, `sql/`, `inc/`.
* **Per-request rate limit** (120 calls / minute / session by default).
* Uploads directory has its own `.htaccess` that **disables PHP execution** and only serves images.
* `Permissions-Policy` / `X-Content-Type-Options` / `X-Frame-Options` headers.

### Backups

```bash
# Nightly snapshot
0 4 * * * www-data sqlite3 /var/www/2030b/backend/database/system.sqlite ".backup '/backups/system-$(date +\%F).sqlite'"
```

Per-user SQLite files can be `rsync`'d directly — they're WAL-mode.

---

## 🎨 Animated logo + page loader

* **Logo** (`B30Logos.master`) is a **pure animated SVG** — two opposing buyer/seller nodes orbit a central `2030B` tile, connected by a swap arrow. Rings spin in opposite directions, the tile pulses, and the gradient breathes.
* **Page loader** is injected **synchronously** by `js/logos.js` into `<head>` so it appears **before** the page paints. It uses orbiting CSS shapes (no SVG dependency) + a sliding-gradient tagline. Hides on `window.load + 400 ms` with a hard 6 s safety timeout.

Both respect `prefers-reduced-motion`.

---

## 🤝 Contributing

This is Maher's project. Any change to thresholds, fees, currencies, or affiliate links can be done **entirely in `config.json`** (or live in the admin panel) without touching code or restarting PHP.

License — see `LICENSE`.
