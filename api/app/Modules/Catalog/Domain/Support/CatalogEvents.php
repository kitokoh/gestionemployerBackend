<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Support;

/**
 * Contrat d'intégration public du catalogue B2B (BC-28 CATALOG, #6884).
 *
 * Événements nommés par CHAÎNE (pas de classe partagée) : le module CRM
 * (BC-11) écoute `catalog.quote.requested` via une chaîne littérale —
 * aucun import cross-BC, aucune arête MAT-002 requise (spec C-LEAD :
 * « événement/contrat — pas d'import cross-BC direct »).
 *
 * Payload (1er argument, array) :
 * [
 *   'company_id' => string, 'product_slug' => string, 'product_name' => string,
 *   'quantity' => int|null, 'buyer_company' => string, 'contact_name' => string,
 *   'email' => string, 'phone' => string|null, 'message' => string|null,
 *   'consented_at' => string|null (ISO-8601), 'reference' => string,
 * ]
 */
final class CatalogEvents
{
    /** Demande de devis/contact B2B reçue sur le catalogue public. */
    public const QUOTE_REQUESTED = 'catalog.quote.requested';
}
