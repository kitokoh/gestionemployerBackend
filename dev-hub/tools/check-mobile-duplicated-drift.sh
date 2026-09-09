#!/usr/bin/env bash
# ============================================================
# check-mobile-duplicated-drift.sh — Garde anti-mojibake sur fichiers
# dupliqués entre apps mobiles (issue #7091, RETEX L-09/L-07, triage 2026-09-09)
#
# Contexte : des fichiers byte-identiques sont dupliqués entre
# leopardo_employee / leopardo_manager / leopardo_hr (11 familles constatées
# au 2026-09-09, chantier de migration #2661). Les correctifs d'encodage
# (accents/mojibake) appliqués à UNE seule copie laissent la classe de bug
# vivante chez les copie(s) sœur(s) — correctifs répétés constatés en QA.
#
# Ce garde analyse le diff base..head d'une PR :
#   * ERREUR (exit 1) : un fichier dupliqué modifié devient propre (plus de
#     séquence mojibake) alors qu'au moins une copie sœur, byte-identique à
#     la base, contient ENCORE le mojibake → le correctif doit être propagé
#     aux sœurs (ou la duplication supprimée via #2661).
#   * WARNING (::warning::) : des copies byte-identiques à la base ont
#     divergé dans cette PR sans cas mojibake — signaler pour propagation
#     ou suppression volontaire de la duplication.
#
# Le mojibake est détecté avec le même jeu de patrons que la garde
# gouvernance (#1612). Heuristique légère et déterministe, cohérente avec les
# gardes sœurs du dossier : elle ne se déclenche que sur la classe de bug
# exacte (copies identiques à la base), jamais sur les divergences préexistantes.
#
# Usage: bash dev-hub/tools/check-mobile-duplicated-drift.sh <base_sha> <head_sha>
# Exit 0 = OK ; exit 1 = correctif mojibake non propagé aux copies dupliquées.
# ============================================================
set -euo pipefail

BASE_SHA="${1:-}"
HEAD_SHA="${2:-}"

if [ -z "$BASE_SHA" ] || [ -z "$HEAD_SHA" ]; then
  echo "Usage: $0 <base_sha> <head_sha>" >&2
  exit 1
fi

export BASE_SHA HEAD_SHA
python3 - <<'PYEOF'
import os
import re
import subprocess
import sys

APPS = ["leopardo_employee", "leopardo_manager", "leopardo_hr"]
PREFIX = "front/mobile_apps/"
ROOT = os.getcwd()

# Mêmes patrons que check-governance.ps1 (#1612) — séquences mojibake latin-1/UTF-8.
MOJI = re.compile(
    r"G\u00c7\u00f6|G\u00e5\u00c6|G\u00f9\u00ef|\+\u00ac|\+\u00a6|\+\u00bf|"
    r"\+\u00ba|\+\u00e1|\u00e2-\u00ac|\u00e2-\u00a6|\u00e2-\u00bf|\u00e2-\u00e1|"
    r"\u00f3\u2014{2}|\u00c3[\u00a0-\u00bf]|\u00e2\u20ac|\ufffd"
)

BASE_SHA = os.environ["BASE_SHA"]
HEAD_SHA = os.environ["HEAD_SHA"]

def git(*args):
    return subprocess.run(
        ["git", *args], capture_output=True, text=True, check=True
    ).stdout

def git_bytes(sha, path):
    """Contenu binaire d'un fichier à un SHA (None si absent de l'arbre)."""
    res = subprocess.run(
        ["git", "show", f"{sha}:{path}"], capture_output=True
    )
    if res.returncode != 0:
        return None
    return res.stdout

def relpath_for(app, full_path):
    return full_path[len(PREFIX + app + "/lib/"):]

def has_mojibake(content_bytes):
    if content_bytes is None:
        return False
    text = content_bytes.decode("utf-8", errors="replace")
    return bool(MOJI.search(text))

# --- Diff base..head sur les lib/ des apps du trio
statuses = {}
for line in git("diff", "--name-status", BASE_SHA, HEAD_SHA, "--", PREFIX).splitlines():
    parts = line.split("\t")
    if len(parts) < 2:
        continue
    status, path = parts[0], parts[1]
    app = next((a for a in APPS if path.startswith(PREFIX + a + "/lib/")), None)
    if app is None:
        continue
    statuses.setdefault(path, []).append((status, app))

errors, warnings = [], []

for path, entries in statuses.items():
    for status, app in entries:
        rel = relpath_for(app, path)
        # Copie(s) sœur(s) : même relpath dans les autres apps du trio
        siblings = []
        for other in APPS:
            if other == app:
                continue
            other_path = f"{PREFIX}{other}/lib/{rel}"
            if git_bytes(HEAD_SHA, other_path) is not None:
                siblings.append((other, other_path))

        if status == "D":
            # Suppression d'une copie dupliquée : sœur(s) byte-identique(s) à la base ?
            base_self = git_bytes(BASE_SHA, path)
            for other, other_path in siblings:
                if git_bytes(BASE_SHA, other_path) == base_self and base_self is not None:
                    warnings.append(
                        f"{path} supprimé alors que {other_path} était byte-identique à la base — "
                        "confirmer l'intention (migration leopardo_core #2661 ?)"
                    )
            continue

        if status not in ("M", "A", "R", "T"):
            continue

        head_self = git_bytes(HEAD_SHA, path)
        head_self_moji = has_mojibake(head_self)
        base_self = git_bytes(BASE_SHA, path)
        base_self_moji = has_mojibake(base_self)

        for other, other_path in siblings:
            base_other = git_bytes(BASE_SHA, other_path)
            head_other = git_bytes(HEAD_SHA, other_path)
            if base_other is None:
                continue  # la sœur n'existait pas à la base : pas une copie préexistante

            identical_at_base = base_self == base_other and base_self is not None

            # CAS A — erreur : copie nettoyée ici, sœur encore mojibake à head
            # (et les deux copies étaient identiques à la base = même contenu fautif).
            if (
                identical_at_base
                and base_self_moji
                and not head_self_moji
                and has_mojibake(head_other)
            ):
                errors.append(
                    f"{path} : correctif d'encodage appliqué sans propager à la copie "
                    f"sœur {other_path} (byte-identique à la base, mojibake encore présent) — "
                    "propager le correctif ou supprimer la duplication (chantier #2661, issue #7091)"
                )
            elif (
                identical_at_base
                and head_other is not None
                and head_self != head_other
                and not has_mojibake(head_other)
                and not (has_mojibake(head_self))
            ):
                warnings.append(
                    f"{path} : copies byte-identiques à la base désormais divergentes de "
                    f"{other_path} — propager le changement ou supprimer la duplication (#2661)"
                )

for w in warnings:
    print(f"::warning::check-mobile-duplicated-drift : {w}")
if errors:
    for e in errors:
        print(f"::error::check-mobile-duplicated-drift : {e}")
    print(f"::error::check-mobile-duplicated-drift : {len(errors)} correctif(s) d'encodage non propagé(s) aux copies dupliquées (issue #7091).")
    sys.exit(1)

print(f"check-mobile-duplicated-drift : OK — {len(warnings)} avertissement(s), aucune copie dupliquée divergente sur le mojibake.")
PYEOF
