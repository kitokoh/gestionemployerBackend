#!/usr/bin/env bash
# ============================================================
# check-i18n-catalog-parity.sh — Garde bloquante i18n consolidée (issue #7089, RETEX L-10)
#
# Vérifie la parité des CLÉS entre les 4 langues (fr/en/ar/tr) pour les
# 3 familles de catalogues du monorepo :
#   backend : api/lang/<lang>/*.php            (clés plates `'xxx.yyy' =>`)
#   shared  : shared/i18n/locales/*.json       (consommé par le web Next.js)
#   mobile  : front/mobile_apps/leopardo_core/lib/l10n/app_*.arb (clés utilisateur)
#
# Contexte (leçon L-10, triage RETEX 2026-09-09) : rapports I18N_DEBT_REPORT_*
# quasi quotidiens en août 2026 — clés manquantes entre shared/i18n/locales,
# api/lang et les ARB mobile. Une clé ajoutée dans une seule langue casse
# l'expérience des 3 autres publics ; les gardes existantes
# (check-mobile-l10n-sync.sh, sync shared/i18n) sont conservées — celle-ci est
# le filet commun bloquant sur toute PR touchant les catalogues.
#
# Vert sur main au 2026-09-09 (parité vérifiée). Ne signale que la DÉRIVE de
# clés (manquantes / surnuméraires) entre les 4 langues d'une même famille.
# Heuristique volontairement légère (clés plates), cohérente avec les gardes
# sœurs du dossier : les clés imbriquées des catalogues sont rares et leur
# traitement fin relève du validateur partagé (shared/i18n/validators).
#
# Usage: bash dev-hub/tools/check-i18n-catalog-parity.sh [root_dir]
#   root_dir (défaut: racine du repo) — permet au test d'utiliser des fixtures.
# Exit 0 = parité OK ; exit 1 = dérive (messages ::error::).
# ============================================================
set -uo pipefail

ROOT_DIR="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

cd "$ROOT_DIR"

python3 - "$ROOT_DIR" <<'PYEOF'
import json
import os
import re
import sys

root = sys.argv[1]
errors = []

# --- 1. Backend : api/lang/<lang>/*.php — clés plates (heuristic, cf. entête)
def php_keys(path):
    with open(path, encoding="utf-8") as f:
        text = f.read()
    return set(re.findall(r"'([A-Za-z0-9_.\-]+)'\s*=>", text))

def check_backend():
    langs = ["fr", "en", "ar", "tr"]
    base = os.path.join(root, "api/lang")
    if not os.path.isdir(base):
        return  # pas de backend (fixtures de test partielles)
    files = set()
    for lang in langs:
        d = os.path.join(base, lang)
        if os.path.isdir(d):
            files |= set(os.listdir(d))
    for name in sorted(files):
        keysets = {}
        missing_file = False
        for lang in langs:
            p = os.path.join(base, lang, name)
            if not os.path.exists(p):
                errors.append(f"api/lang/{lang}/{name} : fichier manquant (présent dans les autres langues)")
                missing_file = True
                continue
            try:
                keysets[lang] = php_keys(p)
            except Exception as exc:  # noqa: BLE001 — garde légère
                errors.append(f"api/lang/{lang}/{name} : illisible ({exc})")
        if missing_file:
            continue
        ref = keysets["fr"]
        for lang in langs:
            if keysets[lang] != ref:
                missing = sorted(ref - keysets[lang])
                extra = sorted(keysets[lang] - ref)
                if missing:
                    errors.append(f"api/lang/{lang}/{name} : {len(missing)} clé(s) manquante(s) vs fr — ex. {missing[:3]}")
                if extra:
                    errors.append(f"api/lang/{lang}/{name} : {len(extra)} clé(s) absente(s) de fr — ex. {extra[:3]}")

# --- 2. Shared (web) : shared/i18n/locales/*.json
def json_keys(path, skip_at=True):
    with open(path, encoding="utf-8") as f:
        data = json.load(f)
    return {k for k in data if not (skip_at and k.startswith("@"))}

def check_shared():
    base = os.path.join(root, "shared/i18n/locales")
    if not os.path.isdir(base):
        return
    files = sorted(f for f in os.listdir(base) if f.endswith(".json"))
    if not files:
        return
    ref = None
    for name in files:
        p = os.path.join(base, name)
        try:
            keys = json_keys(p)
        except Exception as exc:  # noqa: BLE001
            errors.append(f"shared/i18n/locales/{name} : illisible ({exc})")
            continue
        if ref is None:
            ref = keys
        elif keys != ref:
            missing = sorted(ref - keys)
            extra = sorted(keys - ref)
            if missing:
                errors.append(f"shared/i18n/locales/{name} : {len(missing)} clé(s) manquante(s) — ex. {missing[:3]}")
            if extra:
                errors.append(f"shared/i18n/locales/{name} : {len(extra)} clé(s) surnuméraire(s) — ex. {extra[:3]}")

# --- 3. Mobile : ARB de leopardo_core (clés utilisateur, hors @metadata)
def check_arb():
    base = os.path.join(root, "front/mobile_apps/leopardo_core/lib/l10n")
    if not os.path.isdir(base):
        return
    files = sorted(f for f in os.listdir(base) if f.endswith(".arb"))
    if not files:
        return
    ref = None
    for name in files:
        p = os.path.join(base, name)
        try:
            keys = json_keys(p)
        except Exception as exc:  # noqa: BLE001
            errors.append(f"{base}/{name} : illisible ({exc})")
            continue
        if ref is None:
            ref = keys
        elif keys != ref:
            missing = sorted(ref - keys)
            extra = sorted(keys - ref)
            if missing:
                errors.append(f"{name} : {len(missing)} clé(s) manquante(s) — ex. {missing[:3]}")
            if extra:
                errors.append(f"{name} : {len(extra)} clé(s) surnuméraire(s) — ex. {extra[:3]}")

check_backend()
check_shared()
check_arb()

if errors:
    for e in errors:
        print(f"::error::check-i18n-catalog-parity : {e}")
    print(f"::error::check-i18n-catalog-parity : {len(errors)} dérive(s) de parité i18n — toute chaîne ajoutée doit l'être dans les 4 langues (fr/en/ar/tr), leçon L-10.")
    sys.exit(1)

print("check-i18n-catalog-parity : parité i18n OK (backend api/lang, shared/i18n, ARB mobile — 4 langues).")
PYEOF
