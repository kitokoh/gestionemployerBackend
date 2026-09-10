<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

/**
 * Registre des 3 thèmes vitrine v1 (BC-27 SHOWCASE, V-THEMES #6868).
 *
 * Un thème = un jeu de variables de marque (couleurs, typographie) appliqué
 * au rendu public ; les sections restent identiques (même contenu, parcours
 * visuel différent). Les variables sont surchargeables par tenant dans
 * `company_showcases.settings` (allowlist exposée publiquement :
 * `colors`, `brand_name`, `tagline` — cf. VitrinePublicResource).
 *
 * Les valeurs par défaut sont alignées sur les tokens de la charte
 * (`docs/REFERENTIEL_PRODUIT/COULEURS.md`) : aucune couleur hors palette.
 * Le rendu (CSS variables côté vitrine) consomme `variables()`.
 */
final class ShowcaseThemeRegistry
{
    public const DEFAULT_THEME = 'industrie';

    /**
     * Thèmes v1 : identifiant => métadonnées + variables de marque par défaut.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function themes(): array
    {
        return [
            'industrie' => [
                'label' => 'Industrie',
                'description' => 'Sobre et dense — adapté aux usines, BTP et logistique.',
                'variables' => [
                    'primary' => '#0F766E',
                    'accent' => '#0D9488',
                    'surface' => '#F8FAFC',
                    'on_primary' => '#FFFFFF',
                    'font_family' => 'system-ui, sans-serif',
                    'radius' => '0.25rem',
                ],
            ],
            'service' => [
                'label' => 'Service',
                'description' => 'Aéré et lumineux — adapté aux services, conseil et santé.',
                'variables' => [
                    'primary' => '#1D4ED8',
                    'accent' => '#3B82F6',
                    'surface' => '#FFFFFF',
                    'on_primary' => '#FFFFFF',
                    'font_family' => 'system-ui, sans-serif',
                    'radius' => '0.75rem',
                ],
            ],
            'commerce' => [
                'label' => 'Commerce',
                'description' => 'Chaleureux et contrasté — adapté au commerce et à la distribution.',
                'variables' => [
                    'primary' => '#B45309',
                    'accent' => '#F59E0B',
                    'surface' => '#FFFBEB',
                    'on_primary' => '#FFFFFF',
                    'font_family' => 'system-ui, sans-serif',
                    'radius' => '0.5rem',
                ],
            ],
        ];
    }

    /**
     * Identifiants des thèmes v1 (validation d'entrée).
     *
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::themes());
    }

    public static function exists(string $theme): bool
    {
        return array_key_exists($theme, self::themes());
    }

    /**
     * Variables par défaut d'un thème (vide si thème inconnu).
     *
     * @return array<string, string>
     */
    public static function variables(string $theme): array
    {
        $themeConfig = self::themes()[$theme] ?? null;

        if ($themeConfig === null) {
            return [];
        }

        /** @var array<string, string> $variables */
        $variables = $themeConfig['variables'];

        return $variables;
    }

    /**
     * Variables effectives : défauts du thème, surchargés par les variables
     * scalaires du tenant (allowlist `colors.*`, `font_family`, `radius`).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public static function resolvedVariables(string $theme, array $settings): array
    {
        $variables = self::variables($theme);

        $colors = $settings['colors'] ?? null;
        if (is_array($colors)) {
            foreach (['primary', 'accent', 'surface', 'on_primary'] as $key) {
                if (isset($colors[$key]) && is_string($colors[$key])) {
                    $variables[$key] = $colors[$key];
                }
            }
        }

        foreach (['font_family', 'radius'] as $key) {
            if (isset($settings[$key]) && is_string($settings[$key])) {
                $variables[$key] = $settings[$key];
            }
        }

        return $variables;
    }
}
