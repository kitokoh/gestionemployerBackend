<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-28 CATALOG (C-RGPD #6889) — Une demande de devis B2B a été EFFACÉE
 * (droit d'effacement RGPD, canal : support tenant).
 *
 * L'événement porte uniquement ce qui est nécessaire aux consommateurs pour
 * propager l'effacement (jamais le corps du message) : company + email
 * acheteur. Consommé par CRM (suppression des leads BC-11 `b2b_catalog`
 * liés au même email) — contrat cross-BC (pattern C-LEAD #6884).
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`catalog.inquiry_erased` v1.0.0).
 */
class CatalogInquiryErased
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly string $buyerEmail,
        public readonly int $inquiryId,
    ) {}
}
