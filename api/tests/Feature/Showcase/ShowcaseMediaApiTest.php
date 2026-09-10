<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6872, V-MEDIA) — upload logo/images via le service de
 * stockage existant : validation type/taille, sanitisation du nom, lien
 * média↔vitrine/section, rendu public avec cache headers, et isolation tenant.
 *
 * Aucun PHPUnit n'est exécuté par l'auteur de ce lot (pas de runtime PHP dans
 * l'environnement de rédaction) : ces tests sont la spécification exécutable à
 * faire tourner en CI.
 */
class ShowcaseMediaApiTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
    }

    private function company(string $slug, bool $withFeature = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => $slug]);

        if ($withFeature) {
            $company->setFeature('company_showcase', true);
            $company->save();
        }

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

    private function showcase(
        Company $company,
        CompanyShowcaseStatus $status = CompanyShowcaseStatus::Draft,
        ?string $previewToken = null,
    ): CompanyShowcase {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $status, $previewToken): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => $status,
                'theme' => 'industrie',
                'settings' => [],
                'preview_token' => $previewToken,
                'published_at' => $status === CompanyShowcaseStatus::Published ? now() : null,
            ]);

            return $showcase;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upload(array $payload): TestResponse
    {
        return $this->post('/api/v1/showcase/media', $payload);
    }

    public function test_principal_uploads_logo_and_it_is_linked_to_the_showcase(): void
    {
        $company = $this->company('acme-media');
        $showcase = $this->showcase($company);
        $this->principal($company);

        $response = $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('Logo Acme.png', 320, 160),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.kind', 'logo')
            ->assertJsonPath('data.original_name', 'Logo Acme.png')
            ->assertJsonStructure(['data' => ['id', 'kind', 'section_id', 'original_name', 'mime_type', 'size', 'width', 'height', 'created_at']]);

        $uuid = $response->json('data.id');
        $this->assertIsString($uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', (string) $uuid);
        $this->assertIsInt($response->json('data.width'));

        /** @var array{media: ShowcaseMedia, logo_id: string|null} $stored */
        $stored = app(TenantManager::class)->withinTenant($company, function () use ($showcase): array {
            /** @var ShowcaseMedia $media */
            $media = ShowcaseMedia::query()->firstOrFail();
            $showcase->refresh();

            return [
                'media' => $media,
                'logo_id' => $showcase->settings['logo_id'] ?? null,
            ];
        });

        $media = $stored['media'];

        $this->assertSame($uuid, $media->uuid);
        $this->assertSame($uuid, $stored['logo_id']);
        $this->assertSame($company->id, $media->company_id);
        $this->assertSame($showcase->id, $media->showcase_id);
        $this->assertSame(320, $media->width);
        $this->assertSame(160, $media->height);
        $this->assertGreaterThan(0, $media->size);
        $this->assertNotNull($media->checksum);

        // Le chemin serveur ne contient jamais le nom client, et le disque
        // reçoit bien le fichier (service de stockage existant).
        $this->assertStringStartsWith('showcase/'.$company->id.'/'.$showcase->id.'/', $media->path);
        $this->assertStringNotContainsString('Logo', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_original_name_is_sanitized_and_never_used_as_path(): void
    {
        $company = $this->company('acme-sanitize');
        $this->showcase($company);
        $this->principal($company);

        $response = $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('../../evil logo.png', 64, 64),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.original_name', 'evil logo.png');

        /** @var ShowcaseMedia $media */
        $media = app(TenantManager::class)->withinTenant(
            $company,
            fn (): ShowcaseMedia => ShowcaseMedia::query()->firstOrFail()
        );

        $this->assertStringNotContainsString('evil', $media->path);
        $this->assertStringNotContainsString('..', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_section_media_is_linked_to_the_section(): void
    {
        $company = $this->company('acme-section-media');
        $showcase = $this->showcase($company);

        $sectionId = app(TenantManager::class)->withinTenant($company, function () use ($company, $showcase): int {
            /** @var CompanyShowcaseSection $section */
            $section = CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'gallery',
                'content' => ['items' => [['image_url' => 'https://cdn.example.test/a.jpg']]],
                'sort_order' => 10,
            ]);

            return $section->id;
        });

        $this->principal($company);

        $this->upload([
            'kind' => 'image',
            'section_id' => $sectionId,
            'file' => UploadedFile::fake()->image('photo.png', 200, 200),
        ])->assertStatus(201)
            ->assertJsonPath('data.kind', 'image')
            ->assertJsonPath('data.section_id', $sectionId);
    }

    public function test_unsupported_file_types_are_rejected(): void
    {
        $company = $this->company('acme-media-types');
        $this->showcase($company);
        $this->principal($company);

        // Logo : un document n'est pas un média vitrine.
        $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('contrat.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        // Image de section : le SVG (vecteur actif) est réservé au logo.
        $this->upload([
            'kind' => 'image',
            'file' => UploadedFile::fake()->create('icone.svg', 5, 'image/svg+xml'),
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        // `kind` hors allowlist.
        $this->upload([
            'kind' => 'banniere',
            'file' => UploadedFile::fake()->image('ok.png', 32, 32),
        ])->assertStatus(422)->assertJsonValidationErrors(['kind']);
    }

    public function test_oversize_files_are_rejected(): void
    {
        $company = $this->company('acme-media-size');
        $this->showcase($company);
        $this->principal($company);

        // Logo : 2 Mo max.
        $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('gros-logo.png', 32, 32)->size(3000),
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        // Image de section : 5 Mo max.
        $this->upload([
            'kind' => 'image',
            'file' => UploadedFile::fake()->image('grosse-image.png', 32, 32)->size(6000),
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_media_cannot_target_a_section_of_another_showcase(): void
    {
        $companyA = $this->company('tenant-media-a');
        $this->showcase($companyA);

        $companyB = $this->company('tenant-media-b');
        $showcaseB = $this->showcase($companyB);

        $foreignSectionId = app(TenantManager::class)->withinTenant($companyB, function () use ($companyB, $showcaseB): int {
            /** @var CompanyShowcaseSection $section */
            $section = CompanyShowcaseSection::query()->create([
                'company_id' => $companyB->id,
                'showcase_id' => $showcaseB->id,
                'type' => 'gallery',
                'content' => ['items' => [['image_url' => 'https://cdn.example.test/b.jpg']]],
                'sort_order' => 10,
            ]);

            return $section->id;
        });

        $this->principal($companyA);

        $this->upload([
            'kind' => 'image',
            'section_id' => $foreignSectionId,
            'file' => UploadedFile::fake()->image('photo.png', 64, 64),
        ])->assertStatus(422)->assertJsonValidationErrors(['section_id']);
    }

    public function test_cross_tenant_media_is_never_addressable(): void
    {
        $companyA = $this->company('tenant-a-media');
        $this->showcase($companyA);
        $this->principal($companyA);

        $uuid = (string) $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('a.png', 64, 64),
        ])->assertStatus(201)->json('data.id');

        // La ressource expose l'uuid, pas l'id interne : on récupère l'id réel.
        $internalId = app(TenantManager::class)->withinTenant(
            $companyA,
            fn (): int => (int) ShowcaseMedia::query()->firstOrFail()->getKey()
        );

        $companyB = $this->company('tenant-b-media');
        $this->showcase($companyB, CompanyShowcaseStatus::Published);
        $this->principal($companyB);

        // Le média d'un autre tenant est introuvable → 404.
        $this->deleteJson('/api/v1/showcase/media/'.$internalId)->assertStatus(404);
        $this->getJson('/api/v1/showcase/media')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Et son uuid n'est pas servi sur la vitrine publique d'un autre tenant.
        $this->getJson('/api/v1/public/vitrine/tenant-b-media/media/'.$uuid)->assertStatus(404);
    }

    public function test_media_list_and_delete_are_scoped_to_the_tenant(): void
    {
        $company = $this->company('acme-media-crud');
        $this->showcase($company);
        $this->principal($company);

        $uuid = (string) $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])->assertStatus(201)->json('data.id');

        $this->getJson('/api/v1/showcase/media')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $uuid)
            ->assertJsonPath('data.0.kind', 'logo');

        $internalId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => (int) ShowcaseMedia::query()->firstOrFail()->getKey()
        );

        $this->deleteJson('/api/v1/showcase/media/'.$internalId)->assertNoContent();

        $this->getJson('/api/v1/showcase/media')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_public_media_is_served_with_long_lived_cache_headers(): void
    {
        $company = $this->company('acme-public-media');
        $this->showcase($company, CompanyShowcaseStatus::Published);
        $this->principal($company);

        $uuid = (string) $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 128, 128),
        ])->assertStatus(201)->json('data.id');

        $response = $this->get('/api/v1/public/vitrine/acme-public-media/media/'.$uuid);

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith(
            'image/',
            (string) $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'max-age=31536000',
            (string) $response->headers->get('Cache-Control')
        );
        $this->assertStringContainsString(
            'immutable',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_svg_logo_is_served_with_a_restrictive_csp(): void
    {
        $company = $this->company('acme-svg-media');
        $this->showcase($company, CompanyShowcaseStatus::Published);
        $this->principal($company);

        $uuid = (string) $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
        ])->assertStatus(201)->json('data.id');

        $response = $this->get('/api/v1/public/vitrine/acme-svg-media/media/'.$uuid);

        $response->assertOk();

        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
    }

    public function test_draft_media_is_only_served_with_the_preview_token(): void
    {
        $company = $this->company('acme-draft-media');
        $this->showcase($company, CompanyShowcaseStatus::Draft, 'preview-secret');
        $this->principal($company);

        $uuid = (string) $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])->assertStatus(201)->json('data.id');

        $this->get('/api/v1/public/vitrine/acme-draft-media/media/'.$uuid)->assertStatus(404);

        $this->get('/api/v1/public/vitrine/acme-draft-media/media/'.$uuid.'?token=preview-secret')
            ->assertOk();

        $this->get('/api/v1/public/vitrine/acme-draft-media/media/'.$uuid.'?token=mauvais')
            ->assertStatus(404);
    }

    public function test_media_management_requires_a_manager_role(): void
    {
        $company = $this->company('acme-media-rbac');
        $this->showcase($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/showcase/media')->assertStatus(403);

        $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 32, 32),
        ])->assertStatus(403);
    }

    public function test_media_management_is_fail_closed_without_the_feature_flag(): void
    {
        $company = $this->company('acme-media-flag', withFeature: false);
        $this->showcase($company);
        $this->principal($company);

        $this->getJson('/api/v1/showcase/media')->assertStatus(403);
    }

    public function test_media_requires_an_existing_showcase(): void
    {
        $company = $this->company('acme-media-noshow');
        $this->principal($company);

        $this->getJson('/api/v1/showcase/media')->assertStatus(404);

        $this->upload([
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 32, 32),
        ])->assertStatus(404);
    }
}
