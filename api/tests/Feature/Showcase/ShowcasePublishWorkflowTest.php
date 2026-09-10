<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE — V-PUBLISH #6871 (+ V-SEO #6873, V-RGPD #6875).
 *
 * Workflow de publication (draft → published → unpublish), jeton d'aperçu
 * privé (`?token=`, X-Robots-Tag noindex), sitemap des vitrines publiées,
 * robots.txt, méta SEO et bloc légal/cookies du DTO public, invalidation du
 * cache public à chaque transition.
 */
class ShowcasePublishWorkflowTest extends TestCase
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

    private function showcase(Company $company, CompanyShowcaseStatus $status = CompanyShowcaseStatus::Draft): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $status): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => $status,
                'theme' => 'industrie',
            ]);

            return $showcase;
        });
    }

    private function addHero(Company $company, CompanyShowcase $showcase, string $heading = 'Acme Industries'): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($company, $showcase, $heading): void {
            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'hero',
                'content' => ['heading' => $heading, 'subheading' => 'Pilotez votre terrain'],
                'sort_order' => 10,
            ]);
        });
    }

    public function test_publish_requires_at_least_one_section(): void
    {
        $company = $this->company('publish-empty');
        $this->principal($company);
        $this->showcase($company);

        $this->postJson('/api/v1/showcase/publish')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections');
    }

    public function test_publish_then_unpublish_toggles_public_visibility(): void
    {
        $company = $this->company('publish-toggle');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase);

        // Brouillon : jamais public.
        $this->getJson('/api/v1/public/vitrine/publish-toggle')->assertStatus(404);

        $this->postJson('/api/v1/showcase/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('company_showcases', [
            'slug' => 'publish-toggle',
            'status' => 'published',
        ]);

        // Audit de publication journalisé.
        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertTrue(
                AuditLog::query()->where('action', 'showcase.published')->exists()
            );
        });

        // Publiée : lisible en public.
        $this->getJson('/api/v1/public/vitrine/publish-toggle')
            ->assertOk()
            ->assertJsonPath('data.slug', 'publish-toggle');

        // Dépublication : 404 public.
        $this->postJson('/api/v1/showcase/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->getJson('/api/v1/public/vitrine/publish-toggle')->assertStatus(404);
    }

    public function test_preview_token_serves_draft_with_noindex_and_invalid_token_404(): void
    {
        $company = $this->company('publish-preview');
        $this->principal($company);
        $showcase = $this->showcase($company, CompanyShowcaseStatus::Draft);
        $this->addHero($company, $showcase);

        $token = $this->postJson('/api/v1/showcase/preview-token')
            ->assertOk()
            ->json('data.preview_token');

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        // Jeton valide → brouillon servi, non indexable.
        $response = $this->getJson('/api/v1/public/vitrine/publish-preview?token='.$token)->assertOk();
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $response->assertJsonPath('data.slug', 'publish-preview');

        // Jeton invalide → 404 (brouillon jamais exposé).
        $this->getJson('/api/v1/public/vitrine/publish-preview?token='.str_repeat('a', 64))
            ->assertStatus(404);
    }

    public function test_publish_revokes_the_preview_token(): void
    {
        $company = $this->company('publish-revoke');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase);

        $token = $this->postJson('/api/v1/showcase/preview-token')->json('data.preview_token');

        $this->postJson('/api/v1/showcase/publish')->assertOk();

        app(TenantManager::class)->withinTenant($company, function () use ($showcase): void {
            $this->assertNull($showcase->refresh()->preview_token);
        });

        // Le jeton d'aperçu est mort (la vitrine reste publique sans lui).
        $this->getJson('/api/v1/public/vitrine/publish-revoke')->assertOk();
        $this->assertIsString($token);
    }

    public function test_public_payload_exposes_seo_meta_legal_and_cookie_policy(): void
    {
        $company = $this->company('publish-seo');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase, 'Atelier du Sud');

        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->getJson('/api/v1/public/vitrine/publish-seo')
            ->assertOk()
            ->assertJsonPath('data.meta.title', 'Atelier du Sud')
            ->assertJsonPath('data.meta.description', 'Pilotez votre terrain')
            ->assertJsonPath('data.meta.canonical_path', '/public/vitrine/publish-seo')
            ->assertJsonPath('data.meta.indexable', true)
            ->assertJsonPath('data.cookies.third_party', false)
            ->assertJsonPath('data.lang', 'fr')
            // Bloc légal de repli : mentions + confidentialité non vides.
            ->assertJsonStructure(['data' => ['legal' => ['notice', 'privacy', 'contact_email']]]);
    }

    public function test_sitemap_lists_published_showcases_only(): void
    {
        $published = $this->company('sitemap-published');
        $this->principal($published);
        $showcasePublished = $this->showcase($published);
        $this->addHero($published, $showcasePublished);
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $draft = $this->company('sitemap-draft');
        $this->principal($draft);
        $showcaseDraft = $this->showcase($draft);
        $this->addHero($draft, $showcaseDraft);

        Cache::forget('showcase:public:sitemap');

        $xml = $this->get('/api/v1/public/vitrine/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertIsString($xml);
        $this->assertStringContainsString('/public/vitrine/sitemap-published', $xml);
        $this->assertStringNotContainsString('/public/vitrine/sitemap-draft', $xml);
    }

    public function test_robots_txt_allows_vitrine_and_excludes_preview_tokens(): void
    {
        $body = $this->get('/api/v1/public/vitrine/robots.txt')
            ->assertOk()
            ->getContent();

        $this->assertIsString($body);
        $this->assertStringContainsString('Allow: /public/vitrine/', $body);
        $this->assertStringContainsString('Disallow: /public/vitrine/*?token=', $body);
        $this->assertStringContainsString('Sitemap: /public/vitrine/sitemap.xml', $body);
    }

    public function test_unpublish_invalidates_the_public_cache(): void
    {
        $company = $this->company('publish-cache');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase);
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->getJson('/api/v1/public/vitrine/publish-cache')->assertOk();

        // Le cache publie est chaud…
        Cache::put(ShowcasePublicCache::key('publish-cache', 'fr'), ['sentinel' => true], 60);
        $this->assertNotNull(Cache::get(ShowcasePublicCache::key('publish-cache', 'fr')));

        $this->postJson('/api/v1/showcase/unpublish')->assertOk();

        // …et purgé par la dépublication.
        $this->assertNull(Cache::get(ShowcasePublicCache::key('publish-cache', 'fr')));
    }
}
