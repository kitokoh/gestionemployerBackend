# Product context

> Mise à jour : 2026-09-09 — liste des surfaces alignée sur le dépôt (7 apps Flutter + core).

## Vision

Leopardo HR est un **Mobile-First Company OS** pour PME terrain. Le produit relie employes, managers/RH et administrateurs plateforme autour des operations quotidiennes:

- presence et pointage;
- horaires, sites, taches;
- absences, avances, paie et documents;
- notifications et communication;
- onboarding, QR, dossiers employes;
- pilotage client et readiness;
- API/OpenAPI pour ecosysteme.

## Surfaces produit

Apps mobiles Flutter (dans `front/mobile_apps/`, packages Melos) :

- `leopardo_employee`: app employe.
- `leopardo_manager`: app manager/RH.
- `leopardo_hr`: app RH dediee (manager/RH, cf. `front/mobile_apps/leopardo_hr/pubspec.yaml`).
- `leopardo_platform_admin`: app super-admin plateforme.
- `leopardo_marketing`: app marketing (BC-12 GROWTH).
- `leopardo_accounting`: app comptabilite (BC-08 ACCOUNTING).
- `leopardo_travel_agent`: app agence de voyage (BC-24 TRAVEL).
- `leopardo_core`: code partage mobile (package Flutter, pas une app autonome), consomme par les 7 apps ci-dessus. Toute nouvelle app doit en dependre (convergence F-27, zero copie locale).

Surfaces web et autres :

- `front/web`: vitrine/portail client web (Next.js).
- `front/admin-dashboard`: dashboard plateforme web (Vue).
- `front/zkteco-kiosk`: kiosque terrain ZKTeco (JS/Python — **pas Flutter**).
- `front/web-offline`: PWA offline.
- `edge`: service edge-sync.
- `api`: backend Laravel (mono-repo racine).

> Le kiosk ZKTeco et l'edge ne sont pas des apps Flutter ; ne pas les confondre avec les packages Melos.

## Personas

- Employe terrain: veut pointer, consulter documents, demander absence/avance.
- Manager/RH: veut voir equipe, valider, corriger, planifier.
- Dirigeant: veut visibilite, couts, alertes, conformite.
- Super-admin plateforme: veut creer clients, plans, modules, health.
- Partenaire: veut API, docs, webhooks, SDK.

## Promesse

Rendre l'entreprise visible et actionnable depuis le mobile, sans Excel ni processus disperses.

