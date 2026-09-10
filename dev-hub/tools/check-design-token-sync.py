#!/usr/bin/env python3
"""
check-design-token-sync.py — Vérifie la synchronisation des tokens design.

Source de vérité : docs/REFERENTIEL_PRODUIT/COULEURS.md (APV L.07 : « AppColors.dart +
tailwind.config.js + COULEURS.md bougent ensemble dans la même PR »).

Vérifications :
  1. Flutter : chaque hex de COULEURS.md existe dans app_colors.dart (0xFF…)
     et chaque token `AppColors.<id>` documenté y est déclaré.
  2. Web / Admin (tailwind) : quand le fichier de config définit une palette nommée
     (ex. emerald), la valeur documentée de la nuance doit correspondre à l'hex de
     COULEURS.md. Une palette absente du fichier = palette par défaut Tailwind -> INFO.
  3. Usage (INFO) : occurrences des classes documentées dans le code source.

Usage :
  python3 dev-hub/tools/check-design-token-sync.py [--root DIR] [--no-flutter]
        [--no-web] [--no-admin] [--warn-only]

Code : issue #7070 / protocole P05. Sortie : 0 = OK, 1 = écart détecté
(sauf --warn-only).
"""

import argparse
import os
import re
import sys

PREFIXES = {
    "bg", "text", "border", "ring", "fill", "stroke", "from", "via", "to",
    "divide", "placeholder", "accent", "caret", "outline", "decoration", "shadow",
}
DEFAULT_PALETTES = {"slate", "gray", "zinc", "neutral", "stone", "red", "orange",
                    "amber", "yellow", "lime", "green", "emerald", "teal", "cyan",
                    "sky", "blue", "indigo", "violet", "purple", "fuchsia", "pink", "rose"}


def parse_couleurs(path):
    """Retourne une liste de dicts {hex, flutter, classes:[...]}."""
    rows = []
    with open(path, encoding="utf-8") as fh:
        for raw in fh:
            line = raw.strip()
            if not line.startswith("|"):
                continue
            cells = [c.strip().strip("`") for c in line.strip("|").split("|")]
            hexes = [c for c in cells if re.fullmatch(r"#[0-9A-Fa-f]{6}", c)]
            flutters = [c for c in cells if c.startswith("AppColors.")]
            classes = [c for c in cells
                       if re.fullmatch(r"(?:bg|text|border|ring)-[a-z]+-[0-9]{2,3}", c)
                       or re.fullmatch(r"[a-z]+-[0-9]{2,3}", c)]
            if hexes:
                rows.append({"hex": hexes[0].lstrip("#").upper(),
                             "flutter": flutters[0] if flutters else None,
                             "classes": classes})
    return rows


def check_flutter(rows, app_colors_path):
    problems, infos = [], []
    if not os.path.exists(app_colors_path):
        return ["Fichier introuvable : %s" % app_colors_path], []
    raw = open(app_colors_path, encoding="utf-8").read()
    src = raw.upper()
    for row in rows:
        if "0XFF" + row["hex"] not in src:
            problems.append("Flutter : hex #%s (COULEURS.md) absent de %s"
                            % (row["hex"], os.path.relpath(app_colors_path)))
    declared = set(re.findall(r"static const Color\s+([A-Za-z0-9_]+)", raw))
    for row in rows:
        tok = row["flutter"]
        if tok:
            ident = tok.split(".")[-1]
            if ident not in declared:
                problems.append("Flutter : token %s documenté dans COULEURS.md mais "
                                "non déclaré dans app_colors.dart" % tok)
    infos.append("Flutter : %d tokens documentés, %d déclarés dans app_colors.dart"
                 % (len([r for r in rows if r["flutter"]]), len(declared)))
    return problems, infos


def parse_tailwind_palettes(config_path):
    """{palette: {shade: '#hex'}} pour les blocs `name: { shade: 'hex', ... }`."""
    text = open(config_path, encoding="utf-8").read()
    palettes = {}
    # Bloc palette : nom: { ... } sans accolade imbriquée dans le corps
    for m in re.finditer(r"([a-z][a-z0-9-]*):\s*\{(?P<body>[^{}]*)\}", text):
        name, body = m.group(1), m.group("body")
        shades = dict(re.findall(r"(\d{2,3}|[a-z]+)\s*:\s*['\"](#?[0-9A-Fa-f]{6})['\"]", body))
        if shades:
            palettes[name] = {k: v.lstrip("#").upper() for k, v in shades.items()}
    return palettes


def normalize_class(cls):
    """('emerald', '500') pour bg-emerald-500 / emerald-500 / text-slate-900."""
    parts = cls.split("-")
    if parts[0] in PREFIXES:
        parts = parts[1:]
    if len(parts) == 2 and parts[1].isdigit():
        return parts[0], parts[1]
    return None


def check_tailwind(rows, config_path, label):
    problems, infos = [], []
    if not os.path.exists(config_path):
        return ["Fichier introuvable : %s" % config_path], []
    palettes = parse_tailwind_palettes(config_path)
    infos.append("%s : %d palettes explicites trouvées dans le config"
                 % (label, len(palettes)))
    seen = set()
    for row in rows:
        for cls in row["classes"]:
            norm = normalize_class(cls)
            if not norm:
                continue
            key = (norm[0], norm[1])
            if key in seen:
                continue
            seen.add(key)
            pal, shade = norm
            if pal in palettes:
                if shade in palettes[pal]:
                    actual = palettes[pal][shade]
                    if actual != row["hex"]:
                        problems.append(
                            "%s : %s-%s vaut %s dans le config mais %s dans COULEURS.md"
                            % (label, pal, shade, "#" + actual, "#" + row["hex"]))
                else:
                    infos.append("%s : nuance %s-%s absente du config (INFO)"
                                 % (label, pal, shade))
            else:
                infos.append("%s : palette %s absente du config (défaut Tailwind ? INFO)"
                             % (label, pal))
    return problems, infos


def check_cross_surface(web_path, admin_path):
    """Compare les palettes partagées entre la vitrine web et l'admin (issue #7149).

    La garde historique ne comparait chaque config qu'à COULEURS.md : une dérive
    entre deux surfaces sur une palette non documentée (ex. échelle cyan) restait
    invisible. Ici : toute clé (palette, nuance) présente des deux côtés doit
    porter la même valeur.
    """
    problems = []
    if not (os.path.exists(web_path) and os.path.exists(admin_path)):
        return problems
    web = parse_tailwind_palettes(web_path)
    admin = parse_tailwind_palettes(admin_path)
    for pal in sorted(set(web) & set(admin)):
        for shade in sorted(set(web[pal]) & set(admin[pal])):
            if web[pal][shade] != admin[pal][shade]:
                problems.append(
                    "derive inter-surfaces — %s-%s : web #%s vs admin #%s"
                    % (pal, shade, web[pal][shade], admin[pal][shade]))
    return problems


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--no-flutter", action="store_true")
    ap.add_argument("--no-web", action="store_true")
    ap.add_argument("--no-admin", action="store_true")
    ap.add_argument("--warn-only", action="store_true")
    args = ap.parse_args()
    root = args.root

    couleurs = os.path.join(root, "docs/REFERENTIEL_PRODUIT/COULEURS.md")
    if not os.path.exists(couleurs):
        print("::error::COULEURS.md introuvable (%s)" % couleurs)
        return 2
    rows = parse_couleurs(couleurs)
    print("COULEURS.md : %d lignes de tokens lues" % len(rows))

    problems, infos = [], []
    if not args.no_flutter:
        p, i = check_flutter(rows, os.path.join(
            root, "front/mobile_apps/leopardo_core/lib/core/theme/app_colors.dart"))
        problems += p; infos += i
    if not args.no_web:
        p, i = check_tailwind(rows, os.path.join(root, "front/web/tailwind.config.ts"), "Web")
        problems += p; infos += i
    if not args.no_admin:
        p, i = check_tailwind(rows, os.path.join(root, "front/admin-dashboard/tailwind.config.js"),
                              "Admin")
        problems += p; infos += i

    # Comparaison inter-surfaces (issue #7149) : palettes partagées web ↔ admin
    if not args.no_web and not args.no_admin:
        p = check_cross_surface(
            os.path.join(root, "front/web/tailwind.config.ts"),
            os.path.join(root, "front/admin-dashboard/tailwind.config.js"))
        problems += p
        if not p:
            infos.append("Web ↔ Admin : palettes partagées cohérentes")

    for line in infos:
        print("ℹ", line)
    for line in problems:
        print("❌", line)
    if problems:
        print("\nÉcarts détectés : %d. Règle APV L.07 / protocole P05 : COULEURS.md,"
              " app_colors.dart et les configs Tailwind bougent ensemble." % len(problems))
        return 0 if args.warn_only else 1
    print("\n✅ Tokens synchronisés (COULEURS.md ↔ Flutter ↔ Tailwind).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
