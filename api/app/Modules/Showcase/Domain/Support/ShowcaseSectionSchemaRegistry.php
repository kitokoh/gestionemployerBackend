<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;

/**
 * Registre des contrats de sections vitrine (BC-27 SHOWCASE, #6866).
 *
 * Chaque type de section v1 porte un document JSON Schema (draft-07 subset)
 * versionné (`schemaVersion`) — c'est la source unique du contrat :
 * `ShowcaseSectionSchemaValidator` l'interprète pour valider le `content`
 * (les tests unitaires couvrent schéma valide/invalide par type).
 *
 * v1 (schemaVersion 1) : hero, features, gallery, testimonials, contact,
 * footer. Le type `products` (BC-28) est ajouté par le composant C-VITRINE
 * #6891 avec une migration douce de version si le contrat évolue.
 */
final class ShowcaseSectionSchemaRegistry
{
    public const SCHEMA_VERSION = 1;

    /** Locale de référence du contenu (`content` porte toujours cette locale). */
    public const DEFAULT_LOCALE = 'fr';

    /**
     * Locales de contenu supportées par la vitrine (spec §10, #6874).
     *
     * @var list<string>
     */
    public const SUPPORTED_LOCALES = ['fr', 'en', 'ar', 'tr'];

    /**
     * Locales à direction d'écriture droite-à-gauche (RTL) — l'arabe impose
     * l'attribut `dir="rtl"` au rendu public (#6874).
     *
     * @var list<string>
     */
    public const RTL_LOCALES = ['ar'];

    /**
     * Documents JSON Schema (draft-07 subset) par type de section.
     *
     * Sous-ensemble interprété par ShowcaseSectionSchemaValidator :
     * `type` (object|array|string|integer|number|boolean), `properties`,
     * `required`, `additionalProperties` (booléen), `items` (schéma
     * d'élément), `maxLength`, `maxItems`, `minItems`, `description`.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schemas(): array
    {
        $string = static fn (int $max, string $description): array => [
            'type' => 'string',
            'maxLength' => $max,
            'description' => $description,
        ];

        return [
            ShowcaseSectionType::Hero->value => [
                'title' => 'hero',
                'type' => 'object',
                'description' => 'Bandeau principal : accroche, sous-titre, visuel et appel à l\'action.',
                'additionalProperties' => false,
                'properties' => [
                    'heading' => ['type' => 'string', 'maxLength' => 120, 'description' => "Titre principal (ex. nom de l'entreprise)."],
                    'subheading' => ['type' => 'string', 'maxLength' => 280, 'description' => 'Sous-titre / slogan court.'],
                    'image_url' => ['type' => 'string', 'maxLength' => 500, 'description' => 'URL publique du visuel principal (externe, repli).'],
                    'image_id' => ['type' => 'string', 'maxLength' => 64, 'description' => 'Identifiant stable (uuid) d\'un média vitrine #6872 (préféré à image_url).'],
                    'cta_label' => ['type' => 'string', 'maxLength' => 40, 'description' => "Libellé du bouton d'appel à l'action."],
                    'cta_url' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Cible du bouton (lien interne /public/vitrine/{slug} ou externe).'],
                ],
                'required' => ['heading'],
            ],
            ShowcaseSectionType::Features->value => [
                'title' => 'features',
                'type' => 'object',
                'description' => 'Liste de fonctionnalités / atouts (icône + titre + texte).',
                'additionalProperties' => false,
                'properties' => [
                    'title' => $string(120, 'Titre de la section.'),
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'description' => 'Atouts présentés.',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'icon' => $string(40, 'Nom de l\'icône (jeu de la charte).'),
                                'title' => $string(120, 'Titre de l\'atout.'),
                                'description' => $string(500, 'Texte descriptif.'),
                            ],
                            'required' => ['title'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
            ShowcaseSectionType::Gallery->value => [
                'title' => 'gallery',
                'type' => 'object',
                'description' => 'Galerie d\'images (site, produits, équipe…).',
                'additionalProperties' => false,
                'properties' => [
                    'title' => $string(120, 'Titre de la section.'),
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 20,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'image_url' => $string(500, 'URL publique de l\'image (externe, repli).'),
                                'image_id' => $string(64, 'Identifiant stable (uuid) d\'un média vitrine #6872.'),
                                'caption' => $string(200, 'Légende optionnelle.'),
                            ],
                            'required' => ['image_url'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
            ShowcaseSectionType::Testimonials->value => [
                'title' => 'testimonials',
                'type' => 'object',
                'description' => 'Témoignages clients (citation, auteur, fonction).',
                'additionalProperties' => false,
                'properties' => [
                    'title' => $string(120, 'Titre de la section.'),
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'quote' => $string(1000, 'Citation.'),
                                'author' => $string(120, 'Nom du client.'),
                                'role' => $string(120, 'Fonction / entreprise du client.'),
                            ],
                            'required' => ['quote', 'author'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
            ShowcaseSectionType::Contact->value => [
                'title' => 'contact',
                'type' => 'object',
                'description' => 'Bloc contact : email du tenant (destinataire BC-13), téléphone, adresse.',
                'additionalProperties' => false,
                'properties' => [
                    'title' => $string(120, 'Titre de la section.'),
                    'email' => $string(190, 'Email de contact du tenant.'),
                    'phone' => $string(40, 'Téléphone affiché.'),
                    'address' => $string(300, 'Adresse affichée.'),
                ],
                'required' => ['email'],
            ],
            ShowcaseSectionType::Footer->value => [
                'title' => 'footer',
                'type' => 'object',
                'description' => 'Pied de page : texte de bas de page + liens.',
                'additionalProperties' => false,
                'properties' => [
                    'text' => $string(500, 'Texte (ex. copyright, mention courte).'),
                    'links' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'label' => $string(80, 'Libellé du lien.'),
                                'url' => $string(500, 'Cible du lien.'),
                            ],
                            'required' => ['label', 'url'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function knownTypes(): array
    {
        return ShowcaseSectionType::v1();
    }

    /**
     * @param  string  $type  Valeur de {@see ShowcaseSectionType}.
     * @return array<string, mixed>|null
     */
    public static function schemaFor(string $type): ?array
    {
        return self::schemas()[$type] ?? null;
    }

    public static function isKnownType(string $type): bool
    {
        return self::schemaFor($type) !== null;
    }

    /**
     * @return list<string>
     */
    public static function supportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    public static function defaultLocale(): string
    {
        return self::DEFAULT_LOCALE;
    }

    public static function isSupportedLocale(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    public static function isRtlLocale(string $locale): bool
    {
        return in_array($locale, self::RTL_LOCALES, true);
    }

    /**
     * Direction d'écriture (`ltr`/`rtl`) d'une locale supportée — `ltr` par
     * repli pour une locale inconnue.
     */
    public static function directionFor(string $locale): string
    {
        return self::isRtlLocale($locale) ? 'rtl' : 'ltr';
    }

    /**
     * Champs localisables d'une section, par type — chemins relatifs au
     * contenu (`items.title` = sous-champ `title` de chaque élément du
     * tableau `items`). Les champs non listés (médias, icônes, e-mails,
     * URLs) sont partagés entre les locales : l'éditeur V-EDITOR #6870 ne
     * les duplique pas dans `translations`.
     *
     * @return array<string, list<string>>
     */
    public static function localizableFields(): array
    {
        return [
            ShowcaseSectionType::Hero->value => ['heading', 'subheading', 'cta_label'],
            ShowcaseSectionType::Features->value => ['title', 'items.title', 'items.description'],
            ShowcaseSectionType::Gallery->value => ['title', 'items.caption'],
            ShowcaseSectionType::Testimonials->value => ['title', 'items.quote', 'items.author', 'items.role'],
            ShowcaseSectionType::Contact->value => ['title', 'phone', 'address'],
            ShowcaseSectionType::Footer->value => ['text', 'links.label'],
        ];
    }

    /**
     * Champs localisables d'un type (`[]` si le type est inconnu).
     *
     * @return list<string>
     */
    public static function localizableFieldsFor(string $type): array
    {
        return self::localizableFields()[$type] ?? [];
    }
}
