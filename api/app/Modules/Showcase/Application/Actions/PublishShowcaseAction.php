<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Support\Carbon;

/**
 * BC-27 SHOWCASE (#6871, V-PUBLISH) — publication d'une vitrine (1-clic).
 *
 * - passe `status` à `published` et pose `published_at` (horodatage de
 *   publication / re-publication) ;
 * - révoque le jeton d'aperçu (un brouillon publié n'a plus d'aperçu privé) ;
 * - invalide le cache public (clé = slug) : la publication est visible
 *   immédiatement sur `/public/vitrine/{slug}` ;
 * - journalise l'action dans `audit_logs` (qui / quand / état précédent).
 *
 * Idempotent : republier une vitrine déjà publiée rafraîchit `published_at`
 * (re-publication après édition). Le contrôleur porte le RBAC (Policy
 * `publish`) et l'isolation tenant (scope `company_id`).
 */
final class PublishShowcaseAction
{
    public function __construct(private readonly ShowcasePublicCache $cache) {}

    public function execute(CompanyShowcase $showcase, ?int $actorId = null): CompanyShowcase
    {
        $previousStatus = $showcase->status;

        $showcase->status = CompanyShowcaseStatus::Published;
        $showcase->published_at = Carbon::now();
        $showcase->preview_token = null;
        $showcase->save();

        $this->cache->forget($showcase->slug);

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.published',
            'auditable_type' => CompanyShowcase::class,
            'auditable_id' => $showcase->id,
            'old_values' => ['status' => $previousStatus->value],
            'new_values' => [
                'status' => $showcase->status->value,
                'published_at' => $showcase->published_at?->toIso8601String(),
            ],
        ]);

        return $showcase;
    }
}
