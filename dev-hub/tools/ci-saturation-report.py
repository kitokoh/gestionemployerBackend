#!/usr/bin/env python3
"""
ci-saturation-report.py — Rapport de saturation CI GitHub Actions.

Issue #7090 (leçon L-12 : saturation CI récurrente, triage 2026-09-09). Mesure
l'état de la file d'exécution du dépôt : runs `queued` et `in_progress` avec leur
âge, par workflow et par branche, pour objectiver les goulots (runners, jobs
coincés, pics d'agents parallèles).

Usage :
  GITHUB_TOKEN=<token> python3 dev-hub/tools/ci-saturation-report.py [--repo kitokoh/leopardo-hr]
    [--min-age 10] [--json out.json]
Sortie : rapport markdown sur stdout. Sortie 0 (outil de mesure, non bloquant).
"""

import argparse
import json
import os
import time
import urllib.request

API = "https://api.github.com"


def get(url, token):
    req = urllib.request.Request(url)
    req.add_header("Authorization", "Bearer " + token)
    req.add_header("Accept", "application/vnd.github+json")
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read().decode())


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--repo", default="kitokoh/leopardo-hr")
    ap.add_argument("--min-age", type=int, default=10, help="âge minimal (min) pour signaler un run en attente")
    ap.add_argument("--json", default=None, help="écrire le détail brut dans ce fichier")
    args = ap.parse_args()
    token = os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN")
    if not token:
        print("::error::GITHUB_TOKEN requis")
        return 2

    now = time.time()
    runs = []
    for page in (1, 2):
        url = f"{API}/repos/{args.repo}/actions/runs?per_page=100&page={page}"
        batch = get(url, token).get("workflow_runs", [])
        runs += batch
        if len(batch) < 100:
            break

    def age_min(ts):
        return max(0, (now - time.mktime(time.strptime(ts[:19], "%Y-%m-%dT%H:%M:%S"))) / 60)

    queued = [r for r in runs if r["status"] == "queued"]
    active = [r for r in runs if r["status"] == "in_progress"]
    stalled = [r for r in queued if age_min(r["created_at"]) >= args.min_age]

    print(f"# Rapport saturation CI — {args.repo} ({time.strftime('%Y-%m-%d %H:%M UTC', time.gmtime())})\n")
    print(f"- Runs scrutés : {len(runs)}")
    print(f"- **Queued : {len(queued)}** | In progress : {len(active)}")
    print(f"- Queued depuis ≥ {args.min_age} min (signal de saturation) : **{len(stalled)}**\n")

    print("## Top workflows en attente\n")
    wf = {}
    for r in stalled:
        wf[r["name"]] = wf.get(r["name"], 0) + 1
    for name, n in sorted(wf.items(), key=lambda x: -x[1])[:10]:
        print(f"- {name} : {n} run(s) en attente")

    print("\n## Runs en attente (âge décroissant)\n")
    for r in sorted(stalled, key=lambda x: -age_min(x["created_at"]))[:20]:
        print(f"- [{int(age_min(r['created_at']))} min] {r['name']} — branche `{r['head_branch']}` ({r['id']})")

    print("\n## Pistes (issue #7090 / leçon L-12)")
    print("1. Job `in_progress` anormalement long → vérifier/annuler (run bloquant la file).")
    print("2. Vague de branches parallèles → lots BC + quotas de merge.")
    print("3. Checks requis jamais démarrés → vérifier concurrency/triggers (L-11).")

    if args.json:
        with open(args.json, "w") as fh:
            json.dump({"queued": queued, "active": active, "now_epoch": now}, fh, indent=1)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
