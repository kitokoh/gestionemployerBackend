<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Enums;

/**
 * Thèmes v1 de la vitrine (BC-27 SHOWCASE, #6868 V-THEMES).
 *
 * Un thème = jeu de templates de sections + variables de présentation
 * (palette, typo, arrondi) ; le contenu des sections reste identique — il est
 * rendu différemment par le moteur de thèmes (`ShowcaseThemeRegistry` +
 * `ShowcaseThemeRenderer`). Chaque thème est défini à partir des design tokens
 * produit (`docs/REFERENTIEL_PRODUIT/COULEURS.md`).
 *
 * La colonne `theme` de `company_showcases` est une string (défaut historique
 * `default` = rendu neutre de repli, cf. `showcase.vitrine`). Les 3 thèmes v1
 * ci-dessous sont sélectionnables via `PATCH /showcase`.
 */
enum ShowcaseTheme: string
{
    case Industrie = 'industrie';
    case Service = 'service';
    case Commerce = 'commerce';

    /**
     * Thème v1 par défaut (repli de rendu si le thème stocké est inconnu).
     */
    public static function default(): self
    {
        return self::Industrie;
    }

    /**
     * Valeurs des thèmes v1 (ordre canonique) — allowlist de l'API.
     *
     * @return list<string>
     */
    public static function v1(): array
    {
        return array_map(static fn (self $theme): string => $theme->value, self::cases());
    }

    /**
     * Clé i18n du libellé du thème (UI d'administration).
     */
    public function labelKey(): string
    {
        return match ($this) {
            self::Industrie => 'showcase.theme_industrie',
            self::Service => 'showcase.theme_service',
            self::Commerce => 'showcase.theme_commerce',
        };
    }

    public static function tryFromOrNull(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
