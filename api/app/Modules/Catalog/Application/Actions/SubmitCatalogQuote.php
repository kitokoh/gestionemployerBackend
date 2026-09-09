<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Notifications\Contracts\InAppNotifier;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Mail\CommunicationMail;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Domain\Models\CatalogQuote;
use App\Modules\Catalog\Domain\Support\CatalogEvents;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Soumission d'une demande de devis/contact B2B (BC-28 CATALOG, #6884 — C-LEAD).
 *
 * Flux : produit PUBLIÉ du tenant exigé (404 sinon) → événement-chaîne
 * `catalog.quote.requested` (consommé par CRM BC-11 pour créer le lead —
 * aucun import cross-BC) → notification in-app des managers du tenant
 * (contrat Core InAppNotifier, best-effort) → confirmation acheteur par
 * email (best-effort, pattern #2620). Aucune donnée d'acheteur n'est
 * jamais exposée publiquement.
 */
class SubmitCatalogQuote
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{status: string, reference: string}
     */
    public function execute(Company $company, array $validated): array
    {
        $product = $this->findPublishedProduct($company, (string) $validated['product_slug']);

        if ($product === null) {
            abort(404);
        }

        $reference = (string) Str::uuid();
        $consentedAt = now();

        // #6885 : enregistrement structuré de la demande (back-office) —
        // table tenant du module, workflow de statuts dédié. Le lead CRM
        // BC-11 reste l'intégration commerciale (#6884), corrélable par
        // `reference`.
        try {
            $this->tenantManager->withinTenant($company, function () use ($company, $validated, $product, $reference, $consentedAt): void {
                CatalogQuote::query()->create([
                    'company_id' => (string) $company->id,
                    'reference' => $reference,
                    'product_slug' => $product->slug,
                    'product_name' => $product->name,
                    'quantity' => isset($validated['quantity']) ? (int) $validated['quantity'] : null,
                    'buyer_company' => (string) $validated['buyer_company'],
                    'contact_name' => (string) $validated['contact_name'],
                    'email' => (string) $validated['email'],
                    'phone' => isset($validated['phone']) && $validated['phone'] !== null ? (string) $validated['phone'] : null,
                    'message' => isset($validated['message']) && $validated['message'] !== null ? (string) $validated['message'] : null,
                    'status' => 'new',
                    'consented_at' => $consentedAt,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('catalog.quote.persist_failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            abort(500);
        }

        $payload = [
            'company_id' => (string) $company->id,
            'product_slug' => $product->slug,
            'product_name' => $product->name,
            'quantity' => isset($validated['quantity']) ? (int) $validated['quantity'] : null,
            'buyer_company' => (string) $validated['buyer_company'],
            'contact_name' => (string) $validated['contact_name'],
            'email' => (string) $validated['email'],
            'phone' => isset($validated['phone']) && $validated['phone'] !== null ? (string) $validated['phone'] : null,
            'message' => isset($validated['message']) && $validated['message'] !== null ? (string) $validated['message'] : null,
            'consented_at' => $consentedAt->toIso8601String(),
            'reference' => $reference,
        ];

        // Création du lead CRM (BC-11) : événement-chaîne, écouté par le
        // module CRM — fail-safe : un échec d'écoute ne casse pas la
        // soumission (pattern listeners auxiliaires #6958).
        try {
            Event::dispatch(CatalogEvents::QUOTE_REQUESTED, [$payload]);
        } catch (\Throwable $e) {
            Log::error('catalog.quote.lead_capture_failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        $this->notifyTenantManagers($company, $payload);
        $this->sendBuyerConfirmation($payload);

        return [
            'status' => 'received',
            'reference' => $reference,
        ];
    }

    private function findPublishedProduct(Company $company, string $slug): ?CatalogProduct
    {
        return $this->tenantManager->withinTenant($company, function () use ($company, $slug): ?CatalogProduct {
            /** @var CatalogProduct|null $product */
            $product = CatalogProduct::query()
                ->where('company_id', $company->id)
                ->where('slug', $slug)
                ->where('status', CatalogProductStatus::Published->value)
                ->first();

            return $product;
        });
    }

    /**
     * Notification in-app des managers du tenant (BC-13 via contrat Core,
     * best-effort — jamais bloquant).
     *
     * @param  array<string, mixed>  $payload
     */
    private function notifyTenantManagers(Company $company, array $payload): void
    {
        try {
            $managerIds = Employee::query()
                ->where('company_id', $company->id)
                ->where('role', 'manager')
                ->where('status', 'active')
                ->pluck('id');

            foreach ($managerIds as $managerId) {
                $title = (string) __('catalog.quote_notification_title');
                $body = (string) __('catalog.quote_notification_body', [
                    'contact' => (string) $payload['contact_name'],
                    'company' => (string) $payload['buyer_company'],
                    'product' => (string) $payload['product_name'],
                ]);

                $this->notifier->dispatch(
                    (int) $managerId,
                    'catalog.quote_received',
                    $title,
                    $body,
                    ['reference' => (string) $payload['reference']],
                    '/catalog/quotes/'.(string) $payload['reference'],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('catalog.quote.manager_notification_failed', [
                'company_id' => (string) $company->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Confirmation acheteur (email best-effort — un mailer non configuré ne
     * fait pas échouer la demande, pattern #2620).
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendBuyerConfirmation(array $payload): void
    {
        try {
            Mail::to((string) $payload['email'])->send(new CommunicationMail(
                subjectLine: __('catalog.quote_email_subject'),
                bodyText: __('catalog.quote_email_body', [
                    'product' => (string) $payload['product_name'],
                    'reference' => (string) $payload['reference'],
                ]),
            ));
        } catch (\Throwable $e) {
            Log::warning('catalog.quote.buyer_email_failed', [
                'reference' => (string) $payload['reference'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
