# Audit — Vues réelles des verticales : pilote Restaurant (issue #6920)

> Audit 2026-09-08 (PM/ARCHI, branche `fix/6920-restaurant-vertical-audit`, Part of #6920).
> Objet : étape 1 du pilote BC-25 — **inventaire des routes API publiques Restaurant**
> + **choix d'architecture** (app autonome par verticale vs pages du web central),
> préalable à la spécification courte puis à l'implémentation.
> Registre des sous-domaines : `docs/ops/DOMAINS.md` § « Surfaces verticales » (#6918).

## 1. État constaté (2026-09-08, main)

### 1.1 API publique par verticale — déjà en place (contrats RESTO/TRAVEL)

Les verticales exposent déjà des **boutiques publiques sans auth utilisateur**, sous
`/api/v1/public/**`, derrière un **jeton signé par tenant** (middlewares dédiés) :

| Surface | Routes (noms) | Middleware | Réf |
|---|---|---|---|
| Restaurant — boutique | `GET /public/restaurant/shop/menu` (`restaurant.public.menu`) ; `POST …/orders` (`.orders.store`) ; `GET …/orders/{reference}` (`.track`) ; `POST …/orders/{reference}/pay` (`.orders.pay`) | `throttle:shop-public` + `restaurant.public.shop` | RESTO-805 #6226 |
| Restaurant — kiosque | `GET /public/restaurant/kiosk/menu` ; `POST …/orders` ; `GET …/orders/{reference}` | idem (même jeton boutique) | RESTO-807 #6228 |
| Travel — boutique | `GET /public/travel/shop/trips[/{travelTrip}]` ; `POST …/bookings` ; `GET …/bookings/{reference}` ; `POST /public/travel/payments/initiate` ; `GET /public/travel/tickets/{ticket}/pdf` | `throttle` + `public.shop` (pattern TRAVEL-1001 #6114) | #6114 |
| ATS — portail carrières | `GET /public/careers/{companySlug}` (+ `feed.xml`, offres, candidature) | `throttle:public-careers` — **tenant résolu par slug**, sans jeton | #5xxx ATS |

**Mécanique du jeton boutique** (`EnsureRestaurantPublicShopAccess`, identique au pattern
Travel) : en-tête `X-Restaurant-Shop-Token`, hash **SHA-256** en base
(`restaurant_public_shop_tokens.token_hash`, `active`, `last_used_at`), résolution du
tenant (`current_company` + `tenant_scope_required`) → le scope global `BelongsToCompany`
s'applique, **aucune fuite cross-tenant (fail-closed 401/403)**. Hook anti-bot CAPTCHA
optionnel (`X-Captcha-Token`) si `restaurantmanager.public_shop.captcha_secret` est posé.

### 1.2 Surfaces web

- **Aucune app verticale autonome** : les sous-domaines Vercel (`leopardo-resto.vercel.app`
  dev/prod) servent des **pages de validation statiques** (#6918).
- Le **web central** (`front/web`, Next.js App Router) héberge les pages tenant
  authentifiées (`(dashboard)/restaurant/**` : POS, cuisine, stock, livraison…) et
  quelques pages **publiques slug** : `[companySlug]/careers/**` (portail ATS public —
  SSR) et `(landing)/careers`. Il existe aussi `shop`, `order`, `kiosk` dans `front/web/src/app`
  (surfaces publiques à confirmer/cartographier dans la spec).

### 1.3 Personnalisation tenant (branding public)

- **Écart** : aucune API publique de **profil/identité tenant** (nom, logo, couleurs,
  horaires, contact) résolue par slug ou par jeton. Les endpoints existants sont
  authentifiés (tenant manager). Le rendu public autonome (exigence #6920 point 2)
  nécessite cette API — à créer côté backend (périmètre spec), en réutilisant la
  résolution par slug du portail ATS.

## 2. Analyse — app autonome par verticale vs pages du web central

| Critère | A. App Next.js autonome (`front/vertical-restaurant/`) | B. Pages du web central sous le sous-domaine |
|---|---|---|
| Isolation/dette | Forte : 1 app = 1 verticale, pas de couplage au bundle central | Faible : grossit le bundle central, risque de régressions croisées |
| Déploiement | Indépendant (projet Vercel dédié = le sous-domaine #6918 existe déjà) | Couplé au déploiement du web central |
| SSR/SEO public | Natif App Router, propre au domaine | Possible mais mêlé aux routes du hub |
| Partage design-system | Consomme `front/web` ? non — doit embarquer les tokens (design-system) | Réutilise directement les composants |
| Coût initial | Nouveau projet (setup, CI, i18n) | Faible (pages nouvelles) |
| Scaling verticales suivantes | Template réutilisable Travel/Fuel/Edu/Delivery (#6920 point 4) | Chaque verticale ajoute des pages au hub |

**Recommandation** : **Option A — app autonome par verticale**, cohérente avec la
décision #6918 (1 verticale = 1 projet Vercel = 1 sous-domaine) et l'exigence de
déploiement indépendant. Le **web central garde l'espace tenant** (aperçu + lien) —
point 3 de #6920. La page publique consommée depuis la plateforme (iframe/lien)
pointe vers l'app verticale.

Précédents internes à réutiliser :
- résolution tenant public par **slug** (`[companySlug]/careers` du web central ;
  `public/careers/{companySlug}` côté API) ;
- **jeton boutique** si la surface doit restreindre (paiement/commandes) — le menu
  public peut être libre (slug), les écritures (commande/paiement) derrière le jeton.

## 3. Écarts à couvrir par la spécification courte (prochaine étape)

1. **API publique profil tenant** (`GET /api/v1/public/companies/{slug}/profile` —
   nom, logo, couleurs (design tokens), horaires, contact, statut boutique) : 0 donnée
   interne/cross-tenant (pattern portail ATS) ;
2. Choix définitif du rendu du menu : **publique par slug** (lecture) en v1 — le jeton
   boutique reste requis pour **écritures** (commande/paiement) ;
3. Nouvelle app `front/vertical-restaurant/` (Next.js App Router, SSR) : page publique
   par établissement (slug), personnalisation tenant, lien fiche ; déployée sur
   `leopardo-resto.vercel.app` (dev) / `-prod` (registre #6918) ;
4. **CORS/SANCTUM_STATEFUL_DOMAINS** : raccordement via le registre #6918 quand l'app
   réelle consomme l'API publique (écritures) ;
5. Espace tenant dans le web central : aperçu personnalisé + lien vers le sous-domaine ;
6. Template réutilisable pour Travel/Fuel/Edu/Delivery (même structure).

## 4. Hors périmètre v1 (rappel #6920)

Éditeur de contenu complet (BC-27), paiement en ligne complet, catalogue B2B (BC-28).

---

*Sources : `api/routes/api.php` (groupes publics 157-227), `api/routes/modules/restaurantmanager.php:335-361`,
`api/app/Http/Middleware/Restaurant/EnsureRestaurantPublicShopAccess.php`,
`api/app/Modules/RestaurantManager/Domain/Models/RestaurantPublicShopToken.php`,
`front/web/src/app/[companySlug]/careers`, `docs/ops/DOMAINS.md` (§ Surfaces verticales).*
