<?php

declare(strict_types=1);

namespace Tests\Unit\Showcase;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BC-27 SHOWCASE (#6866) — contrat de sections : le registre expose un JSON
 * Schema par type v1 (document valide + versionné) et le validator accepte
 * les contenus conformes / rejette les contenus invalides (champ manquant,
 * clé inconnue, borne dépassée, mauvais type, type inconnu).
 */
final class ShowcaseSectionSchemaTest extends TestCase
{
    private ShowcaseSectionSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ShowcaseSectionSchemaValidator();
    }

    public function test_registry_exposes_v1_types_with_documents(): void
    {
        $schemas = ShowcaseSectionSchemaRegistry::schemas();

        $this->assertSame(['hero', 'features', 'gallery', 'testimonials', 'contact', 'footer'], array_keys($schemas));
        $this->assertSame(ShowcaseSectionType::v1(), ShowcaseSectionSchemaRegistry::knownTypes());
        $this->assertSame(1, ShowcaseSectionSchemaRegistry::SCHEMA_VERSION);

        foreach ($schemas as $type => $schema) {
            $this->assertSame('object', $schema['type'], "Le schéma du type {$type} doit être un objet.");
            $this->assertArrayHasKey('properties', $schema, "Le schéma du type {$type} doit déclarer des properties.");
            $this->assertFalse($schema['additionalProperties'] ?? false, "Le schéma du type {$type} doit être fermé (additionalProperties: false).");
            $this->assertIsArray(ShowcaseSectionSchemaRegistry::schemaFor($type));
        }

        $this->assertNull(ShowcaseSectionSchemaRegistry::schemaFor('products')); // BC-28 #6891, hors v1
        $this->assertFalse(ShowcaseSectionSchemaRegistry::isKnownType('products'));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function validContents(): iterable
    {
        yield 'hero minimal' => ['hero', ['heading' => 'Acme Industries']];
        yield 'hero complet' => ['hero', [
            'heading' => 'Acme Industries',
            'subheading' => 'Fabricant depuis 1998',
            'image_url' => 'https://cdn.example.com/acme.jpg',
            'cta_label' => 'Nous contacter',
            'cta_url' => '/public/vitrine/acme',
        ]];
        yield 'features' => ['features', [
            'title' => 'Nos atouts',
            'items' => [
                ['icon' => 'shield', 'title' => 'Qualité', 'description' => 'Certifiée.'],
                ['title' => 'Délais courts'],
            ],
        ]];
        yield 'gallery' => ['gallery', [
            'items' => [
                ['image_url' => 'https://cdn.example.com/1.jpg', 'caption' => 'Atelier'],
            ],
        ]];
        yield 'testimonials' => ['testimonials', [
            'items' => [
                ['quote' => 'Excellent partenaire.', 'author' => 'M. Dupont', 'role' => 'Acheteur'],
            ],
        ]];
        yield 'contact' => ['contact', [
            'title' => 'Contact',
            'email' => 'contact@acme.dz',
            'phone' => '+213 00 00 00 00',
        ]];
        yield 'footer texte seul' => ['footer', ['text' => '© 2026 Acme']];
        yield 'footer vide (rien de requis)' => ['footer', []];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    #[DataProvider('validContents')]
    public function test_valid_content_is_accepted(string $type, array $content): void
    {
        $this->assertSame([], $this->validator->validate($type, $content), "Le contenu {$type} doit être accepté.");
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function invalidContents(): iterable
    {
        yield 'hero sans heading' => ['hero', [], 'content.heading'];
        yield 'hero clé inconnue' => ['hero', ['heading' => 'Acme', 'evil' => true], 'content'];
        yield 'hero heading trop long' => ['hero', ['heading' => str_repeat('a', 121)], 'content.heading'];
        yield 'hero mauvais type heading' => ['hero', ['heading' => 42], 'content.heading'];
        yield 'features items manquant' => ['features', ['title' => 'X'], 'content.items'];
        yield 'features items vides' => ['features', ['items' => []], 'content.items'];
        yield 'features item sans title' => ['features', ['items' => [['description' => 'x']]], 'content.items.0.title'];
        yield 'features trop d items' => ['features', ['items' => array_fill(0, 13, ['title' => 'x'])], 'content.items'];
        yield 'gallery item sans image' => ['gallery', ['items' => [['caption' => 'x']]], 'content.items.0.image_url'];
        yield 'contact sans email' => ['contact', ['phone' => '123'], 'content.email'];
        yield 'testimonials sans auteur' => ['testimonials', ['items' => [['quote' => 'q']]], 'content.items.0.author'];
        yield 'footer lien sans label' => ['footer', ['links' => [['url' => 'https://x.fr']]], 'content.links.0.label'];
        yield 'type inconnu' => ['products', [], 'type'];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    #[DataProvider('invalidContents')]
    public function test_invalid_content_is_rejected(string $type, array $content, string $expectedErrorKey): void
    {
        $errors = $this->validator->validate($type, $content);

        $this->assertNotSame([], $errors, "Le contenu {$type} doit être rejeté.");
        $this->assertArrayHasKey($expectedErrorKey, $errors, "Une erreur est attendue sur {$expectedErrorKey}. Erreurs: ".json_encode($errors));
    }
}
