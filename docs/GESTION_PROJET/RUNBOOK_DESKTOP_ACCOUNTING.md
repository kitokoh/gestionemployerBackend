# RUNBOOK — Pilote desktop Leopardo Accounting (Windows / macOS)

> **Statut :** pilote interne — canal fermé (protocole P06, décision #7055 GO, pipeline #7056).
> **Version :** 0.1 — 2026-09-09
> **Portée :** installation, lancement, vérifications et désinstallation du client desktop
> `leopardo_accounting` sur postes pilotes Windows 10/11 et macOS 13+.
> **⚠️ Aucun installateur public :** ce guide s'adresse aux pilotes nommés. Rien ici n'autorise
> une mise à disposition publique (vitrine `/download` = demande d'accès pilote, #3257).

---

## 1. Prérequis

| Élément | Windows | macOS |
|---|---|---|
| OS | Windows 10 22H2+ / 11 | macOS 13 Ventura+ (Apple Silicon ou Intel) |
| Compte pilote | Compte local avec droits d'installation | Compte avec droits admin (1ʳᵉ ouverture) |
| Accès réseau | API du volet ciblé (voir §3) | idem |
| Artefact | `.exe` du build CI (`desktop-distribute.yml`) | `.app` du build CI |

> Le build est **non signé / non notarié** en phase pilote : Windows affichera SmartScreen
> (« Plus d'informations » → « Exécuter quand même ») ; macOS affichera Gatekeeper
> (clic droit → « Ouvrir » → confirmer). C'est attendu — ne pas désactiver ces protections
> à l'échelle de la machine.

## 2. Récupération de l'artefact (responsable pilote)

1. Déclencher le build : GitHub Actions → workflow **Desktop - Build & Distribute (pilote)** →
   `Run workflow` → app `leopardo_accounting`, plateforme `windows` et/ou `macos`,
   environnement `dev` (ou `pilot` si un volet pilote dédié existe).
2. Récupérer l'artefact `leopardo-desktop-leopardo_accounting-<os>-<run>` (onglet Summary du run).
3. Vérifier l'empreinte : comparer le SHA-256 de l'artefact reçu avec celui affiché dans le run
   (toute divergence = ne pas installer, signaler immédiatement).

## 3. Configuration du volet API (avant première installation)

- Le build embarque l'URL d'API via `--dart-define=API_BASE_URL` (garde #4524 : jamais d'URL vide).
- Volet par défaut du pilote : API de continuité `https://gestionemployerbackend.onrender.com/api/v1`
  (topologie dev, registre `docs/ops/DOMAINS.md`). Si le pilote cible le volet prod tagué
  (`https://leopardo-prod.onrender.com/api/v1`), utiliser l'input `api_url` du workflow — décision
  tracée sur l'issue pilote (jamais de données réelles de prod sur un poste non encadré).
- Comptes de test : creds du pilote fournies hors canal (jamais dans ce fichier, jamais en clair
  dans une issue — constitution §V).

## 4. Installation

### Windows
1. Dézipper l'artefact dans `C:\Program Files\Leopardo\` (ou dossier utilisateur si pas admin).
2. Lancer `leopardo_accounting.exe`.
3. SmartScreen éventuel → « Plus d'informations » → « Exécuter quand même » (build non signé).
4. Créer un raccourci bureau (optionnel).

### macOS
1. Copier `Leopardo Accounting.app` dans `/Applications/`.
2. Première ouverture : clic droit sur l'app → « Ouvrir » → confirmer (Gatekeeper, build non notarié).
3. Si le système bloque quand même : `xattr -dr com.apple.quarantine "/Applications/Leopardo Accounting.app"`
   (commande admin, à documenter sur l'issue pilote — alternative à ne pas généraliser).

## 5. Vérifications post-installation (smoke pilote — à cocher et dater)

1. L'application démarre, fenêtre titrée **Leopardo Accounting**, pas d'écran noir > 6 s
   (StartupGate, anti page noire).
2. Login comptable : succès contre le volet API choisi (§3) ; échec réseau → message clair, pas de crash.
3. Parcours critique : liste des documents, fiche document, écran impayés — données cohérentes avec le web.
4. **Offline** : couper le réseau → l'app reste utilisable en lecture (documents récents consultables) ;
   au retour réseau, les actions redeviennent disponibles sans redémarrage (pattern sync existant).
5. Fermeture propre : quitter l'app → aucun processus `leopardo_accounting` résiduel (vérifier
   Gestionnaire des tâches / Activité).
6. Rouvrir l'app : session restaurée (token secure storage — DPAPI Windows / Keychain macOS).

## 6. Désinstallation

- **Windows** : fermer l'app, supprimer le dossier d'installation + le raccourci. Résidus éventuels
  de cache Flutter sous `%APPDATA%\com.leopardo.accounting` (identifiant à confirmer au build signé).
- **macOS** : glisser l'app à la Corbeille ; résidus sous `~/Library/Application Support/` (même
  identifiant). Le token de session vit dans le trousseau : le supprimer via « Trousseau d'accès »
  si l'on veut une déconnexion totale du poste.

## 7. Problèmes connus & parades

| Symptôme | Cause probable | Parade |
|---|---|---|
| Écran noir au démarrage | Init bloquante avant premier frame | Redémarrer ; si persiste, signaler (quick card : jamais d'await avant runApp) |
| « Windows a protégé votre PC » | Build non signé | SmartScreen → Exécuter quand même (pilote uniquement) |
| Login OK sur poste A, refusé sur poste B (Windows) | Token lié au compte Windows (DPAPI) | Se reconnecter sur B — normal |
| Données offline absentes | Jamais synchronisées avant la coupure | Se connecter une fois en ligne avant de tester l'offline |
| API injoignable | Mauvais volet embarqué au build | Rebuild avec le bon `api_url` ; vérifier le registre DOMAINS.md |
| Clavier/date locale | Préférence langue de l'app (FR/EN/TR/AR) | Réglage dans l'app, pas celui de l'OS |

## 8. Limites du pilote (opposables — ne pas les franchir)

- **Non signé / non notarié** : distribution publique interdite (stores, site, liens directs).
- Pas d'auto-update : chaque nouveau build passe par le workflow CI.
- Pas de push, GPS, biométrie, Google Sign-In sur desktop (profil desktop, pattern #3932).
- Le pilote n'implémente **pas** de fonctionnalité desktop spécifique : il valide build,
  installation, login et parcours comptable existants sur poste fixe.

## 9. Retour pilote (obligatoire)

Chaque pilote renseigne : OS + version, build (run id), ce qui a fonctionné, ce qui a échoué,
le temps passé — via le canal convenu (issue de suivi pilote). Les enseignements alimentent la
revue mensuelle (P04 §6 / P06 §8) et la décision d'extension aux autres BC.

---

**Historique :** v0.1 (2026-09-09) — création, pilote interne non signé (protocole P06).
