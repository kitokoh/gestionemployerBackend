<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Events\ShowcaseContactReceived;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Application\Actions\SubmitShowcaseContactAction;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseContactMessage;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\StoreShowcaseContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * BC-27 SHOWCASE (#6875 V-RGPD) — formulaire de contact public d'une vitrine.
 *
 * `POST /public/vitrine/{slug}/contact` — SANS auth (route isolée, rate limit
 * `throttle:shop-public` + `throttle:showcase-contact`). Le tenant est résolu
 * par slug (404 fail-closed `noindex` si société suspendue / vitrine absente
 * ou non publiée).
 *
 * Flux : honeypot (201 factice, rien n'est persisté) → validation stricte
 * (consentement RGPD obligatoire) → enregistrement minimisé
 * (`showcase_contact_messages`, rétention bornée) → événement
 * `ShowcaseContactReceived` (notification tenant BC-13, aucun import
 * cross-BC direct).
 */
final class ShowcasePublicContactController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly SubmitShowcaseContactAction $submitContact,
    ) {}

    public function store(StoreShowcaseContactRequest $request, string $slug): JsonResponse
    {
        // Honeypot anti-spam : un bot qui remplit le champ caché reçoit un
        // accusé factice — aucun message n'est persisté.
        if ($request->filled('company_website')) {
            return new JsonResponse(['data' => ['status' => 'received']], Response::HTTP_CREATED);
        }

        /** @var Company|null $company */
        $company = Company::query()
            ->where('slug', $slug)
            ->where('status', '!=', 'suspended')
            ->first();

        if (! $company instanceof Company) {
            return $this->notFound();
        }

        $name = (string) $request->validated('name');
        $email = (string) $request->validated('email');
        $message = (string) $request->validated('message');
        $ip = $request->ip();

        /** @var ShowcaseContactMessage|null $contact */
        $contact = $this->tenantManager->withinTenant($company, function () use ($company, $slug, $name, $email, $message, $ip): ?ShowcaseContactMessage {
            /** @var CompanyShowcase|null $showcase */
            $showcase = CompanyShowcase::query()
                ->where('slug', $slug)
                ->first();

            if (! $showcase instanceof CompanyShowcase || $showcase->status !== CompanyShowcaseStatus::Published) {
                return null;
            }

            $contact = $this->submitContact->execute($showcase, $name, $email, $message, $ip);

            // Contrat cross-BC : la notification tenant (BC-13) est créée par
            // le listener dans le même cycle de requête, contexte tenant posé.
            event(new ShowcaseContactReceived((string) $company->id, $contact));

            return $contact;
        });

        if (! $contact instanceof ShowcaseContactMessage) {
            return $this->notFound();
        }

        return new JsonResponse([
            'data' => [
                'status' => 'received',
                'reference' => $contact->id,
            ],
        ], Response::HTTP_CREATED);
    }

    private function notFound(): JsonResponse
    {
        return (new JsonResponse(['message' => 'Not Found'], Response::HTTP_NOT_FOUND))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
