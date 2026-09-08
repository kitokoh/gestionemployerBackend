<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Enums;

/**
 * Types de sections de la vitrine tenant (BC-27 SHOWCASE, #6866).
 *
 * v1 — spec SOLUTION_SITE_VITRINE.md §5 : page = liste ordonnée de sections
 * typées, chacune validée par un JSON Schema versionné
 * (ShowcaseSectionSchemas). « produits » est optionnel v1 (consomme le
 * catalogue BC-28 via #6891) : le type existe dans le contrat dès maintenant
 * pour que l'éditeur (#6870) puisse afficher la section.
 *
 * @see \App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemas
 */
enum ShowcaseSectionType: string
{
    case Hero = 'hero';
    case Features = 'features';
    case Products = 'products';
    case Gallery = 'gallery';
    case Testimonials = 'testimonials';
    case Contact = 'contact';
    case Footer = 'footer';

    /**
     * Liste canonique des types v1 (ordre d'affichage suggéré dans l'éditeur).
     *
     * @return list<string>
     */
    public static function allowedValues(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
