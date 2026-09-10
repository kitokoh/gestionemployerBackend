<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE — V-I18N #6874 (+ V-THEMES #6868, C-VITRINE #6891).
 *
 * Résolution de la langue publique (`?lang=` > Accept-Language > fr),
 * surcharge de contenu par locale (`content_i18n`), registre des 3 thèmes
 * v1 (variables résolues + thème inconnu refusé) et section `products`
 * branchée sur le catalogue BC-28 (dépendance optionnelle : section omise
 * si catalogue absent/vide, seuls les produits publiés sortent).
 */
class ShowcaseI18nThemesProductsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => $slug,
        ]);

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
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>|null  $contentI18n
     */
    private function publishedShowcase(Company $company, string $type = 'hero', array $content = ['heading' => 'Acme'], ?array $contentI18n = null, string $theme = 'industrie'): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $type, $content, $contentI18n, $theme): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Published,
                'theme' => $theme,
                'published_at' => now(),
            ]);

            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => $type,
                'content' => $content,
                'content_i18n' => $contentI18n,
                'sort_order' => 10,
            ]);

            return $showcase;
        });
    }

    public function test_lang_query_param_serves_localized_content(): void
    {
        $company = $this->company('i18n-lang');
        $this->publishedShowcase($company, 'hero', ['heading' => 'Bienvenue'], [
            'en' => ['heading' => 'Welcome'],
            'tr' => ['heading' => 'Hos geldiniz'],
        ]);

        $this->getJson('/api/v1/public/vitrine/i18n-lang?lang=en')
            ->assertOk()
            ->assertJsonPath('data.lang', 'en')
            ->assertJsonPath('data.sections.0.content.heading', 'Welcome');

        $this->getJson('/api/v1/public/vitrine/i18n-lang?lang=tr')
            ->assertOk()
            ->assertJsonPath('data.lang', 'tr')
            ->assertJsonPath('data.sections.0.content.heading', 'Hos geldiniz');

        // Locale non supportée → repli sur le défaut (fr) et contenu par défaut.
        $this->getJson('/api/v1/public/vitrine/i18n-lang?lang=zz')
            ->assertOk()
            ->assertJsonPath('data.lang', 'fr')
            ->assertJsonPath('data.sections.0.content.heading', 'Bienvenue');
    }

    public function test_accept_language_header_resolves_locale(): void
    {
        $company = $this->company('i18n-header');
        $this->publishedShowcase($company, 'hero', ['heading' => 'Bienvenue'], [
            'ar' => ['heading' => 'مرحبا'],
        ]);

        $this->withHeader('Accept-Language', 'ar-DZ,ar;q=0.9,fr;q=0.8')
            ->getJson('/api/v1/public/vitrine/i18n-header')
            ->assertOk()
            ->assertJsonPath('data.lang', 'ar')
            ->assertJsonPath('data.sections.0.content.heading', 'مرحبا');
    }

    public function test_theme_variables_are_resolved_in_admin_and_public_payloads(): void
    {
        $company = $this->company('themes-resolved');
        $this->principal($company);
        $this->publishedShowcase($company, 'hero', ['heading' => 'Acme'], null, 'commerce');

        // Variables par défaut du thème commerce exposées au public.
        $this->getJson('/api/v1/public/vitrine/themes-resolved')
            ->assertOk()
            ->assertJsonPath('data.theme_config.id', 'commerce')
            ->assertJsonPath('data.theme_config.variables.accent', ShowcaseThemeRegistry::variables('commerce')['accent']);

        // Le tenant surcharge une couleur de marque.
        $this->putJson('/api/v1/showcase/settings', [
            'settings' => ['colors' => ['accent' => '#FF0000'], 'brand_name' => 'Acme', 'logo_id' => 42],
        ])->assertOk();

        $this->getJson('/api/v1/public/vitrine/themes-resolved')
            ->assertOk()
            ->assertJsonPath('data.theme_config.variables.accent', '#FF0000')
            ->assertJsonPath('data.settings.brand_name', 'Acme')
            // Allowlist : une clé interne poussée dans settings n'est jamais stockée.
            ->assertJsonMissingPath('data.settings.logo_id');
    }

    public function test_settings_endpoint_rejects_unknown_theme(): void
    {
        $company = $this->company('themes-unknown');
        $this->principal($company);
        $this->publishedShowcase($company);

        $this->putJson('/api/v1/showcase/settings', ['theme' => 'neon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');
    }

    public function test_products_section_is_omitted_when_catalog_is_empty(): void
    {
        $company = $this->company('products-empty');
        $this->publishedShowcase($company, 'products', ['title' => 'Nos produits']);

        $this->getJson('/api/v1/public/vitrine/products-empty')
            ->assertOk()
            // Dépendance BC-28 optionnelle : la section disparaît proprement.
            ->assertJsonCount(0, 'data.sections');
    }

    public function test_products_section_renders_published_products_only(): void
    {
        $company = $this->company('products-render');
        $this->publishedShowcase($company, 'products', ['title' => 'Nos produits', 'limit' => 6]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            CatalogProduct::query()->create([
                'company_id' => $company->id,
                'name' => 'Vis inox',
                'slug' => 'vis-inox',
                'description' => 'Vis qualité industrielle',
                'price_minor' => 1500,
                'currency' => 'DZD',
                'unit' => 'piece',
                'status' => CatalogProductStatus::Published,
            ]);

            CatalogProduct::query()->create([
                'company_id' => $company->id,
                'name' => 'Brouillon secret',
                'slug' => 'brouillon-secret',
                'price_minor' => 999,
                'currency' => 'DZD',
                'unit' => 'piece',
                'status' => CatalogProductStatus::Draft,
            ]);
        });

        $response = $this->getJson('/api/v1/public/vitrine/products-render')->assertOk();

        $response->assertJsonPath('data.sections.0.type', 'products');
        $response->assertJsonPath('data.sections.0.content.items.0.slug', 'vis-inox');
        $response->assertJsonCount(1, 'data.sections.0.content.items');

        // Le brouillon de produit n'est jamais exposé.
        $this->assertStringNotContainsString('brouillon-secret', $response->getContent() ?: '');
    }
}
