<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Catalog\Domain\Models\CatalogQuote;

/**
 * RBAC du back-office des demandes de devis B2B (BC-28, #6885).
 *
 * Les demandes portent des données acheteur (RGPD) : liste/lecture/gestion
 * réservées au responsable du tenant (`principal`/`rh`). deny-by-default.
 */
class CatalogQuotePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function view(Employee $actor, CatalogQuote $quote): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $quote->company_id === (string) $actor->company_id;
    }

    public function update(Employee $actor, CatalogQuote $quote): bool
    {
        return $this->view($actor, $quote);
    }
}
