<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\CatalogInquiryReceived;
use App\Modules\CRM\Domain\Enums\CrmLeadStatus;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;

/**
 * BC-28 CATALOG (C-LEAD #6884) — consomme `catalog.inquiry_received` :
 * matérialise le prospect côté CRM BC-11 (tenant-scoped, source
 * `b2b_catalog`) puis notifie les managers du tenant (BC-13, canal in-app
 * `app` via CommunicationService — préférences/audit respectés).
 *
 * Exécuté en synchronie dans la requête publique : le contexte tenant est
 * déjà posé par le middleware `catalog.public` (`TenantManager`), donc les
 * écritures tombent dans le schéma du tenant concerné.
 */
class CreateCrmLeadFromCatalogInquiry
{
    public function __construct(private readonly CommunicationService $communicationService) {}

    public function handle(CatalogInquiryReceived $event): void
    {
        $company = Company::query()->find($event->companyId);

        if (! $company instanceof Company) {
            return;
        }

        $this->persistCrmLead($event);

        $this->notifyTenantManagers($event, $company);
    }

    private function persistCrmLead(CatalogInquiryReceived $event): void
    {
        $inquiry = $event->inquiry;

        $notes = sprintf(
            'Catalogue B2B — produit « %s » (%s)',
            (string) $inquiry->product_name,
            (string) $inquiry->product_slug
        );

        if (is_string($inquiry->message) && trim($inquiry->message) !== '') {
            $notes .= ' — '.trim($inquiry->message);
        }

        CrmLead::query()->create([
            'company_id' => $event->companyId,
            'company_name' => (string) $inquiry->company_name,
            'email' => (string) $inquiry->email,
            'source' => 'b2b_catalog',
            'status' => CrmLeadStatus::New->value,
            'notes' => $notes,
        ]);
    }

    private function notifyTenantManagers(CatalogInquiryReceived $event, Company $company): void
    {
        $managers = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh', 'manager'])
            ->limit(20)
            ->get();

        foreach ($managers as $manager) {
            $this->communicationService->notifyEmployee(
                $manager,
                'catalog.inquiry_received',
                [
                    'category' => 'catalog',
                    'data' => [
                        'product' => (string) $event->inquiry->product_name,
                        'company_name' => (string) $event->inquiry->company_name,
                    ],
                ],
                ['app']
            );
        }
    }
}
