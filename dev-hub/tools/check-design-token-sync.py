#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
T3 — Garde de synchronisation des tokens design (issue #7118, protocole P05 §5).

Pourquoi : la synchro des tokens entre surfaces reposait sur une regle manuelle
(L.07 : AppColors + tailwind.config.* + COULEURS.md bougent ensemble) — deja en
derive (ex. echelle cyan : web '#ecf9ff/50' vs admin '#ecfeff/50'). Ce script
compare les valeurs reelles des surfaces entre elles et avec la semantique
canonique AppColors (docs/REFERENTIEL_PRODUIT/COULEURS.md).

Usage:
    python3 dev-hub/tools/check-design-token-sync.py
Exit: 0 = aucune derive ; 1 = derives detectees (stdout) ; 2 = fichier manquant.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

FILES = {
    "dart": ROOT / "front/mobile_apps/leopardo_core/lib/core/theme/app_colors.dart",
    "web": ROOT / "front/web/tailwind.config.ts",
    "admin": ROOT / "front/admin-dashboard/tailwind.config.js",
}

# Semantique canonique : token Flutter -> (echelle, step) attendu dans les configs
# (source : docs/REFERENTIEL_PRODUIT/COULEURS.md). Echelles absentes d'une config
# (ex. amber/red/violet) signalees en info, pas en derive.
DART_EXPECT = {
    "rh": ("emerald", "500"), "rhLight": ("emerald", "100"), "rhDark": ("emerald", "700"),
    "success": ("emerald", "500"),
    "warning": ("amber", "500"),
    "danger": ("red", "500"),
    "info": ("blue", "500"),
    "security": ("blue", "500"), "securityLight": ("blue", "100"),
    "ia": ("violet", "600"), "iaLight": ("violet", "100"),
    "finance": ("amber", "500"), "financeLight": ("amber", "100"),
    "bgDark": ("slate", "900"), "cardDark": ("slate", "800"), "borderDark": ("slate", "700"),
    "cardLight": ("slate", "50"), "textDark": ("slate", "100"),
    "textMutedDark": ("slate", "400"), "textMuted": ("slate", "500"),
    "borderLight": ("slate", "200"), "border": ("slate", "200"),
}
# Derives actees (a resorber) : vide pour l'instant — toute divergence est une issue.
ALLOWED_DRIFT = set()

WRAPPERS = {"colors", "extend", "theme"}


def norm(h):
    h = re.sub(r"^#", "", str(h)).strip().lower()
    return h if len(h) == 6 else h[-6:]


def parse_config(text):
    """Parse tolérant d'un tailwind.config (ts/js) -> {(palette, step): hex}.

    Parser à indentation : suit les blocs 'cle: {' pour connaître la palette
    courante (ignore les wrappers colors/theme/extend). Couvre les steps
    numeriques, DEFAULT, et les cles plates type surface.
    """
    text = re.sub(r"/\*.*?\*/", "", text, flags=re.S)
    text = re.sub(r"//[^\n]*", "", text)
    out = {}
    stack = []
    last_palette = None
    for raw in text.splitlines():
        line = raw.strip()
        m = re.match(r"([A-Za-z_][\w-]*|'[^']*'|\d+|DEFAULT)\s*:", line)
        if not m:
            continue
        key = m.group(1).strip("'")
        rest = line[m.end():]
        if "{" in rest:
            stack.append(key)
            if key not in WRAPPERS:
                last_palette = key
            continue
        hm = re.search(r"'#([0-9a-fA-F]{6})'|\"#([0-9a-fA-F]{6})\"", rest)
        if not hm:
            continue
        hx = hm.group(1) or hm.group(2)
        if re.fullmatch(r"\d+|DEFAULT", key):
            fam = last_palette if last_palette else (stack[-1] if stack else "?")
            out[(fam, key.lower())] = norm(hx)
        elif last_palette:
            out[(last_palette, key.lower())] = norm(hx)
        else:
            out[(key, "DEFAULT")] = norm(hx)
    return out


def parse_dart(text):
    out = {}
    for m in re.finditer(r"static\s+const\s+Color\s+(\w+)\s*=\s*Color\(\s*0xFF([0-9a-fA-F]{6})", text):
        out[m.group(1)] = norm(m.group(2))
    return out


def main():
    problems, infos = [], []
    for key, path in FILES.items():
        if not path.exists():
            problems.append(f"[{key}] fichier introuvable : {path}")
    if problems:
        print("\n".join(problems))
        return 2

    dart = parse_dart(FILES["dart"].read_text(encoding="utf-8"))
    cfg = {
        "web": parse_config(FILES["web"].read_text(encoding="utf-8")),
        "admin": parse_config(FILES["admin"].read_text(encoding="utf-8")),
    }

    # A. derive web <-> admin sur les cles partagees
    for key in sorted(set(cfg["web"]) & set(cfg["admin"])):
        if cfg["web"][key] != cfg["admin"][key]:
            msg = f"derive web/admin — {key[0]}.{key[1]} : web={cfg['web'][key]} admin={cfg['admin'][key]}"
            (infos if key in ALLOWED_DRIFT else problems).append(msg)

    # B. semantique canonique : token Flutter attendu dans les configs
    for token, (scale, step) in DART_EXPECT.items():
        if token not in dart:
            continue
        expected = dart[token]
        for surface, conf in cfg.items():
            val = conf.get((scale, step))
            if val is None:
                infos.append(
                    f"info — {scale}-{step} absent de {surface} (token {token}, semantique non couverte)"
                )
            elif val != expected:
                problems.append(
                    f"derive semantique — {token} (AppColors #{expected}) != "
                    f"{scale}-{step} [{surface}] #{val}"
                )

    if problems:
        print(f"::error::Check design token sync — {len(problems)} derive(s) (issue #7118)")
        print("\n".join(f"  - {p}" for p in problems))
    for i in infos:
        print(f"  ~ {i}")
    print(f"tokens compares : dart={len(dart)} web={len(cfg['web'])} admin={len(cfg['admin'])}")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
