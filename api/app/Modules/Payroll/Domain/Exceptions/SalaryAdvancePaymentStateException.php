<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Plan 60 — double validation des avances : l'update conditionnel atomique de
 * déclaration de paiement (#3429/#2997, anti-TOCTOU) a affecté 0 ligne — soit
 * l'avance a déjà été déclarée payée (conflit), soit elle n'existe plus dans
 * la société de l'acteur. L'interface distingue les deux cas (404 vs 422).
 */
class SalaryAdvancePaymentStateException extends \RuntimeException
{
}
