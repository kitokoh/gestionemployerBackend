<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

/**
 * Résolution multilingue du contenu d'une section (BC-27 SHOWCASE, #6874).
 *
 * `content` porte le contenu de référence (locale par défaut, schéma complet) ;
 * `translations` porte des surcouches partielles par locale. Le rendu public
 * résout, pour la locale demandée, le contenu effectif par fusion :
 *   - scalaire traduit → valeur traduite ;
 *   - liste (`items`, `links`) → fusion index par index avec le contenu de
 *     référence (un élément non traduit retombe sur la référence ; les
 *     éléments de référence non couverts sont conservés) ;
 *   - objet imbriqué → fusion récursive.
 *
 * Aucune donnée interne n'est manipulée ici : la résolution ne fait que
 * composer le contenu déjà validé par schéma. Une locale sans surcouche
 * retombe intégralement sur le contenu de référence.
 */
final class ShowcaseSectionContentResolver
{
    /**
     * @param  array<string, mixed>  $content  Contenu de référence (locale par défaut).
     * @param  array<string, mixed>|null  $translations  Surcouches par locale.
     * @return array<string, mixed> Contenu effectif pour `$locale`.
     */
    public static function resolve(array $content, ?array $translations, string $locale): array
    {
        if ($locale === ShowcaseSectionSchemaRegistry::defaultLocale() || ! is_array($translations)) {
            return $content;
        }

        $overlay = $translations[$locale] ?? null;

        if (! is_array($overlay)) {
            return $content;
        }

        return self::merge($content, $overlay);
    }

    /**
     * Locales réellement disponibles pour un contenu (locale de référence
     * toujours incluse), dans l'ordre canonique
     * {@see ShowcaseSectionSchemaRegistry::SUPPORTED_LOCALES}.
     *
     * @param  array<string, mixed>|null  $translations
     * @return list<string>
     */
    public static function availableLocales(?array $translations): array
    {
        $present = [ShowcaseSectionSchemaRegistry::defaultLocale() => true];

        if (is_array($translations)) {
            foreach (array_keys($translations) as $locale) {
                if (is_string($locale) && ShowcaseSectionSchemaRegistry::isSupportedLocale($locale)) {
                    $present[$locale] = true;
                }
            }
        }

        $locales = [];

        foreach (ShowcaseSectionSchemaRegistry::supportedLocales() as $locale) {
            if (isset($present[$locale])) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * Fusion d'une surcouche sur le contenu de référence, sans écraser un
     * champ non traduit par une valeur absente.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $overlay): array
    {
        $result = $base;

        foreach ($overlay as $key => $value) {
            $isListValue = is_array($value) && $value !== [] && array_is_list($value);
            $baseValue = $result[$key] ?? null;

            if ($isListValue && is_array($baseValue) && array_is_list($baseValue)) {
                $merged = $baseValue;

                foreach ($value as $index => $item) {
                    $baseItem = $merged[$index] ?? null;
                    $merged[$index] = is_array($item) && is_array($baseItem)
                        ? self::merge($baseItem, $item)
                        : $item;
                }

                $result[$key] = array_values($merged);

                continue;
            }

            $isObjectValue = is_array($value) && ! array_is_list($value);

            if ($isObjectValue && is_array($baseValue) && ! array_is_list($baseValue)) {
                $result[$key] = self::merge($baseValue, $value);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
