<?php

declare(strict_types=1);

namespace Tests\Unit\Showcase;

use App\Modules\Showcase\Domain\Support\ShowcaseSectionContentResolver;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaValidator;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionTranslationValidator;
use PHPUnit\Framework\TestCase;

/**
 * BC-27 SHOWCASE (#6874 V-I18N) — contrat multilingue des sections :
 * fusion partielle par locale (scalaires, listes index par index, repli sur la
 * référence), locales disponibles et validation des surcouches (locale
 * supportée, locale de référence refusée, mode partiel, clés inconnues).
 */
final class ShowcaseSectionLocalizationTest extends TestCase
{
    private ShowcaseSectionTranslationValidator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translator = new ShowcaseSectionTranslationValidator(new ShowcaseSectionSchemaValidator);
    }

    public function test_supported_locales_are_fr_en_ar_tr_with_arabic_rtl(): void
    {
        $this->assertSame(['fr', 'en', 'ar', 'tr'], ShowcaseSectionSchemaRegistry::supportedLocales());
        $this->assertSame('fr', ShowcaseSectionSchemaRegistry::defaultLocale());
        $this->assertTrue(ShowcaseSectionSchemaRegistry::isRtlLocale('ar'));
        $this->assertSame('rtl', ShowcaseSectionSchemaRegistry::directionFor('ar'));
        $this->assertSame('ltr', ShowcaseSectionSchemaRegistry::directionFor('en'));
        $this->assertSame('ltr', ShowcaseSectionSchemaRegistry::directionFor('de'));
    }

    public function test_content_resolver_overlays_scalars_and_merges_lists_by_index(): void
    {
        $content = [
            'title' => 'Nos atouts',
            'items' => [
                ['icon' => 'shield', 'title' => 'Qualité', 'description' => 'Certifiée.'],
                ['icon' => 'clock', 'title' => 'Délais courts'],
            ],
        ];

        $translations = [
            'en' => [
                'title' => 'Our strengths',
                'items' => [
                    ['title' => 'Quality'],
                ],
            ],
        ];

        $resolved = ShowcaseSectionContentResolver::resolve($content, $translations, 'en');

        $this->assertSame('Our strengths', $resolved['title']);
        // Élément 0 : champs traduits + champs de référence conservés (icône, description).
        $this->assertSame('Quality', $resolved['items'][0]['title']);
        $this->assertSame('shield', $resolved['items'][0]['icon']);
        $this->assertSame('Certifiée.', $resolved['items'][0]['description']);
        // Élément 1 non traduit : conservé à l'identique.
        $this->assertSame('Délais courts', $resolved['items'][1]['title']);
    }

    public function test_content_resolver_falls_back_to_reference_content(): void
    {
        $content = ['heading' => 'Acme Industries'];

        // Locale de référence.
        $this->assertSame($content, ShowcaseSectionContentResolver::resolve($content, ['en' => ['heading' => 'X']], 'fr'));
        // Locale supportée sans surcouche.
        $this->assertSame($content, ShowcaseSectionContentResolver::resolve($content, ['en' => ['heading' => 'X']], 'tr'));
        // Aucune surcouche du tout.
        $this->assertSame($content, ShowcaseSectionContentResolver::resolve($content, null, 'ar'));
    }

    public function test_available_locales_are_canonically_ordered(): void
    {
        $this->assertSame(['fr'], ShowcaseSectionContentResolver::availableLocales(null));
        $this->assertSame(
            ['fr', 'en', 'ar'],
            ShowcaseSectionContentResolver::availableLocales(['ar' => [], 'en' => []])
        );
        // Locale non supportée ignorée.
        $this->assertSame(['fr', 'en'], ShowcaseSectionContentResolver::availableLocales(['de' => [], 'en' => []]));
    }

    public function test_translation_validator_accepts_partial_overlay(): void
    {
        // Surcouche partielle : seul le titre est traduit (le reste retombe sur la référence).
        $this->assertSame([], $this->translator->validate('features', [
            'en' => ['title' => 'Our strengths'],
        ]));

        $this->assertSame([], $this->translator->validate('hero', [
            'ar' => ['heading' => 'أكمي'],
        ]));
    }

    public function test_translation_validator_rejects_unsupported_and_reference_locales(): void
    {
        $errors = $this->translator->validate('hero', ['de' => ['heading' => 'Acme']]);
        $this->assertArrayHasKey('translations.de', $errors);

        $errors = $this->translator->validate('hero', ['fr' => ['heading' => 'Acme']]);
        $this->assertArrayHasKey('translations.fr', $errors);
    }

    public function test_translation_validator_rejects_unknown_field_and_wrong_type(): void
    {
        $errors = $this->translator->validate('hero', ['en' => ['evil' => 'x']]);
        $this->assertArrayHasKey('translations.en', $errors);

        $errors = $this->translator->validate('hero', ['en' => 'not-an-object']);
        $this->assertArrayHasKey('translations.en', $errors);

        // Borne de longueur toujours appliquée en mode partiel.
        $errors = $this->translator->validate('hero', ['en' => ['heading' => str_repeat('a', 121)]]);
        $this->assertArrayHasKey('translations.en.heading', $errors);
    }
}
