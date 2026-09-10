<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Showcase\Domain\Models\ShowcaseContactMessage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-27 SHOWCASE (#6875 V-RGPD) — un visiteur a envoyé un message via le
 * formulaire de contact public d'une vitrine.
 *
 * Contrat cross-BC : le module Showcase ne touche jamais aux tables d'un
 * autre bounded context — les listeners (notification tenant BC-13)
 * consomment cet événement dans le même cycle de requête (contexte tenant
 * déjà posé). Le payload d'événement ne porte AUCUNE PII (identifiant de
 * message uniquement) : le détail est relu côté listener.
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`showcase.contact_received` v1.0.0).
 */
class ShowcaseContactReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly ShowcaseContactMessage $message,
    ) {}
}
