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
 * BC-27 SHOWCASE (#6873, V-SEO) — rendu SSR + SEO.
 *
 * La page publique `/vitrine/{slug}` expose title/meta/OG/canonical ; le
 * `sitemap.xml` ne liste QUE les vitrines publiées ; brouillon/slug inconnu
 * répondent 404 `X-Robots-Tag: noindex` (jamais indexés) ; `robots.txt`
 * autorise les vitrines et exclut les aperçus.
 */
class ShowcaseSeoTest extends TestCase
{
    use RefreshTenantDatabase;

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

    private function showcase(Company $company, CompanyShowcaseStatus $status): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $status): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => $status,
                'theme' => 'default',
                'settings' => [
                    'brand_name' => 'Acme Industries',
                    'tagline' => 'Usinage de précision',
                    'logo_id' => 42, // interne — jamais exposé
                ],
                'published_at' => $status === CompanyShowcaseStatus::Published ? now() : null,
            ]);

            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'hero',
                'content' => [
                    'heading' => 'Acme Industries',
                    'subheading' => 'Usinage de précision depuis 1998',
                    'image_url' => 'https://cdn.example.test/acme-hero.jpg',
                ],
                'sort_order' => 10,
            ]);

            return $showcase;
        });
    }

    public function test_public_api_exposes_seo_meta(): void
    {
        $company = $this->company('acme-seo');
        $this->showcase($company, CompanyShowcaseStatus::Published);

        $this->getJson('/api/v1/public/vitrine/acme-seo')
            ->assertOk()
            ->assertJsonPath('data.meta.title', 'Acme Industries | Usinage de précision')
            ->assertJsonPath('data.meta.description', 'Usinage de précision depuis 1998')
            ->assertJsonPath('data.meta.og_image', 'https://cdn.example.test/acme-hero.jpg')
            ->assertJsonPath('data.meta.canonical_path', '/vitrine/acme-seo')
            ->assertJsonPath('data.meta.indexable', true);
    }

    public function test_ssr_page_renders_meta_open_graph_and_canonical(): void
    {
        $company = $this->company('acme-ssr');
        $this->showcase($company, CompanyShowcaseStatus::Published);

        $response = $this->get('/vitrine/acme-ssr')->assertOk();

        $html = (string) $response->getContent();

        $this->assertStringContainsString('<title>Acme Industries | Usinage de précision</title>', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('/vitrine/acme-ssr', $html);
        $this->assertStringContainsString('Usinage de précision depuis 1998', $html);

        // RGPD #6875 : aucun script/asset tiers (aucun cookie tiers).
        $this->assertStringNotContainsString('<script src=', $html);
        $this->assertStringNotContainsString('googletagmanager', $html);
        $this->assertStringNotContainsString('google-analytics', $html);

        // Aucune donnée interne (id de logo, company_id…) dans le HTML.
        $this->assertStringNotContainsString('logo_id', $html);
        $this->assertStringNotContainsString('company_id', $html);
    }

    public function test_ssr_draft_and_unknown_pages_are_404_with_noindex(): void
    {
        $company = $this->company('acme-draft-seo');
        $this->showcase($company, CompanyShowcaseStatus::Draft);

        $draft = $this->get('/vitrine/acme-draft-seo')->assertStatus(404);
        $this->assertSame('noindex, nofollow', $draft->headers->get('X-Robots-Tag'));

        $unknown = $this->get('/vitrine/does-not-exist')->assertStatus(404);
        $this->assertSame('noindex, nofollow', $unknown->headers->get('X-Robots-Tag'));
    }

    public function test_public_api_404_is_noindex(): void
    {
        $response = $this->getJson('/api/v1/public/vitrine/does-not-exist')->assertStatus(404);

        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function test_sitemap_lists_only_published_showcases(): void
    {
        Cache::flush();

        $published = $this->company('acme-published');
        $this->showcase($published, CompanyShowcaseStatus::Published);

        $draft = $this->company('acme-hidden');
        $this->showcase($draft, CompanyShowcaseStatus::Draft);

        $response = $this->get('/vitrine/sitemap.xml')->assertOk();

        $xml = (string) $response->getContent();

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('/vitrine/acme-published', $xml);
        $this->assertStringNotContainsString('/vitrine/acme-hidden', $xml);
    }

    public function test_api_sitemap_and_robots_are_public(): void
    {
        Cache::flush();

        $company = $this->company('acme-api-seo');
        $this->showcase($company, CompanyShowcaseStatus::Published);

        $this->get('/api/v1/public/vitrine/sitemap.xml')
            ->assertOk()
            ->assertSee('/vitrine/acme-api-seo', false);

        $this->get('/api/v1/public/vitrine/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap:', false)
            ->assertSee('Disallow: /vitrine/*?token=', false);
    }

    public function test_preview_of_draft_is_served_but_noindex(): void
    {
        $company = $this->company('acme-preview-seo');
        $this->showcase($company, CompanyShowcaseStatus::Draft);

        $this->principal($company);
        $token = $this->postJson('/api/v1/showcase/preview-token')->assertOk()->json('data.preview_token');

        $preview = $this->get('/vitrine/acme-preview-seo?token='.$token)->assertOk();
        $this->assertSame('noindex, nofollow', $preview->headers->get('X-Robots-Tag'));
    }
}
