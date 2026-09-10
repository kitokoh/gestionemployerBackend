<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Cache public des vitrines (BC-27 SHOWCASE, #6867 — étendu #6874).
 *
 * - Lecture publique mise en cache Redis (TTL borné, clé = slug tenant +
 *   locale → jamais de contenu d'un tenant sous le slug d'un autre, spec §8 ;
 *   et jamais le contenu d'une locale servi sous une autre, V-I18N #6874) ;
 * - invalidation à chaque mutation d'une vitrine publiée (sections,
 *   publication/dépublication — #6871) via {@see forget()} qui purge TOUTES
 *   les locales ;
 * - le cache stocke le DTO public résolu (tableau JSON-safe), jamais de
 *   modèles Eloquent.
 */
final class ShowcasePublicCache
{
    public const TTL_SECONDS = 900; // 15 min

    public const KEY_PREFIX = 'showcase:public:vitrine:';

    public static function key(string $slug, string $locale = ShowcaseSectionSchemaRegistry::DEFAULT_LOCALE): string
    {
        return self::KEY_PREFIX.$locale.':'.$slug;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $resolver
     * @return T
     */
    public function remember(string $slug, string $locale, \Closure $resolver): mixed
    {
        return Cache::remember(self::key($slug, $locale), now()->addSeconds(self::TTL_SECONDS), $resolver);
    }

    /**
     * Purge le DTO public d'un slug pour toutes les locales supportées (une
     * mutation change le contenu de référence et donc, potentiellement, toutes
     * les déclinaisons).
     */
    public function forget(string $slug): void
    {
        foreach (ShowcaseSectionSchemaRegistry::supportedLocales() as $locale) {
            Cache::forget(self::key($slug, $locale));
        }
    }
}
