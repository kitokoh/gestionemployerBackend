<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

/**
 * Statut d'une demande de devis B2B (BC-28 CATALOG, C-LEAD #6884).
 *
 * Whitelist stricte (pattern CrmLeadStatus ADR-CRM-005) : les statuts du
 * back-office tenant (#6885) — v1 (C-LEAD) n'écrit que `new` à la
 * réception ; les transitions relèvent du back-office.
 */
enum CatalogInquiryStatus: string
{
    case New = 'new';

    case Contacted = 'contacted';

    case QuoteSent = 'quote_sent';

    case Closed = 'closed';

    case Lost = 'lost';

    /**
     * Transitions autorisées du back-office tenant (C-BACKOFFICE #6885,
     * spec §7 : « nouveau → contacté → devis envoyé → clos/perdu »).
     * `closed` et `lost` sont terminaux ; toute autre transition → 422.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Lost],
            self::Contacted => [self::QuoteSent, self::Lost],
            self::QuoteSent => [self::Closed, self::Lost],
            self::Closed, self::Lost => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed || $this === self::Lost;
    }
}
