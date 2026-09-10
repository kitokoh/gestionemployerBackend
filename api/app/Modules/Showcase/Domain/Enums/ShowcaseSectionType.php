<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Enums;

/**
 * Types de sections de vitrine (BC-27 SHOWCASE, #6866).
 *
 * v1 : héro, fonctionnalités, galerie, témoignages, contact, pied de page.
 * Le type `products` (catalogue BC-28) arrive avec le composant
 * C-VITRINE #6891 — absent volontairement de v1 (dépendance optionnelle).
 *
 * @see \App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry
 */
enum ShowcaseSectionType: string
{
    case Hero = 'hero';
    case Features = 'features';
    case Gallery = 'gallery';
    case Testimonials = 'testimonials';
    case Products = 'products';
    case Contact = 'contact';
    case Footer = 'footer';

    /**
     * Types autorisés en v1 (ordre canonique d'affichage par défaut).
     *
     * @return list<string>
     */
    public static function v1(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public static function tryFromOrNull(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
