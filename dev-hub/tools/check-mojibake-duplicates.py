#!/usr/bin/env python3
"""
check-mojibake-duplicates.py — Garde encodage & doublons (leçons L-09/L-07).

Issue #7091 (triage 2026-09-09) : correctifs d'encodage répétés (mojibake) sur des
fichiers byte-identiques dupliqués entre apps mobiles (chantier #2661).

Vérifie, sous `front/mobile_apps/` :
  1. MOJIBAKE : aucun pattern d'encodage cassé dans les .dart (Ã©, â€™, ï¿½, �…).
  2. DOUBLONS (info) : fichiers byte-identiques présents dans plusieurs apps (hors
     leopardo_core) → candidats à la migration vers leopardo_core (#2661).

Usage :
  python3 dev-hub/tools/check-mojibake-duplicates.py [--root DIR] [--warn-only]
Sortie : 0 = OK (ou --warn-only), 1 = mojibake détecté.
"""

import argparse
import hashlib
import os
import re
import sys

BAD = re.compile(r"Ã©|Ã¨|Ãª|Ã§|Ã¢|Ã´|Ã»|Ã¯|Ã¼|â€™|â€œ|â€|ï¿½|\ufffd")


def scan(root):
    apps_root = os.path.join(root, "front/mobile_apps")
    mojibake = []
    by_hash = {}
    for dirpath, _dirs, files in os.walk(apps_root):
        for fn in files:
            if not fn.endswith(".dart"):
                continue
            path = os.path.join(dirpath, fn)
            rel = os.path.relpath(path, apps_root)
            with open(path, encoding="utf-8", errors="replace") as fh:
                content = fh.read()
            for lineno, line in enumerate(content.splitlines(), 1):
                if BAD.search(line):
                    mojibake.append(f"{rel}:{lineno}: {line.strip()[:90]}")
            h = hashlib.sha1(content.encode("utf-8", errors="replace")).hexdigest()
            by_hash.setdefault(h, []).append(rel)
    dupes = {h: v for h, v in by_hash.items() if len(v) > 1 and "leopardo_core" not in v[0]}
    return mojibake, dupes


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--warn-only", action="store_true")
    args = ap.parse_args()
    mojibake, dupes = scan(args.root)

    if mojibake:
        print("❌ Mojibake détecté (%d) :" % len(mojibake))
        for m in mojibake[:30]:
            print("   ", m)
        if len(mojibake) > 30:
            print(f"    … et {len(mojibake) - 30} autres")
        return 0 if args.warn_only else 1

    print("✅ Aucun mojibake dans front/mobile_apps.")
    if dupes:
        print("\nℹ Doublons byte-identiques entre apps (chantier #2661 — à migrer vers leopardo_core) :")
        for h, v in list(dupes.items())[:10]:
            print(f"   - {v[0]}  ≡  {v[1]}")
        if len(dupes) > 10:
            print(f"    … et {len(dupes) - 10} autres groupes")
    return 0


if __name__ == "__main__":
    sys.exit(main())
