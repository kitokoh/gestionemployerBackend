<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use Illuminate\Http\Request;

/**
 * Résolution de la locale de contenu d'une vitrine publique (BC-27 SHOWCASE,
 * #6874).
 *
 * Ordre de priorité (spec §10 « rendu selon Accept-Language ou sélecteur
 * exposé ») :
 *   1. sélecteur explicite `?lang=<fr|en|ar|tr>` (lien de langue exposé sur la
 *      page publique et l'éditeur, aperçu dans la langue éditée) ;
 *   2. en-tête `Accept-Language` (locale de base à 2 lettres, q-values
 *      respectées) ;
 *   3. locale de référence `fr`.
 *
 * Une valeur inconnue ou non supportée est ignorée (jamais d'erreur : le
 * visiteur obtient le contenu de référence) — le résultat est toujours une
 * locale de {@see ShowcaseSectionSchemaRegistry::SUPPORTED_LOCALES}.
 */
final class ShowcaseLocaleResolver
{
    public function resolve(Request $request): string
    {
        $explicit = $request->query('lang');

        if (is_string($explicit) && trim($explicit) !== '') {
            $normalized = $this->normalize($explicit);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->fromAcceptLanguage((string) $request->header('Accept-Language', ''));
    }

    private function normalize(string $value): ?string
    {
        $base = strtolower(substr(trim($value), 0, 2));

        return ShowcaseSectionSchemaRegistry::isSupportedLocale($base) ? $base : null;
    }

    private function fromAcceptLanguage(string $header): string
    {
        if (trim($header) === '') {
            return ShowcaseSectionSchemaRegistry::defaultLocale();
        }

        $bestLocale = null;
        $bestQuality = 0.0;

        foreach (explode(',', $header) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $bits = explode(';', $part);
            $tag = strtolower(trim($bits[0]));
            $quality = 1.0;

            foreach (array_slice($bits, 1) as $parameter) {
                $parameter = trim($parameter);

                if (str_starts_with($parameter, 'q=')) {
                    $quality = (float) substr($parameter, 2);
                }
            }

            $locale = $this->normalize($tag);

            // `>` strict : à qualité égale, la première langue déclarée gagne
            // (ordre de préférence de l'en-tête).
            if ($locale !== null && $quality > 0.0 && $quality > $bestQuality) {
                $bestLocale = $locale;
                $bestQuality = $quality;
            }
        }

        return $bestLocale ?? ShowcaseSectionSchemaRegistry::defaultLocale();
    }
}
