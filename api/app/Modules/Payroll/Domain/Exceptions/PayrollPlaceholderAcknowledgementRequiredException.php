<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Pays à barèmes « placeholder » (aucune valeur légale implémentée, #5623) :
 * le calcul d'un run RÉEL exige la confirmation explicite
 * `acknowledge_placeholder` (#2332) — aucun changement de statut avant le 422.
 */
class PayrollPlaceholderAcknowledgementRequiredException extends \RuntimeException
{
    public function __construct(
        public readonly string $countryCode,
    ) {
        parent::__construct("Confirmation placeholder requise pour le pays {$countryCode}.");
    }
}
