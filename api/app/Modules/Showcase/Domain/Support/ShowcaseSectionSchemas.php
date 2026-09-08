<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;

/**
 * Contrat de sections de la vitrine tenant (BC-27 SHOWCASE, #6866).
 *
 * Source de vérité du JSON Schema v1 par type de section. Sous-ensemble
 * draft-07 supporté par ShowcaseSectionContentValidator :
 *
 *   - racine et sous-objets : { type: "object", properties, required[],
 *     additionalProperties: false } ;
 *   - scalaires : string (minLength/maxLength/enum/format: uri|email),
 *     integer (min/max), boolean ;
 *   - tableaux : { type: "array", items: <schéma>, maxItems }.
 *
 * Chaque PR qui fait évoluer le contrat incrémente la version de schéma
 * concernée (et seulement celle-là) : le contenu existant reste valide tant
 * que la contrainte est additive (convention #6866 « migration douce »).
 */
final class ShowcaseSectionSchemas
{
    /** Version courante du contrat de sections (colonne schema_version). */
    public const VERSION = 1;

    /**
     * @return array<string, array<string, mixed>> schéma par type
     */
    public static function all(): array
    {
        $string = static fn (int $max, int $min = 1): array => [
            'type' => 'string',
            'minLength' => $min,
            'maxLength' => $max,
        ];

        return [
            ShowcaseSectionType::Hero->value => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['title'],
                'properties' => [
                    'badge' => $string(80),
                    'title' => $string(120),
                    'subtitle' => $string(400, 0),
                    'cta_label' => $string(60),
                    'cta_href' => ['type' => 'string', 'maxLength' => 300, 'format' => 'uri'],
                    'image_url' => ['type' => 'string', 'maxLength' => 500, 'format' => 'uri'],
                ],
            ],
            ShowcaseSectionType::Features->value => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['items'],
                'properties' => [
                    'title' => $string(120, 0),
                    'items' => [
                        'type' => 'array',
                        'maxItems' => 6,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['title'],
                            'properties' => [
                                'icon' => $string(60, 0),
                                'title' => $string(120),
                                'description' => $string(300, 0),
                            ],
                        ],
                    ],
                ],
            ],
            ShowcaseSectionType::Products->value => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [],
                'properties' => [
                    'title' => $string(120, 0),
                    'note' => $string(300, 0),
                    // Les produits publiés du catalogue BC-28 (#6891) sont
                    // injectés au rendu — jamais stockés dans la section.
                ],
            ],
            ShowcaseSectionType::Gallery->value => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['images'],
                'properties' => [
                    'title' => $string(120, 0),
                    'images' => [
                        'type' => 'array',
                        'maxItems' => 12,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['image_url'],
                            'properties' => [
                                'image_url' => ['type' => 'string', 'maxLength' => 500, 'format' => 'uri'],
                                'alt' => $string(200, 0),
                                'caption' => $string(200, 0),
                            ],
                        ],
                    ],
                ],
            ],
            ShowcaseSectionType::Testimonials->value => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['items'],
                'properties' => [
                    'title' => $string(120, 0),
                    'items' => [
                        'type' => 'array',
                        'maxItems' => 6,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['quote', 'author'],
                            'properties' => [
                                'quote' => $string(600),
                                'author' => $string(120),
                                'role' => $string(120, 0),
                                'avatar_url' => ['type' => 'string', 'maxLength' => 500, 'format' => 'uri', 'minLength' => 0],
                            ],
                        ],
                    ],
                ],
            ],
            ShowcaseSectionType::Contact->value => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [],
                'properties' => [
                    'title' => $string(120, 0),
                    'subtitle' => $string(300, 0),
                    'email' => ['type' => 'string', 'maxLength' => 190, 'format' => 'email', 'minLength' => 0],
                    'phone' => $string(60, 0),
                    'address' => $string(300, 0),
                ],
            ],
            ShowcaseSectionType::Footer->value => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [],
                'properties' => [
                    'tagline' => $string(300, 0),
                    'address' => $string(300, 0),
                    'phone' => $string(60, 0),
                    'links' => [
                        'type' => 'array',
                        'maxItems' => 8,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['label', 'href'],
                            'properties' => [
                                'label' => $string(80),
                                'href' => ['type' => 'string', 'maxLength' => 300, 'format' => 'uri'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Schéma JSON d'un type de section.
     *
     * @return array<string, mixed>
     */
    public static function for(ShowcaseSectionType $type): array
    {
        return self::all()[$type->value];
    }

    /**
     * Vrai si le type est un type de section v1 connu.
     */
    public static function supports(string $type): bool
    {
        return isset(self::all()[$type]);
    }
}
