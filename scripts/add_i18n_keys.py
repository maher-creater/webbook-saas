#!/usr/bin/env python3
"""
Add the new i18n keys for v3 work (providers, security, docs, keys, penalties)
into every lang/*.json file. Existing keys are left untouched.
"""
import json
import os
from pathlib import Path

LANG_DIR = Path(__file__).resolve().parent.parent / "lang"

# Master English source for the new keys
NEW_KEYS_EN = {
    # ---- Nav additions ----
    "nav.docs.user": "User guide",
    "nav.docs.user.sub": "Sign up, pair, level up",
    "nav.docs.affiliate": "Affiliate program",
    "nav.docs.affiliate.sub": "Referral kickbacks",
    "nav.docs.admin": "Sub-admin manual",
    "nav.docs.admin.sub": "Verify, single-ops, penalties",
    "nav.docs.superadmin": "Super-admin guide",
    "nav.docs.superadmin.sub": "Providers, security, keys",
    "nav.docs.api": "Auth API reference",
    "nav.docs.api.sub": "Keys, endpoints, examples",
    "nav.docs.legacy": "Legacy docs",
    "nav.affiliate": "Affiliate",

    # ---- Landing additions ----
    "landing.eco.title": "Spend your credits in the 2030B ecosystem",
    "landing.eco.subtitle": "Every credit you earn from real P2P pairings unlocks projects, currencies and services across our network.",
    "landing.keys.title": "Your credits are your keys",
    "landing.keys.subtitle": "Hold credits, get keys. Keys unlock APIs, premium tools and the internal P2P market at Level 4.",
    "landing.keys.cta": "Manage my keys",

    # ---- Providers (super-admin tab + tiles) ----
    "providers.title": "P2P providers",
    "providers.subtitle": "Enable or disable providers, set fees and UID rules.",
    "providers.add": "Add provider",
    "providers.reset": "Reset",
    "providers.save": "Save providers",
    "providers.code": "Code",
    "providers.name": "Display name",
    "providers.website": "Website",
    "providers.verify_url": "Verify URL",
    "providers.level_required": "Required level",
    "providers.min_usdt": "Min USDT",
    "providers.max_usdt": "Max USDT",
    "providers.fees": "Maker / taker fees",
    "providers.uid_label": "UID label",
    "providers.uid_regex": "UID regex",
    "providers.color_primary": "Primary color",
    "providers.color_accent": "Accent color",
    "providers.supported_fiat": "Supported fiat",
    "providers.enabled": "Enabled",
    "providers.delete": "Delete",
    "providers.empty": "No provider configured yet.",
    "providers.saved": "Providers saved.",
    "providers.disabled_warn": "This provider is disabled.",
    "providers.choose": "Choose provider",

    # ---- Security (super-admin tab) ----
    "security.title": "Security policy",
    "security.subtitle": "Auth, rate-limits, uploads, CSP, audit and privacy.",
    "security.save": "Save security",
    "security.reset": "Reset",
    "security.raw": "Advanced (raw JSON)",
    "security.section.auth": "Authentication",
    "security.section.rate": "Rate limits",
    "security.section.upload": "Uploads",
    "security.section.csrf": "CSRF",
    "security.section.cors": "CORS",
    "security.section.csp": "Content-Security-Policy",
    "security.section.ip": "IP policy",
    "security.section.fraud": "Fraud detection",
    "security.section.audit": "Audit log",
    "security.section.privacy": "Data privacy",
    "security.saved": "Security policy saved.",
    "security.invalid_json": "Invalid JSON.",

    # ---- Penalties ----
    "penalties.title": "Penalties",
    "penalties.subtitle": "Issued sanctions, deductions and appeals.",
    "penalties.issue": "Issue penalty",
    "penalties.close": "Close",
    "penalties.target": "Target",
    "penalties.rule": "Rule",
    "penalties.severity": "Severity",
    "penalties.delta": "Credits delta",
    "penalties.reason": "Reason",
    "penalties.status": "Status",
    "penalties.empty": "No penalties on record.",
    "penalties.confirm_close": "Close this penalty and refund credits?",

    # ---- Keys (user + super-admin) ----
    "keys.title": "API keys",
    "keys.subtitle": "Programmatic access to your account.",
    "keys.create": "Create key",
    "keys.label": "Label",
    "keys.scopes": "Scopes",
    "keys.origin": "Origin allow-list",
    "keys.enabled": "Enabled",
    "keys.copy": "Copy",
    "keys.copied": "Key copied to clipboard.",
    "keys.delete": "Delete",
    "keys.confirm_delete": "Delete this key permanently?",
    "keys.empty": "You have no API key yet.",
    "keys.default": "System default key",
    "keys.rotate": "Rotate key",
    "keys.snippet": "Quick-start embed",

    # ---- Docs landing tiles ----
    "docs.tiles.user": "User guide",
    "docs.tiles.affiliate": "Affiliate program",
    "docs.tiles.admin": "Sub-admin manual",
    "docs.tiles.superadmin": "Super-admin guide",
    "docs.tiles.api": "Auth API reference",

    # ---- Docs section titles (shared) ----
    "docs.toc": "Table of contents",
    "docs.back": "Back to docs",

    # ---- Affiliate page ----
    "affiliate.title": "Affiliate program",
    "affiliate.subtitle": "Invite friends, earn a share of their pairing credits.",
    "affiliate.your_code": "Your referral code",
    "affiliate.your_link": "Your referral link",
    "affiliate.earnings": "Total kickback credits",
    "affiliate.referrals": "Referrals",
    "affiliate.copy": "Copy",

    # ---- Auth (default key wiring) ----
    "auth.api.unavailable": "Auth API is not available. Please try again later.",
    "auth.api.invalid_key": "Default API key is missing or invalid. Contact super-admin.",
}

def merge_lang(path: Path, additions: dict, translate=None):
    data = json.loads(path.read_text(encoding="utf-8"))
    added = 0
    for k, v in additions.items():
        if k not in data:
            data[k] = translate(k, v) if translate else v
            added += 1
    path.write_text(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    return added

if __name__ == "__main__":
    # English: master
    n = merge_lang(LANG_DIR / "en.json", NEW_KEYS_EN)
    print(f"en.json: +{n}")
    # Other languages will be filled by a follow-up script that has the translations.
