<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-28 CATALOG (C-LEAD #6884) — Une demande de devis B2B a été reçue via
 * le formulaire public du catalogue d'un tenant.
 *
 * Contrat cross-BC (spec §7 : « lead BC-11 via le mécanisme d'intégration
 * propre (événement/contrat — pas d'import cross-BC direct) ») : Catalog
 * ne touche JAMAIS aux tables d'un autre bounded context — les listeners
 * (CRM BC-11, notification BC-13) consomment cet événement dans le même
 * cycle de requête (contexte tenant déjà posé par le middleware
 * `catalog.public`).
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`catalog.inquiry_received` v1.0.0).
 */
class CatalogInquiryReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly CatalogInquiry $inquiry,
    ) {}
}
