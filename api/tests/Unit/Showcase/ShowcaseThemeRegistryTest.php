<?php

declare(strict_types=1);

namespace Tests\Unit\Showcase;

use App\Modules\Showcase\Domain\Enums\ShowcaseTheme;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use PHPUnit\Framework\TestCase;

/**
 * BC-27 SHOWCASE (#6868) — moteur de thèmes : 3 thèmes v1 connus, variables de
 * présentation avec surcouche tenant et REPLI STRICT (toute valeur hors
 * allowlist retombe sur le thème — jamais de CSS arbitraire).
 */
final class ShowcaseThemeRegistryTest extends TestCase
{
    public function test_enum_exposes_the_three_v1_themes(): void
    {
        $this->assertSame(['industrie', 'service', 'commerce'], ShowcaseTheme::v1());
        $this->assertSame(ShowcaseTheme::Industrie, ShowcaseTheme::default());
        $this->assertSame(ShowcaseThemeRegistry::DEFAULT, ShowcaseTheme::default()->value);
    }

    public function test_registry_knows_v1_themes_and_their_variables(): void
    {
        foreach (ShowcaseTheme::v1() as $theme) {
            $this->assertTrue(ShowcaseThemeRegistry::isKnown($theme));

            $definition = ShowcaseThemeRegistry::definition($theme);

            foreach (['primary', 'accent', 'surface', 'on_primary', 'ink', 'muted', 'border', 'font_family', 'radius'] as $key) {
                $this->assertArrayHasKey($key, $definition, "Le thème {$theme} doit définir « {$key} ».");
            }

            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $definition['primary']);
        }

        $this->assertFalse(ShowcaseThemeRegistry::isKnown('default'));
        $this->assertFalse(ShowcaseThemeRegistry::isKnown('inconnu'));
    }

    public function test_unknown_theme_falls_back_to_the_default_definition(): void
    {
        $this->assertSame(
            ShowcaseThemeRegistry::definition(ShowcaseThemeRegistry::DEFAULT),
            ShowcaseThemeRegistry::definition('does-not-exist')
        );
    }

    public function test_tenant_overrides_are_applied_when_valid(): void
    {
        $variables = ShowcaseThemeRegistry::resolveVariables('service', [
            'colors' => [
                'primary' => '#123456',
                'accent' => '#abcdef',
                'surface' => '#ffffff',
                'on_primary' => '#0f172a',
            ],
            'font_family' => 'serif',
            'radius' => 'lg',
        ]);

        $this->assertSame('#123456', $variables['primary']);
        $this->assertSame('#ABCDEF', $variables['accent']);
        $this->assertSame('#FFFFFF', $variables['surface']);
        $this->assertSame('#0F172A', $variables['on_primary']);
        $this->assertStringContainsString('Times New Roman', $variables['font_family']);
        $this->assertSame('1rem', $variables['radius']);
    }

    public function test_invalid_tenant_values_fall_back_to_the_theme(): void
    {
        $variables = ShowcaseThemeRegistry::resolveVariables('industrie', [
            'colors' => [
                // Injection CSS / couleur non conforme → rejetée.
                'primary' => '#000000; background: url(javascript:alert(1))',
                'accent' => 'red',
            ],
            'font_family' => '</style><script>alert(2)</script>',
            'radius' => '99999px',
        ]);

        $theme = ShowcaseThemeRegistry::definition('industrie');

        $this->assertSame($theme['primary'], $variables['primary']);
        $this->assertSame($theme['accent'], $variables['accent']);
        $this->assertSame($theme['radius'], $variables['radius']);
        $this->assertStringNotContainsString('javascript', $variables['primary']);
        $this->assertStringNotContainsString('script', $variables['font_family']);
    }

    /**
     * Défense en profondeur pour le rendu `<style>` : aucune valeur renvoyée ne
     * peut clore une déclaration/balise CSS (pas de `;`, `{`, `}`, `<`, `>` ni
     * guillemet). La palette reste hexadécimale, les polices/arrondis viennent
     * d'une allowlist fermée.
     */
    public function test_resolved_variables_are_css_safe(): void
    {
        $variables = ShowcaseThemeRegistry::resolveVariables('commerce', [
            'colors' => ['primary' => '#FF0000'],
            'font_family' => 'mono',
            'radius' => 'full',
        ]);

        foreach ($variables as $key => $value) {
            $this->assertIsString($value, "La variable {$key} doit être une chaîne.");
            $this->assertDoesNotMatchRegularExpression('/[;{}<>"\']/', $value, "La variable {$key} doit être sûre en CSS.");
        }
    }
}
