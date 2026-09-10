# Runbook — Rotation des jetons d'accès (issue #7065)

| | |
|---|---|
| **Statut** | Runbook opérationnel — **la rotation elle-même reste à exécuter par le PM** (actions sur les comptes) |
| **Date** | 2026-09-10 |
| **Contexte** | Jetons (GitHub, Vercel, Render, Cloudflare) transmis en clair dans une conversation le 2026-09-09 → réputés compromis (issue #7065, P1 sécurité) |
| **Objectif** | Révoquer/remplacer les jetons exposés, vérifier l'absence d'usage illégitime, puis empêcher la récidive |

> ⚠️ Ce document **ne contient aucun secret**. Ne jamais coller un jeton dans un ticket, un chat,
> un commit ou un fichier non chiffré.

---

## 1. Inventaire à traiter

| Fournisseur | Type | Environnements | Où le créer/révoquer |
|---|---|---|---|
| GitHub | PAT (fine-grained recommandé) | — | Settings → Developer settings → Personal access tokens |
| Vercel | Token d'équipe (account) + token projet | prod (+ dev) | Settings → Tokens |
| Render | API Key | prod (+ dev) | Account Settings → API Keys |
| Cloudflare | API Token (scoped) | prod (account X) + dev (account Y) | My Profile → API Tokens |
| Mailgun | (à fournir « plus tard ») | — | À intégrer directement en secret, jamais en clair |

## 2. Procédure de rotation (par fournisseur)

### 2.1 GitHub
1. Identifier le PAT concerné (nom + dernière utilisation) : *Settings → Developer settings → Tokens*.
2. Créer un **nouveau PAT fine-grained** avec le minimum de scopes nécessaires à l'agent/CI
   (contents, pull-requests, issues, actions — pas d'`admin`).
3. Mettre à jour les consommateurs : secrets GitHub Actions (`GITHUB_TOKEN` est fourni par la CI,
   pas besoin de PAT), scripts locaux, coffre d'équipe.
4. **Révoquer l'ancien PAT** (bouton Revoke) immédiatement après bascule vérifiée.
5. Vérifier : `gh auth status` + un `git fetch` authentifié.

### 2.2 Vercel
1. Créer un nouveau token (scope minimal : projet concerné si possible).
2. Mettre à jour : secrets CI/CD (déploiement vitrine), variable locale d'agent.
3. Révoquer l'ancien token.
4. Vérifier : un déploiement de préversion réussi.

### 2.3 Render
1. Créer une nouvelle API key.
2. Mettre à jour les secrets CI (`RENDER_API_KEY`) et le déploiement manuel éventuel.
3. Révoquer l'ancienne clé.
4. Vérifier : un `GET /v1/services` répond et le service prod est bien listé.

### 2.4 Cloudflare
1. Créer un token **scoped** (Pages: Edit sur le compte prod uniquement).
2. Mettre à jour les secrets CI (`CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`).
3. Révoquer l'ancien token.
4. Vérifier : un déploiement Pages de préversion.

### 2.5 Vérification d'usage illégitime (à faire AVANT révocation)
- GitHub : `Settings → Security log` (événements `token_access`, IP inconnues) ;
- Vercel / Render / Cloudflare : journal d'audit des tokens (dernière utilisation, IP) ;
- Alerter le PM si un usage non reconnu apparaît (→ traiter comme incident, cf. `docs/ops/INCIDENTS.md`).

## 3. Checklist de clôture (issue #7065)

- [ ] Nouveaux jetons créés avec scopes minimaux ;
- [ ] Consommateurs basculés (CI/CD + agents + coffre) ;
- [ ] Anciens jetons **révoqués** ;
- [ ] Journaux d'audit vérifiés (aucun usage inconnu) ;
- [ ] Secrets stockés hors repo (coffre / secrets CI), jamais en clair ;
- [ ] Une seule copie opérationnelle par secret (pas de jeton partagé entre dev et prod) ;
- [ ] Mailgun : à intégrer directement en secret lors de sa mise à disposition.

## 4. Prévention (récidive)

1. **Règle d'or** : un secret ne se transmet jamais par chat/ticket — seulement par coffre ou secret CI.
2. Les jetons d'agent sont **jetsables** : durée de vie courte + scope minimal + rotation planifiée.
3. Interdire les PAT `admin` pour les agents ; privilégier fine-grained.
4. Rappel dans le protocole P02 (onboarding) : la checklist d'accès mentionne le canal sécurisé.
5. Audit trimestriel des jetons actifs (à inscrire à la revue mensuelle P07).

## 5. Références

- Issue #7065 (rotation des jetons exposés le 2026-09-09) ;
- `docs/ops/INCIDENTS.md`, `docs/ops/DOMAINS.md`, `docs/CI_CD_SECRETS.md` (inventaire des secrets CI) ;
- Protocoles P02 (accès onboarding), P07 (gouvernance infra).
