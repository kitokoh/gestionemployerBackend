#!/usr/bin/env python3
"""
check-markdown-links.py — Vérificateur de liens markdown internes (issue #7124)

Scanne les fichiers .md du dépôt, résout chaque lien relatif interne
(`[txt](chemin)`, `[txt](chemin#ancre)`) contre l'arbre réel :

  - fichier introuvable -> ERREUR (exit 1)
  - ancre introuvable   -> AVERTISSEMENT (non bloquant en V1 : tolère les
    ancres HTML `<a name>` et la casse)

Usage :
  python3 dev-hub/tools/check-markdown-links.py [--root DIR] [--only DIR|FILE]

Sortie : 0 = OK, 1 = au moins un fichier cassé.
"""
import argparse, os, posixpath, re, sys

LINK_RE = re.compile(r"\[([^\]]*)\]\(([^)]*)\)")
EXT = ".md"

def slugify(heading: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", heading.strip().lower()).strip("-")

def collect_anchors(path: str) -> set:
    anchors = set()
    try:
        lines = open(path, encoding="utf-8", errors="ignore").read().splitlines()
    except OSError:
        return anchors
    for ln in lines:
        m = re.match(r"^#{1,6}\s+(.*)", ln)
        if m:
            anchors.add(slugify(m.group(1)))
        m = re.search(r'<a\s+name="([^"]+)"', ln)
        if m:
            anchors.add(m.group(1).lower())
    return anchors

def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--only", default=None, help="chemin relatif (fichier ou dossier) à vérifier seul")
    args = ap.parse_args()
    root = os.path.abspath(args.root)

    files = []
    if args.only:
        p = os.path.join(root, args.only)
        if os.path.isdir(p):
            for r, _, fs in os.walk(p):
                files += [os.path.join(r, f) for f in fs if f.endswith(EXT)]
        elif p.endswith(EXT):
            files = [p]
    else:
        for r, dirs, fs in os.walk(root):
            dirs[:] = [d for d in dirs if d not in ("node_modules", ".git", "vendor", "build",
                                                    ".dart_tool", ".specify", ".github", "dist")]
            files += [os.path.join(r, f) for f in fs if f.endswith(EXT)]

    errors, warns, checked = [], [], 0
    for path in sorted(files):
        rel = os.path.relpath(path, root)
        filedir = os.path.dirname(rel)
        for i, ln in enumerate(open(path, encoding="utf-8", errors="ignore"), 1):
            for m in LINK_RE.finditer(ln):
                t = m.group(2).strip()
                if not t or t.startswith(("http://", "https://", "mailto:", "tel:")):
                    continue
                target, _, frag = t.partition("#")
                if not target:
                    continue
                c1 = posixpath.normpath(posixpath.join(filedir, target))
                c2 = posixpath.normpath(target)
                real = None
                for c in (c1, c2):
                    if os.path.exists(os.path.join(root, c)):
                        real = c
                        break
                checked += 1
                if real is None:
                    errors.append(f"{rel}:{i} -> fichier introuvable : {t}")
                elif frag:
                    anchors = collect_anchors(os.path.join(root, real))
                    if slugify(frag) not in anchors and frag.lower() not in anchors:
                        warns.append(f"{rel}:{i} -> ancre non résolue : {t}")

    for e in errors:
        print(f"❌ {e}")
    for w in warns:
        print(f"⚠️  {w}")
    print(f"{len(files)} fichiers .md | {checked} liens internes | {len(errors)} erreur(s), {len(warns)} avertissement(s)")
    return 1 if errors else 0

if __name__ == "__main__":
    sys.exit(main())
