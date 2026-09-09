<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CatalogInquiryErased;
use App\Modules\CRM\Domain\Models\CrmLead;

/**
 * BC-28 CATALOG (C-RGPD #6889) — consomme `catalog.inquiry_erased` :
 * propage l'effacement RGPD aux leads CRM BC-11 issus du catalogue
 * (source `b2b_catalog`, même email acheteur) — les données acheteur ne
 * survivent pas à la demande dans un autre bounded context.
 *
 * Exécuté en synchronie dans le cycle de la requête tenant (contexte posé
 * par le middleware) ou de la commande de purge (withinTenant).
 */
class EraseCrmLeadsOnCatalogInquiryErased
{
    public function handle(CatalogInquiryErased $event): void
    {
        CrmLead::query()
            ->where('company_id', $event->companyId)
            ->where('source', 'b2b_catalog')
            ->where('email', $event->buyerEmail)
            ->delete();
    }
}
