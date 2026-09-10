<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseContactMessage;
use Illuminate\Support\Carbon;

/**
 * BC-27 SHOWCASE (#6875 V-RGPD) — enregistrement d'un message du formulaire
 * de contact public.
 *
 * Minimisation : seuls nom, e-mail et message sont persistés, avec
 * l'horodatage du consentement explicite, une empreinte IP hachée (SHA-256,
 * jamais l'IP en clair) et une date de rétention bornée
 * (`RETENTION_DAYS`). La notification des responsables du tenant (BC-13) est
 * portée par l'événement `ShowcaseContactReceived` déclenché par le
 * contrôleur (contrat cross-BC — jamais d'import Notification depuis ce
 * module).
 */
final class SubmitShowcaseContactAction
{
    /** Conservation du message avant purge (jours) — base légale : intérêt légitime. */
    public const RETENTION_DAYS = 180;

    public function execute(
        CompanyShowcase $showcase,
        string $name,
        string $email,
        string $message,
        ?string $ipAddress,
    ): ShowcaseContactMessage {
        $consentAt = Carbon::now();
        $hashedIp = $this->hashIp($ipAddress);

        /** @var ShowcaseContactMessage $contact */
        $contact = ShowcaseContactMessage::query()->create([
            'company_id' => $showcase->company_id,
            'showcase_id' => $showcase->id,
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'consent_at' => $consentAt,
            'retention_until' => $consentAt->copy()->addDays(self::RETENTION_DAYS)->toDateString(),
            'ip_hash' => $hashedIp,
        ]);

        return $contact;
    }

    private function hashIp(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '' || $ipAddress === '127.0.0.1' || $ipAddress === '::1') {
            return null;
        }

        return hash('sha256', $ipAddress);
    }
}
