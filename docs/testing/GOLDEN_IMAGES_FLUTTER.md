# Golden tests d'images Flutter (widgets) — méthodologie

> ⚠️ **Ne pas confondre** avec `docs/testing/GOLDEN_TESTS.md`, qui couvre les golden tests
> **backend** (invariants de calcul Payroll/Accounting). Le présent document couvre les
> **golden tests d'images** des widgets Flutter (rendu pixel) — protocole P05 §5, temps **T2**
> (issue #7105).

## 1. Objet

Verrouiller le rendu visuel des widgets **partagés** de `leopardo_core` pour qu'une modification
de thème ou de widget ne casse pas silencieusement l'apparence des 7 apps qui les consomment.
Complément des gardes de tokens (couleurs) : un golden capture le **résultat composite**
(typo + espacements + ombres + icônes), pas seulement les valeurs déclarées.

## 2. Quand utiliser un golden (et quand ne pas en faire)

| Oui | Non |
|---|---|
| Widget partagé du core (`leopardo_core/lib/core/widgets/**`) à rendu stable | Pages entières avec données dynamiques (dates, i18n, réseau) |
| Changement de thème/tokens impactant un widget visuel | Écrans tenant-contextuels (branding tenant par client) |
| Composant réutilisé par ≥ 2 apps | Tests de comportement (ceux-ci restent des widget tests classiques) |

## 3. Structure recommandée

```
front/mobile_apps/leopardo_core/test/goldens/
  golden_glass_card_test.dart     # fichier de test = 1 widget par golden
  golden_leopardo_badge_test.dart
  references/
    glass_card_default.png        # généré sur la plateforme canonique (CI ubuntu)
    leopardo_badge_default.png
```

- Un golden par **état visuel distinct** (par défaut ; dark mode si le widget le gère).
- Données **figées** dans le test (texte court fixe, pas d'horloge, pas de locale aléatoire).

## 4. Écrire le test

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';

void main() {
  testWidgets('GlassCard — rendu par défaut stable', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: GlassCard(child: Text('Test')))),
    );
    await expectLater(
      find.byType(GlassCard),
      matchesGoldenFile('references/glass_card_default.png'),
    );
  });
}
```

## 5. Générer et mettre à jour les références

```bash
cd front/mobile_apps/leopardo_core
flutter test test/goldens --update-goldens   # génère / met à jour les .png
```

- La mise à jour d'un golden est **volontaire et revue** : le diff d'image est affiché dans la PR
  (capture avant/après) — jamais de `--update-goldens` « pour faire passer la CI ».
- Les références sont commitées ; si le repo active Git LFS pour les images (#4124), ajouter
  `test/goldens/references/**/*.png` aux fichiers LFS.

## 6. Plateformes & stabilité CI

- Le rendu des textes dépend de la plateforme → choisir **une plateforme canonique** (runner
  ubuntu de la CI) : les références sont générées et comparées sur cette plateforme uniquement.
- Variantes OS (macOS/Windows) : suffixer le nom (`glass_card_default.macos.png`) seulement si un
  rendu différent est attendu et testé sur ce runner.
- Zones dynamiques (heure, curseur, animations) : masquer via un widget de test dédié ou figer
  les données — jamais de tolérance globale flottante (elle vide le test de son sens).

## 7. Intégration CI (temps T2 — issue #7105)

- Job dédié dans le workflow mobile (`mobile-apps-ci.yml` ou équivalent) exécutant
  `melos run test --no-select` restreint à `leopardo_core`, ou `flutter test test/goldens` dans le package.
- La CI compare **sans** `--update-goldens` : toute différence non commitée fait échouer le job.
- DoD : une PR touchant un widget du core avec impact visuel doit fournir ou mettre à jour son golden.

## 8. Liens

- Protocole : `docs/PROTOCOLES/P05_DESIGN_HARMONISE.md` §5 (plan T1-T3, règle 6 : écart → issue `design`)
- Golden backend (ne pas confondre) : `docs/testing/GOLDEN_TESTS.md`
- Suivi d'implémentation : issue #7105 (T2)
