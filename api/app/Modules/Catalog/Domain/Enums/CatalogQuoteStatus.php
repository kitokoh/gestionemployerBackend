<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

/**
 * Workflow des demandes de devis B2B (BC-28 CATALOG, #6885).
 *
 * `new` → `contacted` → `quote_sent` → `closed|lost` ; `lost` est aussi
 * accessible depuis `contacted` (le fournisseur abandonne avant d'avoir
 * envoyé un devis). `closed`/`lost` sont terminaux. Whitelist stricte :
 * un statut inconnu est rejeté en 422, jamais accepté silencieusement.
 */
enum CatalogQuoteStatus: string
{
    case New = 'new';

    case Contacted = 'contacted';

    case QuoteSent = 'quote_sent';

    case Closed = 'closed';

    case Lost = 'lost';

    /**
     * Transitions autorisées depuis un statut donné.
     *
     * @return list<string>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Contacted->value],
            self::Contacted => [self::QuoteSent->value, self::Lost->value],
            self::QuoteSent => [self::Closed->value, self::Lost->value],
            self::Closed, self::Lost => [],
        };
    }

    public function canTransitionTo(CatalogQuoteStatus $target): bool
    {
        return in_array($target->value, $this->allowedTransitions(), true);
    }
}
