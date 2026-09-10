<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6871, V-PUBLISH) — publication d'une vitrine.
 *
 * - Refuse (422) une vitrine sans aucune section : une vitrine publiée
 *   n'est jamais une page vide ;
 * - passe `status` à `published`, pose `published_at` (horodatage de
 *   publication / re-publication) et **révoque** le jeton d'aperçu ;
 * - invalide le cache public (clé = slug) pour que la publication soit
 *   visible immédiatement ;
 * - journalise l'action (audit : qui / quand / état précédent).
 *
 * Idempotent : republier une vitrine déjà publiée rafraîchit `published_at`
 * (re-publication après édition — cas V-PUBLISH).
 */
final class PublishShowcaseAction
{
    public function __construct(private readonly ShowcasePublicCache $cache) {}

    public function execute(CompanyShowcase $showcase, ?int $actorId = null): CompanyShowcase
    {
        $hasSections = CompanyShowcaseSection::query()
            ->where('showcase_id', $showcase->id)
            ->exists();

        if (! $hasSections) {
            throw ValidationException::withMessages([
                'sections' => __('showcase.publish_requires_sections'),
            ]);
        }

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
            'new_values' => ['status' => $showcase->status->value, 'published_at' => $showcase->published_at->toIso8601String()],
        ]);

        return $showcase;
    }
}
