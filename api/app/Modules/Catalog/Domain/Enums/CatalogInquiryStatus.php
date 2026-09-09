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
}
