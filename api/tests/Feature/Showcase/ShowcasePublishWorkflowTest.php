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
 * BC-27 SHOWCASE (#6871, V-PUBLISH) — workflow de publication.
 *
 * Transitions `draft → published` / `published → draft` (+ re-publication),
 * RBAC (responsable uniquement), isolation tenant, invalidation du cache
 * public, audit (`qui` / `quand`) et jeton d'aperçu privé (brouillon servi
 * uniquement avec `?token=`, réponse `noindex`).
 */
class ShowcasePublishWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug = 'acme-industries'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => $slug]);
        $company->setFeature('company_showcase', true);
        $company->save();

        return $company;
    }

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $managerRole === 'employee' ? 'employee' : 'manager',
            'manager_role' => $managerRole === 'employee' ? null : $managerRole,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function draftShowcase(Company $company, bool $withSection = true): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $withSection): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Draft,
                'theme' => 'default',
            ]);

            if ($withSection) {
                CompanyShowcaseSection::query()->create([
                    'company_id' => $company->id,
                    'showcase_id' => $showcase->id,
                    'type' => 'hero',
                    'content' => ['heading' => 'Acme Industries'],
                    'sort_order' => 10,
                ]);
            }

            return $showcase;
        });
    }

    public function test_publish_transitions_draft_to_published_and_sets_published_at(): void
    {
        $company = $this->company();
        $this->employee($company, 'principal');
        $this->draftShowcase($company);

        $this->postJson('/api/v1/showcase/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->withinTenant($company, function () use ($company): void {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->firstOrFail();
            $this->assertSame(CompanyShowcaseStatus::Published, $showcase->status);
            $this->assertNotNull($showcase->published_at);

            $this->assertTrue(
                AuditLog::query()->where('action', 'showcase.published')->where('company_id', $company->id)->exists(),
                'la publication doit être auditée (qui / quand).'
            );
        });
    }

    public function test_republication_is_idempotent(): void
    {
        $company = $this->company();
        $this->employee($company, 'principal');
        $this->draftShowcase($company);

        $this->postJson('/api/v1/showcase/publish')->assertOk();
        $this->postJson('/api/v1/showcase/publish')->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_unpublish_transitions_published_to_draft_and_public_route_returns_404(): void
    {
        $company = $this->company();
        $this->employee($company, 'principal');
        $this->draftShowcase($company);

        $this->postJson('/api/v1/showcase/publish')->assertOk();
        $this->getJson('/api/v1/public/vitrine/acme-industries')->assertOk();

        $this->postJson('/api/v1/showcase/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->getJson('/api/v1/public/vitrine/acme-industries')->assertStatus(404);

        $this->withinTenant($company, function (): void {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->firstOrFail();
            $this->assertNull($showcase->published_at);
        });
    }

    public function test_publish_is_restricted_to_managers(): void
    {
        $company = $this->company();
        $this->draftShowcase($company);

        $this->employee($company, 'employee');
        $this->postJson('/api/v1/showcase/publish')->assertStatus(403);
    }

    public function test_publish_does_not_cross_tenant_boundary(): void
    {
        $companyA = $this->company('tenant-a');
        $companyB = $this->company('tenant-b');
        $this->draftShowcase($companyA);
        $this->draftShowcase($companyB);

        // Le responsable de B publie : seule la vitrine de B change.
        $this->employee($companyB, 'principal');
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->assertSame(CompanyShowcaseStatus::Draft, $this->statusFor($companyA));
        $this->assertSame(CompanyShowcaseStatus::Published, $this->statusFor($companyB));
    }

    public function test_publish_invalidates_public_cache(): void
    {
        $company = $this->company();
        $this->employee($company, 'principal');
        $this->draftShowcase($company);

        // Brouillon → 404, rien n'est mis en cache.
        $this->getJson('/api/v1/public/vitrine/acme-industries')->assertStatus(404);
        $key = ShowcasePublicCache::key('acme-industries');
        $this->assertFalse(Cache::has($key));

        $this->postJson('/api/v1/showcase/publish')->assertOk();

        // La publication est visible immédiatement (cache invalidé / repeuplé).
        $this->getJson('/api/v1/public/vitrine/acme-industries')
            ->assertOk()
            ->assertJsonPath('data.slug', 'acme-industries');
        $this->assertTrue(Cache::has($key));

        // Dépublication → cache purgé et route publique 404.
        $this->postJson('/api/v1/showcase/unpublish')->assertOk();
        $this->assertFalse(Cache::has($key));
        $this->getJson('/api/v1/public/vitrine/acme-industries')->assertStatus(404);
    }

    public function test_preview_token_serves_draft_with_noindex(): void
    {
        $company = $this->company();
        $this->employee($company, 'principal');
        $this->draftShowcase($company);

        // Sans jeton : un brouillon n'est jamais public.
        $this->getJson('/api/v1/public/vitrine/acme-industries')->assertStatus(404);

        $token = $this->postJson('/api/v1/showcase/preview-token')
            ->assertOk()
            ->json('data.preview_token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $preview = $this->getJson('/api/v1/public/vitrine/acme-industries?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.slug', 'acme-industries');

        $this->assertSame('noindex, nofollow', $preview->headers->get('X-Robots-Tag'));

        // Jeton invalide → 404 (fail-closed).
        $this->getJson('/api/v1/public/vitrine/acme-industries?token=invalid')->assertStatus(404);
    }

    public function test_publish_revokes_preview_token(): void
    {
        $company = $this->company();
        $this->employee($company, 'principal');
        $this->draftShowcase($company);

        $token = $this->postJson('/api/v1/showcase/preview-token')->assertOk()->json('data.preview_token');

        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->withinTenant($company, function (): void {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->firstOrFail();
            $this->assertNull($showcase->preview_token);
        });

        // Un ancien jeton sur une vitrine publiée ne provoque pas de noindex
        // (la vitrine est publique) mais n'est plus un secret valide.
        $this->getJson('/api/v1/public/vitrine/acme-industries?token='.$token)->assertOk();
    }

    private function statusFor(Company $company): CompanyShowcaseStatus
    {
        return app(TenantManager::class)->withinTenant($company, function (): CompanyShowcaseStatus {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->firstOrFail();

            return $showcase->status;
        });
    }

    /**
     * @param  \Closure(): void  $callback
     */
    private function withinTenant(Company $company, \Closure $callback): void
    {
        app(TenantManager::class)->withinTenant($company, $callback);
    }
}
