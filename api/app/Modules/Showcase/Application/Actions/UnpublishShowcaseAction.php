<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;

/**
 * BC-27 SHOWCASE (#6871, V-PUBLISH) — dépublication d'une vitrine.
 *
 * Repasse la vitrine en `draft` (elle disparaît de la route publique :
 * 404), remet `published_at` à null (l'historique reste dans l'audit) et
 * invalide le cache public. Idempotent : dépublier un brouillon ne fait
 * rien d'autre que rafraîchir le cache.
 */
final class UnpublishShowcaseAction
{
    public function __construct(private readonly ShowcasePublicCache $cache) {}

    public function execute(CompanyShowcase $showcase, ?int $actorId = null): CompanyShowcase
    {
        $previousStatus = $showcase->status;

        $showcase->status = CompanyShowcaseStatus::Draft;
        $showcase->published_at = null;
        $showcase->save();

        $this->cache->forget($showcase->slug);

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.unpublished',
            'auditable_type' => CompanyShowcase::class,
            'auditable_id' => $showcase->id,
            'old_values' => ['status' => $previousStatus->value],
            'new_values' => ['status' => $showcase->status->value],
        ]);

        return $showcase;
    }
}
