/* 2030B P2P Pairing — Config loader
 * Loads /config.json (or ../config.json from /pages/), caches it on window.B30Config.
 */
(function () {
  const FALLBACK = {
    platform: { name: '2030B P2P Pairing', version: '1.0' },
    levels: {
      "1": { required_pairings: 1, unlocks: 'basic_membership' },
      "2": { required_redotpay_operations: 3, required_redotpay_pairings: 1, unlocks: 'redotpay_buy_access' },
      "3": { required_binance_operations: 20, required_binance_pairings: 1, unlocks: 'binance_buy_access' },
      "4": { required_pairings: 100, unlocks: 'internal_p2p_coming_soon' }
    },
    fees: { buy_fee_percent: 3, sell_fee_percent: 0 },
    profit_percent: 10,
    credits_per_pairing: 1,
    default_fiat_currency: 'TND',
    supported_fiat_currencies: ['TND','USD','EUR','GBP','CAD','AUD','JPY','CNY','INR','BRL','ZAR','NGN'],
    exchange_rate_api: 'https://api.exchangerate-api.com/v4/latest/',
    min_sell_amount_redotpay_usdt: 50,
    min_sell_amount_binance_usdt: 100,
    affiliate: {
      binance:  'https://accounts.binance.com/register?ref=2030BMAHER',
      redotpay: 'https://url.hk/i/en/2030BMAHER'
    },
    supported_languages: ['en','ar','fr','es','de','pt','it','zh','hi','ja','ru','tr']
  };

  let loaded = null;
  const listeners = [];

  function path() {
    const prefix = (document.body && document.body.dataset.prefix) || '';
    return `${prefix}config.json`;
  }

  async function load() {
    if (loaded) return loaded;
    try {
      const res = await fetch(path(), { cache: 'no-cache' });
      if (res.ok) loaded = await res.json();
      else loaded = FALLBACK;
    } catch (e) {
      loaded = FALLBACK;
    }
    listeners.forEach(fn => { try { fn(loaded); } catch (e) {} });
    return loaded;
  }

  function get() { return loaded || FALLBACK; }
  function onLoad(fn) {
    if (loaded) fn(loaded);
    else listeners.push(fn);
  }

  window.B30Config = { load, get, onLoad, FALLBACK };
  // Kick off immediately
  if (document.body) load();
  else document.addEventListener('DOMContentLoaded', load, { once: true });
})();
