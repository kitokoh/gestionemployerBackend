<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Listeners;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\CRM\Domain\Enums\CrmLeadStatus;
use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Support\Facades\Log;

/**
 * Création d'un lead CRM BC-11 à partir d'une demande de devis B2B reçue
 * sur le catalogue public d'un tenant (BC-28, #6884 — C-LEAD).
 *
 * Écoute l'événement-chaîne `catalog.quote.requested` (constante
 * `CatalogEvents::QUOTE_REQUESTED` = 'catalog.quote.requested', module
 * Catalog) — contrat par CHAÎNE, aucun import cross-BC (spec C-LEAD).
 * Fail-safe : un échec ici est loggé, jamais propagé au flux public.
 *
 * Trace RGPD : le consentement explicite de l'acheteur est consigné dans
 * les notes du lead (finalité « traitement de la demande de devis »,
 * horodaté) — l'inventaire/registre RGPD complet est couvert par C-RGPD
 * (#6889). Aucune donnée d'acheteur n'est exposée publiquement.
 */
class CaptureCatalogQuoteListener
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    /**
     * @param  array{
     *   company_id: string,
     *   product_slug: string,
     *   product_name: string,
     *   quantity: int|null,
     *   buyer_company: string,
     *   contact_name: string,
     *   email: string,
     *   phone: string|null,
     *   message: string|null,
     *   consented_at: string|null,
     *   reference: string
     * }  $payload
     */
    public function handle(array $payload): void
    {
        $company = Company::query()->find($payload['company_id']);

        if ($company === null) {
            Log::warning('catalog.quote.listener_company_missing', ['reference' => $payload['reference']]);

            return;
        }

        [$firstName, $lastName] = $this->splitContactName((string) $payload['contact_name']);

        $notes = $this->buildNotes($payload);

        try {
            $this->tenantManager->withinTenant($company, function () use ($company, $payload, $firstName, $lastName, $notes): void {
                CrmLead::query()->create([
                    'company_id' => (string) $company->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company_name' => (string) $payload['buyer_company'],
                    'email' => (string) $payload['email'],
                    'phone' => $payload['phone'],
                    'source' => 'catalog',
                    'status' => CrmLeadStatus::New->value,
                    'tags' => ['b2b_catalog', 'quote'],
                    'notes' => $notes,
                ]);
            });

            Log::info('catalog.quote.lead_created', [
                'company_id' => (string) $company->id,
                'reference' => (string) $payload['reference'],
            ]);
        } catch (\Throwable $e) {
            Log::error('catalog.quote.lead_creation_failed', [
                'company_id' => (string) $company->id,
                'reference' => (string) $payload['reference'],
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitContactName(string $contactName): array
    {
        $parts = preg_split('/\s+/', trim($contactName), 2);

        if ($parts === false || $parts[0] === '') {
            return ['Contact', ''];
        }

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildNotes(array $payload): string
    {
        $lines = [
            'Demande de devis B2B (catalogue public)',
            'Produit : '.$payload['product_name'].' ('.$payload['product_slug'].')',
        ];

        if ($payload['quantity'] !== null) {
            $lines[] = 'Quantité : '.$payload['quantity'];
        }

        if (is_string($payload['message']) && $payload['message'] !== '') {
            $lines[] = 'Message : '.$payload['message'];
        }

        if (is_string($payload['consented_at']) && $payload['consented_at'] !== '') {
            $lines[] = 'Consentement RGPD explicite (finalité : traitement de la demande) — '.$payload['consented_at'];
        }

        return implode("\n", $lines);
    }
}
