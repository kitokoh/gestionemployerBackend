<?php

declare(strict_types=1);

namespace Tests\Unit\Showcase;

use App\Modules\Showcase\Domain\Support\ShowcaseSectionContentValidator;
use PHPUnit\Framework\TestCase;

/**
 * BC-27 SHOWCASE (#6866) — contrat de sections : validation JSON Schema v1
 * (valide/invalide) pour chaque type de section.
 */
class ShowcaseSectionContentValidatorTest extends TestCase
{
    private ShowcaseSectionContentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ShowcaseSectionContentValidator;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function assertValid(string $type, array $content): void
    {
        $this->assertSame([], $this->validator->validate($type, $content), "{$type} devrait être valide");
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function assertInvalid(string $type, array $content): void
    {
        $this->assertNotSame([], $this->validator->validate($type, $content), "{$type} devrait être invalide");
    }

    public function test_hero_valid_and_invalid(): void
    {
        $this->assertValid('hero', ['title' => 'Bienvenue']);
        $this->assertValid('hero', ['title' => 'Bienvenue', 'subtitle' => 'Texte', 'cta_href' => 'https://leopardo.com']);
        $this->assertInvalid('hero', ['subtitle' => 'Sans titre']);
        $this->assertInvalid('hero', ['title' => 'OK', 'bogus' => 1]);
        $this->assertInvalid('hero', ['title' => 'OK', 'cta_href' => 'nimporte']);
    }

    public function test_features_items_contract(): void
    {
        $this->assertValid('features', [
            'items' => [
                ['title' => 'Un', 'description' => 'Desc'],
                ['title' => 'Deux'],
            ],
        ]);
        $this->assertInvalid('features', ['title' => 'Sans items']);
        $this->assertInvalid('features', ['items' => [['description' => 'Sans titre']]]);
        $this->assertInvalid('features', [
            'items' => array_fill(0, 7, ['title' => 'x']),
        ]);
        $this->assertInvalid('features', [
            'items' => [['title' => 'x', 'extra' => true]],
        ]);
    }

    public function test_gallery_contract(): void
    {
        $this->assertValid('gallery', ['images' => [['image_url' => 'https://cdn.example.com/a.jpg']]]);
        $this->assertInvalid('gallery', ['title' => 'pas d images']);
        $this->assertInvalid('gallery', ['images' => [['alt' => 'sans url']]]);
    }

    public function test_testimonials_contract(): void
    {
        $this->assertValid('testimonials', [
            'items' => [
                ['quote' => 'Super produit', 'author' => 'Ali', 'role' => 'DG'],
            ],
        ]);
        $this->assertInvalid('testimonials', ['items' => [['quote' => 'sans auteur']]]);
        $this->assertInvalid('testimonials', ['items' => 'pas un tableau']);
    }

    public function test_contact_and_footer_allow_empty_object(): void
    {
        // {} JSON → [] en PHP : objet vide accepté (aucun champ requis).
        $this->assertValid('contact', []);
        $this->assertValid('footer', []);
        $this->assertValid('contact', ['email' => 'contact@entreprise.com', 'phone' => '+213550000000']);
        $this->assertInvalid('contact', ['email' => 'pas-un-email']);
        $this->assertValid('footer', [
            'tagline' => 'Depuis 2020',
            'links' => [['label' => 'Mentions légales', 'href' => 'https://entreprise.com/legal']],
        ]);
        $this->assertInvalid('footer', ['links' => [['href' => 'https://x.com']]]);
    }

    public function test_products_section_is_forward_compatible(): void
    {
        // Type réservé à l'intégration catalogue BC-28 (#6891) : contenu libre v1.
        $this->assertValid('products', ['title' => 'Nos produits']);
        $this->assertInvalid('products', ['unknown' => 1]);
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->assertNotSame([], $this->validator->validate('mosaic', ['title' => 'x']));
    }

    public function test_length_and_type_constraints(): void
    {
        $this->assertInvalid('hero', ['title' => str_repeat('a', 121)]);
        $this->assertInvalid('hero', ['title' => 42]);
        $this->assertInvalid('hero', ['title' => null]);
        $this->assertInvalid('hero', ['title' => true]);
    }
}
