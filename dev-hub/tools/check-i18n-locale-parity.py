#!/usr/bin/env python3
"""
check-i18n-locale-parity.py — Parité des clés i18n partagées (fr/en/ar/tr).

Vérifie que les 4 fichiers de `shared/i18n/locales/` (fr, en, ar, tr) exposent le
même jeu de clés (structure à plat via chemins imbriqués `a.b.c`). `fr` est la
locale de référence ; toute clé manquante en en/ar/tr est un écart.

Issue #7089 (leçon L-10 : dette i18n récurrente — rapports I18N_DEBT_REPORT_* d'août
2026). Usage :
  python3 dev-hub/tools/check-i18n-locale-parity.py [--root DIR] [--warn-only]
Sortie : 0 = parité OK, 1 = écarts détectés (sauf --warn-only).
"""

import argparse
import json
import os
import sys

LOCALES = ["fr", "en", "ar", "tr"]


def flatten(obj, prefix=""):
    out = set()
    for k, v in obj.items():
        path = f"{prefix}.{k}" if prefix else k
        if isinstance(v, dict):
            out |= flatten(v, path)
        else:
            out.add(path)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--warn-only", action="store_true")
    args = ap.parse_args()

    base = os.path.join(args.root, "shared/i18n/locales")
    keys = {}
    for loc in LOCALES:
        path = os.path.join(base, f"{loc}.json")
        if not os.path.exists(path):
            print(f"::error::{path} introuvable")
            return 2
        with open(path, encoding="utf-8") as fh:
            keys[loc] = flatten(json.load(fh))

    ref = keys["fr"]
    problems, infos = [], []
    infos.append(f"fr : {len(ref)} clés (référence)")
    for loc in LOCALES[1:]:
        missing = sorted(ref - keys[loc])
        extra = sorted(keys[loc] - ref)
        infos.append(f"{loc} : {len(keys[loc])} clés, {len(missing)} manquantes, {len(extra)} surnuméraires")
        for k in missing:
            problems.append(f"{loc} : clé manquante `{k}` (présente en fr)")
        for k in extra:
            problems.append(f"{loc} : clé surnuméraire `{k}` (absente en fr) — à supprimer ou ajouter partout")

    for line in infos:
        print("ℹ", line)
    if problems:
        print("\nÉcarts i18n : %d (règle : une clé ajoutée l'est dans les 4 locales — protocole P04/leçon L-10)" % len(problems))
        for p in problems[:40]:
            print("❌", p)
        if len(problems) > 40:
            print(f"… et {len(problems) - 40} autres")
        return 0 if args.warn_only else 1
    print("\n✅ Parité i18n OK (fr/en/ar/tr).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
