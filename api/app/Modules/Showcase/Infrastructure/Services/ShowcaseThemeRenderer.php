<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use Illuminate\Support\Facades\View;

/**
 * Moteur de rendu des thèmes de vitrine (BC-27 SHOWCASE, #6868 V-THEMES).
 *
 * Le contenu (sections validées par schéma) est séparé de la présentation :
 * le thème choisi détermine la PAGE Blade et le template par TYPE de section
 * (`showcase/themes/{theme}/…`). Un thème inconnu (dont l'historique
 * `default`) retombe sur le rendu neutre `showcase.vitrine` ; un type de
 * section non couvert par un thème retombe sur le template du thème v1 par
 * défaut — jamais d'erreur de template (repli propre).
 *
 * Aucune donnée interne n'est manipulée ici : le renderer ne fait que résoudre
 * des noms de vues et calculer les variables de présentation (allowlist
 * {@see ShowcaseThemeRegistry}).
 */
final class ShowcaseThemeRenderer
{
    /**
     * Vue de page d'un thème, avec repli neutre.
     */
    public function pageView(string $theme): string
    {
        $candidate = 'showcase.themes.'.$theme.'.page';

        if (ShowcaseThemeRegistry::isKnown($theme) && View::exists($candidate)) {
            return $candidate;
        }

        return 'showcase.vitrine';
    }

    /**
     * Vue de section par type, avec repli sur le thème v1 par défaut.
     *
     * @param  list<string>  $types
     * @return array<string, string>
     */
    public function sectionViews(string $theme, array $types): array
    {
        $resolvedTheme = ShowcaseThemeRegistry::isKnown($theme) ? $theme : ShowcaseThemeRegistry::DEFAULT;

        $views = [];

        foreach ($types as $type) {
            if (! ShowcaseSectionSchemaRegistry::isKnownType($type)) {
                continue;
            }

            $candidate = 'showcase.themes.'.$resolvedTheme.'.sections.'.$type;
            $fallback = 'showcase.themes.'.ShowcaseThemeRegistry::DEFAULT.'.sections.'.$type;

            if (View::exists($candidate)) {
                $views[$type] = $candidate;
            } elseif (View::exists($fallback)) {
                $views[$type] = $fallback;
            }
            // Ni le thème ni le thème par défaut ne couvrent ce type :
            // la section est ignorée au rendu (jamais d'erreur de template).
        }

        return $views;
    }

    /**
     * Variables de présentation effectives (thème + surcouche tenant +
     * variables de marque), avec repli.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function variables(string $theme, array $settings, string $companyName): array
    {
        $variables = ShowcaseThemeRegistry::resolveVariables($theme, $settings);

        $brandName = $settings['brand_name'] ?? null;
        $tagline = $settings['tagline'] ?? null;
        $logoUrl = $settings['logo_url'] ?? null;

        // Le DTO public expose `logo_url` déjà résolue (uuid média → URL).
        $variables['brand_name'] = is_string($brandName) && trim($brandName) !== ''
            ? $brandName
            : $companyName;
        $variables['tagline'] = is_string($tagline) && trim($tagline) !== '' ? $tagline : null;
        $variables['logo_url'] = is_string($logoUrl) && trim($logoUrl) !== '' ? $logoUrl : null;

        return $variables;
    }
}
