<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseContactMessage;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6875, V-RGPD) — conformité publique.
 *
 * Bloc mentions légales / confidentialité présent par défaut et éditable,
 * déclaration « aucun cookie tiers », formulaire de contact minimisé
 * (consentement obligatoire, honeypot anti-spam, rétention bornée, IP
 * hachée), notification des responsables (BC-13) et revue de non-fuite du
 * DTO public.
 */
class ShowcaseRgpdTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => 'acme-rgpd']);
        $company->setFeature('company_showcase', true);
        $company->save();
        $this->company = $company;

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->principal = $principal;

        $this->publishedShowcase($company);
    }

    private function publishedShowcase(Company $company): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Published,
                'theme' => 'default',
                'settings' => ['brand_name' => 'Acme Industries'],
                'published_at' => now(),
            ]);

            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'hero',
                'content' => ['heading' => 'Acme Industries'],
                'sort_order' => 10,
            ]);

            return $showcase;
        });
    }

    public function test_published_page_exposes_default_legal_notice_and_no_third_party_cookies(): void
    {
        $payload = $this->getJson('/api/v1/public/vitrine/acme-rgpd')->assertOk()->json('data');

        $this->assertNotEmpty($payload['legal']['notice']);
        $this->assertNotEmpty($payload['legal']['privacy']);
        $this->assertFalse($payload['cookies']['third_party']);
        $this->assertFalse($payload['cookies']['banner_required']);

        // Aucune donnée interne dans le DTO public.
        foreach (['id', 'company_id', 'preview_token', 'status', 'created_at', 'updated_at'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload, "Le DTO public ne doit pas exposer « {$forbidden} ».");
        }
    }

    public function test_tenant_can_edit_legal_block_and_brand_variables_are_allowlisted(): void
    {
        Sanctum::actingAs($this->principal);

        $this->patchJson('/api/v1/showcase/settings', [
            'legal' => [
                'notice' => 'Mentions légales personnalisées SARL Acme.',
                'privacy' => 'Politique de confidentialité personnalisée.',
                'contact_email' => 'contact@acme.dz',
            ],
            'settings' => [
                'brand_name' => 'Acme Industries',
                'logo_id' => 999, // clé interne — doit être ignorée
            ],
        ])->assertOk();

        $payload = $this->getJson('/api/v1/public/vitrine/acme-rgpd')->assertOk()->json('data');

        $this->assertSame('Mentions légales personnalisées SARL Acme.', $payload['legal']['notice']);
        $this->assertSame('Politique de confidentialité personnalisée.', $payload['legal']['privacy']);
        $this->assertSame('contact@acme.dz', $payload['legal']['contact_email']);
        $this->assertArrayNotHasKey('logo_id', $payload['settings'], 'les clés internes doivent être ignorées (allowlist).');
    }

    public function test_contact_form_persists_a_minimized_consented_message(): void
    {
        $this->postJson('/api/v1/public/vitrine/acme-rgpd/contact', [
            'name' => 'Amina Haddad',
            'email' => 'amina@example.dz',
            'message' => 'Bonjour, je souhaite un devis.',
            'consent' => 1,
        ])->assertStatus(201)->assertJsonPath('data.status', 'received');

        app(TenantManager::class)->withinTenant($this->company, function (): void {
            /** @var ShowcaseContactMessage $message */
            $message = ShowcaseContactMessage::query()->firstOrFail();

            $this->assertSame('Amina Haddad', $message->name);
            $this->assertSame('amina@example.dz', $message->email);
            $this->assertNotNull($message->consent_at);
            $this->assertNotNull($message->retention_until);
        });
    }

    public function test_contact_form_requires_consent(): void
    {
        $this->postJson('/api/v1/public/vitrine/acme-rgpd/contact', [
            'name' => 'Amina Haddad',
            'email' => 'amina@example.dz',
            'message' => 'Bonjour.',
        ])->assertStatus(422)->assertJsonValidationErrors('consent');

        app(TenantManager::class)->withinTenant($this->company, function (): void {
            $this->assertSame(0, ShowcaseContactMessage::query()->count());
        });
    }

    public function test_contact_form_honeypot_is_a_silent_fake_success(): void
    {
        $this->postJson('/api/v1/public/vitrine/acme-rgpd/contact', [
            'company_website' => 'https://spam.example',
            'name' => 'Bot',
            'email' => 'bot@spam.example',
            'message' => 'spam',
            'consent' => 1,
        ])->assertStatus(201)->assertJsonPath('data.status', 'received');

        app(TenantManager::class)->withinTenant($this->company, function (): void {
            $this->assertSame(0, ShowcaseContactMessage::query()->count());
        });
    }

    public function test_contact_form_is_closed_on_draft_showcase(): void
    {
        app(TenantManager::class)->withinTenant($this->company, function (): void {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->firstOrFail();
            $showcase->status = CompanyShowcaseStatus::Draft;
            $showcase->save();
        });

        $this->postJson('/api/v1/public/vitrine/acme-rgpd/contact', [
            'name' => 'Amina Haddad',
            'email' => 'amina@example.dz',
            'message' => 'Bonjour.',
            'consent' => 1,
        ])->assertStatus(404);
    }

    public function test_contact_form_notifies_tenant_managers(): void
    {
        $this->postJson('/api/v1/public/vitrine/acme-rgpd/contact', [
            'name' => 'Amina Haddad',
            'email' => 'amina@example.dz',
            'message' => 'Bonjour, je souhaite un devis.',
            'consent' => 1,
        ])->assertStatus(201);

        app(TenantManager::class)->withinTenant($this->company, function (): void {
            $this->assertGreaterThanOrEqual(
                1,
                Notification::query()->where('employee_id', $this->principal->id)->count(),
                'le responsable du tenant doit être notifié (BC-13).'
            );
        });
    }
}
