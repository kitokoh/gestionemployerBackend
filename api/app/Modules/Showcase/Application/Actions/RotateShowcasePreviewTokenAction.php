<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use Illuminate\Support\Str;

/**
 * BC-27 SHOWCASE (#6871, V-PUBLISH) — jeton d'aperçu privé d'un brouillon.
 *
 * Génère (ou régénère) un jeton aléatoire de 64 caractères stocké sur la
 * vitrine : `GET /public/vitrine/{slug}?token=...` sert alors le brouillon
 * au demandeur (réponse `X-Robots-Tag: noindex`, jamais indexable) sans le
 * publier. Régénérer invalide l'ancien jeton. Un jeton n'est jamais exposé
 * par l'API publique.
 */
final class RotateShowcasePreviewTokenAction
{
    public function execute(CompanyShowcase $showcase, ?int $actorId = null): string
    {
        $token = Str::random(64);

        $showcase->preview_token = $token;
        $showcase->save();

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.preview_token_rotated',
            'auditable_type' => CompanyShowcase::class,
            'auditable_id' => $showcase->id,
            'old_values' => [],
            'new_values' => ['preview_token_rotated' => true],
        ]);

        return $token;
    }
}
