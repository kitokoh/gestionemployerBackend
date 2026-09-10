<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6868, V-THEMES) — 3 thèmes v1 (Industrie/Service/Commerce),
 * variables éditables (logo/couleurs/texte), contenu séparé de la présentation,
 * rendu serveur + fallback, et échappement XSS.
 */
class ShowcaseThemeTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function company(string $slug): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => $slug]);
        $company->setFeature('company_showcase', true);
        $company->save();

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function showcase(
        Company $company,
        string $theme,
        CompanyShowcaseStatus $status = CompanyShowcaseStatus::Published,
        array $settings = [],
        string $heading = 'Acme Industries',
    ): CompanyShowcase {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $theme, $status, $settings, $heading): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => $status,
                'theme' => $theme,
                'settings' => $settings,
                'published_at' => $status === CompanyShowcaseStatus::Published ? now() : null,
            ]);

            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'hero',
                'content' => ['heading' => $heading],
                'sort_order' => 10,
            ]);

            return $showcase;
        });
    }

    public function test_principal_can_select_each_v1_theme_and_invalid_theme_is_rejected(): void
    {
        $company = $this->company('acme-themes');
        $this->principal($company);
        $this->showcase($company, 'default', CompanyShowcaseStatus::Draft);

        foreach (['industrie', 'service', 'commerce'] as $theme) {
            $this->patchJson('/api/v1/showcase', ['theme' => $theme])
                ->assertOk()
                ->assertJsonPath('data.theme', $theme);
        }

        $this->patchJson('/api/v1/showcase', ['theme' => 'inconnu'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['theme']);

        $persisted = app(TenantManager::class)->withinTenant(
            $company,
            fn (): ?string => CompanyShowcase::query()->first()?->theme
        );

        $this->assertSame('commerce', $persisted);
    }

    public function test_each_v1_theme_renders_server_side(): void
    {
        foreach (['industrie', 'service', 'commerce'] as $theme) {
            $company = $this->company('acme-'.$theme);
            $this->showcase($company, $theme, CompanyShowcaseStatus::Published, [], 'Hero '.$theme);

            $html = (string) $this->get('/vitrine/'.$company->slug)->assertOk()->getContent();

            $this->assertStringContainsString('showcase--'.$theme, $html, "Le thème {$theme} doit produire sa page.");
            $this->assertStringContainsString('Hero '.$theme, $html, "Le contenu doit être rendu pour {$theme}.");
        }
    }

    /**
     * Le même contenu est rendu différemment selon le thème (contenu séparé de
     * la présentation) : les 3 pages ne sont pas identiques.
     */
    public function test_same_content_renders_differently_per_theme(): void
    {
        $renders = [];

        foreach (['industrie', 'service', 'commerce'] as $theme) {
            $company = $this->company('acme-same-'.$theme);
            $this->showcase($company, $theme, CompanyShowcaseStatus::Published, [], 'Contenu identique');
            $renders[$theme] = (string) $this->get('/vitrine/'.$company->slug)->assertOk()->getContent();
        }

        $this->assertNotSame($renders['industrie'], $renders['service']);
        $this->assertNotSame($renders['service'], $renders['commerce']);

        foreach ($renders as $html) {
            $this->assertStringContainsString('Contenu identique', $html);
        }
    }

    public function test_unknown_theme_falls_back_to_the_neutral_page(): void
    {
        $company = $this->company('acme-legacy-theme');
        $this->showcase($company, 'legacy-inconnu', CompanyShowcaseStatus::Published, [], 'Repli neutre');

        $html = (string) $this->get('/vitrine/'.$company->slug)->assertOk()->getContent();

        $this->assertStringContainsString('Repli neutre', $html);
        $this->assertStringNotContainsString('showcase--legacy-inconnu', $html);
        $this->assertStringNotContainsString('showcase--', $html);
    }

    public function test_tenant_brand_variables_are_applied_to_the_theme_css(): void
    {
        $company = $this->company('acme-vars');
        $this->showcase($company, 'service', CompanyShowcaseStatus::Published, [
            'brand_name' => 'Marque Client',
            'tagline' => 'Conseil & services',
            'colors' => [
                'primary' => '#123456',
                'accent' => '#abcdef',
            ],
            'font_family' => 'serif',
            'radius' => 'lg',
        ]);

        $html = (string) $this->get('/vitrine/'.$company->slug)->assertOk()->getContent();

        $this->assertStringContainsString('--showcase-primary: #123456', $html);
        $this->assertStringContainsString('--showcase-accent: #ABCDEF', $html);
        $this->assertStringContainsString('--showcase-radius: 1rem', $html);
        $this->assertStringContainsString('Times New Roman', $html);
        // Variables de marque éditables (texte).
        $this->assertStringContainsString('Marque Client', $html);
        $this->assertStringContainsString('Conseil &amp; services', $html);
    }

    public function test_public_dto_exposes_only_sanitized_colors(): void
    {
        $company = $this->company('acme-colors');
        $this->showcase($company, 'industrie', CompanyShowcaseStatus::Published, [
            'colors' => [
                'primary' => '#123456',
                'accent' => '#000000; background: url(javascript:alert(1))',
            ],
        ]);

        $this->getJson('/api/v1/public/vitrine/'.$company->slug)
            ->assertOk()
            ->assertJsonPath('data.settings.colors.primary', '#123456')
            ->assertJsonMissingPath('data.settings.colors.accent')
            ->assertJsonMissingPath('data.settings.logo_id');
    }

    public function test_theme_content_and_variables_are_html_escaped(): void
    {
        $company = $this->company('acme-xss');

        $this->showcase($company, 'commerce', CompanyShowcaseStatus::Published, [
            'brand_name' => 'Acme <script>window.__showcase_xss = 1</script>',
            'font_family' => '</style><script>alert(9)</script>',
            'colors' => ['primary' => '#000000; background: url(javascript:alert(7))'],
        ], '<img src=x onerror="window.__showcase_xss = 2">');

        $html = (string) $this->get('/vitrine/'.$company->slug)->assertOk()->getContent();

        // Le contenu est échappé (rendu littéral, jamais exécutable).
        $this->assertStringContainsString('&lt;script&gt;window.__showcase_xss = 1&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>window.__showcase_xss = 1', $html);
        $this->assertStringNotContainsString('onerror="window.__showcase_xss = 2"', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=&quot;window.__showcase_xss = 2&quot;&gt;', $html);

        // Les variables tenant invalides ne peuvent pas casser la feuille de style.
        $this->assertStringNotContainsString('alert(9)', $html);
        $this->assertStringNotContainsString('javascript:alert(7)', $html);
    }

    public function test_changing_theme_invalidates_the_public_cache(): void
    {
        $company = $this->company('acme-cache-theme');
        $this->showcase($company, 'industrie', CompanyShowcaseStatus::Published);

        $first = (string) $this->get('/vitrine/'.$company->slug)->assertOk()->getContent();
        $this->assertStringContainsString('showcase--industrie', $first);

        $this->principal($company);
        $this->patchJson('/api/v1/showcase', ['theme' => 'commerce'])->assertOk();

        $second = (string) $this->get('/vitrine/'.$company->slug)->assertOk()->getContent();
        $this->assertStringContainsString('showcase--commerce', $second);
        $this->assertStringNotContainsString('showcase--industrie', $second);

        $this->assertTrue($second !== $first);

        $persisted = app(TenantManager::class)->withinTenant(
            $company,
            fn (): ?string => CompanyShowcase::query()->first()?->theme
        );

        $this->assertSame('commerce', $persisted);
    }
}
