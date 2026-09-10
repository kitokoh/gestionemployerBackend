<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6874 V-I18N) — contenu multilingue des sections
 * (fr/en/ar/tr) : validation des surcouches par locale (422), rendu public
 * selon `?lang=` / `Accept-Language`, direction RTL arabe, cache public par
 * locale et rendu SSR localisé.
 */
class ShowcaseSectionI18nTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug = 'acme-i18n'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => $slug,
        ]);
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

    private function showcase(Company $company): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Draft,
                'theme' => 'default',
            ]);

            return $showcase;
        });
    }

    public function test_section_accepts_partial_translations_per_locale(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->showcase($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries', 'subheading' => 'Slogan FR'],
            'translations' => [
                'en' => ['heading' => 'Acme Industries', 'subheading' => 'EN tagline'],
                'ar' => ['heading' => 'أكمي للصناعات'],
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'hero')
            ->assertJsonPath('data.content.heading', 'Acme Industries')
            ->assertJsonPath('data.translations.en.subheading', 'EN tagline')
            ->assertJsonPath('data.translations.ar.heading', 'أكمي للصناعات');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->showcase($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
            'translations' => ['de' => ['heading' => 'Acme']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['translations.de']);
    }

    public function test_reference_locale_is_rejected_in_translations(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->showcase($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
            'translations' => ['fr' => ['heading' => 'Acme fr']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['translations.fr']);
    }

    public function test_unknown_field_in_translation_is_rejected(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->showcase($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
            'translations' => ['en' => ['script_raw' => '<script>alert(1)</script>']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['translations.en']);
    }

    public function test_update_replaces_and_purges_translation_map(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->showcase($company);

        $sectionId = $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
            'translations' => ['en' => ['heading' => 'EN heading']],
        ])->assertStatus(201)->json('data.id');

        $this->patchJson("/api/v1/showcase/sections/{$sectionId}", [
            'translations' => ['ar' => ['heading' => 'عنوان']],
        ])
            ->assertOk()
            ->assertJsonPath('data.translations.ar.heading', 'عنوان')
            ->assertJsonMissingPath('data.translations.en');

        $this->patchJson("/api/v1/showcase/sections/{$sectionId}", ['translations' => []])
            ->assertOk()
            ->assertJsonMissingPath('data.translations.ar');
    }

    public function test_public_rendering_follows_lang_query_and_accept_language(): void
    {
        $company = $this->company();
        $this->principal($company);
        $showcase = $this->showcase($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
            'translations' => [
                'en' => ['heading' => 'Acme Industries EN'],
                'ar' => ['heading' => 'أكمي للصناعات'],
            ],
        ])->assertStatus(201);

        app(TenantManager::class)->withinTenant($company, function () use ($showcase): void {
            $showcase->status = CompanyShowcaseStatus::Published;
            $showcase->published_at = now();
            $showcase->save();
        });

        // Défaut : contenu de référence fr.
        $this->getJson('/api/v1/public/vitrine/acme-i18n')
            ->assertOk()
            ->assertJsonPath('data.lang', 'fr')
            ->assertJsonPath('data.direction', 'ltr')
            ->assertJsonPath('data.sections.0.content.heading', 'Acme Industries')
            ->assertJsonPath('data.available_locales', ['fr', 'en', 'ar']);

        // Sélecteur exposé `?lang=`.
        $this->getJson('/api/v1/public/vitrine/acme-i18n?lang=ar')
            ->assertOk()
            ->assertJsonPath('data.lang', 'ar')
            ->assertJsonPath('data.direction', 'rtl')
            ->assertJsonPath('data.sections.0.content.heading', 'أكمي للصناعات');

        // `Accept-Language`.
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9,fr;q=0.5')
            ->getJson('/api/v1/public/vitrine/acme-i18n')
            ->assertOk()
            ->assertJsonPath('data.lang', 'en')
            ->assertJsonPath('data.sections.0.content.heading', 'Acme Industries EN');

        // Locale supportée mais sans surcouche : repli sur le contenu de référence.
        $this->getJson('/api/v1/public/vitrine/acme-i18n?lang=tr')
            ->assertOk()
            ->assertJsonPath('data.lang', 'tr')
            ->assertJsonPath('data.sections.0.content.heading', 'Acme Industries');
    }

    public function test_public_cache_is_keyed_per_locale(): void
    {
        $company = $this->company();
        $this->principal($company);
        $showcase = $this->showcase($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
            'translations' => ['en' => ['heading' => 'EN heading']],
        ])->assertStatus(201);

        app(TenantManager::class)->withinTenant($company, function () use ($showcase): void {
            $showcase->status = CompanyShowcaseStatus::Published;
            $showcase->published_at = now();
            $showcase->save();
        });

        $this->getJson('/api/v1/public/vitrine/acme-i18n?lang=en')->assertOk();

        $this->assertTrue(Cache::has(ShowcasePublicCache::key('acme-i18n', 'en')));
        $this->assertFalse(Cache::has(ShowcasePublicCache::key('acme-i18n', 'fr')));
    }

    public function test_ssr_page_sets_arabic_direction(): void
    {
        $company = $this->company();
        $this->principal($company);
        $showcase = $this->showcase($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
            'translations' => ['ar' => ['heading' => 'أكمي للصناعات']],
        ])->assertStatus(201);

        app(TenantManager::class)->withinTenant($company, function () use ($showcase): void {
            $showcase->status = CompanyShowcaseStatus::Published;
            $showcase->published_at = now();
            $showcase->save();
        });

        $this->get('/vitrine/acme-i18n?lang=ar')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('أكمي للصناعات', false);

        $this->get('/vitrine/acme-i18n?lang=en')
            ->assertOk()
            ->assertSee('dir="ltr"', false);
    }
}
